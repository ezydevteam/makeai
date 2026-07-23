<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\NotificationEventService;
use App\Services\RateLimiterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        $this->rememberIntendedUrl($request);

        return Inertia::render('Auth/Login');
    }

    /**
     * Seed url.intended from the page the guest came from so a successful login
     * returns them there instead of always the dashboard. The auth middleware
     * already sets url.intended when it bounces a guest off a protected route, so
     * we only fill it in for guests who reached login on their own (e.g. the "Log
     * In" link in a blog post's comment box).
     *
     * We read the Referer header rather than session()->previousUrl(): most links
     * into login are Inertia <Link> navigations (XHR), and Laravel does NOT record
     * the previous URL for AJAX requests — so previousUrl() would be stale (usually
     * the last full page load, e.g. the home page) in the SPA. An explicit
     * ?redirect= query wins over the referer when present.
     */
    private function rememberIntendedUrl(Request $request): void
    {
        if ($request->session()->has('url.intended')) {
            return;
        }

        $target = $request->query('redirect') ?: $request->headers->get('referer');

        if (is_string($target) && $this->isSafeRedirectTarget($request, $target)) {
            $request->session()->put('url.intended', $target);
        }
    }

    /**
     * Only ever redirect within this application (guards against open redirects)
     * and never back to an auth screen or the bare home page, which would loop or
     * defeat the dashboard default.
     */
    private function isSafeRedirectTarget(Request $request, ?string $target): bool
    {
        if (! $target || ! str_starts_with($target, $request->getSchemeAndHttpHost())) {
            return false;
        }

        $path = trim((string) parse_url($target, PHP_URL_PATH), '/');

        if ($path === '') {
            return false;
        }

        $authPrefixes = ['login', 'register', 'two-factor', 'forgot-password', 'reset-password', 'logout', 'auth'];

        foreach ($authPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }

    public function login(Request $request)
    {
        $captcha = CaptchaService::fromSettings();

        $rules = [
            'email' => 'required|email',
            'password' => 'required|string',
        ];

        if ($captcha->isEnabled()) {
            $rules['captcha_token'] = 'required|string';
        }

        $request->validate($rules);
        $captcha->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $rateLimiter = app(RateLimiterService::class);

        // Persistent IP ban: a sustained brute-force run (default 20 failures/hour)
        // earns a bounded auth-scoped ban, so an attacker who paces below the short
        // 5-per-15-min sliding window is still stopped. Scoped to 'auth' (not the
        // whole site) so a shared/NAT address isn't fully locked out.
        if ($rateLimiter->isIpBanned($request->ip(), 'auth')) {
            throw ValidationException::withMessages([
                'email' => [translate('Too many failed attempts from your network. Try again later.')],
            ]);
        }

        $result = $rateLimiter->attempt('auth', $request->ip());

        if (! $result['allowed']) {
            $seconds = $result['retry_after_seconds'];
            throw ValidationException::withMessages([
                'email' => [
                    $seconds < 60
                        ? translate('Too many attempts. Try again in :seconds seconds.', ['seconds' => $seconds])
                        : translate('Too many attempts. Try again in :minutes minutes.', ['minutes' => ceil($seconds / 60)])
                ],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => [translate('Invalid credentials or account locked.')],
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $rateLimiter->hit('auth', $request->ip(), 900);

            // Track failures over a rolling hour; once past the threshold, persist a
            // bounded auth-scoped ban (checked at the top of this method next time).
            $banThreshold = (int) settings('rl_login_ban_threshold', 20);
            if ($banThreshold > 0) {
                $hourly = $rateLimiter->attempt('login_fail', $request->ip(), $banThreshold, 3600);
                if (! $hourly['allowed']) {
                    $rateLimiter->banIp(
                        $request->ip(),
                        'Excessive failed login attempts',
                        'auth',
                        null,
                        (int) settings('rl_login_ban_duration', 3600),
                    );
                }
            }

            if ($user) {
                $user->incrementLoginAttempts();

                if ($user->fresh()->isLocked()) {
                    throw ValidationException::withMessages([
                        'email' => [translate('Invalid credentials or account locked.')],
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'email' => [translate('These credentials do not match our records.')],
            ]);
        }

        $rateLimiter->clear('auth', $request->ip());
        $rateLimiter->clear('login_fail', $request->ip());

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [translate('Your account has been deactivated.')],
            ]);
        }

        if ($user->is_banned) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [translate('Your account has been suspended. Please contact support.')],
            ]);
        }

        if ($user->two_factor_enabled) {
            $request->session()->put('user_2fa_id', $user->id);
            $request->session()->put('user_2fa_remember', $request->boolean('remember'));
            $request->session()->put('user_2fa_method', $user->twoFactorChannel());
            Auth::logout();

            return redirect()->route('two-factor.show');
        }

        $isNewLoginIp = ! $user->loginHistory()
            ->where('ip', $request->ip())
            ->where('success', true)
            ->exists();

        $user->recordLogin($request->ip(), (string) $request->userAgent());
        if ($isNewLoginIp) {
            app(NotificationEventService::class)->newLogin(
                $user,
                $request->ip(),
                $request->header('CF-IPCity') ?: $request->header('X-Vercel-IP-City'),
                $request->header('CF-IPCountry') ?: $request->header('X-Vercel-IP-Country')
            );
        }
        $request->session()->regenerate();

        return redirect()->intended(route('user.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
