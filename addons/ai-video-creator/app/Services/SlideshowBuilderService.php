<?php

namespace Addons\AiVideoCreator\Services;

use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Services\Providers\ElevenLabsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SlideshowBuilderService
{
    public function __construct(private ElevenLabsClient $elevenlabs) {}

    public function build(VcRender $render): string
    {
        $params = $render->provider_settings ?? [];
        $ffmpeg = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg');

        if (! file_exists($ffmpeg) && ! $this->commandExists($ffmpeg)) {
            throw new \RuntimeException('ffmpeg not found at: ' . $ffmpeg);
        }

        $imagePaths = array_map(fn ($p) => storage_path('app/' . $p), $params['images'] ?? []);
        foreach ($imagePaths as $path) {
            if (! file_exists($path)) {
                throw new \RuntimeException("Image not found: {$path}");
            }
        }

        if (empty($imagePaths)) {
            throw new \RuntimeException('No images provided for slideshow.');
        }

        // Generate voiceover
        $voicePath = null;
        if (! empty($params['script'])) {
            $ttsProvider = addon_setting('ai-video-creator', 'tts_provider', 'openai');
            $voicePath = tempnam(sys_get_temp_dir(), 'voice_') . '.mp3';

            if ($ttsProvider === 'elevenlabs') {
                $this->elevenlabs->textToSpeech(
                    $params['script'],
                    $params['voice_id'] ?? '21m00Tcm4TlvDq8ikWAM',
                    $voicePath,
                );
            } elseif ($ttsProvider === 'openai') {
                $apiKey = settings('openai_api_key');
                if (empty($apiKey)) {
                    throw new \RuntimeException('OpenAI API key is not configured for TTS.');
                }

                Http::withToken($apiKey)
                    ->timeout(120)
                    ->withOptions(['sink' => $voicePath])
                    ->post('https://api.openai.com/v1/audio/speech', [
                        'model' => 'tts-1',
                        'input' => $params['script'],
                        'voice' => $params['voice_id'] ?? 'alloy',
                    ]);
            }
        }

        $slideDuration = (int) ($params['slide_duration'] ?? 3);
        $outputPath = 'video-creator/' . $render->user_id . '/' . $render->ulid . '.mp4';
        $absOutputPath = storage_path('app/' . $outputPath);

        Storage::makeDirectory(dirname($outputPath));

        // Build concat file list
        $concatFile = tempnam(sys_get_temp_dir(), 'ffmpeg_concat_') . '.txt';
        $lines = [];
        foreach ($imagePaths as $path) {
            $lines[] = "file '{$path}'";
            $lines[] = "duration {$slideDuration}";
        }
        // ffmpeg concat requires last file twice
        $lastPath = end($imagePaths);
        $lines[] = "file '{$lastPath}'";
        file_put_contents($concatFile, implode("\n", $lines));

        // Build ffmpeg command
        $cmd = escapeshellarg($ffmpeg)
            . ' -f concat -safe 0 -i ' . escapeshellarg($concatFile)
            . ' -vf "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,format=yuv420p"'
            . ' -r 25';

        if ($voicePath && file_exists($voicePath)) {
            $cmd .= ' -i ' . escapeshellarg($voicePath) . ' -shortest';

            if (! empty($params['background_music_path'])) {
                $musicPath = storage_path('app/' . $params['background_music_path']);
                $vol = (float) ($params['music_volume'] ?? 0.3);
                $cmd .= ' -i ' . escapeshellarg($musicPath)
                    . ' -filter_complex "[1:a]volume=1[voice];[2:a]volume=' . $vol . '[music];[voice][music]amix=inputs=2:duration=first[aout]"'
                    . ' -map 0:v -map "[aout]"';
            } else {
                $cmd .= ' -map 0:v -map 1:a';
            }
        }

        $cmd .= ' -c:v libx264 -c:a aac -b:a 192k'
            . ' -movflags +faststart'
            . ' -y ' . escapeshellarg($absOutputPath);

        $exitCode = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        @unlink($concatFile);
        if ($voicePath) {
            @unlink($voicePath);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'ffmpeg failed: ' . implode("\n", array_slice($output, -5)),
            );
        }

        return $outputPath;
    }

    private function commandExists(string $cmd): bool
    {
        $check = PHP_OS_FAMILY === 'Windows'
            ? "where {$cmd}"
            : "command -v {$cmd}";

        return ! empty(trim(shell_exec($check) ?? ''));
    }
}
