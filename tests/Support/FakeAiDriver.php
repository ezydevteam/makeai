<?php

namespace Tests\Support;

use App\Services\AI\Contracts\AiDriverFactory;
use App\Services\AI\Contracts\AiDriverInterface;
use Generator;

/**
 * A scripted driver: yields exactly the chunks a test hands it, so streaming
 * controllers can be exercised end to end without a real provider.
 */
class FakeAiDriver implements AiDriverInterface
{
    /**
     * @param  array<int, string|array>  $chunks  yielded in order, as the real driver would
     */
    public function __construct(private array $chunks = []) {}

    /**
     * Bind this driver in place of every provider driver for the current test.
     *
     * @param  array<int, string|array>  $chunks
     */
    public static function bind(array $chunks): self
    {
        $driver = new self($chunks);

        app()->instance(AiDriverFactory::class, new class($driver) implements AiDriverFactory
        {
            public function __construct(private FakeAiDriver $driver) {}

            public function make(string $driverName, ?string $apiKey = null): AiDriverInterface
            {
                return $this->driver;
            }
        });

        return $driver;
    }

    public function streamChatCompletion(array $messages, string $model, array $options = []): Generator
    {
        foreach ($this->chunks as $chunk) {
            yield $chunk;
        }
    }

    public function chatCompletion(array $messages, string $model, array $options = []): array
    {
        $content = '';
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];

        foreach ($this->chunks as $chunk) {
            if (is_string($chunk)) {
                $content .= $chunk;
            } elseif (is_array($chunk) && isset($chunk['input_tokens'])) {
                $usage = $chunk;
            }
        }

        return [
            'content' => $content,
            'model' => $usage['model'] ?? $model,
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
        ];
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function getModels(): array
    {
        return ['gpt-4o-mini'];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function setApiKey(string $key): self
    {
        return $this;
    }

    public function setBaseUrl(string $url): self
    {
        return $this;
    }

    public function embedText(string $text): array
    {
        return ['vector' => [0.1], 'dimensions' => 1, 'tokens_used' => 1, 'model' => 'fake'];
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn () => $this->embedText(''), $texts);
    }
}
