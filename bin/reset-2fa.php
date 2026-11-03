<?php
declare(strict_types=1);
/**
 * Turn off two-step login for one account — for when the phone AND the recovery codes are lost.
 * Needs shell / hosting-panel access to the server, so it is not a way around the code for anyone else.
 *
 *   php bin/reset-2fa.php admin@example.com
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('AQARAT_CLI', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
if ($email === '') {
    fwrite(STDERR, "Usage: php bin/reset-2fa.php <email>\n");
    exit(1);
}
$st = db()->prepare("UPDATE users SET totp_secret = NULL, totp_last_step = 0, recovery_codes = '[]' WHERE email = ?");
$st->execute([$email]);
if ($st->rowCount() === 0) {
    fwrite(STDERR, "No account with that email.\n");
    exit(1);
}
db()->prepare("INSERT INTO admin_log (user_id, action, target, ip) VALUES (NULL, '2fa.reset-cli', ?, 'cli')")->execute([$email]);
echo "Two-step login is now off for $email. Sign in with the password and turn it on again.\n";
