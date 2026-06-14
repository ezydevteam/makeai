<?php

namespace Addons\SocialScheduler\Database\Factories;

use Addons\SocialScheduler\Models\SsSocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SsSocialAccountFactory extends Factory
{
    protected $model = SsSocialAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => 'twitter',
            'platform_user_id' => (string) fake()->randomNumber(9),
            'platform_username' => fake()->userName(),
            'platform_name' => fake()->name(),
            'access_token' => 'test-plaintext-token',
            'is_active' => true,
        ];
    }
}
