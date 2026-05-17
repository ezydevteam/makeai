<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Rate limiting
        $throttleKey = 'admin-login:'.$request->ip();
        $maxAttempts = (int) settings('login_throttle_max', 5);
        $decayMinutes = (int) settings('login_throttle_minutes', 15);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => [translate('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ])],
            ]);
        }

        // Attempt login
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, $decayMinutes * 60);

            throw ValidationException::withMessages([
                'email' => [translate('These credentials do not match our records.')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        // Check if account is active
        if (! $admin->is_active) {
            Auth::guard('admin')->logout();
            throw ValidationException::withMessages([
                'email' => [translate('Your admin account has been deactivated.')],
            ]);
        }

        // Check if 2FA is enabled
        if ($admin->two_factor_enabled) {
            // Store admin ID in session for 2FA verification
            $request->session()->put('admin_2fa_id', $admin->id);
            Auth::guard('admin')->logout();

            return redirect()->route('admin.2fa.show');
        }

        // Record login
        $admin->recordLogin($request->ip());

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

        return Inertia::render('Admin/Auth/TwoFactor');
    }

    /**
     * Verify 2FA code.
     */
    public function verify2fa(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $adminId = $request->session()->get('admin_2fa_id');
        if (! $adminId) {
            return redirect()->route('admin.login');
        }

        $admin = Admin::find($adminId);
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        // Verify OTP (email-based for now)
        if (! $admin->verifyOtp($request->code)) {
            throw ValidationException::withMessages([
                'code' => [translate('Invalid or expired verification code.')],
            ]);
        }

        $admin->clearOtp();
        $request->session()->forget('admin_2fa_id');

        Auth::guard('admin')->login($admin);
        $admin->recordLogin($request->ip());

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
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
}
