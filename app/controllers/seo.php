<?php
declare(strict_types=1);

/* ───────────── SEO: canonical/hreflang, JSON-LD, Open Graph, sitemap, city pages, share images ───────────── */

/** hreflang alternates for the current page (same URL, ?lang=xx). */
function seo_alternates(): array
{
    $q = $_GET;
    unset($q['lang']);
    $path = base_path() . current_path();
    $out = [];
    foreach (SUPPORTED_LANGS as $l) {
        $out[$l] = absolute_origin() . $path . '?' . http_build_query($q + ['lang' => $l]);
    }
    $out['x-default'] = absolute_origin() . $path . ($q ? '?' . http_build_query($q) : '');
    return $out;
}

function property_seo(array $p): array
{
    $url = property_url($p, true);
    $desc = str_limit(trim(preg_replace('/\s+/u', ' ', price_label($p) . ' · ' . t('type_' . $p['type']) . ' · '
        . (float) $p['area'] . ' ' . t('sqm') . ' · ' . city_name($p) . '. ' . $p['description']) ?? ''), 200);
    $image = absolute_origin() . url('og/' . (int) $p['id'] . '.jpg');
    $ld = [
        '@context'    => 'https://schema.org',
        '@type'       => 'RealEstateListing',
        'name'        => $p['title'],
        'url'         => $url,
        'description' => $desc,
        'datePosted'  => fmt_date($p['created_at']),
        'image'       => [$image],
        'offers'      => [
            '@type'         => 'Offer',
            'price'         => (int) $p['price'],
            'priceCurrency' => $p['currency'] ?? 'USD',
            'availability'  => $p['status'] === 'published' ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'businessFunction' => $p['purpose'] === 'rent' ? 'https://purl.org/goodrelations/v1#LeaseOut' : 'https://purl.org/goodrelations/v1#Sell',
        ],
        'about' => array_filter([
            '@type'          => $p['type'] === 'apartment' ? 'Apartment' : ($p['type'] === 'land' ? 'Landform' : 'House'),
            'floorSize'      => ['@type' => 'QuantitativeValue', 'value' => (float) $p['area'], 'unitCode' => 'MTK'],
            'numberOfRooms'  => (int) $p['bedrooms'] ?: null,
            'address'        => ['@type' => 'PostalAddress', 'addressLocality' => $p['city_en'] ?? '', 'streetAddress' => $p['district_en'] ?: $p['district'], 'addressRegion' => 'Kurdistan Region', 'addressCountry' => 'IQ'],
            'geo'            => $p['lat'] !== null ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $p['lat'], 'longitude' => (float) $p['lng']] : null,
        ]),
    ];
    if (!empty($p['office_name'])) {
        $ld['offers']['seller'] = ['@type' => 'RealEstateAgent', 'name' => $p['office_name'], 'url' => absolute_origin() . url('office/' . $p['office_id'])];
    }
    return [
        'canonical' => $url,
        'og'        => ['type' => 'product', 'title' => $p['title'] . ' — ' . price_label($p), 'description' => $desc, 'image' => $image, 'url' => $url,
                        'image_width' => 1200, 'image_height' => 630],
        'jsonld'    => $ld,
    ];
}

