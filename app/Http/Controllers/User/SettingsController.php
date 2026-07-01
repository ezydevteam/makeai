<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Http\Requests\Auth\RegenerateRecoveryCodesRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Models\User;
use App\Models\UserApiKey;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\CountryCatalog;

class SettingsController extends Controller
{
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('User/Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'country' => $user->country,
                'profession' => $user->profession,
            ],
            'countries' => collect(CountryCatalog::countries(app()->getLocale()))
                ->map(fn (array $country) => [
                    'value' => $country['code'],
                    'label' => $country['name'],
                ])
                ->values()
                ->all(),
        ]);
    }

    public function security(Request $request, TotpService $totp): Response
    {
        /** @var User $user */
        $user = $request->user();
        $pendingSecret = null;
        $provisioningUri = null;

        if (! $user->hasTotpEnabled()) {
            $pendingSecret = $request->session()->get('user_pending_totp_secret');

            if (! $pendingSecret) {
                $pendingSecret = $totp->generateSecret();
                $request->session()->put('user_pending_totp_secret', $pendingSecret);
            }

            $provisioningUri = $totp->provisioningUri(
                settings('app_name', translate('Application')),
                $user->email,
                $pendingSecret,
            );
        }

        return Inertia::render('User/Security', [
            'twoFactor' => [
                'enabled' => $user->hasTotpEnabled(),
                'confirmed_at' => $user->two_factor_confirmed_at?->toDateTimeString(),
                'recovery_codes_count' => $user->recoveryCodesCount(),
                'manual_key' => $pendingSecret,
                'provisioning_uri' => $provisioningUri,
            ],
            'recoveryCodes' => $request->session()->pull('user_plain_recovery_codes', []),
        ]);
    }

    // ─── Profile ───────────────────────────────

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $emailChanged = $validated['email'] !== $user->email;

        $user->update($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();

            return redirect()->route('user.dashboard.profile')
                ->with('success', translate('Profile updated. Please verify your new email address.'));
        }

        return redirect()->route('user.dashboard.profile')
            ->with('success', translate('Profile updated successfully.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [translate('The current password is incorrect.')],
            ]);
        }

        // Check password history (prevent reuse of last 3 passwords)
        $recentPasswords = $user->passwordHistory()->latest()->limit(3)->pluck('password')->toArray();
        foreach ($recentPasswords as $hashedPassword) {
            if (Hash::check($validated['password'], $hashedPassword)) {
                throw ValidationException::withMessages([
                    'password' => [translate('You cannot reuse a recent password.')],
                ]);
            }
        }

        // Save current password to history before updating
        $user->passwordHistory()->create(['password' => $user->password]);

        $user->update([
            'password' => $validated['password'],
            'password_changed_at' => now(),
        ]);

        return redirect()->route('user.dashboard.profile')
            ->with('success', translate('Password changed successfully.'));
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => $path]);

        return redirect()->route('user.dashboard.profile')
            ->with('success', translate('Avatar updated successfully.'));
    }

    // ─── API Keys ──────────────────────────────

    public function apiKeys(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('User/ApiKeys', [
            'apiKeys' => $user->apiKeys()->latest()->get()->map(fn (UserApiKey $key) => [
                'id' => $key->id,
                'provider' => $key->provider,
                'is_active' => $key->is_active,
                'created_at' => $key->created_at?->toISOString(),
            ]),
        ]);
    }

    public function storeApiKey(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'api_key' => ['required', 'string', 'max:1000'],
        ]);

        $user->apiKeys()->create([
            'provider' => $validated['provider'],
            'api_key' => encrypt($validated['api_key']),
            'created_at' => now(),
        ]);

        return back()->with('success', translate('API key added successfully.'));
    }

    public function destroyApiKey(Request $request, UserApiKey $key): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($key->user_id !== $user->id) {
            abort(403);
        }

        $key->delete();
        $request->session()->forget(['error', 'warning', 'info']);

        return back()->with('success', translate('API key removed.'));
    }

    // ─── 2FA ───────────────────────────────────

    public function enableTwoFactor(EnableTwoFactorRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get('user_pending_totp_secret');

        if (! $secret || ! $totp->verify($secret, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => [translate('Invalid authenticator code.')],
            ]);
        }

        $recoveryCodes = $user->enableTotp($secret);
        $request->session()->forget('user_pending_totp_secret');
        $request->session()->put('user_plain_recovery_codes', $recoveryCodes);

        return redirect()
            ->route('user.dashboard.security')
            ->with('success', translate('Two-factor authentication enabled successfully.'));
    }

    public function disableTwoFactor(DisableTwoFactorRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensurePasswordMatches($user, (string) $request->validated('password'));
        $this->ensureValidSecondFactor($user, (string) $request->validated('code'), $totp);

        $user->disableTotp();

        return back()->with('success', translate('Two-factor authentication disabled successfully.'));
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensurePasswordMatches($user, (string) $request->validated('password'));
        $this->ensureValidSecondFactor($user, (string) $request->validated('code'), $totp);

        $request->session()->put('user_plain_recovery_codes', $user->generateRecoveryCodes());

        return redirect()
            ->route('user.dashboard.security')
            ->with('success', translate('Recovery codes regenerated successfully.'));
    }

    // ─── Helpers ───────────────────────────────

    private function ensurePasswordMatches(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [translate('The provided password is incorrect.')],
            ]);
        }
    }

    private function ensureValidSecondFactor(User $user, string $code, TotpService $totp): void
    {
        if ($user->verifyTotp($code, $totp)) {
            return;
        }

        if ($user->useRecoveryCode($code)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [translate('Invalid authenticator or recovery code.')],
        ]);
    }

    // ─── Account Deletion ──────────────────────

    public function requestAccountDeletion(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [translate('The provided password is incorrect.')],
            ]);
        }

        $user->update([
            'scheduled_deletion_at' => now()->addDays(30),
        ]);

        return back()->with('success', translate('Account deletion scheduled. Your account will be permanently deleted in 30 days.'));
    }

    public function cancelAccountDeletion(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'scheduled_deletion_at' => null,
        ]);

        return back()->with('success', translate('Account deletion request cancelled.'));
    }
}
