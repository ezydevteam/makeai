<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiDriverFactory;
use App\Services\AI\Contracts\AiDriverInterface;

/**
 * The production factory: every provider is backed by the Laravel AI SDK driver.
 */
class LaravelAiDriverFactory implements AiDriverFactory
{
    public function make(string $driverName, ?string $apiKey = null): AiDriverInterface
    {
        return new LaravelAiDriver($driverName, $apiKey);
    }
}
