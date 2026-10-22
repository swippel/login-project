<?php

declare(strict_types=1);

use SecureLogin\EmailCodes;

$app = require dirname(__DIR__) . '/src/bootstrap.php';

$user = $app->auth->user();

if ($user === null) {
    $app->web->redirect('/login.php');
}

$userId = (int) $user['id'];
$error = '';
$success = '';

if ($app->web->isPost()) {
    $app->web->requireCsrf();

    $code = $app->web->input('code');

    switch ($app->web->input('action')) {
        case 'email_code_enable':
            // Erst die Adresse mit einem Code bestätigen, damit niemand eine fremde hinterlegt.
            if ($app->emailCodes->send($userId, $user['email'], EmailCodes::ADDRESS)) {
                $success = 'Wir haben dir einen Code geschickt. Bitte gib ihn unten ein.';
            } else {
                $error = 'Der Code konnte nicht gesendet werden. Bitte in 60 Sekunden erneut versuchen.';
            }

            break;

        case 'email_code_confirm':
            if ($app->emailCodes->check($userId, EmailCodes::ADDRESS, $code)) {
                $app->users->setEmailCode($userId, true);
                $user['email_code_login'] = 1;
                $success = 'Die Bestaetigung per E-Mail ist aktiviert.';
            } else {
                $error = 'Der Code ist ungueltig oder abgelaufen.';
            }

            break;

        case 'email_code_disable':
            $app->users->setEmailCode($userId, false);
            $app->emailCodes->clear($userId, EmailCodes::LOGIN);
            $user['email_code_login'] = 0;
            $success = 'Die Bestaetigung per E-Mail ist deaktiviert.';

            break;

        case 'totp_start':
            $app->twoFactor->beginSetup();
            $app->web->redirect('/account.php');

        case 'totp_cancel':
            $app->twoFactor->cancelSetup();
            $app->web->redirect('/account.php');

        case 'totp_enable':
            if ($app->twoFactor->completeSetup($userId, $code)) {
                $app->auth->refreshState($userId);
                $success = 'Zwei-Faktor-Authentifizierung ist aktiviert.';
            } else {
                $error = 'Der Code ist ungueltig. Pruefe die Uhrzeit deines Geraets.';
            }

            break;

        case 'totp_disable':
            if ($app->twoFactor->disable($user, $app->web->input('password'), $code)) {
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

echo $app->web->render('account', [
    'title' => 'Dein Konto',
    'email' => $user['email'],
    'error' => $error,
    'success' => $success,
    'emailCodeEnabled' => (bool) $user['email_code_login'],
    'emailCodePending' => $app->emailCodes->isAddressPending($userId),
    'twoFactorEnabled' => $app->twoFactor->isEnabled($userId),
    'setupSecret' => $setupSecret,
    'setupUri' => $setupSecret === null ? null : $app->twoFactor->setupUri($setupSecret, $user['email']),
]);
