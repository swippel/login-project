<?php

declare(strict_types=1);

use SecureLogin\Application;

/** @var Application $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$app->http->redirect($app->auth->userId() !== null ? '/account.php' : '/login.php');
