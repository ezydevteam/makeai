<?php

namespace App\Support;

/**
 * SINGLE source of truth for the License Server's Ed25519 public key.
 *
 * It is a compiled-in class constant (NOT settings/DB/.env) so a nuller can't swap
 * it. Both LicenseService (core) and AddonLicenseService reference this — there is
 * exactly ONE place to set the key, so they can never diverge.
 *
 * PUBLIC_KEY below is the real key, set from the License Server on 2026-07-19. It
 * pairs with LICENSE_SIGNING_PRIVATE_KEY in that server's .env — the two are
 * generated together by `php artisan license:keys` and are useless apart.
 *
 * ⚠️ NEVER ROTATE THIS CASUALLY. Every copy of MakeAI already sold verifies
 * against the key compiled into its own build. Regenerating the server keypair
 * invalidates all of them at once, and each buyer stays broken until they install
 * an update carrying the new key. If it must be rotated, ship the patch release
 * FIRST and only then switch the server over.
 *
 * PLACEHOLDER stays as it is. It is the sentinel LicenseService,
 * AddonLicenseService and ThemeLicenseService compare against to detect a build
 * packaged without a real key, and the release build refuses to run while the two
 * are equal.
 */
final class LicenseKey
{
    /** Pairs with the private key on the License Server. base64, 32 raw bytes. */
    public const PUBLIC_KEY = 'DxCtRYbyLRMK6uT19g4HvHImOqWvXY2xLPBwqoQV91Y=';

    /** Sentinel used to detect an un-configured build. Do NOT change. */
    public const PLACEHOLDER = 'MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=';
}
