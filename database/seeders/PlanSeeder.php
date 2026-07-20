<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with basic AI features',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'credits' => 100,
                'features' => ['5 AI templates', 'GPT-4o-mini only', '10 chats', 'Basic support'],
                'is_free' => true,
                'trial_days' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for individuals and freelancers',
                'price_monthly' => 9.99,
                'price_yearly' => 95.88,
                'credits' => 2000,
                'features' => ['All AI templates', 'GPT-4o + GPT-4o-mini', '50 chats', 'Priority support', 'Export content'],
                'trial_days' => 7,
                'sort_order' => 2,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For teams and growing businesses',
                'price_monthly' => 29.99,
                'price_yearly' => 287.88,
                'credits' => 10000,
                'features' => ['All AI templates', 'All AI models', 'Unlimited chats', 'Priority support', 'API access', 'Brand voice', 'Custom templates'],
                'is_featured' => true,
                'trial_days' => 14,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited power for large organizations',
                'price_monthly' => 99.99,
                'price_yearly' => 959.88,
                'price_lifetime' => 4999.00,
                'credits' => 50000,
                'features' => ['Everything in Pro', 'All AI models', 'Unlimited everything', 'Dedicated support', 'Custom integrations', 'SLA guarantee', 'White-label ready'],
                'trial_days' => 0,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $p) {
            // No json_encode here — the model's features mutator handles encoding. Passing an
            // already-encoded string double-encodes it and the features render as characters.
            Plan::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
