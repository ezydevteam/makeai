<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Illuminate\Support\Facades\Storage;

class AudioMixerService
{
    private function getFfmpegPath(): string
    {
        $path = addon_setting('ai-voiceover', 'ffmpeg_path', '/usr/bin/ffmpeg');

        if (! $this->commandExists($path)) {
            throw VoiceoverException::ffmpegNotFound($path);
        }

        return $path;
    }

    public function concatenate(array $inputPaths, string $outputPath): string
    {
        $ffmpeg = escapeshellarg($this->getFfmpegPath());

        $absInputPaths = array_map(fn (string $p) => storage_path('app/' . $p), $inputPaths);
        foreach ($absInputPaths as $path) {
            if (! file_exists($path)) {
                throw VoiceoverException::ffmpegError("Input file not found: {$path}");
            }
        }

        $concatFile = tempnam(sys_get_temp_dir(), 'ffmpeg_concat_') . '.txt';
        $lines = array_map(fn (string $p) => "file '" . str_replace("'", "'\\''", $p) . "'", $absInputPaths);
        file_put_contents($concatFile, implode("\n", $lines));

        Storage::makeDirectory(dirname($outputPath));
        $absOutputPath = storage_path('app/' . $outputPath);

        $cmd = $ffmpeg
            . ' -f concat -safe 0 -i ' . escapeshellarg($concatFile)
            . ' -c copy -y ' . escapeshellarg($absOutputPath);

        $exitCode = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        @unlink($concatFile);

        if ($exitCode !== 0) {
            throw VoiceoverException::ffmpegError(implode("\n", array_slice($output, -5)));
        }

        return $outputPath;
    }

    public function mixWithMusic(string $voicePath, string $musicPath, float $musicVolume, string $outputPath): string
    {
        $ffmpeg = escapeshellarg($this->getFfmpegPath());

        $absVoicePath = storage_path('app/' . $voicePath);
        $absMusicPath = storage_path('app/' . $musicPath);

        if (! file_exists($absVoicePath)) {
            throw VoiceoverException::ffmpegError("Voice file not found: {$absVoicePath}");
        }
        if (! file_exists($absMusicPath)) {
            throw VoiceoverException::ffmpegError("Music file not found: {$absMusicPath}");
        }

        Storage::makeDirectory(dirname($outputPath));
        $absOutputPath = storage_path('app/' . $outputPath);

        $volume = number_format($musicVolume, 2, '.', '');

        $cmd = $ffmpeg
            . ' -i ' . escapeshellarg($absVoicePath)
            . ' -i ' . escapeshellarg($absMusicPath)
            . ' -filter_complex "[1:a]volume=' . $volume . '[music];[0:a][music]amix=inputs=2:duration=first:dropout_transition=3[out]"'
            . ' -map "[out]" -c:a libmp3lame -q:a 2 -y ' . escapeshellarg($absOutputPath);

        $exitCode = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw VoiceoverException::ffmpegError(implode("\n", array_slice($output, -5)));
        }

        return $outputPath;
    }

    public function getDuration(string $storagePath): ?int
    {
        $absPath = storage_path('app/' . $storagePath);
        if (! file_exists($absPath)) {
            return null;
        }

        $ffprobe = str_replace('ffmpeg', 'ffprobe', $this->getFfmpegPath());
        $ffprobe = escapeshellarg($ffprobe);

        $cmd = $ffprobe
            . ' -v error -show_entries format=duration'
            . ' -of default=noprint_wrappers=1:nokey=1'
            . ' ' . escapeshellarg($absPath);

        $output = [];
        exec($cmd . ' 2>&1', $output);

        $duration = (float) ($output[0] ?? 0);

        return $duration > 0 ? (int) round($duration) : null;
    }

    public function generateWaveform(string $storagePath): array
    {
        $absPath = storage_path('app/' . $storagePath);
        if (! file_exists($absPath)) {
            throw VoiceoverException::ffmpegError("Audio file not found: {$absPath}");
        }

        $ffmpeg = escapeshellarg($this->getFfmpegPath());

        $waveformDir = dirname($storagePath);
        $waveformFile = $waveformDir . '/waveform_' . basename($storagePath, '.mp3') . '.png';
        $absWaveformPath = storage_path('app/' . $waveformFile);

        Storage::makeDirectory($waveformDir);

        $cmd = $ffmpeg
            . ' -i ' . escapeshellarg($absPath)
            . ' -filter_complex "aformat=channel_layouts=mono,compand,showwavespic=s=800x80:colors=#10b981"'
            . ' -frames:v 1 -y ' . escapeshellarg($absWaveformPath);

        $exitCode = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw VoiceoverException::ffmpegError(implode("\n", array_slice($output, -5)));
        }

        return [
            'waveform_path' => $waveformFile,
            'waveform_url' => Storage::url($waveformFile),
        ];
    }

    private function commandExists(string $cmd): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $check = shell_exec("where {$cmd} 2>nul");
            return ! empty(trim($check ?? ''));
        }

        $check = shell_exec("command -v {$cmd} 2>/dev/null");
        return ! empty(trim($check ?? ''));
    }
}
