<?php

namespace App\Jobs;

use App\Models\Ad;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TrackAdClick implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $adId) {}

    public function handle(): void
    {
        Ad::whereKey($this->adId)->increment('clicks');
    }
}
