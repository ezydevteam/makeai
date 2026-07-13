<?php

namespace App\Support;

/**
 * SINGLE source of truth for the License Server's Ed25519 public key.
 *
 * It is a compiled-in class constant (NOT settings/DB/.env) so a nuller can't swap
 * it. Both LicenseService (core) and AddonLicenseService reference this — there is
 * exactly ONE place to set the key, so they can never diverge.
 *
 * ⚠️ BEFORE PACKAGING FOR ENVATO: replace PUBLIC_KEY with the REAL base64-encoded
 * Ed25519 public key from your License Server. While it still equals PLACEHOLDER,
 * every real (non-test-mode) activation/update is refused with a clear
 * "not configured" message instead of a cryptic signature error.
 */
final class LicenseKey
{
    /** The real key pairs with the private key on the License Server. Replace before shipping. */
    public const PUBLIC_KEY = 'MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=';

    /** Sentinel used to detect an un-configured build. Do NOT change. */
    public const PLACEHOLDER = 'MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=';
}
