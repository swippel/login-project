<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';

if ($app->auth->userId() !== null) {
    $app->web->redirect('/account.php');
}

if (isset($_GET['registered'])) {
    $app->web->flash('success', 'Konto erstellt. Du kannst dich jetzt anmelden.');
}

if (isset($_GET['reset'])) {
    $app->web->flash('success', 'Passwort geaendert. Bitte melde dich an.');
}

$error = '';
$email = '';

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    $email = mb_strtolower(trim($app->web->input('email')));
    $password = $app->web->input('password');

    if (!$app->rateLimit->allow('login', $app->web->clientIp(), 20, 900)) {
        $error = 'Zu viele Versuche. Bitte versuche es in 15 Minuten erneut.';
    } else {
        $user = $app->users->findByEmail($email);

        if ($user === null || !$app->security->verifyPassword($password, $user['password_hash'])) {
            $error = 'E-Mail oder Passwort ist falsch.';
        } else {
            $app->web->redirect($app->auth->afterPassword($user));
        }
    }
}

echo $app->web->render('login.twig', [
    'title' => 'Willkommen zurueck',
    'error' => $error,
    'email' => $email,
]);
