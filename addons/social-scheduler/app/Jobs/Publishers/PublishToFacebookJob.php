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

class PublishToFacebookJob implements ShouldQueue
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
        $pageId = $client->pageId;

        $media = $post->media()->orderBy('sort_order')->first();
        $caption = $post->caption . ($post->hashtags ? "\n\n" . $post->hashtags : '');

        if ($media && $media->type === 'video') {
            $response = $client->http->post("https://graph.facebook.com/v21.0/{$pageId}/videos", [
                'file_url' => $media->url,
                'description' => $caption,
            ]);
        } elseif ($media) {
            $response = $client->http->post("https://graph.facebook.com/v21.0/{$pageId}/photos", [
                'url' => $media->url,
                'message' => $caption,
            ]);
        } else {
            $response = $client->http->post("https://graph.facebook.com/v21.0/{$pageId}/feed", [
                'message' => $caption,
            ]);
        }

        $body = $response->json();

        if ($response->successful() && isset($body['id'])) {
            $platform->update([
                'status' => 'published',
                'external_post_id' => (string) $body['id'],
                'external_post_url' => "https://www.facebook.com/{$pageId}/posts/" . explode('_', $body['id'])[1] ?? $body['id'],
                'published_at' => now(),
            ]);
        } else {
            $platform->update([
                'status' => 'failed',
                'error_message' => Str::limit($body['error']['message'] ?? $response->body(), 500),
            ]);
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
