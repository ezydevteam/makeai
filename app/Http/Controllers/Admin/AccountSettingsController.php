<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\UpdateAccountAvatarRequest;
use App\Jobs\SendTemplatedEmail;
use App\Http\Requests\Admin\Account\UpdateAccountPasswordRequest;
use App\Http\Requests\Admin\Account\UpdateAccountProfileRequest;
use App\Models\Admin;
use App\Services\MailService;
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
        $previousEmail = $admin->email;

        $admin->update($validated);

        // Admins have no email-verification flow at all — no column on `admins`,
        // no route, no notification. What they do get is the same out-of-band
        // alert users get, to the OLD address, so a hijacked session cannot move
        // the highest-privilege account silently.
        if ($emailChanged) {
            app(MailService::class)->send('email_changed', $previousEmail, [
                'user_name' => $admin->name,
                'user_email' => $admin->email,
            ]);
        }

        return redirect()
            ->route('admin.account.settings')
            ->with('success', $emailChanged
                ? translate('Account details updated. A security alert was sent to your previous email address.')
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

        // Matches AdminLoginController's reset flow — an in-account change is
        // the one an attacker with a live session would make.
        SendTemplatedEmail::dispatch('password_changed', $admin->email, [
            'user_name' => $admin->name,
            'user_email' => $admin->email,
        ])->onQueue('emails');

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
