<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Addons\AiVoiceover\Models\VoEpisode;
use Addons\AiVoiceover\Models\VoVoice;
use Addons\AiVoiceover\Services\Providers\ElevenLabsClient;
use Addons\AiVoiceover\Services\Providers\MurfClient;
use Addons\AiVoiceover\Services\Providers\OpenAiTtsClient;
use Addons\AiVoiceover\Services\Providers\PlayHtClient;
use Addons\AiVoiceover\Services\Providers\TtsProviderClient;
use Illuminate\Support\Collection;

class VoiceoverService
{
    private array $providerMap = [
        'elevenlabs' => ElevenLabsClient::class,
        'openai' => OpenAiTtsClient::class,
        'murf' => MurfClient::class,
        'playht' => PlayHtClient::class,
    ];

    public function getClient(string $provider): TtsProviderClient
    {
        if (! isset($this->providerMap[$provider])) {
            throw VoiceoverException::providerError($provider, 'Unknown provider.');
        }

        return app($this->providerMap[$provider]);
    }

    public function getConfiguredProviders(): array
    {
        $configured = [];
        $keyMap = [
            'elevenlabs' => 'elevenlabs_api_key',
            'openai' => 'openai_api_key',
            'murf' => 'murf_api_key',
            'playht' => 'playht_api_key',
        ];

        foreach ($this->providerMap as $name => $class) {
            $keyName = $keyMap[$name] ?? null;
            if (! $keyName) {
                continue;
            }
            $key = settings($keyName) ?: addon_setting('ai-voiceover', $keyName);
            if (! empty($key)) {
                $configured[] = $name;
            }
        }
        return $configured;
    }

    public function calculateCredits(string $text): int
    {
        $chars = mb_strlen($text);
        if ($chars === 0) {
            return 0;
        }

        $perK = (int) addon_setting('ai-voiceover', 'credits_per_1k_chars', 5);

        return (int) ceil($chars / 1000) * $perK;
    }

    public function generateSingle(VoEpisode $episode): string
    {
        $provider = $episode->provider ?: addon_setting('ai-voiceover', 'default_provider', 'openai');
        $voiceId = $episode->voice_id ?: addon_setting('ai-voiceover', 'default_voice_id', 'alloy');
        $client = $this->getClient($provider);

        return $client->generateSpeech(
            $episode->script ?? '',
            $voiceId,
            [
                'user_id' => $episode->user_id,
                'ulid' => $episode->ulid,
            ],
        );
    }

    public function generateMultiSpeaker(VoEpisode $episode): string
    {
        $segments = $episode->segments ?? [];
        $defaultProvider = addon_setting('ai-voiceover', 'default_provider', 'openai');
        $segmentPaths = [];

        foreach ($segments as $i => $seg) {
            $provider = $seg['provider'] ?? $defaultProvider;
            $voiceId = $seg['voice_id'] ?? addon_setting('ai-voiceover', 'default_voice_id', 'alloy');
            $client = $this->getClient($provider);

            $segPath = $client->generateSpeech(
                $seg['text'] ?? '',
                $voiceId,
                [
                    'user_id' => $episode->user_id,
                    'ulid' => $episode->ulid . '_seg' . $i,
                ],
            );
            $segmentPaths[] = $segPath;
        }

        $outputPath = 'voiceover/' . $episode->user_id . '/' . $episode->ulid . '.mp3';

        return app(AudioMixerService::class)->concatenate($segmentPaths, $outputPath);
    }

    public function syncVoices(string $provider): void
    {
        try {
            $client = $this->getClient($provider);
            $voices = $client->listVoices();

            foreach ($voices as $v) {
                VoVoice::updateOrCreate(
                    [
                        'provider' => $provider,
                        'provider_voice_id' => $v['id'],
                    ],
                    [
                        'name' => $v['name'],
                        'gender' => $v['gender'] ?? null,
                        'language' => $v['language'] ?? 'en',
                        'accent' => $v['accent'] ?? null,
                        'preview_url' => $v['preview_url'] ?? null,
                        'labels' => $v['labels'] ?? [],
                        'is_cloned' => $v['is_cloned'] ?? false,
                        'is_active' => true,
                        'synced_at' => now(),
                    ],
                );
            }

            VoVoice::where('provider', $provider)
                ->where('synced_at', '<', now()->subMinutes(5))
                ->update(['is_active' => false]);
        } catch (VoiceoverException $e) {
            throw $e;
        }
    }

    public function getVoicesForProvider(string $provider): Collection
    {
        $voices = VoVoice::forProvider($provider)->active()->orderBy('name')->get();

        if ($voices->isEmpty()) {
            $this->syncVoices($provider);
            $voices = VoVoice::forProvider($provider)->active()->orderBy('name')->get();
        }

        return $voices;
    }

    public function autoSplitScript(string $script): array
    {
        $lines = explode("\n", $script);
        $segments = [];
        $currentSpeaker = null;
        $currentText = '';

        $patterns = [
            '/^(Speaker\s+[A-Z]):\s*(.*)/i',
            '/^\[([^\]]+)\]:\s*(.*)/',
            '/^\*\*([^*]+)\*\*:\s*(.*)/',
            '/^([A-Z][a-z]+):\s*(.*)/',
            '/^(Narrator):\s*(.*)/i',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($currentSpeaker !== null && $currentText !== '') {
                    $segments[] = [
                        'speaker' => $currentSpeaker,
                        'text' => trim($currentText),
                        'voice_id' => null,
                        'provider' => null,
                    ];
                    $currentSpeaker = null;
                    $currentText = '';
                }
                continue;
            }

            $matched = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    if ($currentSpeaker !== null && $currentText !== '') {
                        $segments[] = [
                            'speaker' => $currentSpeaker,
                            'text' => trim($currentText),
                            'voice_id' => null,
                            'provider' => null,
                        ];
                    }
                    $currentSpeaker = $matches[1];
                    $currentText = $matches[2];
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                if ($currentSpeaker === null) {
                    $currentSpeaker = 'Speaker A';
                }
                $currentText .= ($currentText !== '' ? ' ' : '') . $line;
            }
        }

        if ($currentSpeaker !== null && $currentText !== '') {
            $segments[] = [
                'speaker' => $currentSpeaker,
                'text' => trim($currentText),
                'voice_id' => null,
                'provider' => null,
            ];
        }

        return $segments;
    }
}
