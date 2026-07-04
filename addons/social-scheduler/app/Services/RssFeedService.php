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
        // SSRF guard: reject non-http(s) URLs and anything that resolves to a
        // private/reserved address. Validate every redirect hop too, so a public
        // URL can't 30x-redirect the server into an internal service.
        $this->assertFetchableUrl($feed->url);

        $response = Http::timeout(20)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'protocols' => ['http', 'https'],
                    'on_redirect' => function ($request, $response, $uri) {
                        $this->assertFetchableUrl((string) $uri);
                    },
                ],
            ])
            ->get($feed->url);

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

    /**
     * Reject feed URLs that aren't plain http(s) or that resolve to a private /
     * reserved IP range (SSRF protection). Applied to the initial URL and to each
     * redirect target.
     *
     * @throws RssFetchException
     */
    private function assertFetchableUrl(string $url): void
    {
        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RssFetchException('Only public http(s) feed URLs are allowed.');
        }

        // Collect every IP the host resolves to.
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
            }
            if ($ips === []) {
                $resolved = gethostbyname($host);
                if ($resolved !== $host) {
                    $ips[] = $resolved;
                }
            }
        }

        $ips = array_filter($ips);
        if ($ips === []) {
            throw new RssFetchException('Could not resolve the feed host.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RssFetchException('Feed URL resolves to a disallowed (internal) address.');
            }
        }
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
