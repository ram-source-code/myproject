# End-to-end functional + security checks. Run against a FRESH seeded database:
#   rm -f data/aqarat.sqlite* && ADMIN_PASSWORD=Admin12345! php bin/seed.php --demo
#   serve the app (e.g. under /aqarat/) and set BASE_URL, then:
#   pip install requests pillow && python3 tests/e2e.py
import io, os, re, sys, base64, requests
from PIL import Image
B = os.environ.get('BASE_URL', 'http://127.0.0.1:8081/aqarat').rstrip('/')
ADMIN_PW = os.environ.get('ADMIN_PASSWORD', 'Admin12345!')
PREFIX = re.sub(r'^https?://[^/]+', '', B)          # e.g. /aqarat
fails = 0

def ok(c, m):
    global fails
    print(('PASS ' if c else 'FAIL ') + m)
    fails += 0 if c else 1

def sess():
    s = requests.Session()
    s.cookies.set('lang', 'en', path=PREFIX + '/')  # stable English strings for assertions
    return s

def tok(html): return re.search(r'name="_csrf" value="([^"]+)"', html).group(1)
def meta_tok(html): return re.search(r'name="csrf-token" content="([^"]+)"', html).group(1)
def flashes(html): return re.findall(r'class="alert alert-(\w+)">.*?<p>(.*?)</p>', html, re.S)
def login(s, email, pw):
    t = tok(s.get(B + '/login').text)
    return s.post(B + '/login', data={'_csrf': t, 'email': email, 'password': pw})
def img(fmt, size=(1200, 800), mode='RGB'):
    b = io.BytesIO(); Image.new(mode, size, (200, 100, 50, 128) if mode == 'RGBA' else (200, 100, 50)).save(b, fmt); return b.getvalue()

# ── Base path & PWA
anon = sess()
r = anon.get(B + '/')
ok(r.status_code == 200 and f'href="{PREFIX}/properties' in r.text, 'home renders with base-path links')
ok(f'path={PREFIX}/' in r.headers.get('Set-Cookie', ''), 'session cookie scoped to install folder')
m = anon.get(B + '/manifest.webmanifest').json()
ok(m['start_url'].startswith(PREFIX + '/') and m['scope'] == PREFIX + '/' and m['display'] == 'standalone', 'manifest scoped to folder')
ok(anon.get(B + '/sw.js').status_code == 200 and anon.get(B + '/offline').status_code == 200, 'service worker + offline page served')
for p in ['/app/config.php', '/data/aqarat.sqlite', '/data/vapid.json', '/README.md', '/bin/seed.php']:
    ok(anon.get(B + p).status_code in (403, 404), f'internal file blocked: {p}')

# ── Languages
for code, needle in [('ckb', 'dir="rtl"'), ('ar', 'lang="ar"'), ('en', 'dir="ltr"')]:
    s = requests.Session()
    s.get(B + '/lang/' + code, allow_redirects=False)
    ok(needle in s.get(B + '/').text, f'language {code}')

# ── Search, map, currency
r = anon.get(B + '/properties', params={'purpose': 'sale', 'min_price': 100000, 'sort': 'price_desc'})
prices = [int(x.replace(',', '')) for x in re.findall(r'class="card-price">\$([\d,]+)', r.text)]
ok(prices and prices == sorted(prices, reverse=True) and min(prices) >= 100000, f'USD filter+sort ({len(prices)} results)')
ok('IQD' in anon.get(B + '/properties', params={'purpose': 'rent'}).text, 'IQD-priced listings displayed')
mp = anon.get(B + '/api/map', params={'city': 1}).json()
ok(mp['ok'] and mp['items'] and all(i['url'].startswith(PREFIX + '/property/') for i in mp['items']), 'map API returns pins with base-path URLs')
ok('data-map-results' in anon.get(B + '/properties?view=map').text, 'map view renders')
ok(anon.get(B + '/properties', params={'q': 'عەنکاوە'}).status_code == 200, 'Kurdish full-text search')

# ── Offices
r = anon.get(B + '/offices')
ok('office/1' in r.text, 'office directory')
r = anon.get(B + '/office/1')
ok('data-follow-office="1"' in r.text and 'wa.me/964' in r.text, 'office page: follow button + WhatsApp link (+964)')

# ── Registration as office, listing with images
o = sess()
t = tok(o.get(B + '/register').text)
r = o.post(B + '/register', data={'_csrf': t, 'name': 'Test Office', 'email': 'office@example.com', 'phone': '0750 123 4567', 'role': 'agent',
                                   'office_name': '', 'password': 'secret123', 'password_confirm': 'secret123'})
