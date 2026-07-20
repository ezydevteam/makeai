<?php

namespace Addons\AiVideoCreator\Services;

use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Models\VcSubtitle;
use Addons\AiVideoCreator\Services\Providers\WhisperClient;

class SubtitleService
{
    public function __construct(private WhisperClient $whisper) {}

    public function generate(VcRender $render, string $format = 'srt', string $language = 'en'): VcSubtitle
    {
        if ($render->status !== 'completed' || ! $render->file_path) {
            throw new \RuntimeException('Video must be completed before generating subtitles.');
        }

        $credits = (int) addon_setting('ai-video-creator', 'credits_subtitles', 10);

        if (! credit_quota_mode() && $render->user->credits < $credits) {
            throw new \App\Exceptions\AI\InsufficientCreditsException(
                $render->user->credits, $credits,
            );
        }

        $subtitle = VcSubtitle::updateOrCreate(
            ['vc_render_id' => $render->id, 'format' => $format],
            [
                'status' => 'queued',
                'language' => $language,
                'provider' => 'whisper',
                'credits_deducted' => $credits,
            ],
        );

        deduct_credits($render->user_id, $credits, 'Subtitle generation: ' . $render->ulid);

        $subtitle->update(['status' => 'processing']);

        $videoPath = storage_path('app/' . $render->file_path);
        if (! file_exists($videoPath)) {
            throw new \RuntimeException('Video file not found at: ' . $videoPath);
        }

        $result = $this->whisper->transcribe($videoPath);

        $content = $this->formatSegments($result['segments'], $format);

        $subtitle->update([
            'status' => 'completed',
            'content' => $content,
            'segments' => $result['segments'],
        ]);

        return $subtitle->fresh();
    }

    private function formatSegments(array $segments, string $format): string
    {
        return match ($format) {
            'srt' => $this->segmentsToSrt($segments),
            'vtt' => $this->segmentsToVtt($segments),
            'json' => json_encode($segments, JSON_PRETTY_PRINT),
            default => '',
        };
    }

    private function segmentsToSrt(array $segments): string
    {
        $lines = [];
        $counter = 1;

        foreach ($segments as $seg) {
            $start = $this->formatSrtTime($seg['start'] ?? 0);
            $end = $this->formatSrtTime($seg['end'] ?? 0);
            $text = $seg['text'] ?? '';

            $lines[] = (string) $counter++;
            $lines[] = "{$start} --> {$end}";
            $lines[] = $text;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function segmentsToVtt(array $segments): string
    {
        $lines = ["WEBVTT\n"];

        foreach ($segments as $seg) {
            $start = $this->formatVttTime($seg['start'] ?? 0);
            $end = $this->formatVttTime($seg['end'] ?? 0);
            $text = $seg['text'] ?? '';

            $lines[] = "{$start} --> {$end}";
            $lines[] = $text;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function formatSrtTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $milliseconds = (int) (($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $milliseconds);
    }

    private function formatVttTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $milliseconds = (int) (($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $milliseconds);
    }
}
