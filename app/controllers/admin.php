<?php
declare(strict_types=1);

function admin_home(): void
{
    require_admin();
    $stats = db()->query("SELECT
        (SELECT COUNT(*) FROM users) users,
        (SELECT COUNT(*) FROM properties) properties,
        (SELECT COUNT(*) FROM properties WHERE status='pending') pending,
        (SELECT COUNT(*) FROM properties WHERE status='published') published,
        (SELECT COUNT(*) FROM inquiries) inquiries,
        (SELECT COUNT(*) FROM property_images) images,
        (SELECT COALESCE(SUM(views),0) FROM properties) views,
        (SELECT COUNT(DISTINCT property_id) FROM reports WHERE status = 'open') reported,
        (SELECT COUNT(*) FROM duplicate_flags WHERE dismissed = 0) dups")->fetch();
    $byCity = db()->query("SELECT c.name_ar, c.name_en, c.name_ku, COUNT(p.id) n FROM cities c LEFT JOIN properties p ON p.city_id = c.id AND p.status='published'
        GROUP BY c.id ORDER BY n DESC LIMIT 8")->fetchAll();
    $pending = db()->query(PROPERTY_SELECT . " WHERE p.status='pending' ORDER BY p.created_at ASC LIMIT 10")->fetchAll();
    $journal = db()->query('PRAGMA journal_mode')->fetchColumn();
    $log = db()->query('SELECT l.*, u.name FROM admin_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 30')->fetchAll();

    render('admin/index', [
        'pageTitle' => t('admin') . ' — ' . app_name(),
        'stats'     => $stats,
        'byCity'    => $byCity,
        'pending'   => $pending,
        'journal'   => $journal,
        'log'       => $log,
        'active'    => 'admin',
    ]);
}

function admin_properties(): void
{
    require_admin();
    $status = in_array($_GET['status'] ?? '', PROPERTY_STATUSES, true) ? $_GET['status'] : '';
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $where = ['1=1'];
    $args = [];
    if ($status) {
        $where[] = 'p.status = ?';
        $args[] = $status;
    }
    if ($q !== '' && ($m = fts_query($q)) !== '') {
        $where[] = 'p.id IN (SELECT rowid FROM properties_fts WHERE properties_fts MATCH ?)';
        $args[] = $m;
    }
    $w = implode(' AND ', $where);
    $cnt = db()->prepare("SELECT COUNT(*) FROM properties p WHERE $w");
    $cnt->execute($args);
    $pg = paginate((int) $cnt->fetchColumn(), 30, max(1, input_int('page', 1, $_GET) ?? 1));
    $stmt = db()->prepare(PROPERTY_SELECT . " WHERE $w ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([...$args, $pg['per_page'], $pg['offset']]);

    render('admin/properties', [
        'pageTitle' => t('admin_listings') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'pg'        => $pg,
        'status'    => $status,
        'q'         => $q,
        'active'    => 'admin_listings',
    ]);
}

function admin_property_status(int $id): void
{
    require_admin();
    $status = input('status');
    if (!in_array($status, PROPERTY_STATUSES, true)) {
        abort(400);
    }
    db()->prepare("UPDATE properties SET status = ?, updated_at = datetime('now') WHERE id = ?")->execute([$status, $id]);
    admin_log('listing.status', "#$id → $status");
    if ($status === 'published' && ($p = find_property($id))) {
        notify_listing_published($p); // tell the office's followers (once per listing)
    }
    flash('success', t('status_changed'));
    back('/admin/properties');
}

const FEATURE_DAYS = [7, 14, 30];      // plus 0 = until you stop it

/** Feature a listing (a paid extra): for N days, extending a running feature; or stop it. Payment optional. */
function admin_property_feature(int $id): void
{
    $admin = require_admin();
    $p = find_property($id) ?? abort(404);
    if (input('action') === 'stop') {
        db()->prepare('UPDATE properties SET is_featured = 0, featured_until = NULL WHERE id = ?')->execute([$id]);
        admin_log('listing.unfeature', "#$id");
        flash('success', t('feature_stopped'));
        back('/admin/properties');
    }
    $days = (int) input('days');
    $days = in_array($days, FEATURE_DAYS, true) ? $days : null;
    if ($days === null) {
        db()->prepare('UPDATE properties SET is_featured = 1, featured_until = NULL WHERE id = ?')->execute([$id]);
    } else {
        db()->prepare("UPDATE properties SET is_featured = 1,
            featured_until = datetime(CASE WHEN is_featured = 1 AND featured_until > datetime('now') THEN featured_until ELSE datetime('now') END, '+' || ? || ' days')
            WHERE id = ?")->execute([$days, $id]);
    }
    $amount = max(0, (int) convert_arabic_digits((string) ($_POST['amount'] ?? '')));
    if ($amount > 0) {
        db()->prepare("INSERT INTO payments (office_id, office_name, amount, currency, months, created_by, kind, property_id) VALUES (?,?,?,?, NULL, ?, 'featured', ?)")
            ->execute([$p['office_id'] ?: null, ($p['office_name'] ?: $p['owner_name']) . ' — ' . mb_substr($p['title'], 0, 60), $amount,
                input('currency') === 'USD' ? 'USD' : 'IQD', $admin['id'], $id]);
    }
    admin_log('listing.feature', "#$id " . ($days ? "{$days}d" : 'no end'));
    flash('success', t('feature_started'));
    back('/admin/properties');
}

function admin_users(): void
{
    require_admin();
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $args = [];
    $where = '1=1';
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where = "(u.name LIKE ? ESCAPE '\\' OR u.email LIKE ? ESCAPE '\\')";
        $args = [$like, $like];
    }
    $stmt = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM properties p WHERE p.user_id = u.id) listings
        FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT 300");
    $stmt->execute($args);
    render('admin/users', [
        'pageTitle' => t('admin_users') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'q'         => $q,
        'active'    => 'admin_users',
    ]);
}

function admin_user_update(int $id): void
{
    $me = require_admin();
    if ($id === (int) $me['id']) {
        flash('error', t('cannot_edit_self'));
        redirect(url('admin/users'));
    }
    $role = input('role');
    $status = input('status');
    if (!in_array($role, ['user', 'agent', 'admin'], true) || !in_array($status, ['active', 'banned'], true)) {
        abort(400);
    }
    db()->prepare('UPDATE users SET role = ?, status = ? WHERE id = ?')->execute([$role, $status, $id]);
    admin_log('user.update', "#$id role=$role status=$status");
    flash('success', t('user_saved'));
    redirect(url('admin/users'));
}

function admin_cities(): void
{
    require_admin();
    $rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM properties p WHERE p.city_id = c.id) n FROM cities c ORDER BY c.name_en')->fetchAll();
    render('admin/cities', ['pageTitle' => t('admin_cities') . ' — ' . app_name(), 'items' => $rows, 'active' => 'admin_cities']);
}

function admin_city_create(): void
{
    require_admin();
    $ku = mb_substr(input('name_ku'), 0, 80);
    $ar = mb_substr(input('name_ar'), 0, 80);
    $en = mb_substr(input('name_en'), 0, 80);
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($en)), '-');
    if ($ar === '' || $en === '' || $slug === '') {
        with_old(compact('ar', 'en'), ['name_en' => t('v_required')]);
        redirect(url('admin/cities'));
    }
    $stmt = db()->prepare('INSERT OR IGNORE INTO cities (name_ku, name_ar, name_en, slug) VALUES (?,?,?,?)');
    $stmt->execute([$ku, $ar, $en, $slug]);
    admin_log('city.add', $en);
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? t('city_added') : t('city_exists'));
    redirect(url('admin/cities'));
}

