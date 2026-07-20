<?php

namespace App\Support;

/**
 * Single source of truth for the Envato purchase-code format.
 *
 * Referenced by LicenseService, AddonLicenseService, ActivateLicenseRequest, and
 * shared to the frontend as the `purchaseCodeFormat` Inertia prop (consumed by
 * resources/js/lib/purchaseCode.ts). Change the accepted format HERE ONLY — the
 * backend validators and the frontend input masks all derive from this class.
 */
final class PurchaseCode
{
    /** Strict Envato purchase-code (UUID) format. */
    public const UUID_PATTERN = '/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i';

    /** Relaxed format accepted ONLY in LICENSE_TEST_MODE (fake TEST-... codes). */
    public const TEST_PATTERN = '/^[A-Za-z0-9-]{1,50}$/';

    /**
     * SHA-256 hashes of the fake purchase codes recognized ONLY when
     * LICENSE_TEST_MODE is on. Used by BOTH core (LicenseService) and addon
     * (AddonLicenseService) activation, so they stay in sync. These codes never
     * reach the Envato API / License Server.
     *   TEST-LICENSE-0000-REGULAR  -> 1 (Regular)
     *   TEST-LICENSE-0000-EXTENDED -> 2 (Extended)
     */
    private const TEST_CODE_HASHES = [
        'a826f24604f457de266d6db6d77ee0ef4ab0c0fd2b0a00b918022b57de79f5a2' => 1,
        '3faeb2cbd383b0c0eb4d7ebcc72312ba1c2b0d3448afba0b653b9249b392cc5d' => 2,
    ];

    /** Whether developer test mode is active. */
    public static function testModeActive(): bool
    {
        return filter_var(config('app.license_test_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * If test mode is active and $code is a recognized fake code, return its
     * license type (1 = Regular, 2 = Extended). Otherwise null.
     */
    public static function matchTestCode(string $code): ?int
    {
        if (! self::testModeActive()) {
            return null;
        }

        $hash = hash('sha256', strtoupper(trim($code)));

        return self::TEST_CODE_HASHES[$hash] ?? null;
    }

    /**
     * The validation pattern to apply right now: relaxed in test mode, strict
     * (UUID) otherwise. Used by form-request validation that runs before verify().
     */
    public static function validationPattern(): string
    {
        return self::testModeActive() ? self::TEST_PATTERN : self::UUID_PATTERN;
    }

    /** Strict UUID validity check — used by the real license-server code path. */
    public static function isValidUuid(string $code): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, trim($code));
    }

    /**
     * Frontend input-mask config, consumed by resources/js/lib/purchaseCode.ts.
     * Exposes BOTH modes so each component picks: the license/install screens use
     * test-aware masking; addon activation is always strict UUID (addons have no
     * test-mode codes).
     *
     * @return array{testMode: bool, uuid: array{allowed: string, maxLength: int, case: string}, test: array{allowed: string, maxLength: int, case: string}}
     */
    public static function frontendConfig(): array
    {
        return [
            'testMode' => self::testModeActive(),
            'uuid' => ['allowed' => 'a-f0-9-', 'maxLength' => 36, 'case' => 'lower'],
            'test' => ['allowed' => 'A-Za-z0-9-', 'maxLength' => 50, 'case' => 'upper'],
        ];
    }
}
