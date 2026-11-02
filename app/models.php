<?php
declare(strict_types=1);

/**
 * Data access for properties. All SQL uses bound parameters.
 */

const PROPERTY_SELECT = "
    SELECT p.*, c.name_ar AS city_ar, c.name_en AS city_en, c.name_ku AS city_ku,
           u.name AS owner_name, u.phone AS owner_phone, u.role AS owner_role,
           o.id AS office_id, o.name AS office_name, o.slug AS office_slug, o.logo AS office_logo,
           o.is_verified AS office_verified, o.phone AS office_phone, o.whatsapp AS office_whatsapp,
           (SELECT thumb FROM property_images i WHERE i.property_id = p.id ORDER BY sort_order, id LIMIT 1) AS cover_thumb,
           (SELECT color FROM property_images i WHERE i.property_id = p.id ORDER BY sort_order, id LIMIT 1) AS cover_color,
           (SELECT COUNT(*) FROM property_images i WHERE i.property_id = p.id) AS image_count
    FROM properties p
    LEFT JOIN cities c ON c.id = p.city_id
    JOIN users u ON u.id = p.user_id
    LEFT JOIN offices o ON o.user_id = p.user_id
";

/** Build an FTS5 MATCH expression from free text (each term is quoted → no syntax injection). */
function fts_query(string $q): string
{
    $terms = preg_split('/[\s,،]+/u', mb_substr($q, 0, 100), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $terms = array_slice($terms, 0, 8);
    return implode(' ', array_map(static fn($t) => '"' . str_replace('"', '""', $t) . '"*', $terms));
}

/**
 * Parse + whitelist search filters from the query string.
 */
function search_filters(array $src): array
{
    $f = [
        'q'         => mb_substr(trim((string) ($src['q'] ?? '')), 0, 100),
        'purpose'   => in_array($src['purpose'] ?? '', PROPERTY_PURPOSES, true) ? $src['purpose'] : '',
        'type'      => in_array($src['type'] ?? '', PROPERTY_TYPES, true) ? $src['type'] : '',
        'city'      => input_int('city', null, $src),
        'min_price' => input_int('min_price', null, $src),
        'max_price' => input_int('max_price', null, $src),
        'min_area'  => input_int('min_area', null, $src),
        'beds'      => input_int('beds', null, $src),
        'baths'     => input_int('baths', null, $src),
        'furnished' => !empty($src['furnished']) ? 1 : null,
        'deed'      => in_array($src['deed'] ?? '', DEED_TYPES, true) ? $src['deed'] : '',
        'office'    => input_int('office', null, $src),
        'sort'      => in_array($src['sort'] ?? '', ['newest', 'price_asc', 'price_desc', 'area_desc', 'popular'], true) ? $src['sort'] : 'newest',
    ];
    return $f;
}

function search_where(array $f): array
{
    $where = ["p.status = 'published'"];
    $args = [];

    if ($f['q'] !== '' && ($m = fts_query($f['q'])) !== '') {
        $where[] = 'p.id IN (SELECT rowid FROM properties_fts WHERE properties_fts MATCH ?)';
        $args[] = $m;
    }
    foreach (['purpose' => 'p.purpose = ?', 'type' => 'p.type = ?', 'city' => 'p.city_id = ?'] as $k => $sql) {
        if (!empty($f[$k])) {
            $where[] = $sql;
            $args[] = $f[$k];
        }
    }
    // Price filters are in USD; IQD listings are compared via their converted price_usd.
    if ($f['min_price'] !== null) { $where[] = 'p.price_usd >= ?'; $args[] = $f['min_price']; }
    if ($f['max_price'] !== null) { $where[] = 'p.price_usd <= ?'; $args[] = $f['max_price']; }
    if ($f['deed'] !== '')        { $where[] = 'p.deed = ?';       $args[] = $f['deed']; }
    if ($f['office'] !== null)    { $where[] = 'p.user_id = (SELECT user_id FROM offices WHERE id = ?)'; $args[] = $f['office']; }
    if ($f['min_area']  !== null) { $where[] = 'p.area >= ?';      $args[] = $f['min_area']; }
    if ($f['beds']      !== null) { $where[] = 'p.bedrooms >= ?';  $args[] = $f['beds']; }
    if ($f['baths']     !== null) { $where[] = 'p.bathrooms >= ?'; $args[] = $f['baths']; }
    if ($f['furnished'])          { $where[] = 'p.furnished = 1'; }
    return [implode(' AND ', $where), $args];
}

function search_order(array $f): string
{
    return match ($f['sort']) {
        'price_asc'  => 'p.price_usd ASC',
        'price_desc' => 'p.price_usd DESC',
        'area_desc'  => 'p.area DESC',
        'popular'    => 'p.views DESC',
        default      => 'p.is_featured DESC, p.created_at DESC',
    };
}

function search_properties(array $f, int $page, int $perPage): array
{
    [$w, $args] = search_where($f);
    $order = search_order($f);
    $cnt = db()->prepare("SELECT COUNT(*) FROM properties p WHERE $w");
    $cnt->execute($args);
    $pg = paginate((int) $cnt->fetchColumn(), $perPage, $page);

    $stmt = db()->prepare(PROPERTY_SELECT . " WHERE $w ORDER BY $order, p.id DESC LIMIT ? OFFSET ?");
    $stmt->execute([...$args, $pg['per_page'], $pg['offset']]);
    return [$stmt->fetchAll(), $pg];
}

/** Lightweight rows for the map view (only listings with coordinates). */
function map_properties(array $f, int $limit = 500): array
{
    [$w, $args] = search_where($f);
    $stmt = db()->prepare("SELECT p.id, p.title, p.price, p.currency, p.purpose, p.rent_period, p.type, p.lat, p.lng, p.bedrooms, p.area,
            (SELECT thumb FROM property_images i WHERE i.property_id = p.id ORDER BY sort_order, id LIMIT 1) AS cover_thumb
        FROM properties p WHERE $w AND p.lat IS NOT NULL AND p.lng IS NOT NULL ORDER BY p.is_featured DESC, p.created_at DESC LIMIT ?");
    $stmt->execute([...$args, $limit]);
    return $stmt->fetchAll();
}


function find_property(int $id): ?array
{
    $stmt = db()->prepare(PROPERTY_SELECT . ' WHERE p.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function property_images(int $propertyId): array
{
    $stmt = db()->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY sort_order, id');
    $stmt->execute([$propertyId]);
    return $stmt->fetchAll();
}

function latest_properties(int $limit, bool $featuredOnly = false): array
{
    $sql = PROPERTY_SELECT . " WHERE p.status = 'published'" . ($featuredOnly ? ' AND p.is_featured = 1' : '')
        . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function similar_properties(array $p, int $limit = 4): array
{
    $stmt = db()->prepare(PROPERTY_SELECT . " WHERE p.status = 'published' AND p.id != ? AND p.purpose = ? AND (p.type = ? OR p.city_id IS ?)
        ORDER BY (p.type = ?) + (p.city_id IS ?) DESC, ABS(p.price_usd - ?) ASC LIMIT ?");
    $stmt->execute([$p['id'], $p['purpose'], $p['type'], $p['city_id'], $p['type'], $p['city_id'], $p['price_usd'], $limit]);
    return $stmt->fetchAll();
}

function can_manage_property(array $p, ?array $user): bool
{
    return $user && ($user['role'] === 'admin' || (int) $p['user_id'] === (int) $user['id']);
}

function can_view_property(array $p, ?array $user): bool
{
    return in_array($p['status'], ['published', 'sold'], true) || can_manage_property($p, $user);
}

/**
 * Validate the listing form. Returns [data, errors].
 */
function validate_property_input(array $in): array
{
    $e = [];
    $d = [];

    $d['title'] = trim((string) ($in['title'] ?? ''));
    $len = mb_strlen($d['title']);
    if ($len < 5 || $len > 150) {
        $e['title'] = t('v_title');
    }
    $d['description'] = trim((string) ($in['description'] ?? ''));
    if (mb_strlen($d['description']) > 5000) {
        $e['description'] = t('v_description');
    }
    $d['purpose'] = (string) ($in['purpose'] ?? '');
    if (!in_array($d['purpose'], PROPERTY_PURPOSES, true)) {
        $e['purpose'] = t('v_required');
    }
    $d['type'] = (string) ($in['type'] ?? '');
    if (!in_array($d['type'], PROPERTY_TYPES, true)) {
        $e['type'] = t('v_required');
    }
    $d['rent_period'] = null;
    if ($d['purpose'] === 'rent') {
        $d['rent_period'] = in_array($in['rent_period'] ?? '', RENT_PERIODS, true) ? $in['rent_period'] : 'yearly';
    }

    $cityIds = array_map(static fn($c) => (int) $c['id'], cities());
    $d['city_id'] = input_int('city_id', null, $in);
    if (!in_array($d['city_id'], $cityIds, true)) {
        $e['city_id'] = t('v_required');
    }
    $d['district'] = mb_substr(trim((string) ($in['district'] ?? '')), 0, 100);
    $d['address']  = mb_substr(trim((string) ($in['address'] ?? '')), 0, 200);

    $d['currency'] = in_array($in['currency'] ?? '', CURRENCIES, true) ? $in['currency'] : 'USD';
    $d['price'] = input_int('price', null, $in);
    if ($d['price'] === null || $d['price'] < 0 || $d['price'] > 1_000_000_000_000) {
        $e['price'] = t('v_price');
    }
    $d['price_usd'] = to_usd((int) $d['price'], $d['currency']);
    $d['deed'] = in_array($in['deed'] ?? '', DEED_TYPES, true) ? $in['deed'] : null;
    $d['project'] = mb_substr(trim((string) ($in['project'] ?? '')), 0, 100);
    $area = convert_arabic_digits((string) ($in['area'] ?? ''));
    $d['area'] = is_numeric($area) ? (float) $area : -1;
    if ($d['area'] <= 0 || $d['area'] > 10_000_000) {
        $e['area'] = t('v_area');
    }

    foreach (['bedrooms' => 50, 'bathrooms' => 50] as $k => $max) {
        $d[$k] = input_int($k, 0, $in) ?? 0;
        if ($d[$k] < 0 || $d[$k] > $max) {
            $e[$k] = t('v_range', ['min' => 0, 'max' => $max]);
        }
    }
    $d['floor'] = input_int('floor', null, $in);
    if ($d['floor'] !== null && ($d['floor'] < -5 || $d['floor'] > 200)) {
        $e['floor'] = t('v_range', ['min' => -5, 'max' => 200]);
    }
    $d['year_built'] = input_int('year_built', null, $in);
    if ($d['year_built'] !== null && ($d['year_built'] < 1900 || $d['year_built'] > (int) date('Y') + 5)) {
        $e['year_built'] = t('v_range', ['min' => 1900, 'max' => (int) date('Y') + 5]);
    }
    $d['furnished'] = !empty($in['furnished']) ? 1 : 0;

    $features = is_array($in['features'] ?? null) ? $in['features'] : [];
    $d['features'] = json_encode(array_values(array_intersect(PROPERTY_FEATURES, $features)));

    foreach (['lat' => 90, 'lng' => 180] as $k => $lim) {
        $v = trim((string) ($in[$k] ?? ''));
        $d[$k] = null;
        if ($v !== '') {
            if (!is_numeric($v) || abs((float) $v) > $lim) {
                $e[$k] = t('v_coord');
            } else {
                $d[$k] = round((float) $v, 6);
            }
        }
    }
    return [$d, $e];
}

/**
 * Attach uploaded images to a property. Returns list of error messages (per file).
 */
function attach_uploaded_images(int $propertyId, array $files): array
{
    $errors = [];
    $stmt = db()->prepare('SELECT COUNT(*), COALESCE(MAX(sort_order), -1) FROM property_images WHERE property_id = ?');
    $stmt->execute([$propertyId]);
    [$count, $maxSort] = $stmt->fetch(PDO::FETCH_NUM);
    $remaining = config('max_images') - (int) $count;

    $ins = db()->prepare('INSERT INTO property_images (property_id, filename, thumb, width, height, sort_order, phash, color) VALUES (?,?,?,?,?,?,?,?)');
    $watermark = watermark_for_property($propertyId);
    $added = 0;
    foreach ($files as $file) {
        $name = mb_substr(basename((string) ($file['name'] ?? '')), 0, 60);
        if ($remaining <= 0) {
            $errors[] = t('img_limit', ['n' => config('max_images')]);
            break;
        }
        try {
            $img = process_image_upload($file, $watermark);
            $ins->execute([$propertyId, $img['filename'], $img['thumb'], $img['width'], $img['height'], ++$maxSort, $img['phash'], $img['color']]);
            $remaining--;
            $added++;
        } catch (ImageUploadException $ex) {
            $errors[] = $name . ': ' . $ex->getMessage();
        }
    }
    if ($added) {
        detect_duplicates($propertyId);
    }
    return $errors;
}

/** Office logo of the listing owner, or the site icon, used as photo watermark. */
function watermark_for_property(int $propertyId): string
{
    $stmt = db()->prepare('SELECT o.logo FROM properties p JOIN offices o ON o.user_id = p.user_id WHERE p.id = ?');
    $stmt->execute([$propertyId]);
    $logo = (string) $stmt->fetchColumn();
    if ($logo !== '' && preg_match('/^[a-f0-9]{32}\.webp$/', $logo) && is_file(config('upload_dir') . '/' . $logo)) {
        return config('upload_dir') . '/' . $logo;
    }
    return dirname(__DIR__) . '/assets/img/icon-192.png';
}

/**
 * Flag listings that look like copies of another one:
 *  - any photo within Hamming distance ≤ 4 of a photo on a different listing, or
 *  - same purpose/type with near-identical coordinates (≈30 m) and price within 5 %.
 */
function detect_duplicates(int $propertyId): int
{
    $pdo = db();
    $mine = $pdo->prepare('SELECT phash FROM property_images WHERE property_id = ? AND phash IS NOT NULL');
    $mine->execute([$propertyId]);
    $hashes = $mine->fetchAll(PDO::FETCH_COLUMN);
    $found = [];
    if ($hashes) {
        $others = $pdo->prepare("SELECT i.property_id, i.phash FROM property_images i JOIN properties p ON p.id = i.property_id
            WHERE i.property_id != ? AND i.phash IS NOT NULL AND p.status != 'rejected'");
        $others->execute([$propertyId]);
        foreach ($others as $row) {
            foreach ($hashes as $h) {
                $d = hash_distance($h, $row['phash']);
                if ($d <= 4 && (!isset($found[$row['property_id']]) || $d < $found[$row['property_id']][1])) {
                    $found[(int) $row['property_id']] = ['photo', $d];
                }
            }
        }
    }
    $p = $pdo->prepare('SELECT * FROM properties WHERE id = ?');
    $p->execute([$propertyId]);
    $p = $p->fetch();
    if ($p && $p['lat'] !== null) {
        $near = $pdo->prepare("SELECT id FROM properties WHERE id != ? AND status != 'rejected' AND purpose = ? AND type = ?
            AND ABS(lat - ?) < 0.0003 AND ABS(lng - ?) < 0.0003 AND ABS(price_usd - ?) <= MAX(1, ? * 0.05)");
        $near->execute([$propertyId, $p['purpose'], $p['type'], $p['lat'], $p['lng'], $p['price_usd'], $p['price_usd']]);
        foreach ($near->fetchAll(PDO::FETCH_COLUMN) as $oid) {
            $found[(int) $oid] ??= ['location', 0];
        }
    }
    $ins = $pdo->prepare('INSERT OR IGNORE INTO duplicate_flags (property_id, other_id, reason, distance) VALUES (?,?,?,?)');
    foreach ($found as $oid => [$reason, $d]) {
        $ins->execute([$propertyId, $oid, $reason, $d]);
    }
    return count($found);
}

function property_videos(int $propertyId): array
{
    $stmt = db()->prepare('SELECT * FROM property_videos WHERE property_id = ? ORDER BY id');
    $stmt->execute([$propertyId]);
    return $stmt->fetchAll();
}

/** @return string[] error messages */
function attach_uploaded_videos(int $propertyId, array $files): array
{
    $errors = [];
    $stmt = db()->prepare('SELECT COUNT(*) FROM property_videos WHERE property_id = ?');
    $stmt->execute([$propertyId]);
    $remaining = (int) config('max_videos') - (int) $stmt->fetchColumn();
    $ins = db()->prepare('INSERT INTO property_videos (property_id, filename, mime, size) VALUES (?,?,?,?)');
    foreach ($files as $file) {
        $name = mb_substr(basename((string) ($file['name'] ?? '')), 0, 60);
        if ($remaining <= 0) {
            $errors[] = t('video_limit', ['n' => config('max_videos')]);
            break;
        }
        try {
            $v = process_video_upload($file);
            $ins->execute([$propertyId, $v['filename'], $v['mime'], $v['size']]);
            $remaining--;
        } catch (ImageUploadException $ex) {
            $errors[] = $name . ': ' . $ex->getMessage();
        }
    }
    return $errors;
}

function delete_property(int $id): void
{
    foreach (property_videos($id) as $v) {
        delete_video_file($v);
    }
    $imgs = property_images($id);
    db()->prepare('DELETE FROM properties WHERE id = ?')->execute([$id]);
    foreach ($imgs as $img) {
        delete_image_files($img);
    }
}
