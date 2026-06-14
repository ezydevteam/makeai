<?php

namespace Addons\SocialScheduler\Jobs;

use Addons\SocialScheduler\Models\SsScheduledPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckPostPublishStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $postId)
    {
        $this->tries = 3;
        $this->queue = 'social';
    }

    public function handle(): void
    {
        $post = SsScheduledPost::with('postPlatforms')->find($this->postId);
        if (! $post) {
            return;
        }

        $platforms = $post->postPlatforms;
        if ($platforms->isEmpty()) {
            return;
        }

        $allDone = $platforms->every(fn ($p) => in_array($p->status, ['published', 'failed', 'skipped']));
        if (! $allDone && $this->attempts() < 3) {
            $this->release(300);
            return;
        }

        $publishedCount = $platforms->where('status', 'published')->count();
        $totalCount = $platforms->count();

        $status = match (true) {
            $publishedCount === $totalCount => 'published',
            $publishedCount > 0 => 'partial',
            default => 'failed',
        };

        $post->update([
            'status' => $status,
            'published_at' => $publishedCount > 0 ? now() : null,
        ]);
    }
}
