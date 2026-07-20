<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services\Providers;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElevenLabsClient extends TtsProviderClient
{
    private const BASE_URL = 'https://api.elevenlabs.io/v1';
    private const DEFAULT_MODEL = 'eleven_multilingual_v2';

    public function getProviderName(): string
    {
        return 'elevenlabs';
    }

    public function generateSpeech(string $text, string $voiceId, array $options = []): string
    {
        $apiKey = $this->getApiKey('elevenlabs_api_key');

        $stability = $options['stability'] ?? (float) addon_setting('ai-voiceover', 'stability_default', 0.5);
        $similarityBoost = $options['similarity_boost'] ?? (float) addon_setting('ai-voiceover', 'similarity_boost_default', 0.75);
        $style = $options['style'] ?? 0.0;

        $tempPath = sys_get_temp_dir() . '/el_tts_' . uniqid() . '.mp3';

        try {
            $response = Http::withHeader('xi-api-key', $apiKey)
                ->timeout(60)
                ->withOptions(['sink' => $tempPath])
                ->post(self::BASE_URL . '/text-to-speech/' . $voiceId . '/stream', [
                    'text' => $text,
                    'model_id' => $options['model_id'] ?? self::DEFAULT_MODEL,
                    'voice_settings' => [
                        'stability' => $stability,
                        'similarity_boost' => $similarityBoost,
                        'style' => $style,
                        'use_speaker_boost' => true,
                    ],
                ]);

            if (! $response->successful()) {
                @unlink($tempPath);
                throw VoiceoverException::providerError('ElevenLabs', Str::limit($response->body(), 300));
            }

            $userId = $options['user_id'] ?? 0;
            $ulid = $options['ulid'] ?? Str::ulid()->toString();
            $outputPath = $this->resolveOutputPath($userId, $ulid);
            $absPath = storage_path('app/' . $outputPath);

            rename($tempPath, $absPath);

            return $outputPath;
        } catch (VoiceoverException $e) {
            throw $e;
        } catch (\Throwable $e) {
            @unlink($tempPath);
            throw VoiceoverException::providerError('ElevenLabs', $e->getMessage());
        }
    }

    public function listVoices(): array
    {
        $apiKey = $this->getApiKey('elevenlabs_api_key');

        $response = Http::withHeader('xi-api-key', $apiKey)
            ->timeout(30)
            ->get(self::BASE_URL . '/voices');

        if (! $response->successful()) {
            throw VoiceoverException::providerError('ElevenLabs', Str::limit($response->body(), 300));
        }

        $voices = $response->json('voices') ?? [];

        return array_map(function (array $v) {
            return [
                'id' => $v['voice_id'],
                'name' => $v['name'] ?? 'Unknown',
                'gender' => $v['labels']['gender'] ?? null,
                'language' => $v['labels']['language'] ?? 'en',
                'accent' => $v['labels']['accent'] ?? null,
                'preview_url' => $v['preview_url'] ?? null,
                'labels' => $v['labels'] ?? [],
                'is_cloned' => ($v['category'] ?? '') === 'cloned',
            ];
        }, $voices);
    }
}
