<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Services\Integrations\Contracts\ToolIntegration;
use Illuminate\Support\Facades\Http;

/**
 * DeepL / Google Translate engine for the "Translation" tool.
 *
 * NOTE: distinct from App\Services\TranslationService, which is the app's i18n
 * (UI localization) service — a name collision we avoid by living here.
 */
class TranslationIntegration implements ToolIntegration
{
    public function __construct(
        private readonly string $provider = 'deepl',
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_translation_provider', 'deepl');

        return $provider === 'google_translate'
            ? new self('google_translate', settings('external_translation_google_translate_api_key'))
            : new self('deepl', settings('external_translation_deepl_auth_key'));
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
            $result = $this->run(['text' => 'Hello', 'target_language' => 'DE']);

            return ['success' => ($result['ok'] ?? false) === true, 'message' => ucfirst($this->provider).' API reachable.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array{text?:string,content?:string,target_language?:string,target?:string}  $input
     */
    public function run(array $input): array
    {
        $text = trim((string) ($input['text'] ?? $input['content'] ?? ''));
        $target = (string) ($input['target_language'] ?? $input['target'] ?? 'EN');

        if ($text === '') {
            return ['ok' => false, 'type' => 'translation', 'provider' => $this->provider, 'error' => 'No text provided.', 'raw' => null];
        }

        if ($this->provider === 'deepl') {
            // Free keys end in ":fx" and use the free host.
            $host = str_ends_with((string) $this->apiKey, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
            $data = Http::timeout(30)
                ->withHeader('Authorization', 'DeepL-Auth-Key '.$this->apiKey)
                ->asForm()->post($host.'/v2/translate', ['text' => $text, 'target_lang' => strtoupper($target)])
                ->throw()->json();

            $translated = data_get($data, 'translations.0.text', '');
            $source = data_get($data, 'translations.0.detected_source_language');
        } else { // google_translate
            $data = Http::timeout(30)
                ->post('https://translation.googleapis.com/language/translate/v2?key='.$this->apiKey, [
                    'q' => $text,
                    'target' => strtolower($target),
                    'format' => 'text',
                ])->throw()->json();

            $translated = data_get($data, 'data.translations.0.translatedText', '');
            $source = data_get($data, 'data.translations.0.detectedSourceLanguage');
        }

        return [
            'ok' => true,
            'type' => 'translation',
            'provider' => $this->provider,
            'target_language' => strtoupper($target),
            'source_language' => $source,
            'translated' => $translated,
            'raw' => $data,
        ];
    }
}
