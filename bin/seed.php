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

// ---- Cities (Kurdistan Region) ----
require_once dirname(__DIR__) . '/app/controllers/install.php';
seed_cities();
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
require dirname(__DIR__) . '/app/demo.php';
seed_demo_data($adminId);

