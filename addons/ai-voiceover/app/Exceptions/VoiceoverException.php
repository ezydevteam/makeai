<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Exceptions;

class VoiceoverException extends \RuntimeException
{
    public static function providerError(string $provider, string $message): self
    {
        return new self("[{$provider}] {$message}");
    }

    public static function apiKeyMissing(string $provider): self
    {
        return new self("[{$provider}] API key is not configured.");
    }

    public static function ffmpegNotFound(string $path): self
    {
        return new self("ffmpeg not found at: {$path}");
    }

    public static function ffmpegError(string $output): self
    {
        return new self("ffmpeg failed: {$output}");
    }
}
