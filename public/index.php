<?php
declare(strict_types=1);

// PHP built-in server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server') {
    $path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    $file = realpath(__DIR__ . $path);
    if ($path !== '/' && $file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)
        && !str_ends_with($file, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/routes.php';

dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
