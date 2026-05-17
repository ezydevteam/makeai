<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Models\AiModel;

/**
 * OpenRouter Provider Adapter — universal proxy for 100+ models.
 *
 * Uses OpenAI-compatible API format via OpenRouter gateway.
 */
class OpenRouterProvider extends OpenAiProvider
{
    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        parent::__construct(
            $apiKey ?? settings('openrouter_api_key', ''),
            $baseUrl ?? 'https://openrouter.ai/api/v1'
        );
    }

    public function getName(): string
    {
        return 'openrouter';
    }

    public function getModels(): array
    {
        $models = AiModel::where('provider', 'openrouter')->active()->pluck('slug')->toArray();

        return ! empty($models) ? $models : ['openai/gpt-4o', 'anthropic/claude-sonnet-4-20250514'];
    }

    public function isConfigured(): bool
    {
        return ! empty(settings('openrouter_api_key')) || AiKey::forProvider('openrouter')->available()->exists();
    }
}
