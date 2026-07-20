<?php

namespace App\Services\AI;

use Throwable;

/**
 * AiErrors — shared helpers for classifying and sanitizing AI provider errors.
 *
 * sanitize() maps raw provider/SDK exception messages to safe, translated,
 * user-facing messages so raw errors (which can contain endpoints, key
 * fragments or stack details) are never sent to the browser.
 *
 * isRetryable() decides whether an error warrants key rotation / provider
 * fallback (rate limits, quota, timeouts, 5xx) as opposed to errors that a
 * retry cannot fix (content policy, invalid input, authentication).
 */
class AiErrors
{
    /**
     * Determine if an error is retryable (warrants a different key/provider).
     */
    public static function isRetryable(Throwable $e): bool
    {
        $message = $e->getMessage();
        $code = (int) $e->getCode();

        if ($code === 429 || $code === 403 || ($code >= 500 && $code < 600)) {
            return true;
        }

        $retryable = ['rate limit', 'too many requests', 'quota exceeded', 'insufficient_quota',
            'timeout', 'timed out', 'connection refused', 'connection reset',
            'service unavailable', 'internal server error', 'bad gateway', 'gateway timeout',
            'overloaded'];

        foreach ($retryable as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map a raw provider error message to a safe, user-facing message.
     */
    public static function sanitize(string $message): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/rate.?limit|too many requests|quota exceeded|insufficient_?quota|credits? exhausted|429/i', $lower)) {
            return (string) translate('Rate limit reached. Please try again in a moment.');
        }
        if (preg_match('/content.?policy|content.?filter|safety filter|flagged by|inappropriate|violates.*policy/i', $lower)) {
            return (string) translate('Your request was flagged. Please modify your input and try again.');
        }
        if (preg_match('/context.?length|token.?limit|max.?tokens|too long|exceed.*token|exceed.*context/i', $lower)) {
            return (string) translate('Your input is too long. Please shorten it and try again.');
        }
        if (preg_match('/timeout|timed.?out/i', $lower)) {
            return (string) translate('Generation timed out. Try a shorter length or a different model.');
        }
        if (preg_match('/api.?key|invalid.?key|authentication|unauthorized|401|not.?authorized/i', $lower)) {
            return (string) translate('This AI provider is not configured. Please contact support.');
        }
        if (preg_match('/model.?not.?found|model.?unavailable|invalid.?model|unsupported.?model|no.?such.?model|deprecated/i', $lower)) {
            return (string) translate('The selected model is unavailable. Please try a different one.');
        }
        if (preg_match('/network.?error|connection.?refused|connection.?reset|econnrefused|econnreset|enotfound|etimedout/i', $lower)) {
            return (string) translate('Connection error. Please check your internet and try again.');
        }
        if (preg_match('/internal.?server.?error|bad.?gateway|gateway.?timeout|service.?unavailable|overloaded|500|502|503|504/i', $lower)) {
            return (string) translate('The AI service is temporarily unavailable. Please try again later.');
        }
        if (preg_match('/stream.?error|sse.?error/i', $lower)) {
            return (string) translate('Generation interrupted. Please try again.');
        }

        // Credit/limit exceptions raised by TokenGuard carry safe, translated
        // messages already — pass those through untouched.
        if (preg_match('/credit|upgrade|daily limit|monthly limit|budget/i', $lower)
            && ! preg_match('/exception|stack|trace|\.php|vendor\\\\|guzzle/i', $lower)) {
            return $message;
        }

        // Unknown errors: never leak the raw message to the client.
        return (string) translate('Something went wrong. Please try again or contact support.');
    }
}
