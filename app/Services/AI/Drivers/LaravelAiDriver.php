<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiDriverInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\Anthropic\AnthropicGateway;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Providers\AnthropicProvider;
use Laravel\Ai\Providers\DeepSeekProvider;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\GroqProvider;
use Laravel\Ai\Providers\MistralProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\OpenRouterProvider;
use Laravel\Ai\Providers\XaiProvider;

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
        [$instructions, $history, $prompt] = $this->splitForAgent($messages);

        $agent = new \Laravel\Ai\AnonymousAgent(
            instructions: $instructions,
            messages: $history,
            tools: [],
        );

        $response = $agent->prompt(
            prompt: $prompt,
            model: $model,
            provider: $this->driverName,
            timeout: $options['timeout'] ?? 120,
        );

        return [
            'content' => $response->text,
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'model' => $response->meta->model,
        ];
    }

    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator
    {
        [$instructions, $history, $prompt] = $this->splitForAgent($messages);

        $agent = new AnonymousAgent(
            instructions: $instructions,
            messages: $history,
            tools: [],
        );

        $stream = $agent->stream(
            prompt: $prompt,
            model: $model,
            provider: $this->driverName,
            timeout: $options['timeout'] ?? 120,
        );

        $tokens = [];
        $usage = null;

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $tokens[] = $event->text;
                yield $event->text;
            }
            if ($event instanceof StreamEnd) {
                $usage = [
                    'input_tokens' => $event->usage->inputTokens,
                    'output_tokens' => $event->usage->outputTokens,
                    'model' => $model,
                    'content' => implode('', $tokens),
                ];
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

    // ─── Provider Resolution ─────────────────────────────────────

    private function resolveTextProvider(): TextProvider
    {
        if ($this->cachedProvider instanceof TextProvider) {
            return $this->cachedProvider;
        }

        // Set the dynamic API key in config so the SDK can read it
        config()->set("ai.providers.{$this->driverName}.key", $this->apiKey);
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
        config()->set("ai.providers.{$this->driverName}.key", $this->apiKey);
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
                $instructions .= ($instructions ? "\n" : '') . $msg['content'];
            } elseif ($msg['role'] === 'assistant') {
                $history[] = new AssistantMessage($msg['content']);
            } elseif ($msg['role'] === 'user') {
                if ($i === $lastUserIdx) {
                    $prompt = $msg['content'];
                } else {
                    $history[] = new UserMessage($msg['content']);
                }
            }
        }

        return [$instructions, $history, $prompt];
    }
}
