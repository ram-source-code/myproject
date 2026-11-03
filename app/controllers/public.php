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

function properties_index(array $override = []): void
{
    $f = search_filters($_GET);
    $page = max(1, input_int('page', 1, $_GET) ?? 1);
    [$items, $pg] = search_properties($f, $page, (int) config('listings_per_page'));

    $view = ($_GET['view'] ?? '') === 'map' ? 'map' : 'list';
    render('properties/index', $override + [
        'heading'   => null,
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
    // 301 to the canonical slug URL (old /property/123 links keep working)
    $canonical = property_url($p);
    $requested = base_path() . rawurldecode(current_path());
    if (rawurldecode(strtok($canonical, '?')) !== $requested) {
        header('Location: ' . $canonical, true, 301);
        exit;
    }

    // Count a view once per session per listing — but not for speculative prefetch/prerender
    // (the page reports the view itself via a beacon if the visitor actually opens it).
    $speculative = str_contains(strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? '')), 'prefetch');
    if (!$speculative) {
        count_property_view($p, $user);
    }

    render('properties/show', [
        'nearby'    => $nearby = ($p['lat'] !== null ? nearby_properties((float) $p['lat'], (float) $p['lng'], 3, 4, $id) : []),
        'videos'    => property_videos($id),
        'seo'       => property_seo($p),
        'useMap'    => $p['lat'] !== null && $p['lng'] !== null,
        'office'    => $p['office_id'] ? find_office((int) $p['office_id']) : null,
        'pageTitle' => $p['title'] . ' — ' . app_name(),
        'metaDesc'  => str_limit(preg_replace('/\s+/u', ' ', $p['description']) ?? '', 160),
        'p'         => $p,
        'images'    => property_images($id),
        'similar'   => array_slice(array_values(array_filter(similar_properties($p, 8), static fn($s) => !in_array($s['id'], array_column($nearby, 'id'), true))), 0, 4),
        'features'  => json_decode($p['features'], true) ?: [],
    ]);
}

