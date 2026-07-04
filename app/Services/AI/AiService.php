<?php

namespace App\Services\AI;

use App\DTO\CompletionResponse;
use App\DTO\EmbeddingResult;
use App\DTO\RagResult;
use App\Exceptions\AI\IntegrationNotConfiguredException;
use App\Jobs\RecordGenerationHistoryJob;
use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiTool;
use App\Models\User;
use App\Services\AI\Agents\AgentService;
use App\Services\AI\Rag\DocumentIngestionService;
use App\Services\AI\Rag\KnowledgeBaseSearchService;
use Illuminate\Support\Str;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Files\TranscribableAudio;
use Throwable;

/**
 * AiService — the main entry point for all AI operations.
 *
 * All controllers call this. This orchestrates TokenGuard → Provider → UsageLog.
 * Backed by Laravel AI SDK (laravel/ai) via LaravelAiDriver.
 *
 * Ref: AI_SaaS_Master_Prompt Part 14C.1
 */
class AiService
{
    public function __construct(
        private PromptBuilder $promptBuilder,
    ) {}

    /**
     * Execute a callable with automatic API key failover.
     *
     * On retryable errors (429, 5xx, timeouts), marks the key as failed
     * and rotates to the next available key for the same provider.
     */
    private function executeWithFailover(
        callable $callable,
        User $user,
        string $providerName,
        string $modelName,
        string $toolType,
        array $context = []
    ): mixed {
        $currentKeyId = null;
        $adapter = ProviderRegistry::resolveWithFailover($providerName, $currentKeyId);

        if (! $adapter) {
            throw new IntegrationNotConfiguredException($providerName);
        }

        $lastException = null;
        $triedKeyIds = $currentKeyId ? [$currentKeyId] : [];

        while ($adapter) {
            try {
                return $callable($adapter, $currentKeyId);
            } catch (Throwable $e) {
                $lastException = $e;

                if (! $this->isRetryableError($e)) {
                    TokenGuard::recordFailure($user, $providerName, $modelName, $toolType, 0, 0,
                        array_merge($context, ['error' => $e->getMessage(), 'failover' => false])
                    );
                    throw $e;
                }

                $adapter = ProviderRegistry::markFailedAndRotate($providerName, $currentKeyId, $currentKeyId, $triedKeyIds);

                if ($adapter && $currentKeyId) {
                    $triedKeyIds[] = $currentKeyId;
                }
            }
        }

        TokenGuard::recordFailure($user, $providerName, $modelName, $toolType, 0, 0,
            array_merge($context, [
                'error' => $lastException->getMessage(),
                'failover' => true,
                'all_keys_exhausted' => true,
            ])
        );

        throw new IntegrationNotConfiguredException($providerName, $lastException);
    }

    /**
     * Determine if an error is retryable (warrants trying a different API key).
     */
    private function isRetryableError(Throwable $e): bool
    {
        return AiErrors::isRetryable($e);
    }

    // ─── Core: Text Completions ───────────────────────────────────

