<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Jobs;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Addons\AiVoiceover\Models\VoEpisode;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TranscribeAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $episodeId)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $episode = VoEpisode::find($this->episodeId);
        if (! $episode || ! $episode->file_path) {
            return;
        }

        $credits = (int) addon_setting('ai-voiceover', 'credits_stt', 10);
        $user = User::find($episode->user_id);

        if (! $user || (! credit_quota_mode() && $user->credits < $credits)) {
            return; // Silent skip — insufficient credits
        }

        // Check file size limit
        $maxSizeMb = (int) addon_setting('ai-voiceover', 'max_file_size_mb', 25);
        $fileSizeMb = ($episode->file_size_bytes ?: 0) / (1024 * 1024);
        if ($fileSizeMb > $maxSizeMb) {
            $episode->update([
                'error_message' => "Audio file exceeds max size of {$maxSizeMb}MB for transcription.",
            ]);
            return;
        }

        deduct_credits($episode->user_id, $credits, 'Audio transcription: ' . $episode->ulid);

        $apiKey = settings('openai_api_key');
        if (empty($apiKey)) {
            $apiKey = addon_setting('ai-voiceover', 'openai_api_key');
        }
        if (empty($apiKey)) {
            return;
        }

        $absPath = storage_path('app/' . $episode->file_path);
        if (! file_exists($absPath)) {
            return;
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->attach('file', file_get_contents($absPath), basename($absPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
                'timestamp_granularities' => ['segment'],
            ]);

        if ($response->failed()) {
            throw new VoiceoverException('Whisper transcription failed: ' . Str::limit($response->body(), 300));
        }

        $json = $response->json();
        $segments = $json['segments'] ?? [];

        $episode->update([
            'transcript_srt' => $this->toSrt($segments),
            'transcript_vtt' => $this->toVtt($segments),
        ]);
    }

    private function toSrt(array $segments): string
    {
        $lines = [];
        $index = 1;

        foreach ($segments as $seg) {
            $start = $this->formatSrtTime((float) ($seg['start'] ?? 0));
            $end = $this->formatSrtTime((float) ($seg['end'] ?? 0));
            $text = trim($seg['text'] ?? '');

            $lines[] = (string) $index;
            $lines[] = "{$start} --> {$end}";
            $lines[] = $text;
            $lines[] = '';
            $index++;
        }

        return implode("\n", $lines);
    }

    private function toVtt(array $segments): string
    {
        $lines = ["WEBVTT", ""];

        foreach ($segments as $seg) {
            $start = $this->formatVttTime((float) ($seg['start'] ?? 0));
            $end = $this->formatVttTime((float) ($seg['end'] ?? 0));
            $text = trim($seg['text'] ?? '');

            $lines[] = "{$start} --> {$end}";
            $lines[] = $text;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function formatSrtTime(float $seconds): string
    {
        $hours = intdiv((int) $seconds, 3600);
        $minutes = intdiv((int) $seconds % 3600, 60);
        $secs = (int) $seconds % 60;
        $millis = (int) (($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $millis);
    }

    private function formatVttTime(float $seconds): string
    {
        $hours = intdiv((int) $seconds, 3600);
        $minutes = intdiv((int) $seconds % 3600, 60);
        $secs = (int) $seconds % 60;
        $millis = (int) (($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $millis);
    }
}
