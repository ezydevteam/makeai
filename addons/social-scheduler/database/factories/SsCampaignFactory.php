<?php

namespace Addons\SocialScheduler\Database\Factories;

use Addons\SocialScheduler\Models\SsCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsCampaignFactory extends Factory
{
    protected $model = SsCampaign::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word() . ' Campaign',
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
