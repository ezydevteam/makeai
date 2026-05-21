<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminDisableTwoFactorRequest;
use App\Http\Requests\Admin\Auth\AdminEnableTwoFactorRequest;
use App\Http\Requests\Admin\Auth\AdminRegenerateRecoveryCodesRequest;
use App\Models\Admin;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminTwoFactorController extends Controller
{
    public function show(Request $request, TotpService $totp): Response
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $pendingSecret = null;
        $provisioningUri = null;

        if (! $admin->hasTotpEnabled()) {
            $pendingSecret = $request->session()->get('admin_pending_totp_secret');

            if (! $pendingSecret) {
                $pendingSecret = $totp->generateSecret();
                $request->session()->put('admin_pending_totp_secret', $pendingSecret);
            }

            $provisioningUri = $totp->provisioningUri(
                settings('app_name', 'Application'),
                $admin->email,
                $pendingSecret,
            );
        }

        return Inertia::render('Admin/Auth/TwoFactorSettings', [
            'twoFactor' => [
                'enabled' => $admin->hasTotpEnabled(),
                'confirmed_at' => $admin->two_factor_confirmed_at?->toDateTimeString(),
                'recovery_codes_count' => $admin->recoveryCodesCount(),
                'manual_key' => $pendingSecret,
                'provisioning_uri' => $provisioningUri,
            ],
            'recoveryCodes' => $request->session()->pull('admin_plain_recovery_codes', []),
        ]);
    }

    public function enable(AdminEnableTwoFactorRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $secret = $request->session()->get('admin_pending_totp_secret');

        if (! $secret || ! $totp->verify($secret, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => [translate('Invalid authenticator code.')],
            ]);
        }

        $recoveryCodes = $admin->enableTotp($secret);
        $request->session()->forget('admin_pending_totp_secret');
        $request->session()->put('admin_plain_recovery_codes', $recoveryCodes);

        return redirect()
            ->route('admin.security.2fa.show')
            ->with('success', translate('Two-factor authentication enabled successfully.'));
    }

    public function disable(AdminDisableTwoFactorRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $this->ensurePasswordMatches($admin, (string) $request->validated('password'));
        $this->ensureValidSecondFactor($admin, (string) $request->validated('code'), $totp);

        $admin->disableTotp();

        return back()->with('success', translate('Two-factor authentication disabled successfully.'));
    }

    public function regenerateRecoveryCodes(AdminRegenerateRecoveryCodesRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $this->ensurePasswordMatches($admin, (string) $request->validated('password'));
        $this->ensureValidSecondFactor($admin, (string) $request->validated('code'), $totp);

        $request->session()->put('admin_plain_recovery_codes', $admin->generateRecoveryCodes());

        return redirect()
            ->route('admin.security.2fa.show')
            ->with('success', translate('Recovery codes regenerated successfully.'));
    }

    private function ensurePasswordMatches(Admin $admin, string $password): void
    {
        if (! Hash::check($password, $admin->password)) {
            throw ValidationException::withMessages([
                'password' => [translate('The provided password is incorrect.')],
            ]);
        }
    }

    private function ensureValidSecondFactor(Admin $admin, string $code, TotpService $totp): void
    {
        if ($admin->verifyTotp($code, $totp)) {
            return;
        }

        if ($admin->useRecoveryCode($code)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [translate('Invalid authenticator or recovery code.')],
        ]);
    }
}
