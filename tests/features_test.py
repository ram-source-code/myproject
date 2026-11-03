# Checks for: office stats, stale-listing check, price drops, payments, daily backups, two-step login,
# neighbourhoods, photo-size limits and asset URLs. Run against a FRESH demo install, after (or instead of) e2e.py:
#   BASE_URL=http://localhost/aqarat APP_DIR=/path/to/aqarat [PHP_AS=www-data] python3 tests/features_test.py
# APP_DIR lets the test backdate a few timestamps (45-day / 7-day rules) and run the daily job directly.
import base64, hashlib, hmac, html as H, json, os, re, struct, subprocess, sys, time, requests

B = os.environ.get('BASE_URL', 'http://127.0.0.1:8081/aqarat').rstrip('/')
APP = os.environ.get('APP_DIR', os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PHP_AS = os.environ.get('PHP_AS', '')
ADMIN_PW = os.environ.get('ADMIN_PASSWORD', 'Admin12345!')
PREFIX = re.sub(r'^https?://[^/]+', '', B)
fails = 0


def ok(c, m):
    global fails
    print(('PASS ' if c else 'FAIL ') + m)
    fails += 0 if c else 1


def sess():
    s = requests.Session()
    s.cookies.set('lang', 'en', path=PREFIX + '/')
    return s


def tok(t): return re.search(r'name="_csrf" value="([^"]+)"', t).group(1)
def meta_tok(t): return re.search(r'name="csrf-token" content="([^"]+)"', t).group(1)


def login(s, email, pw):
    return s.post(B + '/login', data={'_csrf': tok(s.get(B + '/login').text), 'email': email, 'password': pw})


def php(code):
    """Run PHP inside the app (as the web server user) and return its JSON output."""
    # direct database edits don't go through a POST, so also tell the page cache that something changed
    full = 'define("AQARAT_CLI", true); require ' + json.dumps(APP + '/app/bootstrap.php') + '; page_cache_bump(); ' + code
    cmd = (['sudo', '-u', PHP_AS] if PHP_AS else []) + ['php', '-r', full]
    out = subprocess.run(cmd, capture_output=True, text=True, cwd=APP).stdout.strip().splitlines()
    return json.loads(out[-1]) if out else None


def totp(secret, t=None):
    key = base64.b32decode(secret + '=' * (-len(secret) % 8))
    h = hmac.new(key, struct.pack('>Q', int((t or time.time()) // 30)), hashlib.sha1).digest()
    o = h[19] & 15
    return '%06d' % ((struct.unpack('>I', h[o:o + 4])[0] & 0x7fffffff) % 1000000)


anon = sess()
adm = sess(); login(adm, 'admin@aqarat.local', ADMIN_PW)
office = php("echo json_encode(db()->query(\"SELECT u.id uid, u.email, o.id oid FROM users u JOIN offices o ON o.user_id = u.id WHERE u.role = 'agent' AND o.activated_at IS NOT NULL ORDER BY u.id LIMIT 1\")->fetch());")
o = sess(); r = login(o, office['email'], 'demo-password')
ok('/dashboard' in r.url, 'demo office signs in')
pid = php("echo json_encode((int) db()->query(\"SELECT id FROM properties WHERE status = 'published' AND user_id = " + str(office['uid']) + " ORDER BY id LIMIT 1\")->fetchColumn());")

# ── Office stats: views, WhatsApp/Call taps (once per visitor, never the owner), enquiries
page = anon.get(B + f'/property/{pid}').text
ok('data-track-contact="wa"' in page and 'data-track-contact="calls"' in page, 'listing has tracked WhatsApp + Call buttons')
for _ in range(3):
    anon.post(B + f'/property/{pid}/contact', data={'_csrf': meta_tok(page), 'kind': 'wa'})
anon.post(B + f'/property/{pid}/contact', data={'_csrf': meta_tok(page), 'kind': 'calls'})
anon.post(B + f'/property/{pid}/contact', data={'_csrf': meta_tok(page), 'kind': 'evil'})
op = o.get(B + f'/property/{pid}').text
o.post(B + f'/property/{pid}/contact', data={'_csrf': meta_tok(op), 'kind': 'wa'})
st = php(f"echo json_encode(db()->query('SELECT SUM(views) v, SUM(wa) wa, SUM(calls) c FROM property_stats WHERE property_id = {pid}')->fetch());")
ok(st['wa'] == 1 and st['c'] == 1 and st['v'] >= 1, f'taps counted once per visitor, owner and bogus kinds ignored ({st})')
dash = o.get(B + '/dashboard').text
ok('Last 30 days' in dash and 'Contacts (30 days)' in dash, 'office dashboard shows 30-day stats')

# ── Price drop: badge on the card + listing page, previous price kept
row = php(f"echo json_encode(db()->query('SELECT * FROM properties WHERE id = {pid}')->fetch());")
edit = o.get(B + f'/dashboard/properties/{pid}/edit').text
form = {'_csrf': tok(edit), 'title': row['title'], 'description': row['description'], 'purpose': row['purpose'], 'type': row['type'],
        'city_id': row['city_id'], 'district': row['district'], 'price': int(row['price'] * 0.9), 'currency': row['currency'],
        'area': row['area'], 'bedrooms': row['bedrooms'], 'bathrooms': row['bathrooms'], 'rent_period': row['rent_period'] or ''}
o.post(B + f'/dashboard/properties/{pid}', data=form)
after = php(f"echo json_encode(db()->query('SELECT status, price_usd, prev_price_usd, price_dropped_at FROM properties WHERE id = {pid}')->fetch());")
ok(after['prev_price_usd'] == row['price_usd'] and after['price_usd'] < row['price_usd'] and after['price_dropped_at'], 'lower price recorded as a drop')
if after['status'] != 'published':        # require_approval sends edits to review
    adm.post(B + f'/admin/properties/{pid}/status', data={'_csrf': tok(adm.get(B + '/admin').text), 'status': 'published'})
pp = anon.get(B + f'/property/{pid}').text
ok('Price dropped 10%' in pp and '<s>' in pp, 'listing page shows the drop and the old price')
ok('badge-drop' in anon.get(B + '/properties').text, 'cards show the price-drop badge')
form['price'] = row['price']; form['_csrf'] = tok(o.get(B + f'/dashboard/properties/{pid}/edit').text)
o.post(B + f'/dashboard/properties/{pid}', data=form)
ok(php(f"echo json_encode(db()->query('SELECT prev_price_usd FROM properties WHERE id = {pid}')->fetchColumn());") is None, 'raising the price clears the badge')
if php(f"echo json_encode(db()->query('SELECT status FROM properties WHERE id = {pid}')->fetchColumn());") != 'published':
    adm.post(B + f'/admin/properties/{pid}/status', data={'_csrf': tok(adm.get(B + '/admin').text), 'status': 'published'})

# ── Stale check: 45 days → reminder, +7 days → hidden; one tap brings it back
php(f"db()->exec(\"UPDATE properties SET confirmed_at = datetime('now','-50 days'), stale_since = NULL WHERE id = {pid}\"); echo 1;")
php("maintenance_work(); echo 1;")
d = o.get(B + '/dashboard').text
ok('Are these still available?' in d and f'/dashboard/properties/{pid}/confirm' in d, 'after 45 days the office is asked to confirm')
ok(anon.get(B + f'/property/{pid}').status_code == 200, 'still public during the 7-day grace period')
php(f"db()->exec(\"UPDATE properties SET stale_since = datetime('now','-8 days') WHERE id = {pid}\"); echo 1;")
php("maintenance_work(); echo 1;")
ok(php(f"echo json_encode(db()->query('SELECT status FROM properties WHERE id = {pid}')->fetchColumn());") == 'expired', 'hidden (expired) after the grace period')
ok(anon.get(B + f'/property/{pid}').status_code == 404 and f'/property/{pid}-' not in anon.get(B + '/properties').text, 'expired listing is not public')
d = o.get(B + '/dashboard').text
o.post(B + '/dashboard/properties/confirm-all', data={'_csrf': tok(d)})
ok(php(f"echo json_encode(db()->query('SELECT status FROM properties WHERE id = {pid}')->fetchColumn());") == 'published', '"All still available" brings it back in one tap')

# ── Payments recorded on activation
ap = adm.get(B + '/admin/offices').text
adm.post(B + f"/admin/offices/{office['oid']}/activate", data={'_csrf': tok(ap), 'months': '3', 'amount': '٥٠٠٠٠', 'currency': 'IQD'})
ap = adm.get(B + '/admin/offices').text
ok('50,000' in ap and 'Recent payments' in ap, 'payment amount (Eastern Arabic digits) logged and totalled')

# ── Daily backups
bk = php("echo json_encode(glob(backups_dir() . '/aqarat-*.sqlite'));")
ok(len(bk) == 1, 'daily job wrote one backup')
name = os.path.basename(bk[0])
r = adm.post(B + '/admin/backups/download', data={'_csrf': tok(adm.get(B + '/admin').text), 'file': name})
ok(r.status_code == 200 and r.content[:15] == b'SQLite format 3', 'admin downloads the backup')
r = adm.post(B + '/admin/backups/download', data={'_csrf': tok(adm.get(B + '/admin').text), 'file': '../config.local.php'})
ok(r.status_code == 404, 'backup download refuses other paths')
ok(anon.get(B + '/data/backups/' + name).status_code in (403, 404), 'backups are not reachable over the web')
for i in range(8):
    php(f"copy(backups_dir() . '/{name}', backups_dir() . '/aqarat-2020-01-0{i + 1}.sqlite'); echo 1;")
php("maintenance_backup(db()); echo 1;")
ok(len(php("echo json_encode(glob(backups_dir() . '/aqarat-*.sqlite'));")) == 7, 'only the newest 7 backups are kept')

# ── Neighbourhoods: add (links typed listings), filter, form matching in any language
city, dist = php(f"$r = db()->query('SELECT city_id, district FROM properties WHERE id = {pid}')->fetch(); echo json_encode([(int) $r['city_id'], $r['district']]);")
dp = adm.get(B + f'/admin/cities/{city}/districts').text
ok(H.escape(dist) in dp or dist in dp, 'typed neighbourhood is suggested to the admin')
r = adm.post(B + '/admin/districts', data={'_csrf': tok(dp), 'city_id': city, 'name_ku': dist, 'name_ar': dist, 'name_en': 'Test Quarter'})
did = php(f"echo json_encode((int) db()->query(\"SELECT id FROM districts WHERE name_en = 'Test Quarter'\")->fetchColumn());")
ok(did and php(f"echo json_encode((int) db()->query('SELECT district_id FROM properties WHERE id = {pid}')->fetchColumn());") == did, 'adding it links existing listings')
fl = anon.get(B + f'/properties?city={city}&district={did}').text
ok(f'/property/{pid}-' in fl and 'Test Quarter' in fl, 'filter by neighbourhood works and shows the English name')
form['district'] = 'test quarter'; form['_csrf'] = tok(o.get(B + f'/dashboard/properties/{pid}/edit').text)
php(f"db()->exec('UPDATE properties SET district_id = NULL WHERE id = {pid}'); echo 1;")
o.post(B + f'/dashboard/properties/{pid}', data=form)
ok(php(f"echo json_encode((int) db()->query('SELECT district_id FROM properties WHERE id = {pid}')->fetchColumn());") == did, 'typing the English name in any case matches the neighbourhood')
ok('data-districts=' in o.get(B + '/dashboard/properties/new').text, 'listing form offers neighbourhood suggestions')

# ── Two-step login
a2 = sess(); login(a2, 'admin@aqarat.local', ADMIN_PW)
setup = a2.get(B + '/dashboard/2fa').text
secret = re.search(r'data-copy="([A-Z2-7]{32})"', setup).group(1)
ok('data-qr="otpauth://totp/' in setup and 'qrcode.js' in setup, 'setup page has the QR code (self-hosted library)')
r = a2.post(B + '/dashboard/2fa', data={'_csrf': tok(setup), 'code': '000000'})
ok('Wrong code' in r.text, 'wrong confirmation code refused')
r = a2.post(B + '/dashboard/2fa', data={'_csrf': tok(r.text), 'code': totp(secret)})
codes = re.findall(r'<li><code>([a-z2-7]{4}-[a-z2-7]{4})</code></li>', r.text)
ok(len(codes) == 8 and 'two-step login is on' in r.text.lower(), 'turned on; 8 recovery codes shown once')
ok('/dashboard/2fa/codes' not in a2.get(B + '/dashboard/2fa/codes').url, 'recovery codes cannot be viewed again')
b = sess(); r = login(b, 'admin@aqarat.local', ADMIN_PW)
ok(r.url.endswith('/login/code') and b.get(B + '/admin').status_code in (302, 401, 403) or '/login' in b.get(B + '/admin').url, 'password alone does not sign in')
r = b.post(B + '/login/code', data={'_csrf': tok(b.get(B + '/login/code').text), 'code': '123456'})
ok('Wrong code' in r.text, 'wrong code refused at sign-in')
time.sleep(31 - time.time() % 30)       # a fresh 30-second window, so the code was not used during setup
c = totp(secret)
r = b.post(B + '/login/code', data={'_csrf': tok(b.get(B + '/login/code').text), 'code': c})
ok('/dashboard' in r.url and 'Welcome back' in r.text, 'right code signs in')
b2 = sess(); login(b2, 'admin@aqarat.local', ADMIN_PW)
r = b2.post(B + '/login/code', data={'_csrf': tok(b2.get(B + '/login/code').text), 'code': c})
ok('Wrong code' in r.text, 'the same code cannot be used twice')
r = b2.post(B + '/login/code', data={'_csrf': tok(r.text), 'code': codes[0].upper()})
ok('/dashboard' in r.url and '7 left' in r.text, 'a recovery code signs in (any case) and is used up')
b3 = sess(); login(b3, 'admin@aqarat.local', ADMIN_PW)
r = b3.post(B + '/login/code', data={'_csrf': tok(b3.get(B + '/login/code').text), 'code': codes[0]})
ok('Wrong code' in r.text, 'a used recovery code is refused')
pr = b2.get(B + '/dashboard/profile').text
r = b2.post(B + '/dashboard/2fa/disable', data={'_csrf': tok(pr), 'password': 'wrong'})
ok(php("echo json_encode((bool) db()->query(\"SELECT totp_secret FROM users WHERE email = 'admin@aqarat.local'\")->fetchColumn());"), 'turning it off needs the password')
b2.post(B + '/dashboard/2fa/disable', data={'_csrf': tok(b2.get(B + '/dashboard/profile').text), 'password': ADMIN_PW})
ok(login(sess(), 'admin@aqarat.local', ADMIN_PW).url.rstrip('/').endswith('/dashboard'), 'after turning it off the password is enough again')

# ── Uploads + assets
nf = o.get(B + '/dashboard/properties/new').text
ok(re.search(r'data-file-limit="\d+" data-post-limit="\d+"', nf), 'listing form tells the browser the real upload limits')
home = anon.get(B + '/?lang=en').text
ok(re.search(r'rel="preload" href="[^"?]+\.woff2" as="font"', home), 'font preload has no ?lang= (matches the CSS URL)')
r = o.post(B + '/dashboard/office', data={'_csrf': tok(o.get(B + '/dashboard/office').text), 'name': 'x'}, headers={'X-AQ-Upload': '1'})
ok(r.headers.get('Content-Type', '').startswith('application/json') and 'redirect' in r.json(), 'upload requests get the redirect as JSON (flash survives)')

# ── Offices set up in person (default) — public sign-up makes customer accounts only
adm = sess(); login(adm, 'admin@aqarat.local', ADMIN_PW)
adm.post(B + '/admin/offices/signup-mode', data={'_csrf': tok(adm.get(B + '/admin/offices').text), 'mode': 'admin'})
rp = sess().get(B + '/register').text
ok('value="agent"' not in rp and 'visit you in person' in rp, 'sign-up offers no office account; explains the in-person visit')
g = sess(); rp = g.get(B + '/register').text; time.sleep(3.3)
g.post(B + '/register', data={'_csrf': tok(rp), '_ft': re.search(r'name="_ft" value="([^"]+)"', rp).group(1), 'name': 'Sneaky', 'email': 'sneaky@example.com',
                              'role': 'agent', 'office_name': 'Fake Realty', 'password': 'secret123', 'password_confirm': 'secret123'})
ok(php("echo json_encode(db()->query(\"SELECT role FROM users WHERE email = 'sneaky@example.com'\")->fetchColumn());") == 'user'
   and not php("echo json_encode((bool) db()->query(\"SELECT 1 FROM offices WHERE name = 'Fake Realty'\")->fetchColumn());"), 'posting role=agent still makes a customer account')
ap = adm.get(B + '/admin/offices').text
r = adm.post(B + '/admin/offices/new', data={'_csrf': tok(ap), 'office_name': 'Zagros Realty', 'owner_name': 'Shwan', 'phone': '0750 987 6543',
                                              'city_id': '1', 'months': '3', 'amount': '90000', 'currency': 'IQD', 'visited': '1'})
temp = re.search(r'class="activation-code">([a-z2-9-]+)<', r.text)
ok(temp and 'wa.me/9647509876543?text=' in r.text, 'admin creates the office in person: temporary password + WhatsApp message')
r = adm.post(B + '/admin/offices/new', data={'_csrf': tok(adm.get(B + '/admin/offices').text), 'office_name': 'Dup', 'owner_name': 'Dup', 'phone': '+964 750 987 6543'})
ok(php("echo json_encode((int) db()->query(\"SELECT COUNT(*) FROM offices WHERE name = 'Dup'\")->fetchColumn());") == 0, 'the same phone cannot get a second account')
z = sess(); r = login(z, '٠٧٥٠ ٩٨٧ ٦٥٤٣', temp.group(1))
ok(r.url.endswith('/dashboard/profile#password') or r.url.endswith('/dashboard/profile'), 'office signs in with its phone (Eastern Arabic digits) and is asked for its own password')
ok('/dashboard/profile' in z.get(B + '/dashboard/properties/new').url, 'nothing else opens until the password is changed')
z.post(B + '/dashboard/password', data={'_csrf': tok(z.get(B + '/dashboard/profile').text), 'current_password': temp.group(1), 'password': 'zagros-own-99', 'password_confirm': 'zagros-own-99'})
ok(z.get(B + '/dashboard/properties/new').url.endswith('/dashboard/properties/new'), 'after choosing a password the office can post (active, paid 3 months)')
zoid = php("echo json_encode((int) db()->query(\"SELECT id FROM offices WHERE name = 'Zagros Realty'\")->fetchColumn());")
op = sess().get(B + f'/office/{zoid}').text
ok('visited this office in person on' in op, 'public page: verified, visited in person, with the date')
r = adm.post(B + f'/admin/offices/{zoid}/password', data={'_csrf': tok(adm.get(B + '/admin/offices').text)})
temp2 = re.search(r'class="activation-code">([a-z2-9-]+)<', r.text).group(1)
ok('/login' in login(sess(), '07509876543', 'zagros-own-99').url and '/dashboard/profile' in login(sess(), '07509876543', temp2).url,
   'forgotten password: admin makes a new temporary one, the old password stops working')
adm.post(B + '/admin/offices/signup-mode', data={'_csrf': tok(adm.get(B + '/admin/offices').text), 'mode': 'open'})
ok('value="agent"' in sess().get(B + '/register').text, 'switching to "anyone can sign up" brings the office option back')
adm.post(B + '/admin/offices/signup-mode', data={'_csrf': tok(adm.get(B + '/admin/offices').text), 'mode': 'admin'})

# ── Paid featured listings
fp = php("echo json_encode((int) db()->query(\"SELECT id FROM properties WHERE status = 'published' AND is_featured = 0 ORDER BY id LIMIT 1\")->fetchColumn());")
adm.post(B + f'/admin/properties/{fp}/feature', data={'_csrf': tok(adm.get(B + '/admin/properties').text), 'days': '7', 'amount': '25000', 'currency': 'IQD'})
f1 = php(f"echo json_encode(db()->query('SELECT is_featured, featured_until FROM properties WHERE id = {fp}')->fetch());")
adm.post(B + f'/admin/properties/{fp}/feature', data={'_csrf': tok(adm.get(B + '/admin/properties').text), 'days': '7'})
f2 = php(f"echo json_encode(db()->query('SELECT featured_until FROM properties WHERE id = {fp}')->fetchColumn());")
ok(f1['is_featured'] == 1 and f1['featured_until'] and f2 > f1['featured_until'], 'featured for 7 days; featuring again extends it')
ok(php(f"echo json_encode((int) db()->query(\"SELECT COUNT(*) FROM payments WHERE kind = 'featured' AND property_id = {fp} AND amount = 25000\")->fetchColumn());") == 1
   and '★' in adm.get(B + '/admin/offices').text, 'the feature payment is logged and shown in Payments')
php(f"db()->exec(\"UPDATE properties SET featured_until = datetime('now', '-1 hour') WHERE id = {fp}\"); echo 1;")
php("maintenance_work(); echo 1;")
ok(php(f"echo json_encode((int) db()->query('SELECT is_featured FROM properties WHERE id = {fp}')->fetchColumn());") == 0, 'the daily job ends a feature when its time is up')

# ── Skeletons where content loads after the page
ok('sk-row' in sess().get(B + '/notifications').text, 'notifications lists show skeleton rows while loading')
ok('results-map is-loading' in sess().get(B + '/properties?view=map').text, 'map shows a loading shimmer until pins arrive')

# ── Short cache for signed-out visitors
g1, g2 = sess(), sess()
r1 = g1.get(B + '/offices'); r2 = g2.get(B + '/offices')
ok(r2.headers.get('X-Page-Cache') == 'HIT' and meta_tok(r1.text) != meta_tok(r2.text), 'second visitor gets the cached page with their own security token')
ok(g2.post(B + '/push/status', json={'endpoint': 'x'}, headers={'X-CSRF-Token': meta_tok(r2.text), 'Accept': 'application/json'}).status_code != 403, 'the token in a cached page works')
ok(sess().get(B + '/offices').headers.get('X-Page-Cache') == 'HIT', 'beacons do not empty the cache')
adm.post(B + '/admin/offices/signup-mode', data={'_csrf': tok(adm.get(B + '/admin/offices').text), 'mode': 'admin'})
ok(sess().get(B + '/offices').headers.get('X-Page-Cache') != 'HIT', 'any change empties the cache at once')
ok(adm.get(B + '/offices').headers.get('X-Page-Cache') != 'HIT' and adm.get(B + '/offices').headers.get('X-Page-Cache') != 'HIT', 'signed-in pages are never cached')
lp = php("echo json_encode((int) db()->query(\"SELECT id FROM properties WHERE status = 'published' LIMIT 1\")->fetchColumn());")
ok(all(sess().get(B + f'/property/{lp}').headers.get('X-Page-Cache') != 'HIT' for _ in range(2)), 'listing pages are never cached (views, enquiry form)')
ok(all(sess().get(B + '/properties?q=villa').headers.get('X-Page-Cache') != 'HIT' for _ in range(2)), 'free-text searches are not cached')

# ── Notifications: who hears about a new listing and a price drop
def fake_sub(endpoint):
    from cryptography.hazmat.primitives.asymmetric import ec
    from cryptography.hazmat.primitives import serialization
    k = ec.generate_private_key(ec.SECP256R1())
    pub = k.public_key().public_bytes(serialization.Encoding.X962, serialization.PublicFormat.UncompressedPoint)
    b = lambda x: base64.urlsafe_b64encode(x).rstrip(b'=').decode()
    return {'endpoint': endpoint, 'keys': {'p256dh': b(pub), 'auth': b(os.urandom(16))}}
dev = sess(); h = {'X-CSRF-Token': meta_tok(dev.get(B + '/notifications').text), 'Accept': 'application/json'}
tag = os.urandom(3).hex()
follower = fake_sub(f'https://fcm.googleapis.com/fcm/send/ft-follow-{tag}')
everything = fake_sub(f'https://fcm.googleapis.com/fcm/send/ft-all-{tag}')
dev.post(B + '/push/follow', json={'office_id': office['oid'], 'subscription': follower}, headers=h)
dev.post(B + '/push/search', json={'subscription': everything, 'query': {}}, headers=h)
st = dev.post(B + '/push/status', json={'endpoint': everything['endpoint']}, headers=h).json()
ok(st.get('all_listings') and not st.get('searches'), '"every new listing" is a switch, not a row in saved searches')
alive = lambda: php("echo json_encode(db()->query('SELECT endpoint FROM push_subscriptions')->fetchAll(PDO::FETCH_COLUMN));")
# start from a live listing with no recent price cut (earlier sections leave it in review / just reduced)
php(f"db()->exec(\"UPDATE properties SET status = 'published', prev_price_usd = NULL, price_dropped_at = NULL WHERE id = {pid}\"); echo 1;")
row = php(f"echo json_encode(db()->query('SELECT * FROM properties WHERE id = {pid}')->fetch());")
form = {'_csrf': tok(o.get(B + f'/dashboard/properties/{pid}/edit').text), 'title': row['title'], 'description': row['description'], 'purpose': row['purpose'],
        'type': row['type'], 'city_id': row['city_id'], 'district': row['district'], 'price': int(row['price'] * 0.8), 'currency': row['currency'],
        'area': row['area'], 'bedrooms': row['bedrooms'], 'bathrooms': row['bathrooms'], 'rent_period': row['rent_period'] or '', 'project': row['project'] or '',
        'address': row['address'] or '', 'deed': row['deed'] or '', 'floor': row['floor'] or '', 'year_built': row['year_built'] or '',
        'lat': row['lat'] or '', 'lng': row['lng'] or ''}
for f_ in json.loads(row['features'] or '[]'):
    form.setdefault('features[]', []).append(f_)
if row['furnished']:
    form['furnished'] = '1'
o.post(B + f'/dashboard/properties/{pid}', data=form)
st2 = php(f"echo json_encode(db()->query('SELECT status FROM properties WHERE id = {pid}')->fetchColumn());")
ok(st2 == 'published', f'changing only the price keeps the listing live (no review): {st2}')
drop = php(f"echo json_encode(db()->query(\"SELECT sent + failed FROM notifications WHERE property_id = {pid} AND audience = 'followers' AND url LIKE '%#price' ORDER BY id DESC\")->fetchColumn());")
ok(drop == 1, f'price drop pushed to the office follower ({drop})')
left = alive()
ok(everything['endpoint'] not in left and follower['endpoint'] not in left, 'price drop pushed to the "every new listing" device too (fake tokens are then dropped as expired)')
# renewing a subscription (site keys changed) keeps what the device followed
old = fake_sub(f'https://fcm.googleapis.com/fcm/send/ft-old-{tag}'); new = fake_sub(f'https://fcm.googleapis.com/fcm/send/ft-new-{tag}')
dev.post(B + '/push/follow', json={'office_id': office['oid'], 'subscription': old}, headers=h)
r = dev.post(B + '/push/renew', json={'old_endpoint': old['endpoint'], 'subscription': new}, headers=h)
st = dev.post(B + '/push/status', json={'endpoint': new['endpoint']}, headers=h).json()
ok(r.status_code == 200 and [x['id'] for x in st.get('offices', [])] == [office['oid']] and old['endpoint'] not in alive(), 'renewed subscription keeps the followed office, old one removed')
ok(dev.post(B + '/push/test', json={'endpoint': 'https://fcm.googleapis.com/fcm/send/nobody'}, headers=h).status_code == 404, 'test notification needs this device to be subscribed')
t = dev.post(B + '/push/test', json={'endpoint': new['endpoint']}, headers=h)
ok(t.status_code in (200, 502) and t.json().get('message'), 'test notification reports what the push service said: ' + t.json().get('message', ''))

print(f'\n{fails} failure(s)')
sys.exit(1 if fails else 0)
