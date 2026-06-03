<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginTwoFactorRequest;
use App\Models\User;
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

        return Inertia::render('Auth/TwoFactor');
    }

    public function verify(LoginTwoFactorRequest $request, TotpService $totp)
    {
        $userId = $request->session()->get('user_2fa_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return redirect()->route('login');
        }

        $code = (string) $request->validated('code');
        $verified = $user->verifyTotp($code, $totp) || $user->useRecoveryCode($code);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => [translate('Invalid authenticator or recovery code.')],
            ]);
        }

        $request->session()->forget('user_2fa_id');

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
