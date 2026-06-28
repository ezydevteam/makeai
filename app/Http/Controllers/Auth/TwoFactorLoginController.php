<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginTwoFactorRequest;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\NotificationEventService;
use App\Services\Security\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorLoginController extends Controller
{
    public function show(Request $request): Response
    {
        if (! $request->session()->has('user_2fa_id')) {
            return Inertia::location(route('login'));
        }

        $method = $request->session()->get('user_2fa_method', 'totp');

        if ($method === 'otp') {
            $userId = $request->session()->get('user_2fa_id');
            $user = User::find($userId);

            if ($user && ! $user->isOtpLocked() && ! $request->session()->has('user_otp_sent')) {
                $otpCode = $user->generateOtp();
                $request->session()->put('user_otp_sent', true);

                dispatch(new \App\Jobs\SendTemplatedEmail(
                    $user,
                    'login_2fa_otp',
                    ['code' => $otpCode, 'name' => $user->name]
                ));
            }
        }

        return Inertia::render('Auth/TwoFactor', [
            'twoFactorMethod' => $method,
            'userOtpExpiresAt' => $method === 'otp'
                ? User::find($request->session()->get('user_2fa_id'))?->otp_expires_at?->toISOString()
                : null,
        ]);
    }

    public function verify(LoginTwoFactorRequest $request, TotpService $totp)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $userId = $request->session()->get('user_2fa_id');
        $method = $request->session()->get('user_2fa_method', 'totp');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return redirect()->route('login');
        }

        $code = (string) $request->validated('code');

        if ($method === 'totp') {
            $verified = $user->verifyTotp($code, $totp) || $user->useRecoveryCode($code);
        } else {
            $verified = $user->verifyOtp($code);
            if (! $verified) {
                if ($user->isOtpLocked()) {
                    throw ValidationException::withMessages([
                        'code' => [translate('Too many attempts. Try again later.')],
                    ]);
                }
                throw ValidationException::withMessages([
                    'code' => [translate('Invalid verification code.')],
                ]);
            }
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => [translate('Invalid authenticator or recovery code.')],
            ]);
        }

        if ($method === 'otp') {
            $user->clearOtp();
        }

        $request->session()->forget(['user_2fa_id', 'user_2fa_method', 'user_otp_sent']);

        Auth::login($user, (bool) $request->session()->pull('user_2fa_remember', false));
        $this->recordLogin($user, $request);
        $request->session()->regenerate();

        return redirect()->intended(route('user.dashboard'));
    }

    private function recordLogin(User $user, Request $request): void
    {
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
    }
}
