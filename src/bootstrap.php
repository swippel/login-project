<?php

declare(strict_types=1);

use SecureLogin\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Startet die Anwendung für einen HTTP-Aufruf.
 *
 *     $app = require dirname(__DIR__) . '/src/bootstrap.php';
 */
$app = new Application(dirname(__DIR__));
$app->web->start();

return $app;
