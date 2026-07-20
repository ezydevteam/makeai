<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Integrations\Contracts\ToolIntegration;
use Illuminate\Support\Facades\Http;
use Throwable;

class GrammarService implements ToolIntegration
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
        // LanguageTool's public API needs no key; a self-hosted base_url or a key
        // both work, so grammar is always runnable once enabled.
        return true;
    }

    /**
     * Check text and return issues + a best-effort corrected version.
     *
     * @param  array{text?:string,content?:string,language?:string}  $input
     */
    public function run(array $input): array
    {
        $text = trim((string) ($input['text'] ?? $input['content'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'type' => 'grammar', 'provider' => 'languagetool', 'error' => 'No text provided.', 'raw' => null];
        }

        $url = filled($this->baseUrl)
            ? rtrim($this->baseUrl, '/').'/v2/check'
            : 'https://api.languagetool.org/v2/check';

        $payload = ['text' => $text, 'language' => (string) ($input['language'] ?? 'auto')];
        if (filled($this->apiKey)) {
            $payload['apiKey'] = $this->apiKey;
        }

        $data = Http::timeout(20)->asForm()->post($url, $payload)->throw()->json();

        $issues = collect($data['matches'] ?? [])->map(fn ($m) => [
            'message' => $m['message'] ?? '',
            'offset' => (int) ($m['offset'] ?? 0),
            'length' => (int) ($m['length'] ?? 0),
            'replacements' => collect($m['replacements'] ?? [])->pluck('value')->take(5)->values()->all(),
            'category' => $m['rule']['category']['name'] ?? ($m['shortMessage'] ?? ''),
        ])->values()->all();

        return [
            'ok' => true,
            'type' => 'grammar',
            'provider' => 'languagetool',
            'issue_count' => count($issues),
            'issues' => $issues,
            'corrected' => $this->applyCorrections($text, $issues),
            'raw' => $data,
        ];
    }

    /** Apply each issue's first suggestion, back-to-front so earlier offsets stay valid. */
    private function applyCorrections(string $text, array $issues): string
    {
        usort($issues, fn ($a, $b) => $b['offset'] <=> $a['offset']);

        foreach ($issues as $issue) {
            $replacement = $issue['replacements'][0] ?? null;
            if ($replacement === null || $issue['length'] <= 0) {
                continue;
            }
            $text = mb_substr($text, 0, $issue['offset']).$replacement.mb_substr($text, $issue['offset'] + $issue['length']);
        }

        return $text;
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