function property_inquiry(int $id): void
{
    $p = find_property($id);
    if (!$p || $p['status'] !== 'published') {
        abort(404);
    }
    // Honeypot field + minimum fill time: bots fill every input and submit instantly — pretend success
    if (input('website') !== '' || !form_time_ok(3)) {
        flash('success', t('inquiry_sent'));
        redirect(property_url($p));
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
        redirect(property_url($p) . '#contact');
    }
    rate_limit('inquiry', 30, 600);                  // per IP (CGNAT-friendly)
    $perEmail = db()->prepare("SELECT COUNT(*) FROM inquiries WHERE email = ? AND created_at > datetime('now', '-1 hour')");
    $perEmail->execute([$data['email']]);
    if ((int) $perEmail->fetchColumn() >= 10) {
        abort(429);
    }
    db()->prepare('INSERT INTO inquiries (property_id, user_id, name, email, phone, message, ip) VALUES (?,?,?,?,?,?,?)')
        ->execute([$id, $user['id'] ?? null, $data['name'], $data['email'], $data['phone'], $data['message'], client_ip()]);
    bump_stat($id, 'inquiries');
    clear_old();
    flash('success', t('inquiry_sent'));
    redirect(property_url($p) . '#contact');
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


/* ───────────── Report a listing ───────────── */

const REPORT_REASONS = ['sold', 'wrong_price', 'fake', 'duplicate', 'wrong_location', 'offensive', 'other'];

function property_report(int $id): void
{
    $p = find_property($id);
    if (!$p || $p['status'] !== 'published') {
        abort(404);
    }
    $reason = input('reason');
    if (!in_array($reason, REPORT_REASONS, true)) {
        flash('error', t('v_required'));
        redirect(property_url($p) . '#report');
    }
    if (!form_time_ok(2)) {
        flash('success', t('report_thanks'));        // silent for bots
        redirect(property_url($p));
    }
    rate_limit('report', 30, 3600);
    $ip = client_ip();
    $dupe = db()->prepare("SELECT
        (SELECT COUNT(*) FROM reports WHERE ip = ? AND property_id = ?),
        (SELECT COUNT(*) FROM reports WHERE ip = ? AND created_at > datetime('now', '-1 hour'))");
    $dupe->execute([$ip, $id, $ip]);
    [$same, $recent] = $dupe->fetch(PDO::FETCH_NUM);
    if (!$same && $recent < 30) {
        db()->prepare('INSERT INTO reports (property_id, reason, details, ip) VALUES (?,?,?,?)')
            ->execute([$id, $reason, mb_substr(input('details'), 0, 500), $ip]);
    }
    flash('success', t('report_thanks'));
    redirect(property_url($p));
}


function count_property_view(array $p, ?array $user): void
{
    $id = (int) $p['id'];
    $_SESSION['_viewed'] ??= [];
    if (!in_array($id, $_SESSION['_viewed'], true) && !can_manage_property($p, $user)) {
        $_SESSION['_viewed'][] = $id;
        $_SESSION['_viewed'] = array_slice($_SESSION['_viewed'], -200);
        db()->prepare('UPDATE properties SET views = views + 1 WHERE id = ?')->execute([$id]);
        bump_stat($id, 'views');
    }
}

/** Beacon when a visitor taps WhatsApp or Call on a listing — counted once per visitor, never the owner's own taps. */
function property_contact_beacon(int $id): void
{
    $kind = input('kind');
    $p = find_property($id);
    if ($p && $p['status'] === 'published' && in_array($kind, ['wa', 'calls'], true) && !can_manage_property($p, current_user())) {
        $_SESSION['_contacted'] ??= [];
        $k = $id . ':' . $kind;
        if (!in_array($k, $_SESSION['_contacted'], true)) {
            $_SESSION['_contacted'][] = $k;
            $_SESSION['_contacted'] = array_slice($_SESSION['_contacted'], -200);
            bump_stat($id, $kind);
        }
    }
    http_response_code(204);
    exit;
}

/** Beacon from a prerendered page once it is actually shown. */
function property_view_beacon(int $id): void
{
    $p = find_property($id);
    if ($p && $p['status'] === 'published') {
        count_property_view($p, current_user());
    }
    http_response_code(204);
    exit;
}

/** Live "Show N results" count for the filter form. */
function api_count(): void
{
    [$w, $args] = search_where(search_filters($_GET));
    $stmt = db()->prepare("SELECT COUNT(*) FROM properties p WHERE $w");
    $stmt->execute($args);
    $n = (int) $stmt->fetchColumn();
    header('Cache-Control: private, max-age=30');
    json_out(['ok' => true, 'count' => $n, 'label' => t('show_n_results', ['n' => number_format($n)])]);
}


/* ───────────── Location: "near you" row and recommendations (JSON with server-rendered, escaped cards) ───────────── */

function cards_html(array $rows): string
{
    $html = '';
    foreach ($rows as $p) {
        $html .= render_partial('partials/card', ['p' => $p]);
    }
    return $html;
}

/** Nearest listings around the visitor; widens the radius until there is enough to show. */
function api_nearby(): void
{
    $pt = parse_near($_GET['near'] ?? null);
    if ($pt === null) {
        json_out(['ok' => false, 'error' => 'near'], 422);
    }
    $purpose = in_array($_GET['purpose'] ?? '', PROPERTY_PURPOSES, true) ? $_GET['purpose'] : '';
    $rows = [];
    $used = 3;
    foreach ([3, 10, 25, 50] as $r) {
        $used = $r;
        $rows = array_values(array_filter(nearby_properties($pt[0], $pt[1], $r, 12), static fn($p) => $purpose === '' || $p['purpose'] === $purpose));
        if (count($rows) >= 4) {
            break;
        }
    }
    $rows = array_slice($rows, 0, 8);
    header('Cache-Control: private, max-age=120');
    json_out([
        'ok'     => true,
        'count'  => count($rows),
        'radius' => $used,
        'title'  => t('near_you_within', ['r' => $used]),
        'html'   => cards_html($rows),
        'url'    => url('properties', ['near' => near_string($pt[0], $pt[1]), 'r' => $used, 'purpose' => $purpose]),
    ]);
}

/** "Recommended for you" from ids the browser keeps locally (recently viewed + saved). */
function api_recommend(): void
{
    $ids = array_slice(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))), 0, 20);
    $rows = recommend_properties($ids, 8, parse_near($_GET['near'] ?? null));
    header('Cache-Control: private, max-age=120');
    json_out(['ok' => true, 'count' => count($rows), 'html' => cards_html($rows)]);
}
