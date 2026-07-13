<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CaptchaService;
use App\Services\EmailValidationService;
use App\Services\NotificationEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function showRegistrationForm(Request $request, AffiliateService $affiliate)
    {
        if ($request->filled('ref')) {
            $affiliate->captureVisit($request, (string) $request->query('ref'));
        }

        return Inertia::render('Auth/Register');
    }

    public function register(RegisterRequest $request, AffiliateService $affiliate)
    {
        CaptchaService::fromSettings()->ensureValidToken($request->string('captcha_token')->toString(), $request->ip());

        // Block disposable/undeliverable signups when the Email Validation extension
        // is on, per the admin's "what to block" setting. shouldReject() fails open,
        // so an outage or disabled extension never blocks a legitimate registration.
        if (EmailValidationService::fromSettings()->shouldReject($request->email)) {
            throw ValidationException::withMessages([
                'email' => [translate('This email address could not be verified. Please use a different email.')],
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'ip_address' => $request->ip(),
        ]);

        // Apply the admin's "new user gets" choice (Pricing Settings).
        $user->applyRegistrationDefault();

        $affiliate->attachReferralToUser($request, $user);

        app(NotificationEventService::class)->newUserRegistered($user);

        Auth::login($user);

        if ((bool) settings('email_verification_enabled', true)) {
            $otp = $user->generateOtp();
            $this->sendVerificationOtp($user, $otp);

            return redirect()->route('verification.notice')
                ->with('success', translate('Account created! Please verify your email.'));
        }

        $user->markEmailAsVerified();

        return redirect()->route('user.dashboard')
            ->with('success', translate('Account created! Welcome.'));
    }

    private function sendVerificationOtp(User $user, string $otp): void
    {
        SendTemplatedEmail::dispatch('email_verify_otp', $user->email, [
            'user_name' => $user->name,
            'site_name' => settings('app_name', translate('Application')),
            'otp_code' => $otp,
        ])->onQueue('otp');
    }
}
