<?php
declare(strict_types=1);

/**
 * Data access for properties. All SQL uses bound parameters.
 */

const PROPERTY_SELECT = "
    SELECT p.*, c.name_ar AS city_ar, c.name_en AS city_en,
           u.name AS owner_name, u.phone AS owner_phone, u.email AS owner_email, u.role AS owner_role,
           (SELECT thumb FROM property_images i WHERE i.property_id = p.id ORDER BY sort_order, id LIMIT 1) AS cover_thumb,
           (SELECT COUNT(*) FROM property_images i WHERE i.property_id = p.id) AS image_count
    FROM properties p
    LEFT JOIN cities c ON c.id = p.city_id
    JOIN users u ON u.id = p.user_id
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
        'sort'      => in_array($src['sort'] ?? '', ['newest', 'price_asc', 'price_desc', 'area_desc', 'popular'], true) ? $src['sort'] : 'newest',
    ];
    return $f;
}

function search_properties(array $f, int $page, int $perPage): array
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
    if ($f['min_price'] !== null) { $where[] = 'p.price >= ?';     $args[] = $f['min_price']; }
    if ($f['max_price'] !== null) { $where[] = 'p.price <= ?';     $args[] = $f['max_price']; }
    if ($f['min_area']  !== null) { $where[] = 'p.area >= ?';      $args[] = $f['min_area']; }
    if ($f['beds']      !== null) { $where[] = 'p.bedrooms >= ?';  $args[] = $f['beds']; }
    if ($f['baths']     !== null) { $where[] = 'p.bathrooms >= ?'; $args[] = $f['baths']; }
    if ($f['furnished'])          { $where[] = 'p.furnished = 1'; }

    $order = match ($f['sort']) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'area_desc'  => 'p.area DESC',
        'popular'    => 'p.views DESC',
        default      => 'p.is_featured DESC, p.created_at DESC',
    };

    $w = implode(' AND ', $where);
    $cnt = db()->prepare("SELECT COUNT(*) FROM properties p WHERE $w");
    $cnt->execute($args);
    $pg = paginate((int) $cnt->fetchColumn(), $perPage, $page);

    $stmt = db()->prepare(PROPERTY_SELECT . " WHERE $w ORDER BY $order, p.id DESC LIMIT ? OFFSET ?");
    $stmt->execute([...$args, $pg['per_page'], $pg['offset']]);
    return [$stmt->fetchAll(), $pg];
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
        ORDER BY (p.type = ?) + (p.city_id IS ?) DESC, ABS(p.price - ?) ASC LIMIT ?");
    $stmt->execute([$p['id'], $p['purpose'], $p['type'], $p['city_id'], $p['type'], $p['city_id'], $p['price'], $limit]);
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

    $d['price'] = input_int('price', null, $in);
    if ($d['price'] === null || $d['price'] < 0 || $d['price'] > 10_000_000_000) {
        $e['price'] = t('v_price');
    }
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

    $ins = db()->prepare('INSERT INTO property_images (property_id, filename, thumb, width, height, sort_order) VALUES (?,?,?,?,?,?)');
    foreach ($files as $file) {
        $name = mb_substr(basename((string) ($file['name'] ?? '')), 0, 60);
        if ($remaining <= 0) {
            $errors[] = t('img_limit', ['n' => config('max_images')]);
            break;
        }
        try {
            $img = process_image_upload($file);
            $ins->execute([$propertyId, $img['filename'], $img['thumb'], $img['width'], $img['height'], ++$maxSort]);
            $remaining--;
        } catch (ImageUploadException $ex) {
            $errors[] = $name . ': ' . $ex->getMessage();
        }
    }
    return $errors;
}

function delete_property(int $id): void
{
    $imgs = property_images($id);
    db()->prepare('DELETE FROM properties WHERE id = ?')->execute([$id]);
    foreach ($imgs as $img) {
        delete_image_files($img);
    }
}
