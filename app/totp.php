<?php
declare(strict_types=1);

/**
 * Two-step login: time-based one-time codes (RFC 6238 — Google Authenticator, Microsoft Authenticator, 2FAS…).
 * Pure PHP, works offline. Codes are 6 digits, change every 30 s; ±1 step is accepted for clock drift,
 * and a code can never be used twice (the last accepted step is stored).
 */

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const RECOVERY_CODES = 8;
const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32_encode(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= B32[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $s): string
{
    $s = strtoupper((string) preg_replace('/[\s=-]+/', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) {
        $v = strpos(B32, $c);
        if ($v === false) {
            return '';
        }
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));          // 160 bits, as RFC 4226 recommends
}

function totp_code(string $secret, int $step): string
{
    $key = base32_decode($secret);
    $h = hash_hmac('sha1', pack('J', $step), $key, true);
    $o = ord($h[19]) & 0x0f;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/** The time step a valid code belongs to (newer than $lastStep), or null. */
function totp_verify(string $secret, string $code, int $lastStep = 0, ?int $now = null): ?int
{
    $code = (string) preg_replace('/\D/', '', convert_arabic_digits($code));
    if (strlen($code) !== TOTP_DIGITS) {
        return null;
    }
    $step = intdiv($now ?? time(), TOTP_PERIOD);
    foreach ([0, -1, 1] as $d) {
        $s = $step + $d;
        if ($s > $lastStep && hash_equals(totp_code($secret, $s), $code)) {
            return $s;
        }
    }
    return null;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = app_name();
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer) . '&period=' . TOTP_PERIOD . '&digits=' . TOTP_DIGITS;
}

/** 8 one-time recovery codes (shown once); only their hashes are stored. */
function new_recovery_codes(): array
{
    $codes = [];
    for ($i = 0; $i < RECOVERY_CODES; $i++) {
        $c = strtolower(base32_encode(random_bytes(5)));      // 8 characters, 40 bits each
        $codes[] = substr($c, 0, 4) . '-' . substr($c, 4, 4);
    }
    return $codes;
}

function recovery_hash(string $code): string
{
    return hash('sha256', 'aqarat-recovery:' . strtolower((string) preg_replace('/[^a-z2-7]/i', '', $code)));
}

/** Use up a recovery code: returns the remaining hashes, or null if the code isn't one of them. */
function use_recovery_code(array $hashes, string $code): ?array
{
    $h = recovery_hash($code);
    foreach ($hashes as $i => $stored) {
        if (hash_equals((string) $stored, $h)) {
            unset($hashes[$i]);
            return array_values($hashes);
        }
    }
    return null;
}

function user_2fa(int $userId): ?array
{
    $st = db()->prepare('SELECT totp_secret, totp_last_step, recovery_codes FROM users WHERE id = ?');
    $st->execute([$userId]);
    $r = $st->fetch();
    return ($r && $r['totp_secret']) ? $r : null;
}
