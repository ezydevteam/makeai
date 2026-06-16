<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Clear stale config/route caches if application is not installed yet
$envPath = __DIR__.'/makeai/.env';
if (!file_exists($envPath) || !str_contains((string)@file_get_contents($envPath), 'INSTALLED=true')) {
    @unlink(__DIR__.'/makeai/bootstrap/cache/config.php');
    @unlink(__DIR__.'/makeai/bootstrap/cache/routes-v7.php');
}

// Maintenance mode check
if (file_exists($maintenance = __DIR__.'/makeai/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoloader (pre-installed, ships in zip)
require __DIR__.'/makeai/vendor/autoload.php';

// Bootstrap Laravel
/** @var Application $app */
$app = require_once __DIR__.'/makeai/bootstrap/app.php';

$app->handleRequest(Request::capture());