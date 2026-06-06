<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $rateLimiter = app(RateLimiterService::class);
        $result = $rateLimiter->attempt('auth', $request->ip());

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                'email' => [translate('Too many attempts. Try again in :seconds seconds.', [
                    'seconds' => $result['retry_after_seconds'],
                ])],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if ($user && $user->isLocked()) {
            $minutes = (int) ceil(now()->diffInMinutes($user->locked_until));
            throw ValidationException::withMessages([
                'email' => [translate('Account locked for :minutes minutes due to too many attempts.', [
                    'minutes' => $minutes,
                ])],
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $rateLimiter->hit('auth', $request->ip(), 900);

            if ($user) {
                $user->incrementLoginAttempts();

                if ($user->fresh()->isLocked()) {
                    throw ValidationException::withMessages([
                        'email' => [translate('Account locked for 15 minutes due to too many failed attempts.')],
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
