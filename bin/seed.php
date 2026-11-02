<?php
declare(strict_types=1);

/**
 * Initialise the database: cities, an admin account and (optionally) demo listings.
 *
 *   php bin/seed.php                       # cities + admin (random password printed)
 *   php bin/seed.php --demo                # ... plus ~24 demo listings with generated photos
 *   ADMIN_EMAIL=me@x.com ADMIN_PASSWORD=secret123 php bin/seed.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('AQARAT_CLI', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$demo = in_array('--demo', $argv, true);
$pdo = db();
echo 'journal_mode = ' . $pdo->query('PRAGMA journal_mode')->fetchColumn() . PHP_EOL;

// ---- Cities ----
$cities = [
    ['الرياض', 'Riyadh'], ['جدة', 'Jeddah'], ['مكة المكرمة', 'Makkah'], ['المدينة المنورة', 'Madinah'],
    ['الدمام', 'Dammam'], ['الخبر', 'Khobar'], ['أبها', 'Abha'], ['الطائف', 'Taif'],
    ['تبوك', 'Tabuk'], ['بريدة', 'Buraydah'], ['دبي', 'Dubai'], ['الكويت', 'Kuwait City'],
];
$ins = $pdo->prepare('INSERT OR IGNORE INTO cities (name_ar, name_en, slug) VALUES (?,?,?)');
foreach ($cities as [$ar, $en]) {
    $ins->execute([$ar, $en, trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($en)), '-')]);
}
echo 'cities: ' . $pdo->query('SELECT COUNT(*) FROM cities')->fetchColumn() . PHP_EOL;

// ---- Admin ----
$email = strtolower(getenv('ADMIN_EMAIL') ?: 'admin@aqarat.local');
$password = getenv('ADMIN_PASSWORD') ?: '';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$adminId = (int) $stmt->fetchColumn();
if (!$adminId) {
    $generated = $password === '';
    if ($generated) {
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    }
    $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?, 'admin')")
        ->execute(['مدير الموقع', $email, '+966500000000', password_hash($password, PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    echo "admin created: $email" . ($generated ? "  password: $password  (change it after first login)" : '') . PHP_EOL;
} else {
    echo "admin exists: $email" . PHP_EOL;
}

if (!$demo) {
    exit(0);
}

// ---- Demo agents ----
$agents = [];
foreach ([['شركة الأفق العقارية', 'agent1@aqarat.local', '+966511111111'], ['سارة العتيبي', 'agent2@aqarat.local', '+966522222222']] as [$n, $em, $ph]) {
    $stmt->execute([$em]);
    $id = (int) $stmt->fetchColumn();
    if (!$id) {
        $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?, 'agent')")
            ->execute([$n, $em, $ph, password_hash('demo-password', PASSWORD_DEFAULT)]);
        $id = (int) $pdo->lastInsertId();
    }
    $agents[] = $id;
}
$agents[] = $adminId;

if ((int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn() > 0) {
    echo "properties already exist, skipping demo listings\n";
    exit(0);
}

/** Draw a simple stylised building photo with GD and return a temp JPEG path. */
function demo_photo(int $seed, string $kind): string
{
    mt_srand($seed);
    $w = 1400; $h = 933;
    $im = imagecreatetruecolor($w, $h);
    // sky gradient
    $top = [mt_rand(70, 140), mt_rand(140, 190), mt_rand(200, 245)];
    $bot = [mt_rand(220, 250), mt_rand(200, 230), mt_rand(170, 210)];
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($im, (int) ($top[0] + ($bot[0] - $top[0]) * $t), (int) ($top[1] + ($bot[1] - $top[1]) * $t), (int) ($top[2] + ($bot[2] - $top[2]) * $t));
        imageline($im, 0, $y, $w, $y, $c);
    }
    // sun
    imagefilledellipse($im, mt_rand(150, 1250), mt_rand(90, 220), 120, 120, imagecolorallocatealpha($im, 255, 240, 200, 30));
    // ground
    $ground = $kind === 'land' ? imagecolorallocate($im, 196, 164, 120) : imagecolorallocate($im, 120, 150, 95);
    imagefilledrectangle($im, 0, (int) ($h * 0.78), $w, $h, $ground);

    if ($kind !== 'land') {
        $palette = [[235, 225, 205], [210, 200, 185], [190, 205, 215], [245, 240, 230], [200, 185, 165]];
        $count = $kind === 'villa' ? 1 : mt_rand(2, 4);
        $x = mt_rand(60, 200);
        for ($b = 0; $b < $count; $b++) {
            $bw = $kind === 'villa' ? mt_rand(700, 900) : mt_rand(220, 340);
            $bh = $kind === 'villa' ? mt_rand(300, 380) : mt_rand(350, 640);
            $base = (int) ($h * 0.8);
            $col = $palette[mt_rand(0, count($palette) - 1)];
            $body = imagecolorallocate($im, ...$col);
            $shade = imagecolorallocate($im, (int) ($col[0] * 0.8), (int) ($col[1] * 0.8), (int) ($col[2] * 0.8));
            imagefilledrectangle($im, $x, $base - $bh, $x + $bw, $base, $body);
            imagefilledrectangle($im, $x + $bw - 30, $base - $bh, $x + $bw, $base, $shade);
            $glass = imagecolorallocate($im, mt_rand(60, 100), mt_rand(110, 150), mt_rand(150, 190));
            for ($wy = $base - $bh + 30; $wy < $base - 70; $wy += 70) {
                for ($wx = $x + 25; $wx < $x + $bw - 70; $wx += 60) {
                    imagefilledrectangle($im, $wx, $wy, $wx + 34, $wy + 44, $glass);
                }
            }
            imagefilledrectangle($im, $x + (int) ($bw / 2) - 30, $base - 90, $x + (int) ($bw / 2) + 30, $base, imagecolorallocate($im, 90, 60, 40));
            $x += $bw + mt_rand(30, 80);
            if ($x > $w - 200) {
                break;
            }
        }
    }
    // palm trees
    for ($p = 0; $p < mt_rand(1, 3); $p++) {
        $px = mt_rand(40, $w - 40);
        $py = (int) ($h * 0.82);
        imagesetthickness($im, 14);
        imageline($im, $px, $py, $px + 15, $py - 220, imagecolorallocate($im, 110, 80, 50));
        imagesetthickness($im, 1);
        imagefilledellipse($im, $px + 15, $py - 230, 180, 70, imagecolorallocate($im, 40, 120, 60));
    }
    $tmp = tempnam(sys_get_temp_dir(), 'aq') . '.jpg';
    imagejpeg($im, $tmp, 88);
    imagedestroy($im);
    return $tmp;
}

