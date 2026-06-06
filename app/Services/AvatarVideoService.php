<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class AvatarVideoService
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
        $provider = settings('external_avatar_video_provider', 'did');
        $apiKey = settings("external_avatar_video_{$provider}_api_key");

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
                'did' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.d-id.com/credits'),
                'heygen' => Http::timeout(15)->withHeader('X-Api-Key', $this->apiKey)->get('https://api.heygen.com/v2/user'),
                'synthesia' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.synthesia.io/v2/user'),
                default => Http::timeout(15)->get('https://httpbin.org/get'),
            };

            return ['success' => $response->successful(), 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
