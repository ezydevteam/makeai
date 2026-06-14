<?php

namespace Addons\AiRepurposer\Jobs;

use Addons\AiRepurposer\Models\RpJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CleanupRepurposerFiles implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $jobs = RpJob::where('source_type', 'file_upload')
            ->whereNotNull('source_path')
            ->where('created_at', '<', now()->subDays(7))
            ->get();

        foreach ($jobs as $job) {
            if ($job->source_path && Storage::disk('local')->exists($job->source_path)) {
                Storage::disk('local')->delete($job->source_path);
                $job->update(['source_path' => null]);
            }
        }
    }
}
