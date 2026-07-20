<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiDriverInterface;
use App\Services\AI\Providers\PerplexityProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\Anthropic\AnthropicGateway;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\AnthropicProvider;
use Laravel\Ai\Providers\BedrockProvider;
use Laravel\Ai\Providers\CohereProvider;
use Laravel\Ai\Providers\DeepSeekProvider;
use Laravel\Ai\Providers\ElevenLabsProvider;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\GroqProvider;
use Laravel\Ai\Providers\JinaProvider;
use Laravel\Ai\Providers\MistralProvider;
use Laravel\Ai\Providers\OllamaProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\OpenRouterProvider;
use Laravel\Ai\Providers\VoyageAiProvider;
use Laravel\Ai\Providers\XaiProvider;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * LaravelAiDriver — wraps Laravel AI SDK providers behind AiDriverInterface.
 *
 * Each provider is resolved fresh (not through AiManager cache) so we can
 * inject dynamic API keys from our ai_keys DB table. This preserves our
 * round-robin key load balancing while using the SDK's HTTP infrastructure.
 *
 * Provider driver mapping:
 *   openai     → OpenAiProvider (native)
 *   anthropic  → AnthropicProvider (native)
 *   google     → GeminiProvider (native)
 *   deepseek   → DeepSeekProvider (native, OpenAI-compatible)
 *   xai        → XaiProvider (native, OpenAI-compatible)
 *   openrouter → OpenRouterProvider (native, OpenAI-compatible)
 *   groq       → GroqProvider (native, OpenAI-compatible)
 *   mistral    → MistralProvider (native)
 */
class LaravelAiDriver implements AiDriverInterface
{
    private string $apiKey;

    private string $baseUrl;

    private ?TextProvider $cachedProvider = null;

    private string $driverName;

    private static array $driverMap = [
        'openai' => 'openai',
        'anthropic' => 'anthropic',
        'google' => 'gemini',
        'gemini' => 'gemini',
        'deepseek' => 'deepseek',
        'xai' => 'xai',
        'openrouter' => 'openrouter',
        'groq' => 'groq',
        'mistral' => 'mistral',
        'ollama' => 'ollama',
        'bedrock' => 'bedrock',
        'cohere' => 'cohere',
        'eleven' => 'eleven',
        'jina' => 'jina',
        'voyageai' => 'voyageai',
        'perplexity' => 'perplexity',
    ];

    public function __construct(
        string $driverName,
        ?string $apiKey = null,
        ?string $baseUrl = null,
    ) {
        $this->driverName = strtolower($driverName);
        $this->apiKey = $apiKey ?? '';
        $this->baseUrl = $baseUrl ?? '';
    }

    // ─── AiDriverInterface Implementation ─────────────────────────

    public function getName(): string
    {
        return $this->driverName;
    }

    public function getModels(): array
    {
        $config = config("ai.providers.{$this->driverName}.models", []);

        return $config;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function setApiKey(string $key): self
    {
        $this->apiKey = $key;
        $this->cachedProvider = null;

        return $this;
    }

    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;
        $this->cachedProvider = null;

        return $this;
    }

