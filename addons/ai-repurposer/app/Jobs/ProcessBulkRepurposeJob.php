<?php

namespace Addons\AiRepurposer\Jobs;

use Addons\AiRepurposer\Models\RpJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBulkRepurposeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public readonly string $batchId) {}

    public function handle(): void
    {
        $jobs = RpJob::bulkBatch($this->batchId)
            ->where('status', 'queued')
            ->get();

        $delay = 0;
        foreach ($jobs as $job) {
            ProcessRepurposeJob::dispatch($job->id)
                ->onQueue('ai')
                ->delay(now()->addSeconds($delay));

            $delay += 5;
        }
    }
}