ok('Enter your office name' in r.text, 'office registration requires office name')
t = tok(r.text)
r = o.post(B + '/register', data={'_csrf': t, 'name': 'Test Office', 'email': 'office@example.com', 'phone': '0750 123 4567', 'role': 'agent',
                                   'office_name': 'Test Realty', 'password': 'secret123', 'password_confirm': 'secret123'})
ok(r.url.endswith('/dashboard/office') and 'Test Realty' in r.text, 'office account + office profile created')
t = tok(r.text)
r = o.post(B + '/dashboard/office', data={'_csrf': t, 'name': 'Test Realty', 'about': 'x', 'phone': '07501234567', 'whatsapp': '', 'address': '', 'city_id': '1'},
           files={'logo': ('logo.png', img('PNG', (300, 300)), 'image/png')})
ok('/uploads/' in r.text and '.webp' in r.text, 'office logo uploaded as WebP')
office_id = re.search(r'/office/(\d+)"', r.text).group(1)

t = tok(o.get(B + '/dashboard/properties/new').text)
files = [('images[]', ('a.jpg', img('JPEG'), 'image/jpeg')), ('images[]', ('evil.jpg', b'<?php system($_GET["c"]); ?>', 'image/jpeg'))]
data = {'_csrf': t, 'title': 'Test house in Ankawa', 'purpose': 'sale', 'type': 'house', 'price': '٢٥٠٠٠٠', 'currency': 'USD', 'city_id': '1',
        'district': 'Ankawa', 'project': 'Dream City', 'deed': 'tapo', 'area': '250', 'bedrooms': '4', 'bathrooms': '3',
        'lat': '36.2300', 'lng': '43.9900', 'features[]': ['generator', 'solar'], 'description': '<script>alert(1)</script>'}
r = o.post(B + '/dashboard/properties', data=data, files=files)
pid = re.search(r'/dashboard/properties/(\d+)/edit', r.url).group(1)
ok(any('evil.jpg' in f[1] for f in flashes(r.text)), 'non-image upload rejected')
ok(anon.get(B + '/property/' + pid).status_code == 404, 'new office listing pending (hidden)')

# ── Follow via push subscription (simulated browser) + SSRF guard
def fake_sub(endpoint):
    from cryptography.hazmat.primitives.asymmetric import ec
    from cryptography.hazmat.primitives import serialization
    k = ec.generate_private_key(ec.SECP256R1())
    pub = k.public_key().public_bytes(serialization.Encoding.X962, serialization.PublicFormat.UncompressedPoint)
    b64 = lambda b: base64.urlsafe_b64encode(b).rstrip(b'=').decode()
    return {'endpoint': endpoint, 'keys': {'p256dh': b64(pub), 'auth': b64(os.urandom(16))}}
try:
    fake_sub('x'); have_crypto = True
except Exception:
    have_crypto = False
if have_crypto:
    f = sess(); html = f.get(B + '/office/' + office_id).text; h = {'X-CSRF-Token': meta_tok(html), 'Accept': 'application/json'}
    bad = f.post(B + '/push/follow', json={'office_id': int(office_id), 'subscription': fake_sub('https://127.0.0.1/steal')}, headers=h)
    ok(bad.status_code == 422, 'SSRF: non-push-service endpoint rejected')
    ok(f.post(B + '/push/follow', json={'office_id': int(office_id), 'subscription': fake_sub('https://fcm.googleapis.com/fcm/send/x')}).status_code == 419, 'follow requires CSRF token')
    sub = fake_sub('https://fcm.googleapis.com/fcm/send/e2e-test-' + os.urandom(4).hex())
    r = f.post(B + '/push/follow', json={'office_id': int(office_id), 'subscription': sub}, headers=h).json()
    ok(r.get('following') is True and r.get('followers') == 1, 'device follows office (no account needed)')
    st = f.post(B + '/push/status', json={'endpoint': sub['endpoint']}, headers=h).json()
    ok([x['id'] for x in st['offices']] == [int(office_id)], 'status lists followed office')
    # office sends a notification to its followers
    html = o.get(B + '/dashboard/notifications').text
    ok('1 followers' in html or 'Send to 1 followers' in html, 'office sees follower count')
    r = o.post(B + '/dashboard/notifications', data={'_csrf': tok(html), 'title': 'New homes in Ankawa', 'body': 'Three new houses this week', 'property_id': ''})
    ok(any('Notification sent to' in m for _, m in flashes(r.text)), 'office notification dispatched: ' + '; '.join(m for _, m in flashes(r.text)))
    st = f.post(B + '/push/status', json={'endpoint': sub['endpoint']}, headers=h).json()
    ok(any('New homes in Ankawa' in n['title'] for n in st['feed']) or not st.get('subscribed'), 'notification recorded in follower feed (or expired sub cleaned up)')
    r = f.post(B + '/push/unfollow', json={'office_id': int(office_id), 'endpoint': sub['endpoint']}, headers=h).json()
    ok(r.get('following') is False, 'unfollow')
