<?php

namespace Addons\SocialScheduler\Jobs;

use Addons\SocialScheduler\Models\SsPostPlatform;
use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Jobs\Publishers\PublishToFacebookJob;
use Addons\SocialScheduler\Jobs\Publishers\PublishToInstagramJob;
use Addons\SocialScheduler\Jobs\Publishers\PublishToTwitterJob;
use Addons\SocialScheduler\Jobs\Publishers\PublishToLinkedInJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishSocialPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct()
    {
        $this->queue = 'social';
    }

    public function handle(): void
    {
        $posts = SsScheduledPost::due()
            ->with(['postPlatforms.socialAccount'])
            ->limit(50)
            ->get();

        foreach ($posts as $post) {
            $post->update(['status' => 'publishing']);

            foreach ($post->postPlatforms as $pp) {
                if ($pp->status !== 'pending') {
                    continue;
                }

                $account = $pp->socialAccount;

                if (! $account || ! $account->is_active) {
                    $pp->update([
                        'status' => 'skipped',
                        'error_message' => 'Account not found or inactive.',
                    ]);
                    continue;
                }

                if ($account->is_token_expired && ! $account->refresh_token) {
                    $pp->update([
                        'status' => 'skipped',
                        'error_message' => 'Account token expired — reconnect account.',
                    ]);
                    continue;
                }

                $jobClass = match ($pp->platform) {
                    'instagram' => PublishToInstagramJob::class,
                    'facebook' => PublishToFacebookJob::class,
                    'twitter' => PublishToTwitterJob::class,
                    'linkedin' => PublishToLinkedInJob::class,
                    default => null,
                };

                if ($jobClass) {
                    $jobClass::dispatch($post->id, $pp->id, $account->id)
                        ->onQueue('social');
                }
            }

            CheckPostPublishStatus::dispatch($post->id)
                ->delay(now()->addMinutes(10))
                ->onQueue('social');
        }
    }
}
