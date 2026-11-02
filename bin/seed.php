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

// ---- Cities (Kurdistan Region + neighbours) ----
$cities = [
    ['هەولێر', 'أربيل', 'Erbil'], ['سلێمانی', 'السليمانية', 'Sulaymaniyah'], ['دهۆک', 'دهوك', 'Duhok'],
    ['هەڵەبجە', 'حلبجة', 'Halabja'], ['کەرکووک', 'كركوك', 'Kirkuk'], ['زاخۆ', 'زاخو', 'Zakho'],
    ['سۆران', 'سوران', 'Soran'], ['ڕانیە', 'رانية', 'Ranya'], ['کۆیە', 'كويسنجق', 'Koya'],
    ['شەقڵاوە', 'شقلاوة', 'Shaqlawa'], ['کەلار', 'كلار', 'Kalar'], ['ئاکرێ', 'عقرة', 'Akre'],
    ['چەمچەماڵ', 'جمجمال', 'Chamchamal'], ['ئامێدی', 'العمادية', 'Amedi'],
];
$ins = $pdo->prepare('INSERT INTO cities (name_ku, name_ar, name_en, slug) VALUES (?,?,?,?)
    ON CONFLICT(slug) DO UPDATE SET name_ku = excluded.name_ku, name_ar = excluded.name_ar');
foreach ($cities as [$ku, $ar, $en]) {
    $ins->execute([$ku, $ar, $en, trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($en)), '-')]);
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
        ->execute(['بەڕێوەبەری ماڵپەڕ', $email, '0750 000 0000', password_hash($password, PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    echo "admin created: $email" . ($generated ? "  password: $password  (change it after first login)" : '') . PHP_EOL;
} else {
    echo "admin exists: $email" . PHP_EOL;
}

if (!$demo) {
    exit(0);
}

// ---- Demo offices (نوسینگە) ----
function demo_logo(int $seed, string $letter): string
{
    $palette = [[15, 118, 110], [180, 83, 9], [29, 78, 216], [190, 18, 60], [109, 40, 217]];
    [$r, $g, $b] = $palette[$seed % count($palette)];
    $im = imagecreatetruecolor(400, 400);
    imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
    $white = imagecolorallocate($im, 255, 255, 255);
    // stylised roof + building blocks
    imagefilledpolygon($im, [70, 200, 200, 80, 330, 200, 300, 200, 200, 115, 100, 200], $white);
    imagefilledrectangle($im, 120, 210, 185, 320, $white);
    imagefilledrectangle($im, 215, 210, 280, 320, $white);
    imagefilledellipse($im, 320, 90, 50, 50, imagecolorallocate($im, 251, 191, 36));
    $tmp = tempnam(sys_get_temp_dir(), 'aqlogo') . '.png';
    imagepng($im, $tmp);
    imagedestroy($im);
    return $tmp;
}

$cityId = static function (string $en) use ($pdo): int {
    $s = $pdo->prepare('SELECT id FROM cities WHERE name_en = ?');
    $s->execute([$en]);
    return (int) $s->fetchColumn();
};

$officesDemo = [
    ['نوسینگەی ئاسۆ بۆ خانووبەرە', 'asso@aqarat.local', '0750 111 2233', 'Erbil', 'شەقامی ١٠٠ مەتری، نزیک پارکی سامی عەبدولڕەحمان', 'زیاتر لە ١٥ ساڵ ئەزموون لە کڕین و فرۆشتنی خانوو و زەوی لە هەولێر. پسپۆڕ لە پڕۆژەکانی دریم سیتی و ئێمپایەر.', 1],
    ['نوسینگەی سلێمانی هۆم', 'slemani@aqarat.local', '0770 222 3344', 'Sulaymaniyah', 'گەڕەکی بەختیاری، شەقامی سالم', 'خانوو و شوقە بۆ فرۆشتن و کرێ لە سلێمانی و دەوروبەری. خزمەتگوزاری متمانەپێکراو و خێرا.', 1],
    ['نوسینگەی دهۆک پڕۆپەرتی', 'duhok@aqarat.local', '0750 333 4455', 'Duhok', 'گەڕەکی ماڵتا، نزیک بازاڕی شار', 'باشترین هەلی وەبەرهێنان لە دهۆک و زاخۆ. زەوی، خانوو و دوکان.', 0],
    ['نوسینگەی عەنکاوە', 'ankawa@aqarat.local', '0751 444 5566', 'Erbil', 'عەنکاوە، شەقامی سەرەکی', 'پسپۆڕ لە ڤێلا و شوقەی کرێ لە عەنکاوە و گوندی ئیتاڵی.', 0],
];
$officeUsers = [];
foreach ($officesDemo as $i => [$name, $em, $ph, $cityEn, $addr, $about, $verified]) {
    $stmt->execute([$em]);
    $uid = (int) $stmt->fetchColumn();
    if (!$uid) {
        $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?, 'agent')")
            ->execute([$name, $em, $ph, password_hash('demo-password', PASSWORD_DEFAULT)]);
        $uid = (int) $pdo->lastInsertId();
    }
    $exists = $pdo->prepare('SELECT id FROM offices WHERE user_id = ?');
    $exists->execute([$uid]);
    if (!$exists->fetchColumn()) {
        $tmp = demo_logo($i, mb_substr($name, 0, 1));
        $logo = process_logo_upload(['name' => 'logo.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'type' => 'image/png']);
        @unlink($tmp);
        @unlink(substr($tmp, 0, -4));
        $pdo->prepare('INSERT INTO offices (user_id, name, slug, logo, about, phone, whatsapp, address, city_id, is_verified) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, $name, 'office-' . ($i + 1), $logo, $about, $ph, $ph, $addr, $cityId($cityEn), $verified]);
    }
    $officeUsers[$cityEn][] = $uid;
}
$officeUsers['_any'] = [$adminId];
echo 'offices: ' . $pdo->query('SELECT COUNT(*) FROM offices')->fetchColumn() . PHP_EOL;

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
    $top = [mt_rand(70, 140), mt_rand(140, 190), mt_rand(200, 245)];
    $bot = [mt_rand(220, 250), mt_rand(200, 230), mt_rand(170, 210)];
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($im, (int) ($top[0] + ($bot[0] - $top[0]) * $t), (int) ($top[1] + ($bot[1] - $top[1]) * $t), (int) ($top[2] + ($bot[2] - $top[2]) * $t));
        imageline($im, 0, $y, $w, $y, $c);
    }
    // mountains in the background (Kurdistan!)
    for ($m = 0; $m < 3; $m++) {
        $mc = imagecolorallocate($im, 110 + $m * 25, 125 + $m * 22, 140 + $m * 18);
        $pts = [0, (int) ($h * 0.78)];
        for ($x = 0; $x <= $w; $x += 100) {
            $pts[] = $x;
            $pts[] = (int) ($h * (0.42 + 0.1 * $m) + mt_rand(-60, 60));
        }
        $pts[] = $w; $pts[] = (int) ($h * 0.78);
        imagefilledpolygon($im, $pts, $mc);
    }
    imagefilledellipse($im, mt_rand(150, 1250), mt_rand(90, 200), 110, 110, imagecolorallocatealpha($im, 255, 240, 200, 30));
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
    for ($p = 0; $p < mt_rand(1, 3); $p++) {
        $px = mt_rand(40, $w - 40);
        $py = (int) ($h * 0.82);
        imagefilledellipse($im, $px, $py - 120, 120, 200, imagecolorallocate($im, 40, 110, 60));
        imagesetthickness($im, 12);
        imageline($im, $px, $py, $px, $py - 60, imagecolorallocate($im, 110, 80, 50));
        imagesetthickness($im, 1);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'aq') . '.jpg';
    imagejpeg($im, $tmp, 88);
    imagedestroy($im);
    return $tmp;
}

// city => [center lat, lng, districts[], projects[]]
$geo = [
    'Erbil' => [36.1911, 44.0092, ['عەنکاوە', 'بەختیاری', 'گوڵان', 'شۆڕش', 'ئازادی', 'ڕاستی', 'کوردستان'], ['دریم سیتی', 'گوندی ئیتاڵی', 'ئێمپایەر وۆڵد', 'گوندی ئینگلیزی', 'ناز سیتی', '']],
    'Sulaymaniyah' => [35.5613, 45.4306, ['سەرچنار', 'بەختیاری', 'گۆیژە', 'ڕاپەڕین', 'ئازادی', 'سەرشەقام'], ['گوندی ئەڵمانی', 'سلێمانی هایتس', 'سەرچنار ڕێزیدەنس', '']],
    'Duhok' => [36.8669, 42.9503, ['ماڵتا', 'نەخۆشخانە', 'شاندۆخا', 'زرگەلی'], ['دهۆک هیلز', '']],
    'Halabja' => [35.1779, 45.9861, ['سەنتەری شار', 'گەڕەکی کانی ئاشقان'], ['']],
    'Zakho' => [37.1440, 42.6870, ['سەنتەری شار', 'بادینان'], ['']],
];
$templates = [
    ['house', 'sale', 'خانووی :a مەتری :b ژوور لە :d', 140000, 420000, 200, 400, 'tapo'],
    ['apartment', 'sale', 'شوقەی :b ژوور لە :p', 65000, 210000, 110, 220, 'investment'],
    ['apartment', 'rent', 'شوقەی :b ژوور بۆ کرێ لە :d', 450, 1400, 90, 200, null],
    ['villa', 'sale', 'ڤێلای مۆدێرن لەگەڵ مەلەوانگە لە :p', 380000, 1200000, 350, 700, 'tapo'],
    ['house', 'rent', 'خانوو بۆ کرێ لە :d', 500, 1500, 200, 350, null],
    ['land', 'sale', 'زەوی :a مەتر لە :d — دوو شەقام', 40000, 300000, 200, 1200, 'tapo'],
    ['shop', 'rent', 'دوکان لەسەر شەقامی سەرەکی :d', 600, 2500, 30, 120, null],
    ['office', 'rent', 'ئۆفیسی ئامادە لە :d', 700, 2200, 80, 250, null],
    ['land', 'sale', 'زەوی کشتوکاڵی :a مەتر نزیک :d', 25000, 120000, 1000, 5000, 'agricultural'],
    ['building', 'sale', 'باڵەخانەی بازرگانی لە :d', 600000, 2500000, 400, 1200, 'tapo'],
];
$desc = "موڵکێکی نایاب لە شوێنێکی گرنگ، نزیک قوتابخانە، مزگەوت، بازاڕ و شەقامە سەرەکییەکان.\n\nتایبەتمەندییەکان:\n- تەواوکاری بە کوالیتی بەرز\n- ڕووناکی سروشتی و هەوای پاک\n- ئاوی بەردەوام و هێڵی مۆلیدە\n\nبۆ بینین و زانیاری زیاتر پەیوەندی بکە یان پرسیار بنێرە.";

$insP = $pdo->prepare("INSERT INTO properties (user_id, city_id, title, description, purpose, type, district, address, project, price, currency, price_usd,
        rent_period, deed, area, bedrooms, bathrooms, floor, year_built, furnished, features, lat, lng, status, is_featured, views, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'published',?,?, datetime('now', ?), datetime('now', ?))");
$n = 0;
mt_srand(7);
$cityKeys = array_keys($geo);
$weights = ['Erbil' => 12, 'Sulaymaniyah' => 9, 'Duhok' => 6, 'Halabja' => 2, 'Zakho' => 2];
$plan = [];
foreach ($weights as $c => $k) { for ($i = 0; $i < $k; $i++) { $plan[] = $c; } }
foreach ($plan as $i => $cityEn) {
    [$lat0, $lng0, $districts, $projects] = $geo[$cityEn];
    [$type, $purpose, $title, $pmin, $pmax, $amin, $amax, $deed] = $templates[$i % count($templates)];
    $d = $districts[array_rand($districts)];
    $proj = in_array($type, ['apartment', 'villa'], true) ? $projects[array_rand($projects)] : '';
    $beds = in_array($type, ['land', 'shop', 'office', 'building'], true) ? 0 : mt_rand(2, 6);
    $baths = $beds ? max(1, $beds - mt_rand(0, 1)) : ($type === 'office' ? 1 : 0);
    $area = mt_rand($amin, $amax);
    $priceUsd = (int) (round(mt_rand($pmin, $pmax) / ($purpose === 'rent' ? 50 : 1000)) * ($purpose === 'rent' ? 50 : 1000));
    $currency = ($i % 5 === 2) ? 'IQD' : 'USD';     // some listings priced in dinar
    $price = $currency === 'IQD' ? (int) (round($priceUsd * config('iqd_per_usd') / 250000) * 250000) : $priceUsd;
    if ($currency === 'IQD' && $price === 0) { $price = 250000; }
    $feats = array_rand(array_flip(PROPERTY_FEATURES), mt_rand(3, 7));
    $age = '-' . mt_rand(0, 45) . ' days';
    $owners = $officeUsers[$cityEn] ?? $officeUsers['Erbil'];
    $owner = ($i % 7 === 6) ? $adminId : $owners[$i % count($owners)];
    $insP->execute([
        $owner, $cityId($cityEn), strtr($title, [':b' => $beds ?: 3, ':d' => $d, ':a' => $area, ':p' => $proj ?: $d]), $desc, $purpose, $type, $d,
        '', $proj, $price, $currency, to_usd($price, $currency), $purpose === 'rent' ? 'monthly' : null, $deed,
        $area, $beds, $baths, $type === 'apartment' ? mt_rand(1, 18) : null,
        mt_rand(2008, 2025), (int) (mt_rand(0, 3) === 0), json_encode(array_values((array) $feats)),
        round($lat0 + (mt_rand(-400, 400) / 10000), 6), round($lng0 + (mt_rand(-500, 500) / 10000), 6),
        (int) ($i % 4 === 0), mt_rand(5, 1500), $age, $age,
    ]);
    $pid = (int) $pdo->lastInsertId();
    $files = [];
    for ($k = 0; $k < mt_rand(3, 6); $k++) {
        $tmp = demo_photo($i * 10 + $k, $type === 'land' ? 'land' : (in_array($type, ['villa', 'house'], true) ? 'villa' : 'tower'));
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

// ---- Demo: a "stolen photo" listing (resized + recompressed copy of listing #1's cover) → duplicate detection
$src = $pdo->query('SELECT filename FROM property_images WHERE property_id = 1 ORDER BY sort_order LIMIT 1')->fetchColumn();
if ($src) {
    $im = imagecreatefromwebp(config('upload_dir') . '/' . $src);
    $copy = imagescale($im, 1100);
    $tmp = tempnam(sys_get_temp_dir(), 'aqdup') . '.jpg';
    imagejpeg($copy, $tmp, 70);
    $owner = $officeUsers['Sulaymaniyah'][0] ?? $adminId;
    $pdo->prepare("INSERT INTO properties (user_id, city_id, title, description, purpose, type, district, price, currency, price_usd, area, bedrooms, bathrooms, status)
        VALUES (?,?,?,?, 'sale', 'house', 'ڕاستی', 355000, 'USD', 355000, 290, 4, 3, 'pending')")
        ->execute([$owner, $cityId('Erbil'), 'خانووی ٤ ژوور — نرخی تایبەت', 'کۆپی وێنە بۆ تاقیکردنەوەی دۆزینەوەی دووبارە.']);
    $dupId = (int) $pdo->lastInsertId();
    attach_uploaded_images($dupId, [['name' => 'copy.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'type' => 'image/jpeg']]);
    @unlink($tmp);
    @unlink(substr($tmp, 0, -4));
    echo 'duplicate flags: ' . $pdo->query('SELECT COUNT(*) FROM duplicate_flags')->fetchColumn() . PHP_EOL;
}

// ---- Demo videos (only if ffmpeg is available on this machine; the app itself never needs ffmpeg)
$ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
if ($ffmpeg !== '') {
    foreach ([1, 4] as $pid) {
        $img = $pdo->query('SELECT filename FROM property_images WHERE property_id = ' . $pid . ' ORDER BY sort_order LIMIT 1')->fetchColumn();
        if (!$img) { continue; }
        $png = tempnam(sys_get_temp_dir(), 'aqv') . '.png';
        imagepng(imagecreatefromwebp(config('upload_dir') . '/' . $img), $png);
        $mp4 = tempnam(sys_get_temp_dir(), 'aqv') . '.mp4';
        // 6-second slow zoom ("Ken Burns") tour, H.264 + faststart so it streams immediately
        shell_exec(escapeshellarg($ffmpeg) . ' -y -loglevel error -loop 1 -i ' . escapeshellarg($png)
            . " -vf \"scale=1600:-2,zoompan=z='min(zoom+0.0015,1.25)':d=150:s=1280x720:fps=25,format=yuv420p\" -t 6 -c:v libx264 -preset veryfast -crf 28 -movflags +faststart "
            . escapeshellarg($mp4) . ' 2>&1');
        if (is_file($mp4) && filesize($mp4) > 0) {
            $errs = attach_uploaded_videos($pid, [['name' => 'tour.mp4', 'tmp_name' => $mp4, 'error' => UPLOAD_ERR_OK, 'size' => filesize($mp4), 'type' => 'video/mp4']]);
            echo "demo video for #$pid" . ($errs ? ': ' . implode('; ', $errs) : '') . PHP_EOL;
        }
        @unlink($png); @unlink($mp4); @unlink(substr($png, 0, -4)); @unlink(substr($mp4, 0, -4));
    }
}
