<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminForgotPasswordRequest;
use App\Http\Requests\Admin\Auth\AdminLoginRequest;
use App\Http\Requests\Admin\Auth\AdminResetPasswordRequest;
use App\Http\Requests\Admin\Auth\AdminTwoFactorRequest;
use App\Http\Requests\Admin\Auth\AdminVerifyPasswordResetOtpRequest;
use App\Jobs\SendTemplatedEmail;
use App\Models\Admin;
use App\Services\CaptchaService;
use App\Services\RateLimiterService;
use App\Services\Security\TotpService;
use App\Support\DemoCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminLoginController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Admin/Auth/Login');
    }

    /**
     * Handle an admin login attempt.
     */
    public function login(AdminLoginRequest $request)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = strtolower((string) $request->validated('email')).'|'.$request->ip();

        $result = $rateLimiter->attempt('auth', $throttleKey);

        if (! $result['allowed']) {
            $seconds = $result['retry_after_seconds'];
            throw ValidationException::withMessages([
                'email' => [
                    $seconds < 60
                        ? translate('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])
                        : translate('Too many login attempts. Please try again in :minutes minutes.', ['minutes' => ceil($seconds / 60)])
                ],
            ]);
        }

        $credentials = $request->only('email', 'password');

        // The demo super admin and the three demo staff admins are published credentials.
        // Once demo mode is off they are refused before authentication, so no admin
        // session is ever created for one.
        if (DemoCredentials::blocked($credentials['email'] ?? null, $credentials['password'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => [DemoCredentials::message()],
            ]);
        }

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $rateLimiter->hit('auth', $throttleKey, 900);

            throw ValidationException::withMessages([
                'email' => [translate('These credentials do not match our records.')],
            ]);
        }

        $rateLimiter->clear('auth', $throttleKey);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();
            throw ValidationException::withMessages([
                'email' => [translate('Your admin account has been deactivated.')],
            ]);
        }

        if ($admin->two_factor_enabled) {
            $method = $admin->hasTotpEnabled() ? 'totp' : 'email';

            if ($method === 'email') {
                $otp = $admin->generateOtp();
                $this->sendOtp($admin, $otp, 'login_otp');
            }

            $request->session()->put('admin_2fa_id', $admin->id);
            $request->session()->put('admin_2fa_method', $method);
            Auth::guard('admin')->logout();

            return redirect()->route('admin.2fa.show');
        }

        $admin->recordLogin($request->ip(), (string) $request->userAgent());

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Show 2FA verification form.
     */
    public function show2fa(Request $request): Response
    {
        if (! $request->session()->has('admin_2fa_id')) {
            return Inertia::location(route('admin.login'));
        }

        return Inertia::render('Admin/Auth/TwoFactor', [
            'method' => $request->session()->get('admin_2fa_method', 'email'),
        ]);
    }

    /**
     * Verify 2FA code.
     */
    public function verify2fa(AdminTwoFactorRequest $request, TotpService $totp)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $adminId = $request->session()->get('admin_2fa_id');
        if (! $adminId) {
            return redirect()->route('admin.login');
        }

        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = $adminId.'|'.$request->ip();

        $result = $rateLimiter->attempt('otp', $throttleKey, 5, 900);

        if (! $result['allowed']) {
            $seconds = $result['retry_after_seconds'];
            throw ValidationException::withMessages([
                'code' => [
                    $seconds < 60
                        ? translate('Too many verification attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])
                        : translate('Too many verification attempts. Please try again in :minutes minutes.', ['minutes' => ceil($seconds / 60)])
                ],
            ]);
        }

        $admin = Admin::find($adminId);
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $code = (string) $request->validated('code');
        $verified = $admin->hasTotpEnabled()
            ? $admin->verifyTotp($code, $totp) || $admin->useRecoveryCode($code)
            : $admin->verifyOtp($code);

        if (! $verified) {
            $rateLimiter->hit('otp', $throttleKey, 900);

            throw ValidationException::withMessages([
                'code' => [translate('Invalid or expired verification code.')],
            ]);
        }

        $rateLimiter->clear('otp', $throttleKey);
        $admin->clearOtp();
        $request->session()->forget('admin_2fa_id');
        $request->session()->forget('admin_2fa_method');

        Auth::guard('admin')->login($admin);
        $admin->recordLogin($request->ip(), (string) $request->userAgent());

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function showForgotPasswordForm(): Response
    {
        return Inertia::render('Admin/Auth/ForgotPassword');
    }

    public function sendPasswordResetOtp(AdminForgotPasswordRequest $request)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $email = strtolower((string) $request->validated('email'));
        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = $email.'|'.$request->ip();

        $result = $rateLimiter->attempt('otp', $throttleKey, 3, 3600);

        if (! $result['allowed']) {
            $seconds = $result['retry_after_seconds'];
            throw ValidationException::withMessages([
                'email' => [
                    $seconds < 60
                        ? translate('Too many reset requests. Please try again in :seconds seconds.', ['seconds' => $seconds])
                        : translate('Too many reset requests. Please try again in :minutes minutes.', ['minutes' => ceil($seconds / 60)])
                ],
            ]);
        }

        $rateLimiter->hit('otp', $throttleKey, 3600);

        /** @var Admin $admin */
        $admin = Admin::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->firstOrFail();

        $otp = $admin->generateOtp();
        $this->sendOtp($admin, $otp, 'reset_password_otp');

        $request->session()->put('admin_password_reset_email', $email);
        $request->session()->forget('admin_password_reset_verified');

        return redirect()
            ->route('admin.password.reset')
            ->with('success', translate('A reset code has been sent to this admin email.'));
    }

    public function showResetPasswordForm(Request $request): Response
    {
        return Inertia::render('Admin/Auth/ResetPassword', [
            'email' => $request->session()->get('admin_password_reset_email', ''),
            'verified' => (bool) $request->session()->get('admin_password_reset_verified', false),
        ]);
    }

    public function verifyPasswordResetOtp(AdminVerifyPasswordResetOtpRequest $request)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $data = $request->validated();
        $email = strtolower((string) $data['email']);
        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = $email.'|'.$request->ip();

        $result = $rateLimiter->attempt('otp', $throttleKey, 5, 900);

        if (! $result['allowed']) {
            $seconds = $result['retry_after_seconds'];
            throw ValidationException::withMessages([
                'code' => [
                    $seconds < 60
                        ? translate('Too many reset attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])
                        : translate('Too many reset attempts. Please try again in :minutes minutes.', ['minutes' => ceil($seconds / 60)])
                ],
            ]);
        }

        $admin = Admin::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $admin || ! $admin->verifyOtp((string) $data['code'])) {
            $rateLimiter->hit('otp', $throttleKey, 900);

            throw ValidationException::withMessages([
                'code' => [translate('Invalid or expired reset code.')],
            ]);
        }

        $rateLimiter->clear('otp', $throttleKey);
        $request->session()->put('admin_password_reset_email', $email);
        $request->session()->put('admin_password_reset_verified', true);

        return redirect()
            ->route('admin.password.reset')
            ->with('success', translate('Reset code verified. Set a new password.'));
    }

    public function resetPassword(AdminResetPasswordRequest $request)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        $data = $request->validated();
        $email = strtolower((string) $data['email']);

        if (
            ! $request->session()->get('admin_password_reset_verified')
            || $request->session()->get('admin_password_reset_email') !== $email
        ) {
            throw ValidationException::withMessages([
                'code' => [translate('Please verify your reset code first.')],
            ]);
        }

        $admin = Admin::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $admin) {
            throw ValidationException::withMessages([
                'email' => [translate('No active admin account was found for this email address.')],
            ]);
        }

        $admin->forceFill([
            'password' => (string) $data['password'],
        ])->save();
        $admin->clearOtp();
        $request->session()->forget('admin_password_reset_email');
        $request->session()->forget('admin_password_reset_verified');

        $this->sendAccountNotice($admin, 'password_changed');

        return redirect()
            ->route('admin.login')
            ->with('success', translate('Password reset successfully. You can now sign in.'));
    }

    /**
     * Log the admin out.
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function sendOtp(Admin $admin, string $otp, string $template): void
    {
        SendTemplatedEmail::dispatch($template, $admin->email, [
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'otp_code' => $otp,
        ])->onQueue('otp');
    }

    private function sendAccountNotice(Admin $admin, string $template): void
    {
        SendTemplatedEmail::dispatch($template, $admin->email, [
            'user_name' => $admin->name,
            'user_email' => $admin->email,
        ])->onQueue('emails');
    }
}
