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

class PublishToLinkedInJob implements ShouldQueue
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
        $userId = $account->platform_user_id;

        $media = $post->media()->orderBy('sort_order')->first();
        $mediaAssets = [];

        if ($media) {
            $assetUrn = $this->registerUpload($client, $media->url, $media->mime_type, $userId);
            if ($assetUrn) {
                $mediaAssets = [['status' => 'READY', 'media' => $assetUrn]];
            }
        }

        $caption = $post->caption . ($post->hashtags ? "\n\n" . $post->hashtags : '');

        $payload = [
            'author' => "urn:li:person:{$userId}",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $caption],
                    'shareMediaCategory' => $mediaAssets ? 'IMAGE' : 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        if ($mediaAssets) {
            $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = $mediaAssets;
        }

        $response = $client->http->withHeaders([
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202405',
        ])->post('https://api.linkedin.com/v2/ugcPosts', $payload);

        $body = $response->json();

        if ($response->successful() && isset($body['id'])) {
            $postId = Str::afterLast($body['id'], ':');

            $platform->update([
                'status' => 'published',
                'external_post_id' => $body['id'],
                'external_post_url' => "https://www.linkedin.com/feed/update/{$body['id']}",
                'published_at' => now(),
            ]);
        } else {
            $platform->update([
                'status' => 'failed',
                'error_message' => Str::limit($body['message'] ?? $response->body(), 500),
            ]);
        }
    }

    private function registerUpload($client, string $mediaUrl, ?string $mimeType, string $userId): ?string
    {
        try {
            // Register upload
            $registerResp = $client->http->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202405',
            ])->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => "urn:li:person:{$userId}",
                    'serviceRelationships' => [['relationshipType' => 'OWNER', 'identifier' => 'urn:li:userGeneratedContent']],
                ],
            ]);

            $registerBody = $registerResp->json();

            if (! isset($registerBody['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'])) {
                return null;
            }

            $uploadUrl = $registerBody['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $asset = $registerBody['value']['asset'];

            // Upload the actual image
            $imageContent = file_get_contents($mediaUrl);
            if ($imageContent) {
                $client->http->withBody($imageContent, $mimeType ?? 'image/jpeg')
                    ->put($uploadUrl);
            }

            return $asset;
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