    public function chatCompletion(array $messages, string $model, array $options = []): array
    {
        $this->injectConfigForSdk();

        [$instructions, $history, $prompt] = $this->splitForAgent($messages);

        $agent = new ThinkingAgent(
            instructions: $instructions,
            messages: $history,
            tools: [],
            maxTokensOption: isset($options['max_tokens']) ? (int) $options['max_tokens'] : null,
            temperatureOption: $this->temperatureFor($model, $options),
        );

        $response = $agent->prompt(
            prompt: $prompt,
            model: $model,
            provider: $this->driverName,
            timeout: $options['timeout'] ?? 120,
        );

        return [
            'content' => $response->text,
            'input_tokens' => $response->usage->promptTokens,
            'output_tokens' => $response->usage->completionTokens,
            'model' => $response->meta->model,
        ];
    }

    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator
    {
        $this->injectConfigForSdk();

        [$instructions, $history, $prompt] = $this->splitForAgent($messages);

        $agent = new ThinkingAgent(
            instructions: $instructions,
            messages: $history,
            tools: [],
            maxTokensOption: isset($options['max_tokens']) ? (int) $options['max_tokens'] : null,
            temperatureOption: $this->temperatureFor($model, $options),
        );

        $stream = $agent->stream(
            prompt: $prompt,
            model: $model,
            provider: $this->driverName,
            timeout: $options['timeout'] ?? 120,
        );

        $tokens = [];
        $reasoningTokens = [];
        $usage = null;
        $isReasoning = false;

        foreach ($stream as $event) {
            if ($event instanceof ReasoningStart) {
                $isReasoning = true;
                yield ['reasoning_start' => true];
            } elseif ($event instanceof ReasoningEnd) {
                $isReasoning = false;
                yield ['reasoning_end' => true];
            } elseif ($event instanceof ReasoningDelta) {
                $reasoningTokens[] = $event->delta;
                yield ['reasoning' => $event->delta];
            } elseif ($event instanceof TextDelta) {
                $tokens[] = $event->delta;
                yield $event->delta;
            } elseif ($event instanceof StreamEnd) {
                $usage = [
                    'input_tokens' => $event->usage->promptTokens,
                    'output_tokens' => $event->usage->completionTokens,
                    'reasoning_tokens' => $event->usage->reasoningTokens,
                    'model' => $model,
                    'content' => implode('', $tokens),
                ];
            } elseif ($event instanceof StreamError) {
                throw new \RuntimeException(
                    ($event->message ?? $event->type ?? 'Stream error from provider'),
                );
            }
        }

        // Fallback usage estimation
        if ($usage === null) {
            $fullContent = implode('', $tokens);
            $outputTokens = (int) ceil(strlen($fullContent) / 4);
            $inputText = collect($messages)->pluck('content')->implode(' ');
            $inputTokens = (int) ceil(strlen($inputText) / 4);

            $usage = [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'model' => $model,
                'content' => $fullContent,
            ];
        }

        yield $usage;
    }

    public function embedText(string $text): array
    {
        $provider = $this->resolveEmbeddingProvider();

        $response = $provider->embeddings([$text]);

        return [
            'vector' => $response->first(),
            'dimensions' => count($response->first()),
            'model' => $response->meta->model,
            'tokens_used' => $response->tokens,
        ];
    }

    public function embedBatch(array $texts): array
    {
        $provider = $this->resolveEmbeddingProvider();

        $response = $provider->embeddings($texts);

        return collect($response->embeddings)->map(fn (array $vector, int $i) => [
            'vector' => $vector,
            'dimensions' => count($vector),
            'model' => $response->meta->model,
            'tokens_used' => $response->tokens,
        ])->all();
    }

    /**
     * Resolve the temperature to send for a model, if any.
     *
     * OpenAI reasoning models (o1/o3/o4 family) reject non-default
     * temperature values, so no temperature is sent for those.
     */
    private function temperatureFor(string $model, array $options): ?float
    {
        if (! isset($options['temperature'])) {
            return null;
        }

        if (preg_match('/^o[134](-|$)/i', $model)) {
            return null;
        }

        return max(0.0, min(2.0, (float) $options['temperature']));
    }

    // ─── Config Injection (for SDK resolution) ───────────────────

    /**
     * Inject the dynamic DB-stored API key into config so the SDK's AiManager
     * can read it when resolving providers.
     */
    private function injectConfigForSdk(): void
    {
        if ($this->apiKey) {
            config()->set("ai.providers.{$this->driverName}.key", $this->apiKey);
        }
        if ($this->baseUrl) {
            config()->set("ai.providers.{$this->driverName}.url", $this->baseUrl);
        }
    }

    // ─── Provider Resolution ─────────────────────────────────────