/** JSON for a <script type="application/ld+json"> block — safe against </script> breakouts. */
function jsonld(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/* ───────────── City landing pages: /city/erbil ───────────── */

function city_page(string $slug): void
{
    $stmt = db()->prepare('SELECT * FROM cities WHERE slug = ?');
    $stmt->execute([$slug]);
    $city = $stmt->fetch();
    if (!$city) {
        abort(404);
    }
    $_GET['city'] = (string) $city['id'];
    $purpose = in_array($_GET['purpose'] ?? '', PROPERTY_PURPOSES, true) ? $_GET['purpose'] : '';
    $name = city_name($city);
    properties_index([
        'pageTitle' => t($purpose === 'rent' ? 'city_title_rent' : ($purpose === 'sale' ? 'city_title_sale' : 'city_title'), ['city' => $name]) . ' — ' . app_name(),
        'heading'   => t($purpose === 'rent' ? 'city_title_rent' : ($purpose === 'sale' ? 'city_title_sale' : 'city_title'), ['city' => $name]),
        'metaDesc'  => t('city_meta', ['city' => $name]),
    ]);
}

/* ───────────── sitemap.xml / robots.txt ───────────── */

function sitemap_xml(): void
{
    // Rebuilt at most hourly: with tens of thousands of listings, building it costs far more than serving a file.
    $cache = dirname((string) config('db_path')) . '/sitemap-' . substr(hash('sha256', absolute_origin() . base_path()), 0, 12) . '.xml';
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    if (is_file($cache) && filemtime($cache) > time() - 3600) {
        readfile($cache);
        exit;
    }
    ob_start();
    $o = absolute_origin();
    $urls = [[$o . url('/'), null], [$o . url('properties'), null], [$o . url('offices'), null],
             [$o . url('privacy'), '2026-11-03'], [$o . url('terms'), '2026-11-02']];
    foreach (db()->query("SELECT DISTINCT c.slug FROM cities c JOIN properties p ON p.city_id = c.id AND p.status = 'published'") as $c) {
        $urls[] = [$o . url('city/' . $c['slug']), null];
    }
    foreach (db()->query("SELECT o.id FROM offices o JOIN users u ON u.id = o.user_id AND u.status = 'active' WHERE o.activated_at IS NOT NULL") as $r) {
        $urls[] = [$o . url('office/' . $r['id']), null];
    }
    foreach (db()->query("SELECT id, title, updated_at FROM properties WHERE status = 'published' ORDER BY id DESC LIMIT 45000") as $p) {
        $urls[] = [property_url($p, true), substr($p['updated_at'], 0, 10)];
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $mod]) {
        echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . "</url>\n";
    }
    echo "</urlset>\n";
    $xml = (string) ob_get_flush();
    $tmp = $cache . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $xml) !== false) {
        @rename($tmp, $cache);       // atomic: a crawler never sees a half-written file
    }
    exit;
}

/** Only effective when installed at the domain root; for a sub-folder add the Sitemap line to the root robots.txt. */
function robots_txt(): void
{
    $b = base_path();
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    // The admin path is NOT listed (robots.txt is public recon material); private pages send X-Robots-Tag: noindex.
    foreach (['dashboard', 'login', 'register', 'push', 'api', 'lang'] as $p) {
        echo "Disallow: $b/$p\n";
    }
    echo 'Sitemap: ' . absolute_origin() . url('sitemap.xml') . "\n";
    exit;
}

/* ───────────── Share preview image (1200×630 JPEG) for WhatsApp / Telegram / Facebook ───────────── */

