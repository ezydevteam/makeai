<?php

namespace App\Exceptions\AI;

use RuntimeException;

/**
 * Thrown when the platform-wide daily AI spending budget has been exceeded.
 */
class GlobalBudgetExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            translate('Platform AI budget limit reached for today. Please try again tomorrow.')
        );
    }
}
