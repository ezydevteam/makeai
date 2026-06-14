<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ElevenLabsClient
{
    public function textToSpeech(string $text, string $voiceId, string $outputPath): void
    {
        $apiKey = addon_setting('ai-video-creator', 'elevenlabs_api_key');
        if (empty($apiKey)) {
            throw new VideoProviderException('ElevenLabs API key is not configured.');
        }

        $response = Http::withHeader('xi-api-key', $apiKey)
            ->timeout(120)
            ->withOptions(['sink' => $outputPath])
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => 'eleven_monolingual_v1',
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                ],
            ]);

        if (! $response->successful()) {
            throw new VideoProviderException('ElevenLabs TTS failed: ' . Str::limit($response->body(), 300));
        }
    }
}
