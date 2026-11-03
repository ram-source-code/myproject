<?php
declare(strict_types=1);

/**
 * Location features — fully self-contained (no external geo services).
 *
 * Distances are exact great-circle (haversine) values from a small PHP function registered in SQLite
 * (works on every host — no SQLite math extension needed). A bounding box prefilters rows first, so the
 * function only runs on nearby candidates.
 * The visitor's position comes from the browser (Geolocation API), rounded to 3 decimals (~100 m) before it is
 * sent, and is never stored — except inside a saved-search alert the visitor explicitly creates.
 */

const NEAR_RADII = [1, 2, 3, 5, 10, 25, 50];      // km
const KM_PER_DEG = 111.32;

/** Parse "lat,lng" into rounded floats, or null when absent/invalid. */
function parse_near(mixed $raw): ?array
{
    if (!is_string($raw) || !preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $raw, $m)) {
        return null;
    }
    $lat = round((float) $m[1], 3);
    $lng = round((float) $m[2], 3);
    if (abs($lat) > 85 || abs($lng) > 180) {
        return null;
    }
    return [$lat, $lng];
}

function near_string(float $lat, float $lng): string
{
    return number_format($lat, 3, '.', '') . ',' . number_format($lng, 3, '.', '');
}

/** Great-circle distance (haversine) in km — used in PHP for display and alert matching. */
function distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371.0088;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

/** "650 m" / "1.2 km" / "12 km" */
function format_distance(float $km): string
{
    if ($km < 1) {
        return (string) max(50, (int) (round($km * 1000 / 50) * 50)) . ' ' . t('unit_m');
    }
    return ($km < 10 ? number_format($km, 1) : (string) (int) round($km)) . ' ' . t('unit_km');
}

/** Register dist_km(lat1, lng1, lat2, lng2) on the SQLite connection (PHP 8.4+ and older APIs). */
function register_geo_functions(PDO $pdo): void
{
    $fn = static fn($a, $b, $c, $d) => ($a === null || $b === null || $c === null || $d === null)
        ? null : distance_km((float) $a, (float) $b, (float) $c, (float) $d);
    if (method_exists($pdo, 'createFunction')) {                       // Pdo\Sqlite (PHP 8.4+)
        $pdo->createFunction('dist_km', $fn, 4, defined('PDO::SQLITE_DETERMINISTIC') ? PDO::SQLITE_DETERMINISTIC : 0);
    } elseif (method_exists($pdo, 'sqliteCreateFunction')) {
        $pdo->sqliteCreateFunction('dist_km', $fn, 4, defined('PDO::SQLITE_DETERMINISTIC') ? PDO::SQLITE_DETERMINISTIC : 0);
    }
}

/**
 * SQL fragments for "within $radiusKm of ($lat,$lng)": [where-sql, where-args, order-sql, order-args].
 * A bounding box (index-friendly) narrows candidates, then the exact haversine distance decides.
 */
function geo_sql(float $lat, float $lng, float $radiusKm, string $alias = 'p'): array
{
    $cos = max(0.01, cos(deg2rad($lat)));
    $dLat = $radiusKm / KM_PER_DEG * 1.01;
    $dLng = $radiusKm / (KM_PER_DEG * $cos) * 1.01;
    $where = "$alias.lat IS NOT NULL AND $alias.lng IS NOT NULL AND $alias.lat BETWEEN ? AND ? AND $alias.lng BETWEEN ? AND ?"
        // CAST: PDO binds parameters as TEXT, and SQLite would compare a number < text as always true.
        . " AND dist_km($alias.lat, $alias.lng, ?, ?) <= CAST(? AS REAL)";
    $whereArgs = [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng, $lat, $lng, $radiusKm];
    return [$where, $whereArgs, "dist_km($alias.lat, $alias.lng, ?, ?) ASC", [$lat, $lng]];
}

/** Add distance_km to each row (when the row has coordinates). */
function with_distances(array $rows, float $lat, float $lng): array
{
    foreach ($rows as &$r) {
        if ($r['lat'] !== null && $r['lng'] !== null) {
            $r['distance_km'] = distance_km($lat, $lng, (float) $r['lat'], (float) $r['lng']);
        }
    }
    return $rows;
}

/** Published listings nearest to a point (optionally excluding one id). */
function nearby_properties(float $lat, float $lng, float $radiusKm, int $limit, ?int $excludeId = null): array
{
    [$gw, $ga, $go, $goa] = geo_sql($lat, $lng, $radiusKm);
    $sql = "SELECT p.id FROM properties p WHERE p.status = 'published' AND $gw" . ($excludeId ? ' AND p.id != ?' : '') . " ORDER BY $go, p.id DESC LIMIT ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([...$ga, ...($excludeId ? [$excludeId] : []), ...$goa, $limit]);
    return with_distances(properties_by_ids($stmt->fetchAll(PDO::FETCH_COLUMN)), $lat, $lng);
}

