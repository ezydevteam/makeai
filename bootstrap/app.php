<?php

use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\AdminPermission;
use App\Http\Middleware\CheckCredits;
use App\Http\Middleware\DemoMode;
use App\Http\Middleware\DetectPricingCountry;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            DemoMode::class,
            LocaleMiddleware::class,
            DetectPricingCountry::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin.auth' => AdminAuth::class,
            'admin.permission' => AdminPermission::class,
            'check.credits' => CheckCredits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
