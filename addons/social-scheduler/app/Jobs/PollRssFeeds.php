<?php

namespace Addons\SocialScheduler\Jobs;

use Addons\SocialScheduler\Models\SsRssFeed;
use Addons\SocialScheduler\Services\RssFeedService;
use Addons\SocialScheduler\Services\RssFetchException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollRssFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->queue = 'social';
    }

    public function handle(RssFeedService $service): void
    {
        SsRssFeed::active()->chunk(20, function ($feeds) use ($service) {
            foreach ($feeds as $feed) {
                try {
                    $items = $service->fetchNewItems($feed);

                    foreach ($items as $item) {
                        $service->createPostFromItem($feed, $item);
                    }

                    $lastGuid = $items[0]['guid'] ?? $feed->last_item_guid;

                    $feed->update([
                        'last_polled_at' => now(),
                        'last_item_guid' => $lastGuid,
                        'status' => 'active',
                        'last_error' => null,
                    ]);
                } catch (RssFetchException $e) {
                    $feed->update([
                        'status' => 'error',
                        'last_error' => $e->getMessage(),
                        'last_polled_at' => now(),
                    ]);
                }
            }
        });
    }
}
