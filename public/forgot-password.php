<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';

$error = '';
$sent = false;

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    $email = $app->security->cleanEmail($app->web->input('email'));

    if ($email === null) {
        $error = 'Bitte gib eine gueltige E-Mail-Adresse ein.';
    } elseif (!$app->mailer->isConfigured()) {
        $error = 'Der E-Mail-Versand ist noch nicht eingerichtet. '
            . 'Trage RESEND_API_KEY und RESEND_FROM in der Datei .env ein.';
    } elseif (!$app->rateLimit->allow('reset', $app->web->clientIp(), 5, 3600)) {
        $error = 'Zu viele Anfragen. Bitte versuche es in einer Stunde erneut.';
    } else {
        $app->passwordReset->request($email);
        $sent = true;
    }
}

echo $app->web->render('forgot-password.twig', [
    'title' => 'Passwort vergessen?',
    'error' => $error,
    'sent' => $sent,
    'mailConfigured' => $app->mailer->isConfigured(),
]);
