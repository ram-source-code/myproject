<?php
declare(strict_types=1);

// ───────────────────────── Config / i18n ─────────────────────────

function config(string $key, $default = null)
{
    static $cfg = null;
    $cfg ??= require __DIR__ . '/config.php';
    return $cfg[$key] ?? $default;
}

const SUPPORTED_LANGS = ['ar', 'en'];

function lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $c = $_COOKIE['lang'] ?? '';
    $lang = in_array($c, SUPPORTED_LANGS, true) ? $c : config('default_lang', 'ar');
    return $lang;
}

function dir_attr(): string
{
    return lang() === 'ar' ? 'rtl' : 'ltr';
}

/** Translate a key, with optional :placeholder replacements (values are NOT escaped). */
function t(string $key, array $replace = []): string
{
    static $strings = [];
    $l = lang();
    $strings[$l] ??= require __DIR__ . "/lang/{$l}.php";
    $s = $strings[$l][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $s = str_replace(':' . $k, (string) $v, $s);
    }
    return $s;
}

function app_name(): string
{
    return lang() === 'ar' ? config('app_name') : config('app_name_en');
}

// ───────────────────────── Output ─────────────────────────

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $q = array_filter($query, static fn($v) => $v !== null && $v !== '' && $v !== []);
    return config('app_url') . '/' . ltrim($path, '/') . ($q ? '?' . http_build_query($q) : '');
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function upload_url(string $file): string
{
    return config('app_url') . config('upload_url') . '/' . rawurlencode($file);
}

function money(int|float $amount): string
{
    $n = number_format((float) $amount, 0, '.', ',');
    return lang() === 'ar' ? $n . ' ' . t('currency') : t('currency') . ' ' . $n;
}

function fmt_date(string $sqlDate): string
{
    $ts = strtotime($sqlDate . ' UTC');
    return $ts ? date('Y-m-d', $ts) : '';
}

function render(string $view, array $data = [], ?string $layout = 'layout'): void
{
    $content = render_partial($view, $data);
    if ($layout === null) {
        echo $content;
        clear_old();
        return;
    }
    echo render_partial($layout, $data + ['content' => $content]);
    clear_old(); // old input is single-use: shown once on the page after a failed submit
}

function render_partial(string $view, array $data = []): string
{
    $file = __DIR__ . '/views/' . $view . '.php';
    if (!preg_match('~^[a-z0-9_/\-]+$~', $view) || !is_file($file)) {
        throw new RuntimeException('View not found: ' . $view);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

// ───────────────────────── HTTP ─────────────────────────

function redirect(string $to, int $code = 303): never
{
    // Only allow local redirects to prevent open-redirect abuse.
    if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
        $to = url('/');
    }
    header('Location: ' . $to, true, $code);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $p = parse_url($ref);
    if ($ref && ($p['host'] ?? '') === parse_url('//' . $host, PHP_URL_HOST)) {
        redirect(($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : ''));
    }
    redirect(url($fallback));
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [400 => t('err_400'), 403 => t('err_403'), 404 => t('err_404'), 405 => t('err_405'), 419 => t('err_419'), 429 => t('err_429'), 500 => t('err_500')];
    render('error', ['code' => $code, 'title' => $titles[$code] ?? t('err_500'), 'message' => $message]);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    // Do not trust X-Forwarded-For by default (spoofable); configure your proxy to set REMOTE_ADDR.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function input(string $key, $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function input_int(string $key, ?int $default = null, ?array $src = null): ?int
{
    $src ??= $_POST + $_GET;
    $v = $src[$key] ?? null;
    if (!is_string($v) || $v === '') {
        return $default;
    }
    $v = str_replace([',', '٬'], '', convert_arabic_digits($v));
    $i = filter_var($v, FILTER_VALIDATE_INT);
    return $i === false ? $default : $i;
}

function input_float(string $key, ?float $default = null): ?float
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    if (!is_string($v) || $v === '') {
        return $default;
    }
    $f = filter_var(str_replace(['٫', ','], ['.', ''], convert_arabic_digits($v)), FILTER_VALIDATE_FLOAT);
    return $f === false ? $default : $f;
}

function convert_arabic_digits(string $s): string
{
    return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
}

function str_limit(string $s, int $len): string
{
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
}

// ───────────────────────── Session / CSRF / Flash ─────────────────────────

function start_session(): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_name(config('session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // Idle timeout: 2 hours
    $now = time();
    if (isset($_SESSION['_last']) && $now - $_SESSION['_last'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['_last'] = $now;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => t('err_419')], 419);
        }
        abort(419);
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function with_old(array $data, array $errors = []): void
{
    unset($data['_csrf'], $data['password'], $data['password_confirm'], $data['current_password']);
    $_SESSION['_old'] = $data;
    $_SESSION['_errors'] = $errors;
}

function old(string $key, $default = ''): string
{
    $v = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($v) ? (string) $v : '';
}

function old_array(string $key, array $default = []): array
{
    $v = $_SESSION['_old'][$key] ?? $default;
    return is_array($v) ? $v : $default;
}

function errors(): array
{
    static $errs = null;
    if ($errs === null) {
        $errs = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
    }
    return $errs;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/** Renders error message markup and aria attributes for a field. */
function field_error(string $field): string
{
    $err = errors()[$field] ?? null;
    return $err ? '<p class="field-error" id="err-' . e($field) . '">' . e($err) . '</p>' : '';
}

function field_aria(string $field): string
{
    return isset(errors()[$field]) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
}

// ───────────────────────── Auth ─────────────────────────

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $user = null;
    }
    $stmt = db()->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch() ?: null;
    if ($u && $u['status'] !== 'active') {
        logout_user();
        $u = null;
    }
    return $user = $u;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['_csrf']); // rotate CSRF token on privilege change
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', t('login_required'));
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect(url('login'));
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        abort(403);
    }
    return $u;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

// ───────────────────────── Domain helpers ─────────────────────────

const PROPERTY_TYPES = ['apartment', 'villa', 'house', 'land', 'office', 'shop', 'building', 'warehouse', 'chalet', 'farm'];
const PROPERTY_PURPOSES = ['sale', 'rent'];
const RENT_PERIODS = ['monthly', 'yearly', 'daily'];
const PROPERTY_FEATURES = ['parking', 'elevator', 'pool', 'garden', 'balcony', 'maid_room', 'driver_room', 'ac', 'kitchen', 'security', 'gym', 'basement', 'roof', 'sea_view'];
const PROPERTY_STATUSES = ['pending', 'published', 'rejected', 'sold'];

function cities(): array
{
    static $c = null;
    return $c ??= db()->query('SELECT * FROM cities ORDER BY name_en')->fetchAll();
}

function city_name(?array $row): string
{
    if (!$row) {
        return '';
    }
    return lang() === 'ar' ? ($row['city_ar'] ?? $row['name_ar'] ?? '') : ($row['city_en'] ?? $row['name_en'] ?? '');
}

function price_label(array $p): string
{
    $s = money((int) $p['price']);
    if ($p['purpose'] === 'rent' && !empty($p['rent_period'])) {
        $s .= ' / ' . t('period_' . $p['rent_period']);
    }
    return $s;
}

function favorite_ids(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $u = current_user();
    if (!$u) {
        return $ids = [];
    }
    $stmt = db()->prepare('SELECT property_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$u['id']]);
    return $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Simple pagination metadata */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Per-IP rate limit using the given table/column, returns true when allowed */
function rate_limited_inquiry(string $ip): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM inquiries WHERE ip = ? AND created_at > datetime('now', '-10 minutes')");
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= 5;
}
