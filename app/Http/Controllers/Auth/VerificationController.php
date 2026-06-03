<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function notice()
    {
        if (Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('user.dashboard');
        }

        return Inertia::render('Auth/VerifyEmail');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = Auth::user();

        if ($user->isOtpLocked()) {
            $availableIn = max(1, (int) $user->otp_locked_until->diffInSeconds(now()));

            throw ValidationException::withMessages([
                'code' => [translate('Too many failed attempts. Please try again in :seconds seconds.', [
                    'seconds' => $availableIn,
                ])],
            ]);
        }

        if (! $user->verifyOtp($request->code)) {
            if ($user->isOtpLocked()) {
                throw ValidationException::withMessages([
                    'code' => [translate('Too many failed attempts. Please request a new verification code.')],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => [translate('Invalid or expired verification code.')],
            ]);
        }

        $user->clearOtp();
        $user->markEmailAsVerified();

        return redirect()->route('user.dashboard')
            ->with('success', translate('Email verified successfully!'));
    }

    public function resend()
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('user.dashboard');
        }

        $otp = $user->generateOtp();
        $this->sendVerificationOtp($user, $otp);

        return back()->with('success', translate('Verification code resent.'));
    }

    private function sendVerificationOtp(User $user, string $otp): void
    {
        SendTemplatedEmail::dispatch('email_verify_otp', $user->email, [
            'user_name' => $user->name,
            'site_name' => settings('app_name', 'Application'),
            'otp_code' => $otp,
        ])->onQueue('otp');
    }
}
