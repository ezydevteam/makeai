<?php

namespace Addons\SocialScheduler\Jobs\Publishers;

use Addons\SocialScheduler\Models\SsPostPlatform;
use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Models\SsSocialAccount;
use Addons\SocialScheduler\Services\SocialAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PublishToTwitterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $postId,
        public int $postPlatformId,
        public int $accountId,
    ) {
        $this->queue = 'social';
        $this->tries = 3;
        $this->backoff = [60, 300, 900];
    }

    public function handle(SocialAccountService $accountService): void
    {
        $post = SsScheduledPost::find($this->postId);
        $platform = SsPostPlatform::find($this->postPlatformId);
        $account = SsSocialAccount::find($this->accountId);

        if (! $post || ! $platform || ! $account) {
            return;
        }

        $platform->update(['status' => 'publishing', 'attempt_count' => $platform->attempt_count + 1]);

        $client = $accountService->getApiClient($account);

        // Handle thread
        if ($post->post_type === 'thread') {
            $this->publishThread($client, $post, $platform);
            return;
        }

        // Build tweet payload
        $override = $post->platform_overrides['twitter'] ?? [];
        $text = $override['caption'] ?? ($post->caption . ($post->hashtags ? "\n" . $post->hashtags : ''));
        $text = Str::limit($text, 280);

        $payload = ['text' => $text];

        // Upload media if present
        $media = $post->media()->orderBy('sort_order')->first();
        if ($media) {
            $mediaId = $this->uploadMedia($client, $media->url, $media->mime_type);
            if ($mediaId) {
                $payload['media'] = ['media_ids' => [$mediaId]];
            }
        }

        $response = $client->http->post('https://api.twitter.com/2/tweets', $payload);

        $body = $response->json();

        if ($response->successful() && isset($body['data']['id'])) {
            $platform->update([
                'status' => 'published',
                'external_post_id' => (string) $body['data']['id'],
                'external_post_url' => 'https://x.com/i/status/' . $body['data']['id'],
                'published_at' => now(),
            ]);
        } else {
            $platform->update([
                'status' => 'failed',
                'error_message' => Str::limit($body['detail'] ?? $body['title'] ?? $response->body(), 500),
            ]);
        }
    }

    private function publishThread($client, $post, $platform): void
    {
        $override = $post->platform_overrides['twitter'] ?? [];
        $text = $override['caption'] ?? ($post->caption . ($post->hashtags ? "\n" . $post->hashtags : ''));

        $segments = [];
        $current = '';
        $part = 1;
        $total = 1;

        // Split by \n\n first, then by character limit
        foreach (explode("\n\n", $text) as $paragraph) {
            if (mb_strlen($current . "\n\n" . $paragraph) > 270) {
                if ($current) {
                    $segments[] = $current;
                }
                $current = $paragraph;
            } else {
                $current = $current ? $current . "\n\n" . $paragraph : $paragraph;
            }
        }
        if ($current) {
            $segments[] = $current;
        }

        $total = count($segments);
        $lastTweetId = null;

        foreach ($segments as $i => $segment) {
            $tweetText = mb_strlen($segment) > 270
                ? Str::limit($segment, 270)
                : $segment;

            $tweetText .= " {$part}/{$total}";
            $payload = ['text' => $tweetText];

            if ($lastTweetId) {
                $payload['reply'] = ['in_reply_to_tweet_id' => $lastTweetId];
            }

            $response = $client->http->post('https://api.twitter.com/2/tweets', $payload);
            $body = $response->json();

            if ($response->successful() && isset($body['data']['id'])) {
                $lastTweetId = $body['data']['id'];

                if ($i === 0) {
                    $platform->update([
                        'status' => 'published',
                        'external_post_id' => (string) $lastTweetId,
                        'external_post_url' => 'https://x.com/i/status/' . $lastTweetId,
                        'published_at' => now(),
                    ]);
                }
            } else {
                $platform->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($body['detail'] ?? $body['title'] ?? $response->body(), 500),
                ]);
                return;
            }

            $part++;
        }
    }

    private function uploadMedia($client, string $url, ?string $mimeType): ?string
    {
        try {
            $mediaContent = file_get_contents($url);
            if (! $mediaContent) {
                return null;
            }

            $response = $client->http->attach(
                'media',
                $mediaContent,
                'media.' . ($mimeType === 'video/mp4' ? 'mp4' : 'jpg'),
            )->post('https://upload.twitter.com/1.1/media/upload.json');

            $body = $response->json();
            return $body['media_id_string'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        SsPostPlatform::where('id', $this->postPlatformId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
