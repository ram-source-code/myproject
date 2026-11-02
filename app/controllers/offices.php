<?php
declare(strict_types=1);

/* ───────────── نوسینگەکان — real-estate offices ───────────── */

const OFFICE_SELECT = "
    SELECT o.*, c.name_ar AS city_ar, c.name_en AS city_en, c.name_ku AS city_ku,
        (SELECT COUNT(*) FROM properties p WHERE p.user_id = o.user_id AND p.status = 'published') AS listings,
        (SELECT COUNT(*) FROM office_follows f WHERE f.office_id = o.id) AS followers
    FROM offices o
    JOIN users u ON u.id = o.user_id AND u.status = 'active'
    LEFT JOIN cities c ON c.id = o.city_id
";

function find_office(int $id): ?array
{
    $stmt = db()->prepare(OFFICE_SELECT . ' WHERE o.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function office_for_user(int $userId): ?array
{
    $stmt = db()->prepare(OFFICE_SELECT . ' WHERE o.user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function office_slug(string $name): string
{
    $slug = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($name)), '-');
    return mb_substr($slug !== '' ? $slug : 'office', 0, 60) . '-' . bin2hex(random_bytes(3));
}

function create_office(int $userId, string $name, string $phone = ''): int
{
    db()->prepare('INSERT INTO offices (user_id, name, slug, phone, whatsapp) VALUES (?,?,?,?,?)')
        ->execute([$userId, $name, office_slug($name), $phone, $phone]);
    return (int) db()->lastInsertId();
}

function offices_index(): void
{
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
    $city = input_int('city', null, $_GET);
    $where = ['1=1'];
    $args = [];
    if ($q !== '') {
        $where[] = "o.name LIKE ? ESCAPE '\\'";
        $args[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($city) {
        $where[] = 'o.city_id = ?';
        $args[] = $city;
    }
    $stmt = db()->prepare(OFFICE_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY o.is_verified DESC, listings DESC, o.name LIMIT 200');
    $stmt->execute($args);
    render('offices/index', [
        'pageTitle' => t('offices') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'q'         => $q,
        'city'      => $city,
    ]);
}

function office_show(int $id): void
{
    $o = find_office($id);
    if (!$o) {
        abort(404);
    }
    $f = search_filters($_GET);
    $f['office'] = $id;
    $page = max(1, input_int('page', 1, $_GET) ?? 1);
    [$items, $pg] = search_properties($f, $page, 12);
    render('offices/show', [
        'pageTitle' => $o['name'] . ' — ' . app_name(),
        'metaDesc'  => str_limit(preg_replace('/\s+/u', ' ', $o['about']) ?? '', 160),
        'o'         => $o,
        'items'     => $items,
        'pg'        => $pg,
        'filters'   => $f,
    ]);
}

/* ───────────── Office dashboard ───────────── */

function require_office_user(): array
{
    $u = require_login();
    if (!in_array($u['role'], ['agent', 'admin'], true)) {
        flash('info', t('office_only'));
        redirect(url('dashboard'));
    }
    return $u;
}

function dash_office(): void
{
    $u = require_office_user();
    $o = office_for_user((int) $u['id']);
    render('dashboard/office', [
        'pageTitle' => t('my_office') . ' — ' . app_name(),
        'o'         => $o,
        'u'         => $u,
        'active'    => 'office',
    ]);
}

function dash_office_save(): void
{
    $u = require_office_user();
    $d = [
        'name'     => mb_substr(input('name'), 0, 100),
        'about'    => mb_substr(input('about'), 0, 2000),
        'phone'    => mb_substr(convert_arabic_digits(input('phone')), 0, 30),
        'whatsapp' => mb_substr(convert_arabic_digits(input('whatsapp')), 0, 30),
        'address'  => mb_substr(input('address'), 0, 200),
        'city_id'  => input_int('city_id'),
    ];
    $errors = [];
    if (mb_strlen($d['name']) < 2) {
        $errors['name'] = t('v_name');
    }
    foreach (['phone', 'whatsapp'] as $k) {
        if ($d[$k] !== '' && !valid_phone($d[$k])) {
            $errors[$k] = t('v_phone');
        }
    }
    if ($d['city_id'] !== null && !in_array($d['city_id'], array_map(static fn($c) => (int) $c['id'], cities()), true)) {
        $d['city_id'] = null;
    }
    if ($errors) {
        with_old($d, $errors);
        redirect(url('dashboard/office'));
    }

    $o = office_for_user((int) $u['id']);
    if (!$o) {
        create_office((int) $u['id'], $d['name']);
        $o = office_for_user((int) $u['id']);
    }
    db()->prepare('UPDATE offices SET name=?, about=?, phone=?, whatsapp=?, address=?, city_id=? WHERE id=?')
        ->execute([$d['name'], $d['about'], $d['phone'], $d['whatsapp'], $d['address'], $d['city_id'], $o['id']]);

    $logo = normalize_files($_FILES['logo'] ?? null)[0] ?? null;
    if ($logo) {
        try {
            $name = process_logo_upload($logo);
            db()->prepare('UPDATE offices SET logo = ? WHERE id = ?')->execute([$name, $o['id']]);
            if ($o['logo'] && preg_match('/^[a-f0-9]{32}\.webp$/', $o['logo'])) {
                @unlink(config('upload_dir') . '/' . $o['logo']);
            }
        } catch (ImageUploadException $ex) {
            flash('error', $ex->getMessage());
        }
    }
    flash('success', t('office_saved'));
    redirect(url('dashboard/office'));
}

/* ───────────── Sending notifications (office → followers) ───────────── */

const OFFICE_DAILY_PUSH_LIMIT = 5;

function dash_notifications(): void
{
    $u = require_office_user();
    $o = office_for_user((int) $u['id']);
    $history = [];
    $listings = [];
    $sentToday = 0;
    if ($o) {
        $stmt = db()->prepare('SELECT * FROM notifications WHERE office_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$o['id']]);
        $history = $stmt->fetchAll();
        $stmt = db()->prepare("SELECT id, title FROM properties WHERE user_id = ? AND status = 'published' ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$u['id']]);
        $listings = $stmt->fetchAll();
        $sentToday = office_pushes_today((int) $o['id']);
    }
    render('dashboard/notifications', [
        'pageTitle' => t('send_notification') . ' — ' . app_name(),
        'o'         => $o,
        'history'   => $history,
        'listings'  => $listings,
        'sentToday' => $sentToday,
        'limit'     => OFFICE_DAILY_PUSH_LIMIT,
        'active'    => 'notifications',
    ]);
}

function office_pushes_today(int $officeId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE office_id = ? AND kind = 'manual' AND created_at >= datetime('now', '-1 day')");
    $stmt->execute([$officeId]);
    return (int) $stmt->fetchColumn();
}

/** Validates the compose form shared by offices and admins. Returns [title, body, relativeUrl, propertyId, errors]. */
function notification_input(?int $restrictToUserId): array
{
    $title = mb_substr(input('title'), 0, 80);
    $body = mb_substr(input('body'), 0, 240);
    $propertyId = input_int('property_id');
    $errors = [];
    if (mb_strlen($title) < 3) {
        $errors['title'] = t('v_notif_title');
    }
    if (mb_strlen($body) < 3) {
        $errors['body'] = t('v_notif_body');
    }
    $url = '/';
    if ($propertyId) {
        $p = find_property($propertyId);
        if (!$p || $p['status'] !== 'published' || ($restrictToUserId !== null && (int) $p['user_id'] !== $restrictToUserId)) {
            $errors['property_id'] = t('v_required');
            $propertyId = null;
        } else {
            $url = '/property/' . $propertyId;
        }
    }
    return [$title, $body, $url, $propertyId, $errors];
}

function dash_notification_send(): void
{
    $u = require_office_user();
    $o = office_for_user((int) $u['id']);
    if (!$o) {
        flash('error', t('create_office_first'));
        redirect(url('dashboard/office'));
    }
    if ($u['role'] !== 'admin' && office_pushes_today((int) $o['id']) >= OFFICE_DAILY_PUSH_LIMIT) {
        flash('error', t('push_limit', ['n' => OFFICE_DAILY_PUSH_LIMIT]));
        redirect(url('dashboard/notifications'));
    }
    [$title, $body, $relUrl, $propertyId, $errors] = notification_input((int) $u['id']);
    if ($errors) {
        with_old(compact('title', 'body') + ['property_id' => (string) $propertyId], $errors);
        redirect(url('dashboard/notifications'));
    }
    if (!$propertyId) {
        $relUrl = '/office/' . $o['id'];
    }
    $res = send_notification((int) $u['id'], (int) $o['id'], 'followers', $o['name'] . ': ' . $title, $body, $relUrl, $propertyId,
        $o['logo'] ? '/uploads/' . $o['logo'] : null);
    flash('success', t('push_sent', ['sent' => $res['sent'], 'total' => $res['total']]));
    redirect(url('dashboard/notifications'));
}
