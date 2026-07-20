<?php

namespace App\Services;

use App\DTO\LicenseResult;
use App\Support\PurchaseCode;
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

    // Public key lives in App\Support\LicenseKey — the single place to set it,
    // shared with AddonLicenseService so the two can never diverge.
    private const LICENSE_SERVER_URL = 'https://license.ezydev.net/api/v1/verify';

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

        $purchaseCode = trim($purchaseCode);
        $purchaseCodeUpper = strtoupper($purchaseCode);

        if (($testType = PurchaseCode::matchTestCode($purchaseCode)) !== null) {
            return $this->buildTestResult($purchaseCodeUpper, $testType, $store);
        }

        if (! PurchaseCode::isValidUuid($purchaseCode)) {
            return LicenseResult::failure(
                translate('Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                'invalid_format'
            );
        }

        if (! $this->publicKeyConfigured()) {
            Log::critical('LicenseService: License Server public key (App\Support\LicenseKey) is still the placeholder — the real key was not set before packaging.');
            return LicenseResult::failure(
                translate('License verification is not configured on this build. Please contact the author/support.'),
                'public_key_missing'
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
                    'envato_error' => translate('The marketplace is temporarily unavailable. Please try again in a few minutes.'),
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
                $this->storeSignedProof($rawPayload, $data['signature']);

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

    /**
     * The License Server update endpoints, derived from the verify URL so there is
     * a single source for the server host + embedded public key.
     */
    public function verifyEndpoint(): string
    {
        return self::LICENSE_SERVER_URL;
    }

    /** Public wrapper so sibling services can store the exact signed payload bytes. */
    public function extractSignedPayload(string $responseBody): ?string
    {
        return $this->extractRawPayload($responseBody);
    }

    public function updateEndpoint(): string
    {
        return str_replace('/verify', '/update', self::LICENSE_SERVER_URL);
    }

    public function downloadEndpoint(): string
    {
        return str_replace('/verify', '/update/download', self::LICENSE_SERVER_URL);
    }

    /**
     * Verify an Ed25519-signed License Server response and return its payload, or
     * null if the signature can't be trusted. Shared by license verification and
     * the update flow so both use the same embedded public key + byte contract.
     *
     * @param  array<string,mixed>  $data  the decoded JSON body ($response->json())
     * @return array<string,mixed>|null
     */
    public function verifySignedResponse(string $responseBody, array $data): ?array
    {
        if (! isset($data['signature'], $data['payload']) || ! $this->publicKeyConfigured()) {
            return null;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return null;
        }

        $rawPayload = $this->extractRawPayload($responseBody);
        if (! $rawPayload) {
            return null;
        }

        $signatureBytes = @base64_decode($data['signature']);
        $publicKeyBytes = @base64_decode($this->getPublicKey());

        if (! $signatureBytes || ! $publicKeyBytes
            || ! @sodium_crypto_sign_verify_detached($signatureBytes, $rawPayload, $publicKeyBytes)) {
            return null;
        }

        return $data['payload'];
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

        if (PurchaseCode::matchTestCode($purchaseCode) !== null) {
            settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
            settings_set('license_status', 'valid', 'string', 'license');
            settings_set('license_grace_started_at', null, 'string', 'license');
            Cache::forget('license.status');
            Log::info('License re-verified via TEST MODE');
            return true;
        }

        if (! $this->publicKeyConfigured()) {
            // Key not configured on this build — cannot verify signatures. Treat as a
            // transient condition (never punish the buyer / start a grace period).
            Log::critical('LicenseService: Re-verify skipped — license server public key not configured.');
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
                $this->checkOfflineDeadline();
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
                // Distinguish transient author/marketplace-side errors from a
                // definitive negative verdict. An Envato API outage returns a
                // signed valid:false with error=envato_error — that must NOT
                // start a grace period, or a legitimate buyer gets invalidated
                // whenever Envato is briefly down.
                $transientErrors = ['envato_error', 'api_error', 'network', 'connection_error'];
                if (in_array($payload['error'] ?? '', $transientErrors, true)) {
                    Log::warning('LicenseService: Re-verify returned a transient server error — keeping current status', [
                        'error' => $payload['error'] ?? null,
                    ]);
                    return false;
                }

                $this->startGracePeriod();
                return false;
            }

            // All good — reset grace
            settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
            settings_set('license_status', 'valid', 'string', 'license');
            settings_set('license_grace_started_at', null, 'string', 'license');
            $this->storeSignedProof($rawPayload, $data['signature']);

            Cache::forget('license.status');

            Log::info('LicenseService: Re-verify successful');
            return true;
        } catch (\Throwable $e) {
            // Network error — never punish for a transient issue, but DO enforce the
            // offline deadline so blocking the license server can't keep a nulled
            // copy alive forever.
            Log::warning('LicenseService: Re-verify connection error (transient)', [
                'error' => $e->getMessage(),
            ]);
            $this->checkOfflineDeadline();
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
            'license_signed_payload',
            'license_signature',
        ];

        foreach ($keys as $key) {
            settings_set($key, null, 'string', 'license');
        }

        // Also disable subscriptions (a `features` toggle; group arg matches its registry group)
        settings_set('subscriptions_enabled', false, 'boolean', 'features');
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

    /**
     * Persist the server's SIGNED payload + signature so the authentic
     * `verified_at` can be re-checked offline later (tamper-evident anchor).
     */
    private function storeSignedProof(string $rawPayload, string $signatureB64): void
    {
        settings_set('license_signed_payload', $rawPayload, 'string', 'license');
        settings_set('license_signature', $signatureB64, 'string', 'license');
    }

    /**
     * Enforce the offline deadline. Reads the last genuinely server-confirmed
     * `verified_at` from the stored SIGNED payload — re-verifying the signature
     * offline so a DB-edited timestamp is rejected. If no signed confirmation has
     * happened within license.max_offline_days, the grace period starts. This is
     * what stops "block the license server → valid forever" nulling.
     */
    private function checkOfflineDeadline(): void
    {
        $maxDays = (int) config('license.max_offline_days', 14);
        if ($maxDays <= 0) {
            return; // deadline disabled
        }

        $payloadJson  = settings('license_signed_payload');
        $signatureB64 = settings('license_signature');

        // No stored signed proof (older activation) — fall back to the plain
        // last-success timestamp so the deadline still applies.
        if (blank($payloadJson) || blank($signatureB64)) {
            $lastSuccess = settings('license_verified_at');
            if (filled($lastSuccess)
                && abs(now()->diffInDays(\Illuminate\Support\Carbon::parse($lastSuccess))) > $maxDays) {
                Log::warning('LicenseService: Offline deadline exceeded (no signed proof) — starting grace');
                $this->startGracePeriod();
            }
            return;
        }

        // Re-verify the stored signature offline. If it fails, the stored proof was
        // tampered with (or the key changed) — do not trust the local timestamp.
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return;
        }

        $sig = @base64_decode($signatureB64);
        $pk  = @base64_decode($this->getPublicKey());

        if (! $sig || ! $pk || ! @sodium_crypto_sign_verify_detached($sig, $payloadJson, $pk)) {
            Log::warning('LicenseService: Stored license proof failed offline signature check — starting grace');
            $this->startGracePeriod();
            return;
        }

        $data = json_decode($payloadJson, true);
        $verifiedAt = $data['verified_at'] ?? null;
        if (blank($verifiedAt)) {
            return;
        }

        if (abs(now()->diffInDays(\Illuminate\Support\Carbon::parse($verifiedAt))) > $maxDays) {
            Log::warning('LicenseService: Offline deadline exceeded — no signed re-verification in :days days, starting grace', [
                'days' => $maxDays,
            ]);
            $this->startGracePeriod();
        }
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

        // Warn admins now — they have $graceHours to re-activate before the app is
        // blocked. Previously admins were only notified AFTER invalidation.
        $this->notifyGraceStarted($graceHours);
    }

    /**
     * Notify admins (in-app + email) that the license entered its grace period,
     * giving them time to act before enforcement kicks in.
     */
    private function notifyGraceStarted(int $graceHours): void
    {
        try {
            app(\App\Services\InAppNotificationService::class)->notifyAdmins([
                'title' => translate('License needs re-verification'),
                'message' => translate('Automatic license re-verification failed. Please re-activate your license within :hours hours to avoid interruption.', ['hours' => $graceHours]),
                'level' => 'warning',
                'category' => 'system',
            ], 'super-admin');

            $admins = \App\Models\Admin::where('is_active', true)->get();
            foreach ($admins as $admin) {
                $subject = translate('Action needed: license re-verification');
                $message = translate('Automatic license re-verification failed. Please re-activate your license within :hours hours in the admin panel to avoid the app being blocked.', ['hours' => $graceHours]);
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
                    'template_slug' => 'license_grace_started',
                    'recipient_email' => $admin->email,
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('LicenseService: Failed to notify admins of grace period start', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markInvalid(): void
    {
        settings_set('license_status', 'invalid', 'string', 'license');
        settings_set('subscriptions_enabled', false, 'boolean', 'features');
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
        return \App\Support\LicenseKey::PUBLIC_KEY;
    }

    /**
     * Whether a real License Server public key has been baked in (i.e. the
     * placeholder shipped in source was replaced before packaging).
     */
    private function publicKeyConfigured(): bool
    {
        return $this->getPublicKey() !== \App\Support\LicenseKey::PLACEHOLDER;
    }

    /**
     * Build mock test results for local development/testing.
     */
    private function buildTestResult(string $purchaseCode, int $type, bool $store = true): LicenseResult
    {
        $supportedUntil = now()->addYears(10)->toDateTimeString();

        if ($store) {
            settings_set('license_purchase_code', Crypt::encryptString($purchaseCode), 'encrypted', 'license');
            settings_set('license_type', $type, 'integer', 'license');
            settings_set('license_buyer', 'test-buyer', 'string', 'license');
            settings_set('license_purchased_at', now()->toDateString(), 'string', 'license');
            settings_set('license_supported_until', $supportedUntil, 'string', 'license');
            settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
            settings_set('license_domain', request()->getHost(), 'string', 'license');
            settings_set('license_status', 'valid', 'string', 'license');
            settings_set('license_grace_started_at', null, 'string', 'license');
            settings_set('license_is_test_mode', true, 'boolean', 'license');

            Cache::forget('license.status');
        }

        Log::info('License verified via TEST MODE', [
            'type' => $type,
        ]);

        return LicenseResult::success([
            'type' => $type,
            'buyer' => 'test-buyer',
            'purchase_date' => now()->toDateString(),
            'license' => $type === 2 ? 'Extended License' : 'Regular License',
            'supported_until' => $supportedUntil,
        ]);
    }
}
