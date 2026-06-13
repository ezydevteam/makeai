<?php

namespace App\Jobs;

use App\Models\Ad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TrackAdClick implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $adId) {}

    public function handle(): void
    {
        $updated = Ad::whereKey($this->adId)->increment('clicks');

        if ($updated === 0) {
            Log::warning('TrackAdClick: ad not found', ['ad_id' => $this->adId]);
        }
    }
}
