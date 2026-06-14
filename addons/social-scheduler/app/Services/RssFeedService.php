<?php

namespace Addons\SocialScheduler\Services;

use Addons\SocialScheduler\Models\SsRssFeed;
use Addons\SocialScheduler\Models\SsScheduledPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RssFetchException extends \RuntimeException {}

class RssFeedService
{
    public function __construct(private AiCaptionService $captionService) {}

    public function fetchNewItems(SsRssFeed $feed): array
    {
        $response = Http::timeout(20)->get($feed->url);

        if (! $response->successful()) {
            throw new RssFetchException("Failed to fetch RSS feed: HTTP {$response->status()}");
        }

        try {
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Throwable) {
            throw new RssFetchException('Failed to parse RSS feed XML.');
        }

        if (! $xml || ! isset($xml->channel->item)) {
            return [];
        }

        $items = [];
        foreach ($xml->channel->item as $item) {
            $guid = (string) ($item->guid ?? $item->link ?? '');

            if ($feed->last_item_guid && $guid === $feed->last_item_guid) {
                break;
            }

            $items[] = [
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'description' => (string) $item->description,
                'guid' => $guid,
            ];
        }

        return array_reverse($items);
    }

    public function createPostFromItem(SsRssFeed $feed, array $item): SsScheduledPost
    {
        $captionPrompt = $feed->caption_prompt
            ?: 'Write a social media post about this article. Include the key insight.';

        $context = "Article title: {$item['title']}\nURL: {$item['link']}\nSummary: " . Str::limit(strip_tags($item['description']), 500);

        $caption = $this->captionService->adaptCaption($context, 'facebook');

        return SsScheduledPost::create([
            'user_id' => $feed->user_id,
            'title' => Str::limit($item['title'], 200),
            'caption' => $caption . "\n\n" . $item['link'],
            'platforms' => $feed->platforms,
            'status' => 'draft',
            'is_rss_auto' => true,
            'rss_feed_id' => $feed->id,
            'scheduled_at' => null,
        ]);
    }
}
