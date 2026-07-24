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
use Installer\Middleware\InstallationMiddleware;
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
                ->group(__DIR__.'/../resources/installer/routes.php');
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
            // Block suspended accounts before any signed-in user gate runs. Applied to
            // the whole web group (not per-route) so every authenticated route —
            // including addon routes — is covered; it no-ops for guests and admins
            // (it only acts on a banned web-guard user).
            NotBanned::class,
            // Signed-in user gates. Phone first: SMS two-factor needs a verified phone,
            // so collecting it before the 2FA prompt keeps the setup order sensible.
            \App\Http\Middleware\EnsurePhoneProvided::class,
            \App\Http\Middleware\EnsureTwoFactorEnabled::class,
            DemoMode::class,
            // Applies the demo bar's preset/addon selection (from the demo_selection
            // cookie) as in-memory setting overrides. Must precede HandleInertiaRequests,
            // which eagerly resolves theme/homepage settings. No-op unless demo.enabled.
            \App\Http\Middleware\DemoSelection::class,
            LocaleMiddleware::class,
            DetectPricingCountry::class,
            ToolSlugRedirect::class,
            HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Gate the API group too. Feature/generation endpoints under routes/api.php
        // (e.g. the auth:sanctum "ai" group) and addon ['api'] routes otherwise
        // bypass license enforcement entirely — LicenseMiddleware returns a 403
        // LICENSE_INVALID for these instead of letting generation run unlicensed.
        //
        // BlockBannedIps is prepended here too: pure api-group routes (/v1/ai/*,
        // /v1/tools catalog) otherwise never see the site-wide IP ban that the web
        // group enforces, letting a banned abuser keep hitting AI generation.
        $middleware->api(prepend: [
            \App\Http\Middleware\BlockBannedIps::class,
        ]);

        $middleware->api(append: [
            LicenseMiddleware::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'social/accounts/*/callback',
            'embed/*',
        ]);

        // The cookie-consent banner writes this cookie from JavaScript, so it must
        // stay plaintext (not Laravel-encrypted) to be readable both client-side
        // and server-side — e.g. gating the analytics tag before consent.
        $middleware->encryptCookies(except: [
            'cookie_consent',
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
            'byok' => \App\Http\Middleware\CheckByokEnabled::class,
            'account.deletion' => \App\Http\Middleware\CheckAccountDeletionEnabled::class,
            'playground' => \App\Http\Middleware\CheckPlaygroundEnabled::class,
            'chains' => \App\Http\Middleware\CheckChainsEnabled::class,
            'tool.embeds' => \App\Http\Middleware\CheckToolEmbedsEnabled::class,
            'register' => \App\Http\Middleware\CheckRegistrationEnabled::class,
            'email.verify' => \App\Http\Middleware\CheckEmailVerificationEnabled::class,
            'not.banned' => NotBanned::class,
            'premium' => \App\Http\Middleware\CheckPremium::class,
            'extended' => \App\Http\Middleware\CheckExtendedLicense::class,
            'throttle' => ThrottleAiRequests::class,
            'addon.enabled' => \App\Http\Middleware\AddonEnabled::class,
            'cache.public' => \App\Http\Middleware\CachePublicPage::class,
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

        // A failed media write (see store_public_upload) surfaces as a flashed error on
        // web/Inertia requests and a 500 JSON payload on API requests — never a silent
        // "saved" with a broken path.
        $exceptions->renderable(function (\App\Exceptions\StorageWriteException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Storage write failed',
                    'message' => $e->getMessage(),
                ], 500);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    });

$app = $appConfigurator->create();

// Bind the public path to the root directory dynamically when running in the distribution package
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'index.php') {
    $app->usePublicPath(dirname($_SERVER['SCRIPT_FILENAME']));
} elseif (file_exists(base_path('../index.php')) && ! is_dir(base_path('public'))) {
    // CLI entrypoints (artisan, cron, queue workers) have no SCRIPT_FILENAME to
    // read the webroot from, so the layout itself is the signal: the packaged
    // build has index.php one level up and no public/ of its own, whereas a
    // standard Laravel checkout always has public/. Detecting the shape rather
    // than the folder name keeps this working if the app directory is renamed.
    $app->usePublicPath(dirname(base_path()));
}

return $app;
