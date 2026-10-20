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
    $confirmation = $app->http->input('confirm_password');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'Bitte gib eine gueltige E-Mail-Adresse ein.';
    } elseif (strlen($password) < 12 || strlen($password) > 1024) {
        $error = 'Das Passwort muss zwischen 12 und 1024 Zeichen lang sein.';
    } elseif ($password !== $confirmation) {
        $error = 'Die Passwoerter stimmen nicht ueberein.';
    } else {
        try {
            $app->users->create($email, $app->security->hashPassword($password));
            $app->http->redirect('/login.php?registered=1');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Diese E-Mail-Adresse ist bereits registriert.';
            } else {
                throw $exception;
            }
        }
    }
}

echo $app->view->render('register.twig', [
    'title' => 'Konto erstellen',
    'error' => $error,
    'email' => $email,
]);