/* ───────────── Neighbourhoods per city ───────────── */

function admin_districts(int $cityId): void
{
    require_admin();
    $c = db()->prepare('SELECT * FROM cities WHERE id = ?');
    $c->execute([$cityId]);
    $city = $c->fetch() ?: abort(404);
    $items = db()->prepare('SELECT d.*, (SELECT COUNT(*) FROM properties p WHERE p.district_id = d.id) n FROM districts d WHERE d.city_id = ? ORDER BY d.name_en');
    $items->execute([$cityId]);
    // What offices typed that isn't on the list yet, most used first
    $sug = db()->prepare("SELECT MIN(trim(district)) district, COUNT(*) n FROM properties
        WHERE city_id = ? AND district_id IS NULL AND trim(district) != '' GROUP BY lower(trim(district)) ORDER BY n DESC LIMIT 30");
    $sug->execute([$cityId]);
    render('admin/districts', [
        'pageTitle' => t('districts_of', ['city' => city_name($city)]) . ' — ' . app_name(),
        'city' => $city, 'items' => $items->fetchAll(), 'suggest' => $sug->fetchAll(), 'active' => 'admin_cities',
    ]);
}

function admin_district_create(): void
{
    require_admin();
    $cityId = (int) input_int('city_id');
    [$ku, $ar, $en] = district_names_input();
    if ($en === '' || !$cityId) {
        with_old(['name_ku' => $ku, 'name_ar' => $ar], ['name_en' => t('v_required')]);
        redirect(url('admin/cities/' . $cityId . '/districts'));
    }
    $st = db()->prepare('INSERT OR IGNORE INTO districts (city_id, name_ku, name_ar, name_en) VALUES (?,?,?,?)');
    $st->execute([$cityId, $ku ?: $en, $ar ?: $en, $en]);
    if ($st->rowCount()) {
        $id = (int) db()->lastInsertId();
        $n = link_district_listings($id, $cityId, [$ku, $ar, $en]);
        admin_log('district.add', $en);
        flash('success', t('district_added', ['n' => $n]));
    } else {
        flash('error', t('district_exists'));
    }
    redirect(url('admin/cities/' . $cityId . '/districts'));
}

function admin_district_update(int $id): void
{
    require_admin();
    $d = db()->prepare('SELECT * FROM districts WHERE id = ?');
    $d->execute([$id]);
    $row = $d->fetch() ?: abort(404);
    [$ku, $ar, $en] = district_names_input();
    if ($en !== '') {
        try {
            db()->prepare('UPDATE districts SET name_ku = ?, name_ar = ?, name_en = ? WHERE id = ?')->execute([$ku ?: $en, $ar ?: $en, $en, $id]);
            link_district_listings($id, (int) $row['city_id'], [$ku, $ar, $en]);
            flash('success', t('saved'));
        } catch (PDOException) {
            flash('error', t('district_exists'));
        }
    }
    redirect(url('admin/cities/' . $row['city_id'] . '/districts'));
}

function admin_district_delete(int $id): void
{
    require_admin();
    $d = db()->prepare('SELECT city_id, name_en FROM districts WHERE id = ?');
    $d->execute([$id]);
    $row = $d->fetch() ?: abort(404);
    db()->prepare('DELETE FROM districts WHERE id = ?')->execute([$id]);      // listings keep the typed name
    admin_log('district.delete', $row['name_en']);
    flash('success', t('district_deleted'));
    redirect(url('admin/cities/' . $row['city_id'] . '/districts'));
}

function district_names_input(): array
{
    return array_map(static fn($k) => mb_substr(trim((string) preg_replace('/\s+/u', ' ', input($k))), 0, 80), ['name_ku', 'name_ar', 'name_en']);
}

/** Attach existing listings in this city whose typed neighbourhood matches one of the names. */
function link_district_listings(int $districtId, int $cityId, array $names): int
{
    $names = array_values(array_unique(array_filter(array_map(static fn($n) => mb_strtolower(trim($n)), $names))));
    if (!$names) {
        return 0;
    }
    $st = db()->prepare('UPDATE properties SET district_id = ? WHERE city_id = ? AND district_id IS NULL AND lower(trim(district)) IN ('
        . implode(',', array_fill(0, count($names), '?')) . ')');
    $st->execute([$districtId, $cityId, ...$names]);
    return $st->rowCount();
}

function admin_city_delete(int $id): void
{
    require_admin();
    db()->prepare('DELETE FROM cities WHERE id = ?')->execute([$id]);
    admin_log('city.delete', "#$id");
    flash('success', t('city_deleted'));
    redirect(url('admin/cities'));
}

function admin_offices(): void
{
    require_admin();
    // What needs you first: waiting for activation, ended, ending soon — then everyone else, newest first.
    $rows = db()->query(OFFICE_SELECT . " ORDER BY CASE
            WHEN o.activated_at IS NULL THEN 0
            WHEN o.active_until IS NOT NULL AND o.active_until <= datetime('now') THEN 1
            WHEN o.active_until IS NOT NULL AND o.active_until <= datetime('now', '+" . RENEW_SOON_DAYS . " days') THEN 2
            ELSE 3 END, o.active_until, o.created_at DESC LIMIT 500")->fetchAll();
    $done = $_SESSION['_activated'] ?? null;
    unset($_SESSION['_activated']);
    $created = $_SESSION['_created'] ?? null;          // new login, shown once
    unset($_SESSION['_created']);
    $payMonth = ['n' => 0];
    foreach (db()->query("SELECT currency, SUM(amount) total, COUNT(*) n FROM payments WHERE created_at >= datetime('now', 'start of month') GROUP BY currency") as $r) {
        $payMonth[$r['currency']] = (int) $r['total'];
        $payMonth['n'] += (int) $r['n'];
    }
    $payments = db()->query('SELECT * FROM payments ORDER BY id DESC LIMIT 50')->fetchAll();
    render('admin/offices', ['pageTitle' => t('offices') . ' — ' . app_name(), 'items' => $rows, 'done' => $done, 'created' => $created, 'payMonth' => $payMonth, 'payments' => $payments, 'active' => 'admin_offices']);
}

function admin_office_verify(int $id): void
{
    require_admin();
    // Verified = the admin visited the office in person; remember when (shown publicly).
    db()->prepare("UPDATE offices SET is_verified = 1 - is_verified,
        verified_at = CASE WHEN is_verified = 0 THEN datetime('now') ELSE NULL END WHERE id = ?")->execute([$id]);
    admin_log('office.verify', "#$id");
    flash('success', t('status_changed'));
    redirect(url('admin/offices'));
}

const ACTIVATION_MONTHS = [1, 3, 6, 12];     // plus 0 = no end date
const RENEW_SOON_DAYS = 7;                    // offices ending within this many days are listed (and warned) first

/** One tap after the office pays: activate (or extend) it, then offer a ready WhatsApp message telling them. */
function admin_office_activate(int $id): void
{
    require_admin();
    $o = find_office($id) ?? abort(404);
    $months = (int) input('months');
    $months = in_array($months, ACTIVATION_MONTHS, true) ? $months : null;
    if ($months === null) {
        db()->prepare("UPDATE offices SET activated_at = COALESCE(activated_at, datetime('now')), active_until = NULL WHERE id = ?")->execute([$id]);
    } else {
        // Extends from the current end date when renewing early, so paid time is never lost.
        db()->prepare("UPDATE offices SET activated_at = COALESCE(activated_at, datetime('now')),
            active_until = datetime(CASE WHEN active_until > datetime('now') THEN active_until ELSE datetime('now') END, '+' || ? || ' months') WHERE id = ?")
            ->execute([$months, $id]);
    }
    admin_log('office.activate', "#$id " . ($months ? "{$months}m" : 'no end'));
    $amount = max(0, (int) convert_arabic_digits((string) ($_POST['amount'] ?? '')));
    if ($amount > 0) {
        db()->prepare('INSERT INTO payments (office_id, office_name, amount, currency, months, created_by) VALUES (?,?,?,?,?,?)')
            ->execute([$id, $o['name'], $amount, input('currency') === 'USD' ? 'USD' : 'IQD', $months, current_user()['id']]);
    }
    $o = find_office($id);
    $until = $o['active_until'] ? substr($o['active_until'], 0, 10) : null;
    $message = t($until ? 'activated_message_until' : 'activated_message', [
        'app' => app_name(), 'office' => $o['name'], 'date' => (string) $until, 'url' => absolute_base_url() . '/dashboard/properties/new',
    ]);
    $phone = office_contact_phone($o);
    $_SESSION['_activated'] = ['office' => $o['name'], 'until' => $until, 'message' => $message, 'wa' => $phone ? whatsapp_url($phone, $message) : null];
    redirect(url('admin/offices'));
}

/** Best number to reach an office owner on WhatsApp. */
function office_contact_phone(array $o): string
{
    $phone = ($o['whatsapp'] ?? '') ?: ($o['phone'] ?? '');
    if ($phone === '') {
        $st = db()->prepare('SELECT phone FROM users WHERE id = ?');
        $st->execute([$o['user_id']]);
        $phone = (string) $st->fetchColumn();
    }
    return valid_phone($phone) ? $phone : '';
}

/** During a visit: create the office's account, active at once, with a temporary password to hand over. */
function admin_office_new(): void
{
    $admin = require_admin();
    $d = [
        'office' => mb_substr(trim(input('office_name')), 0, 100),
        'owner'  => mb_substr(trim(input('owner_name')), 0, 100),
        'phone'  => mb_substr(convert_arabic_digits(trim(input('phone'))), 0, 30),
        'email'  => mb_strtolower(mb_substr(trim(input('email')), 0, 150)),
        'city'   => input_int('city_id'),
    ];
    $errors = [];
    if (mb_strlen($d['office']) < 2) {
        $errors['office_name'] = t('v_office_name');
    }
    if (mb_strlen($d['owner']) < 2) {
        $errors['owner_name'] = t('v_name');
    }
    if (!valid_phone($d['phone'])) {
        $errors['phone'] = t('v_phone');
    } elseif (user_by_phone($d['phone'])) {
        $errors['phone'] = t('v_phone_taken');
    }
    if ($d['email'] !== '') {
        $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$d['email']]);
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('v_email');
        } elseif ($st->fetchColumn()) {
            $errors['email'] = t('v_email_taken');
        }
    }
    if ($errors) {
        with_old($d + ['office_name' => $d['office'], 'owner_name' => $d['owner'], 'city_id' => (string) $d['city']], $errors);
        redirect(url('admin/offices') . '#new-office');
    }
    $email = $d['email'] !== '' ? $d['email'] : 'office-' . phone_intl($d['phone']) . '@phone.invalid';   // signs in with the phone
    $password = readable_password();
    $months = (int) input('months');
    $months = in_array($months, ACTIVATION_MONTHS, true) ? $months : null;
    $visited = !empty($_POST['visited']);

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, must_change_password) VALUES (?,?,?,?, 'agent', 1)")
        ->execute([$d['owner'], $email, $d['phone'], password_hash($password, PASSWORD_ALGO)]);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO offices (user_id, name, slug, phone, whatsapp, city_id, activated_at, active_until, is_verified, verified_at)
        VALUES (?,?,?,?,?,?, datetime('now'), " . ($months ? "datetime('now', '+$months months')" : 'NULL') . ', ?, ' . ($visited ? "datetime('now')" : 'NULL') . ')')
        ->execute([$uid, $d['office'], office_slug($d['office']), $d['phone'], $d['phone'], $d['city'], $visited ? 1 : 0]);
    $oid = (int) $pdo->lastInsertId();
    $amount = max(0, (int) convert_arabic_digits((string) ($_POST['amount'] ?? '')));
    if ($amount > 0) {
        $pdo->prepare('INSERT INTO payments (office_id, office_name, amount, currency, months, created_by) VALUES (?,?,?,?,?,?)')
            ->execute([$oid, $d['office'], $amount, input('currency') === 'USD' ? 'USD' : 'IQD', $months, $admin['id']]);
    }
    $pdo->exec('COMMIT');
    admin_log('office.create', "#$oid " . $d['office']);
    $_SESSION['_created'] = login_handover($d['office'], $d['phone'], $password, false);
    redirect(url('admin/offices') . '#created');
}

/** Office forgot its password: make a new temporary one (they choose their own at the next sign-in). */
function admin_office_password(int $id): void
{
    require_admin();
    $o = find_office($id) ?? abort(404);
    $st = db()->prepare('SELECT id, phone, email, role FROM users WHERE id = ?');
    $st->execute([$o['user_id']]);
    $u = $st->fetch() ?: abort(404);
    if ($u['role'] === 'admin') {
        abort(403);                        // never reset an administrator's password from here
    }
    $password = readable_password();
    db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')->execute([password_hash($password, PASSWORD_ALGO), $u['id']]);
    admin_log('office.password', "#$id");
    $phone = office_contact_phone($o) ?: (string) $u['phone'];
    $_SESSION['_created'] = login_handover($o['name'], $phone, $password, true);
    redirect(url('admin/offices') . '#created');
}

/** What the admin shows or sends the office: how to sign in, with a ready WhatsApp message. */
function login_handover(string $office, string $phone, string $password, bool $reset): array
{
    $msg = t($reset ? 'handover_reset_message' : 'handover_message', [
        'app' => app_name(), 'office' => $office, 'phone' => $phone, 'password' => $password, 'url' => absolute_base_url() . '/login',
    ]);
    return ['office' => $office, 'phone' => $phone, 'password' => $password, 'reset' => $reset, 'message' => $msg,
            'wa' => valid_phone($phone) ? whatsapp_url($phone, $msg) : null];
}

function admin_office_signup_mode(): void
{
    require_admin();
    $mode = input('mode') === 'open' ? 'open' : 'admin';
    save_local_config(['office_signup' => $mode]);
    admin_log('settings.office_signup', $mode);
    flash('success', t($mode === 'open' ? 'signup_now_open' : 'signup_now_admin'));
    redirect(url('admin/offices'));
}

function admin_office_deactivate(int $id): void
{
    require_admin();
    db()->prepare('UPDATE offices SET activated_at = NULL, active_until = NULL WHERE id = ?')->execute([$id]);
    admin_log('office.deactivate', "#$id");
    flash('success', t('office_deactivated'));
    redirect(url('admin/offices'));
}

function admin_notifications(): void
{
    require_admin();
    $stats = db()->query('SELECT
        (SELECT COUNT(*) FROM push_subscriptions) devices,
        (SELECT COUNT(*) FROM push_subscriptions WHERE site_news = 1) news,
        (SELECT COUNT(*) FROM office_follows) follows')->fetch();
    $history = db()->query('SELECT n.*, o.name AS office_name FROM notifications n LEFT JOIN offices o ON o.id = n.office_id
        ORDER BY n.created_at DESC LIMIT 100')->fetchAll();
    $offices = db()->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
    render('admin/notifications', [
        'pageTitle' => t('notifications') . ' — ' . app_name(),
        'stats'     => $stats,
        'history'   => $history,
        'offices'   => $offices,
        'active'    => 'admin_notifications',
    ]);
}

function admin_notification_send(): void
{
    $u = require_admin();
    [$title, $body, $relUrl, $propertyId, $errors] = notification_input(null);
    $target = input('target');                  // 'all' or an office id
    $officeId = ctype_digit($target) ? (int) $target : null;
    if ($officeId && !find_office($officeId)) {
        $errors['target'] = t('v_required');
    }
    if ($errors) {
        with_old(compact('title', 'body', 'target') + ['property_id' => (string) $propertyId], $errors);
        redirect(url('admin/notifications'));
    }
    admin_log('push.send', ($officeId ? "followers of office #$officeId" : 'everyone') . ': ' . $title);
    $res = $officeId
        ? send_notification((int) $u['id'], $officeId, 'followers', $title, $body, $relUrl, $propertyId)
        : send_notification((int) $u['id'], null, 'all', $title, $body, $relUrl, $propertyId);
    flash('success', t('push_sent', ['sent' => $res['sent'], 'total' => $res['total']]));
    redirect(url('admin/notifications'));
}


function admin_reports(): void
{
    require_admin();
    $reports = db()->query("SELECT x.*,
            (SELECT COUNT(*) FROM reports r WHERE r.property_id = x.id AND r.status = 'open') AS open_reports,
            (SELECT GROUP_CONCAT(reason || CASE WHEN details != '' THEN ': ' || details ELSE '' END, ' | ')
               FROM (SELECT reason, details FROM reports r WHERE r.property_id = x.id AND r.status = 'open' ORDER BY created_at DESC LIMIT 5)) AS summary
        FROM (" . PROPERTY_SELECT . " WHERE p.id IN (SELECT property_id FROM reports WHERE status = 'open')) x
        ORDER BY open_reports DESC LIMIT 200")->fetchAll();
    $dups = db()->query("SELECT d.*, a.title AS a_title, a.status AS a_status, b.title AS b_title, b.status AS b_status,
            ua.name AS a_owner, ub.name AS b_owner
        FROM duplicate_flags d JOIN properties a ON a.id = d.property_id JOIN properties b ON b.id = d.other_id
        JOIN users ua ON ua.id = a.user_id JOIN users ub ON ub.id = b.user_id
        WHERE d.dismissed = 0 ORDER BY d.created_at DESC LIMIT 200")->fetchAll();
    render('admin/reports', ['pageTitle' => t('reports') . ' — ' . app_name(), 'reports' => $reports, 'dups' => $dups, 'active' => 'admin_reports']);
}

function admin_report_resolve(int $propertyId): void
{
    require_admin();
    if (input('action') === 'unpublish') {
        db()->prepare("UPDATE properties SET status = 'rejected', updated_at = datetime('now') WHERE id = ?")->execute([$propertyId]);
    }
    db()->prepare("UPDATE reports SET status = 'resolved' WHERE property_id = ?")->execute([$propertyId]);
    admin_log('report.resolve', "#$propertyId " . input('action'));
    flash('success', t('status_changed'));
    redirect(url('admin/reports'));
}

function admin_duplicate_dismiss(int $a, int $b): void
{
    require_admin();
    if (input('action') === 'reject') {
        db()->prepare("UPDATE properties SET status = 'rejected', updated_at = datetime('now') WHERE id = ?")->execute([$a]);
    }
    db()->prepare('UPDATE duplicate_flags SET dismissed = 1 WHERE property_id = ? AND other_id = ?')->execute([$a, $b]);
    admin_log('duplicate.' . (input('action') === 'reject' ? 'reject' : 'dismiss'), "#$a ~ #$b");
    flash('success', t('status_changed'));
    redirect(url('admin/reports') . '#dups');
}


/** Consistent SQLite snapshot (VACUUM INTO works while the site is live, even in WAL mode), streamed as a download. */
/** Download one of the automatic daily backups (name strictly validated: no path tricks). */
function admin_backup_download(): void
{
    require_admin();
    $name = (string) ($_POST['file'] ?? '');
    $file = backups_dir() . '/' . $name;
    if (!preg_match('/^aqarat-\d{4}-\d{2}-\d{2}\.sqlite$/', $name) || !is_file($file)) {
        abort(404);
    }
    admin_log('backup.download', $name);
    @set_time_limit(300);
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-store');
    readfile($file);
    exit;
}

function admin_backup(): void
{
    require_admin();
    $tmp = dirname((string) config('db_path')) . '/backup-' . bin2hex(random_bytes(6)) . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($tmp));
    admin_log('backup.download', basename($tmp));
    @set_time_limit(300);
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="aqarat-backup-' . date('Y-m-d-His') . '.sqlite"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
