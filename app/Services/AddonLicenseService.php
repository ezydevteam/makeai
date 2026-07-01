<?php
 
namespace App\Services;
 
use App\DTO\LicenseResult;
use App\Models\AddonLicense;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
 
class AddonLicenseService
{
    private const PURCHASE_CODE_PATTERN = '/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i';
 
    private const CACHE_KEY_PREFIX = 'addon_license.';
 
    private const CACHE_TTL = 3600;

    // Public key shipped in core — pairs with the private key on the license server.
    // Stored as a class constant, NOT in settings/DB (settings can be edited by a nuller).
    private const LICENSE_SERVER_PUBLIC_KEY = 'MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=';
    private const LICENSE_SERVER_URL        = 'https://license.yourdomain.com/api/v1/verify';
 
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

        // 1. Validate purchase code format locally → fail fast, no network call
        if (! preg_match(self::PURCHASE_CODE_PATTERN, $purchaseCode)) {
            return LicenseResult::failure(
                translate('Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                'invalid_format'
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
                    'envato_item_id' => (int) ($payload['item_id'] ?? 0),
                    'license_type' => $type,
                    'buyer' => $payload['buyer'] ?? translate('Unknown'),
                    'purchased_at' => $payload['purchased_at'] ?? now(),
                    'supported_until' => $supportedUntil,
                    'domain' => request()->getHost(),
                    'verified_at' => now(),
                    'status' => 'valid',
                    'grace_started_at' => null,
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
                $this->startGracePeriod($addonSlug, $license);
                return;
            }
 
            // All good — reset grace
            $license->update([
                'verified_at' => now(),
                'status' => 'valid',
                'grace_started_at' => null,
            ]);
 
            Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);
 
            Log::info('AddonLicenseService: Re-verify successful', ['addon' => $addonSlug]);
            return;
        } catch (ConnectionException $e) {
            // Network error — never punish for transient issues
            Log::warning('AddonLicenseService: Re-verify connection error (transient)', [
                'addon' => $addonSlug,
                'error' => $e->getMessage(),
            ]);
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
        return self::LICENSE_SERVER_PUBLIC_KEY;
    }
}
