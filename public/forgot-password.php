<?php

declare(strict_types=1);

use SecureLogin\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$error = '';
$sent = false;

if ($app->http->isPost()) {
    $app->http->requireValidCsrfToken();

    $email = mb_strtolower(trim($app->http->input('email')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'Bitte gib eine gueltige E-Mail-Adresse ein.';
    } elseif (!$app->mailer->isConfigured()) {
        $error = 'Der E-Mail-Versand ist noch nicht eingerichtet. '
            . 'Trage RESEND_API_KEY und RESEND_FROM in der Datei .env ein.';
    } elseif (!$app->security->allowAttempts('reset', $app->http->clientIp(), 5, 3600)) {
        $error = 'Zu viele Anfragen. Bitte versuche es in einer Stunde erneut.';
    } else {
        $app->passwordService->requestPasswordReset($email);
        $sent = true;
    }
}

echo $app->view->render('forgot-password.twig', [
    'title' => 'Passwort vergessen?',
    'error' => $error,
    'sent' => $sent,
    'mailConfigured' => $app->mailer->isConfigured(),
]);
