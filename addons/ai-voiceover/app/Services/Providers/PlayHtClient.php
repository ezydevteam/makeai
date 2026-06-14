<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services\Providers;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PlayHtClient extends TtsProviderClient
{
    private const BASE_URL = 'https://api.play.ht/api/v2';

    public function getProviderName(): string
    {
        return 'playht';
    }

    public function generateSpeech(string $text, string $voiceId, array $options = []): string
    {
        $apiKey = $this->getApiKey('playht_api_key');
        $userId = $this->getApiKey('playht_user_id');

        $tempPath = sys_get_temp_dir() . '/playht_tts_' . uniqid() . '.mp3';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'X-User-ID' => $userId,
            ])
                ->timeout(60)
                ->withOptions(['sink' => $tempPath])
                ->post(self::BASE_URL . '/tts/stream', [
                    'voice' => $voiceId,
                    'text' => $text,
                    'output_format' => 'mp3',
                    'voice_engine' => 'PlayHT2.0-turbo',
                    'quality' => 'high',
                ]);

            if (! $response->successful()) {
                @unlink($tempPath);
                throw VoiceoverException::providerError('PlayHT', Str::limit($response->body(), 300));
            }

            $outputUserId = $options['user_id'] ?? 0;
            $ulid = $options['ulid'] ?? Str::ulid()->toString();
            $outputPath = $this->resolveOutputPath($outputUserId, $ulid);
            $absPath = storage_path('app/' . $outputPath);

            rename($tempPath, $absPath);

            return $outputPath;
        } catch (VoiceoverException $e) {
            throw $e;
        } catch (\Throwable $e) {
            @unlink($tempPath);
            throw VoiceoverException::providerError('PlayHT', $e->getMessage());
        }
    }

    public function listVoices(): array
    {
        $apiKey = $this->getApiKey('playht_api_key');
        $userId = $this->getApiKey('playht_user_id');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'X-User-ID' => $userId,
        ])
            ->timeout(30)
            ->get(self::BASE_URL . '/voices');

        if (! $response->successful()) {
            throw VoiceoverException::providerError('PlayHT', Str::limit($response->body(), 300));
        }

        $voices = $response->json('voices') ?? [];

        return array_map(function (array $v) {
            return [
                'id' => $v['id'] ?? $v['voice_id'] ?? '',
                'name' => $v['name'] ?? 'Unknown',
                'gender' => $v['gender'] ?? null,
                'language' => $v['language'] ?? 'en',
                'accent' => $v['accent'] ?? null,
                'preview_url' => null,
                'labels' => [],
                'is_cloned' => ($v['is_cloned'] ?? false) || ($v['voice_type'] ?? '') === 'cloned',
            ];
        }, $voices);
    }
}
