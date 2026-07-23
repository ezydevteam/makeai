<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Http\Requests\Auth\RegenerateRecoveryCodesRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Jobs\SendTemplatedEmail;
use App\Models\User;
use App\Models\UserByok;
use App\Services\MailService;
use App\Services\RateLimiterService;
use App\Services\Security\TotpService;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                'phone' => $user->phone,
                'phone_country' => $user->phone_country,
                'phone_verified' => $user->hasVerifiedPhone(),
                'sms_marketing_opt_in' => (bool) $user->sms_marketing_opt_in,
                'timezone' => $user->timezone,
                'brand_voice' => $user->brand_voice,
            ],
            // The brand-voice field is only offered while the feature is globally
            // enabled; tools then apply it when they support it.
            'brandVoiceEnabled' => (bool) settings('global_tools_brand_voice_enabled', true),
            // Whether an admin-configured SMS gateway is active — the phone
            // verification UI only renders when this is true.
            'phoneVerificationEnabled' => SmsService::fromSettings()->isEnabled(),
            // Admin requires a phone number, and this user hasn't satisfied it yet —
            // drives the prompt at the top of the page (see EnsurePhoneProvided).
            'phoneRequired' => (bool) settings('phone_required', false),
            'phoneRequirementMet' => phone_requirement_met($user),
            'countries' => collect(CountryCatalog::countries(app()->getLocale()))
                ->map(fn (array $country) => [
                    'value' => $country['code'],
                    'label' => $country['name'],
                ])
                ->values()
                ->all(),
            'timezones' => collect(timezone_identifiers_list())
                ->map(fn (string $tz) => ['value' => $tz, 'label' => $tz])
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

        // Only prepare a TOTP secret while 2FA is completely off — an SMS-enabled
        // account has no authenticator secret and must not get a stray pending one.
        if (! $user->two_factor_enabled) {
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
                'enabled' => (bool) $user->two_factor_enabled,
                'channel' => $user->twoFactorChannel(),
                'confirmed_at' => $user->two_factor_confirmed_at?->toDateTimeString(),
                'recovery_codes_count' => $user->recoveryCodesCount(),
                'manual_key' => $pendingSecret,
                'provisioning_uri' => $provisioningUri,
                'sms_available' => sms_two_factor_available($user),
                'has_verified_phone' => $user->hasVerifiedPhone(),
                'phone_masked' => $this->maskPhone($user),
            ],
            'recoveryCodes' => $request->session()->pull('user_plain_recovery_codes', []),
        ]);
    }

    private function maskPhone(User $user): ?string
    {
        if (! filled($user->phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $user->phone);
        $visible = substr($digits, -2);

        return str_repeat('•', max(0, strlen($digits) - 2)).$visible;
    }

    // ─── Profile ───────────────────────────────

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $emailChanged = $validated['email'] !== $user->email;
        $previousEmail = $user->email;

        // A changed phone (number or country) invalidates any prior verification.
        $phoneChanged = ($validated['phone'] ?? null) !== $user->phone
            || ($validated['phone_country'] ?? null) !== $user->phone_country;

        $user->update($validated);

        if ($phoneChanged && $user->phone_verified_at !== null) {
            $user->forceFill(['phone_verified_at' => null])->save();
        }

        if ($emailChanged) {
            // Security alert to the previous address so the owner is warned if
            // the change wasn't them. {user_email} is the new address.
            app(MailService::class)->send('email_changed', $previousEmail, [
                'user_name' => $user->name,
                'user_email' => $user->email,
            ]);

            // With verification switched off, verification.notice 404s and the
            // `verified` gate would lock the account out of the whole dashboard,
            // so the new address inherits the verified state.
            if (! (bool) settings('email_verification_enabled', true)) {
                $user->markEmailAsVerified();

                return redirect()->route('user.dashboard.profile')
                    ->with('success', translate('Profile updated successfully.'));
            }

            $user->forceFill(['email_verified_at' => null])->save();

            // This platform verifies by 6-digit code, not by signed link — the
            // framework's sendEmailVerificationNotification() mailed a link that
            // no route even answers, while the UI asked for a code.
            SendTemplatedEmail::dispatch('email_verify_otp', $user->email, [
                'user_name' => $user->name,
                'site_name' => settings('app_name', translate('Application')),
                'otp_code' => $user->generateOtp(),
            ])->onQueue('otp');

            return redirect()->route('verification.notice')
                ->with('success', translate('Profile updated. Enter the code we sent to your new email address.'));
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

        // Same out-of-band alert the reset flow sends: a password change from
        // inside a stolen session is exactly the case worth warning about.
        SendTemplatedEmail::dispatch('password_changed', $user->email, [
            'user_name' => $user->name,
            'user_email' => $user->email,
        ])->onQueue('emails');

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

        // Store the new avatar first; the old one is removed only after that succeeds.
        $path = store_public_upload($request->file('avatar'), 'avatars', $user->avatar);

        $user->update(['avatar' => $path]);

        return redirect()->route('user.dashboard.profile')
            ->with('success', translate('Avatar updated successfully.'));
    }

    // ─── Phone Verification ────────────────────

    public function sendPhoneOtp(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sms = SmsService::fromSettings();

        if (! $sms->isEnabled()) {
            return back()->with('error', translate('Phone verification is currently unavailable.'));
        }

        if ($user->hasVerifiedPhone()) {
            return back()->with('info', translate('Your phone number is already verified.'));
        }

        // SMS costs money, so sends are throttled tightly. Handled here (not via
        // throttle middleware) so a limit hit is an Inertia redirect, not JSON.
        $rateLimiter = app(RateLimiterService::class);
        $sendKey = 'phone-verify-send:'.$user->ulid.'|'.$request->ip();
        $sendResult = $rateLimiter->attempt('otp', $sendKey, 4, 3600);
        if (! $sendResult['allowed']) {
            return back()->with('error', $this->otpThrottleMessage((int) $sendResult['retry_after_seconds']));
        }

        $e164 = PhoneNumber::e164($user->phone, $user->phone_country);
        if ($e164 === null) {
            return back()->with('error', translate('Add a valid phone number before requesting a code.'));
        }

        $otp = $user->generateOtp();
        $message = translate('Your verification code is:').' '.$otp;
        $result = $sms->send($e164, $message);

        if (! ($result['success'] ?? false)) {
            // Roll back the pending OTP so a failed send doesn't lock the slot.
            $user->clearOtp();

            // Never surface the raw provider error to the user: it leaks gateway
            // internals and (e.g. Twilio "Authentication Error") gets rewritten by
            // the frontend sanitizer into a misleading "AI provider" message. Log
            // the real reason for admins; show a clean, SMS-specific message.
            logger()->warning('Phone verification SMS failed to send.', [
                'user_id' => $user->id,
                'provider' => (string) settings('external_sms_gateway_provider', 'twilio'),
                'error' => $result['error'] ?? null,
            ]);

            return back()->with('error', translate('We could not send the verification code right now. Please try again later.'));
        }

        // Bind the OTP to the exact phone it was sent to, so a mid-flow phone
        // change can't be confirmed with a code delivered elsewhere.
        $request->session()->put('phone_otp_target', $user->phone.'|'.$user->phone_country);

        return back()
            ->with('success', translate('A verification code was sent to your phone.'))
            ->with('phone_otp_sent', true);
    }

    public function verifyPhoneOtp(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! SmsService::fromSettings()->isEnabled()) {
            return back()->with('error', translate('Phone verification is currently unavailable.'));
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if ($user->hasVerifiedPhone()) {
            return back()->with('info', translate('Your phone number is already verified.'));
        }

        // Throttle verify attempts (in addition to the per-OTP lockout below).
        $rateLimiter = app(RateLimiterService::class);
        $verifyKey = 'phone-verify-check:'.$user->ulid.'|'.$request->ip();
        $verifyResult = $rateLimiter->attempt('otp', $verifyKey, 6, 900);
        if (! $verifyResult['allowed']) {
            throw ValidationException::withMessages([
                'code' => [$this->otpThrottleMessage((int) $verifyResult['retry_after_seconds'])],
            ]);
        }

        // The OTP is only valid for the phone it was issued against.
        $target = $request->session()->get('phone_otp_target');
        if ($target === null) {
            throw ValidationException::withMessages([
                'code' => [translate('Please request a verification code first.')],
            ]);
        }
        if ($target !== $user->phone.'|'.$user->phone_country) {
            throw ValidationException::withMessages([
                'code' => [translate('Your phone number changed. Please request a new code.')],
            ]);
        }

        if ($user->isOtpLocked()) {
            throw ValidationException::withMessages([
                'code' => [translate('Too many incorrect attempts. Please try again later.')],
            ]);
        }

        if (! $user->verifyOtp((string) $data['code'])) {
            throw ValidationException::withMessages([
                'code' => [translate('The verification code is invalid or has expired.')],
            ]);
        }

        $user->markPhoneAsVerified();
        $user->clearOtp();
        $request->session()->forget('phone_otp_target');

        return redirect()->route('user.dashboard.profile')
            ->with('success', translate('Your phone number has been verified.'));
    }

    /**
     * In-controller rate limit for the Inertia 2FA action routes, throwing an Inertia
     * validation error on the `code` field instead of the JSON the throttle middleware
     * would return.
     */
    private function throttleTwoFactorAction(Request $request, string $action): void
    {
        $user = $request->user();
        $result = app(RateLimiterService::class)->attempt('otp', "2fa-$action:".$user->ulid.'|'.$request->ip(), 5, 900);

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                'code' => [$this->otpThrottleMessage((int) $result['retry_after_seconds'])],
            ]);
        }
    }

    private function otpThrottleMessage(int $seconds): string
    {
        return $seconds < 60
            ? translate('Too many requests. Please try again in :seconds seconds.', ['seconds' => $seconds])
            : translate('Too many requests. Please try again in :minutes minutes.', ['minutes' => (int) ceil($seconds / 60)]);
    }

    // ─── API Keys ──────────────────────────────

    public function byok(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('User/Byok', [
            'byokKeys' => $user->byok()->latest()->get()->map(fn (UserByok $key) => [
                'id' => $key->id,
                'provider' => $key->provider,
                'is_active' => $key->is_active,
                'created_at' => $key->created_at?->toISOString(),
            ]),
        ]);
    }

    public function storeByok(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'api_key' => ['required', 'string', 'max:1000'],
        ]);

        $user->byok()->create([
            'provider' => $validated['provider'],
            'api_key' => encrypt($validated['api_key']),
            'created_at' => now(),
        ]);

        return back()->with('success', translate('BYOK key added successfully.'));
    }

    public function destroyByok(Request $request, UserByok $key): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($key->user_id !== $user->id) {
            abort(403);
        }

        $key->delete();
        $request->session()->forget(['error', 'warning', 'info']);

        return back()->with('success', translate('BYOK key removed.'));
    }

    // ─── 2FA ───────────────────────────────────

    public function enableTwoFactor(EnableTwoFactorRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->throttleTwoFactorAction($request, 'enable');
        $method = $request->validated('method') ?? 'totp';

        if ($method === 'sms') {
            if (! sms_two_factor_available($user)) {
                return back()->with('error', translate('SMS two-factor authentication is unavailable.'));
            }

            if (! $request->session()->get('two_factor_sms_setup') || ! $user->verifyOtp((string) $request->validated('code'))) {
                throw ValidationException::withMessages([
                    'code' => [translate('The verification code is invalid or has expired.')],
                ]);
            }

            $recoveryCodes = $user->enableSmsTwoFactor();
            $user->clearOtp();
            $request->session()->forget('two_factor_sms_setup');
        } else {
            $secret = $request->session()->get('user_pending_totp_secret');

            if (! $secret || ! $totp->verify($secret, (string) $request->validated('code'))) {
                throw ValidationException::withMessages([
                    'code' => [translate('Invalid authenticator code.')],
                ]);
            }

            $recoveryCodes = $user->enableTotp($secret);
            $request->session()->forget('user_pending_totp_secret');
        }

        $request->session()->put('user_plain_recovery_codes', $recoveryCodes);

        return redirect()
            ->route('user.dashboard.security')
            ->with('success', translate('Two-factor authentication enabled successfully.'));
    }

    /**
     * Send an SMS OTP for setting up (or acting on) SMS two-factor auth. Reused by
     * the enable flow and the disable/regenerate forms for SMS-channel accounts.
     */
    public function sendTwoFactorSmsCode(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! sms_two_factor_available($user)) {
            return back()->with('error', translate('SMS two-factor authentication is unavailable.'));
        }

        $rateLimiter = app(RateLimiterService::class);
        $key = '2fa-sms-send:'.$user->ulid.'|'.$request->ip();
        $result = $rateLimiter->attempt('otp', $key, 4, 3600);
        if (! $result['allowed']) {
            return back()->with('error', $this->otpThrottleMessage((int) $result['retry_after_seconds']));
        }

        $e164 = PhoneNumber::e164($user->phone, $user->phone_country);
        if ($e164 === null) {
            return back()->with('error', translate('Add a valid phone number first.'));
        }

        $otp = $user->generateOtp();
        $sent = SmsService::fromSettings()->send($e164, translate('Your verification code is:').' '.$otp);
        if (! ($sent['success'] ?? false)) {
            $user->clearOtp();
            logger()->warning('2FA SMS code failed to send.', ['user_id' => $user->id, 'error' => $sent['error'] ?? null]);

            return back()->with('error', translate('We could not send the verification code right now. Please try again later.'));
        }

        $request->session()->put('two_factor_sms_setup', true);

        return back()
            ->with('success', translate('A verification code was sent to your phone.'))
            ->with('two_factor_sms_sent', true);
    }

    public function disableTwoFactor(DisableTwoFactorRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->throttleTwoFactorAction($request, 'disable');
        $this->ensurePasswordMatches($user, (string) $request->validated('password'));
        $this->ensureValidSecondFactor($user, (string) $request->validated('code'), $totp);

        $user->disableTotp();

        return back()->with('success', translate('Two-factor authentication disabled successfully.'));
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->throttleTwoFactorAction($request, 'recovery');
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
        // Primary factor depends on the channel; recovery codes always work.
        $primaryValid = $user->usesSmsTwoFactor()
            ? $user->verifyOtp($code)
            : $user->verifyTotp($code, $totp);

        if ($primaryValid || $user->useRecoveryCode($code)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => [translate('Invalid verification or recovery code.')],
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
