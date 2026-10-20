<?php

declare(strict_types=1);

use SecureLogin\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$token = $app->http->input('token');
$error = '';
$isValid = preg_match('/^[a-f0-9]{64}$/D', $token) === 1
    && $app->passwordService->isPasswordResetTokenValid($token);

if ($app->http->isPost()) {
    $app->http->requireValidCsrfToken();

    $password = $app->http->input('password');
    $confirmation = $app->http->input('confirm_password');

    if (!$isValid) {
        $error = 'Dieser Link ist ungueltig oder abgelaufen.';
    } elseif (strlen($password) < 12 || strlen($password) > 1024) {
        $error = 'Das Passwort muss zwischen 12 und 1024 Zeichen lang sein.';
    } elseif ($password !== $confirmation) {
        $error = 'Die Passwoerter stimmen nicht ueberein.';
    } elseif (!$app->passwordService->resetPassword($token, $password)) {
        $error = 'Dieser Link ist ungueltig oder abgelaufen.';
    } else {
        $app->auth->logout();
        $app->session->destroy();

        $app->http->redirect('/login.php?reset=1');
    }
}

echo $app->view->render('reset-password.twig', [
    'title' => 'Neues Passwort',
    'error' => $error,
    'token' => $token,
    'isValid' => $isValid,
]);
