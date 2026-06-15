<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode check
if (file_exists($maintenance = __DIR__.'/app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoloader (pre-installed, ships in zip)
require __DIR__.'/app/vendor/autoload.php';

// Bootstrap Laravel
/** @var Application $app */
$app = require_once __DIR__.'/app/bootstrap/app.php';

$app->handleRequest(Request::capture());