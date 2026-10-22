<?php

declare(strict_types=1);

use SecureLogin\Auth;
use SecureLogin\EmailCodes;

$app = require dirname(__DIR__) . '/src/bootstrap.php';

$userId = $app->auth->pendingUserId(Auth::STEP_EMAIL);

if ($userId === null) {
    $app->web->redirect('/login.php');
}

$error = '';
$resent = false;

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    if ($app->web->input('action') === 'resend') {
        $user = $app->users->findById($userId);
        $resent = $user !== null && $app->emailCodes->send($userId, $user['email'], EmailCodes::LOGIN);
        $error = $resent ? '' : 'Ein neuer Code kann erst 60 Sekunden nach dem letzten angefordert werden.';
    } elseif ($app->emailCodes->check($userId, EmailCodes::LOGIN, $app->web->input('code'))) {
        $app->web->redirect($app->auth->afterEmailCode($userId));
    } else {
        $error = 'Der Code ist ungueltig oder abgelaufen.';

        if ($app->emailCodes->attemptsLeft($userId, EmailCodes::LOGIN) === 0) {
            $app->auth->cancelPending();
            $app->web->redirect('/login.php');
        }
    }
}

echo $app->web->render('verify-email', [
    'title' => 'Bestaetigungscode aus dem Postfach',
    'error' => $error,
    'resent' => $resent,
    'attemptsLeft' => $app->emailCodes->attemptsLeft($userId, EmailCodes::LOGIN),
]);
