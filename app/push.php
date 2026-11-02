<?php
declare(strict_types=1);

/**
 * Web Push without external libraries.
 *
 *  - VAPID (RFC 8292): an ES256-signed JWT identifies this server to the push service.
 *  - Payload encryption (RFC 8291, "aes128gcm"): ECDH P-256 + HKDF-SHA256 + AES-128-GCM.
 *
 * Keys are generated once and stored in data/vapid.json (outside the web root).
 */

function b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $s): string
{
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) {
        $s .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode($s, true);
    if ($out === false) {
        throw new InvalidArgumentException('Invalid base64url');
    }
    return $out;
}

/** Uncompressed P-256 public key (0x04 || X || Y) → PEM SubjectPublicKeyInfo. */
function p256_public_pem(string $raw): string
{
    if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
        throw new InvalidArgumentException('Invalid P-256 public key');
    }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Windows PHP (XAMPP) cannot create keys without an openssl.cnf — always pass our minimal one. */
function openssl_args(): array
{
    $cnf = dirname(__DIR__) . '/app/certs/openssl.cnf';
    $cnf = is_file($cnf) ? $cnf : __DIR__ . '/certs/openssl.cnf';
    return is_file($cnf) ? ['config' => $cnf] : [];
}

/** @return array{0: OpenSSLAsymmetricKey, 1: string} [private key, raw public key] */
function p256_generate(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC] + openssl_args());
    if ($key === false) {
        throw new RuntimeException('EC key generation failed');
    }
    return [$key, p256_raw_public($key)];
}

function p256_raw_public(OpenSSLAsymmetricKey $key): string
{
    $ec = openssl_pkey_get_details($key)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

/** @return array{public: string, private_pem: string} public is base64url (for applicationServerKey) */
function vapid_keys(): array
{
    static $keys = null;
    if ($keys) {
        return $keys;
    }
    $file = config('vapid_file');
    if (is_file($file)) {
        $keys = json_decode((string) file_get_contents($file), true);
        if (is_array($keys) && isset($keys['public'], $keys['private_pem'])) {
            return $keys;
        }
    }
    [$priv, $pub] = p256_generate();
    if (!openssl_pkey_export($priv, $pem, null, openssl_args())) {
        throw new RuntimeException('VAPID key export failed: ' . openssl_error_string());
    }
    $keys = ['public' => b64url_encode($pub), 'private_pem' => $pem];
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    // Write atomically and readable by the app user only.
    $tmp = $file . '.' . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode($keys), LOCK_EX);
    chmod($tmp, 0600);
    if (!@rename($tmp, $file)) { // another request won the race; use its keys
        @unlink($tmp);
        $keys = json_decode((string) file_get_contents($file), true);
    }
    return $keys;
}

/** DER ECDSA signature → raw 64-byte R||S as required by JWS ES256. */
function ecdsa_der_to_raw(string $der): string
{
    $pos = 2;                                  // skip SEQUENCE tag + length
    if (ord($der[1]) & 0x80) {
        $pos += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);            // INTEGER tag at $pos
        $int = substr($der, $pos + 2, $len);
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $out;
}

function vapid_auth_header(string $endpoint): string
{
    $keys = vapid_keys();
    $p = parse_url($endpoint);
    $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $header = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64url_encode(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => config('push_subject')]));
    $input = $header . '.' . $claims;
    if (!openssl_sign($input, $der, openssl_pkey_get_private($keys['private_pem']), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID signing failed');
    }
    return 'vapid t=' . $input . '.' . b64url_encode(ecdsa_der_to_raw($der)) . ', k=' . $keys['public'];
}

/**
 * Encrypt a payload for one subscription (RFC 8291 / RFC 8188 aes128gcm, single record).
 * $asKey lets tests inject a fixed application-server key.
 */
function push_encrypt(string $payload, string $uaPublicB64, string $authB64, ?OpenSSLAsymmetricKey $asKey = null, ?string $salt = null): string
{
    $uaPublic = b64url_decode($uaPublicB64);
    $authSecret = b64url_decode($authB64);
    if (strlen($authSecret) !== 16) {
        throw new InvalidArgumentException('Invalid auth secret');
    }
    if ($asKey === null) {
        [$asKey, $asPublic] = p256_generate();
    } else {
        $asPublic = p256_raw_public($asKey);
    }
    $shared = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($uaPublic)), $asKey, 32);
    if ($shared === false) {
        throw new RuntimeException('ECDH failed');
    }
    $salt ??= random_bytes(16);

    $ikm   = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

    $plain = $payload . "\x02";                     // 0x02 = last record delimiter
    $cipher = openssl_encrypt($plain, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('AES-GCM failed');
    }
    $rs = 4096;
    return $salt . pack('N', $rs) . chr(strlen($asPublic)) . $asPublic . $cipher . $tag;
}

