<?php

/**
 * MakeAI — License Configuration
 *
 * License verification is handled entirely by the author's License Server
 * (see App\Services\LicenseService). The Envato API is never called from the
 * buyer app — the License Server holds the author token and talks to Envato.
 */

return [
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

    /*
    |--------------------------------------------------------------------------
    | Local Development Bypass
    |--------------------------------------------------------------------------
    | When true AND APP_ENV=local, license_verified() short-circuits to true so
    | developers can work without activating a license. Requires an explicit
    | opt-in so that setting APP_ENV=local alone (e.g. on a nulled production
    | copy) does NOT disable enforcement. Never enable in production.
    */
    'dev_bypass' => env('LICENSE_DEV_BYPASS', false),

    /*
    |--------------------------------------------------------------------------
    | Max Offline Days
    |--------------------------------------------------------------------------
    | Transient license-server failures never punish the buyer immediately — but
    | if NO genuine, cryptographically-signed re-verification has succeeded within
    | this many days (e.g. because someone blocked the license server to null the
    | app), the grace period starts. The authoritative "last verified" time is read
    | from the server's SIGNED payload, so it cannot be forged or DB-edited.
    | Set to 0 to disable this deadline (not recommended for production builds).
    */
    'max_offline_days' => env('LICENSE_MAX_OFFLINE_DAYS', 14),
];
