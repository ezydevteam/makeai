<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'ip_address' => $request->ip(),
        ]);

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
