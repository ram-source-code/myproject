<?php
declare(strict_types=1);

function dashboard_home(): void
{
    $u = require_login();
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

    $s = db()->prepare("SELECT
        COUNT(*) total,
        SUM(status='published') published,
        SUM(status='pending') pending,
        COALESCE(SUM(views),0) views,
        (SELECT COUNT(*) FROM inquiries i JOIN properties p2 ON p2.id = i.property_id WHERE p2.user_id = ? AND i.is_read = 0) unread
        FROM properties WHERE user_id = ?");
    $s->execute([$u['id'], $u['id']]);

    render('dashboard/index', [
        'pageTitle' => t('dashboard') . ' — ' . app_name(),
        'items'     => $items,
        'stats'     => $s->fetch(),
        'status'    => $status,
        'active'    => 'listings',
    ]);
}

function dash_property_new(): void
{
    require_login();
    render('dashboard/form', [
        'pageTitle' => t('add_listing') . ' — ' . app_name(),
        'useMap'    => true,
        'p'         => null,
        'images'    => [],
        'active'    => 'new',
    ]);
}

function dash_property_create(): void
{
    $u = require_login();
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
    db()->prepare('INSERT INTO properties (user_id, city_id, title, description, purpose, type, district, address, project, price, currency, price_usd,
            rent_period, deed, area, bedrooms, bathrooms, floor, year_built, furnished, features, lat, lng, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $d['city_id'], $d['title'], $d['description'], $d['purpose'], $d['type'], $d['district'], $d['address'], $d['project'],
            $d['price'], $d['currency'], $d['price_usd'], $d['rent_period'], $d['deed'], $d['area'], $d['bedrooms'], $d['bathrooms'], $d['floor'],
            $d['year_built'], $d['furnished'], $d['features'], $d['lat'], $d['lng'], $status]);
    $id = (int) db()->lastInsertId();

    foreach (attach_uploaded_images($id, $files) as $msg) {
        flash('error', $msg);
    }
    if ($status === 'published' && ($p = find_property($id))) {
        notify_new_listing($p);
    }
    clear_old();
    flash('success', $status === 'pending' ? t('listing_pending') : t('listing_created'));
    redirect(url("dashboard/properties/$id/edit"));
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
    require_login();
    $p = owned_property_or_404($id);
    render('dashboard/form', [
        'pageTitle' => t('edit_listing') . ' — ' . app_name(),
        'useMap'    => true,
        'p'         => $p,
        'images'    => property_images($id),
        'active'    => 'listings',
    ]);
}

function dash_property_update(int $id): void
{
    $u = require_login();
    $p = owned_property_or_404($id);
    [$d, $errors] = validate_property_input($_POST);
    if ($errors) {
        with_old($_POST, $errors);
        flash('error', t('form_has_errors'));
        redirect(url("dashboard/properties/$id/edit"));
    }

    // Substantive edits by non-admins send a published listing back to review.
    $status = $p['status'];
    if (config('require_approval') && $u['role'] !== 'admin' && in_array($status, ['published', 'rejected'], true)) {
        $status = 'pending';
    }

    db()->prepare("UPDATE properties SET city_id=?, title=?, description=?, purpose=?, type=?, district=?, address=?, project=?, price=?, currency=?,
            price_usd=?, rent_period=?, deed=?, area=?, bedrooms=?, bathrooms=?, floor=?, year_built=?, furnished=?, features=?, lat=?, lng=?, status=?,
            updated_at=datetime('now') WHERE id=?")
        ->execute([$d['city_id'], $d['title'], $d['description'], $d['purpose'], $d['type'], $d['district'], $d['address'], $d['project'],
            $d['price'], $d['currency'], $d['price_usd'], $d['rent_period'], $d['deed'], $d['area'], $d['bedrooms'], $d['bathrooms'], $d['floor'],
            $d['year_built'], $d['furnished'], $d['features'], $d['lat'], $d['lng'], $status, $id]);

    foreach (attach_uploaded_images($id, normalize_files($_FILES['images'] ?? null)) as $msg) {
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
    if (!in_array($p['status'], ['published', 'sold'], true)) {
        abort(403);
    }
    $new = $p['status'] === 'sold' ? 'published' : 'sold';
    db()->prepare("UPDATE properties SET status = ?, updated_at = datetime('now') WHERE id = ?")->execute([$new, $id]);
    flash('success', t('status_changed'));
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
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE property_images SET sort_order = sort_order + 1 WHERE property_id = ?')->execute([$img['property_id']]);
    $pdo->prepare('UPDATE property_images SET sort_order = 0 WHERE id = ?')->execute([$imageId]);
    $pdo->commit();
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
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    login_user((int) $u['id']); // new session id
    flash('success', t('password_saved'));
    redirect(url('dashboard/profile'));
}
