<?php
declare(strict_types=1);

/* One-time web installer for hosts without SSH. Works only while no admin account exists. */

const KURDISTAN_CITIES = [
    ['هەولێر', 'أربيل', 'Erbil'], ['سلێمانی', 'السليمانية', 'Sulaymaniyah'], ['دهۆک', 'دهوك', 'Duhok'],
    ['هەڵەبجە', 'حلبجة', 'Halabja'], ['کەرکووک', 'كركوك', 'Kirkuk'], ['زاخۆ', 'زاخو', 'Zakho'],
    ['سۆران', 'سوران', 'Soran'], ['ڕانیە', 'رانية', 'Ranya'], ['کۆیە', 'كويسنجق', 'Koya'],
    ['شەقڵاوە', 'شقلاوة', 'Shaqlawa'], ['کەلار', 'كلار', 'Kalar'], ['ئاکرێ', 'عقرة', 'Akre'],
    ['چەمچەماڵ', 'جمجمال', 'Chamchamal'], ['ئامێدی', 'العمادية', 'Amedi'],
];

function seed_cities(): void
{
    $ins = db()->prepare('INSERT OR IGNORE INTO cities (name_ku, name_ar, name_en, slug) VALUES (?,?,?,?)');
    foreach (KURDISTAN_CITIES as [$ku, $ar, $en]) {
        $ins->execute([$ku, $ar, $en, trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($en)), '-')]);
    }
}

function admin_exists(): bool
{
    return (bool) db()->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
}

function install_form(): void
{
    if (admin_exists()) {
        abort(404);
    }
    $checks = [
        'PHP ≥ 8.1'            => PHP_VERSION_ID >= 80100,
        'pdo_sqlite + FTS5'    => extension_loaded('pdo_sqlite'),
        'GD + WebP'            => function_exists('imagewebp'),
        'openssl (push)'       => function_exists('openssl_pkey_derive'),
        'curl (push)'          => function_exists('curl_multi_init'),
        'data/ writable'       => is_writable(dirname((string) config('db_path'))),
        'uploads/ writable'    => is_writable((string) config('upload_dir')),
        'SQLite WAL'           => strtolower((string) db()->query('PRAGMA journal_mode')->fetchColumn()) === 'wal',
        'HTTPS (PWA + push)'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ];
    render('install', ['pageTitle' => 'Install — ' . app_name(), 'checks' => $checks]);
}

function install_submit(): void
{
    if (admin_exists()) {
        abort(404);
    }
    $name = mb_substr(input('name'), 0, 100);
    $email = mb_strtolower(mb_substr(input('email'), 0, 150));
    $password = (string) ($_POST['password'] ?? '');
    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors['name'] = t('v_name');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = t('v_email');
    }
    if (strlen($password) < 10) {
        $errors['password'] = t('v_password_admin');
    }
    if ($errors) {
        with_old(compact('name', 'email'), $errors);
        redirect(url('install'));
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    if (admin_exists()) {          // re-check inside the write lock (two people installing at once)
        $pdo->exec('ROLLBACK');
        abort(404);
    }
    seed_cities();
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?, 'admin')
        ON CONFLICT(email) DO UPDATE SET role = 'admin', password_hash = excluded.password_hash")
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote($email))->fetchColumn();
    $pdo->exec('COMMIT');
    vapid_keys();                  // generate push keys now
    login_user($id);
    flash('success', t('install_done'));
    redirect(url('admin'));
}
