<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\IntegrationSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

class TextToSpeechService
{
    /**
     * @param  array<string, ?string>  $secrets
     * @param  array<string, string>  $options
     */
    public function __construct(
        private readonly ?string $provider = null,
        private readonly array $secrets = [],
        private readonly array $options = [],
    ) {}

    public static function fromSettings(): self
    {
        $config = IntegrationSettings::forSelectedProvider('text_to_speech', 'elevenlabs');

        return new self($config['provider'], $config['secrets'], $config['options']);
    }

    public function isConfigured(): bool
    {
        // Every declared secret for the selected provider must be present — e.g.
        // amazon_polly needs access_key + secret_key, playht needs user_id + api_key.
        return IntegrationSettings::allSecretsPresent($this->secrets);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Credentials are not fully configured.'];
        }

        $apiKey = (string) ($this->secrets['api_key'] ?? '');

        try {
            $response = match ($this->provider) {
                'elevenlabs' => Http::timeout(15)->withHeader('xi-api-key', $apiKey)->get('https://api.elevenlabs.io/v1/user'),
                'openai_tts' => Http::timeout(15)->withToken($apiKey)->get('https://api.openai.com/v1/models'),
                'murf' => Http::timeout(15)->withHeader('api-key', $apiKey)->get('https://api.murf.ai/v1/voices'),
                'playht' => Http::timeout(15)
                    ->withHeader('X-User-ID', (string) ($this->secrets['user_id'] ?? ''))
                    ->withHeader('Authorization', 'Bearer '.$apiKey)
                    ->get('https://api.play.ht/api/v2/voices'),
                'azure_speech' => Http::timeout(15)
                    ->withHeader('Ocp-Apim-Subscription-Key', $apiKey)
                    ->post('https://'.($this->options['region'] ?: 'eastus').'.api.cognitive.microsoft.com/sts/v1.0/issueToken'),
                // AWS SigV4 (Polly) and Google service-account JWT (Google TTS) can't
                // be validated with a plain request — confirm credentials are present.
                'amazon_polly', 'google_tts' => null,
                default => null,
            };

            if ($response === null) {
                return ['success' => true, 'message' => "{$this->provider} credentials saved (no live check available for this provider)."];
            }

            return ['success' => $response->successful(), 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
