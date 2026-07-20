<?php

namespace App\Exceptions\AI;

use RuntimeException;

/**
 * Thrown when a tool requires an external API integration that is not configured.
 */
class IntegrationNotConfiguredException extends RuntimeException
{
    public readonly string $integration;

    public function __construct(string $integration, ?\Throwable $previous = null)
    {
        $this->integration = $integration;

        parent::__construct(
            translate('The :integration integration is not configured. Please contact the administrator.', [
                'integration' => $integration,
            ]),
            0,
            $previous
        );
    }
}
