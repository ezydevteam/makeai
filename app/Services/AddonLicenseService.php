<?php

namespace App\Services;

use App\DataTransferObjects\LicenseResult;
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

    /**
     * Verify a purchase code against the Envato API for a specific addon.
     */
    public function verify(string $addonSlug, string $purchaseCode, int $expectedItemId): LicenseResult
    {
        // 1. Validate format before any API call
        if (! preg_match(self::PURCHASE_CODE_PATTERN, $purchaseCode)) {
            return LicenseResult::failure(
                translate('Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
                'invalid_format'
            );
        }

        // 2. Check API token is configured
        $apiToken = settings('envato_api_token') ?: config('license.api_token');

        if (blank($apiToken)) {
            return LicenseResult::failure(
                translate('Envato API token not configured. Please add it in Settings → Integrations first.'),
                'no_api_token'
            );
        }

        // 3. Call Envato API with retry + full validation
        $apiUrl = config('license.api_url', 'https://api.envato.com/v3/market/author/sale');
        return $this->verifyAgainstEnvato($addonSlug, $purchaseCode, $expectedItemId, $apiToken, $apiUrl);
    }

    private function verifyAgainstEnvato(
        string $addonSlug,
        string $purchaseCode,
        int $expectedItemId,
        string $apiToken,
        string $apiUrl
    ): LicenseResult {
        try {
            $response = Http::withToken($apiToken)
                ->timeout(15)
                ->retry(2, 1000)
                ->get($apiUrl, ['code' => $purchaseCode]);

            if (! $response->successful()) {
                $status = $response->status();

                if ($status === 404) {
                    return LicenseResult::failure(
                        translate('Purchase code not found. Please check and try again.'),
                        'not_found'
                    );
                }

                if ($status === 403) {
                    return LicenseResult::failure(
                        translate('API token does not have permission to verify purchases.'),
                        'api_forbidden'
                    );
                }

                return LicenseResult::failure(
                    translate('Could not reach Envato — try again.'),
                    'api_error'
                );
            }

            $data = $response->json();

            if (empty($data['item']['id']) || empty($data['license'])) {
                return LicenseResult::failure(
                    translate('Invalid response from Envato. Please try again.'),
                    'api_error'
                );
            }

            // Item ID mismatch — must match addon's envato_item_id, NOT core MakeAI's
            if ((int) $data['item']['id'] !== $expectedItemId) {
                return LicenseResult::failure(
                    translate('This purchase code belongs to a different item. Addon purchase codes are separate from your core MakeAI license.'),
                    'item_mismatch'
                );
            }

            // Check for refunded
            if (! empty($data['refunded']) && $data['refunded'] === true) {
                return LicenseResult::failure(
                    translate('This purchase was refunded and cannot be used for activation.'),
                    'refunded'
                );
            }

            // Determine license type
            $licenseString = $data['license'];
            $type = str_contains(strtolower($licenseString), 'extended')
                ? LicenseService::TYPE_EXTENDED
                : LicenseService::TYPE_REGULAR;

            // Supported until
            $supportedUntil = null;
            if (! empty($data['supported_until'])) {
                $supportedUntil = \Illuminate\Support\Carbon::parse($data['supported_until']);
            }

            // Upsert into addon_licenses
            AddonLicense::updateOrCreate(
                ['addon_slug' => $addonSlug],
                [
                    'purchase_code' => Crypt::encryptString($purchaseCode),
                    'envato_item_id' => $expectedItemId,
                    'license_type' => $type,
                    'buyer' => $data['buyer'] ?? translate('Unknown'),
                    'purchased_at' => $data['sold_at'] ?? now(),
                    'supported_until' => $supportedUntil,
                    'domain' => $this->computeDomainHash($purchaseCode),
                    'verified_at' => now(),
                    'status' => 'valid',
                    'grace_started_at' => null,
                ]
            );

            Cache::forget(self::CACHE_KEY_PREFIX . $addonSlug);

            Log::info('AddonLicenseService: License verified for addon', [
                'addon' => $addonSlug,
                'buyer' => $data['buyer'] ?? 'unknown',
                'type' => $type,
            ]);

            return LicenseResult::success([
                'type' => $type,
                'buyer' => $data['buyer'] ?? translate('Unknown'),
                'purchase_date' => $data['sold_at'] ?? now()->toDateString(),
                'license' => $licenseString,
            ]);
        } catch (ConnectionException $e) {
            Log::error('AddonLicenseService: Envato API connection failed', [
                'addon' => $addonSlug,
                'error' => $e->getMessage(),
            ]);

            return LicenseResult::failure(
                translate('Could not connect to Envato — please try again.'),
                'connection_error'
            );
        }
    }

    /**
     * Quick cached check for is_addon_active() and middleware.
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
            $purchaseCode = substr($decrypted, 0, 10) . '…' . substr($decrypted, -8);
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
     * Scheduled re-verification: re-checks with Envato API every 7 days.
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

        $apiToken = settings('envato_api_token') ?: config('license.api_token');
        if (blank($apiToken)) return;

        try {
            $response = Http::withToken($apiToken)
                ->timeout(15)
                ->retry(2, 1000)
                ->get(config('license.api_url'), ['code' => $purchaseCode]);

            if ($response->successful()) {
                $data = $response->json();

                // Check for refund/revocation
                if (! empty($data['refunded']) && $data['refunded'] === true) {
                    $this->startGracePeriod($addonSlug, $license);
                    return;
                }

                // Check item still matches
                if ((int) ($data['item']['id'] ?? 0) !== $license->envato_item_id) {
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
            }

            if ($response->status() === 404) {
                // Purchase code no longer valid — start grace
                $this->startGracePeriod($addonSlug, $license);
                return;
            }

            // Transient error — do nothing, retry next day
            Log::warning('AddonLicenseService: Re-verify API error (transient)', ['addon' => $addonSlug]);
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

        $purchaseCode = '';
        try {
            $purchaseCode = Crypt::decryptString($license->purchase_code);
        } catch (\Throwable) {
            return false;
        }

        return hash_equals($license->domain, $this->computeDomainHash($purchaseCode));
    }

    /**
     * Revoke stored license — only on addon delete.
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

    private function computeDomainHash(string $purchaseCode): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        return hash('sha256', $appUrl . $purchaseCode);
    }

    private function startGracePeriod(string $addonSlug, AddonLicense $license): void
    {
        $graceHours = (int) settings('addon_license_recheck_days', 7) * 24;

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

        Log::critical('AddonLicenseService: License marked invalid, addon deactivated', [
            'addon' => $addonSlug,
        ]);
    }

}
