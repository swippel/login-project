<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';

$app->web->redirect($app->auth->userId() !== null ? '/account.php' : '/login.php');
