<?php

declare(strict_types=1);

use SecureLogin\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

if (!$app->http->isPost()) {
    http_response_code(405);
    exit('Method Not Allowed');
}

$app->http->requireValidCsrfToken();

$app->auth->logout();
$app->session->destroy();

$app->http->redirect('/login.php');
