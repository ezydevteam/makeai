<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini Provider Adapter.
 *
 * Handles Gemini 2.0 Flash, Gemini 1.5 Pro, etc.
 * Uses the generateContent API.
 */
class GoogleProvider implements ProviderInterface
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? settings('google_api_key', '');
        $this->baseUrl = $baseUrl ?? 'https://generativelanguage.googleapis.com/v1beta';
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getModels(): array
    {
        $models = AiModel::where('provider', 'google')->active()->pluck('slug')->toArray();

        return ! empty($models) ? $models : ['gemini-2.0-flash', 'gemini-1.5-pro'];
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) || AiKey::forProvider('google')->available()->exists();
    }

    public function setApiKey(string $key): self
    {
        $this->apiKey = $key;

        return $this;
    }

    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function chatCompletion(array $messages, string $model, array $options = []): array
    {
        $system = '';
        $contents = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $contents[] = [
                    'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? (int) settings('max_tokens_per_request', 4096),
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ];
        if (! empty($system)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(120)
            ->post($url, $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown Google API error');
            throw new \RuntimeException("Google API Error: {$error}");
        }

        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'content' => $content,
            'input_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            'model' => $model,
        ];
    }

    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator
    {
        $system = '';
        $contents = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $contents[] = [
                    'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? (int) settings('max_tokens_per_request', 4096),
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ];
        if (! empty($system)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $url = "{$this->baseUrl}/models/{$model}:streamGenerateContent?alt=sse&key={$this->apiKey}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120,
        ]);

        $tempStream = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_FILE, $tempStream);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            rewind($tempStream);
            $body = stream_get_contents($tempStream);
            fclose($tempStream);
            $error = json_decode($body, true)['error']['message'] ?? 'Unknown Google streaming error';
            throw new \RuntimeException("Google API Error: {$error}");
        }

        rewind($tempStream);
        $fullResponse = stream_get_contents($tempStream);
        fclose($tempStream);

        $tokens = [];
        $inputTokens = 0;
        $outputTokens = 0;

        foreach (explode("\n", $fullResponse) as $line) {
            $line = trim($line);
            if (empty($line) || ! str_starts_with($line, 'data: ')) {
                continue;
            }
            $json = json_decode(substr($line, 6), true);
            if (! $json) {
                continue;
            }

            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                $tokens[] = $text;
                yield $text;
            }

            if (isset($json['usageMetadata'])) {
                $inputTokens = $json['usageMetadata']['promptTokenCount'] ?? 0;
                $outputTokens = $json['usageMetadata']['candidatesTokenCount'] ?? 0;
            }
        }

        yield [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model' => $model,
            'content' => implode('', $tokens),
        ];
    }
}
