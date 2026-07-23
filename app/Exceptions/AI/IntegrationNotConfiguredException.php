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

        // Deliberately generic — the specific provider name still travels on the
        // $integration property (and the JSON payload / logs) for admins, but the
        // user-facing message avoids naming a provider they don't recognise.
        parent::__construct(
            translate('The AI provider may not be configured yet.'),
            0,
            $previous
        );
    }
}
