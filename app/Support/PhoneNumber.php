<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Normalizes and validates user-entered phone numbers against a paired ISO
 * alpha-2 region (the users.phone / users.phone_country split).
 */
class PhoneNumber
{
    /**
     * Normalize a raw entry to the national number (digits only) for the given
     * region. The dial code and formatting are stripped while significant
     * leading zeros are preserved, so the result re-parses against the same
     * region to the identical E.164 number.
     *
     * Returns null when the input carries no digits. Falls back to a plain
     * digits-only strip when the value can't be parsed for the region (invalid
     * region or unparseable input); validation then rejects it via isValid().
     */
    public static function nationalNumber(?string $raw, ?string $regionIso): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        $region = self::region($regionIso);
        if ($region === null) {
            return $digits;
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse($raw, $region);

            return preg_replace('/\D+/', '', $util->format($number, PhoneNumberFormat::NATIONAL));
        } catch (NumberParseException) {
            return $digits;
        }
    }

    /**
     * Whether a raw entry is a valid, dialable number for the region. Empty
     * input is treated as valid because the field is optional — callers enforce
     * presence separately (e.g. required_with).
     */
    public static function isValid(?string $raw, ?string $regionIso): bool
    {
        if (! is_string($raw) || preg_replace('/\D+/', '', $raw) === '') {
            return true;
        }

        $region = self::region($regionIso);
        if ($region === null) {
            return false;
        }

        try {
            $util = PhoneNumberUtil::getInstance();

            return $util->isValidNumber($util->parse($raw, $region));
        } catch (NumberParseException) {
            return false;
        }
    }

    /**
     * Format a stored national number + region as an E.164 string (e.g.
     * "+12025550173") for dialing/SMS. Returns null when the pair can't be
     * parsed into a phone number.
     */
    public static function e164(?string $national, ?string $regionIso): ?string
    {
        if (! is_string($national) || $national === '') {
            return null;
        }

        $region = self::region($regionIso);
        if ($region === null) {
            return null;
        }

        try {
            $util = PhoneNumberUtil::getInstance();

            return $util->format($util->parse($national, $region), PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            return null;
        }
    }

    private static function region(?string $regionIso): ?string
    {
        return is_string($regionIso) && strlen($regionIso) === 2
            ? strtoupper($regionIso)
            : null;
    }
}
