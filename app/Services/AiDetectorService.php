<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Integrations\Contracts\ToolIntegration;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiDetectorService implements ToolIntegration
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_ai_detector_provider', 'gptzero');

        if ($provider === 'sapling') {
            return new self(provider: 'sapling', apiKey: settings('external_ai_detector_sapling_api_key'));
        }

        return new self(provider: 'gptzero', apiKey: settings('external_ai_detector_gptzero_api_key'));
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Score how likely the text is AI-generated (0–100%).
     *
     * @param  array{text?:string,content?:string}  $input
     */
    public function run(array $input): array
    {
        $text = trim((string) ($input['text'] ?? $input['content'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'type' => 'ai_detection', 'provider' => $this->provider, 'error' => 'No text provided.', 'raw' => null];
        }

        if ($this->provider === 'gptzero') {
            $data = Http::timeout(30)
                ->withHeader('X-Api-Key', (string) $this->apiKey)
                ->post('https://api.gptzero.me/v2/predict/text', ['document' => $text])
                ->throw()->json();

            $doc = $data['documents'][0] ?? [];
            $prob = $doc['class_probabilities']['ai']
                ?? $doc['completely_generated_prob']
                ?? 0;
        } else { // sapling
            $data = Http::timeout(30)
                ->post('https://api.sapling.ai/api/v1/aidetect', ['key' => $this->apiKey, 'text' => $text])
                ->throw()->json();

            $prob = $data['score'] ?? 0;
        }

        $ai = round(((float) $prob) * 100, 1);

        return [
            'ok' => true,
            'type' => 'ai_detection',
            'provider' => $this->provider,
            'ai_probability' => $ai,
            'human_probability' => round(100 - $ai, 1),
            'verdict' => $ai >= 70 ? 'ai' : ($ai <= 30 ? 'human' : 'mixed'),
            'raw' => $data,
        ];
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No API key configured.'];
        }

        try {
            if ($this->provider === 'gptzero') {
                $response = Http::timeout(15)
                    ->withHeader('X-Api-Key', $this->apiKey)
                    ->get('https://api.gptzero.me/v2/account');

                return ['success' => $response->successful(), 'message' => 'GPTZero API reachable.'];
            }

            $response = Http::timeout(15)
                ->withHeader('X-API-Key', $this->apiKey)
                ->get('https://api.sapling.ai/user');

            return ['success' => $response->successful(), 'message' => 'Sapling API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
