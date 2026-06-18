<?php

namespace Database\Seeders;

use App\Models\CreditPack;
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
                'ai_models' => ['gpt-4o-mini'],
                'max_tokens_per_request' => 2048,
                'daily_token_limit' => 10000,
                'max_images_per_day' => 3,
                'max_chats' => 10,
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
                'ai_models' => ['gpt-4o', 'gpt-4o-mini'],
                'max_tokens_per_request' => 4096,
                'daily_token_limit' => 50000,
                'max_images_per_day' => 20,
                'max_chats' => 50,
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
                'ai_models' => ['gpt-4o', 'gpt-4o-mini', 'o1', 'o3', 'o4-mini', 'claude-sonnet-4-5', 'gemini-2.5-pro'],
                'max_tokens_per_request' => 8192,
                'daily_token_limit' => 200000,
                'max_images_per_day' => 100,
                'max_chats' => 999999,
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
                'ai_models' => ['*'],
                'max_tokens_per_request' => 16384,
                'daily_token_limit' => 1000000,
                'max_images_per_day' => 999999,
                'max_chats' => 999999,
                'trial_days' => 0,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $p) {
            $features = $p['features'];
            $aiModels = $p['ai_models'];
            unset($p['features'], $p['ai_models']);
            Plan::firstOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, [
                    'features' => json_encode($features),
                    'ai_models' => json_encode($aiModels),
                ])
            );
        }
    }
}
