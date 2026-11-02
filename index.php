<?php
declare(strict_types=1);

// PHP built-in server (local development only): serve real static files, but never internals.
if (PHP_SAPI === 'cli-server') {
    $path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    if (preg_match('#^/(app|data|bin|tests|deploy|\.)|\.(md|sqlite|json|ini)$#i', $path)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $file = realpath(__DIR__ . $path);
    if ($path !== '/' && $file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)
        && !str_ends_with($file, '.php')) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/routes.php';

dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