$cityIds = $pdo->query('SELECT id, name_ar FROM cities')->fetchAll(PDO::FETCH_KEY_PAIR);
$districts = ['النرجس', 'الملقا', 'الياسمين', 'حطين', 'الروضة', 'الشاطئ', 'السلامة', 'العليا', 'الحمراء', 'الفيصلية'];
$templates = [
    ['apartment', 'sale', 'شقة فاخرة :b غرف في حي :d', 750000, 1600000, 110, 220],
    ['apartment', 'rent', 'شقة عائلية للإيجار :b غرف - :d', 35000, 90000, 90, 200],
    ['villa', 'sale', 'فيلا دوبلكس حديثة مع مسبح في :d', 1800000, 4800000, 300, 650],
    ['villa', 'rent', 'فيلا للإيجار السنوي بحي :d', 120000, 260000, 350, 600],
    ['land', 'sale', 'أرض سكنية على شارعين في :d', 600000, 3500000, 400, 1500],
    ['office', 'rent', 'مكتب إداري مجهز على طريق رئيسي - :d', 45000, 150000, 60, 300],
    ['shop', 'rent', 'محل تجاري بواجهة زجاجية في :d', 40000, 120000, 40, 160],
    ['building', 'sale', 'عمارة سكنية استثمارية :b شقق - :d', 3500000, 9000000, 600, 1400],
    ['chalet', 'rent', 'شاليه بإطلالة بحرية ومسبح خاص', 900, 2500, 200, 500],
    ['house', 'sale', 'بيت شعبي مرمم بالكامل في :d', 450000, 900000, 200, 400],
];
$desc = "عقار مميز في موقع حيوي قريب من الخدمات والمدارس والمساجد.\n\nالمواصفات:\n- تشطيب ممتاز ومواد عالية الجودة\n- مداخل مستقلة وإضاءة طبيعية\n- قريب من الطرق الرئيسية\n\nللتواصل والمعاينة يرجى الاتصال أو إرسال استفسار.";

$insP = $pdo->prepare("INSERT INTO properties (user_id, city_id, title, description, purpose, type, district, address, price, rent_period, area,
        bedrooms, bathrooms, floor, year_built, furnished, features, lat, lng, status, is_featured, views, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'published',?,?, datetime('now', ?), datetime('now', ?))");
$n = 0;
mt_srand(42);
for ($i = 0; $i < 24; $i++) {
    [$type, $purpose, $title, $pmin, $pmax, $amin, $amax] = $templates[$i % count($templates)];
    $cityId = array_rand($cityIds);
    $d = $districts[array_rand($districts)];
    $beds = in_array($type, ['land', 'shop', 'office'], true) ? 0 : mt_rand(2, 6);
    $baths = $beds ? max(1, $beds - mt_rand(0, 1)) : ($type === 'office' ? 1 : 0);
    $price = (int) (round(mt_rand($pmin, $pmax) / 1000) * 1000);
    $feats = array_rand(array_flip(PROPERTY_FEATURES), mt_rand(3, 7));
    $age = '-' . mt_rand(0, 60) . ' days';
    $insP->execute([
        $agents[$i % count($agents)], $cityId, strtr($title, [':b' => $beds ?: 4, ':d' => $d]), $desc, $purpose, $type, $d,
        'شارع ' . mt_rand(1, 40), $price, $purpose === 'rent' ? ($type === 'chalet' ? 'daily' : 'yearly') : null,
        mt_rand($amin, $amax), $beds, $baths, in_array($type, ['apartment', 'office'], true) ? mt_rand(1, 12) : null,
        mt_rand(2005, 2025), (int) (mt_rand(0, 3) === 0), json_encode(array_values((array) $feats)),
        round(24.6 + mt_rand(0, 3000) / 10000, 6), round(46.6 + mt_rand(0, 3000) / 10000, 6),
        (int) ($i % 4 === 0), mt_rand(5, 900), $age, $age,
    ]);
    $pid = (int) $pdo->lastInsertId();
    $files = [];
    for ($k = 0; $k < mt_rand(3, 6); $k++) {
        $tmp = demo_photo($i * 10 + $k, $type === 'land' ? 'land' : ($type === 'villa' || $type === 'chalet' || $type === 'house' ? 'villa' : 'tower'));
        $files[] = ['name' => "demo$k.jpg", 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'type' => 'image/jpeg'];
    }
    $errs = attach_uploaded_images($pid, $files);
    foreach ($files as $f) {
        @unlink($f['tmp_name']);
        @unlink(substr($f['tmp_name'], 0, -4));
    }
    if ($errs) {
        fwrite(STDERR, implode("\n", $errs) . "\n");
    }
    $n++;
}
echo "demo listings: $n\n";
