<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Provider Adapter — Claude models.
 *
 * Handles Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Haiku, etc.
 * Uses the Messages API (v1/messages).
 */
class AnthropicProvider implements ProviderInterface
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? settings('anthropic_api_key', '');
        $this->baseUrl = $baseUrl ?? 'https://api.anthropic.com';
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function getModels(): array
    {
        $models = AiModel::where('provider', 'anthropic')->active()->pluck('slug')->toArray();

        return ! empty($models) ? $models : ['claude-sonnet-4-20250514', 'claude-3-5-haiku-20241022'];
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) || AiKey::forProvider('anthropic')->available()->exists();
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
        // Anthropic separates system from messages
        $system = '';
        $apiMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $apiMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? (int) settings('max_tokens_per_request', 4096),
            'messages' => $apiMessages,
        ];
        if (! empty($system)) {
            $payload['system'] = $system;
        }
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($this->baseUrl.'/v1/messages', $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown Anthropic error');
            throw new \RuntimeException("Anthropic API Error: {$error}");
        }

        $data = $response->json();
        $content = collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');

        return [
            'content' => $content,
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            'model' => $data['model'] ?? $model,
        ];
    }

    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator
    {
        $system = '';
        $apiMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $apiMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? (int) settings('max_tokens_per_request', 4096),
            'messages' => $apiMessages,
            'stream' => true,
        ];
        if (! empty($system)) {
            $payload['system'] = $system;
        }
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $ch = curl_init($this->baseUrl.'/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'x-api-key: '.$this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
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
            $error = json_decode($body, true)['error']['message'] ?? 'Unknown Anthropic streaming error';
            throw new \RuntimeException("Anthropic API Error: {$error}");
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

            if ($json['type'] === 'content_block_delta' && isset($json['delta']['text'])) {
                $token = $json['delta']['text'];
                $tokens[] = $token;
                yield $token;
            }
            if ($json['type'] === 'message_delta' && isset($json['usage'])) {
                $outputTokens = $json['usage']['output_tokens'] ?? 0;
            }
            if ($json['type'] === 'message_start' && isset($json['message']['usage'])) {
                $inputTokens = $json['message']['usage']['input_tokens'] ?? 0;
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
