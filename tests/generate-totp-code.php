#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Testhilfe: erzeugt einen gueltigen Einmalcode fuer ein Konto.
 *
 * Der Code wird aus dem verschluesselten Geheimnis berechnet, so wie es
 * die Authenticator-App tut. Dient nur zum Testen.
 *
 * Aufruf:  php tests/generate-totp-code.php <email>
 */

use SecureLogin\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = Application::create(dirname(__DIR__));

$email = mb_strtolower($argv[1] ?? '');
$user = $app->users->findByEmail($email);

if ($user === null) {
    fwrite(STDERR, "Konto nicht gefunden: $email\n");
    exit(1);
}

$row = $app->securityRepository->findTotp((int) $user['id']);

if ($row === null) {
    fwrite(STDERR, "Fuer dieses Konto ist keine 2FA eingerichtet.\n");
    exit(1);
}

$secret = $app->security->decryptTotpSecret(
    (string) $row['secret_nonce'],
    (string) $row['secret_ciphertext'],
);

$step = intdiv(time(), 30);

echo $app->security->totpCode($secret, $step), PHP_EOL;
