<?php

namespace App\DTO;

/**
 * CompletionRequest — typed data transfer object for AI completion calls.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.6
 */
class CompletionRequest
{
    public function __construct(
        public readonly string $model,
        public readonly string $systemPrompt,
        public readonly string $userMessage,
        public readonly int $maxTokens = 2000,
        public readonly float $temperature = 0.7,
        public readonly ?string $apiKey = null,
        public readonly ?string $provider = null,
    ) {}

    /**
     * Convert to API-ready messages array.
     */
    public function toMessages(): array
    {
        $messages = [];

        if (! empty($this->systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $this->userMessage];

        return $messages;
    }

    /**
     * Convert to options array for provider adapter.
     */
    public function toOptions(): array
    {
        return [
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];
    }
}
