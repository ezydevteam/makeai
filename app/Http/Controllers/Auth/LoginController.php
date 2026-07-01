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
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
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

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [translate('Your account has been deactivated.')],
            ]);
        }

        if ($user->two_factor_enabled) {
            $request->session()->put('user_2fa_id', $user->id);
            $request->session()->put('user_2fa_remember', $request->boolean('remember'));
            $request->session()->put('user_2fa_method', $user->hasTotpEnabled() ? 'totp' : 'otp');
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
