<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services\Providers;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MurfClient extends TtsProviderClient
{
    private const BASE_URL = 'https://api.murf.ai/v1';

    public function getProviderName(): string
    {
        return 'murf';
    }

    public function generateSpeech(string $text, string $voiceId, array $options = []): string
    {
        $apiKey = $this->getApiKey('murf_api_key');

        $response = Http::withHeader('api-key', $apiKey)
            ->timeout(60)
            ->post(self::BASE_URL . '/speech/generate-with-key', [
                'voiceId' => $voiceId,
                'text' => $text,
                'format' => 'MP3',
                'channelType' => 'STEREO',
                'sampleRate' => 48000,
                'audioDuration' => 0,
            ]);

        if (! $response->successful()) {
            throw VoiceoverException::providerError('Murf', Str::limit($response->body(), 300));
        }

        $audioData = $response->json('audioFile');
        if (empty($audioData)) {
            throw VoiceoverException::providerError('Murf', 'No audio data in response.');
        }

        $decoded = base64_decode($audioData, true);
        if ($decoded === false) {
            throw VoiceoverException::providerError('Murf', 'Failed to decode audio data.');
        }

        $userId = $options['user_id'] ?? 0;
        $ulid = $options['ulid'] ?? Str::ulid()->toString();
        $outputPath = $this->resolveOutputPath($userId, $ulid);
        $absPath = storage_path('app/' . $outputPath);

        file_put_contents($absPath, $decoded);

        return $outputPath;
    }

    public function listVoices(): array
    {
        $apiKey = $this->getApiKey('murf_api_key');

        $response = Http::withHeader('api-key', $apiKey)
            ->timeout(30)
            ->get(self::BASE_URL . '/speech/voices');

        if (! $response->successful()) {
            throw VoiceoverException::providerError('Murf', Str::limit($response->body(), 300));
        }

        $voices = $response->json('voices') ?? [];

        return array_map(function (array $v) {
            return [
                'id' => $v['voiceId'] ?? '',
                'name' => $v['displayName'] ?? 'Unknown',
                'gender' => $v['gender'] ?? null,
                'language' => $v['locale'] ?? 'en',
                'accent' => null,
                'preview_url' => null,
                'labels' => [],
                'is_cloned' => false,
            ];
        }, $voices);
    }
}
