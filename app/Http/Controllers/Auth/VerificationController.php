<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        if (! $user->verifyOtp($request->code)) {
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

        $user->generateOtp();
        // TODO: Send OTP email

        return back()->with('success', translate('Verification code resent.'));
    }
}
