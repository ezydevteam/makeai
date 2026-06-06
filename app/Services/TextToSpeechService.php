<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class TextToSpeechService
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
        $provider = settings('external_text_to_speech_provider', 'elevenlabs');
        $apiKey = settings("external_text_to_speech_{$provider}_api_key");

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
                'elevenlabs' => Http::timeout(15)->withHeader('xi-api-key', $this->apiKey)->get('https://api.elevenlabs.io/v1/user'),
                'openai_tts' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.openai.com/v1/models'),
                'murf' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.murf.ai/v1/voices'),
                'playht' => Http::timeout(15)->withToken($this->apiKey)->get('https://api.play.ht/api/v2/voices'),
                'azure_speech' => Http::timeout(15)->withHeader('Ocp-Apim-Subscription-Key', $this->apiKey)->get('https://eastus.api.cognitive.microsoft.com/sts/v1.0/issuetoken'),
                'google_tts' => Http::timeout(15)->get('https://texttospeech.googleapis.com/$discovery/rest'),
                'amazon_polly' => Http::timeout(15)->get('https://polly.amazonaws.com'),
                default => Http::timeout(15)->get('https://httpbin.org/get'),
            };

            return ['success' => $response->successful(), 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
