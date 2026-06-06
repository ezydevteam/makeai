<?php

namespace App\Services\AI\Drivers;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;

/**
 * ThinkingAgent extends AnonymousAgent with HasProviderOptions so
 * Gemini's thinkingConfig can be passed through the SDK to enable
 * chain-of-thought reasoning visibility.
 *
 * Gemini models that support thinking:
 * - gemini-2.5-pro, gemini-2.5-flash
 * - gemini-2.0-flash (-thinking variant)
 */
class ThinkingAgent extends AnonymousAgent implements HasProviderOptions
{
    public function providerOptions(Lab|string $provider): array
    {
        $p = $provider instanceof Lab ? $provider->value : $provider;

        if ($p !== 'gemini') {
            return [];
        }

        return [
            'thinkingConfig' => [
                'thinkingBudget' => 1024,
            ],
        ];
    }
}
