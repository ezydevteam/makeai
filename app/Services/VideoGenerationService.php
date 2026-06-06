<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class VideoGenerationService
{
    private ?string $provider;
    private ?string $apiKey;

    public function __construct(?string $provider = null, ?string $apiKey = null)
    {
        $this->provider = $provider;
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        $provider = settings('external_video_generation_provider', 'runway');
        $apiKey = settings("external_video_generation_{$provider}_api_key");

        return new self($provider, $apiKey);
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
                'runway' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.runwayml.com/v1/account'),
                'openai_sora' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.openai.com/v1/models'),
                'minimax' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.minimax.chat/v1/text/chatcompletion_pro'),
                'pika' => Http::timeout(15)->withHeader('Authorization', "Bearer {$this->apiKey}")->get('https://api.pika.art/v1/models'),
                default => Http::timeout(15)->get('https://httpbin.org/get'),
            };

            return ['success' => $response->successful(), 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
