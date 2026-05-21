<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
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
        ]);

        $affiliate->attachReferralToUser($request, $user);

        app(NotificationEventService::class)->newUserRegistered($user);

        Auth::login($user);

        // Generate OTP for email verification
        $otp = $user->generateOtp();
        // TODO: Send OTP email (Mail::to($user)->send(new VerifyEmailOtp($otp)))

        return redirect()->route('verification.notice')
            ->with('success', translate('Account created! Please verify your email.'));
    }
}
