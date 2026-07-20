<?php

namespace Addons\SocialScheduler\Database\Factories;

use Addons\SocialScheduler\Models\SsScheduledPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsScheduledPostFactory extends Factory
{
    protected $model = SsScheduledPost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'caption' => fake()->paragraph(),
            'platforms' => ['twitter', 'facebook'],
            'status' => 'draft',
            'post_type' => 'single',
        ];
    }
}
