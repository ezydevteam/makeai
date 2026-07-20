<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\UpdateAccountAvatarRequest;
use App\Http\Requests\Admin\Account\UpdateAccountPasswordRequest;
use App\Http\Requests\Admin\Account\UpdateAccountProfileRequest;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    public function edit(): Response
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        return Inertia::render('Admin/Account/Settings', [
            'account' => [
                'name' => $admin->name,
                'email' => $admin->email,
                'avatar' => $admin->avatar,
            ],
            'twoFactor' => [
                'enabled' => $admin->hasTotpEnabled(),
                'confirmed_at' => $admin->two_factor_confirmed_at?->toDateTimeString(),
                'recovery_codes_count' => $admin->recoveryCodesCount(),
            ],
        ]);
    }

    public function updateProfile(UpdateAccountProfileRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $validated = $request->validated();
        $emailChanged = $validated['email'] !== $admin->email;

        $admin->update($validated);

        if ($emailChanged) {
            $admin->forceFill([
                'email_verified_at' => null,
            ])->save();
        }

        return redirect()
            ->route('admin.account.settings')
            ->with('success', $emailChanged
                ? translate('Account details updated. Please verify your new email address if verification is enabled.')
                : translate('Account details updated successfully.'));
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => [translate('The current password is incorrect.')],
            ]);
        }

        $recentPasswords = $admin->passwordHistory()->latest()->limit(3)->pluck('password')->toArray();
        foreach ($recentPasswords as $hashedPassword) {
            if (Hash::check($validated['password'], $hashedPassword)) {
                throw ValidationException::withMessages([
                    'password' => [translate('You cannot reuse a recent password.')],
                ]);
            }
        }

        $admin->passwordHistory()->create([
            'password' => $admin->password,
        ]);

        $admin->update([
            'password' => $validated['password'],
            'password_changed_at' => now(),
        ]);

        return redirect()
            ->route('admin.account.settings')
            ->with('success', translate('Password changed successfully.'));
    }

    public function updateAvatar(UpdateAccountAvatarRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        // Store the new avatar first; the old one (unless it's an external social-login
        // URL, which media_path leaves alone) is removed only after that succeeds.
        $path = store_public_upload($request->file('avatar'), 'admin-avatars', $admin->avatar);

        $admin->update([
            'avatar' => $path,
        ]);

        return redirect()
            ->route('admin.account.settings')
            ->with('success', translate('Avatar updated successfully.'));
    }
}
