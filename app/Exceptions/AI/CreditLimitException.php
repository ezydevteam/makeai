<?php

namespace App\Exceptions\AI;

use RuntimeException;

/**
 * Thrown when a caller hits a credit ceiling: their account's daily or monthly allowance,
 * or the per-IP daily allowance that applies to anonymous visitors (and, on a demo, to
 * everyone signed in as the shared account).
 */
class CreditLimitException extends RuntimeException
{
    public readonly string $limitType;

    public readonly float $remaining;

    public function __construct(string $limitType = 'daily', float $remaining = 0)
    {
        $this->limitType = $limitType;
        $this->remaining = $remaining;

        // 'guest' previously fell through to the monthly branch, so an anonymous visitor who
        // spent their per-IP daily allowance was told to "wait until next month or upgrade
        // your plan" — wrong on the period, and advice they cannot act on without an account.
        $message = match ($limitType) {
            'daily' => translate('Daily credit limit reached. Please try again tomorrow.'),
            'guest' => translate('You have reached today\'s limit for this connection. Please try again tomorrow.'),
            default => translate('Monthly credit limit reached. Please wait until next month or upgrade your plan.'),
        };

        parent::__construct($message);
    }
}
