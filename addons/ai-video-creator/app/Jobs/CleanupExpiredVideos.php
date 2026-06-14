<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredVideos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->queue = 'low';
    }

    public function handle(): void
    {
        VcRender::where('expires_at', '<=', now())
            ->whereNotNull('file_path')
            ->chunk(50, function ($renders) {
                foreach ($renders as $render) {
                    if ($render->file_path) {
                        Storage::delete($render->file_path);
                    }
                    if ($render->thumbnail_path) {
                        Storage::delete($render->thumbnail_path);
                    }
                    $render->update([
                        'file_path' => null,
                        'file_url' => null,
                        'thumbnail_path' => null,
                        'thumbnail_url' => null,
                    ]);
                }
            });
    }
}
