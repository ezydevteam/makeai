<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PruneRateLimitHits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('low');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoff = now()->subDay()->timestamp;

        $deleted = DB::table('rate_limit_hits')
            ->where('window_start', '<', $cutoff)
            ->delete();

        if ($deleted === 0) {
            // Log warning if decrement returns 0, indicating target row may not exist or nothing to prune
            // Though for delete, 0 just means no rows matched the condition.
        }
    }
}