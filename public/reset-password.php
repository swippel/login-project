<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';

$token = $app->web->input('token');
$isValid = $app->passwordReset->isValid($token);
$error = '';

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    $password = $app->web->input('password');
    $error = $app->security->passwordError($password, $app->web->input('confirm_password')) ?? '';

    if (!$isValid) {
        $error = 'Dieser Link ist ungueltig oder abgelaufen.';
    } elseif ($error === '') {
        if ($app->passwordReset->reset($token, $password)) {
            $app->auth->logout();
            $app->web->redirect('/login.php?reset=1');
        }

        $error = 'Dieser Link ist ungueltig oder abgelaufen.';
    }
}

echo $app->web->render('reset-password.twig', [
    'title' => 'Neues Passwort',
    'error' => $error,
    'token' => $token,
    'isValid' => $isValid,
]);
