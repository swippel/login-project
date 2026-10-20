#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Testhilfe: legt einen Bestaetigungscode fuer ein Konto an.
 *
 * Wird benutzt, um den Ablauf ohne echten E-Mail-Versand zu pruefen.
 * Aufruf:  php tests/seed-verification-code.php <email> [code]
 */

use SecureLogin\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = Application::create(dirname(__DIR__));

$email = mb_strtolower($argv[1] ?? '');
$code = $argv[2] ?? '123456';

$user = $app->users->findByEmail($email);

if ($user === null) {
    fwrite(STDERR, "Konto nicht gefunden: $email\n");
    exit(1);
}

$userId = (int) $user['id'];

$app->securityRepository->deleteVerifications($userId, \SecureLogin\SecurityRepository::PURPOSE_LOGIN);
$app->securityRepository->createVerification(
    $userId,
    $app->security->hashEmailCode($code),
    \SecureLogin\SecurityRepository::PURPOSE_LOGIN,
    \SecureLogin\Security::EMAIL_CODE_TTL_SECONDS,
);

echo "Code $code fuer $email hinterlegt.\n";