/**
 * Send a notification to many subscriptions concurrently.
 *
 * @param array<int,array{id:int,endpoint:string,p256dh:string,auth:string}> $subs
 * @return array{sent:int, failed:int, gone:int[]} gone = subscription ids the push service says are expired
 */
function push_send_many(array $subs, array $message): array
{
    $result = ['sent' => 0, 'failed' => 0, 'gone' => []];
    if (!$subs) {
        return $result;
    }
    $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($payload) > 3000) {      // keep well below the 4 KB record limit
        $message['body'] = mb_substr($message['body'] ?? '', 0, 300);
        $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    foreach (array_chunk($subs, 50) as $batch) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($batch as $sub) {
            $endpoint = (string) $sub['endpoint'];
            if (!is_allowed_push_endpoint($endpoint)) {
                $result['failed']++;
                $result['gone'][] = (int) $sub['id'];
                continue;
            }
            try {
                $body = push_encrypt($payload, $sub['p256dh'], $sub['auth']);
                $auth = vapid_auth_header($endpoint);
            } catch (Throwable $e) {
                error_log('aqarat push: ' . $e->getMessage());
                $result['failed']++;
                $result['gone'][] = (int) $sub['id'];
                continue;
            }
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/octet-stream',
                    'Content-Encoding: aes128gcm',
                    'TTL: ' . (int) config('push_ttl'),
                    'Urgency: normal',
                    'Authorization: ' . $auth,
                ],
            ]);
            if (($ca = bundled_ca_file()) !== null) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $sub['id']] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $id => $ch) {
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code >= 200 && $code < 300) {
                $result['sent']++;
            } else {
                $result['failed']++;
                if (in_array($code, [404, 410], true)) {
                    $result['gone'][] = $id;
                }
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $result;
}

/**
 * Windows/macOS PHP builds (XAMPP, MAMP) often have no CA bundle configured, so TLS to FCM/Apple fails with
 * "unable to get local issuer certificate". Use the bundled Mozilla CA list only in that case.
 */
function bundled_ca_file(): ?string
{
    static $ca = false;
    if ($ca !== false) {
        return $ca;
    }
    $configured = (string) ini_get('curl.cainfo') !== '' || (string) ini_get('openssl.cafile') !== '';
    $file = __DIR__ . '/certs/cacert.pem';
    return $ca = (!$configured && PHP_OS_FAMILY !== 'Linux' && is_file($file)) ? $file : null;
}

/**
 * Push endpoints come from the browser, but a malicious client could register any URL.
 * Only allow HTTPS endpoints on public hostnames to avoid turning the server into an SSRF proxy.
 */
