<?php
declare(strict_types=1);

function dashboard_home(): void
{
    $u = require_login();
    if ($u['role'] !== 'admin' && !can_post_listings($u)) {
        $has = db()->prepare('SELECT 1 FROM properties WHERE user_id = ? LIMIT 1');
        $has->execute([$u['id']]);
        if (!$has->fetchColumn()) {          // customers: saved listings; offices awaiting activation: their office page
            redirect(url(office_state($u) === 'none' ? 'dashboard/favorites' : 'dashboard/office'));
        }
    }
    $where = $u['role'] === 'admin' ? '1=1' : 'p.user_id = ?';
    $args = $u['role'] === 'admin' ? [] : [$u['id']];
    $status = in_array($_GET['status'] ?? '', PROPERTY_STATUSES, true) ? $_GET['status'] : '';
    if ($status) {
        $where .= ' AND p.status = ?';
        $args[] = $status;
    }

    $stmt = db()->prepare(PROPERTY_SELECT . " WHERE $where ORDER BY p.updated_at DESC LIMIT 200");
    $stmt->execute($args);
    $items = $stmt->fetchAll();
    // Last 30 days, for every listing of this office (not only the rows on screen)
    $mine = db()->prepare($u['role'] === 'admin' ? 'SELECT id FROM properties' : 'SELECT id FROM properties WHERE user_id = ?');
    $mine->execute($u['role'] === 'admin' ? [] : [$u['id']]);
    $month = listing_stats($mine->fetchAll(PDO::FETCH_COLUMN));
    $check = db()->prepare("SELECT id, title, status, stale_since FROM properties WHERE user_id = ?
        AND (status = 'expired' OR (status = 'published' AND stale_since IS NOT NULL)) ORDER BY status = 'expired' DESC, stale_since LIMIT 50");
    $check->execute([$u['id']]);

    $s = db()->prepare("SELECT
        COUNT(*) total,
        SUM(status='published') published,
        SUM(status='pending') pending,
        COALESCE(SUM(views),0) views,
        (SELECT COUNT(*) FROM inquiries i JOIN properties p2 ON p2.id = i.property_id WHERE p2.user_id = ? AND i.is_read = 0) unread
        FROM properties WHERE user_id = ?");
    $s->execute([$u['id'], $u['id']]);

    render('dashboard/index', [
        'needCheck' => $check->fetchAll(),
        'month'     => $month,
        'pageTitle' => t('dashboard') . ' — ' . app_name(),
        'items'     => $items,
        'stats'     => $s->fetch(),
        'status'    => $status,
        'active'    => 'listings',
    ]);
}

function dash_property_new(): void
{
    require_poster();
    render('dashboard/form', [
        'pageTitle' => t('add_listing') . ' — ' . app_name(),
        'useMap'    => true,
        'p'         => null,
        'images'    => [],
        'videos'    => [],
        'active'    => 'new',
    ]);
}

function dash_property_create(): void
{
    $u = require_poster();
    [$d, $errors] = validate_property_input($_POST);
    $files = normalize_files($_FILES['images'] ?? null);
    if (count($files) > config('max_images')) {
        $errors['images'] = t('img_limit', ['n' => config('max_images')]);
    }
    if ($errors) {
        with_old($_POST, $errors);
        flash('error', t('form_has_errors'));
        redirect(url('dashboard/properties/new'));
    }

    $status = (config('require_approval') && !in_array($u['role'], ['admin'], true)) ? 'pending' : 'published';
    $dist = match_district($d['city_id'], $d['district']);
    db()->prepare('INSERT INTO properties (user_id, city_id, title, description, purpose, type, district, address, project, price, currency, price_usd,
            rent_period, deed, area, bedrooms, bathrooms, floor, year_built, furnished, features, lat, lng, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $d['city_id'], $d['title'], $d['description'], $d['purpose'], $d['type'], $d['district'], $d['address'], $d['project'],
            $d['price'], $d['currency'], $d['price_usd'], $d['rent_period'], $d['deed'], $d['area'], $d['bedrooms'], $d['bathrooms'], $d['floor'],
            $d['year_built'], $d['furnished'], $d['features'], $d['lat'], $d['lng'], $status]);
    $id = (int) db()->lastInsertId();
    db()->prepare("UPDATE properties SET district_id = ?, confirmed_at = datetime('now') WHERE id = ?")->execute([$dist['id'] ?? null, $id]);

    foreach (attach_uploaded_images($id, $files) as $msg) {
        flash('error', $msg);
    }
    foreach (attach_uploaded_videos($id, normalize_files($_FILES['videos'] ?? null)) as $msg) {
        flash('error', $msg);
    }
    if ($status === 'published' && ($p = find_property($id))) {
        notify_listing_published($p);
    }
    clear_old();
    flash('success', $status === 'pending' ? t('listing_pending') : t('listing_created'));
    redirect(url("dashboard/properties/$id/edit"));
}

/** Does this edit change nothing but the price (no other field, no new photos or videos)? */
function property_edit_is_price_only(array $p, array $d): bool
{
    foreach (['images', 'videos'] as $k) {
        foreach (normalize_files($_FILES[$k] ?? null) as $f) {
            if ((int) $f['error'] !== UPLOAD_ERR_NO_FILE) {
                return false;
            }
        }
    }
    $norm = static fn($v): string => is_float($v) || (is_string($v) && is_numeric($v) && str_contains($v, '.'))
        ? rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.') : trim((string) $v);
    foreach ($d as $k => $v) {
        if (in_array($k, ['price', 'currency', 'price_usd', 'rent_period'], true) || !array_key_exists($k, $p)) {
            continue;
        }
        if ($norm($v) !== $norm($p[$k])) {
            return false;
        }
    }
    return true;
}

function owned_property_or_404(int $id): array
{
    $p = find_property($id);
    if (!$p || !can_manage_property($p, current_user())) {
        abort(404); // 404 rather than 403 to avoid leaking existence
    }
    return $p;
}

function dash_property_edit(int $id): void
{
    if (!is_admin()) {               // admins may still moderate any listing
        require_poster();
    }
    $p = owned_property_or_404($id);
    render('dashboard/form', [
        'pageTitle' => t('edit_listing') . ' — ' . app_name(),
        'useMap'    => true,
        'p'         => $p,
        'images'    => property_images($id),
        'videos'    => property_videos($id),
        'active'    => 'listings',
    ]);
}

function dash_property_update(int $id): void
{
    $u = is_admin() ? require_login() : require_poster();
    $p = owned_property_or_404($id);
    [$d, $errors] = validate_property_input($_POST);
    if ($errors) {
        with_old($_POST, $errors);
        flash('error', t('form_has_errors'));
        redirect(url("dashboard/properties/$id/edit"));
    }

    // Editing confirms the listing is still available (and brings a hidden one back).
    $status = $p['status'] === 'expired' ? 'published' : $p['status'];
    // Substantive edits by non-admins send a published listing back to review. Changing only the price does not:
    // it needs no checking, and sending it to review used to swallow the price-drop alert.
    if (config('require_approval') && $u['role'] !== 'admin' && in_array($status, ['published', 'rejected'], true)
        && !($status === 'published' && property_edit_is_price_only($p, $d))) {
        $status = 'pending';
    }

    db()->prepare("UPDATE properties SET city_id=?, title=?, description=?, purpose=?, type=?, district=?, address=?, project=?, price=?, currency=?,
            price_usd=?, rent_period=?, deed=?, area=?, bedrooms=?, bathrooms=?, floor=?, year_built=?, furnished=?, features=?, lat=?, lng=?, status=?,
            updated_at=datetime('now') WHERE id=?")
        ->execute([$d['city_id'], $d['title'], $d['description'], $d['purpose'], $d['type'], $d['district'], $d['address'], $d['project'],
            $d['price'], $d['currency'], $d['price_usd'], $d['rent_period'], $d['deed'], $d['area'], $d['bedrooms'], $d['bathrooms'], $d['floor'],
            $d['year_built'], $d['furnished'], $d['features'], $d['lat'], $d['lng'], $status, $id]);
    $dist = match_district($d['city_id'], $d['district']);
    db()->prepare("UPDATE properties SET confirmed_at = datetime('now'), stale_since = NULL, district_id = ? WHERE id = ?")->execute([$dist['id'] ?? null, $id]);

    // Price drop: remember the old price (badge for 30 days) and tell people who are watching this listing.
    $old = (int) $p['price_usd'];
    $new = (int) $d['price_usd'];
    if ($old > 0 && $new > 0 && $new < $old) {
        db()->prepare("UPDATE properties SET prev_price_usd = ?, price_dropped_at = datetime('now') WHERE id = ?")->execute([$old, $id]);
        $lastDrop = $p['price_dropped_at'] ? strtotime($p['price_dropped_at'] . ' UTC') : 0;
        if ($status === 'published' && $lastDrop < time() - 3 * 86400) {      // no repeat alerts for a string of small cuts
            notify_price_drop($id, $old, $new);
        }
    } elseif ($new > $old) {
        db()->prepare('UPDATE properties SET prev_price_usd = NULL, price_dropped_at = NULL WHERE id = ?')->execute([$id]);
    }

    foreach (attach_uploaded_images($id, normalize_files($_FILES['images'] ?? null)) as $msg) {
        flash('error', $msg);
    }
    foreach (attach_uploaded_videos($id, normalize_files($_FILES['videos'] ?? null)) as $msg) {
        flash('error', $msg);
    }
    clear_old();
    flash('success', $status === 'pending' && $p['status'] !== 'pending' ? t('listing_pending') : t('listing_updated'));
    redirect(url("dashboard/properties/$id/edit"));
}

function dash_property_delete(int $id): void
{
    require_login();
    owned_property_or_404($id);
    delete_property($id);
    flash('success', t('listing_deleted'));
    redirect(url('dashboard'));
}

function dash_property_sold(int $id): void
{
    require_login();
    $p = owned_property_or_404($id);
    if (!in_array($p['status'], ['published', 'sold', 'expired'], true)) {
        abort(403);
    }
    $new = $p['status'] === 'sold' ? 'published' : 'sold';
    db()->prepare("UPDATE properties SET status = ?, stale_since = NULL, confirmed_at = datetime('now'), updated_at = datetime('now') WHERE id = ?")->execute([$new, $id]);
    flash('success', t('status_changed'));
    back('/dashboard');
}

/** "Still available" — one tap from the reminder (also brings back a listing hidden for not being confirmed). */
function dash_property_confirm(int $id): void
{
    require_poster();
    $p = owned_property_or_404($id);
    if (!in_array($p['status'], ['published', 'expired'], true)) {
        abort(403);
    }
    db()->prepare("UPDATE properties SET status = 'published', confirmed_at = datetime('now'), stale_since = NULL, updated_at = datetime('now') WHERE id = ?")->execute([$id]);
    flash('success', t('confirmed_available'));
    back('/dashboard');
}

/** Confirm every listing waiting for a check, in one tap. */
function dash_property_confirm_all(): void
{
    $u = require_poster();
    $st = db()->prepare("UPDATE properties SET status = 'published', confirmed_at = datetime('now'), stale_since = NULL, updated_at = datetime('now')
        WHERE user_id = ? AND (status = 'expired' OR (status = 'published' AND stale_since IS NOT NULL))");
    $st->execute([$u['id']]);
    flash('success', t('confirmed_n', ['n' => $st->rowCount()]));
    back('/dashboard');
}

function owned_image_or_404(int $imageId): array
{
    $stmt = db()->prepare('SELECT * FROM property_images WHERE id = ?');
    $stmt->execute([$imageId]);
    $img = $stmt->fetch();
    if (!$img) {
        abort(404);
    }
    owned_property_or_404((int) $img['property_id']);
    return $img;
}

function dash_image_delete(int $imageId): void
{
    require_login();
    $img = owned_image_or_404($imageId);
    db()->prepare('DELETE FROM property_images WHERE id = ?')->execute([$imageId]);
    delete_image_files($img);
    if (wants_json()) {
        json_out(['ok' => true]);
    }
    flash('success', t('image_deleted'));
    redirect(url('dashboard/properties/' . $img['property_id'] . '/edit') . '#images');
}

function dash_image_cover(int $imageId): void
{
    require_login();
    $img = owned_image_or_404($imageId);
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');      // take the write lock up front (a deferred BEGIN can fail with SQLITE_BUSY under load)
    $pdo->prepare('UPDATE property_images SET sort_order = sort_order + 1 WHERE property_id = ?')->execute([$img['property_id']]);
    $pdo->prepare('UPDATE property_images SET sort_order = 0 WHERE id = ?')->execute([$imageId]);
    $pdo->exec('COMMIT');
    flash('success', t('cover_set'));
    redirect(url('dashboard/properties/' . $img['property_id'] . '/edit') . '#images');
}

function dash_favorites(): void
{
    $u = require_login();
    $stmt = db()->prepare(PROPERTY_SELECT . " JOIN favorites f ON f.property_id = p.id
        WHERE f.user_id = ? AND p.status IN ('published','sold') ORDER BY f.created_at DESC");
    $stmt->execute([$u['id']]);
    render('dashboard/favorites', [
        'pageTitle' => t('favorites') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'active'    => 'favorites',
    ]);
}

function dash_inquiries(): void
{
    $u = require_login();
    $stmt = db()->prepare('SELECT i.*, p.title AS property_title FROM inquiries i JOIN properties p ON p.id = i.property_id
        WHERE p.user_id = ? ORDER BY i.created_at DESC LIMIT 300');
    $stmt->execute([$u['id']]);
    render('dashboard/inquiries', [
        'pageTitle' => t('inquiries') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'active'    => 'inquiries',
    ]);
}

function dash_inquiry_read(int $id): void
{
    $u = require_login();
    db()->prepare('UPDATE inquiries SET is_read = 1 - is_read WHERE id = ? AND property_id IN (SELECT id FROM properties WHERE user_id = ?)')
        ->execute([$id, $u['id']]);
    redirect(url('dashboard/inquiries'));
}

function dash_profile(): void
{
    $u = require_login();
    render('dashboard/profile', ['pageTitle' => t('profile') . ' — ' . app_name(), 'u' => $u, 'active' => 'profile']);
}

function dash_profile_update(): void
{
    $u = require_login();
    $name = mb_substr(input('name'), 0, 100);
    $phone = mb_substr(convert_arabic_digits(input('phone')), 0, 30);
    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors['name'] = t('v_name');
    }
    if ($phone !== '' && !valid_phone($phone)) {
        $errors['phone'] = t('v_phone');
    }
    if ($errors) {
        with_old(compact('name', 'phone'), $errors);
        redirect(url('dashboard/profile'));
    }
    db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $u['id']]);
    flash('success', t('profile_saved'));
    redirect(url('dashboard/profile'));
}

function dash_password_update(): void
{
    $u = require_login();
    $cur = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$u['id']]);
    $errors = [];
    if (!password_verify($cur, (string) $stmt->fetchColumn())) {
        $errors['current_password'] = t('v_current_password');
    } elseif (strlen($new) < 8 || strlen($new) > 128) {
        $errors['password'] = t('v_password');
    } elseif (!hash_equals($new, $confirm)) {
        $errors['password_confirm'] = t('v_password_match');
    }
    if ($errors) {
        with_old([], $errors);
        redirect(url('dashboard/profile') . '#password');
    }
    db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([password_hash($new, PASSWORD_ALGO), $u['id']]);
    login_user((int) $u['id']); // new session id
    flash('success', t('password_saved'));
    redirect(url('dashboard/profile'));
}


