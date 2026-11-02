<?php
declare(strict_types=1);

define('AQARAT_ROOT', dirname(__DIR__));

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/image.php';
require __DIR__ . '/models.php';
require __DIR__ . '/push.php';
require __DIR__ . '/geo.php';

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

/** Recreate protection files an FTP client may have skipped (dotfiles are often hidden). */
function ensure_protection_files(): void
{
    $root = AQARAT_ROOT;
    $deny = __DIR__ . '/templates/htaccess-deny.txt';
    $targets = [
        "$root/.htaccess"         => __DIR__ . '/templates/htaccess-root.txt',
        "$root/uploads/.htaccess" => __DIR__ . '/templates/htaccess-uploads.txt',
    ];
    foreach (['app', 'bin', 'tests', 'deploy', 'data'] as $d) {
        $targets["$root/$d/.htaccess"] = $deny;
    }
    foreach ($targets as $file => $tpl) {
        if (!is_file($file) && is_dir(dirname($file)) && is_writable(dirname($file)) && is_file($tpl)) {
            @copy($tpl, $file);
        }
    }
    if (!is_dir("$root/uploads")) {
        @mkdir("$root/uploads", 0755, true);
        @copy(__DIR__ . '/templates/htaccess-uploads.txt', "$root/uploads/.htaccess");
    }
}

if (PHP_SAPI !== 'cli') {
    ensure_protection_files();
    if (config('setup_error') === 'data_not_writable') {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        $dir = htmlspecialchars(AQARAT_ROOT . '/data', ENT_QUOTES);
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup</title>'
            . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:10vh auto;padding:0 16px;line-height:1.6">'
            . '<h1>Almost there</h1><p>The app needs to create its database, but this folder is not writable by PHP:</p>'
            . '<p><code>' . $dir . '</code></p><p>In your hosting file manager (or FTP), set the permissions of the <b>data</b> and '
            . '<b>uploads</b> folders to <b>755</b> (or 775), then reload this page. Setup continues automatically.</p>'
            . '<p><b>XAMPP on macOS/Linux:</b> run <code>chmod -R 777 data uploads</code> in the app folder (fine for local testing). '
            . '<b>XAMPP on Windows:</b> make sure the folder is not read-only and is not inside a OneDrive-synced folder.</p>'
            . '<p dir="rtl" lang="ckb">مۆڵەتی فۆڵدەرەکانی <b>data</b> و <b>uploads</b> بکە بە <b>755</b> و پەڕەکە نوێ بکەرەوە.</p></body>';
        exit;
    }
    header_remove('X-Powered-By');
    $tileOrigin = maps_enabled() ? (string) preg_replace('~^(https?://[^/]+).*$~', '$1', (string) config('tile_url')) : '';
    $tileOrigin = preg_match('~^https?://[A-Za-z0-9.\-:{}]+$~', $tileOrigin) ? ' ' . str_replace(['{s}.', '{s}'], ['*.', '*'], $tileOrigin) : '';
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:{$tileOrigin}; media-src 'self' blob:; style-src 'self'; font-src 'self'; script-src 'self' 'inline-speculation-rules'; connect-src 'self'; worker-src 'self';" . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? ' upgrade-insecure-requests;' : '') . " manifest-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), interest-cohort=()');   // location: own pages only, after the visitor allows it
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    if (preg_match('#/(dashboard|admin|login|register|install|notifications|push|api)(/|$)#', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
        header('X-Robots-Tag: noindex, nofollow');       // private/utility pages never appear in search results
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    start_session();
}
