<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GrammarService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            provider: 'languagetool',
            apiKey: settings('external_grammar_languagetool_api_key'),
            baseUrl: settings('external_grammar_languagetool_base_url'),
        );
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): array
    {
        try {
            $url = filled($this->baseUrl)
                ? rtrim($this->baseUrl, '/') . '/v2/check'
                : 'https://api.languagetool.org/v2/check';

            $response = Http::timeout(15)
                ->asForm()
                ->post($url, [
                    'text' => 'Hello world.',
                    'language' => 'en-US',
                ]);

            return ['success' => $response->successful(), 'message' => 'LanguageTool API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
