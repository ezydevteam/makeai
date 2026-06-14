<?php

namespace App\Services;

use App\DataTransferObjects\LicenseResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Envato license types mapped from API strings to ints.
     */
    public const TYPE_REGULAR = 1;
    public const TYPE_EXTENDED = 2;

    // Public key shipped in core — pairs with the private key on the License Server.
    // Class constant, NOT settings/DB/.env — settings can be edited by a nuller.
    private const LICENSE_SERVER_PUBLIC_KEY = 'MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=';
    private const LICENSE_SERVER_URL        = 'https://license.yourdomain.com/api/v1/verify';

    /**
     * Verify a purchase code via the Author License Server.
     * Called during installation wizard step 4, manual re-entry, and scheduled re-verify.
     */
    public function verify(string $purchaseCode, bool $store = true): LicenseResult
    {
        // Block verification in demo mode
        if (config('demo.enabled')) {
            return LicenseResult::failure(
                translate('Verification is disabled in demo mode.'),
                'demo_mode'
            );
        }

        // 1. Validate format locally: /^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i → fail fast
        $purchaseCode = trim($purchaseCode);
        if (! preg_match('/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $purchaseCode)) {
            return LicenseResult::failure(
                translate('Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                'invalid_format'
            );
        }

        // 2. POST LICENSE_SERVER_URL:
        //    { product: 'core', slug: 'makeai', purchase_code, domain: request()->getHost(), version }
        //    timeout 15s, 2 retries with backoff
        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->post(self::LICENSE_SERVER_URL, [
                    'product' => 'core',
                    'slug' => 'makeai',
                    'purchase_code' => $purchaseCode,
                    'domain' => request()->getHost(),
                    'version' => config('app.version', '1.0.0'),
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

            // 3. Verify Ed25519 signature:
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

            // 4. Reject if payload.valid === false (map payload.error → user-facing message)
            if (empty($payload['valid'])) {
                $errorCode = $payload['error'] ?? 'api_error';
                $errorMap = [
                    'invalid_format' => translate('Invalid purchase code format.'),
                    'not_found' => translate('Purchase code not found.'),
                    'wrong_item' => translate('This code belongs to a different product.'),
                    'refunded' => translate('This purchase was refunded.'),
                    'revoked' => translate('This license has been revoked — contact support.'),
                ];
                $errorMessage = $errorMap[$errorCode] ?? translate('License validation failed.');
                return LicenseResult::failure($errorMessage, $errorCode);
            }

            $type = (int) ($payload['license_type'] ?? 1);
            $supportedUntil = null;
            if (! empty($payload['supported_until'])) {
                $supportedUntil = $payload['supported_until'];
            }

            // 5. Store encrypted in settings table, group 'license':
            if ($store) {
                settings_set('license_purchase_code', Crypt::encryptString($purchaseCode), 'encrypted', 'license');
                settings_set('license_type', $type, 'integer', 'license');
                settings_set('license_buyer', $payload['buyer'] ?? translate('Unknown'), 'string', 'license');
                settings_set('license_purchased_at', $payload['purchased_at'] ?? now()->toDateString(), 'string', 'license');
                settings_set('license_supported_until', $supportedUntil, 'string', 'license');
                settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
                settings_set('license_domain', request()->getHost(), 'string', 'license');
                settings_set('license_status', 'valid', 'string', 'license');
                settings_set('license_grace_started_at', null, 'string', 'license');

                Cache::forget('license.status');
            }

            Log::info('LicenseService: Core license verified successfully', [
                'buyer' => $payload['buyer'] ?? 'unknown',
                'type' => $type,
            ]);

            return LicenseResult::success([
                'type' => $type,
                'buyer' => $payload['buyer'] ?? translate('Unknown'),
                'purchase_date' => $payload['purchased_at'] ?? now()->toDateString(),
                'license' => $type === 2 ? 'Extended License' : 'Regular License',
                'supported_until' => $supportedUntil,
            ]);

        } catch (\Throwable $e) {
            Log::error('LicenseService: License server connection failed or error returned', [
                'error' => $e->getMessage(),
            ]);

            return LicenseResult::failure(
                translate('Could not connect to license server — please try again.'),
                'connection_error'
            );
        }
    }

    public function getLicenseType(): int
    {
        return (int) settings('license_type', 1);
    }

    public function isExtended(): bool
    {
        return $this->getLicenseType() === 2;
    }

    public function isValid(): bool
    {
        return license_verified();
    }

    public function reactivate(string $code): bool
    {
        $result = $this->verify($code);
        return $result->valid;
    }

    /**
     * Scheduled re-verification — daily job `license:reverify` (queue: low, 03:00),
     * runs only when license_verified_at > settings('license_recheck_days', 7) days ago.
     *
     * Network failure / License Server unreachable: keep current status, retry next
     *   day — NEVER punish buyers for author-side downtime.
     * Signed valid:false (refunded/revoked): license_status = 'grace',
     *   license_grace_started_at = now().
     * After 72h grace: license_status = 'invalid' → frontend features stopped,
     *   persistent banner shown, admin emailed.
     */
    public function reverify(bool $force = false): bool
    {
        $status = settings('license_status');
        if ($status === 'invalid' && ! $force) {
            return false;
        }

        $purchaseCodeEnc = settings('license_purchase_code');
        if (blank($purchaseCodeEnc)) {
            $this->deactivate();
            return false;
        }

        try {
            $purchaseCode = Crypt::decryptString($purchaseCodeEnc);
        } catch (\Throwable) {
            $this->markInvalid();
            return false;
        }

        if (! $force) {
            $lastReverify = settings('license_verified_at');
            $recheckDays = (int) settings('license_recheck_days', 7);

            if (filled($lastReverify)) {
                $daysSince = now()->diffInDays(\Illuminate\Support\Carbon::parse($lastReverify));
                if ($daysSince < $recheckDays && $status === 'valid') {
                    return true;
                }
            }
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->post(self::LICENSE_SERVER_URL, [
                    'product' => 'core',
                    'slug' => 'makeai',
                    'purchase_code' => $purchaseCode,
                    'domain' => request()->getHost(),
                    'version' => config('app.version', '1.0.0'),
                ]);

            if (! $response->successful()) {
                Log::warning('LicenseService: Re-verify API error (transient)');
                return false;
            }

            $body = $response->body();
            $data = $response->json();

            if (! isset($data['signature']) || ! isset($data['payload'])) {
                Log::warning('LicenseService: Re-verify response missing signature or payload');
                return false;
            }

            $rawPayload = $this->extractRawPayload($body);
            if (! $rawPayload) {
                Log::warning('LicenseService: Re-verify response payload extraction failed');
                return false;
            }

            if (! function_exists('sodium_crypto_sign_verify_detached')) {
                Log::warning('LicenseService: Re-verify skipped signature due to missing sodium');
                return false;
            }

            $signatureBytes = @base64_decode($data['signature']);
            $publicKeyBytes = @base64_decode($this->getPublicKey());

            if (! $signatureBytes || ! $publicKeyBytes || ! @sodium_crypto_sign_verify_detached($signatureBytes, $rawPayload, $publicKeyBytes)) {
                $this->startGracePeriod();
                return false;
            }

            $payload = $data['payload'];

            if (empty($payload['valid'])) {
                $this->startGracePeriod();
                return false;
            }

            // All good — reset grace
            settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
            settings_set('license_status', 'valid', 'string', 'license');
            settings_set('license_grace_started_at', null, 'string', 'license');

            Cache::forget('license.status');

            Log::info('LicenseService: Re-verify successful');
            return true;
        } catch (\Throwable $e) {
            // Network error — never punish for transient issues
            Log::warning('LicenseService: Re-verify connection error (transient)', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the current license status details for the admin page.
     */
    public function getStatus(): array
    {
        $status = settings('license_status', 'invalid');
        $verified = ($status === 'valid' || $status === 'grace');
        $graceStart = settings('license_grace_started_at');
        $inGracePeriod = ($status === 'grace' && filled($graceStart));
        $graceExpired = ($status === 'invalid' && filled($graceStart));
        $graceHoursRemaining = 0;

        if ($inGracePeriod) {
            $graceHours = config('license.grace_period', 72);
            $startedAt = \Illuminate\Support\Carbon::parse($graceStart);
            $expiresAt = $startedAt->copy()->addHours($graceHours);

            if (now()->greaterThan($expiresAt)) {
                $graceExpired = true;
                $inGracePeriod = false;
            } else {
                $graceHoursRemaining = max(0, (int) now()->diffInHours($expiresAt, false));
            }
        }

        return [
            'verified' => $verified,
            'type' => $this->getLicenseType(),
            'type_label' => $this->isExtended() ? translate('Extended License') : translate('Regular License'),
            'buyer' => settings('license_buyer', ''),
            'purchase_date' => settings('license_purchased_at', ''),
            'last_reverify' => settings('license_verified_at', ''),
            'domain_ok' => $this->checkDomain(),
            'in_grace_period' => $inGracePeriod,
            'grace_expired' => $graceExpired,
            'grace_hours_remaining' => $graceHoursRemaining,
            'grace_started_at' => $graceStart,
        ];
    }

    /**
     * Check if the current domain matches the domain bound at activation.
     */
    public function checkDomain(): bool
    {
        $storedDomain = settings('license_domain');
        if (blank($storedDomain)) {
            return false;
        }
        return $storedDomain === request()->getHost();
    }

    /**
     * Deactivate the current license — wipe all stored license data.
     */
    public function deactivate(): void
    {
        $keys = [
            'license_purchase_code',
            'license_type',
            'license_buyer',
            'license_purchased_at',
            'license_supported_until',
            'license_verified_at',
            'license_domain',
            'license_status',
            'license_grace_started_at',
        ];

        foreach ($keys as $key) {
            settings_set($key, null, 'string', 'license');
        }

        // Also disable subscriptions
        settings_set('subscriptions_enabled', false, 'boolean', 'license');
        Cache::forget('license.status');

        Log::info('LicenseService: License deactivated');
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

    private function startGracePeriod(): void
    {
        $graceHours = config('license.grace_period', 72);
        $graceStart = settings('license_grace_started_at');

        if ($graceStart) {
            $expiresAt = \Illuminate\Support\Carbon::parse($graceStart)->addHours($graceHours);

            if (now()->greaterThan($expiresAt)) {
                $this->markInvalid();
                return;
            }
            // Still in grace
            return;
        }

        settings_set('license_status', 'grace', 'string', 'license');
        settings_set('license_grace_started_at', now()->toDateTimeString(), 'string', 'license');
        Cache::forget('license.status');

        Log::warning('LicenseService: Grace period started', [
            'grace_hours' => $graceHours,
        ]);
    }

    private function markInvalid(): void
    {
        settings_set('license_status', 'invalid', 'string', 'license');
        settings_set('subscriptions_enabled', false, 'boolean', 'license');
        Cache::forget('license.status');

        // Notify admins (mail + in-app)
        try {
            $inAppNotificationService = app(\App\Services\InAppNotificationService::class);
            $inAppNotificationService->notifyAdmins([
                'title' => translate('Core license deactivated'),
                'message' => translate('The core application license is invalid. Access to frontend features has been blocked.'),
                'level' => 'error',
                'category' => 'system',
            ], 'super-admin');

            $admins = \App\Models\Admin::where('is_active', true)->get();
            foreach ($admins as $admin) {
                $subject = translate('Core License Deactivated');
                $message = translate('The core application license is invalid. Access to frontend features has been blocked.');
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
                    'template_slug' => 'core_license_deactivated',
                    'recipient_email' => $admin->email,
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('LicenseService: Failed to notify admins of invalid core license', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::critical('LicenseService: License marked invalid.');
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
