<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services\Providers;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Illuminate\Support\Facades\Storage;

abstract class TtsProviderClient
{
    abstract public function generateSpeech(string $text, string $voiceId, array $options = []): string;

    abstract public function listVoices(): array;

    abstract public function getProviderName(): string;

    protected function getApiKey(string $keyName): string
    {
        $key = settings($keyName);
        if (! empty($key)) {
            return $key;
        }

        $key = addon_setting('ai-voiceover', $keyName);
        if (! empty($key)) {
            return $key;
        }

        throw VoiceoverException::apiKeyMissing($this->getProviderName());
    }

    protected function resolveOutputPath(int $userId, string $ulid): string
    {
        $path = 'voiceover/' . $userId . '/' . $ulid . '.mp3';
        Storage::makeDirectory(dirname($path));
        return $path;
    }
}
