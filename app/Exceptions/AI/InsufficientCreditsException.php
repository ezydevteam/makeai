<?php

namespace App\Exceptions\AI;

use RuntimeException;

/**
 * Thrown when user's credit balance is too low for the estimated request cost.
 */
class InsufficientCreditsException extends RuntimeException
{
    public readonly float $balance;

    public readonly float $estimatedCost;

    public function __construct(float $balance, float $estimatedCost)
    {
        $this->balance = $balance;
        $this->estimatedCost = $estimatedCost;

        parent::__construct(
            translate('Insufficient credits. You have :balance credits but this request requires ~:cost credits.', [
                'balance' => number_format($balance, 2),
                'cost' => number_format($estimatedCost, 2),
            ])
        );
    }
}
