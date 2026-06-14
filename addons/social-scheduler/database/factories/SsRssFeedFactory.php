<?php

namespace Addons\SocialScheduler\Database\Factories;

use Addons\SocialScheduler\Models\SsRssFeed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsRssFeedFactory extends Factory
{
    protected $model = SsRssFeed::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => 'https://example.com/feed.xml',
            'platforms' => ['twitter'],
            'status' => 'active',
        ];
    }
}
