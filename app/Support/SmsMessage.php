<?php

namespace App\Support;

/**
 * Builds the final SMS body from a message plus an optional call-to-action, and
 * estimates how many billable segments it costs.
 *
 * Used by the composer preview, the pre-send estimate and the send job so the three
 * can never disagree about what actually goes out (or what it costs).
 */
class SmsMessage
{
    /** Characters that occupy two positions in the GSM-7 alphabet. */
    private const GSM7_EXTENDED = ['^', '{', '}', '\\', '[', ']', '~', '|', '€'];

    private const GSM7_BASE = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        ."¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /**
     * Compose the outgoing body.
     *
     * - no URL              → the message alone
     * - URL, no label       → message + the bare link
     * - URL + label         → message + "<label>: <link>"
     */
    public static function compose(string $message, ?string $actionUrl = null, ?string $actionLabel = null): string
    {
        $body = trim($message);
        $url = trim((string) $actionUrl);

        if ($url === '') {
            return $body;
        }

        $label = trim((string) $actionLabel);
        $cta = $label === '' ? $url : $label.': '.$url;

        return $body === '' ? $cta : $body."\n\n".$cta;
    }

    /**
     * Whether the body fits the GSM-7 alphabet. Anything outside it (emoji, most
     * non-Latin scripts) forces UCS-2, which cuts the per-segment budget by more
     * than half — the single biggest driver of unexpected SMS cost.
     */
    public static function isGsm7(string $body): bool
    {
        foreach (preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (in_array($char, self::GSM7_EXTENDED, true)) {
                continue;
            }

            if (mb_strpos(self::GSM7_BASE, $char) === false) {
                return false;
            }
        }

        return true;
    }

    /** Billable length: GSM-7 extended characters count as two. */
    public static function length(string $body): int
    {
        $chars = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! self::isGsm7($body)) {
            return count($chars);
        }

        $length = 0;
        foreach ($chars as $char) {
            $length += in_array($char, self::GSM7_EXTENDED, true) ? 2 : 1;
        }

        return $length;
    }

    /**
     * Number of billable segments. Concatenated (multi-part) messages carry a header,
     * so the per-segment budget drops once the body no longer fits a single segment.
     */
    public static function segments(string $body): int
    {
        $length = self::length($body);
        if ($length === 0) {
            return 0;
        }

        $gsm7 = self::isGsm7($body);
        $single = $gsm7 ? 160 : 70;
        $multi = $gsm7 ? 153 : 67;

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    /**
     * @return array{body: string, length: int, segments: int, encoding: string}
     */
    public static function summary(string $message, ?string $actionUrl = null, ?string $actionLabel = null): array
    {
        $body = self::compose($message, $actionUrl, $actionLabel);

        return [
            'body' => $body,
            'length' => self::length($body),
            'segments' => self::segments($body),
            'encoding' => self::isGsm7($body) ? 'GSM-7' : 'UCS-2',
        ];
    }
}
