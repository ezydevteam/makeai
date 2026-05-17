<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Models\AiModel;

/**
 * xAI Provider Adapter — Grok models.
 *
 * Uses OpenAI-compatible API format.
 */
class XaiProvider extends OpenAiProvider
{
    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        parent::__construct(
            $apiKey ?? settings('xai_api_key', ''),
            $baseUrl ?? 'https://api.x.ai/v1'
        );
    }

    public function getName(): string
    {
        return 'xai';
    }

    public function getModels(): array
    {
        $models = AiModel::where('provider', 'xai')->active()->pluck('slug')->toArray();

        return ! empty($models) ? $models : ['grok-3', 'grok-3-mini'];
    }

    public function isConfigured(): bool
    {
        return ! empty(settings('xai_api_key')) || AiKey::forProvider('xai')->available()->exists();
    }
}
