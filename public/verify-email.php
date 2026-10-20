<?php

declare(strict_types=1);

use SecureLogin\Application;
use SecureLogin\AuthService;
use SecureLogin\SecurityRepository;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

if ($app->auth->userId() !== null) {
    $app->http->redirect('/account.php');
}

$purpose = SecurityRepository::PURPOSE_LOGIN;
$userId = $app->auth->pendingUserId(AuthService::SESSION_EMAIL_USER);

if ($userId === null) {
    $app->http->redirect('/login.php');
}

$error = '';
$resent = false;
$attemptsLeft = $app->passwordService->remainingEmailCodeAttempts($userId, $purpose);

if ($app->http->isPost()) {
    $app->http->requireValidCsrfToken();

    if ($app->http->input('action') === 'resend') {
        $user = $app->users->findById($userId);
        $resent = $user !== null
            && $app->passwordService->requestEmailCode($userId, (string) $user['email'], $purpose);

        if (!$resent) {
            $error = 'Ein neuer Code kann erst 60 Sekunden nach dem letzten angefordert werden.';
        }
    } else {
        $code = trim($app->http->input('code'));

        if ($app->passwordService->verifyEmailCode($userId, $purpose, $code)) {
            $app->http->redirect($app->auth->continueAfterEmailCode($userId));
        }

        $error = 'Der Code ist ungueltig oder abgelaufen.';
        $attemptsLeft = $app->passwordService->remainingEmailCodeAttempts($userId, $purpose);

        if ($attemptsLeft <= 0) {
            $app->auth->clearPending();
            $app->http->redirect('/login.php');
        }
    }
}

echo $app->view->render('verify-email.twig', [
    'title' => 'Bestaetigungscode aus dem Postfach',
    'error' => $error,
    'resent' => $resent,
    'attemptsLeft' => $attemptsLeft,
]);