function og_image(int $id): void
{
    $p = find_property($id);
    if (!$p || !in_array($p['status'], ['published', 'sold'], true)) {
        abort(404);
    }
    $stmt = db()->prepare('SELECT filename FROM property_images WHERE property_id = ? ORDER BY sort_order, id LIMIT 1');
    $stmt->execute([$id]);
    $cover = (string) $stmt->fetchColumn();
    $dir = config('upload_dir') . '/og';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Cache key changes whenever price, cover, office logo or the listing changes
    $key = substr(md5(implode('|', [$p['price'], $p['currency'], $p['status'], $cover, $p['office_logo'], $p['updated_at'], $p['area'], $p['bedrooms']])), 0, 12);
    $file = "$dir/$id-$key.jpg";
    if (!is_file($file)) {
        foreach (glob("$dir/$id-*.jpg") ?: [] as $old) {
            @unlink($old);
        }
        render_og_image($p, $cover, $file);
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

function render_og_image(array $p, string $cover, string $file): void
{
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    imagefill($im, 0, 0, imagecolorallocate($im, 15, 59, 56));
    $src = $cover !== '' && preg_match('/^[a-f0-9]{32}\.webp$/', $cover) ? @imagecreatefromwebp(config('upload_dir') . '/' . $cover) : false;
    if ($src instanceof GdImage) {
        $crop = resize_cover($src, $W, $H);
        imagecopy($im, $crop, 0, 0, 0, 0, $W, $H);
        imagedestroy($crop);
        imagedestroy($src);
    }
    imagealphablending($im, true);
    // bottom gradient for legible text
    for ($y = 300; $y < $H; $y++) {
        $a = (int) max(0, 127 - ($y - 300) * 0.42 * 127 / 127);
        imageline($im, 0, $y, $W, $y, imagecolorallocatealpha($im, 2, 12, 20, max(20, $a)));
    }
    $bold = dirname(__DIR__) . '/fonts/Inter-Bold.ttf';
    $med = dirname(__DIR__) . '/fonts/Inter-Medium.ttf';
    $white = imagecolorallocate($im, 255, 255, 255);
    $mint = imagecolorallocate($im, 94, 234, 212);

    // Purpose pill
    $pill = $p['purpose'] === 'rent' ? 'FOR RENT' : 'FOR SALE';
    if ($p['status'] === 'sold') {
        $pill = $p['purpose'] === 'rent' ? 'RENTED' : 'SOLD';
    }
    $bb = imagettfbbox(26, 0, $bold, $pill);
    $pw = $bb[2] - $bb[0] + 40;
    imagefilledrectangle($im, 56, 410, 56 + $pw, 460, $p['purpose'] === 'rent' ? imagecolorallocate($im, 29, 78, 216) : imagecolorallocate($im, 15, 118, 110));
    imagettftext($im, 26, 0, 76, 447, $white, $bold, $pill);

    // Price (Latin digits render correctly with GD; Kurdish/Arabic text would need shaping, so it is not drawn)
    $price = ($p['currency'] ?? 'USD') === 'IQD' ? number_format((float) $p['price']) . ' IQD' : '$' . number_format((float) $p['price']);
    if ($p['purpose'] === 'rent' && $p['rent_period']) {
        $price .= ' / ' . ['monthly' => 'month', 'yearly' => 'year', 'daily' => 'day'][$p['rent_period']];
    }
    imagettftext($im, 64, 0, 56, 548, $white, $bold, $price);

    $facts = [];
    if ((int) $p['bedrooms']) { $facts[] = (int) $p['bedrooms'] . ' beds'; }
    if ((int) $p['bathrooms']) { $facts[] = (int) $p['bathrooms'] . ' baths'; }
    $facts[] = number_format((float) $p['area']) . ' m²';
    if (!empty($p['city_en'])) { $facts[] = $p['city_en']; }
    imagettftext($im, 30, 0, 58, 598, $mint, $med, implode('  ·  ', $facts));

    // Office logo (or site icon) top corner
    $logoPath = !empty($p['office_logo']) && preg_match('/^[a-f0-9]{32}\.webp$/', $p['office_logo'])
        ? config('upload_dir') . '/' . $p['office_logo'] : dirname(__DIR__, 2) . '/assets/img/icon-192.png';
    $logo = str_ends_with($logoPath, '.webp') ? @imagecreatefromwebp($logoPath) : @imagecreatefrompng($logoPath);
    if ($logo instanceof GdImage) {
        imagefilledrectangle($im, 40, 40, 160, 160, $white);
        imagecopyresampled($im, $logo, 46, 46, 0, 0, 108, 108, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
    imagettftext($im, 26, 0, $W - 210, 92, $white, $bold, 'AQARAT');
    imagejpeg($im, $file, 82);
    imagedestroy($im);
}


/* ───────────── Legal pages ───────────── */

function legal_page(string $doc): void
{
    $email = preg_replace('/^mailto:/', '', (string) config('push_subject'));
    render('legal/page', [
        'pageTitle' => t($doc === 'privacy' ? 'privacy_policy' : 'terms') . ' — ' . app_name(),
        'heading'   => t($doc === 'privacy' ? 'privacy_policy' : 'terms'),
        'doc'       => $doc,
        'updated'   => $doc === 'privacy' ? '2026-11-03' : '2026-11-02',
        'email'     => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'admin@example.com',
    ]);
}

function privacy_page(): void { legal_page('privacy'); }
function terms_page(): void { legal_page('terms'); }