    /**
     * Run a chat completion (stateless — no history) with automatic key failover.
     */
    public function complete(
        User $user,
        string $prompt,
        ?string $systemPrompt = null,
        ?string $provider = null,
        ?string $model = null,
        array $options = [],
        string $toolSlug = 'direct'
    ): CompletionResponse {
        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');

        try {
            // Pre-flight failures are credit/account problems — a different
            // provider cannot fix them, so no fallback here.
            TokenGuard::before($user, null, $modelName);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'chat', 0, 0, [
                'preflight_error' => class_basename($e),
                'tool_slug' => $toolSlug,
            ]);
            throw $e;
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            return $this->executeWithFailover(function ($adapter) use ($user, $providerName, $modelName, $messages, $options, $systemPrompt, $prompt, $toolSlug) {
                $result = $adapter->chatCompletion($messages, $modelName, $options);

                TokenGuard::after(
                    $user,
                    $result['input_tokens'],
                    $result['output_tokens'],
                    $result['model'],
                    $providerName,
                    'chat',
                    ['tool_slug' => $toolSlug]
                );

                RecordGenerationHistoryJob::dispatch($user, [
                    'tool_slug' => $toolSlug,
                    'prompt_system' => $systemPrompt ?? '',
                    'prompt_user' => $prompt,
                    'field_values' => [],
                    'model' => $result['model'],
                    'provider' => $providerName,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 2000,
                    'output_preview' => Str::limit($result['content'], 500),
                    'tokens_input' => $result['input_tokens'],
                    'tokens_output' => $result['output_tokens'],
                ])->onQueue('ai');

                return new CompletionResponse(
                    content: $result['content'],
                    inputTokens: $result['input_tokens'],
                    outputTokens: $result['output_tokens'],
                    model: $result['model'],
                );
            }, $user, $providerName, $modelName, 'chat');
        } catch (Throwable $e) {
            // Fall back to the secondary provider only for errors it can fix
            // (provider not configured / rate limits / outages).
            $fallback = $this->resolveFallback($providerName);
            if ($fallback && ($e instanceof IntegrationNotConfiguredException || AiErrors::isRetryable($e))) {
                return $this->complete($user, $prompt, $systemPrompt, $fallback['provider'], $fallback['model'], $options, $toolSlug);
            }
            throw $e;
        }
    }

    private function resolveFallback(string $primaryProvider): ?array
    {
        $fbProvider = settings('fallback_ai_provider', '');
        $fbModel = settings('fallback_ai_model', '');

        if (empty($fbProvider) || empty($fbModel)) {
            return null;
        }

        if ($fbProvider === $primaryProvider) {
            return null;
        }

        return ['provider' => $fbProvider, 'model' => $fbModel];
    }

    /**
     * Run a template-based completion using the PromptBuilder.
     */
    public function runTemplate(
        User $user,
        AiTool $template,
        array $inputs,
        ?string $provider = null,
        ?string $model = null
    ): array {
        $completion = $this->promptBuilder->build($template, $inputs, $user);

        $finalModel = $model ?? $completion->model;
        $providerName = $provider ?? $completion->provider ?? settings('default_ai_provider', 'openai');

        try {
            TokenGuard::before($user, $template, $finalModel);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'preflight_error' => class_basename($e),
            ]);
            throw $e;
        }

        $adapter = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($providerName, $completion->apiKey)
            : ProviderRegistry::resolve($providerName);

        try {
            $result = $adapter->chatCompletion(
                $completion->toMessages(),
                $finalModel,
                $completion->toOptions()
            );

            TokenGuard::after(
                $user,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'template',
                ['template_slug' => $template->slug, 'personal_api_key' => (bool) $completion->apiKey],
                ! $completion->apiKey
            );

            RecordGenerationHistoryJob::dispatch($user, [
                'tool_slug' => $template->slug,
                'prompt_system' => $completion->systemPrompt,
                'prompt_user' => $completion->userMessage,
                'field_values' => $inputs,
                'model' => $result['model'],
                'provider' => $providerName,
                'temperature' => $completion->temperature,
                'max_tokens' => $completion->maxTokens,
                'output_preview' => Str::limit($result['content'], 500),
                'tokens_input' => $result['input_tokens'],
                'tokens_output' => $result['output_tokens'],
            ])->onQueue('ai');
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $template->incrementUsage();

        return array_merge($result, [
            'output_type' => $template->output_type ?? 'markdown',
        ]);
    }

    /**
     * Send a message in a chat conversation.
     */
    public function chat(
        User $user,
        AiChat $chat,
        string $message,
        ?string $provider = null,
        ?string $model = null
    ): AiChatMessage {
        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? $chat->model ?? settings('default_ai_model', 'gpt-4o-mini');

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'chat', 0, 0, [
                'chat_id' => $chat->id,
                'preflight_error' => class_basename($e),
            ]);
            throw $e;
        }

        $userMessage = $chat->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $messages = $chat->getMessagesForApi();

        $result = $this->executeWithFailover(function ($adapter, $keyId) use ($user, $providerName, $modelName, $chat, $messages) {
            $result = $adapter->chatCompletion($messages, $modelName);

            $assistantMessage = $chat->messages()->create([
                'role' => 'assistant',
                'content' => $result['content'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'metadata' => ['model' => $result['model'], 'provider' => $providerName],
            ]);

            TokenGuard::after(
                $user,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'chat',
                ['chat_id' => $chat->id]
            );

            return $assistantMessage;
        }, $user, $providerName, $modelName, 'chat', ['chat_id' => $chat->id]);

        if ($chat->messages()->count() <= 2 && $chat->title === 'New Chat') {
            $chat->update(['title' => Str::limit($message, 60)]);
        }

        if ($chat->model !== $modelName) {
            $chat->update(['model' => $modelName]);
        }

        return $result;
    }

    /**
     * Stream a template-based completion.
     */
    public function streamTemplate(
        User $user,
        AiTool $template,
        array $inputs,
        ?string $provider = null,
        ?string $model = null
    ): \Generator {
        $completion = $this->promptBuilder->build($template, $inputs, $user);
        $finalModel = $model ?? $completion->model;
        $providerName = $provider ?? $completion->provider ?? settings('default_ai_provider', 'openai');

        try {
            TokenGuard::before($user, $template, $finalModel);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'preflight_error' => class_basename($e),
            ]);
            throw $e;
        }

        $adapter = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($providerName, $completion->apiKey)
            : ProviderRegistry::resolve($providerName);

        try {
            $usageStats = null;
            foreach ($adapter->streamChatCompletion($completion->toMessages(), $finalModel, $completion->toOptions()) as $chunk) {
                if (is_string($chunk)) {
                    yield $chunk;
                } elseif (is_array($chunk)) {
                    if (isset($chunk['reasoning_start']) || isset($chunk['reasoning_end']) || isset($chunk['reasoning'])) {
                        yield $chunk;
                    } else {
                        $usageStats = $chunk;
                    }
                }
            }

            if ($usageStats) {
                $creditsUsed = TokenGuard::after(
                    $user,
                    $usageStats['input_tokens'],
                    $usageStats['output_tokens'],
                    $usageStats['model'] ?? $finalModel,
                    $providerName,
                    'template',
                    ['template_slug' => $template->slug, 'personal_api_key' => (bool) $completion->apiKey],
                    ! $completion->apiKey
                );

                $template->incrementUsage();

                yield [
                    'usage' => [
                        'input_tokens' => $usageStats['input_tokens'],
                        'output_tokens' => $usageStats['output_tokens'],
                        'reasoning_tokens' => $usageStats['reasoning_tokens'] ?? 0,
                        'credits_used' => $creditsUsed,
                    ],
                ];
            }
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─── 14C.1: Streaming (public entry) ─────────────────────────

    /**
     * Stream a simple prompt (no template).
     */
    public function stream(
        User $user,
        string $prompt,
        ?string $systemPrompt = null,
        ?string $provider = null,
        ?string $model = null,
    ): \Generator {
        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');
        $adapter = ProviderRegistry::resolve($providerName);

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'stream', 0, 0, [
                'preflight_error' => class_basename($e),
            ]);
            throw $e;
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $usageStats = null;
            foreach ($adapter->streamChatCompletion($messages, $modelName) as $chunk) {
                if (is_string($chunk)) {
                    yield $chunk;
                } elseif (is_array($chunk)) {
                    if (isset($chunk['reasoning_start']) || isset($chunk['reasoning_end']) || isset($chunk['reasoning'])) {
                        yield $chunk;
                    } else {
                        $usageStats = $chunk;
                    }
                }
            }

            if ($usageStats) {
                TokenGuard::after(
                    $user,
                    $usageStats['input_tokens'],
                    $usageStats['output_tokens'],
                    $usageStats['model'] ?? $modelName,
                    $providerName,
                    'stream',
                    ['tool_slug' => 'direct']
                );
            }
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'stream', 0, 0, [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─── 14C.1: Embeddings ───────────────────────────────────────

    /**
     * Generate an embedding vector for a single text.
     */
    public function embedText(
        string $text,
        ?string $provider = null,
        ?string $model = null,
    ): EmbeddingResult {
        $providerName = $provider
            ?? (settings('ai_embedding_provider')
            ?: settings('default_ai_provider')
            ?: config('ai.default_for_embeddings', 'openai'));

        $adapter = ProviderRegistry::resolve($providerName);

        try {
            TokenGuard::before(null, null, $model);

            $result = $adapter->embedText($text);

            TokenGuard::after(
                null,
                $result['tokens_used'],
                0,
                $result['model'],
                $providerName,
                'embedding',
                ['tool_slug' => 'embedding']
            );

            return new EmbeddingResult(
                vector: $result['vector'],
                dimensions: $result['dimensions'],
                model: $result['model'],
                tokensUsed: $result['tokens_used'],
            );
        } catch (Throwable $e) {
            TokenGuard::recordFailure(null, $providerName, $model ?? 'unknown', 'embedding', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'embedding',
            ]);
            throw $e;
        }
    }

    /**
     * Generate embedding vectors for multiple texts.
     *
     * @param  string[]  $texts
     * @return EmbeddingResult[]
     */
    public function embedBatch(
        array $texts,
        ?string $provider = null,
    ): array {
        $providerName = $provider
            ?? (settings('ai_embedding_provider')
            ?: settings('default_ai_provider')
            ?: config('ai.default_for_embeddings', 'openai'));

        $adapter = ProviderRegistry::resolve($providerName);

        try {
            TokenGuard::before(null, null, null);

            $results = $adapter->embedBatch($texts);

            // Calculate total tokens used across all embeddings
            $totalTokens = array_sum(array_column($results, 'tokens_used'));
            $modelName = $results[0]['model'] ?? 'unknown';
            
            TokenGuard::after(
                null,
                $totalTokens,
                0,
                $modelName,
                $providerName,
                'embedding',
                ['tool_slug' => 'embedding_batch', 'batch_size' => count($texts)]
            );

            return array_map(fn (array $r) => new EmbeddingResult(
                vector: $r['vector'],
                dimensions: $r['dimensions'],
                model: $r['model'],
                tokensUsed: $r['tokens_used'],
            ), $results);
        } catch (Throwable $e) {
            TokenGuard::recordFailure(null, $providerName, 'unknown', 'embedding', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'embedding_batch',
                'batch_size' => count($texts),
            ]);
            throw $e;
        }
    }

    // ─── 14C.1: Image Generation ─────────────────────────────────

    /**
     * Generate an image from a text prompt.
     */
    public function generateImage(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
        ?string $size = null,
        ?string $quality = null,
        ?User $user = null,
    ): array {
        $providerName = $provider ?? settings('ai_image_provider', config('ai.default_for_images', 'openai'));
        $provider = app(AiManager::class)->imageProvider($providerName);

        try {
            TokenGuard::before($user, null, $model);

            $response = $provider->image(
                prompt: $prompt,
                size: $size,
                quality: $quality,
                model: $model,
            );

            // Estimate tokens for image generation (rough estimate: 1000 tokens per image)
            $estimatedTokens = 1000 * count($response->images);

            TokenGuard::after(
                $user,
                $estimatedTokens,
                0,
                $response->meta->model,
                $providerName,
                'image_generation',
                ['tool_slug' => 'image_generator']
            );

            return [
                'images' => collect($response->images)->map(fn ($img) => [
                    'url' => $img->url,
                    'revised_prompt' => $img->revisedPrompt,
                ])->all(),
                'model' => $response->meta->model,
            ];
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $model ?? 'unknown', 'image_generation', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'image_generator',
            ]);
            throw $e;
        }
    }

    // ─── 14C.1: Audio Generation ─────────────────────────────────

    /**
     * Generate audio/speech from text.
     */
    public function generateAudio(
        string $text,
        string $voice = 'default-female',
        ?string $provider = null,
        ?string $model = null,
        ?User $user = null,
    ): array {
        $providerName = $provider ?? settings('ai_audio_provider', config('ai.default_for_audio', 'openai'));

        $provider = app(AiManager::class)->audioProvider($providerName);

        try {
            TokenGuard::before($user, null, $model);

            $response = $provider->audio(
                text: $text,
                voice: $voice,
                model: $model,
            );

            // Estimate tokens for audio generation (rough estimate: 1 token per 4 chars)
            $estimatedTokens = (int) ceil(mb_strlen($text) / 4);

            TokenGuard::after(
                $user,
                $estimatedTokens,
                0,
                $response->meta->model,
                $providerName,
                'audio_generation',
                ['tool_slug' => 'audio_generator']
            );

            return [
                'audio_url' => $response->audioUrl,
                'duration' => $response->duration,
                'model' => $response->meta->model,
            ];
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $model ?? 'unknown', 'audio_generation', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'audio_generator',
            ]);
            throw $e;
        }
    }

    /**
     * Transcribe audio to text.
     */
    public function generateTranscription(
        TranscribableAudio $audio,
        ?string $language = null,
        bool $diarize = false,
        ?string $provider = null,
        ?string $model = null,
        ?User $user = null,
    ): array {
        $providerName = $provider ?? settings('ai_transcription_provider', config('ai.default_for_transcription', 'openai'));

        $provider = app(AiManager::class)->transcriptionProvider($providerName);

        try {
            TokenGuard::before($user, null, $model);

            $response = $provider->transcribe(
                audio: $audio,
                language: $language,
                diarize: $diarize,
                model: $model,
            );

            // Estimate tokens for transcription (rough estimate: 1 token per 4 chars of output)
            $estimatedTokens = (int) ceil(mb_strlen($response->text) / 4);

            TokenGuard::after(
                $user,
                0,
                $estimatedTokens,
                $response->meta->model,
                $providerName,
                'transcription',
                ['tool_slug' => 'transcriber']
            );

            return [
                'text' => $response->text,
                'segments' => $response->segments,
                'language' => $response->language,
                'model' => $response->meta->model,
            ];
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $model ?? 'unknown', 'transcription', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'transcriber',
            ]);
            throw $e;
        }
    }

    // ─── 14C.1: Video Generation ─────────────────────────────────

    /**
     * Generate video from text prompt.
     *
     * Stub — video generation coming in Phase 12.
     */
    public function generateVideo(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        throw new \RuntimeException('Video generation is not yet implemented. Coming in Phase 12.');
    }

    // ─── 14C.1: RAG / Knowledge Base ─────────────────────────────

    /**
     * Search a knowledge base with natural language.
     */
    public function searchKnowledgeBase(
        string $query,
        string $knowledgeBaseId,
        int $topK = 5,
    ): RagResult {
        if (! app()->bound(KnowledgeBaseSearchService::class)) {
            throw new \RuntimeException('Knowledge base search is not yet available. Configure vector store first.');
        }

        return app(KnowledgeBaseSearchService::class)->search($query, $knowledgeBaseId, $topK);
    }

    /**
     * Answer a question using knowledge base context.
     */
    public function answerWithKnowledgeBase(
        string $query,
        string $knowledgeBaseId,
        ?User $user = null,
        int $topK = 5,
        ?string $provider = null,
        ?string $model = null,
    ): RagResult {
        if (! app()->bound(KnowledgeBaseSearchService::class)) {
            throw new \RuntimeException('Knowledge base search is not yet available. Configure vector store first.');
        }

        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');

        return app(KnowledgeBaseSearchService::class)->answer(
            query: $query,
            knowledgeBaseId: $knowledgeBaseId,
            provider: $providerName,
            model: $modelName,
            topK: $topK,
        );
    }

    /**
     * Ingest a document into the knowledge base.
     */
    public function ingestDocument(
        $file,
        int $userId,
        string $collectionId,
    ): array {
        if (! app()->bound(DocumentIngestionService::class)) {
            throw new \RuntimeException('Document ingestion is not yet available. Configure vector store first.');
        }

        return app(DocumentIngestionService::class)->ingest($file, $userId, $collectionId);
    }

    // ─── 14C.1: Agents ───────────────────────────────────────────

    /**
     * Run an AI agent with tool calling.
     */
    public function runAgent(
        string $agentName,
        string $userMessage,
        array $tools = [],
        ?string $provider = null,
        ?string $model = null,
    ): array {
        if (! app()->bound(AgentService::class)) {
            throw new \RuntimeException('Agent execution is not yet available. Coming in Phase 14.');
        }

        return app(AgentService::class)->run(
            agentName: $agentName,
            userMessage: $userMessage,
            tools: $tools,
            provider: $provider,
            model: $model,
        );
    }

    // ─── 14C.1: Document Operations ──────────────────────────────

    /**
     * Summarize a document using AI.
     */
    public function summarizeDocument(
        string $content,
        ?string $maxLength = null,
        ?string $provider = null,
        ?string $model = null,
    ): CompletionResponse {
        $prompt = 'Please provide a concise summary of the following content.';

        if ($maxLength) {
            $prompt .= " Keep the summary to approximately {$maxLength} words.";
        }

        $prompt .= "\n\n---\n{$content}\n---\n\nSummary:";

        $systemPrompt = 'You are an expert document summarizer. Provide clear, accurate summaries that capture the key points without losing important context.';

        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');
        $adapter = ProviderRegistry::resolve($providerName);

        try {
            TokenGuard::before(null, null, $modelName);
            
            $result = $adapter->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ], $modelName);

            TokenGuard::after(
                null,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'document_summary',
                ['tool_slug' => 'document_summarizer']
            );

            return new CompletionResponse(
                content: $result['content'],
                inputTokens: $result['input_tokens'],
                outputTokens: $result['output_tokens'],
                model: $result['model'],
            );
        } catch (Throwable $e) {
            TokenGuard::recordFailure(null, $providerName, $modelName, 'document_summary', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'document_summarizer',
            ]);
            throw new \RuntimeException("Document summarization failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract structured data from a document using AI.
     */
    public function extractStructuredData(
        string $content,
        array $schema,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT);
        $prompt = "Extract the following structured data from the content below. Respond ONLY with valid JSON matching this schema:\n\nSchema:\n{$schemaJson}\n\nContent:\n---\n{$content}\n---";

        $systemPrompt = 'You are a data extraction AI. Always respond with valid, parseable JSON only. Do not include any explanatory text outside the JSON.';

        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');
        $adapter = ProviderRegistry::resolve($providerName);

        try {
            TokenGuard::before(null, null, $modelName);
            
            $result = $adapter->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ], $modelName, ['temperature' => 0.1]);

            $extracted = json_decode($result['content'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON from the response
                preg_match('/\{.*\}/s', $result['content'], $matches);
                if (! empty($matches[0])) {
                    $extracted = json_decode($matches[0], true);
                }
            }

            TokenGuard::after(
                null,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'data_extraction',
                ['tool_slug' => 'data_extractor']
            );

            return [
                'data' => $extracted ?? $result['content'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'model' => $result['model'],
            ];
        } catch (Throwable $e) {
            TokenGuard::recordFailure(null, $providerName, $modelName, 'data_extraction', 0, 0, [
                'error' => $e->getMessage(),
                'tool_slug' => 'data_extractor',
            ]);
            throw new \RuntimeException("Data extraction failed: {$e->getMessage()}", 0, $e);
        }
    }
}
