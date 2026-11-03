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
    return $byIp >= $max * 20 || $byEmail >= $max;   // per account strict; per IP generous (CGNAT)
}

function login_submit(): void
{
    $email = mb_strtolower(mb_substr(trim(input('email')), 0, 150));
    $password = (string) ($_POST['password'] ?? '');
    if ($email !== '' && !str_contains($email, '@') && ($byPhone = user_by_phone($email))) {
        $email = mb_strtolower((string) $byPhone['email']);       // offices may sign in with their phone number
    }

    if (login_throttled($email)) {
        with_old(['email' => $email], ['email' => t('login_throttled', ['min' => (int) (config('login_window_sec') / 60)])]);
        redirect(url('login'));
    }

    $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // Always run password_verify to keep timing similar for unknown emails.
    $hash = $u['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_ALGO);
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
    if (password_needs_rehash($u['password_hash'], PASSWORD_ALGO)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_ALGO), $u['id']]);
    }
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

    $intended = $_SESSION['_intended'] ?? url('dashboard');
    clear_old();
    if (user_2fa((int) $u['id'])) {
        // Password was right; the account is not signed in until the authenticator code is too.
        session_regenerate_id(true);
        $_SESSION['_2fa'] = ['uid' => (int) $u['id'], 't' => time(), 'tries' => 0, 'next' => $intended];
        redirect(url('login/code'));
    }
    login_user((int) $u['id']);
    after_sign_in((int) $u['id'], is_string($intended) ? $intended : url('dashboard'));
}

/** Welcome message, or straight to "choose your own password" for accounts the admin set up. */
function after_sign_in(int $userId, string $next): never
{
    $st = db()->prepare('SELECT must_change_password FROM users WHERE id = ?');
    $st->execute([$userId]);
    if ((int) $st->fetchColumn() === 1) {
        flash('info', t('choose_own_password'));
        redirect(url('dashboard/profile') . '#password');
    }
    flash('success', t('welcome_back'));
    redirect($next);
}

/** Step 2 of signing in, for accounts with two-step login on. */
function login_code_form(): void
{
    $p = $_SESSION['_2fa'] ?? null;
    if (!$p || $p['t'] < time() - 300) {
        unset($_SESSION['_2fa']);
        redirect(url('login'));
    }
    render('auth/login_code', ['pageTitle' => t('twofa_title') . ' — ' . app_name()]);
}

function login_code_submit(): void
{
    $p = $_SESSION['_2fa'] ?? null;
    if (!$p || $p['t'] < time() - 300 || $p['tries'] >= 5) {     // 5 minutes, 5 tries, then start again
        unset($_SESSION['_2fa']);
        flash('error', t('twofa_expired'));
        redirect(url('login'));
    }
    $uid = (int) $p['uid'];
    if (login_throttled('uid:' . $uid)) {                // wrong codes count per account too, across sessions and IPs
        unset($_SESSION['_2fa']);
        flash('error', t('login_throttled', ['min' => (int) (config('login_window_sec') / 60)]));
        redirect(url('login'));
    }
    $f = user_2fa($uid);
    if (!$f) {
        unset($_SESSION['_2fa']);
        redirect(url('login'));
    }
    $code = trim((string) ($_POST['code'] ?? ''));
    $ok = false;
    if (($step = totp_verify($f['totp_secret'], $code, (int) $f['totp_last_step'])) !== null) {
        db()->prepare('UPDATE users SET totp_last_step = ? WHERE id = ?')->execute([$step, $uid]);   // never accept it again
        $ok = true;
    } elseif (($left = use_recovery_code((array) json_decode($f['recovery_codes'], true), $code)) !== null) {
        db()->prepare('UPDATE users SET recovery_codes = ? WHERE id = ?')->execute([json_encode($left), $uid]);
        flash('info', t('twofa_recovery_used', ['n' => count($left)]));
        $ok = true;
    }
    if (!$ok) {
        $_SESSION['_2fa']['tries']++;
        db()->prepare('INSERT INTO login_attempts (ip, email, attempted_at) VALUES (?,?,?)')->execute([client_ip(), 'uid:' . $uid, time()]);
        with_old([], ['code' => t('twofa_wrong')]);
        redirect(url('login/code'));
    }
    unset($_SESSION['_2fa']);
    login_user($uid);
    after_sign_in($uid, is_string($p['next']) ? $p['next'] : url('dashboard'));
}

function register_form(): void
{
    if (current_user()) {
        redirect(url('dashboard'));
    }
    render('auth/register', ['pageTitle' => t('register') . ' — ' . app_name(), 'adminWa' => admin_whatsapp()]);
}

function register_submit(): void
{
    $data = [
        'name'  => mb_substr(input('name'), 0, 100),
        'email' => mb_strtolower(mb_substr(input('email'), 0, 150)),
        'phone' => mb_substr(convert_arabic_digits(input('phone')), 0, 30),
        // While the admin sets offices up in person, public sign-up only makes customer accounts.
        'role'  => input('role') === 'agent' && office_signup_open() ? 'agent' : 'user',
        'office_name' => mb_substr(input('office_name'), 0, 100),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');
    rate_limit('register', 20, 3600);

    $errors = [];
    if (mb_strlen($data['name']) < 2) {
        $errors['name'] = t('v_name');
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = t('v_email');
    }
    if ($data['phone'] !== '' && !valid_phone($data['phone'])) {
        $errors['phone'] = t('v_phone');
    }
    if (!form_time_ok(3)) {
        $errors['name'] = t('form_too_fast');
    }
    if ($data['role'] === 'agent' && mb_strlen($data['office_name']) < 2) {
        $errors['office_name'] = t('v_office_name');
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
        ->execute([$data['name'], $data['email'], $data['phone'], password_hash($password, PASSWORD_ALGO), $data['role']]);
    $userId = (int) db()->lastInsertId();
    if ($data['role'] === 'agent') {
        create_office($userId, $data['office_name'], $data['phone']);
    }
    login_user($userId);
    clear_old();
    flash('success', t('registered'));
    redirect(url($data['role'] === 'agent' ? 'dashboard/office' : 'dashboard'));
}

function logout_submit(): void
{
    logout_user();
    flash('success', t('logged_out'));
    redirect(url('/'));
}
