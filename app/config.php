<?php
declare(strict_types=1);

/**
 * Application configuration. Override any value with an environment variable
 * of the same name prefixed with AQARAT_ (e.g. AQARAT_APP_URL).
 */
$env = static fn(string $key, $default) => ($v = getenv('AQARAT_' . $key)) !== false ? $v : $default;

/*
 * Automatic setup: on the very first request the app writes data/config.local.php with random,
 * unguessable names for the database and the push keys (defence in depth if a host ignores .htaccess).
 * The web installer later adds the site settings (URL, language, contact e-mail) to the same file.
 * Shared hosting needs no environment variables — but AQARAT_* env vars still override everything.
 */
$dataDir = dirname(__DIR__) . '/data';
$localFile = $dataDir . '/config.local.php';
$local = is_file($localFile) ? (array) (require $localFile) : null;
$setupError = null;
if ($local === null) {
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    if (is_dir($dataDir) && is_writable($dataDir)) {
        $legacyDb = $dataDir . '/aqarat.sqlite';               // keep databases created by older versions
        $legacyVapid = $dataDir . '/vapid.json';
        $rand = bin2hex(random_bytes(8));
        $local = [
            'db_file'    => is_file($legacyDb) ? 'aqarat.sqlite' : "aqarat-$rand.sqlite",
            'vapid_file' => is_file($legacyVapid) ? 'vapid.json' : "vapid-$rand.json",
        ];
        $tmp = $localFile . '.' . $rand;
        if (@file_put_contents($tmp, "<?php\n// Generated automatically. Safe to edit.\nreturn " . var_export($local, true) . ";\n", LOCK_EX) !== false) {
            @chmod($tmp, 0640);
            if (!@rename($tmp, $localFile)) {             // another request created it first
                @unlink($tmp);
                $local = (array) (require $localFile);
            }
        } else {
            $setupError = 'data_not_writable';
        }
    } else {
        $setupError = 'data_not_writable';
    }
    $local ??= [];
}
$lc = static fn(string $key, $default) => $local[$key] ?? $default;

$config = [
    'app_name_ku'     => $env('APP_NAME_KU', 'عەقارات'),
    'app_name'        => $env('APP_NAME', 'عقارات'),
    'app_name_en'     => $env('APP_NAME_EN', 'Aqarat'),
    // Leave empty to auto-detect the install folder (e.g. /aqarat). Set a full URL such as
    // https://example.com/aqarat if you are behind a proxy or need absolute links in notifications.
    'app_url'         => rtrim((string) $env('APP_URL', $lc('app_url', '')), '/'),
    'debug'           => filter_var($env('DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
    'default_lang'    => $env('LANG', $lc('default_lang', 'ckb')),          // ckb = Kurdish (Sorani)
    'timezone'        => $env('TIMEZONE', 'Asia/Baghdad'),
    // Listings can be priced in USD or IQD; IQD prices are converted for filtering/sorting.
    'iqd_per_usd'     => (int) $env('IQD_PER_USD', $lc('iqd_per_usd', 1450)),
    'map_center'      => [36.1911, 44.0092],          // Erbil
    // Map tiles are the ONLY external resource. Point this at your own tile server, or set it
    // to an empty string to switch maps off completely (AQARAT_TILE_URL="").
    'tile_url'        => $env('TILE_URL', $lc('tile_url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png')),
    'tile_attribution'=> $env('TILE_ATTRIBUTION', '© OpenStreetMap'),
    // Videos (stored as uploaded — no transcoding needed)
    'max_video_bytes' => 60 * 1024 * 1024,
    'max_videos'      => 2,
    'watermark'       => filter_var($env('WATERMARK', '1'), FILTER_VALIDATE_BOOLEAN),
    'saved_search_limit' => 10,
    // Web Push (VAPID). Keys are generated automatically into data/vapid.json on first use.
    'push_subject'    => $env('PUSH_SUBJECT', $lc('push_subject', 'mailto:admin@example.com')),
    'vapid_file'      => $env('VAPID_FILE', $dataDir . '/' . basename((string) $lc('vapid_file', 'vapid.json'))),
    'push_ttl'        => 86400 * 3,

    // Storage (database lives OUTSIDE the public web root)
    'db_path'         => $env('DB_PATH', $dataDir . '/' . basename((string) $lc('db_file', 'aqarat.sqlite'))),
    'upload_dir'      => dirname(__DIR__) . '/uploads',

    // Image pipeline (GD → WebP)
    'max_upload_bytes'  => 8 * 1024 * 1024,   // per file
    'max_images'        => 15,                // per property
    'max_pixels'        => 40_000_000,        // reject decompression bombs
    'image_max_width'   => 1600,
    'image_max_height'  => 1200,
    'thumb_width'       => 640,
    'thumb_height'      => 427,
    'webp_quality'      => 80,

    // Security
    'session_name'      => 'aqarat_sid',
    'login_max_attempts'=> 5,
    'login_window_sec'  => 900,
    'listings_per_page' => 12,
    // New listings by regular users need admin approval
    'require_approval'  => filter_var($env('REQUIRE_APPROVAL', $lc('require_approval', '1')), FILTER_VALIDATE_BOOLEAN),
];

$config['setup_error'] = $setupError;
$config['local_config_file'] = $localFile;
return $config;
