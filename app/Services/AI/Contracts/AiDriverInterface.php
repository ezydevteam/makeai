<?php

namespace App\Services\AI\Contracts;

/**
 * AiDriverInterface — canonical contract for all AI provider drivers.
 *
 * All provider adapters conform to this. Backed by Laravel AI SDK (laravel/ai).
 */
interface AiDriverInterface
{
    // ─── Chat / Text Generation ──────────────────────────────────

    /**
     * Send a synchronous chat completion request.
     *
     * @param  array  $messages  [['role' => 'user', 'content' => 'Hello']]
     * @param  string  $model    Model identifier
     * @param  array   $options  Additional options (temperature, max_tokens, etc.)
     * @return array{content: string, input_tokens: int, output_tokens: int, model: string}
     */
    public function chatCompletion(array $messages, string $model, array $options = []): array;

    /**
     * Stream a chat completion, yielding tokens as they arrive.
     *
     * @param  array  $messages
     * @param  string $model
     * @param  array  $options
     * @return \Generator Yields string tokens, final yield is array with usage stats
     */
    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator;

    // ─── Metadata ─────────────────────────────────────────────────

    public function getName(): string;

    public function getModels(): array;

    public function isConfigured(): bool;

    public function setApiKey(string $key): self;

    public function setBaseUrl(string $url): self;

    // ─── Embeddings (14C.1) ──────────────────────────────────────

    /**
     * Generate an embedding vector for the given text.
     *
     * @return array{vector: array<float>, dimensions: int, tokens_used: int, model: string}
     */
    public function embedText(string $text): array;

    /**
     * Generate embedding vectors for multiple texts.
     *
     * @param  string[]  $texts
     * @return array<int, array{vector: array<float>, dimensions: int, tokens_used: int, model: string}>
     */
    public function embedBatch(array $texts): array;
}
