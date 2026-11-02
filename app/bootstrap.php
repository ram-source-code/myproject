<?php
declare(strict_types=1);

define('AQARAT_ROOT', dirname(__DIR__));

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/image.php';
require __DIR__ . '/models.php';

date_default_timezone_set(config('timezone', 'UTC'));
mb_internal_encoding('UTF-8');

ini_set('display_errors', config('debug') ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log('aqarat: ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    try {
        render('error', [
            'code'    => 500,
            'title'   => t('err_500'),
            'message' => config('debug') ? $e->getMessage() : '',
        ]);
    } catch (Throwable) {
        echo 'Internal Server Error';
    }
});

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    start_session();
}
