<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the admin has turned on "Require Two-Factor Authentication", a signed-in user
 * who has not enabled 2FA is funnelled to the security page to set it up.
 *
 * The gate deliberately covers EVERY web route rather than only auth-protected ones:
 * core product surfaces (e.g. the /ai-tools pages) are public so guests can use them,
 * so a "requires auth" rule would have left a signed-in user free to keep using the
 * app. Only the routes needed to complete setup — plus infrastructure endpoints the
 * security page itself depends on — are exempt, so the user can never be trapped in a
 * redirect loop. Admin-panel requests are never gated (separate guard and 2FA system).
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && ! $user->two_factor_enabled
            && ! $this->isExempt($request)
            && (bool) settings('two_factor_required', false)
        ) {
            if ($request->expectsJson()) {
                abort(403, translate('Two-factor authentication is required.'));
            }

            return redirect()->route('user.dashboard.security')
                ->with('warning', translate('Two-factor authentication is required. Please enable it to continue.'));
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        // Admin panel: authenticated via its own guard with its own 2FA system. A user
        // and an admin can share a browser session, so skip by path/name too.
        if ($request->is('admin', 'admin/*') || $request->routeIs('admin.*')) {
            return true;
        }

        // Endpoints the security/setup pages themselves need in order to work.
        if ($request->is(
            'sanctum/csrf-cookie',
            'broadcasting/auth',
            'css/theme-variables.css',
            'storage/*',
            'build/*',
        )) {
            return true;
        }

        return $request->routeIs(
            // Completing 2FA setup.
            'user.dashboard.security',
            'user.dashboard.security.2fa.*',
            // Adding/verifying the phone an SMS second factor is sent to.
            'user.dashboard.profile',
            'user.dashboard.profile.update',
            'user.dashboard.profile.phone.*',
            'profile.theme',
            // Session/verification flows — never trap the user signed in.
            'logout',
            'login',
            'register',
            'verification.*',
            'two-factor.*',
            'password.*',
        );
    }
}
