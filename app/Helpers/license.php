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
        if (app()->environment('local')) {
            return true;
        }
        return is_extended_license() && (bool) settings('subscriptions_enabled', false);
    }
}

if (! function_exists('license_verified')) {
    /**
     * Quick check if license is verified (from cache).
     */
    function license_verified(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        return (bool) settings('license_verified', false);
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
