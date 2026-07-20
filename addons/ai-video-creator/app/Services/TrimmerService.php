<?php

namespace Addons\AiVideoCreator\Services;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Support\Facades\Storage;

class TrimmerService
{
    public function trim(VcRender $render, float $startSeconds, float $endSeconds): string
    {
        if ($startSeconds < 0) {
            throw new \InvalidArgumentException('Start time must be >= 0.');
        }

        if ($endSeconds <= $startSeconds) {
            throw new \InvalidArgumentException('End time must be after start time.');
        }

        if (($endSeconds - $startSeconds) < 1) {
            throw new \InvalidArgumentException('Trimmed video must be at least 1 second.');
        }

        if (! $render->file_path) {
            throw new \RuntimeException('No video file available to trim.');
        }

        $ffmpeg = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg');
        $inputPath = storage_path('app/' . $render->file_path);
        $outputPath = 'video-creator/' . $render->user_id . '/' . $render->ulid . '_trimmed.mp4';
        $absOutput = storage_path('app/' . $outputPath);

        $duration = $endSeconds - $startSeconds;

        $cmd = escapeshellarg($ffmpeg)
            . ' -i ' . escapeshellarg($inputPath)
            . ' -ss ' . escapeshellarg((string) $startSeconds)
            . ' -t ' . escapeshellarg((string) $duration)
            . ' -c copy'
            . ' -avoid_negative_ts make_zero'
            . ' -y ' . escapeshellarg($absOutput);

        $exitCode = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'Trim failed: ' . implode(' ', array_slice($output, -3)),
            );
        }

        // Replace original with trimmed
        Storage::delete($render->file_path);
        Storage::move($outputPath, $render->file_path);

        $render->update([
            'duration_actual' => (int) $duration,
            'file_size_bytes' => Storage::size($render->file_path),
        ]);

        return $render->file_path;
    }

    public function extractThumbnail(string $storagePath): ?string
    {
        $ffmpeg = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg');
        $inputPath = storage_path('app/' . $storagePath);
        $thumbPath = str_replace('.mp4', '_thumb.jpg', $storagePath);
        $absThumb = storage_path('app/' . $thumbPath);

        $cmd = escapeshellarg($ffmpeg)
            . ' -i ' . escapeshellarg($inputPath)
            . ' -ss 00:00:01.000 -vframes 1'
            . ' -vf "scale=480:-1"'
            . ' -y ' . escapeshellarg($absThumb);

        $exitCode = 0;
        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        return $exitCode === 0 ? $thumbPath : null;
    }
}
