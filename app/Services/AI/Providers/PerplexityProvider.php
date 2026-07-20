<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\OpenAiProvider;

class PerplexityProvider extends OpenAiProvider
{
    public function __construct(OpenAiGateway $gateway, array $config, $events)
    {
        parent::__construct($gateway, $config, $events);
    }
}
