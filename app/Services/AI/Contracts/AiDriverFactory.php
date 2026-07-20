<?php

namespace App\Services\AI\Contracts;

/**
 * Builds provider drivers for ProviderRegistry.
 *
 * ProviderRegistry is a static facade over driver construction, which left no seam
 * for a test (or an addon) to supply its own driver — every path ran `new
 * LaravelAiDriver`, so a streaming controller could not be exercised without calling
 * a real provider. Construction goes through the container instead.
 */
interface AiDriverFactory
{
    public function make(string $driverName, ?string $apiKey = null): AiDriverInterface;
}
