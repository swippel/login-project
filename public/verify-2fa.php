<?php

declare(strict_types=1);

use SecureLogin\Application;
use SecureLogin\AuthService;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

if ($app->auth->userId() !== null) {
    $app->http->redirect('/account.php');
}

$userId = $app->auth->pendingUserId(AuthService::SESSION_TOTP_USER);

if ($userId === null) {
    $app->http->redirect('/login.php');
}

$error = '';

if ($app->http->isPost()) {
    $app->http->requireValidCsrfToken();

    $code = trim($app->http->input('code'));

    if ($app->twoFactor->verify($userId, $code)) {
        $app->http->redirect($app->auth->continueAfterTotp($userId));
    }

    $error = 'Der Code ist ungueltig oder wurde schon verwendet.';
}

echo $app->view->render('verify-2fa.twig', [
    'title' => 'Bestaetigungscode der App',
    'error' => $error,
]);