else:
    print('SKIP push tests (pip install cryptography)')

# ── Saved-search alerts: one matching device, one non-matching device
if have_crypto:
    g = sess(); h = {'X-CSRF-Token': meta_tok(g.get(B + '/properties').text), 'Accept': 'application/json'}
    sub_match = fake_sub('https://fcm.googleapis.com/fcm/send/e2e-match-' + os.urandom(4).hex())
    sub_other = fake_sub('https://fcm.googleapis.com/fcm/send/e2e-other-' + os.urandom(4).hex())
    r = g.post(B + '/push/search', json={'subscription': sub_match, 'query': {'purpose': 'sale', 'type': 'house', 'city': '1', 'max_price': '300000', 'sort': 'price_asc'}}, headers=h).json()
    ok(r.get('ok') and 'House' in r.get('label', '') and '$300,000' in r.get('label', ''), 'saved search stored with readable label: ' + r.get('label', ''))
    g.post(B + '/push/search', json={'subscription': sub_other, 'query': {'type': 'land', 'city': '2'}}, headers=h)
    st = g.post(B + '/push/status', json={'endpoint': sub_match['endpoint']}, headers=h).json()
    ok(len(st.get('searches', [])) == 1 and st['searches'][0]['url'].startswith(PREFIX + '/properties?'), 'status lists saved search with link')
    ok(g.post(B + '/push/search', json={'subscription': fake_sub('https://evil.example/x'), 'query': {}}, headers=h).status_code == 422, 'saved search rejects non-push endpoint')

# ── Authorization
other = sess()
t = tok(other.get(B + '/register').text)
other.post(B + '/register', data={'_csrf': t, 'name': 'Other', 'email': 'other@example.com', 'role': 'user', 'password': 'secret123', 'password_confirm': 'secret123'})
ok(other.get(B + f'/dashboard/properties/{pid}/edit').status_code == 404, 'IDOR: cannot edit another office listing')
ok(other.get(B + '/admin').status_code == 403, 'non-admin blocked from /admin')
r = other.get(B + '/dashboard/notifications')
ok('/dashboard/notifications' not in r.url or 'real estate office accounts' in r.text, 'regular users cannot send notifications')
ok(other.post(B + '/dashboard/notifications', data={'_csrf': meta_tok(r.text), 'title': 'spam', 'body': 'spam spam'}).url.endswith('/dashboard'), 'regular user POST to notify redirected')

# ── Admin approves → auto notification once
a = sess(); r = login(a, 'admin@aqarat.local', ADMIN_PW); ok('/dashboard' in r.url, 'admin login')
t = tok(a.get(B + '/admin').text)
a.post(B + f'/admin/properties/{pid}/status', data={'_csrf': t, 'status': 'published'})
pr = anon.get(B + '/property/' + pid)
ok(pr.status_code == 200 and '&lt;script&gt;' in pr.text and '<script>alert' not in pr.text, 'approved listing public; XSS escaped')
ok('$250,000' in pr.text and 'Dream City' in pr.text and 'Tapo' in pr.text, 'Kurdish digits parsed; project + deed shown')
ok('wa.me/9647501234567' in pr.text, 'listing WhatsApp uses office number in +964 format')
if have_crypto:
    st_m = g.post(B + '/push/status', json={'endpoint': sub_match['endpoint']}, headers=h).json()
    st_o = g.post(B + '/push/status', json={'endpoint': sub_other['endpoint']}, headers=h).json()
    ok(st_m.get('subscribed') is False, 'matching saved search was pushed (fake FCM token → 410 → cleaned up)')
    ok(st_o.get('subscribed') is True and len(st_o['searches']) == 1, 'non-matching saved search untouched')

