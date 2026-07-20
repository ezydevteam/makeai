<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Free User
        $freePlan = Plan::where('slug', 'free')->first();
        User::firstOrCreate(
            ['email' => 'free@test.com'],
            [
                'name' => 'Free User',
                'password' => Hash::make('password123'),
                'plan_id' => $freePlan?->id,
                'credits' => 100,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // 2. Enterprise User
        $enterprisePlan = Plan::where('slug', 'enterprise')->first();
        User::firstOrCreate(
            ['email' => 'enterprise@test.com'],
            [
                'name' => 'Enterprise User',
                'password' => Hash::make('password123'),
                'plan_id' => $enterprisePlan?->id,
                'credits' => 50000,
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addYear(),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
    }
}
