<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class LicenseMiddleware
{
    /**
     * Routes that are always allowed even when license is invalid.
     */
    private const ALWAYS_ALLOWED = [
        'admin.login',
        'admin.login.attempt',
        'admin.password.request',
        'admin.password.email',
        'admin.password.reset',
        'admin.password.verify',
        'admin.password.update',
        'admin.2fa.show',
        'admin.2fa.verify',
        'admin.logout',
        'admin.license.settings',
        'admin.license.activate',
        'admin.license.deactivate',
        'admin.license.reverify',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow installation wizard — app isn't set up yet
        if ($request->is('install', 'install/*')) {
            return $next($request);
        }

        if (! config('license.require_verified', true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        // Always allow license management and authentication routes
        if ($routeName && in_array($routeName, self::ALWAYS_ALLOWED, true)) {
            return $next($request);
        }

        // Check if license is verified
        if (license_verified()) {
            return $next($request);
        }

        // License NOT verified — check if grace period is active
        $graceStart = settings('license_grace_started_at');

        if (filled($graceStart)) {
            $graceHours = config('license.grace_period', 72);
            $startedAt = Carbon::parse($graceStart);
            $expiresAt = $startedAt->copy()->addHours($graceHours);

            if (! now()->greaterThan($expiresAt)) {
                // Grace period still active — allow through with a warning flag
                return $next($request);
            }
        }

        // Grace period expired OR never verified — block everything
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'LICENSE_INVALID',
                'error' => 'License verification required. Please activate your license in the admin panel.',
            ], 403);
        }

        // Admin requests get redirected to license settings
        if ($request->is('admin', 'admin/*')) {
            return redirect()->route('admin.license.settings')
                ->with('error', translate('License verification is required. Please activate your license to continue.'));
        }

        // Public frontend. Read-only (GET/HEAD/OPTIONS) requests are allowed so the
        // page can render with the `licenseBlocked` Inertia prop that shows a blocking
        // banner overlay. Any state-changing request (a feature action) is blocked
        // server-side — the client banner alone is trivially bypassed by a nuller.
        if (! $request->isMethodSafe()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'LICENSE_INVALID',
                    'error' => 'License verification required. Please activate your license in the admin panel.',
                ], 403);
            }

            return redirect()->back()
                ->with('error', translate('This action is unavailable until the application license is activated.'));
        }

        return $next($request);
    }
}
