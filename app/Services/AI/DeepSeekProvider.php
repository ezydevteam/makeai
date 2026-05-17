<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Models\AiModel;

/**
 * DeepSeek Provider Adapter.
 *
 * Uses OpenAI-compatible API format.
 */
class DeepSeekProvider extends OpenAiProvider
{
    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        parent::__construct(
            $apiKey ?? settings('deepseek_api_key', ''),
            $baseUrl ?? 'https://api.deepseek.com/v1'
        );
    }

    public function getName(): string
    {
        return 'deepseek';
    }

    public function getModels(): array
    {
        $models = AiModel::where('provider', 'deepseek')->active()->pluck('slug')->toArray();

        return ! empty($models) ? $models : ['deepseek-chat', 'deepseek-coder'];
    }

    public function isConfigured(): bool
    {
        return ! empty(settings('deepseek_api_key')) || AiKey::forProvider('deepseek')->available()->exists();
    }
}
