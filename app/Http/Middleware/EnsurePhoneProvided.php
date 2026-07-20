<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the admin has turned on "Require Phone Number", a signed-in user without a
 * (verified) phone is funnelled to their profile to add one.
 *
 * Onboarding runs first: while onboarding is enabled and still pending, the user is
 * left alone so the welcome flow can play out on the dashboard — OnboardingController
 * then sends them to the profile once it completes. Like the 2FA gate this covers
 * every web route (core product pages are public), with only the routes needed to
 * satisfy the requirement exempted so nobody can be trapped.
 */
class EnsurePhoneProvided
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && ! phone_requirement_met($user)
            && ! $this->onboardingPending($user)
            && ! $this->isExempt($request)
        ) {
            if ($request->expectsJson()) {
                abort(403, translate('A verified phone number is required.'));
            }

            return redirect()->route('user.dashboard.profile')
                ->with('warning', translate('Please add and verify your phone number to continue.'));
        }

        return $next($request);
    }

    /**
     * Let the onboarding flow finish before the phone gate kicks in, so a new user
     * sees the welcome experience rather than being bounced straight out of it.
     */
    private function onboardingPending(User $user): bool
    {
        return (bool) settings('onboarding_enabled', true)
            && $user->onboarding_completed_at === null;
    }

    private function isExempt(Request $request): bool
    {
        // Admin panel: separate guard, and an admin may share a browser session.
        if ($request->is('admin', 'admin/*') || $request->routeIs('admin.*')) {
            return true;
        }

        // Endpoints the profile page itself depends on.
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
            // Where the phone is added and verified.
            'user.dashboard.profile',
            'user.dashboard.profile.update',
            'user.dashboard.profile.phone.*',
            'user.dashboard.avatar.update',
            'profile.theme',
            // Finishing onboarding must stay reachable (it redirects here afterwards).
            'user.dashboard.onboarding.*',
            'user.dashboard.preferences.patch',
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
