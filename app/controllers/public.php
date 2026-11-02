<?php
declare(strict_types=1);

function home_page(): void
{
    $featured = latest_properties(6, true);
    $latest = latest_properties(8);
    $stats = db()->query("SELECT
            (SELECT COUNT(*) FROM properties WHERE status='published') AS listings,
            (SELECT COUNT(*) FROM properties WHERE status='published' AND purpose='sale') AS sale,
            (SELECT COUNT(*) FROM properties WHERE status='published' AND purpose='rent') AS rent,
            (SELECT COUNT(DISTINCT city_id) FROM properties WHERE status='published') AS cities,
            (SELECT COUNT(*) FROM offices) AS offices")->fetch();
    $byType = db()->query("SELECT type, COUNT(*) n FROM properties WHERE status='published' GROUP BY type ORDER BY n DESC")->fetchAll(PDO::FETCH_KEY_PAIR);

    $topCities = db()->query("SELECT c.*, COUNT(p.id) n FROM cities c JOIN properties p ON p.city_id = c.id AND p.status = 'published'
        GROUP BY c.id ORDER BY n DESC LIMIT 6")->fetchAll();
    $offices = db()->query(OFFICE_SELECT . ' ORDER BY o.is_verified DESC, listings DESC LIMIT 8')->fetchAll();

    render('home', [
        'pageTitle' => app_name() . ' — ' . t('tagline'),
        'featured'  => $featured,
        'latest'    => $latest,
        'stats'     => $stats,
        'byType'    => $byType,
        'topCities' => $topCities,
        'offices'   => $offices,
    ]);
}

function properties_index(): void
{
    $f = search_filters($_GET);
    $page = max(1, input_int('page', 1, $_GET) ?? 1);
    [$items, $pg] = search_properties($f, $page, (int) config('listings_per_page'));

    $view = ($_GET['view'] ?? '') === 'map' ? 'map' : 'list';
    render('properties/index', [
        'pageTitle' => t('nav_listings') . ' — ' . app_name(),
        'filters'   => $f,
        'items'     => $items,
        'pg'        => $pg,
        'viewMode'  => $view,
        'useMap'    => $view === 'map',
    ]);
}

function property_show(int $id): void
{
    $p = find_property($id);
    $user = current_user();
    if (!$p || !can_view_property($p, $user)) {
        abort(404);
    }

    // Count a view once per session per listing
    $_SESSION['_viewed'] ??= [];
    if (!in_array($id, $_SESSION['_viewed'], true) && !can_manage_property($p, $user)) {
        $_SESSION['_viewed'][] = $id;
        $_SESSION['_viewed'] = array_slice($_SESSION['_viewed'], -200);
        db()->prepare('UPDATE properties SET views = views + 1 WHERE id = ?')->execute([$id]);
    }

    render('properties/show', [
        'useMap'    => $p['lat'] !== null && $p['lng'] !== null,
        'office'    => $p['office_id'] ? find_office((int) $p['office_id']) : null,
        'pageTitle' => $p['title'] . ' — ' . app_name(),
        'metaDesc'  => str_limit(preg_replace('/\s+/u', ' ', $p['description']) ?? '', 160),
        'p'         => $p,
        'images'    => property_images($id),
        'similar'   => similar_properties($p),
        'features'  => json_decode($p['features'], true) ?: [],
    ]);
}

function property_inquiry(int $id): void
{
    $p = find_property($id);
    if (!$p || $p['status'] !== 'published') {
        abort(404);
    }
    // Honeypot field: bots fill every input
    if (input('website') !== '') {
        flash('success', t('inquiry_sent'));
        redirect(url('property/' . $id));
    }
    $user = current_user();
    $data = [
        'name'    => mb_substr(input('name'), 0, 100),
        'email'   => mb_substr(input('email'), 0, 150),
        'phone'   => mb_substr(input('phone'), 0, 30),
        'message' => mb_substr(input('message'), 0, 2000),
    ];
    $errors = [];
    if (mb_strlen($data['name']) < 2) {
        $errors['name'] = t('v_name');
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = t('v_email');
    }
    if ($data['phone'] !== '' && !valid_phone($data['phone'])) {
        $errors['phone'] = t('v_phone');
    }
    if (mb_strlen($data['message']) < 10) {
        $errors['message'] = t('v_message');
    }
    if ($errors) {
        with_old($data, $errors);
        redirect(url('property/' . $id) . '#contact');
    }
    if (rate_limited_inquiry(client_ip())) {
        abort(429);
    }
    db()->prepare('INSERT INTO inquiries (property_id, user_id, name, email, phone, message, ip) VALUES (?,?,?,?,?,?,?)')
        ->execute([$id, $user['id'] ?? null, $data['name'], $data['email'], $data['phone'], $data['message'], client_ip()]);
    clear_old();
    flash('success', t('inquiry_sent'));
    redirect(url('property/' . $id) . '#contact');
}

function favorite_toggle(int $id): void
{
    $user = current_user();
    if (!$user) {
        if (wants_json()) {
            json_out(['ok' => false, 'login' => url('login')], 401);
        }
        require_login();
    }
    $p = find_property($id);
    if (!$p || !can_view_property($p, $user)) {
        abort(404);
    }
    $del = db()->prepare('DELETE FROM favorites WHERE user_id = ? AND property_id = ?');
    $del->execute([$user['id'], $id]);
    $saved = false;
    if ($del->rowCount() === 0) {
        db()->prepare('INSERT OR IGNORE INTO favorites (user_id, property_id) VALUES (?, ?)')->execute([$user['id'], $id]);
        $saved = true;
    }
    if (wants_json()) {
        json_out(['ok' => true, 'saved' => $saved, 'label' => $saved ? t('fav_remove') : t('fav_add')]);
    }
    flash('success', $saved ? t('fav_added') : t('fav_removed'));
    back('/property/' . $id);
}

function switch_lang(string $code): void
{
    setcookie('lang', $code, ['expires' => time() + 31536000, 'path' => base_path() . '/', 'samesite' => 'Lax', 'httponly' => true]);
    back('/');
}