/**
 * "Recommended for you" from listings the visitor viewed/saved on this device (ids only, sent by the browser).
 * Builds a taste profile (cities, types, purposes, price band, centre of interest) and scores other listings.
 */
function recommend_properties(array $seedIds, int $limit = 8, ?array $near = null): array
{
    $seedIds = array_values(array_unique(array_filter(array_map('intval', $seedIds), static fn($i) => $i > 0)));
    $seedIds = array_slice($seedIds, 0, 20);
    if (!$seedIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($seedIds), '?'));
    $stmt = db()->prepare("SELECT id, city_id, type, purpose, price_usd, lat, lng FROM properties WHERE id IN ($in)");
    $stmt->execute($seedIds);
    $seeds = $stmt->fetchAll();
    if (!$seeds) {
        return [];
    }
    $count = static function (array $vals): array { $c = array_count_values(array_filter($vals, static fn($v) => $v !== null && $v !== '')); arsort($c); return $c; };
    $cities = $count(array_map(static fn($s) => (string) $s['city_id'], $seeds));
    $types = $count(array_column($seeds, 'type'));
    $purposes = $count(array_column($seeds, 'purpose'));
    $prices = array_map('intval', array_column($seeds, 'price_usd'));
    sort($prices);
    $median = $prices[intdiv(count($prices), 2)] ?: 1;
    $geo = array_values(array_filter($seeds, static fn($s) => $s['lat'] !== null));
    $centre = $near ?? ($geo ? [array_sum(array_column($geo, 'lat')) / count($geo), array_sum(array_column($geo, 'lng')) / count($geo)] : null);

    // Candidate pool: same purpose(s) and city/type overlap, newest first — scored in PHP (small, bounded set).
    $topPurpose = (string) array_key_first($purposes);
    $cityIds = array_map('intval', array_keys(array_slice($cities, 0, 3, true)));
    $typeKeys = array_keys(array_slice($types, 0, 3, true));
    $args = [$topPurpose];
    $cond = [];
    if ($cityIds) { $cond[] = 'p.city_id IN (' . implode(',', array_fill(0, count($cityIds), '?')) . ')'; array_push($args, ...$cityIds); }
    if ($typeKeys) { $cond[] = 'p.type IN (' . implode(',', array_fill(0, count($typeKeys), '?')) . ')'; array_push($args, ...$typeKeys); }
    // Score on light columns only; full cards are loaded for the winners at the end.
    $sql = "SELECT p.id, p.city_id, p.type, p.price_usd, p.lat, p.lng, p.is_featured, p.created_at FROM properties p WHERE p.status = 'published' AND p.purpose = ? " . ($cond ? 'AND (' . implode(' OR ', $cond) . ')' : '')
        . ' AND p.id NOT IN (' . $in . ') ORDER BY p.created_at DESC LIMIT 300';
    $stmt = db()->prepare($sql);
    $stmt->execute([...$args, ...$seedIds]);

    $maxCity = max($cities ?: [1]);
    $maxType = max($types ?: [1]);
    $scored = [];
    foreach ($stmt->fetchAll() as $p) {
        $s = 0.0;
        $s += 3.0 * (($cities[(string) $p['city_id']] ?? 0) / $maxCity);
        $s += 3.0 * (($types[$p['type']] ?? 0) / $maxType);
        $ratio = max(1, (int) $p['price_usd']) / $median;
        $s += 2.0 * max(0, 1 - abs(log($ratio)) / log(2.5));          // within ×2.5 of the median price
        if ($centre && $p['lat'] !== null) {
            $d = distance_km($centre[0], $centre[1], (float) $p['lat'], (float) $p['lng']);
            $s += 2.0 * max(0, 1 - $d / 15);                            // within ~15 km of where they look
            $p['distance_km'] = $near ? $d : null;
        }
        $s += $p['is_featured'] ? 0.5 : 0;
        $s += 0.5 * max(0, 1 - (time() - strtotime($p['created_at'] . ' UTC')) / (86400 * 60));   // fresher is better
        $p['_score'] = $s;
        $scored[] = $p;
    }
    usort($scored, static fn($a, $b) => $b['_score'] <=> $a['_score']);
    $top = array_slice($scored, 0, $limit);
    $meta = array_column($top, null, 'id');
    $rows = properties_by_ids(array_column($top, 'id'));
    foreach ($rows as &$r) {
        $r['distance_km'] = $meta[$r['id']]['distance_km'] ?? null;
        $r['_score'] = $meta[$r['id']]['_score'];
    }
    unset($r);
    return $rows;
}
