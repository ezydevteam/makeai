<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordOtpRequest;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;
use App\Http\Requests\Auth\VerifyPasswordResetOtpRequest;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use App\Services\NotificationEventService;
use App\Services\RateLimiterService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function showForgotForm(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetOtp(ForgotPasswordOtpRequest $request)
    {
        $email = strtolower((string) $request->validated('email'));
        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = $email.'|'.$request->ip();

        $result = $rateLimiter->attempt('otp', $throttleKey, 3, 3600);

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                'email' => [translate('Too many reset requests. Please try again in :seconds seconds.', [
                    'seconds' => $result['retry_after_seconds'],
                ])],
            ]);
        }

        $rateLimiter->hit('otp', $throttleKey, 3600);

        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user) {
            $otp = $user->generateOtp();
            $this->sendOtp($user, $otp);
        }

        $request->session()->put('password_reset_email', $email);
        $request->session()->forget('password_reset_verified');

        return redirect()
            ->route('password.reset')
            ->with('success', translate('If an account with that email exists, a reset code has been sent.'));
    }

    public function showResetForm(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->session()->get('password_reset_email', ''),
            'verified' => (bool) $request->session()->get('password_reset_verified', false),
        ]);
    }

    public function verifyResetOtp(VerifyPasswordResetOtpRequest $request)
    {
        $data = $request->validated();
        $email = strtolower((string) $data['email']);

        if ($request->session()->get('password_reset_email') !== $email) {
            throw ValidationException::withMessages([
                'email' => [translate('Please request a new reset code for this email address.')],
            ]);
        }

        $rateLimiter = app(RateLimiterService::class);
        $throttleKey = $email.'|'.$request->ip();

        $result = $rateLimiter->attempt('otp', $throttleKey, 5, 900);

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                'code' => [translate('Too many reset attempts. Please try again in :seconds seconds.', [
                    'seconds' => $result['retry_after_seconds'],
                ])],
            ]);
        }

        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            $rateLimiter->hit('otp', $throttleKey, 900);

            throw ValidationException::withMessages([
                'code' => [translate('Invalid or expired reset code.')],
            ]);
        }

        if ($user->isOtpLocked()) {
            $availableIn = max(1, (int) $user->otp_locked_until->diffInSeconds(now()));

            throw ValidationException::withMessages([
                'code' => [translate('Too many failed attempts. Please try again in :seconds seconds.', [
                    'seconds' => $availableIn,
                ])],
            ]);
        }

        if (! $user->verifyOtp((string) $data['code'])) {
            $rateLimiter->hit('otp', $throttleKey, 900);

            if ($user->isOtpLocked()) {
                throw ValidationException::withMessages([
                    'code' => [translate('Too many failed attempts. Please request a new reset code.')],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => [translate('Invalid or expired reset code.')],
            ]);
        }

        $rateLimiter->clear('otp', $throttleKey);
        $user->clearOtp();
        $request->session()->put('password_reset_email', $email);
        $request->session()->put('password_reset_verified', true);

        return redirect()
            ->route('password.reset')
            ->with('success', translate('Reset code verified. Set a new password.'));
    }

    public function resetPassword(ResetPasswordOtpRequest $request)
    {
        $data = $request->validated();
        $email = strtolower((string) $data['email']);

        if (
            ! $request->session()->get('password_reset_verified')
            || $request->session()->get('password_reset_email') !== $email
        ) {
            throw ValidationException::withMessages([
                'code' => [translate('Please verify your reset code first.')],
            ]);
        }

        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [translate('Password reset could not be completed. Please start over.')],
            ]);
        }

        $user->forceFill(['password' => (string) $data['password']])->save();
        $user->clearOtp();
        $request->session()->forget('password_reset_email');
        $request->session()->forget('password_reset_verified');

        app(NotificationEventService::class)->passwordChanged($user);
        $this->sendAccountNotice($user);

        return redirect()
            ->route('login')
            ->with('success', translate('Password reset successfully. You can now sign in.'));
    }

    private function sendOtp(User $user, string $otp): void
    {
        SendTemplatedEmail::dispatch('reset_password_otp', $user->email, [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'otp_code' => $otp,
        ])->onQueue('otp');
    }

    private function sendAccountNotice(User $user): void
    {
        SendTemplatedEmail::dispatch('password_changed', $user->email, [
            'user_name' => $user->name,
            'user_email' => $user->email,
        ])->onQueue('emails');
    }
}
