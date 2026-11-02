<?php
// Unit test for the Web Push crypto: run `php tests/push_test.php`
declare(strict_types=1);
define('AQARAT_CLI', true);
putenv('AQARAT_VAPID_FILE=' . sys_get_temp_dir() . '/aqarat_vapid_test_' . getmypid() . '.json');
require dirname(__DIR__) . '/app/bootstrap.php';

$fail = 0;
$ok = function (bool $c, string $m) use (&$fail) { echo ($c ? 'PASS ' : 'FAIL ') . $m . PHP_EOL; $fail += $c ? 0 : 1; };

// --- Simulate a browser subscription (user agent key pair + auth secret)
[$uaPriv, $uaPub] = p256_generate();
$auth = random_bytes(16);
$payload = json_encode(['title' => 'نوسینگەی ئاسۆ', 'body' => 'خانووی نوێ لە هەولێر — $120,000'], JSON_UNESCAPED_UNICODE);

$msg = push_encrypt($payload, b64url_encode($uaPub), b64url_encode($auth));

// --- Decrypt exactly like a browser would (RFC 8291)
$salt = substr($msg, 0, 16);
$rs = unpack('N', substr($msg, 16, 4))[1];
$idlen = ord($msg[20]);
$asPub = substr($msg, 21, $idlen);
$ct = substr($msg, 21 + $idlen);
$shared = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($asPub)), $uaPriv, 32);
$ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
$nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
$plain = openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
$ok($rs === 4096 && $idlen === 65, 'aes128gcm header layout');
$ok($plain === $payload . "\x02", 'payload decrypts with UA private key');

// Tampering must fail authentication
$bad = $ct; $bad[5] = chr(ord($bad[5]) ^ 1);
$ok(openssl_decrypt(substr($bad, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($bad, -16)) === false, 'tampered ciphertext rejected');

// --- VAPID JWT verifies with the published public key
$hdr = vapid_auth_header('https://fcm.googleapis.com/fcm/send/abc');
preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $hdr, $m);
$claims = json_decode(b64url_decode($m[2]), true);
$ok($claims['aud'] === 'https://fcm.googleapis.com' && $claims['exp'] > time(), 'JWT audience/expiry');
$raw = b64url_decode($m[3]);
$int = fn(string $x) => (ord(ltrim($x, "\0")[0] ?? "\0") & 0x80 ? "\0" : '') . ltrim($x, "\0");
$r = $int(substr($raw, 0, 32)); $s = $int(substr($raw, 32));
$der = "\x30" . chr(4 + strlen($r) + strlen($s)) . "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
$ok(openssl_verify($m[1] . '.' . $m[2], $der, p256_public_pem(b64url_decode($m[4])), OPENSSL_ALGO_SHA256) === 1, 'ES256 signature verifies');

// --- SSRF guard
$ok(is_allowed_push_endpoint('https://fcm.googleapis.com/fcm/send/x'), 'FCM allowed');
$ok(is_allowed_push_endpoint('https://wns2-par02p.notify.windows.com/w/?token=x'), 'WNS allowed');
foreach (['http://fcm.googleapis.com/x', 'https://127.0.0.1/x', 'https://evil.com/fcm.googleapis.com', 'https://fcm.googleapis.com.evil.com/x', 'https://fcm.googleapis.com:8443/x', 'https://u:p@fcm.googleapis.com/x'] as $u) {
    $ok(!is_allowed_push_endpoint($u), "blocked $u");
}

// --- phone helpers
$ok(phone_intl('0750 123 4567') === '9647501234567', 'local phone → intl');
$ok(phone_intl('+964 770 123 4567') === '9647701234567', '+964 phone');
$ok(phone_intl('٠٧٥٠١٢٣٤٥٦٧') === '9647501234567', 'Eastern Arabic digits');

@unlink(config('vapid_file'));
exit($fail ? 1 : 0);
