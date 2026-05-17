<?php

namespace App\Exceptions\AI;

use RuntimeException;

/**
 * Thrown when user hits their daily or monthly credit limit.
 */
class CreditLimitException extends RuntimeException
{
    public readonly string $limitType;

    public function __construct(string $limitType = 'daily')
    {
        $this->limitType = $limitType;
        $message = $limitType === 'daily'
            ? translate('Daily credit limit reached. Please try again tomorrow.')
            : translate('Monthly credit limit reached. Please wait until next month or upgrade your plan.');

        parent::__construct($message);
    }
}
