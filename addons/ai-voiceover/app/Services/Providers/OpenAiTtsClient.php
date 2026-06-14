<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services\Providers;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAiTtsClient extends TtsProviderClient
{
    private const BASE_URL = 'https://api.openai.com/v1/audio';

    private const FIXED_VOICES = ['alloy', 'ash', 'coral', 'echo', 'fable', 'nova', 'onyx', 'shimmer', 'verse'];

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function generateSpeech(string $text, string $voiceId, array $options = []): string
    {
        $apiKey = $this->getApiKey('openai_api_key');
        $model = $options['model'] ?? 'tts-1-hd';
        $speed = (float) ($options['speed'] ?? addon_setting('ai-voiceover', 'speed_default', 1.0));

        $tempPath = sys_get_temp_dir() . '/openai_tts_' . uniqid() . '.mp3';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->withOptions(['sink' => $tempPath])
                ->post(self::BASE_URL . '/speech', [
                    'model' => $model,
                    'input' => $text,
                    'voice' => $voiceId,
                    'response_format' => $options['format'] ?? 'mp3',
                    'speed' => $speed,
                ]);

            if (! $response->successful()) {
                @unlink($tempPath);
                throw VoiceoverException::providerError('OpenAI', Str::limit($response->body(), 300));
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
            throw VoiceoverException::providerError('OpenAI', $e->getMessage());
        }
    }

    public function listVoices(): array
    {
        return array_map(function (string $voiceId) {
            return [
                'id' => $voiceId,
                'name' => ucfirst($voiceId),
                'gender' => null,
                'language' => 'en',
                'accent' => null,
                'preview_url' => null,
                'labels' => [],
                'is_cloned' => false,
            ];
        }, self::FIXED_VOICES);
    }
}
