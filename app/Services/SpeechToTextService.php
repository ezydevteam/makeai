<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SpeechToTextService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_speech_to_text_provider', 'openai_whisper');

        if ($provider === 'assemblyai') {
            return new self(
                provider: 'assemblyai',
                apiKey: settings('external_speech_to_text_assemblyai_api_key'),
            );
        }

        if ($provider === 'deepgram') {
            return new self(
                provider: 'deepgram',
                apiKey: settings('external_speech_to_text_deepgram_api_key'),
            );
        }

        return new self(
            provider: 'openai_whisper',
            apiKey: settings('external_speech_to_text_openai_whisper_api_key'),
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
            if ($this->provider === 'assemblyai') {
                $response = Http::timeout(15)
                    ->withHeader('Authorization', $this->apiKey)
                    ->get('https://api.assemblyai.com/v2/account');

                return ['success' => $response->successful(), 'message' => 'AssemblyAI API reachable.'];
            }

            if ($this->provider === 'deepgram') {
                $response = Http::timeout(15)
                    ->withToken($this->apiKey)
                    ->get('https://api.deepgram.com/v1/projects');

                return ['success' => $response->successful(), 'message' => 'Deepgram API reachable.'];
            }

            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->get('https://api.openai.com/v1/models');

            return ['success' => $response->successful(), 'message' => 'Whisper (OpenAI) API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
