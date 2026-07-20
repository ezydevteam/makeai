<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginTwoFactorRequest;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\NotificationEventService;
use App\Services\RateLimiterService;
use App\Services\Security\TotpService;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
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
        $user = User::find($request->session()->get('user_2fa_id'));

        // Resolve OTP-delivery channels. An 'sms' challenge degrades to email when the
        // gateway/phone can't deliver, so a user is never locked out at the door.
        $displayMethod = $method;
        if ($user && in_array($method, ['sms', 'otp'], true)) {
            $viaSms = $method === 'sms' && user_can_receive_sms($user);
            $displayMethod = $viaSms ? 'sms' : 'email';

            if (! $user->isOtpLocked() && ! $request->session()->has('user_otp_sent')) {
                $request->session()->put('user_otp_sent', true);
                $this->deliverLoginOtp($user, $user->generateOtp(), $viaSms);
            }
        }

        return Inertia::render('Auth/TwoFactor', [
            'twoFactorMethod' => $displayMethod,
            'userOtpExpiresAt' => in_array($displayMethod, ['sms', 'email'], true)
                ? $user?->otp_expires_at?->toISOString()
                : null,
        ]);
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->session()->has('user_2fa_id')) {
            return redirect()->route('login');
        }

        $method = $request->session()->get('user_2fa_method', 'totp');
        if (! in_array($method, ['sms', 'otp'], true)) {
            return back();
        }

        $user = User::find($request->session()->get('user_2fa_id'));
        if (! $user) {
            return redirect()->route('login');
        }

        $rateLimiter = app(RateLimiterService::class);
        $key = 'login-2fa-resend:'.$user->id.'|'.$request->ip();
        if (! $rateLimiter->attempt('otp', $key, 3, 900)['allowed'] || $user->isOtpLocked()) {
            return back()->with('error', translate('Too many requests. Please try again later.'));
        }

        $viaSms = $method === 'sms' && user_can_receive_sms($user);
        $this->deliverLoginOtp($user, $user->generateOtp(), $viaSms);

        return back()->with('success', translate('A new verification code has been sent.'));
    }

    private function deliverLoginOtp(User $user, string $otp, bool $viaSms): void
    {
        if ($viaSms) {
            $e164 = PhoneNumber::e164($user->phone, $user->phone_country);
            if ($e164 !== null) {
                SmsService::fromSettings()->send($e164, translate('Your verification code is:').' '.$otp);

                return;
            }
        }

        SendTemplatedEmail::dispatch('login_otp', $user->email, [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'otp_code' => $otp,
            'site_name' => settings('app_name', translate('Application')),
        ])->onQueue('otp');
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

        // Rate limit in-controller (not via throttle middleware) so a limit hit is an
        // Inertia validation error, not the JSON the AI-throttle middleware returns.
        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = 'login-2fa-verify:'.$user->id.'|'.$request->ip();
        if (! $rateLimiter->attempt('otp', $throttleKey, 5, 900)['allowed']) {
            throw ValidationException::withMessages([
                'code' => [translate('Too many attempts. Please try again later.')],
            ]);
        }

        $code = (string) $request->validated('code');
        $isOtpChannel = in_array($method, ['sms', 'otp'], true);

        // Primary factor for the channel, then recovery codes as a universal backup.
        $verified = $method === 'totp'
            ? $user->verifyTotp($code, $totp)
            : $user->verifyOtp($code);

        if (! $verified) {
            $verified = $user->useRecoveryCode($code);
        }

        if (! $verified) {
            if ($isOtpChannel && $user->isOtpLocked()) {
                throw ValidationException::withMessages([
                    'code' => [translate('Too many attempts. Try again later.')],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => [translate('Invalid verification or recovery code.')],
            ]);
        }

        if ($isOtpChannel) {
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
