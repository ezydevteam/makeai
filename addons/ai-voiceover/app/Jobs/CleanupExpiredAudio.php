<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Jobs;

use Addons\AiVoiceover\Models\VoEpisode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $days = (int) addon_setting('ai-voiceover', 'auto_delete_days', 0);
        if ($days <= 0) {
            return;
        }

        VoEpisode::where('expires_at', '<=', now())
            ->whereNotNull('file_path')
            ->chunk(50, function ($episodes) {
                foreach ($episodes as $episode) {
                    if ($episode->file_path) {
                        Storage::delete($episode->file_path);
                    }
                    if ($episode->waveform_path) {
                        Storage::delete($episode->waveform_path);
                    }
                    $episode->update([
                        'file_path' => null,
                        'file_url' => null,
                        'waveform_path' => null,
                        'waveform_url' => null,
                    ]);
                }
            });
    }
}
