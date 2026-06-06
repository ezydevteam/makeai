<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WebSearchService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $endpoint = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_web_search_provider', 'serpapi');

        if ($provider === 'bing') {
            return new self(
                provider: 'bing',
                apiKey: settings('external_web_search_bing_api_key'),
                endpoint: settings('external_web_search_bing_endpoint'),
            );
        }

        if ($provider === 'perplexity') {
            return new self(
                provider: 'perplexity',
                apiKey: settings('external_web_search_perplexity_api_key'),
            );
        }

        return new self(
            provider: 'serpapi',
            apiKey: settings('external_web_search_serpapi_api_key'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No API key configured.'];
        }

        try {
            $response = match ($this->provider) {
                'serpapi' => Http::timeout(15)->get('https://serpapi.com/search', [
                    'api_key' => $this->apiKey,
                    'q' => 'test',
                    'num' => 1,
                ]),
                'bing' => Http::timeout(15)
                    ->withHeader('Ocp-Apim-Subscription-Key', $this->apiKey)
                    ->get(($this->endpoint ?: 'https://api.bing.microsoft.com/v7.0/search'), ['q' => 'test', 'count' => 1]),
                'perplexity' => Http::timeout(15)
                    ->withToken($this->apiKey)
                    ->post('https://api.perplexity.ai/chat/completions', [
                        'model' => 'sonar',
                        'messages' => [['role' => 'user', 'content' => 'test']],
                        'max_tokens' => 5,
                    ]),
                default => null,
            };

            return ['success' => $response?->successful() ?? false, 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
