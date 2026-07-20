<?php

namespace Addons\SocialScheduler\Database\Factories;

use Addons\SocialScheduler\Models\SsPostAnalytics;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsPostAnalyticsFactory extends Factory
{
    protected $model = SsPostAnalytics::class;

    public function definition(): array
    {
        return [
            'ss_post_platform_id' => SsPostPlatformFactory::new(),
            'platform' => 'twitter',
            'impressions' => fake()->numberBetween(100, 10000),
            'likes' => fake()->numberBetween(0, 500),
            'comments' => fake()->numberBetween(0, 100),
            'shares' => fake()->numberBetween(0, 50),
            'engagement_rate' => fake()->randomFloat(2, 0.1, 5.0),
            'fetched_at' => now(),
        ];
    }
}
