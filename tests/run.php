<?php

declare(strict_types=1);

/**
 * Tests ohne Datenbank und ohne Test-Framework:  php tests/run.php
 */

use SecureLogin\Config;
use SecureLogin\Security;
use SecureLogin\Totp;

require_once dirname(__DIR__) . '/vendor/autoload.php';

putenv('APP_KEY=' . base64_encode(str_repeat('k', 32)));
$security = new Security(Config::fromFile('/nicht/vorhanden'));

$failures = 0;

function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    $failures += $ok ? 0 : 1;
}

// RFC 6238, Anhang B: Geheimnis "12345678901234567890", Zeit 59 s -> 94287082 (letzte sechs Stellen)
$rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
check('TOTP entspricht dem RFC-6238-Testvektor', Totp::code($rfcSecret, intdiv(59, 30)) === '287082');
check('TOTP: Code des aktuellen Schritts passt', Totp::matchingStep($rfcSecret, '287082', -1, 59) === 1);
check('TOTP: Code des Vorschritts passt noch', Totp::matchingStep($rfcSecret, '287082', -1, 89) === 1);
check('TOTP: verbrauchter Schritt wird abgelehnt', Totp::matchingStep($rfcSecret, '287082', 1, 59) === null);
check('TOTP: falscher Code wird abgelehnt', Totp::matchingStep($rfcSecret, '000000', -1, 59) === null);
check('TOTP: neues Geheimnis hat 32 Zeichen', strlen(Totp::generateSecret()) === 32);

$hash = $security->hashPassword('ein langes passwort');
check('Passwort: richtiges wird akzeptiert', $security->verifyPassword('ein langes passwort', $hash));
check('Passwort: falsches wird abgelehnt', !$security->verifyPassword('falsch', $hash));
check('Passwort: zu kurz wird abgelehnt', $security->passwordError('kurz', 'kurz') !== null);
check('Passwort: Abweichung bei der Wiederholung wird abgelehnt', $security->passwordError('123456789012', '123456789013') !== null);
check('Passwort: gueltig', $security->passwordError('123456789012', '123456789012') === null);

check('E-Mail wird klein geschrieben', $security->cleanEmail('  Name@Example.COM ') === 'name@example.com');
check('E-Mail: Unsinn wird abgelehnt', $security->cleanEmail('kein-at') === null);

$encrypted = $security->encrypt('geheim');
check('Verschluesselung: Rundlauf', $security->decrypt($encrypted['nonce'], $encrypted['ciphertext']) === 'geheim');

$code = $security->generateCode();
check('E-Mail-Code hat sechs Ziffern', preg_match('/^[0-9]{6}$/D', $code) === 1);
check('E-Mail-Code: Hash ist stabil und nicht der Klartext', $security->hashCode($code) === $security->hashCode($code) && $security->hashCode($code) !== $code);

exit($failures === 0 ? 0 : 1);
