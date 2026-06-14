<?php

namespace Addons\SocialScheduler\Jobs;

use Addons\SocialScheduler\Models\SsPostAnalytics;
use Addons\SocialScheduler\Models\SsPostPlatform;
use Addons\SocialScheduler\Services\SocialAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchPostAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->queue = 'low';
    }

    public function handle(SocialAccountService $accountService): void
    {
        if (! addon_setting('social-scheduler', 'analytics_pull_enabled', true)) {
            return;
        }

        SsPostPlatform::where('status', 'published')
            ->whereNotNull('external_post_id')
            ->where('published_at', '>=', now()->subDays(90))
            ->with('socialAccount')
            ->chunk(50, function ($platformPosts) use ($accountService) {
                foreach ($platformPosts as $pp) {
                    try {
                        $client = $accountService->getApiClient($pp->socialAccount);
                        $metrics = $this->fetchMetrics($pp, $client);

                        SsPostAnalytics::updateOrCreate(
                            ['ss_post_platform_id' => $pp->id],
                            array_merge($metrics, [
                                'platform' => $pp->platform,
                                'fetched_at' => now(),
                            ]),
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Analytics fetch failed for platform post {$pp->id}: " . $e->getMessage());
                    }
                }
            });
    }

    private function fetchMetrics(SsPostPlatform $pp, $client): array
    {
        $defaults = [
            'impressions' => 0, 'reach' => 0, 'likes' => 0,
            'comments' => 0, 'shares' => 0, 'saves' => 0,
            'clicks' => 0, 'video_views' => 0, 'engagement_rate' => 0.00,
        ];

        try {
            $metrics = match ($pp->platform) {
                'instagram' => $this->fetchInstagramMetrics($client, $pp->external_post_id),
                'facebook' => $this->fetchFacebookMetrics($client, $pp->external_post_id),
                'twitter' => $this->fetchTwitterMetrics($client, $pp->external_post_id),
                'linkedin' => $this->fetchLinkedInMetrics($client, $pp->external_post_id),
                default => [],
            };

            $engagementRate = 0.0;
            $impressions = $metrics['impressions'] ?? 0;
            if ($impressions > 0) {
                $engagement = ($metrics['likes'] ?? 0) + ($metrics['comments'] ?? 0) + ($metrics['shares'] ?? 0);
                $engagementRate = round(($engagement / $impressions) * 100, 2);
            }

            return array_merge($defaults, $metrics, ['engagement_rate' => $engagementRate]);
        } catch (\Throwable) {
            return $defaults;
        }
    }

    private function fetchInstagramMetrics($client, $mediaId): array
    {
        $response = $client->http->get("https://graph.facebook.com/v21.0/{$mediaId}/insights", [
            'metric' => 'impressions,reach,likes,comments,saves,shares',
        ]);

        $body = $response->json();
        $metrics = [];

        foreach ($body['data'] ?? [] as $m) {
            $name = $m['name'] ?? '';
            $value = $m['values'][0]['value'] ?? 0;
            if ($name === 'saves') {
                $metrics['saves'] = (int) $value;
            } elseif ($name === 'shares') {
                $metrics['shares'] = (int) $value;
            } else {
                $metrics[$name] = (int) $value;
            }
        }

        return $metrics;
    }

    private function fetchFacebookMetrics($client, $postId): array
    {
        $response = $client->http->get("https://graph.facebook.com/v21.0/{$postId}/insights", [
            'metric' => 'post_impressions,post_engaged_users,post_reactions_by_type_total',
        ]);

        $body = $response->json();
        $metrics = [];

        foreach ($body['data'] ?? [] as $m) {
            $name = $m['name'] ?? '';
            $value = $m['values'][0]['value'] ?? 0;
            $metrics[$name === 'post_impressions' ? 'impressions' : $name] = is_array($value) ? array_sum($value) : (int) $value;
        }

        return $metrics;
    }

    private function fetchTwitterMetrics($client, $tweetId): array
    {
        $response = $client->http->get("https://api.twitter.com/2/tweets/{$tweetId}", [
            'tweet.fields' => 'public_metrics',
        ]);

        $body = $response->json();
        $metrics = $body['data']['public_metrics'] ?? [];

        return [
            'impressions' => $metrics['impression_count'] ?? 0,
            'likes' => $metrics['like_count'] ?? 0,
            'comments' => $metrics['reply_count'] ?? 0,
            'shares' => $metrics['retweet_count'] ?? 0,
            'clicks' => $metrics['url_link_clicks'] ?? 0,
        ];
    }

    private function fetchLinkedInMetrics($client, $ugcPostUrn): array
    {
        $response = $client->http->withHeaders([
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202405',
        ])->get("https://api.linkedin.com/v2/organizationalEntityShareStatistics", [
            'q' => 'organizationalEntity',
            'shares' => [$ugcPostUrn],
        ]);

        $body = $response->json();
        $element = $body['elements'][0] ?? [];

        return [
            'impressions' => $element['impressionCount'] ?? 0,
            'clicks' => $element['clickCount'] ?? 0,
            'likes' => $element['likeCount'] ?? 0,
            'comments' => $element['commentCount'] ?? 0,
            'shares' => $element['shareCount'] ?? 0,
        ];
    }
}
