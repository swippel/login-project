<?php

declare(strict_types=1);

use SecureLogin\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

if ($app->auth->userId() !== null) {
    $app->http->redirect('/account.php');
}

$error = '';
$email = '';

if ($app->http->isPost()) {
    $app->http->requireValidCsrfToken();

    $email = mb_strtolower(trim($app->http->input('email')));
    $password = $app->http->input('password');

    if (!$app->security->allowAttempts('login', $app->http->clientIp(), 20, 900)) {
        $error = 'Zu viele Versuche. Bitte versuche es in 15 Minuten erneut.';
    } else {
        $user = $app->users->findByEmail($email);
        $valid = $user !== null
            && $app->security->verifyPassword($password, (string) $user['password_hash']);

        if (!$valid) {
            $error = 'E-Mail oder Passwort ist falsch.';
        } else {
            $app->auth->upgradePasswordHash($user, $password);

            $app->http->redirect($app->auth->startLogin($user));
        }
    }
}

if (isset($_GET['registered'])) {
    $app->session->flash('success', 'Konto erstellt. Du kannst dich jetzt anmelden.');
}

if (isset($_GET['reset'])) {
    $app->session->flash('success', 'Passwort geaendert. Bitte melde dich an.');
}

echo $app->view->render('login.twig', [
    'title' => 'Willkommen zurueck',
    'error' => $error,
    'email' => $email,
]);