function is_allowed_push_endpoint(string $url): bool
{
    $p = parse_url($url);
    if (($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
        return false;
    }
    if (isset($p['port']) && (int) $p['port'] !== 443) {
        return false;
    }
    $host = strtolower($p['host']);
    $allowed = [
        'fcm.googleapis.com', 'android.googleapis.com',          // Chrome, Edge (Android), Opera
        'updates.push.services.mozilla.com',                     // Firefox
        'web.push.apple.com',                                    // Safari / iOS 16.4+
        '.notify.windows.com',                                   // Edge (Windows)
    ];
    foreach ($allowed as $a) {
        if ($host === ltrim($a, '.') || (str_starts_with($a, '.') && str_ends_with($host, $a))) {
            return true;
        }
    }
    return false;
}

// ───────────────────────── Domain: follows & sending ─────────────────────────

function upsert_subscription(array $sub, ?int $userId): int
{
    $endpoint = (string) ($sub['endpoint'] ?? '');
    $p256dh = (string) ($sub['keys']['p256dh'] ?? '');
    $auth = (string) ($sub['keys']['auth'] ?? '');
    if (!is_allowed_push_endpoint($endpoint) || strlen($endpoint) > 1000) {
        throw new InvalidArgumentException('endpoint');
    }
    try {
        if (strlen(b64url_decode($p256dh)) !== 65 || strlen(b64url_decode($auth)) !== 16) {
            throw new InvalidArgumentException('keys');
        }
    } catch (InvalidArgumentException) {
        throw new InvalidArgumentException('keys');
    }
    db()->prepare("INSERT INTO push_subscriptions (endpoint, p256dh, auth, user_id, lang) VALUES (?,?,?,?,?)
        ON CONFLICT(endpoint) DO UPDATE SET p256dh = excluded.p256dh, auth = excluded.auth,
            user_id = COALESCE(excluded.user_id, push_subscriptions.user_id), lang = excluded.lang,
            fail_count = 0, last_seen_at = datetime('now')")
        ->execute([$endpoint, $p256dh, $auth, $userId, lang()]);
    $stmt = db()->prepare('SELECT id FROM push_subscriptions WHERE endpoint = ?');
    $stmt->execute([$endpoint]);
    return (int) $stmt->fetchColumn();
}

/**
 * Deliver a notification and record it. Audience: followers of an office, or everyone opted into site news.
 * @return array{id:int, sent:int, failed:int, total:int}
 */
function send_notification(?int $senderId, ?int $officeId, string $audience, string $title, string $body, string $url, ?int $propertyId = null, ?string $icon = null, ?string $image = null, string $kind = 'manual'): array
{
    if ($audience === 'followers') {
        $stmt = db()->prepare('SELECT s.id, s.endpoint, s.p256dh, s.auth FROM push_subscriptions s
            JOIN office_follows f ON f.subscription_id = s.id WHERE f.office_id = ?');
        $stmt->execute([$officeId]);
    } else {
        $stmt = db()->query('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE site_news = 1');
    }
    $subs = $stmt->fetchAll();

    db()->prepare('INSERT INTO notifications (sender_id, office_id, audience, kind, title, body, url, property_id) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$senderId, $officeId, $audience, $kind, $title, $body, $url, $propertyId]);
    $nid = (int) db()->lastInsertId();

    $base = absolute_base_url();
    $res = push_send_many($subs, [
        'title' => $title,
        'body'  => $body,
        'url'   => $base . $url,
        'icon'  => $icon ? $base . $icon : $base . '/assets/img/icon-192.png',
        'badge' => $base . '/assets/img/badge-72.png',
        'image' => $image ? $base . $image : null,
        'tag'   => 'n' . $nid,
        'dir'   => 'auto',
    ]);

    if ($res['gone']) {
        $in = implode(',', array_map('intval', $res['gone']));
        db()->exec("DELETE FROM push_subscriptions WHERE id IN ($in)");
    }
    db()->prepare('UPDATE notifications SET sent = ?, failed = ? WHERE id = ?')->execute([$res['sent'], $res['failed'], $nid]);
    return ['id' => $nid, 'sent' => $res['sent'], 'failed' => $res['failed'], 'total' => count($subs)];
}

/** Absolute URL of the install folder, e.g. https://example.com/aqarat (used inside push payloads). */
function absolute_base_url(): string
{
    $cfg = (string) config('app_url');
    if (preg_match('~^https?://~', $cfg)) {
        return $cfg;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host . base_path();
}

/**
 * A listing just went live: notify (once per listing)
 *   1. the followers of its office, and
 *   2. every device whose saved search matches it (skipping devices already notified in step 1).
 */
function notify_listing_published(array $p): void
{
    $stmt = db()->prepare("SELECT 1 FROM notifications WHERE property_id = ? AND kind = 'auto'");
    $stmt->execute([$p['id']]);
    if ($stmt->fetchColumn()) {
        return;
    }
    try {
        $notified = [];
        $icon = $p['office_logo'] ? '/uploads/' . $p['office_logo'] : null;
        $image = $p['cover_thumb'] ? '/uploads/' . $p['cover_thumb'] : null;
        $body = $p['title'] . ' — ' . price_label($p);
        $url = '/property/' . (int) $p['id'];

        if (!empty($p['office_id'])) {
            $s = db()->prepare('SELECT subscription_id FROM office_follows WHERE office_id = ?');
            $s->execute([$p['office_id']]);
            $notified = array_flip(array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN)));
            if ($notified) {
                send_notification((int) $p['user_id'], (int) $p['office_id'], 'followers',
                    $p['office_name'] . ' · ' . t('push_new_listing'), $body, $url, (int) $p['id'], $icon, $image, 'auto');
            }
        }

        // Saved searches → group matching subscriptions, one push per device
        $rows = db()->query('SELECT ss.id, ss.subscription_id, ss.filters, s.endpoint, s.p256dh, s.auth, s.lang
            FROM saved_searches ss JOIN push_subscriptions s ON s.id = ss.subscription_id')->fetchAll();
        $targets = [];
        foreach ($rows as $r) {
            $sid = (int) $r['subscription_id'];
            if (isset($notified[$sid]) || isset($targets[$sid])) {
                continue;
            }
            $f = json_decode((string) $r['filters'], true);
            if (is_array($f) && listing_matches_filters($p, search_filters($f))) {
                $targets[$sid] = $r;
                db()->prepare('UPDATE saved_searches SET hits = hits + 1 WHERE id = ?')->execute([$r['id']]);
            }
        }
        if (!$targets) {
            return;
        }
        $base = absolute_base_url();
        $ins = db()->prepare("INSERT INTO notifications (subscription_id, audience, kind, title, body, url, property_id, sent, failed)
            VALUES (?, 'search', 'auto', ?, ?, ?, ?, ?, ?)");
        $res = push_send_many(array_map(static fn($r) => ['id' => $r['subscription_id']] + $r, array_values($targets)), [
            'title' => t('push_search_match'),
            'body'  => $body,
            'url'   => $base . $url,
            'icon'  => $base . ($icon ?? '/assets/img/icon-192.png'),
            'badge' => $base . '/assets/img/badge-72.png',
            'image' => $image ? $base . $image : null,
            'tag'   => 'p' . (int) $p['id'],
            'dir'   => 'auto',
        ]);
        $gone = array_flip($res['gone']);
        foreach ($targets as $sid => $r) {
            if (!isset($gone[$sid])) {
                $ins->execute([$sid, t('push_search_match'), $body, $url, (int) $p['id'], 1, 0]);
            }
        }
        if ($res['gone']) {
            db()->exec('DELETE FROM push_subscriptions WHERE id IN (' . implode(',', array_map('intval', $res['gone'])) . ')');
        }
    } catch (Throwable $e) {
        error_log('aqarat notify_listing_published: ' . $e->getMessage());
    }
}

/** Does a property satisfy a (normalised) search filter set? Mirrors search_where(). */
function listing_matches_filters(array $p, array $f): bool
{
    if ($p['status'] !== 'published') {
        return false;
    }
    foreach (['purpose', 'type', 'deed'] as $k) {
        if (($f[$k] ?? '') !== '' && $p[$k] !== $f[$k]) {
            return false;
        }
    }
    $usd = (int) $p['price_usd'];
    return !(
        (!empty($f['city']) && (int) $p['city_id'] !== (int) $f['city'])
        || ($f['min_price'] !== null && $usd < $f['min_price'])
        || ($f['max_price'] !== null && $usd > $f['max_price'])
        || ($f['min_area'] !== null && (float) $p['area'] < $f['min_area'])
        || ($f['beds'] !== null && (int) $p['bedrooms'] < $f['beds'])
        || ($f['baths'] !== null && (int) $p['bathrooms'] < $f['baths'])
        || ($f['furnished'] && !(int) $p['furnished'])
        || ($f['office'] !== null && (int) ($p['office_id'] ?? 0) !== $f['office'])
        || (($pt = parse_near($f['near'] ?? '')) !== null && ($p['lat'] === null
            || distance_km($pt[0], $pt[1], (float) $p['lat'], (float) $p['lng']) > (float) $f['r']))
        || ($f['q'] !== '' && !text_matches_all($f['q'], $p['title'] . ' ' . $p['description'] . ' ' . $p['district'] . ' ' . $p['address'] . ' ' . $p['project']))
    );
}

function text_matches_all(string $q, string $haystack): bool
{
    foreach (preg_split('/[\s,،]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
        if (mb_stripos($haystack, $term) === false) {
            return false;
        }
    }
    return true;
}

/** Only the meaningful (non-default) filters, for storage and labels. */
function compact_filters(array $f): array
{
    unset($f['sort']);
    if (($f['near'] ?? '') === '') {
        unset($f['r']);
    }
    return array_filter($f, static fn($v) => $v !== null && $v !== '' && $v !== 0 && $v !== false);
}

/** Human label in the current language, e.g. "خانوو · هەولێر · ≤ $200,000 · 3+ ژوور". */
function search_label(array $f): string
{
    $parts = [];
    if (!empty($f['purpose'])) { $parts[] = t('purpose_' . $f['purpose']); }
    if (!empty($f['type'])) { $parts[] = t('type_' . $f['type']); }
    if (!empty($f['city'])) {
        foreach (cities() as $c) {
            if ((int) $c['id'] === (int) $f['city']) { $parts[] = city_name($c); }
        }
    }
    if (isset($f['min_price']) && isset($f['max_price'])) { $parts[] = money((int) $f['min_price']) . '–' . money((int) $f['max_price']); }
    elseif (isset($f['max_price'])) { $parts[] = '≤ ' . money((int) $f['max_price']); }
    elseif (isset($f['min_price'])) { $parts[] = '≥ ' . money((int) $f['min_price']); }
    if (!empty($f['beds'])) { $parts[] = (int) $f['beds'] . '+ ' . t('beds_short'); }
    if (!empty($f['min_area'])) { $parts[] = '≥ ' . (int) $f['min_area'] . ' ' . t('sqm'); }
    if (!empty($f['deed'])) { $parts[] = t('deed_' . $f['deed']); }
    if (!empty($f['furnished'])) { $parts[] = t('furnished'); }
    if (!empty($f['q'])) { $parts[] = '“' . $f['q'] . '”'; }
    if (!empty($f['near'])) { $parts[] = t('within_km', ['r' => (int) ($f['r'] ?? 5)]); }
    return $parts ? implode(' · ', $parts) : t('all_listings');
}
