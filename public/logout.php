<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';

if (!$app->web->isPost()) {
    http_response_code(405);
    exit('Method Not Allowed');
}

$app->web->requireCsrf();
$app->auth->logout();
$app->web->redirect('/login.php');
