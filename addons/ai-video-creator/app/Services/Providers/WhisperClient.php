<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhisperClient
{
    public function transcribe(string $audioOrVideoPath): array
    {
        $apiKey = settings('openai_api_key');
        if (empty($apiKey)) {
            throw new VideoProviderException('OpenAI API key is not configured in global AI settings.');
        }

        $response = Http::withToken($apiKey)
            ->attach('file', file_get_contents($audioOrVideoPath), basename($audioOrVideoPath))
            ->timeout(120)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
                'timestamp_granularities' => ['segment'],
            ]);

        if (! $response->successful()) {
            throw new VideoProviderException('Whisper transcription failed: ' . Str::limit($response->body(), 300));
        }

        $data = $response->json();

        return [
            'text' => $data['text'] ?? '',
            'segments' => $data['segments'] ?? [],
        ];
    }
}
