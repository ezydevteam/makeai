<?php

namespace App\Services\AI\Drivers;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;

/**
 * ThinkingAgent extends AnonymousAgent with:
 *
 * - maxTokens()/temperature() accessors — the SDK's TextGenerationOptions
 *   resolves these by method name, which is how per-request generation
 *   options (admin default_max_tokens, per-tool overrides, creativity)
 *   reach the provider gateways.
 * - HasProviderOptions so Gemini's thinkingConfig can be passed through
 *   to enable chain-of-thought reasoning visibility.
 *
 * Gemini models that support thinking:
 * - gemini-2.5-pro, gemini-2.5-flash
 * - gemini-2.0-flash (-thinking variant)
 */
class ThinkingAgent extends AnonymousAgent implements HasProviderOptions
{
    public function __construct(
        string $instructions = '',
        iterable $messages = [],
        iterable $tools = [],
        private readonly ?int $maxTokensOption = null,
        private readonly ?float $temperatureOption = null,
    ) {
        parent::__construct($instructions, $messages, $tools);
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokensOption;
    }

    public function temperature(): ?float
    {
        return $this->temperatureOption;
    }

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
