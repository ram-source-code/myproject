<?php
declare(strict_types=1);

/**
 * Application configuration. Override any value with an environment variable
 * of the same name prefixed with AQARAT_ (e.g. AQARAT_APP_URL).
 */
$env = static fn(string $key, $default) => ($v = getenv('AQARAT_' . $key)) !== false ? $v : $default;

return [
    'app_name_ku'     => $env('APP_NAME_KU', 'عەقارات'),
    'app_name'        => $env('APP_NAME', 'عقارات'),
    'app_name_en'     => $env('APP_NAME_EN', 'Aqarat'),
    // Leave empty to auto-detect the install folder (e.g. /aqarat). Set a full URL such as
    // https://example.com/aqarat if you are behind a proxy or need absolute links in notifications.
    'app_url'         => rtrim((string) $env('APP_URL', ''), '/'),
    'debug'           => filter_var($env('DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
    'default_lang'    => $env('LANG', 'ckb'),          // ckb = Kurdish (Sorani)
    'timezone'        => $env('TIMEZONE', 'Asia/Baghdad'),
    // Listings can be priced in USD or IQD; IQD prices are converted for filtering/sorting.
    'iqd_per_usd'     => (int) $env('IQD_PER_USD', 1450),
    'map_center'      => [36.1911, 44.0092],          // Erbil
    // Web Push (VAPID). Keys are generated automatically into data/vapid.json on first use.
    'push_subject'    => $env('PUSH_SUBJECT', 'mailto:admin@example.com'),
    'vapid_file'      => $env('VAPID_FILE', dirname(__DIR__) . '/data/vapid.json'),
    'push_ttl'        => 86400 * 3,

    // Storage (database lives OUTSIDE the public web root)
    'db_path'         => $env('DB_PATH', dirname(__DIR__) . '/data/aqarat.sqlite'),
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
    'require_approval'  => filter_var($env('REQUIRE_APPROVAL', '1'), FILTER_VALIDATE_BOOLEAN),
];
