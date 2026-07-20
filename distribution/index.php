<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The Laravel application lives one level down, outside the served document
// root's reach (see .htaccess / web.config / deploy/nginx.conf.example).
$app_dir = __DIR__.'/core';

if (! is_file($app_dir.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('MakeAI: the "core" folder is missing or incomplete. Please re-upload it from the package without renaming it.');
}

$envPath = $app_dir.'/.env';

/**
 * First run: write a .env the installer can actually boot with.
 *
 * Without this, a freshly extracted package returns HTTP 500 instead of the
 * wizard — config defaults put sessions on the `database` driver and the
 * database on a sqlite file that does not exist, so the session middleware
 * queries a missing database before anything can render.
 *
 * Everything written here is deliberately dependency-free (file sessions, file
 * cache, sync queue, no database). The wizard replaces these with the buyer's
 * real settings when it finalises.
 */
if (! file_exists($envPath)) {
    if (! is_file($app_dir.'/.env.example')) {
        http_response_code(500);
        exit('MakeAI: core/.env.example is missing. Please re-upload the "core" folder from the package.');
    }

    if (! is_writable($app_dir)) {
        http_response_code(500);
        exit(
            'MakeAI: the "core" folder is not writable, so the configuration file could not be created. '
            .'Please set its permissions to 755 (or 775) in your hosting file manager and reload this page.'
        );
    }

    $env = (string) file_get_contents($app_dir.'/.env.example');

    // A key generated per install — never a shipped constant, which would let
    // anyone forge sessions and cookies on every site running this product.
    $bootstrapValues = [
        'APP_ENV'          => 'production',
        'APP_DEBUG'        => 'false',
        'APP_KEY'          => 'base64:'.base64_encode(random_bytes(32)),
        'INSTALLED'        => 'false',
        // Install-time drivers: none of these need a database or a queue worker.
        'SESSION_DRIVER'   => 'file',
        'CACHE_STORE'      => 'file',
        'QUEUE_CONNECTION' => 'sync',
        // Left blank for the wizard to fill. Matches .env.example; restated here
        // so a fresh boot cannot inherit a stale connection from an edited copy.
        'DB_CONNECTION'    => 'mysql',
        'DB_HOST'          => '127.0.0.1',
        'DB_PORT'          => '3306',
        'DB_DATABASE'      => '',
        'DB_USERNAME'      => '',
        'DB_PASSWORD'      => '',
    ];

    foreach ($bootstrapValues as $key => $value) {
        $line = $key.'='.(preg_match('/\s|#/', $value) ? '"'.$value.'"' : $value);

        $env = preg_match('/^'.preg_quote($key, '/').'=.*$/m', $env)
            ? preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $env, 1)
            : rtrim($env, "\r\n")."\n".$line."\n";
    }

    if (@file_put_contents($envPath, $env) === false) {
        http_response_code(500);
        exit('MakeAI: could not write core/.env. Please check the permissions of the "core" folder (755 or 775).');
    }

    @chmod($envPath, 0644);
}

// Clear stale config/route caches if the application is not installed yet
if (! str_contains((string) @file_get_contents($envPath), 'INSTALLED=true')) {
    @unlink($app_dir.'/bootstrap/cache/config.php');
    @unlink($app_dir.'/bootstrap/cache/routes-v7.php');
}

// Maintenance mode check
if (file_exists($maintenance = $app_dir.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoloader (pre-installed, ships in zip)
require $app_dir.'/vendor/autoload.php';

// Bootstrap Laravel
/** @var Application $app */
$app = require_once $app_dir.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
