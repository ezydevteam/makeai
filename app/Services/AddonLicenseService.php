<?php
 
namespace App\Services;
 
use App\DTO\LicenseResult;
use App\Models\AddonLicense;
use App\Support\PurchaseCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
 
class AddonLicenseService
{
    private const CACHE_KEY_PREFIX = 'addon_license.';
 
    private const CACHE_TTL = 3600;

    // Public key lives in App\Support\LicenseKey — the single place to set it,
    // shared with LicenseService so the two can never diverge.
    private const LICENSE_SERVER_URL = 'https://license.ezydev.net/api/v1/verify';
 
    /**
     * Verify a purchase code via the author's license server.
     * Called ONLY during first-time activation (or manual re-entry after invalidation).
     */
    public function verify(string $addonSlug, string $purchaseCode): LicenseResult
    {
        // Block verification in demo mode
        if (config('demo.enabled')) {
            return LicenseResult::failure(
                translate('Verification is disabled in demo mode.'),
                'demo_mode'
            );
        }

        // Test-mode activation — recognized fake codes (shared with core) never
        // reach the license server. Checked before the format/public-key checks so
        // the TEST-... codes work exactly like core activation.
        if (($testType = PurchaseCode::matchTestCode($purchaseCode)) !== null) {
            return $this->buildTestResult($addonSlug, $purchaseCode, $testType);
        }

        // 1. Validate purchase code format locally → fail fast, no network call
        if (! PurchaseCode::isValidUuid($purchaseCode)) {
            return LicenseResult::failure(
                translate('Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                'invalid_format'
            );
        }
 
        if (! $this->publicKeyConfigured()) {
            \Illuminate\Support\Facades\Log::critical('AddonLicenseService: License Server public key (App\Support\LicenseKey) is still the placeholder — real key not set before packaging.');
            return LicenseResult::failure(
                translate('License verification is not configured on this build. Please contact the author/support.'),
                'public_key_missing'
            );
        }

        // 2. POST to LICENSE_SERVER_URL { product:'addon', slug, purchase_code, domain, version }
        //    — timeout 15s, 2 retries with backoff
        $manifest = app(AddonService::class)->getAddonConfig($addonSlug);
        $version = $manifest['version'] ?? '1.0.0';

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->post(self::LICENSE_SERVER_URL, [
                    'product' => 'addon',
                    'slug' => $addonSlug,
                    'purchase_code' => $purchaseCode,
                    'domain' => request()->getHost(),
                    'version' => $version,
                ]);

            if (! $response->successful()) {
                return LicenseResult::failure(
                    translate('Could not reach license server — try again.'),
                    'network'
                );
            }

            $body = $response->body();
            $data = $response->json();

            if (! isset($data['signature']) || ! isset($data['payload'])) {
                return LicenseResult::failure(
                    translate('Invalid response from license server. Missing signature or payload.'),
                    'api_error'
                );
            }

            // 3. Extract the exact raw payload JSON substring from the response body to avoid key ordering/formatting mismatches
            $rawPayload = $this->extractRawPayload($body);

            if (! $rawPayload) {
                return LicenseResult::failure(
                    translate('Could not verify license server response signature (payload extraction failed).'),
                    'signature_invalid'
                );
            }

            if (! function_exists('sodium_crypto_sign_verify_detached')) {
                return LicenseResult::failure(
                    translate('PHP Sodium extension is required for license signature verification.'),
                    'sodium_missing'
                );
            }

            $signatureBytes = @base64_decode($data['signature']);
            $publicKeyBytes = @base64_decode($this->getPublicKey());

            if (! $signatureBytes || ! $publicKeyBytes) {
                return LicenseResult::failure(
                    translate('Could not verify license server response (invalid signature/public key encoding).'),
                    'signature_invalid'
                );
            }

            $isVerified = @sodium_crypto_sign_verify_detached(
                $signatureBytes,
                $rawPayload,
                $publicKeyBytes
            );

            if (! $isVerified) {
                return LicenseResult::failure(
                    translate('Could not verify license server response.'),
                    'signature_invalid'
                );
            }

            $payload = $data['payload'];

            // 4. Reject if payload.valid === false (map payload.error to user-facing message)
            if (empty($payload['valid'])) {
                $errorCode = $payload['error'] ?? 'api_error';
                $errorMap = [
                    'invalid_format' => translate('Invalid purchase code format.'),
                    'not_found' => translate('Purchase code not found.'),
                    'wrong_item' => translate('This code belongs to a different item.'),
                    'refunded' => translate('This purchase was refunded.'),
                    'revoked' => translate('This license has been revoked.'),
                ];
                $errorMessage = $errorMap[$errorCode] ?? translate('License validation failed.');
                return LicenseResult::failure($errorMessage, $errorCode);
            }

            $type = (int) ($payload['license_type'] ?? 1);
            $supportedUntil = null;
            if (! empty($payload['supported_until'])) {
                $supportedUntil = \Illuminate\Support\Carbon::parse($payload['supported_until']);
            }

            // 5. Encrypt purchase_code with Crypt::encryptString(), upsert into addon_licenses
            // 6. Set: verified_at = now(), domain = request()->getHost(), status = 'valid'
            AddonLicense::updateOrCreate(
                ['addon_slug' => $addonSlug],
                [
                    'purchase_code' => Crypt::encryptString($purchaseCode),
                    'license_type' => $type,
                    'buyer' => $payload['buyer'] ?? translate('Unknown'),
                    'purchased_at' => $payload['purchased_at'] ?? now(),
                    'supported_until' => $supportedUntil,
                    'domain' => request()->getHost(),
                    'verified_at' => now(),
                    'status' => 'valid',
                    'grace_started_at' => null,
                    'signed_payload' => $rawPayload,
                    'signature' => $data['signature'],
                ]
            );

            Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);

            Log::info('AddonLicenseService: License verified for addon via proxy server', [
                'addon' => $addonSlug,
                'buyer' => $payload['buyer'] ?? 'unknown',
                'type' => $type,
            ]);

            return LicenseResult::success([
                'type' => $type,
                'buyer' => $payload['buyer'] ?? translate('Unknown'),
                'purchase_date' => $payload['purchased_at'] ?? now()->toDateString(),
                'license' => $type === 2 ? 'Extended License' : 'Regular License',
            ]);

        } catch (\Throwable $e) {
            Log::error('AddonLicenseService: License server connection failed or error returned', [
                'addon' => $addonSlug,
                'error' => $e->getMessage(),
            ]);

            return LicenseResult::failure(
                translate('Could not connect to license server — please try again.'),
                'connection_error'
            );
        }
    }
 
    /**
     * Quick cached check — used by is_addon_active() and AddonLicenseMiddleware.
     * Cache key: "addon_license.{slug}" TTL 3600 (cleared on verify/revoke).
     */
    public function isLicensed(string $addonSlug): bool
    {
        return Cache::remember(self::CACHE_KEY_PREFIX . $addonSlug, self::CACHE_TTL, function () use ($addonSlug) {
            $license = AddonLicense::where('addon_slug', $addonSlug)->first();
            if (! $license) return false;
 
            if ($license->status === 'invalid') return false;
 
            // Domain check
            if (! $this->checkDomain($addonSlug)) {
                Log::warning('AddonLicenseService: Domain mismatch for addon', ['addon' => $addonSlug]);
                // Still return true — spec says domain change shows warning, not auto-deactivation
            }
 
            return true;
        });
    }
 
    /**
     * Get license info for display in admin UI (purchase code masked).
     */
    public function getLicenseInfo(string $addonSlug): ?array
    {
        $license = AddonLicense::where('addon_slug', $addonSlug)->first();
        if (! $license) return null;
 
        $purchaseCode = '';
        try {
            $decrypted = Crypt::decryptString($license->purchase_code);
            $purchaseCode = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxx' . substr($decrypted, -4);
        } catch (\Throwable) {
            $purchaseCode = '********';
        }
 
        return [
            'license_type' => $license->license_type,
            'license_type_label' => $license->license_type === 2 ? translate('Extended License') : translate('Regular License'),
            'buyer' => $license->buyer,
            'purchase_code_masked' => $purchaseCode,
            'purchased_at' => $license->purchased_at?->toDateString(),
            'supported_until' => $license->supported_until?->toDateString(),
            'verified_at' => $license->verified_at?->toDateTimeString(),
            'status' => $license->status,
            'domain_ok' => $this->checkDomain($addonSlug),
            'grace_started_at' => $license->grace_started_at?->toDateTimeString(),
        ];
    }
 
    /**
     * Scheduled re-verification — daily job re-verifies addons whose
     * verified_at > 7 days ago (configurable via settings('addon_license_recheck_days', 7)).
     * Calls the same license server /verify endpoint with the stored (decrypted) code.
     *
     * On network failure / license server down: keep status, retry next day —
     *   NEVER punish buyers for the author's server being temporarily unreachable.
     * On signed invalid response (refunded / revoked): status = 'grace', grace_started_at = now().
     * After 72h grace: status = 'invalid' → addon auto-deactivated + admin notified (mail + in-app).
     */
    public function reverify(string $addonSlug): void
    {
        $license = AddonLicense::where('addon_slug', $addonSlug)->first();
        if (! $license) return;
 
        $purchaseCode = '';
        try {
            $purchaseCode = Crypt::decryptString($license->purchase_code);
        } catch (\Throwable) {
            $this->markInvalid($addonSlug);
            return;
        }

        // Test-mode licenses never reach the server — keep them valid.
        if (PurchaseCode::matchTestCode($purchaseCode) !== null) {
            $license->update([
                'verified_at' => now(),
                'status' => 'valid',
                'grace_started_at' => null,
            ]);
            Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);
            Log::info('AddonLicenseService: addon re-verified via TEST MODE', ['addon' => $addonSlug]);

            return;
        }

        // Key not configured on this build — can't verify signatures. Treat as a
        // transient condition; never punish the buyer (mirrors LicenseService).
        if (! $this->publicKeyConfigured()) {
            Log::critical('AddonLicenseService: Re-verify skipped — license server public key not configured.', ['addon' => $addonSlug]);
            return;
        }

        $manifest = app(AddonService::class)->getAddonConfig($addonSlug);
        $version = $manifest['version'] ?? '1.0.0';

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->post(self::LICENSE_SERVER_URL, [
                    'product' => 'addon',
                    'slug' => $addonSlug,
                    'purchase_code' => $purchaseCode,
                    'domain' => request()->getHost(),
                    'version' => $version,
                ]);
 
            if (! $response->successful()) {
                Log::warning('AddonLicenseService: Re-verify API error (transient)', ['addon' => $addonSlug]);
                $this->checkOfflineDeadline($addonSlug, $license);
                return;
            }
 
            $body = $response->body();
            $data = $response->json();
 
            if (! isset($data['signature']) || ! isset($data['payload'])) {
                Log::warning('AddonLicenseService: Re-verify response missing signature or payload', ['addon' => $addonSlug]);
                return;
            }
 
            $rawPayload = $this->extractRawPayload($body);
            if (! $rawPayload) {
                Log::warning('AddonLicenseService: Re-verify response payload extraction failed', ['addon' => $addonSlug]);
                return;
            }
 
            if (! function_exists('sodium_crypto_sign_verify_detached')) {
                Log::warning('AddonLicenseService: Re-verify skipped signature due to missing sodium', ['addon' => $addonSlug]);
                return;
            }
 
            $signatureBytes = @base64_decode($data['signature']);
            $publicKeyBytes = @base64_decode($this->getPublicKey());
 
            if (! $signatureBytes || ! $publicKeyBytes || ! @sodium_crypto_sign_verify_detached($signatureBytes, $rawPayload, $publicKeyBytes)) {
                $this->startGracePeriod($addonSlug, $license);
                return;
            }
 
            $payload = $data['payload'];

            if (empty($payload['valid'])) {
                // Transient marketplace/author-side errors must NOT start a grace
                // period — otherwise an Envato outage invalidates a legit addon.
                $transientErrors = ['envato_error', 'api_error', 'network', 'connection_error'];
                if (in_array($payload['error'] ?? '', $transientErrors, true)) {
                    Log::warning('AddonLicenseService: Re-verify returned a transient server error — keeping current status', [
                        'addon' => $addonSlug,
                        'error' => $payload['error'] ?? null,
                    ]);
                    return;
                }

                $this->startGracePeriod($addonSlug, $license);
                return;
            }

            // All good — reset grace
            $license->update([
                'verified_at' => now(),
                'status' => 'valid',
                'grace_started_at' => null,
                'signed_payload' => $rawPayload,
                'signature' => $data['signature'],
            ]);

            Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);

            Log::info('AddonLicenseService: Re-verify successful', ['addon' => $addonSlug]);
            return;
        } catch (ConnectionException $e) {
            // Network error — never punish for a transient issue, but DO enforce the
            // offline deadline so blocking the license server can't keep a nulled
            // addon alive forever.
            Log::warning('AddonLicenseService: Re-verify connection error (transient)', [
                'addon' => $addonSlug,
                'error' => $e->getMessage(),
            ]);
            $this->checkOfflineDeadline($addonSlug, $license);
        }
    }
 
    /**
     * Check if the current domain matches the domain bound at activation.
     */
    public function checkDomain(string $addonSlug): bool
    {
        $license = AddonLicense::where('addon_slug', $addonSlug)->first();
        if (! $license) return false;

        return $license->domain === request()->getHost();
    }

    /**
     * Check the license server for an available update for one addon and cache the
     * result in addon settings for the admin UI. Read-only — never modifies files.
     *
     * @return array{update_available:bool, latest_version:?string, changelog:?string, error:?string}
     */
    public function checkAddonUpdate(string $addonSlug): array
    {
        $fail = static fn (string $error): array => [
            'update_available' => false, 'latest_version' => null, 'changelog' => null, 'error' => $error,
        ];

        $license = AddonLicense::where('addon_slug', $addonSlug)->first();
        if (! $license) {
            return $fail('not_licensed');
        }

        try {
            $code = Crypt::decryptString($license->purchase_code);
        } catch (\Throwable) {
            return $fail('decrypt_failed');
        }

        $manifest = app(AddonService::class)->getAddonConfig($addonSlug);
        $current = $manifest['version'] ?? '0.0.0';

        // Test mode: never contact the server — simulate a manifest so the badge and
        // flow are testable locally. Control via settings("addon_{slug}_test_latest_version").
        if (PurchaseCode::testModeActive()) {
            $latest = (string) settings("addon_{$addonSlug}_test_latest_version", $this->bumpVersion($current));
            $available = version_compare($latest, $current, '>');
            $changelog = $available ? '(Test mode) Simulated addon update to preview the UI.' : '';

            addon_setting_set($addonSlug, 'update_available', $available, 'boolean');
            addon_setting_set($addonSlug, 'latest_version', $latest);
            addon_setting_set($addonSlug, 'update_changelog', $changelog);
            addon_setting_set($addonSlug, 'update_checked_at', now()->toDateTimeString());

            return ['update_available' => $available, 'latest_version' => $latest, 'changelog' => $changelog ?: null, 'error' => null];
        }

        $license_service = app(LicenseService::class);

        try {
            $response = Http::timeout(20)->retry(2, 1000)->post($license_service->updateEndpoint(), [
                'product' => 'addon',
                'slug' => $addonSlug,
                'purchase_code' => $code,
                'domain' => request()?->getHost() ?? settings('site_url', ''),
                'current_version' => $current,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AddonLicenseService: addon update check network error', ['addon' => $addonSlug]);
            return $fail('network');
        }

        if (! $response->successful()) {
            return $fail('server');
        }

        $payload = $license_service->verifySignedResponse($response->body(), (array) $response->json());
        if ($payload === null || empty($payload['valid'])) {
            return $fail($payload['error'] ?? 'unverified');
        }

        $available = (bool) ($payload['update_available'] ?? false);
        addon_setting_set($addonSlug, 'update_available', $available, 'boolean');
        addon_setting_set($addonSlug, 'latest_version', $payload['latest_version'] ?? '');
        addon_setting_set($addonSlug, 'update_changelog', $payload['changelog'] ?? '');
        addon_setting_set($addonSlug, 'update_checked_at', now()->toDateTimeString());

        return [
            'update_available' => $available,
            'latest_version' => $payload['latest_version'] ?? null,
            'changelog' => $payload['changelog'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Check every active, licensed addon for updates.
     *
     * @return array<string,array>
     */
    public function checkAllAddonUpdates(): array
    {
        $results = [];

        foreach (\App\Models\Addon::where('is_active', true)->pluck('slug') as $slug) {
            if (! AddonLicense::where('addon_slug', $slug)->exists()) {
                continue; // no license → not eligible for server updates
            }
            $results[$slug] = $this->checkAddonUpdate($slug);
        }

        return $results;
    }

    /**
     * Remove stored license — only on addon delete.
     */
    public function revoke(string $addonSlug): void
    {
        AddonLicense::where('addon_slug', $addonSlug)->delete();
        Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);
    }
 
    /**
     * Get all addon slugs with a valid license.
     */
    public function getLicensedAddons(): array
    {
        return AddonLicense::where('status', '!=', 'invalid')->pluck('addon_slug')->toArray();
    }

    private function bumpVersion(string $version): string
    {
        $parts = explode('.', $version ?: '1.0.0');
        $parts[count($parts) - 1] = (string) ((int) end($parts) + 1);

        return implode('.', $parts);
    }

    /**
     * Extract the exact raw payload JSON substring from the response body.
     */
    private function extractRawPayload(string $responseBody): ?string
    {
        if (! preg_match('/"payload"\s*:\s*\{/', $responseBody, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $startPos = $matches[0][1] + strpos($matches[0][0], '{');
        $length = strlen($responseBody);
        $braceCount = 0;
        $inQuote = false;
        $escaped = false;

        for ($i = $startPos; $i < $length; $i++) {
            $char = $responseBody[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = ! $inQuote;
                continue;
            }

            if (! $inQuote) {
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        return substr($responseBody, $startPos, $i - $startPos + 1);
                    }
                }
            }
        }

        return null;
    }
 
    /**
     * Enforce the offline deadline for an addon. Reads the last genuinely
     * server-confirmed `verified_at` from the stored SIGNED payload — re-verifying
     * the signature offline so a DB-edited timestamp is rejected. If no signed
     * confirmation has happened within license.max_offline_days, grace starts.
     * Mirrors LicenseService::checkOfflineDeadline for the core license.
     */
    private function checkOfflineDeadline(string $addonSlug, AddonLicense $license): void
    {
        $maxDays = (int) config('license.max_offline_days', 14);
        if ($maxDays <= 0) {
            return; // deadline disabled
        }

        $payloadJson = $license->signed_payload;
        $signatureB64 = $license->signature;

        // No stored signed proof (activated before this feature) — fall back to the
        // plain last-success timestamp so the deadline still applies.
        if (blank($payloadJson) || blank($signatureB64)) {
            if ($license->verified_at
                && abs(now()->diffInDays($license->verified_at)) > $maxDays) {
                Log::warning('AddonLicenseService: Offline deadline exceeded (no signed proof) — starting grace', ['addon' => $addonSlug]);
                $this->startGracePeriod($addonSlug, $license);
            }
            return;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return;
        }

        // Re-verify the stored signature offline. If it fails, the proof was
        // tampered with (or the key changed) — do not trust the local timestamp.
        $sig = @base64_decode($signatureB64);
        $pk = @base64_decode($this->getPublicKey());

        if (! $sig || ! $pk || ! @sodium_crypto_sign_verify_detached($sig, $payloadJson, $pk)) {
            Log::warning('AddonLicenseService: Stored addon proof failed offline signature check — starting grace', ['addon' => $addonSlug]);
            $this->startGracePeriod($addonSlug, $license);
            return;
        }

        $data = json_decode($payloadJson, true);
        $verifiedAt = $data['verified_at'] ?? null;
        if (blank($verifiedAt)) {
            return;
        }

        if (abs(now()->diffInDays(\Illuminate\Support\Carbon::parse($verifiedAt))) > $maxDays) {
            Log::warning('AddonLicenseService: Offline deadline exceeded — no signed re-verification in :days days, starting grace', [
                'addon' => $addonSlug,
                'days' => $maxDays,
            ]);
            $this->startGracePeriod($addonSlug, $license);
        }
    }

    private function startGracePeriod(string $addonSlug, AddonLicense $license): void
    {
        $graceHours = 72;

        if ($license->grace_started_at) {
            $expiresAt = $license->grace_started_at->copy()->addHours($graceHours);
 
            if (now()->greaterThan($expiresAt)) {
                $this->markInvalid($addonSlug);
                return;
            }
            // Still in grace — leave as-is
            return;
        }
 
        $license->update([
            'status' => 'grace',
            'grace_started_at' => now(),
        ]);
 
        Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);
 
        Log::warning('AddonLicenseService: Grace period started', [
            'addon' => $addonSlug,
            'grace_hours' => $graceHours,
        ]);
    }
 
    private function markInvalid(string $addonSlug): void
    {
        AddonLicense::where('addon_slug', $addonSlug)->update([
            'status' => 'invalid',
        ]);
 
        Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);
 
        // Auto-deactivate the addon
        app(AddonService::class)->deactivate($addonSlug);

        // Notify admins (mail + in-app)
        try {
            $inAppNotificationService = app(\App\Services\InAppNotificationService::class);
            $inAppNotificationService->notifyAdmins([
                'title' => translate('Addon license deactivated'),
                'message' => translate('The license for addon :slug is invalid. The addon has been deactivated.', ['slug' => $addonSlug]),
                'level' => 'error',
                'category' => 'system',
            ], 'super-admin');

            $admins = \App\Models\Admin::where('is_active', true)->get();
            foreach ($admins as $admin) {
                $subject = translate('Addon License Deactivated: :slug', ['slug' => $addonSlug]);
                $message = translate('The license for addon :slug is invalid. The addon has been deactivated.', ['slug' => $addonSlug]);
                $html = <<<HTML
                    <div style="font-family:Inter,Arial,sans-serif;color:#111827;line-height:1.6">
                        <p>Hello {$admin->name},</p>
                        <p>{$message}</p>
                        <p style="margin-top:32px;color:#6b7280;font-size:13px">MakeAI Admin Notification</p>
                    </div>
                HTML;

                \Illuminate\Support\Facades\Mail::html($html, function ($mail) use ($admin, $subject) {
                    $mail->to($admin->email, $admin->name)->subject($subject);
                });

                \App\Models\MailLog::create([
                    'template_slug' => 'addon_license_deactivated',
                    'recipient_email' => $admin->email,
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('AddonLicenseService: Failed to notify admins of invalid license', [
                'addon' => $addonSlug,
                'error' => $e->getMessage(),
            ]);
        }
 
        Log::critical('AddonLicenseService: License marked invalid, addon deactivated', [
            'addon' => $addonSlug,
        ]);
    }

    /**
     * Get the public key for license server verification.
     */
    protected function getPublicKey(): string
    {
        if (app()->runningUnitTests() && app()->has('test.license_public_key')) {
            return app('test.license_public_key');
        }
        return \App\Support\LicenseKey::PUBLIC_KEY;
    }

    /**
     * Whether a real License Server public key has been baked in (mirrors
     * LicenseService::publicKeyConfigured).
     */
    private function publicKeyConfigured(): bool
    {
        return $this->getPublicKey() !== \App\Support\LicenseKey::PLACEHOLDER;
    }

    /**
     * Build and store a mock addon license for LICENSE_TEST_MODE — never contacts
     * the license server. Mirrors LicenseService::buildTestResult for core.
     */
    private function buildTestResult(string $addonSlug, string $purchaseCode, int $type): LicenseResult
    {
        AddonLicense::updateOrCreate(
            ['addon_slug' => $addonSlug],
            [
                'purchase_code' => Crypt::encryptString(strtoupper(trim($purchaseCode))),
                'license_type' => $type,
                'buyer' => 'test-buyer',
                'purchased_at' => now(),
                'supported_until' => now()->addYears(10),
                'domain' => request()->getHost(),
                'verified_at' => now(),
                'status' => 'valid',
                'grace_started_at' => null,
            ]
        );

        Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);

        Log::info('AddonLicenseService: addon activated via TEST MODE', [
            'addon' => $addonSlug,
            'type' => $type,
        ]);

        return LicenseResult::success([
            'type' => $type,
            'buyer' => 'test-buyer',
            'purchase_date' => now()->toDateString(),
            'license' => $type === 2 ? 'Extended License' : 'Regular License',
        ]);
    }
}
