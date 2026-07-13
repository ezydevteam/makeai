<?php

namespace App\Support;

/**
 * Sanitises a request payload BEFORE it is written to admin_audit_logs.
 *
 * Two jobs, both security- and clarity-driven:
 *   1. Redact secrets — admin forms submit smtp_password, stripe_secret,
 *      openai_api_key, license keys, etc. Storing those verbatim would leak
 *      live credentials into a table any super-admin can read. We never want
 *      them on disk, so redaction happens at WRITE time, not display time.
 *   2. Shrink noise — strip framework/pagination keys and truncate huge blobs
 *      (base64 uploads, long HTML bodies) so the stored payload stays small and
 *      the review modal stays readable.
 */
class AuditLogRedactor
{
    /** Keys matching this pattern (case-insensitive) are always masked. */
    private const SENSITIVE_PATTERN = '/pass|secret|token|api[_-]?key|(?<!en)crypt|credential|private|license|signature|webhook|access[_-]?key|client[_-]?secret/i';

    /** Framework / navigation keys that carry no audit value. */
    private const NOISE_KEYS = ['_token', '_method', 'page', 'per_page', 'sort', 'direction'];

    private const MASK = '••••••';

    /** Strings longer than this are stored as a placeholder, not verbatim. */
    private const MAX_STRING = 300;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        return self::walk($payload);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function walk(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::NOISE_KEYS, true)) {
                continue;
            }

            if (is_string($key) && preg_match(self::SENSITIVE_PATTERN, $key)) {
                $clean[$key] = self::MASK;

                continue;
            }

            $clean[$key] = self::sanitizeValue($value);
        }

        return $clean;
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::walk($value);
        }

        if (is_string($value) && mb_strlen($value) > self::MAX_STRING) {
            return '['.__('long value').', '.number_format(mb_strlen($value)).' '.__('chars').']';
        }

        return $value;
    }
}
