<?php

namespace App\Jobs;

use App\Models\Ad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class IncrementAdImpressions implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $adId) {}

    public function handle(): void
    {
        $updated = Ad::whereKey($this->adId)->increment('impressions');

        if ($updated === 0) {
            Log::warning('IncrementAdImpressions: ad not found', ['ad_id' => $this->adId]);
        }
    }
}
