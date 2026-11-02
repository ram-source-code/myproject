<?php
declare(strict_types=1);

/**
 * Application configuration. Override any value with an environment variable
 * of the same name prefixed with AQARAT_ (e.g. AQARAT_APP_URL).
 */
$env = static fn(string $key, $default) => ($v = getenv('AQARAT_' . $key)) !== false ? $v : $default;

return [
    'app_name'        => $env('APP_NAME', 'عقارات'),
    'app_name_en'     => $env('APP_NAME_EN', 'Aqarat'),
    'app_url'         => rtrim((string) $env('APP_URL', ''), '/'),
    'debug'           => filter_var($env('DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
    'default_lang'    => $env('LANG', 'ar'),
    'currency'        => $env('CURRENCY', 'SAR'),
    'timezone'        => $env('TIMEZONE', 'Asia/Riyadh'),

    // Storage (database lives OUTSIDE the public web root)
    'db_path'         => $env('DB_PATH', dirname(__DIR__) . '/data/aqarat.sqlite'),
    'upload_dir'      => dirname(__DIR__) . '/public/uploads',
    'upload_url'      => '/uploads',

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