    private function resolveTextProvider(): TextProvider
    {
        if ($this->cachedProvider instanceof TextProvider) {
            return $this->cachedProvider;
        }

        // Set the dynamic API key in config so the SDK can read it
        if ($this->apiKey !== '') {
            config()->set("ai.providers.{$this->driverName}.key", $this->apiKey);
        }
        if ($this->baseUrl) {
            config()->set("ai.providers.{$this->driverName}.url", $this->baseUrl);
        }

        $config = config("ai.providers.{$this->driverName}", []);
        $events = app(Dispatcher::class);

        $this->cachedProvider = $this->newProviderInstance($config, $events);

        if (! $this->cachedProvider instanceof TextProvider) {
            throw new \RuntimeException("Provider [{$this->driverName}] does not support text generation.");
        }

        return $this->cachedProvider;
    }

    private function resolveEmbeddingProvider(): EmbeddingProvider
    {
        if ($this->apiKey !== '') {
            config()->set("ai.providers.{$this->driverName}.key", $this->apiKey);
        }
        if ($this->baseUrl) {
            config()->set("ai.providers.{$this->driverName}.url", $this->baseUrl);
        }

        $config = config("ai.providers.{$this->driverName}", []);
        $events = app(Dispatcher::class);

        $provider = $this->newProviderInstance($config, $events);

        if (! $provider instanceof EmbeddingProvider) {
            throw new \RuntimeException("Provider [{$this->driverName}] does not support embeddings.");
        }

        return $provider;
    }

    /**
     * Create a fresh SDK provider instance (bypasses AiManager cache).
     */
    private function newProviderInstance(array $config, Dispatcher $events): object
    {
        $driver = $config['driver'] ?? $this->driverName;

        return match ($driver) {
            'openai' => new OpenAiProvider(new OpenAiGateway($events), $config, $events),
            'anthropic' => new AnthropicProvider(new AnthropicGateway($events), $config, $events),
            'gemini' => new GeminiProvider(new GeminiGateway($events), $config, $events),
            'deepseek' => new DeepSeekProvider($config, $events),
            'xai' => new XaiProvider($config, $events),
            'openrouter' => new OpenRouterProvider($config, $events),
            'groq' => new GroqProvider($config, $events),
            'mistral' => new MistralProvider($config, $events),
            'ollama' => new OllamaProvider($config, $events),
            'bedrock' => new BedrockProvider($config, $events),
            'cohere' => new CohereProvider($config, $events),
            'eleven' => new ElevenLabsProvider($config, $events),
            'jina' => new JinaProvider($config, $events),
            'voyageai' => new VoyageAiProvider($config, $events),
            'perplexity' => new PerplexityProvider(new OpenAiGateway($events), $config, $events),
            default => throw new \RuntimeException("Unsupported AI driver: {$driver}"),
        };
    }

    // ─── Message Conversion Helpers ───────────────────────────────

    /**
     * Split messages into instructions, conversation history, and the prompt.
     *
     * The SDK's agent.prompt() prepends the user's prompt as a new message,
     * so the last user message becomes the prompt, rest go into history.
     */
    private function splitForAgent(array $messages): array
    {
        $instructions = '';
        $history = [];
        $prompt = '';

        // Find the last user message index
        $lastUserIdx = null;
        foreach (array_reverse(array_keys($messages)) as $idx) {
            if ($messages[$idx]['role'] === 'user') {
                $lastUserIdx = $idx;
                break;
            }
        }

        foreach ($messages as $i => $msg) {
            if ($msg['role'] === 'system') {
                $instructions .= ($instructions ? "\n" : '').$msg['content'];
            } elseif ($msg['role'] === 'assistant') {
                $history[] = new AssistantMessage($msg['content']);
            } elseif ($msg['role'] === 'user') {
                // Support both string content and array content (multi-modal)
                $messageContent = $msg['content'];
                if ($i === $lastUserIdx) {
                    $prompt = $messageContent;
                } else {
                    $history[] = new UserMessage($messageContent);
                }
            }
        }

        return [$instructions, $history, $prompt];
    }
}
