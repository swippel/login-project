<?php

declare(strict_types=1);

use SecureLogin\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$userId = $app->auth->userId();

if ($userId === null) {
    $app->http->redirect('/login.php');
}

$user = $app->users->findById($userId);

if ($user === null) {
    $app->auth->logout();
    $app->session->destroy();
    $app->http->redirect('/login.php');
}

$error = '';
$success = '';

if ($app->http->isPost()) {
    $app->http->requireValidCsrfToken();

    switch ($app->http->input('action')) {
        case 'email_code_enable':
            $sent = $app->passwordService->requestAddressConfirmation($user);

            $success = $sent
                ? 'Wir haben dir einen Code geschickt. Bitte gib ihn unten ein.'
                : 'Der Code konnte nicht gesendet werden. Bitte in 60 Sekunden erneut versuchen.';

            break;

        case 'email_code_confirm':
            if ($app->passwordService->confirmAddress($userId, trim($app->http->input('code')))) {
                $success = 'Die Bestaetigung per E-Mail ist aktiviert.';
            } else {
                $error = 'Der Code ist ungueltig oder abgelaufen.';
            }

            break;

        case 'email_code_disable':
            $app->passwordService->disableEmailCode($userId);
            $success = 'Die Bestaetigung per E-Mail ist deaktiviert.';

            break;

        case 'totp_start':
            $app->twoFactor->beginSetup();
            $app->http->redirect('/account.php');

            break;

        case 'totp_enable':
            if ($app->twoFactor->completeSetup($userId, trim($app->http->input('code')))) {
                $app->auth->refreshState($userId);
                $success = 'Zwei-Faktor-Authentifizierung ist aktiviert.';
            } else {
                $error = 'Der Code ist ungueltig. Pruefe die Uhrzeit deines Geraets.';
            }

            break;

        case 'totp_cancel':
            $app->twoFactor->cancelSetup();
            $app->http->redirect('/account.php');

            break;

        case 'totp_disable':
            $password = $app->http->input('password');
            $code = trim($app->http->input('code'));

            if ($app->twoFactor->disable($user, $password, $code)) {
                $app->auth->refreshState($userId);
                $success = 'Zwei-Faktor-Authentifizierung ist deaktiviert.';
            } else {
                $error = 'Passwort oder Code ist falsch.';
            }

            break;

        default:
            $error = 'Unbekannte Aktion.';
    }
}

$setupSecret = $app->twoFactor->setupSecret();
$setupUri = $setupSecret === null
    ? null
    : $app->twoFactor->setupUri($setupSecret, (string) $user['email']);

echo $app->view->render('account.twig', [
    'title' => 'Dein Konto',
    'email' => (string) $user['email'],
    'error' => $error,
    'success' => $success,
    'emailCodeEnabled' => (int) $user['email_code_login'] === 1,
    'emailCodePending' => $app->passwordService->isAddressConfirmationPending(),
    'twoFactorEnabled' => $app->twoFactor->isEnabled($userId),
    'setupSecret' => $setupSecret,
    'setupUri' => $setupUri,
]);
