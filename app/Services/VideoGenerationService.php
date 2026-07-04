<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\IntegrationSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

class VideoGenerationService
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
        $config = IntegrationSettings::forSelectedProvider('video_generation', 'runway');

        return new self($config['provider'], $config['secrets'], $config['options']);
    }

    public function isConfigured(): bool
    {
        // Every declared secret must be present — e.g. kling needs api_key + secret,
        // google_veo needs credentials_json.
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
                'runway' => Http::timeout(15)->withToken($apiKey)->get('https://api.runwayml.com/v1/account'),
                'openai_sora' => Http::timeout(15)->withToken($apiKey)->get('https://api.openai.com/v1/models'),
                'minimax' => Http::timeout(15)->withToken($apiKey)->get('https://api.minimax.chat/v1/text/chatcompletion_pro'),
                'pika' => Http::timeout(15)->withHeader('Authorization', "Bearer {$apiKey}")->get('https://api.pika.art/v1/models'),
                // Kling signs a JWT from api_key + secret; Google Veo (Vertex AI)
                // uses a service-account JWT — neither can be validated with a plain
                // request, so confirm the required credentials are present instead.
                'kling', 'google_veo' => null,
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
