<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';

if ($app->auth->userId() !== null) {
    $app->web->redirect('/account.php');
}

$error = '';
$email = '';

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    $email = mb_strtolower(trim($app->web->input('email')));
    $password = $app->web->input('password');

    if ($app->security->cleanEmail($email) === null) {
        $error = 'Bitte gib eine gueltige E-Mail-Adresse ein.';
    } else {
        $error = $app->security->passwordError($password, $app->web->input('confirm_password')) ?? '';
    }

    if ($error === '') {
        try {
            $app->users->create($email, $app->security->hashPassword($password));
            $app->web->redirect('/login.php?registered=1');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $error = 'Diese E-Mail-Adresse ist bereits registriert.';
        }
    }
}

echo $app->web->render('register', [
    'title' => 'Konto erstellen',
    'error' => $error,
    'email' => $email,
]);
