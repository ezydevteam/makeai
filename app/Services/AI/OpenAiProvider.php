<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Provider Adapter.
 *
 * Handles communication with OpenAI's API (GPT-4o, o1, o3, o4-mini).
 * Also works with any OpenAI-compatible API (OpenRouter, local models).
 *
 * Supports both synchronous and streaming completions.
 */
class OpenAiProvider implements ProviderInterface
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? settings('openai_api_key', '');
        $this->baseUrl = $baseUrl ?? 'https://api.openai.com/v1';
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getModels(): array
    {
        $models = AiModel::where('provider', 'openai')->active()->pluck('slug')->toArray();

        return ! empty($models) ? $models : config('ai.providers.openai.models', []);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) || AiKey::forProvider('openai')->available()->exists();
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

    /**
     * Synchronous chat completion.
     */
    public function chatCompletion(array $messages, string $model, array $options = []): array
    {
        $payload = $this->buildPayload($messages, $model, $options);

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post($this->baseUrl.'/chat/completions', $payload);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown OpenAI error');
            throw new \RuntimeException("OpenAI API Error: {$error}");
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
            'model' => $data['model'] ?? $model,
        ];
    }

    /**
     * Streaming chat completion — yields tokens as they arrive.
     *
     * Usage:
     *   foreach ($provider->streamChatCompletion($msgs, $model) as $chunk) {
     *       if (is_string($chunk)) echo $chunk; // token
     *       if (is_array($chunk))  // final usage stats
     *   }
     */
    public function streamChatCompletion(array $messages, string $model, array $options = []): \Generator
    {
        $payload = $this->buildPayload($messages, $model, $options);
        $payload['stream'] = true;
        $payload['stream_options'] = ['include_usage' => true];

        $ch = curl_init($this->baseUrl.'/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$this->apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_WRITEFUNCTION => function () {
                return 0;
            }, // placeholder — overridden below
        ]);

        $buffer = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $tokens = [];

        // Use a temporary file to buffer the stream
        $tempStream = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_FILE, $tempStream);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            rewind($tempStream);
            $body = stream_get_contents($tempStream);
            fclose($tempStream);
            $error = json_decode($body, true)['error']['message'] ?? 'Unknown OpenAI streaming error';
            throw new \RuntimeException("OpenAI API Error: {$error}");
        }

        rewind($tempStream);
        $fullResponse = stream_get_contents($tempStream);
        fclose($tempStream);

        // Parse SSE lines
        $lines = explode("\n", $fullResponse);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || ! str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6);
            if ($data === '[DONE]') {
                break;
            }

            $json = json_decode($data, true);
            if (! $json) {
                continue;
            }

            // Extract token
            $delta = $json['choices'][0]['delta'] ?? [];
            if (isset($delta['content'])) {
                $token = $delta['content'];
                $tokens[] = $token;
                yield $token;
            }

            // Extract usage from final chunk (stream_options.include_usage)
            if (isset($json['usage'])) {
                $inputTokens = $json['usage']['prompt_tokens'] ?? 0;
                $outputTokens = $json['usage']['completion_tokens'] ?? 0;
            }
        }

        // If no usage data from stream, estimate from content
        if ($inputTokens === 0 && $outputTokens === 0) {
            $fullContent = implode('', $tokens);
            $outputTokens = (int) ceil(strlen($fullContent) / 4);
            // Input tokens estimated from message content
            $inputText = collect($messages)->pluck('content')->implode(' ');
            $inputTokens = (int) ceil(strlen($inputText) / 4);
        }

        // Final yield — usage stats as array
        yield [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model' => $model,
            'content' => implode('', $tokens),
        ];
    }

    /**
     * Build the API payload.
     */
    private function buildPayload(array $messages, string $model, array $options): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? (int) settings('max_tokens_per_request', 4096),
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }
        if (isset($options['frequency_penalty'])) {
            $payload['frequency_penalty'] = $options['frequency_penalty'];
        }
        if (isset($options['presence_penalty'])) {
            $payload['presence_penalty'] = $options['presence_penalty'];
        }

        return $payload;
    }

    /**
     * Get request headers.
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
