<?php

declare(strict_types=1);

use SecureLogin\Auth;

$app = require dirname(__DIR__) . '/src/bootstrap.php';

$userId = $app->auth->pendingUserId(Auth::STEP_TOTP);

if ($userId === null) {
    $app->web->redirect('/login.php');
}

$error = '';

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    if (!$app->rateLimit->allow('totp', (string) $userId, 10, 900)) {
        $error = 'Zu viele Versuche. Bitte versuche es in 15 Minuten erneut.';
    } elseif ($app->twoFactor->verify($userId, $app->web->input('code'))) {
        $app->web->redirect($app->auth->afterTotp($userId));
    } else {
        $error = 'Der Code ist ungueltig oder wurde schon verwendet.';
    }
}

echo $app->web->render('verify-2fa', [
    'title' => 'Bestaetigungscode der App',
    'error' => $error,
]);
