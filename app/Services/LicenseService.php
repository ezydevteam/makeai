<?php

namespace App\Services;

use App\DataTransferObjects\LicenseResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Envato license types mapped from API strings to ints.
     */
    public const TYPE_REGULAR = 1;
    public const TYPE_EXTENDED = 2;

    /**
     * Settings keys stored for license data.
     */
    private const KEY_PURCHASE_CODE = 'license_key';
    private const KEY_TYPE = 'license_type';
    private const KEY_VERIFIED = 'license_verified';
    private const KEY_BUYER = 'license_buyer';
    private const KEY_PURCHASE_DATE = 'license_purchase_date';
    private const KEY_DOMAIN = 'license_domain';
    private const KEY_LAST_REVERIFY = 'license_last_reverify';
    private const KEY_GRACE_START = 'license_grace_start';

    /**
     * Consecutive re-verify failures before blocking the frontend
     * (grace period must also be expired).
     */
    private const MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * Validate a purchase code against the Envato Market API.
     */
    public function validate(string $purchaseCode): LicenseResult
    {
        $itemId = config('license.item_id');
        $apiUrl = config('license.api_url');
        $apiToken = config('license.api_token');

        if (blank($apiToken)) {
            Log::warning('LicenseService: ENVATO_API_TOKEN not configured.');

            return LicenseResult::failure(
                translate('API token not configured. Please set ENVATO_API_TOKEN in your .env file.'),
                'no_api_token'
            );
        }

        try {
            $response = Http::withToken($apiToken)
                ->timeout(15)
                ->get($apiUrl, ['code' => $purchaseCode]);

            if (! $response->successful()) {
                $status = $response->status();

                if ($status === 404) {
                    return LicenseResult::failure(
                        translate('Invalid purchase code. Please check and try again.'),
                        'invalid_code'
                    );
                }

                if ($status === 403) {
                    return LicenseResult::failure(
                        translate('API token does not have permission to verify this purchase.'),
                        'api_forbidden'
                    );
                }

                Log::error('LicenseService: Envato API returned status '.$status, [
                    'body' => $response->body(),
                ]);

                return LicenseResult::failure(
                    translate('Could not verify with Envato API at this time. Please try again later.'),
                    'api_error'
                );
            }

            $data = $response->json();

            if (empty($data['item']['id']) || empty($data['license'])) {
                return LicenseResult::failure(
                    translate('Invalid response from Envato API. Please try again.'),
                    'api_error'
                );
            }

            // Verify item ID matches our product
            if ($itemId && (string) $data['item']['id'] !== (string) $itemId) {
                return LicenseResult::failure(
                    translate('This purchase code does not belong to this product.'),
                    'item_mismatch'
                );
            }

            $licenseString = $data['license'];
            $type = $this->parseLicenseType($licenseString);

            return LicenseResult::success([
                'type' => $type,
                'buyer' => $data['buyer'] ?? translate('Unknown'),
                'purchase_date' => $data['sold_at'] ?? now()->toDateString(),
                'license' => $licenseString,
            ]);

        } catch (ConnectionException $e) {
            Log::error('LicenseService: Envato API connection failed', ['error' => $e->getMessage()]);

            return LicenseResult::failure(
                translate('Could not connect to Envato API. Please try again later.'),
                'connection_error'
            );
        }
    }

    /**
     * Full activation flow: validate purchase code, store results, mark verified.
     */
    public function activate(string $purchaseCode): LicenseResult
    {
        // Prevent re-activation if already verified (check raw setting, not the admin-bypassed helper)
        if ((bool) settings(self::KEY_VERIFIED, false)) {
            return LicenseResult::failure(
                translate('A license is already active. Deactivate it first before activating a new one.'),
                'already_verified'
            );
        }

        $result = $this->validate($purchaseCode);

        if (! $result->valid) {
            return $result;
        }

        $domainHash = $this->computeDomainHash($purchaseCode);

        settings_set(self::KEY_PURCHASE_CODE, $purchaseCode, 'encrypted', 'license');
        settings_set(self::KEY_TYPE, $result->type, 'integer', 'license');
        settings_set(self::KEY_VERIFIED, true, 'boolean', 'license');
        settings_set(self::KEY_BUYER, $result->buyer, 'string', 'license');
        settings_set(self::KEY_PURCHASE_DATE, $result->purchaseDate, 'string', 'license');
        settings_set(self::KEY_DOMAIN, $domainHash, 'encrypted', 'license');
        settings_set(self::KEY_LAST_REVERIFY, now()->toDateTimeString(), 'string', 'license');
        settings_set(self::KEY_GRACE_START, null, 'string', 'license');

        Log::info('LicenseService: License activated successfully', [
            'buyer' => $result->buyer,
            'type' => $result->type,
        ]);

        return $result;
    }

    /**
     * Deactivate the current license — wipe all stored license data.
     */
    public function deactivate(): void
    {
        $keys = [
            self::KEY_PURCHASE_CODE,
            self::KEY_TYPE,
            self::KEY_VERIFIED,
            self::KEY_BUYER,
            self::KEY_PURCHASE_DATE,
            self::KEY_DOMAIN,
            self::KEY_LAST_REVERIFY,
            self::KEY_GRACE_START,
        ];

        foreach ($keys as $key) {
            settings_set($key, null, 'string', 'license');
        }

        // Also disable subscriptions
        settings_set('subscriptions_enabled', false, 'boolean', 'license');

        Log::info('LicenseService: License deactivated');
    }

    /**
     * Re-verify the active license against the Envato API.
     *
     * Handles grace period tracking: after MAX_CONSECUTIVE_FAILURES,
     * the license is considered invalid and the frontend is blocked.
     */
    public function reverify(): bool
    {
        if (! license_verified()) {
            return false;
        }

        $purchaseCode = settings(self::KEY_PURCHASE_CODE);

        if (blank($purchaseCode)) {
            $this->deactivate();

            return false;
        }

        $result = $this->validate($purchaseCode);

        if ($result->valid) {
            // Reset grace tracking on success
            settings_set(self::KEY_LAST_REVERIFY, now()->toDateTimeString(), 'string', 'license');
            settings_set(self::KEY_GRACE_START, null, 'string', 'license');

            Log::info('LicenseService: Re-verify successful');

            return true;
        }

        // Verification failed — start or continue grace period
        return $this->handleReverifyFailure();
    }

    /**
     * Get the current license status details for the admin page.
     */
    public function getStatus(): array
    {
        $verified = license_verified();
        $graceStart = settings(self::KEY_GRACE_START);
        $inGracePeriod = filled($graceStart);
        $graceExpired = false;
        $graceHoursRemaining = 0;

        if ($inGracePeriod) {
            $graceHours = config('license.grace_period', 72);
            $startedAt = \Illuminate\Support\Carbon::parse($graceStart);
            $expiresAt = $startedAt->copy()->addHours($graceHours);

            if (now()->greaterThan($expiresAt)) {
                $graceExpired = true;
            } else {
                $graceHoursRemaining = max(0, (int) now()->diffInHours($expiresAt, false));
            }
        }

        return [
            'verified' => $verified,
            'type' => get_license_type(),
            'type_label' => is_extended_license() ? translate('Extended License') : translate('Regular License'),
            'buyer' => get_license_buyer(),
            'purchase_date' => settings(self::KEY_PURCHASE_DATE, ''),
            'last_reverify' => settings(self::KEY_LAST_REVERIFY, ''),
            'domain_ok' => $this->checkDomain(),
            'in_grace_period' => $inGracePeriod && ! $graceExpired,
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
        $purchaseCode = settings(self::KEY_PURCHASE_CODE);
        $storedHash = settings(self::KEY_DOMAIN);

        if (blank($purchaseCode) || blank($storedHash)) {
            return false;
        }

        return hash_equals($storedHash, $this->computeDomainHash($purchaseCode));
    }

    /**
     * Compute a domain-binding hash for the current app URL and purchase code.
     */
    private function computeDomainHash(string $purchaseCode): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return hash('sha256', $appUrl.$purchaseCode);
    }

    /**
     * Parse the Envato license string into a type constant.
     */
    private function parseLicenseType(string $license): int
    {
        if (str_contains(strtolower($license), 'extended')) {
            return self::TYPE_EXTENDED;
        }

        return self::TYPE_REGULAR;
    }

    /**
     * Handle a failed re-verify attempt: track grace period, block if expired.
     */
    private function handleReverifyFailure(): bool
    {
        $graceStart = settings(self::KEY_GRACE_START);
        $graceHours = config('license.grace_period', 72);

        if (blank($graceStart)) {
            // First failure — start the grace period
            settings_set(self::KEY_GRACE_START, now()->toDateTimeString(), 'string', 'license');

            Log::warning('LicenseService: Re-verify failed. Grace period started ('.$graceHours.'h).');

            return true; // Still considered valid during grace period
        }

        $startedAt = \Illuminate\Support\Carbon::parse($graceStart);
        $expiresAt = $startedAt->copy()->addHours($graceHours);

        if (now()->greaterThan($expiresAt)) {
            // Grace period expired — BLOCK THE ENTIRE FRONTEND
            settings_set(self::KEY_VERIFIED, false, 'boolean', 'license');
            settings_set('subscriptions_enabled', false, 'boolean', 'license');

            Log::critical('LicenseService: Grace period expired. License deactivated — frontend blocked.');

            return false;
        }

        Log::warning('LicenseService: Re-verify failed. Grace period active, '.max(0, (int) now()->diffInHours($expiresAt, false)).'h remaining.');

        return true; // Still within grace period
    }
}
