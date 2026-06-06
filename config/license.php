<?php

/**
 * MakeAI — License Configuration
 *
 * Envato marketplace license verification settings.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Envato Item ID
    |--------------------------------------------------------------------------
    | Your CodeCanyon item ID for API verification.
    */
    'item_id' => env('ENVATO_ITEM_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Envato API
    |--------------------------------------------------------------------------
    */
    'api_url' => 'https://api.envato.com/v3/market/author/sale',
    'api_token' => env('ENVATO_API_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Re-verification Interval (days)
    |--------------------------------------------------------------------------
    */
    'reverify_interval' => 7,

    /*
    |--------------------------------------------------------------------------
    | Grace Period (hours)
    |--------------------------------------------------------------------------
    | After verification fails, frontend stays active for this long.
    */
    'grace_period' => 72,

    /*
    |--------------------------------------------------------------------------
    | Require License Verification
    |--------------------------------------------------------------------------
    | When true, the LicenseMiddleware blocks all admin/api/frontend routes
    | unless a valid license is active. Set to false to bypass (emergency use only).
    */
    'require_verified' => env('LICENSE_REQUIRE_VERIFIED', true),
];
