<?php

use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\AdminPermission;
use App\Http\Middleware\CheckCredits;
use App\Http\Middleware\DemoMode;
use App\Http\Middleware\DetectPricingCountry;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\NotBanned;
use App\Http\Middleware\PreventRequestsDuringMaintenance as MakeAiPreventRequestsDuringMaintenance;
use App\Http\Middleware\ThrottleAiRequests;
use App\Http\Middleware\ToolSlugRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(
            PreventRequestsDuringMaintenance::class,
            MakeAiPreventRequestsDuringMaintenance::class,
        );

        $middleware->web(append: [
            InstallationMiddleware::class,
            LicenseMiddleware::class,
            DemoMode::class,
            LocaleMiddleware::class,
            DetectPricingCountry::class,
            ToolSlugRedirect::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin.auth' => AdminAuth::class,
            'admin.permission' => AdminPermission::class,
            'check.credits' => CheckCredits::class,
            'not.banned' => NotBanned::class,
            'throttle' => ThrottleAiRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\App\Exceptions\AI\CreditLimitException $e) {
            return response()->json([
                'error' => 'Credit limit exceeded',
                'message' => $e->getMessage(),
                'limit_type' => $e->limitType,
                'remaining' => $e->remaining,
            ], 402);
        });

        $exceptions->renderable(function (\App\Exceptions\AI\InsufficientCreditsException $e) {
            return response()->json([
                'error' => 'Insufficient credits',
                'message' => $e->getMessage(),
                'balance' => $e->balance,
                'estimated_cost' => $e->estimatedCost,
            ], 402);
        });

        $exceptions->renderable(function (\App\Exceptions\AI\IntegrationNotConfiguredException $e) {
            return response()->json([
                'error' => 'Integration not available',
                'message' => $e->getMessage(),
                'integration' => $e->integration,
            ], 503);
        });
    })->create();
