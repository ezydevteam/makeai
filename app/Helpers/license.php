<?php

/**
 * MakeAI — License Helper Functions
 *
 * Controls feature gating based on Envato license type.
 * Auto-loaded via composer.json.
 */
if (! function_exists('get_license_type')) {
    /**
     * Get the license type: 1 = Regular, 2 = Extended.
     */
    function get_license_type(): int
    {
        return (int) settings('license_type', 1);
    }
}

if (! function_exists('is_extended_license')) {
    /**
     * Check if running Extended License (allows charging end users).
     */
    function is_extended_license(): bool
    {
        return get_license_type() === 2;
    }
}

if (! function_exists('is_regular_license')) {
    /**
     * Check if running Regular License.
     */
    function is_regular_license(): bool
    {
        return get_license_type() === 1;
    }
}

if (! function_exists('isProAvailable')) {
    /**
     * Check if subscription/billing features should be available.
     * Requires: Extended License AND subscriptions enabled by admin.
     */
    function isProAvailable(): bool
    {
        return is_extended_license() && (bool) settings('subscriptions_enabled', false);
    }
}

if (! function_exists('credit_quota_mode')) {
    /**
     * Credit accounting mode.
     *
     * When billing is unavailable (Regular license, or Extended with subscriptions
     * off), credits are a RESETTING ALLOWANCE (guest per-IP daily + logged-in
     * daily/monthly) rather than a purchasable wallet — TokenGuard meters against the
     * quota and never blocks on an un-refillable balance. Returns true in quota mode.
     */
    function credit_quota_mode(): bool
    {
        return ! isProAvailable();
    }
}

if (! function_exists('license_verified')) {
    /**
     * Quick check if license is verified (from cache).
     */
    function license_verified(): bool
    {
        // Local-dev convenience bypass. Deliberately requires an EXPLICIT opt-in
        // flag (LICENSE_DEV_BYPASS) in addition to the local environment, so that
        // simply flipping APP_ENV=local on a production null does NOT disable
        // license enforcement.
        if (app()->environment('local') && config('license.dev_bypass', false)) {
            return true;
        }

        return \Illuminate\Support\Facades\Cache::remember('license.status', 3600, function () {
            $status = settings('license_status');
            if ($status === 'grace') {
                $graceStart = settings('license_grace_started_at');
                if (filled($graceStart)) {
                    $graceHours = config('license.grace_period', 72);
                    $startedAt = \Illuminate\Support\Carbon::parse($graceStart);
                    $expiresAt = $startedAt->copy()->addHours($graceHours);
                    if (now()->greaterThan($expiresAt)) {
                        return false;
                    }
                }
            }
            return $status === 'valid' || $status === 'grace';
        });
    }
}

if (! function_exists('get_license_buyer')) {
    /**
     * Get the Envato buyer username from stored license data.
     */
    function get_license_buyer(): string
    {
        return (string) settings('license_buyer', '');
    }
}

if (! function_exists('get_license_domain')) {
    /**
     * Get the domain hash stored at activation.
     */
    function get_license_domain(): string
    {
        return (string) settings('license_domain', '');
    }
}
