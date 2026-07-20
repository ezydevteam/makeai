<?php

namespace Addons\AiVideoCreator\Services;

use Addons\AiVideoCreator\Exceptions\StorageLimitException;
use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Services\Providers\DidClient;
use Addons\AiVideoCreator\Services\Providers\HeyGenClient;
use Addons\AiVideoCreator\Services\Providers\KlingClient;
use Addons\AiVideoCreator\Services\Providers\MinimaxClient;
use Addons\AiVideoCreator\Services\Providers\PikaClient;
use Addons\AiVideoCreator\Services\Providers\RunwayClient;
use Addons\AiVideoCreator\Services\Providers\VideoProviderClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VideoProviderService
{
    public function __construct(
        private KlingClient   $kling,
        private RunwayClient  $runway,
        private PikaClient    $pika,
        private MinimaxClient $minimax,
        private HeyGenClient  $heygen,
        private DidClient     $did,
    ) {}

    public function getClient(string $renderType): VideoProviderClient
    {
        return match ($renderType) {
            'text_to_video' => $this->resolveTextVideoClient(),
            'image_to_video' => $this->resolveImageVideoClient(),
            'avatar_video' => $this->resolveAvatarClient(),
            default => throw new VideoProviderException("Unknown render type: {$renderType}"),
        };
    }

    private function resolveTextVideoClient(): VideoProviderClient
    {
        return $this->resolveClient(
            addon_setting('ai-video-creator', 'text_video_provider', 'kling'),
            ['kling' => $this->kling, 'runway' => $this->runway, 'pika' => $this->pika, 'minimax' => $this->minimax],
        );
    }

    private function resolveImageVideoClient(): VideoProviderClient
    {
        return $this->resolveClient(
            addon_setting('ai-video-creator', 'image_video_provider', 'kling'),
            ['kling' => $this->kling, 'runway' => $this->runway, 'pika' => $this->pika],
        );
    }

    private function resolveAvatarClient(): VideoProviderClient
    {
        return $this->resolveClient(
            addon_setting('ai-video-creator', 'avatar_provider', 'heygen'),
            ['heygen' => $this->heygen, 'did' => $this->did],
        );
    }

    private function resolveClient(string $provider, array $map): VideoProviderClient
    {
        $client = $map[$provider] ?? null;
        if (! $client) {
            throw new VideoProviderException("Provider '{$provider}' is not available.");
        }

        return $client;
    }

    public function getProviderName(string $renderType): string
    {
        return match ($renderType) {
            'text_to_video' => addon_setting('ai-video-creator', 'text_video_provider', 'kling'),
            'image_to_video' => addon_setting('ai-video-creator', 'image_video_provider', 'kling'),
            'avatar_video' => addon_setting('ai-video-creator', 'avatar_provider', 'heygen'),
            'slideshow' => 'local',
            default => 'unknown',
        };
    }

    public function calculateCredits(string $type, int $durationSeconds): int
    {
        return match ($type) {
            'text_to_video' => $durationSeconds <= 5
                ? (int) addon_setting('ai-video-creator', 'credits_text_video', 50)
                : (int) addon_setting('ai-video-creator', 'credits_text_video_long', 100),
            'image_to_video' => (int) addon_setting('ai-video-creator', 'credits_image_video', 40),
            'avatar_video' => (int) ceil($durationSeconds / 30) * (int) addon_setting('ai-video-creator', 'credits_avatar_video', 80),
            'slideshow' => (int) ceil($durationSeconds / 60) * (int) addon_setting('ai-video-creator', 'credits_slideshow', 30),
            default => 0,
        };
    }

    public function createRender(User $user, array $params): VcRender
    {
        $this->checkAccess($user);

        $type = $params['type'] ?? 'text_to_video';
        $duration = $params['duration'] ?? 5;
        $credits = $this->calculateCredits($type, $duration);

        if (! credit_quota_mode() && $user->credits < $credits) {
            throw new \App\Exceptions\AI\InsufficientCreditsException($user->credits, $credits);
        }

        $this->checkStorageLimit($user);

        if (! deduct_credits($user->id, $credits, 'Video Creator: ' . $type)) {
            throw new \App\Exceptions\AI\InsufficientCreditsException($user->credits, $credits);
        }

        $autoDeleteDays = (int) addon_setting('ai-video-creator', 'auto_delete_days', 30);

        $provider = $this->getProviderName($type);

        return VcRender::create([
            'user_id' => $user->id,
            'vc_project_id' => $params['project_id'] ?? null,
            'type' => $type,
            'status' => 'queued',
            'provider' => $provider,
            'title' => $params['title'] ?? null,
            'prompt' => $params['prompt'] ?? null,
            'script' => $params['script'] ?? null,
            'duration_seconds' => $duration,
            'aspect_ratio' => $params['aspect_ratio'] ?? '16:9',
            'provider_settings' => $params['provider_settings'] ?? [],
            'input_media_path' => $params['input_media_path'] ?? null,
            'credits_deducted' => $credits,
            'expires_at' => $autoDeleteDays > 0 ? now()->addDays($autoDeleteDays) : null,
        ]);
    }

    public function refundCredits(VcRender $render): void
    {
        $user = $render->user;
        if (! $user) {
            return;
        }

        // Mode-correct: metered mode returns wallet credits; quota mode (Regular
        // license) winds back the consumed daily/monthly allowance instead, so a
        // failed render doesn't keep eating the user's quota.
        $user->refundCredits(
            (float) $render->credits_deducted,
            'Video generation failed — refund: ' . $render->ulid,
            ['render_ulid' => $render->ulid],
        );
    }

    private function checkAccess(User $user): void
    {
        $showTo = addon_setting('ai-video-creator', 'show_to', 'logged_in');

        if ($showTo === 'pro' && ! isProAvailable()) {
            abort(403, translate('Pro subscription required to use Video Creator.'));
        }
    }

    private function checkStorageLimit(User $user): void
    {
        $usedBytes = VcRender::where('user_id', $user->id)
            ->whereNotNull('file_path')
            ->sum('file_size_bytes');

        $usedMB = $usedBytes / 1024 / 1024;
        $maxMB = (int) addon_setting('ai-video-creator', 'max_storage_mb_per_user', 500);

        if ($usedMB >= $maxMB) {
            throw new StorageLimitException("Storage limit reached: {$maxMB} MB. Delete old renders to free space.");
        }
    }
}
