<?php
declare(strict_types=1);

/* ───────────── Follow offices / push subscription API (JSON, CSRF via X-CSRF-Token) ───────────── */

function json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 16384);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function push_follow(): void
{
    rate_limit('push', 120, 600);
    $in = json_body();
    $officeId = (int) ($in['office_id'] ?? 0);
    if (!$officeId || !find_office($officeId)) {
        json_out(['ok' => false, 'error' => 'office'], 404);
    }
    try {
        $sid = upsert_subscription((array) ($in['subscription'] ?? []), current_user()['id'] ?? null);
    } catch (InvalidArgumentException) {
        json_out(['ok' => false, 'error' => t('push_unsupported')], 422);
    }
    db()->prepare('INSERT OR IGNORE INTO office_follows (subscription_id, office_id) VALUES (?, ?)')->execute([$sid, $officeId]);
    json_out(['ok' => true, 'following' => true, 'followers' => follower_count($officeId), 'label' => t('following')]);
}

function push_unfollow(): void
{
    $in = json_body();
    $officeId = (int) ($in['office_id'] ?? 0);
    $endpoint = (string) ($in['endpoint'] ?? '');
    db()->prepare('DELETE FROM office_follows WHERE office_id = ? AND subscription_id = (SELECT id FROM push_subscriptions WHERE endpoint = ?)')
        ->execute([$officeId, $endpoint]);
    json_out(['ok' => true, 'following' => false, 'followers' => follower_count($officeId), 'label' => t('follow')]);
}

function push_news(): void
{
    $in = json_body();
    $enabled = !empty($in['enabled']) ? 1 : 0;
    if ($enabled) {
        try {
            $sid = upsert_subscription((array) ($in['subscription'] ?? []), current_user()['id'] ?? null);
        } catch (InvalidArgumentException) {
            json_out(['ok' => false, 'error' => t('push_unsupported')], 422);
        }
        db()->prepare('UPDATE push_subscriptions SET site_news = 1 WHERE id = ?')->execute([$sid]);
    } else {
        db()->prepare('UPDATE push_subscriptions SET site_news = 0 WHERE endpoint = ?')->execute([(string) ($in['endpoint'] ?? '')]);
    }
    json_out(['ok' => true, 'site_news' => (bool) $enabled]);
}

/** Save the current search as an alert for this device. */
function push_search_save(): void
{
    rate_limit('push', 120, 600);
    $in = json_body();
    $f = compact_filters(search_filters(is_array($in['query'] ?? null) ? $in['query'] : []));
    try {
        $sid = upsert_subscription((array) ($in['subscription'] ?? []), current_user()['id'] ?? null);
    } catch (InvalidArgumentException) {
        json_out(['ok' => false, 'error' => t('push_unsupported')], 422);
    }
    $count = db()->prepare('SELECT COUNT(*) FROM saved_searches WHERE subscription_id = ?');
    $count->execute([$sid]);
    if ((int) $count->fetchColumn() >= (int) config('saved_search_limit')) {
        json_out(['ok' => false, 'error' => t('search_limit', ['n' => config('saved_search_limit')])], 422);
    }
    ksort($f);
    $label = search_label($f);
    db()->prepare('INSERT OR IGNORE INTO saved_searches (subscription_id, filters, label) VALUES (?,?,?)')
        ->execute([$sid, json_encode($f, JSON_UNESCAPED_UNICODE), $label]);
    json_out(['ok' => true, 'label' => $label, 'message' => t('search_saved')]);
}

function push_search_delete(): void
{
    $in = json_body();
    db()->prepare('DELETE FROM saved_searches WHERE id = ? AND subscription_id = (SELECT id FROM push_subscriptions WHERE endpoint = ?)')
        ->execute([(int) ($in['id'] ?? 0), (string) ($in['endpoint'] ?? '')]);
    json_out(['ok' => true]);
}

