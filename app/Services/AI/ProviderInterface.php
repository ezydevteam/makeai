<?php

namespace App\Services\AI;

/**
 * ProviderInterface — contract for all AI provider adapters.
 *
 * Every provider (OpenAI, Anthropic, Google, etc.) MUST implement this.
 * This is the CAVEMAN LAW. No exceptions. 🦴
 */
interface ProviderInterface
{
    /**
     * Send a chat completion request (synchronous).
     *
     * @param  array  $messages  [['role' => 'user', 'content' => 'Hello']]
     * @param  string  $model  The model identifier
     * @param  array  $options  Additional options (temperature, max_tokens, etc.)
     * @return array{content: string, input_tokens: int, output_tokens: int, model: string}
     */
    public function chatCompletion(array $messages, string $model, array $options = []): array;

    /**
     * Send a streaming chat completion request.
     *
     * Yields tokens one by one as they arrive from the provider.
     *
     * @param  array  $messages  [['role' => 'user', 'content' => 'Hello']]
     * @param  string  $model  The model identifier
     * @param  array  $options  Additional options (temperature, max_tokens, etc.)
     * @return \Generator Yields string tokens, final yield is array with usage stats
     */
    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get supported models.
     */
    public function getModels(): array;

    /**
     * Check if provider is configured (has API key).
     */
    public function isConfigured(): bool;

    /**
     * Set the API key for the current request (used for load balancing).
     */
    public function setApiKey(string $key): self;

    /**
     * Set the base URL for the current request.
     */
    public function setBaseUrl(string $url): self;
}
