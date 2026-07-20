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

class PublishToInstagramJob implements ShouldQueue
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
        $igUserId = $client->pageId;
        $caption = $post->caption . ($post->hashtags ? "\n\n" . $post->hashtags : '');

        $creationId = $this->createMediaContainer($client, $igUserId, $post, $caption);

        if (! $creationId) {
            return;
        }

        // Publish
        $response = $client->http->post("https://graph.facebook.com/v21.0/{$igUserId}/media_publish", [
            'creation_id' => $creationId,
        ]);

        $body = $response->json();

        if ($response->successful() && isset($body['id'])) {
            $platform->update([
                'status' => 'published',
                'external_post_id' => (string) $body['id'],
                'external_post_url' => "https://www.instagram.com/p/" . ($body['shortcode'] ?? $body['id']),
                'published_at' => now(),
            ]);

            // First comment
            if (
                addon_setting('social-scheduler', 'first_comment_enabled', true)
                && $post->first_comment
            ) {
                sleep(5);
                $client->http->post("https://graph.facebook.com/v21.0/{$body['id']}/comments", [
                    'message' => $post->first_comment,
                ]);
            }
        } else {
            $platform->update([
                'status' => 'failed',
                'error_message' => Str::limit($body['error']['message'] ?? $response->body(), 500),
            ]);
        }
    }

    private function createMediaContainer($client, $igUserId, $post, string $caption): ?string
    {
        if ($post->post_type === 'carousel') {
            $slides = $post->carouselSlides()->orderBy('slide_index')->get();
            if ($slides->isEmpty()) {
                return $this->createSingleMediaContainer($client, $igUserId, $post, $caption);
            }

            $children = [];
            foreach ($slides as $slide) {
                $slideMedia = $slide->media()->first();
                if (! $slideMedia) {
                    continue;
                }

                $response = $client->http->post("https://graph.facebook.com/v21.0/{$igUserId}/media", [
                    'image_url' => $slideMedia->url,
                    'is_carousel_item' => true,
                ]);

                $body = $response->json();
                if (isset($body['id'])) {
                    $children[] = $body['id'];
                }
            }

            if (empty($children)) {
                return null;
            }

            $response = $client->http->post("https://graph.facebook.com/v21.0/{$igUserId}/media", [
                'media_type' => 'CAROUSEL',
                'children' => implode(',', $children),
                'caption' => $caption,
            ]);

            $body = $response->json();
            return $body['id'] ?? null;
        }

        return $this->createSingleMediaContainer($client, $igUserId, $post, $caption);
    }

    private function createSingleMediaContainer($client, $igUserId, $post, string $caption): ?string
    {
        $media = $post->media()->orderBy('sort_order')->first();

        if ($media && $media->type === 'video') {
            $response = $client->http->post("https://graph.facebook.com/v21.0/{$igUserId}/media", [
                'media_type' => $post->post_type === 'reel' ? 'REELS' : 'VIDEO',
                'video_url' => $media->url,
                'caption' => $caption,
            ]);
        } elseif ($media) {
            $response = $client->http->post("https://graph.facebook.com/v21.0/{$igUserId}/media", [
                'image_url' => $media->url,
                'caption' => $caption,
            ]);
        } else {
            // No media — Instagram requires media, mark as skipped
            SsPostPlatform::where('id', $this->postPlatformId)->update([
                'status' => 'skipped',
                'error_message' => 'Instagram requires an image or video.',
            ]);
            return null;
        }

        $body = $response->json();
        return $body['id'] ?? null;
    }

    public function failed(\Throwable $e): void
    {
        SsPostPlatform::where('id', $this->postPlatformId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
