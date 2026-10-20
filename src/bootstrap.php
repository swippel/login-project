<?php

declare(strict_types=1);

use SecureLogin\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Startet die Anwendung fuer einen HTTP-Aufruf.
 *
 * Aufrufende holen sich hierueber alle Bausteine:
 *
 *     $app = require dirname(__DIR__) . '/src/bootstrap.php';
 *     $app->auth->userId();
 */
$app = Application::create(dirname(__DIR__));

$app->session->start($app->http->isHttps());
$app->http->sendSecurityHeaders();

$app->view->addGlobal('csrf_token', $app->http->csrfToken());
$app->view->addGlobal('flash', [
    'success' => $app->session->takeFlash('success'),
    'error' => $app->session->takeFlash('error'),
]);

return $app;