function dash_video_delete(int $videoId): void
{
    require_login();
    $stmt = db()->prepare('SELECT * FROM property_videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $v = $stmt->fetch();
    if (!$v) {
        abort(404);
    }
    owned_property_or_404((int) $v['property_id']);
    db()->prepare('DELETE FROM property_videos WHERE id = ?')->execute([$videoId]);
    delete_video_file($v);
    flash('success', t('video_deleted'));
    redirect(url('dashboard/properties/' . $v['property_id'] . '/edit') . '#videos');
}


/** "Download my data" — everything stored about the signed-in user, as JSON. */
function dash_export(): void
{
    $u = require_login();
    $q = static function (string $sql, array $args) { $st = db()->prepare($sql); $st->execute($args); return $st->fetchAll(); };
    $data = [
        'exported_at' => date('c'),
        'account'     => $u,
        'office'      => $q('SELECT name, about, phone, whatsapp, address, city_id, is_verified, created_at FROM offices WHERE user_id = ?', [$u['id']])[0] ?? null,
        'listings'    => array_map(static function ($p) {
            $p['photos'] = array_map(static fn($i) => upload_url($i['filename']), property_images((int) $p['id']));
            $p['videos'] = array_map(static fn($v) => upload_url($v['filename']), property_videos((int) $p['id']));
            return $p;
        }, $q('SELECT id, title, description, purpose, type, city_id, district, address, project, price, currency, rent_period, deed, area,
                      bedrooms, bathrooms, floor, year_built, furnished, features, lat, lng, status, views, created_at, updated_at
               FROM properties WHERE user_id = ?', [$u['id']])),
        'favorites'      => $q('SELECT property_id, created_at FROM favorites WHERE user_id = ?', [$u['id']]),
        'inquiries_sent' => $q('SELECT property_id, name, email, phone, message, created_at FROM inquiries WHERE user_id = ?', [$u['id']]),
        'inquiries_received' => $q('SELECT i.property_id, i.name, i.email, i.phone, i.message, i.created_at FROM inquiries i
                                    JOIN properties p ON p.id = i.property_id WHERE p.user_id = ?', [$u['id']]),
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="aqarat-my-data-' . date('Y-m-d') . '.json"');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** "Delete my account" — removes the user, their office, listings (incl. photo/video files) and favourites. */
function dash_delete_account(): void
{
    $u = require_login();
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$u['id']]);
    if (!password_verify((string) ($_POST['password'] ?? ''), (string) $stmt->fetchColumn())) {
        with_old([], ['delete_password' => t('v_current_password')]);
        redirect(url('dashboard/profile') . '#delete');
    }
    if ($u['role'] === 'admin' && (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn() <= 1) {
        flash('error', t('last_admin'));
        redirect(url('dashboard/profile') . '#delete');
    }
    $ids = db()->prepare('SELECT id FROM properties WHERE user_id = ?');
    $ids->execute([$u['id']]);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $pid) {
        delete_property((int) $pid);                         // also deletes photo/video files
    }
    $logo = db()->prepare('SELECT logo FROM offices WHERE user_id = ?');
    $logo->execute([$u['id']]);
    $l = (string) $logo->fetchColumn();
    if ($l !== '' && preg_match('/^[a-f0-9]{32}\.webp$/', $l)) {
        @unlink(config('upload_dir') . '/' . $l);
    }
    db()->prepare('UPDATE inquiries SET user_id = NULL WHERE user_id = ?')->execute([$u['id']]);
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$u['id']]);   // cascades: office, follows, favorites
    logout_user();
    flash('success', t('account_deleted'));
    redirect(url('/'));
}

/* ───────────── Two-step login (authenticator app) ───────────── */

function dash_2fa_setup(): void
{
    $u = require_login();
    if (user_2fa((int) $u['id'])) {
        redirect(url('dashboard/profile') . '#twofa');
    }
    $_SESSION['_2fa_setup'] ??= totp_new_secret();           // same secret on reload, until it is confirmed
    render('dashboard/twofa', [
        'pageTitle' => t('twofa_title') . ' — ' . app_name(),
        'secret'    => $_SESSION['_2fa_setup'],
        'uri'       => totp_uri($_SESSION['_2fa_setup'], $u['email']),
        'useQr'     => true,
        'active'    => 'profile',
    ]);
}

function dash_2fa_enable(): void
{
    $u = require_login();
    $secret = $_SESSION['_2fa_setup'] ?? '';
    $step = $secret ? totp_verify($secret, (string) ($_POST['code'] ?? '')) : null;
    if ($step === null) {
        with_old([], ['code' => t('twofa_wrong')]);
        redirect(url('dashboard/2fa'));
    }
    $codes = new_recovery_codes();
    db()->prepare('UPDATE users SET totp_secret = ?, totp_last_step = ?, recovery_codes = ? WHERE id = ?')
        ->execute([$secret, $step, json_encode(array_map('recovery_hash', $codes)), $u['id']]);
    unset($_SESSION['_2fa_setup']);
    if ($u['role'] === 'admin') {
        admin_log('2fa.enable', '#' . $u['id']);
    }
    $_SESSION['_show_codes'] = $codes;
    flash('success', t('twofa_on'));
    redirect(url('dashboard/2fa/codes'));
}

/** Recovery codes are shown exactly once, right after they are made. */
function dash_2fa_codes(): void
{
    $u = require_login();
    $codes = $_SESSION['_show_codes'] ?? null;
    unset($_SESSION['_show_codes']);
    if (!$codes) {
        redirect(url('dashboard/profile') . '#twofa');
    }
    render('dashboard/twofa_codes', ['pageTitle' => t('twofa_codes_title') . ' — ' . app_name(), 'codes' => $codes, 'active' => 'profile']);
}

function dash_2fa_new_codes(): void
{
    $u = require_login();
    if (!user_2fa((int) $u['id']) || !twofa_password_ok((int) $u['id'])) {
        redirect(url('dashboard/profile') . '#twofa');
    }
    $codes = new_recovery_codes();
    db()->prepare('UPDATE users SET recovery_codes = ? WHERE id = ?')->execute([json_encode(array_map('recovery_hash', $codes)), $u['id']]);
    $_SESSION['_show_codes'] = $codes;
    redirect(url('dashboard/2fa/codes'));
}

function dash_2fa_disable(): void
{
    $u = require_login();
    if (!twofa_password_ok((int) $u['id'])) {
        redirect(url('dashboard/profile') . '#twofa');
    }
    db()->prepare("UPDATE users SET totp_secret = NULL, totp_last_step = 0, recovery_codes = '[]' WHERE id = ?")->execute([$u['id']]);
    if ($u['role'] === 'admin') {
        admin_log('2fa.disable', '#' . $u['id']);
    }
    flash('success', t('twofa_off'));
    redirect(url('dashboard/profile') . '#twofa');
}

/** Changing two-step settings needs the current password (a borrowed unlocked phone is not enough). */
function twofa_password_ok(int $userId): bool
{
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$userId]);
    if (password_verify((string) ($_POST['password'] ?? ''), (string) $st->fetchColumn())) {
        return true;
    }
    with_old([], ['twofa_password' => t('current_password_wrong')]);
    return false;
}