# ── SEO
ok(re.search(r'/property/\d+-[^"?]+', pr.url) is not None, 'listing redirected to canonical slug URL: ' + pr.url[len(B):][:40])
ok('application/ld+json' in pr.text and '"RealEstateListing"' in pr.text, 'JSON-LD structured data')
ok('hreflang="ckb"' in pr.text and 'hreflang="ar"' in pr.text and 'property="og:image"' in pr.text, 'hreflang + Open Graph tags')
og = anon.get(B + f'/og/{pid}.jpg')
ok(og.status_code == 200 and og.headers['Content-Type'] == 'image/jpeg' and Image.open(io.BytesIO(og.content)).size == (1200, 630), 'share preview image 1200×630')
sm = anon.get(B + '/sitemap.xml').text
ok('<urlset' in sm and f'/property/{pid}-' in sm and '/city/erbil' in sm, 'sitemap lists listings and city pages')
ok('Property for sale in Erbil' in anon.get(B + '/city/erbil?purpose=sale').text, 'city landing page')
home = anon.get(B + '/').text
ok(not re.search(r'(src|href)="https?://(?!127\.0\.0\.1)', home.split('<body')[0]) and 'googleapis' not in home, 'no external fonts/scripts/styles in <head>')
ok('fonts/noto-sans-arabic-wght.woff2' in anon.get(B + '/assets/css/app.css').text and anon.get(B + '/assets/fonts/noto-sans-arabic-wght.woff2').status_code == 200, 'self-hosted @font-face')

# ── Report a listing
rp = sess(); html = rp.get(pr.url).text
r = rp.post(B + f'/property/{pid}/report', data={'_csrf': tok(html), 'reason': 'wrong_price', 'details': 'price is 200k'})
ok(any('Thank you' in m for _, m in flashes(r.text)), 'report submitted')
ok('price is 200k' in a.get(B + '/admin/reports').text, 'admin sees report')
ok('Possible duplicates' in a.get(B + '/admin/reports').text, 'duplicates section on admin reports page')
html = a.get(B + '/admin/notifications').text
ok('Broadcast' in html, 'admin notifications page')
r = a.post(B + '/admin/notifications', data={'_csrf': tok(html), 'target': 'all', 'title': 'Welcome', 'body': 'Aqarat is live', 'property_id': ''})
ok(any('Notification sent' in m for _, m in flashes(r.text)), 'admin broadcast')
t = tok(a.get(B + '/admin/offices').text)
a.post(B + f'/admin/offices/{office_id}/verify', data={'_csrf': t})
ok('Verified' in anon.get(B + '/office/' + office_id).text, 'admin verifies office')

# ── Videos: valid MP4 accepted, disguised script rejected
mp4 = b'\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2' + b'\x00\x00\x00\x08free' + os.urandom(2048)
fake = b'<?php echo 1; ?>' + b'A' * 100
t = tok(o.get(B + f'/dashboard/properties/{pid}/edit').text)
d3 = dict(data); d3['_csrf'] = t
r = o.post(B + f'/dashboard/properties/{pid}', data=d3, files=[('videos[]', ('tour.mp4', mp4, 'video/mp4')), ('videos[]', ('x.mp4', fake, 'video/mp4'))])
ok(any('x.mp4' in m for _, m in flashes(r.text)) and '.mp4#t=0.1' in r.text, 'video upload: MP4 kept, disguised file rejected')
vid = re.search(r'/uploads/([a-f0-9]{32}\.mp4)', r.text).group(1)
ok(anon.get(B + '/uploads/' + vid).status_code == 200, 'video served from uploads')

# ── Moderation: edit after approval returns to review
t = tok(o.get(B + f'/dashboard/properties/{pid}/edit').text)
d2 = dict(data); d2['_csrf'] = t; d2['title'] = 'Edited after approval'
o.post(B + f'/dashboard/properties/{pid}', data=d2)
ok(anon.get(B + '/property/' + pid).status_code == 404, 'edited listing returns to review')

# ── CSRF + throttling
ok(sess().post(B + '/login', data={'email': 'x', 'password': 'y'}).status_code == 419, 'POST without CSRF -> 419')
x = sess()
for _ in range(6):
    r = x.post(B + '/login', data={'_csrf': tok(x.get(B + '/login').text), 'email': 'office@example.com', 'password': 'wrong'})
ok('Too many attempts' in r.text, 'login throttled')

print(f'\n{fails} failure(s)')
sys.exit(1 if fails else 0)
