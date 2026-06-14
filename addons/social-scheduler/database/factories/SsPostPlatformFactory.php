<?php

namespace Addons\SocialScheduler\Database\Factories;

use Addons\SocialScheduler\Models\SsPostPlatform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsPostPlatformFactory extends Factory
{
    protected $model = SsPostPlatform::class;

    public function definition(): array
    {
        return [
            'ss_scheduled_post_id' => SsScheduledPostFactory::new(),
            'ss_social_account_id' => SsSocialAccountFactory::new(),
            'platform' => 'twitter',
            'status' => 'pending',
        ];
    }
}
