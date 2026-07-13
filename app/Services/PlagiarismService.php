<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Integrations\Contracts\ToolIntegration;
use Illuminate\Support\Facades\Http;
use Throwable;

class PlagiarismService implements ToolIntegration
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

    /**
     * Check text for copied/matching sources.
     *
     * @param  array{text?:string,content?:string}  $input
     */
    public function run(array $input): array
    {
        $text = trim((string) ($input['text'] ?? $input['content'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'type' => 'plagiarism', 'provider' => $this->provider, 'error' => 'No text provided.', 'raw' => null];
        }

        if ($this->provider === 'copyscape') {
            $data = Http::timeout(45)->asForm()
                ->post('https://www.copyscape.com/api/', [
                    'u' => $this->username,
                    'k' => $this->apiKey,
                    'o' => 'csearch',
                    'e' => 'UTF-8',
                    't' => $text,
                    'c' => 10,
                    'f' => 'json',
                ])->throw()->json();

            $results = collect($data['result'] ?? [])->map(fn ($r) => [
                'url' => $r['url'] ?? '',
                'title' => $r['title'] ?? '',
                'matched_percent' => (float) ($r['percentmatched'] ?? 0),
                'matched_words' => (int) ($r['wordsmatched'] ?? 0),
            ])->values()->all();

            $score = (float) collect($results)->max('matched_percent') ?: 0.0;
        } else { // originality.ai
            $data = Http::timeout(45)
                ->withHeader('X-OAI-API-KEY', (string) $this->apiKey)
                ->post('https://api.originality.ai/api/v1/scan/plag', ['content' => $text])
                ->throw()->json();

            // Originality returns a 0–1 score; normalize to a percent.
            $raw = (float) ($data['total_text_score'] ?? data_get($data, 'results.score', 0));
            $score = $raw <= 1 ? round($raw * 100, 1) : round($raw, 1);
            $results = collect(data_get($data, 'results.matches', $data['matches'] ?? []))->map(fn ($m) => [
                'url' => $m['url'] ?? $m['website'] ?? '',
                'title' => $m['title'] ?? '',
                'matched_percent' => (float) ($m['score'] ?? $m['percent'] ?? 0),
                'matched_words' => (int) ($m['matched_words'] ?? 0),
            ])->values()->all();
        }

        return [
            'ok' => true,
            'type' => 'plagiarism',
            'provider' => $this->provider,
            'plagiarism_percent' => round((float) $score, 1),
            'match_count' => count($results),
            'matches' => $results,
            'raw' => $data,
        ];
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No API key configured.'];
        }

        try {
            if ($this->provider === 'copyscape') {
                // Use the account balance operation — it verifies the credentials
                // WITHOUT spending a search credit (csearch would bill on every test).
                $response = Http::timeout(15)->asForm()->post('https://www.copyscape.com/api/', [
                    'u' => $this->username,
                    'k' => $this->apiKey,
                    'o' => 'balance',
                ]);

                // Copyscape returns an <error>…</error> element for bad credentials.
                $ok = $response->successful() && ! str_contains(strtolower($response->body()), '<error');

                return [
                    'success' => $ok,
                    'message' => $ok ? 'Copyscape API reachable.' : 'Copyscape rejected the credentials.',
                ];
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
