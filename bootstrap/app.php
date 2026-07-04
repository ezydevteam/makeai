<?php

use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\AdminPermission;
use App\Http\Middleware\CheckAffiliateEnabled;
use App\Http\Middleware\CheckCredits;
use App\Http\Middleware\CheckBlogEnabled;
use App\Http\Middleware\CheckContactEnabled;
use App\Http\Middleware\CheckTicketsEnabled;
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

$appConfigurator = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/install.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(
            PreventRequestsDuringMaintenance::class,
            MakeAiPreventRequestsDuringMaintenance::class,
        );

        // Runs before session/CSRF so an "entire site" IP ban short-circuits
        // early and cheaply (it exempts /admin/* internally).
        $middleware->web(prepend: [
            \App\Http\Middleware\BlockBannedIps::class,
        ]);

        $middleware->web(append: [
            InstallationMiddleware::class,
            LicenseMiddleware::class,
            DemoMode::class,
            LocaleMiddleware::class,
            DetectPricingCountry::class,
            ToolSlugRedirect::class,
            HandleInertiaRequests::class,
        ]);

        // Gate the API group too. Feature/generation endpoints under routes/api.php
        // (e.g. the auth:sanctum "ai" group) and addon ['api'] routes otherwise
        // bypass license enforcement entirely — LicenseMiddleware returns a 403
        // LICENSE_INVALID for these instead of letting generation run unlicensed.
        $middleware->api(append: [
            LicenseMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'social/accounts/*/callback',
            'embed/*',
        ]);

        $middleware->alias([
            'admin.auth' => AdminAuth::class,
            'admin.permission' => AdminPermission::class,
            'admin.super' => \App\Http\Middleware\CheckSuperAdmin::class,
            'admin.audit' => \App\Http\Middleware\AdminAuditLog::class,
            'affiliate' => CheckAffiliateEnabled::class,
            'check.credits' => CheckCredits::class,
            'tickets' => CheckTicketsEnabled::class,
            'contact' => CheckContactEnabled::class,
            'blog' => CheckBlogEnabled::class,
            'notifications' => \App\Http\Middleware\CheckNotificationEnabled::class,
            'register' => \App\Http\Middleware\CheckRegistrationEnabled::class,
            'email.verify' => \App\Http\Middleware\CheckEmailVerificationEnabled::class,
            'not.banned' => NotBanned::class,
            'premium' => \App\Http\Middleware\CheckPremium::class,
            'extended' => \App\Http\Middleware\CheckExtendedLicense::class,
            'throttle' => ThrottleAiRequests::class,
            'addon.enabled' => \App\Http\Middleware\AddonEnabled::class,
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
    });

$app = $appConfigurator->create();

// Bind the public path to the root directory dynamically when running in the distribution package
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'index.php') {
    $app->usePublicPath(dirname($_SERVER['SCRIPT_FILENAME']));
} elseif (file_exists(base_path('../index.php')) && basename(base_path()) === 'makeai') {
    $app->usePublicPath(dirname(base_path()));
}

return $app;
app;