/** What does this device follow? Also returns a small feed of recent notifications for the inbox page. */
function push_status(): void
{
    $endpoint = (string) (json_body()['endpoint'] ?? '');
    $stmt = db()->prepare('SELECT id, site_news FROM push_subscriptions WHERE endpoint = ?');
    $stmt->execute([$endpoint]);
    $sub = $stmt->fetch();
    if (!$sub) {
        json_out(['ok' => true, 'subscribed' => false, 'site_news' => false, 'offices' => [], 'feed' => []]);
    }
    db()->prepare("UPDATE push_subscriptions SET last_seen_at = datetime('now') WHERE id = ?")->execute([$sub['id']]);

    $stmt = db()->prepare('SELECT o.id, o.name, o.logo FROM offices o JOIN office_follows f ON f.office_id = o.id WHERE f.subscription_id = ? ORDER BY o.name');
    $stmt->execute([$sub['id']]);
    $offices = array_map(static fn($o) => [
        'id'   => (int) $o['id'],
        'name' => $o['name'],
        'url'  => url('office/' . $o['id']),
        'logo' => $o['logo'] ? upload_url($o['logo']) : null,
    ], $stmt->fetchAll());

    $stmt = db()->prepare("SELECT n.title, n.body, n.url, n.created_at FROM notifications n
        WHERE (n.audience = 'followers' AND n.office_id IN (SELECT office_id FROM office_follows WHERE subscription_id = ?))
           OR (n.audience = 'all' AND ? = 1)
           OR (n.audience = 'search' AND n.subscription_id = ?)
        ORDER BY n.created_at DESC, n.id DESC LIMIT 30");
    $stmt->execute([$sub['id'], (int) $sub['site_news'], $sub['id']]);
    $feed = array_map(static fn($n) => [
        'title' => $n['title'],
        'body'  => $n['body'],
        'url'   => url($n['url']),
        'date'  => fmt_date($n['created_at']),
    ], $stmt->fetchAll());

    $stmt = db()->prepare('SELECT id, filters, label, hits FROM saved_searches WHERE subscription_id = ? ORDER BY created_at DESC');
    $stmt->execute([$sub['id']]);
    $searches = array_map(static function ($s) {
        $f = json_decode((string) $s['filters'], true) ?: [];
        return ['id' => (int) $s['id'], 'label' => search_label($f), 'hits' => (int) $s['hits'], 'url' => url('properties', $f)];
    }, $stmt->fetchAll());

    json_out(['ok' => true, 'subscribed' => true, 'site_news' => (bool) $sub['site_news'], 'offices' => $offices, 'feed' => $feed, 'searches' => $searches]);
}

function follower_count(int $officeId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM office_follows WHERE office_id = ?');
    $stmt->execute([$officeId]);
    return (int) $stmt->fetchColumn();
}

/** People's notification centre (state is per device, rendered by JS). */
function notifications_page(): void
{
    render('notifications', ['pageTitle' => t('notifications') . ' — ' . app_name()]);
}

/* ───────────── PWA ───────────── */

function pwa_manifest(): void
{
    $base = base_path();
    $icons = [];
    foreach ([192, 512] as $s) {
        $icons[] = ['src' => "$base/assets/img/icon-$s.png", 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any'];
    }
    $icons[] = ['src' => "$base/assets/img/icon-maskable-512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];

    while (ob_get_level() > 0) {      // nothing (a stray BOM/whitespace from an edited config) may precede the JSON
        ob_end_clean();
    }
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo json_encode([
        'id'               => "$base/",
        'name'             => app_name() . ' — ' . t('tagline'),
        'short_name'       => app_name(),
        'description'      => t('meta_description'),
        'lang'             => lang(),
        'dir'              => dir_attr(),
        'start_url'        => "$base/?source=pwa",
        'scope'            => "$base/",
        'display'          => 'standalone',
        'orientation'      => 'any',              // tablets rotate freely
        'background_color' => '#f6f7f9',
        'theme_color'      => '#0f766e',
        'categories'       => ['lifestyle', 'shopping', 'business'],
        'icons'            => $icons,
        'display_override' => ['standalone', 'minimal-ui'],
        'launch_handler'   => ['client_mode' => 'navigate-existing'],
        'shortcuts'        => [
            ['name' => t('nav_buy'), 'url' => "$base/properties?purpose=sale", 'icons' => [['src' => "$base/assets/img/icon-192.png", 'sizes' => '192x192']]],
            ['name' => t('nav_rent'), 'url' => "$base/properties?purpose=rent", 'icons' => [['src' => "$base/assets/img/icon-192.png", 'sizes' => '192x192']]],
            ['name' => t('offices'), 'url' => "$base/offices", 'icons' => [['src' => "$base/assets/img/icon-192.png", 'sizes' => '192x192']]],
            ['name' => t('notifications'), 'url' => "$base/notifications", 'icons' => [['src' => "$base/assets/img/icon-192.png", 'sizes' => '192x192']]],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function offline_page(): void
{
    render('offline', ['pageTitle' => t('offline_title') . ' — ' . app_name()]);
}

/* ───────────── Map data ───────────── */

function api_map(): void
{
    $rows = map_properties(search_filters($_GET));
    header('Cache-Control: private, max-age=60');
    json_out(['ok' => true, 'items' => array_map(static fn($p) => [
        'id'    => (int) $p['id'],
        'title' => $p['title'],
        'price' => price_label($p),
        'short' => short_price($p),
        'lat'   => (float) $p['lat'],
        'lng'   => (float) $p['lng'],
        'beds'  => (int) $p['bedrooms'],
        'area'  => (float) $p['area'],
        'type'  => t('type_' . $p['type']),
        'url'   => property_url($p),
        'dist'  => isset($p['distance_km']) ? format_distance((float) $p['distance_km']) : null,
        'thumb' => $p['cover_thumb'] ? upload_url($p['cover_thumb']) : null,
    ], $rows)]);
}

/** Compact price for map pins: $185K, $1.2M, 95M IQD … */
function short_price(array $p): string
{
    $n = (float) $p['price'];
    $fmt = static fn(float $v, string $suf) => rtrim(rtrim(number_format($v, $v < 10 ? 1 : 0, '.', ''), '0'), '.') . $suf;
    $s = match (true) {
        $n >= 1_000_000_000 => $fmt($n / 1_000_000_000, 'B'),
        $n >= 1_000_000 => $fmt($n / 1_000_000, 'M'),
        $n >= 1_000 => $fmt($n / 1_000, 'K'),
        default => (string) (int) $n,
    };
    return ($p['currency'] ?? 'USD') === 'IQD' ? $s . ' IQD' : '$' . $s;
}
