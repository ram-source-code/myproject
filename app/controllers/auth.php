<?php
declare(strict_types=1);

function login_form(): void
{
    if (current_user()) {
        redirect(url('dashboard'));
    }
    render('auth/login', ['pageTitle' => t('login') . ' — ' . app_name()]);
}

function login_throttled(string $email): bool
{
    $since = time() - (int) config('login_window_sec');
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$since]);
    $stmt = db()->prepare('SELECT
        (SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?),
        (SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at >= ?)');
    $stmt->execute([client_ip(), $since, $email, $since]);
    [$byIp, $byEmail] = $stmt->fetch(PDO::FETCH_NUM);
    $max = (int) config('login_max_attempts');
    return $byIp >= $max * 4 || $byEmail >= $max;
}

function login_submit(): void
{
    $email = mb_strtolower(mb_substr(input('email'), 0, 150));
    $password = (string) ($_POST['password'] ?? '');

    if (login_throttled($email)) {
        with_old(['email' => $email], ['email' => t('login_throttled', ['min' => (int) (config('login_window_sec') / 60)])]);
        redirect(url('login'));
    }

    $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // Always run password_verify to keep timing similar for unknown emails.
    $hash = $u['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && $u;

    if (!$ok) {
        db()->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?,?,?)')->execute([client_ip(), $email, time()]);
        with_old(['email' => $email], ['email' => t('login_failed')]);
        redirect(url('login'));
    }
    if ($u['status'] !== 'active') {
        with_old(['email' => $email], ['email' => t('account_banned')]);
        redirect(url('login'));
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

    $intended = $_SESSION['_intended'] ?? url('dashboard');
    login_user((int) $u['id']);
    clear_old();
    flash('success', t('welcome_back'));
    redirect(is_string($intended) ? $intended : url('dashboard'));
}

function register_form(): void
{
    if (current_user()) {
        redirect(url('dashboard'));
    }
    render('auth/register', ['pageTitle' => t('register') . ' — ' . app_name()]);
}

function register_submit(): void
{
    $data = [
        'name'  => mb_substr(input('name'), 0, 100),
        'email' => mb_strtolower(mb_substr(input('email'), 0, 150)),
        'phone' => mb_substr(convert_arabic_digits(input('phone')), 0, 30),
        'role'  => input('role') === 'agent' ? 'agent' : 'user',
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    $errors = [];
    if (mb_strlen($data['name']) < 2) {
        $errors['name'] = t('v_name');
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = t('v_email');
    }
    if ($data['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $data['phone'])) {
        $errors['phone'] = t('v_phone');
    }
    if (strlen($password) < 8 || strlen($password) > 128) {
        $errors['password'] = t('v_password');
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = t('v_password_match');
    }
    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = t('v_email_taken');
        }
    }
    if ($errors) {
        with_old($data, $errors);
        redirect(url('register'));
    }

    db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)')
        ->execute([$data['name'], $data['email'], $data['phone'], password_hash($password, PASSWORD_DEFAULT), $data['role']]);
    login_user((int) db()->lastInsertId());
    clear_old();
    flash('success', t('registered'));
    redirect(url('dashboard'));
}

function logout_submit(): void
{
    logout_user();
    flash('success', t('logged_out'));
    redirect(url('/'));
}
