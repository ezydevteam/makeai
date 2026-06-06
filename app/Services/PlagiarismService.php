<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class PlagiarismService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $username = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_plagiarism_provider', 'copyscape');

        if ($provider === 'copyscape') {
            return new self(
                provider: 'copyscape',
                apiKey: settings('external_plagiarism_copyscape_api_key'),
                username: settings('external_plagiarism_copyscape_username'),
            );
        }

        return new self(
            provider: 'originality',
            apiKey: settings('external_plagiarism_originality_api_key'),
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
            if ($this->provider === 'copyscape') {
                $response = Http::timeout(15)
                    ->withBasicAuth($this->username, $this->apiKey)
                    ->post('https://www.copyscape.com/api/', [
                        'o' => 'csearch',
                        'q' => 'test',
                    ]);

                return ['success' => $response->successful(), 'message' => 'Copyscape API reachable.'];
            }

            $response = Http::timeout(15)
                ->withHeader('X-OAI-API-KEY', $this->apiKey)
                ->get('https://api.originality.ai/api/v1/account');

            return ['success' => $response->successful(), 'message' => 'Originality.ai API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
