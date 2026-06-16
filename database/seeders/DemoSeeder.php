<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AffiliateReferral;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiUsageLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Comment;
use App\Models\LoginHistory;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Plan;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Demo Admin ─────────────────────────────────────────────
        User::updateOrCreate(['email' => 'admin@demo.com'], [
            'name' => 'Demo Administrator',
            'password' => Hash::make('demo12345'),
            'credits' => 9999,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Also ensure admin account exists in admins table for blog posts
        $superAdminRoleId = AdminRole::where('slug', 'super-admin')->value('id');
        Admin::updateOrCreate(['email' => 'admin@demo.com'], [
            'name' => 'Demo Administrator',
            'password' => Hash::make('demo12345'),
            'role_id' => $superAdminRoleId,
            'is_active' => true,
        ]);

        // ─── 2. Demo User ──────────────────────────────────────────────
        User::updateOrCreate(['email' => 'user@demo.com'], [
            'name' => 'Demo User',
            'password' => Hash::make('demo12345'),
            'credits' => 500,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ─── 3. Fetch or create plans ──────────────────────────────────
        $freePlan = Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'description' => 'Basic access',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'credits' => 100,
            'features' => json_encode(['5 AI templates', 'GPT-4o-mini only', '10 chats', 'Basic support']),
            'is_active' => true,
            'is_free' => true,
            'sort_order' => 1,
        ]);

        $proPlan = Plan::firstOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'description' => 'Professional access',
            'price_monthly' => 19.99,
            'price_yearly' => 199.99,
            'credits' => 5000,
            'features' => json_encode(['All AI templates', 'GPT-4o + GPT-4o-mini', '50 chats', 'Priority support']),
            'is_active' => true,
            'is_free' => false,
            'sort_order' => 2,
        ]);

        $unlimitedPlan = Plan::firstOrCreate(['slug' => 'unlimited'], [
            'name' => 'Unlimited',
            'description' => 'Power user access',
            'price_monthly' => 49.99,
            'price_yearly' => 499.99,
            'credits' => 99999,
            'max_chats' => 999999,
            'features' => json_encode(['All AI templates', 'All AI models', 'Unlimited chats', 'Priority support', 'API access']),
            'is_active' => true,
            'is_free' => false,
            'is_featured' => true,
            'sort_order' => 3,
        ]);

        $plans = [$freePlan->id, $proPlan->id, $unlimitedPlan->id];
        $planNames = ['free', 'pro', 'unlimited'];

        // ─── 4. 50 Sample Users ────────────────────────────────────────
        $demoUsers = [];
        $firstNames = ['Alice', 'Bob', 'Carol', 'David', 'Eve', 'Frank', 'Grace', 'Henry', 'Iris', 'Jack',
            'Karen', 'Leo', 'Maria', 'Nick', 'Olivia', 'Paul', 'Quinn', 'Rachel', 'Sam', 'Tina',
            'Uma', 'Victor', 'Wendy', 'Xander', 'Yuki', 'Zara', 'Aaron', 'Blake', 'Celine', 'Derek',
            'Elsa', 'Finn', 'Gina', 'Hugo', 'Ivy', 'Jake', 'Kira', 'Liam', 'Mona', 'Noah',
            'Oscar', 'Piper', 'Quentin', 'Rosa', 'Steve', 'Tara', 'Ulysses', 'Vera', 'Will', 'Xena'];

        for ($i = 0; $i < 50; $i++) {
            $name = $firstNames[$i];
            $email = strtolower($name) . ($i + 1) . '@demo.com';
            $planIdx = $i % 3;
            $planId = $plans[$planIdx];

            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name . ' Demo',
                'password' => Hash::make('demo12345'),
                'credits' => match ($planNames[$planIdx]) {
                    'free' => random_int(10, 100),
                    'pro' => random_int(1000, 5000),
                    'unlimited' => 99999,
                },
                'plan_id' => $planId,
                'subscription_status' => $planNames[$planIdx] === 'free' ? 'none' : 'active',
                'subscription_ends_at' => $planNames[$planIdx] === 'free' ? null : now()->addMonths(random_int(1, 12)),
                'credits_used_month' => random_int(0, 5000),
                'is_active' => true,
                'email_verified_at' => now()->subDays(random_int(1, 365)),
                'created_at' => now()->subDays(random_int(1, 365)),
                'last_login_at' => now()->subHours(random_int(1, 168)),
            ]);
            $demoUsers[] = $user;
        }

        $adminUser = User::where('email', 'admin@demo.com')->first();
        $toolSlugs = DB::table('ai_tools')
            ->whereNotNull('slug')
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();

        if ($toolSlugs === []) {
            $toolSlugs = [
                'blog-article-generator',
                'seo-meta-description-generator',
                'email-subject-line-generator',
                'ai-chat',
                'code-explainer',
                'product-description-writer',
            ];
        }

        // ─── 5. AI Usage Logs (200 entries) ─────────────────────────────
        $providers = ['openai', 'anthropic', 'google', 'deepseek', 'meta'];
        $models = [
            'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-5.5'],
            'anthropic' => ['claude-sonnet-4-6', 'claude-haiku-4-5', 'claude-opus-4-8'],
            'google' => ['gemini-3.1-pro', 'gemini-3.5-flash'],
            'deepseek' => ['deepseek-v4-pro', 'deepseek-v3'],
            'meta' => ['llama-4-scout-17b', 'llama-3.3-70b'],
        ];
        $types = ['chat', 'text_generation', 'image_generation', 'transcription', 'embedding'];

        for ($i = 0; $i < 200; $i++) {
            $user = $demoUsers[array_rand($demoUsers)];
            $provider = $providers[array_rand($providers)];
            $model = $models[$provider][array_rand($models[$provider])];
            $type = $types[array_rand($types)];
            $toolSlug = $toolSlugs[array_rand($toolSlugs)];
            $inputTokens = random_int(50, 4000);
            $outputTokens = random_int(20, 2000);
            $creditsUsed = round(($inputTokens * 0.001) + ($outputTokens * 0.003), 2);
            $costUsd = round(($inputTokens * 0.00001) + ($outputTokens * 0.00002), 6);

            AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model,
                'type' => $type,
                'tool_slug' => $toolSlug,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
                'credits_used' => max(1, $creditsUsed),
                'response_time_ms' => random_int(750, 9500),
                'status' => 'completed',
                'metadata' => [
                    'demo' => true,
                    'topic' => $toolSlug,
                ],
                'created_at' => now()->subDays(random_int(0, 180))->subHours(random_int(0, 23)),
            ]);
        }

        // ─── 6. AI Chats (10 chatbots with messages) ────────────────────
        $chatTopics = [
            ['title' => 'Marketing Strategy Planning', 'category' => 'marketing'],
            ['title' => 'Python Script Debugging', 'category' => 'development'],
            ['title' => 'Blog Content Ideas', 'category' => 'writing'],
            ['title' => 'SEO Optimization Tips', 'category' => 'marketing'],
            ['title' => 'Data Analysis Help', 'category' => 'analytics'],
            ['title' => 'Product Launch Plan', 'category' => 'business'],
            ['title' => 'Social Media Calendar', 'category' => 'social'],
            ['title' => 'API Integration Guide', 'category' => 'development'],
            ['title' => 'Email Campaign Copy', 'category' => 'writing'],
            ['title' => 'Financial Report Summary', 'category' => 'finance'],
        ];

        foreach ($chatTopics as $i => $topic) {
            $user = $demoUsers[array_rand($demoUsers)];
            $chat = AiChat::create([
                'user_id' => $user->id,
                'title' => $topic['title'],
                'model' => 'gpt-4o-mini',
                'category' => $topic['category'],
                'is_pinned' => $i < 3,
                'created_at' => now()->subDays(random_int(0, 90)),
            ]);

            // Add 4-6 messages per chat
            $msgCount = random_int(4, 6);
            $userMessages = ['Can you help me with this?', 'What would you suggest?',
                'That sounds great, tell me more.', 'Can you elaborate on that?',
                'Give me some examples.', 'How do I implement this?'];
            $assistantMessages = ['Absolutely! Here are some detailed suggestions...',
                'Based on your requirements, I recommend the following approach...',
                'Let me break this down into actionable steps...',
                'Here are several examples you can work with...',
                'The best practice for this scenario would be...',
                'I have analyzed this and here is my detailed response...'];

            for ($m = 0; $m < $msgCount; $m++) {
                AiChatMessage::create([
                    'chat_id' => $chat->id,
                    'role' => 'user',
                    'content' => $userMessages[$m % count($userMessages)],
                    'created_at' => $chat->created_at->addMinutes($m * 2),
                ]);
                AiChatMessage::create([
                    'chat_id' => $chat->id,
                    'role' => 'assistant',
                    'content' => $assistantMessages[$m % count($assistantMessages)],
                    'created_at' => $chat->created_at->addMinutes(($m * 2) + 1),
                ]);
            }
        }

        // ─── 7. Blog Categories & Tags ──────────────────────────────────
        $categories = ['AI News', 'Tutorials', 'Case Studies', 'Product Updates', 'Industry Insights', 'Tips & Tricks'];
        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[] = BlogCategory::firstOrCreate(['slug' => Str::slug($cat)], [
                'name' => $cat,
                'is_active' => true,
            ]);
        }

        $tags = ['AI', 'machine-learning', 'gpt', 'content', 'automation', 'productivity',
            'deep-learning', 'nlp', 'coding', 'design'];
        $tagModels = [];
        foreach ($tags as $tag) {
            $tagModels[] = BlogTag::firstOrCreate(['slug' => $tag], ['name' => ucfirst(str_replace('-', ' ', $tag))]);
        }

        // ─── 8. 30 Blog Posts ───────────────────────────────────────────
        $admin = Admin::where('email', 'admin@demo.com')->first();
        $postTitles = [
            '10 Ways AI Can Transform Your Content Strategy',
            'Getting Started with GPT-5: A Complete Guide',
            'How We Built Our AI-Powered Analytics Dashboard',
            'The Future of Automated Content Creation',
            '5 AI Tools Every Marketer Should Know About',
            'Understanding Large Language Models: A Deep Dive',
            'AI Image Generation: Best Practices for 2026',
            'Building a Chatbot from Scratch: Step by Step',
            'Why Your Business Needs an AI-First Approach',
            'Comparing Top AI Models: Performance Benchmarks',
            'The Rise of Multimodal AI Systems',
            'How AI Is Revolutionizing Customer Support',
            'AI Ethics: Responsible Development Guidelines',
            '10 Prompts That Will Boost Your Productivity',
            'Machine Learning for Non-Technical Founders',
            'How to Fine-Tune Models for Your Industry',
            'The Complete Guide to AI API Integration',
            'AI-Powered SEO: Ranking Higher in 2026',
            'Real-Time AI: Latency Optimization Techniques',
            'Building Scalable AI Infrastructure',
            'How to Choose the Right AI Model for Your Project',
            'The Economics of AI: Cost vs. Value Analysis',
            'AI in Education: Transforming Learning Experiences',
            'Voice AI and Conversational Interfaces Explained',
            'Data Privacy in the Age of AI',
            'AI-Powered Design: From Concept to Creation',
            'The Developer\'s Guide to AI-Assisted Coding',
            'How AI Is Changing the Healthcare Industry',
            'AI for Small Business: Affordable Solutions',
            'The State of AI in 2026: Trends and Predictions',
        ];

        foreach ($postTitles as $i => $title) {
            $post = BlogPost::updateOrCreate(['slug' => Str::slug($title)], [
                'author_id' => $admin ? $admin->id : 1,
                'title' => $title,
                'content' => '<p>' . $this->loremParagraph() . '</p>'
                    . '<h2>Key Insights</h2>'
                    . '<p>' . $this->loremParagraph() . '</p>'
                    . '<h3>Implementation</h3>'
                    . '<p>' . $this->loremParagraph() . '</p>'
                    . '<blockquote><p>' . $this->loremSentence() . '</p></blockquote>'
                    . '<p>' . $this->loremParagraph() . '</p>',
                'excerpt' => $this->loremSentence(),
                'status' => 'published',
                'is_featured' => $i < 6,
                'published_at' => now()->subDays(365 - ($i * 12)),
                'reading_time' => random_int(3, 15),
                'views_count' => random_int(50, 5000),
                'meta_description' => 'Learn about ' . strtolower($title) . ' in this comprehensive guide.',
                'created_at' => now()->subDays(365 - ($i * 12)),
            ]);

            // Attach random categories and tags
            $post->categories()->sync([$catModels[array_rand($catModels)]->id, $catModels[array_rand($catModels)]->id]);
            $post->tags()->sync([$tagModels[array_rand($tagModels)]->id, $tagModels[array_rand($tagModels)]->id, $tagModels[array_rand($tagModels)]->id]);
        }

        // ─── 9. Dashboard Demo Data ───────────────────────────────────
        $generalDepartment = SupportDepartment::where('slug', 'general')->first();
        $technicalDepartment = SupportDepartment::where('slug', 'technical')->first();
        $billingDepartment = SupportDepartment::where('slug', 'billing')->first();
        $blogPosts = BlogPost::published()->latest('published_at')->take(6)->get();

        $recentOauthUsers = collect([$demoUsers[0], $demoUsers[1], $demoUsers[2], $demoUsers[3], $demoUsers[4], $demoUsers[5]])
            ->filter()
            ->values();

        $oauthProviders = ['google', 'github', 'linkedin', 'facebook'];
        foreach ($recentOauthUsers as $index => $user) {
            $provider = $oauthProviders[$index % count($oauthProviders)];
            $user->forceFill([
                'oauth_provider' => $provider,
                'oauth_id' => $provider . '-demo-' . $user->id,
                'created_at' => now()->subDays(random_int(2, 24)),
            ])->save();
        }

        $referralUsers = collect([$demoUsers[6], $demoUsers[7], $demoUsers[8], $demoUsers[9], $demoUsers[10], $demoUsers[11]])
            ->filter()
            ->values();
        foreach ($referralUsers as $index => $user) {
            $referrer = $recentOauthUsers[$index % max(1, $recentOauthUsers->count())] ?? $adminUser;
            $user->forceFill([
                'referred_by' => $referrer?->id,
                'created_at' => now()->subDays(random_int(3, 28)),
            ])->save();
        }

        $paymentRows = [
            ['user' => $adminUser, 'plan' => $proPlan, 'gateway' => 'stripe', 'amount' => 199.00, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Pro annual renewal', 'days' => 1],
            ['user' => $demoUsers[2], 'plan' => $proPlan, 'gateway' => 'stripe', 'amount' => 19.99, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Monthly subscription', 'days' => 2],
            ['user' => $demoUsers[4], 'plan' => $freePlan, 'gateway' => 'paypal', 'amount' => 49.00, 'type' => 'credit_topup', 'status' => 'completed', 'label' => 'Credit top-up', 'days' => 0],
            ['user' => $demoUsers[7], 'plan' => $proPlan, 'gateway' => 'stripe', 'amount' => 29.00, 'type' => 'one_time', 'status' => 'completed', 'label' => 'One-time tool bundle', 'days' => 4],
            ['user' => $demoUsers[9], 'plan' => $unlimitedPlan, 'gateway' => 'stripe', 'amount' => 499.00, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Unlimited annual plan', 'days' => 9],
            ['user' => $demoUsers[12], 'plan' => $proPlan, 'gateway' => 'stripe', 'amount' => 99.00, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Monthly renewal', 'days' => 17],
            ['user' => $demoUsers[15], 'plan' => null, 'gateway' => 'manual', 'amount' => 24.00, 'type' => 'credit_topup', 'status' => 'completed', 'label' => 'Wallet refill', 'days' => 21],
            ['user' => $demoUsers[18], 'plan' => $freePlan, 'gateway' => 'stripe', 'amount' => 39.00, 'type' => 'one_time', 'status' => 'completed', 'label' => 'Premium export pack', 'days' => 26],
            ['user' => $demoUsers[21], 'plan' => $proPlan, 'gateway' => 'paypal', 'amount' => 19.99, 'type' => 'subscription', 'status' => 'failed', 'label' => 'Failed monthly charge', 'days' => 3],
        ];

        foreach ($paymentRows as $index => $row) {
            Payment::updateOrCreate(
                ['gateway_payment_id' => 'demo-pay-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $row['user']->id,
                    'plan_id' => $row['plan']?->id,
                    'gateway' => $row['gateway'],
                    'amount' => $row['amount'],
                    'currency' => 'USD',
                    'status' => $row['status'],
                    'type' => $row['type'],
                    'metadata' => ['demo' => true, 'label' => $row['label']],
                    'created_at' => now()->subDays($row['days'])->setTime(random_int(9, 19), random_int(0, 59)),
                ]
            );
        }

        $loginCountries = [
            ['country' => 'Bangladesh', 'city' => 'Dhaka'],
            ['country' => 'United States', 'city' => 'New York'],
            ['country' => 'United Kingdom', 'city' => 'London'],
            ['country' => 'India', 'city' => 'Bengaluru'],
            ['country' => 'Singapore', 'city' => 'Singapore'],
        ];

        $loginUsers = collect([$adminUser, ...array_slice($demoUsers, 0, 12)])->filter()->values();
        foreach ($loginUsers as $index => $user) {
            $geo = $loginCountries[$index % count($loginCountries)];
            $provider = $user->oauth_provider ?? 'email';
            $createdAt = now()->subDays(random_int(0, 29))->subHours(random_int(0, 23));

            LoginHistory::create([
                'user_id' => $user->id,
                'ip' => '192.168.' . random_int(10, 250) . '.' . random_int(10, 250),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) DemoBrowser/1.0',
                'country' => $geo['country'],
                'city' => $geo['city'],
                'success' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($provider !== 'email') {
                LoginHistory::create([
                    'user_id' => $user->id,
                    'ip' => '10.0.' . random_int(10, 250) . '.' . random_int(10, 250),
                    'user_agent' => 'Mozilla/5.0 Demo OAuth Login',
                    'country' => $geo['country'],
                    'city' => $geo['city'],
                    'success' => true,
                    'created_at' => $createdAt->copy()->addHours(1),
                    'updated_at' => $createdAt->copy()->addHours(1),
                ]);
            }
        }

        $ticketStatuses = ['open', 'in_progress', 'resolved', 'closed'];
        $ticketPriorities = ['low', 'medium', 'high'];
        $ticketSources = ['email', 'web', 'api'];
        $ticketUsers = collect(array_slice($demoUsers, 0, 10))->values();
        $departments = collect([$generalDepartment, $technicalDepartment, $billingDepartment])->filter()->values();

        for ($i = 0; $i < 12; $i++) {
            $user = $ticketUsers[$i % max(1, $ticketUsers->count())] ?? $demoUsers[0];
            $department = $departments[$i % max(1, $departments->count())] ?? null;
            $status = $ticketStatuses[$i % count($ticketStatuses)];
            $createdAt = now()->subDays(14 - $i);

            SupportTicket::updateOrCreate(
                ['ticket_number' => 'DEMO-TKT-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'department_id' => $department?->id,
                    'assigned_to' => $adminUser?->id,
                    'subject' => [
                        'Billing question about invoice timing',
                        'AI response is slower than expected',
                        'Need help with plan upgrade',
                        'Login issue on mobile device',
                    ][$i % 4],
                    'status' => $status,
                    'priority' => $ticketPriorities[$i % count($ticketPriorities)],
                    'source' => $ticketSources[$i % count($ticketSources)],
                    'first_response_at' => in_array($status, ['in_progress', 'resolved', 'closed'], true) ? $createdAt->copy()->addHours(2) : null,
                    'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $createdAt->copy()->addDays(1) : null,
                    'closed_at' => $status === 'closed' ? $createdAt->copy()->addDays(2) : null,
                    'last_reply_at' => $createdAt->copy()->addHours(5),
                    'last_reply_by' => 'admin',
                    'satisfaction_rating' => in_array($status, ['resolved', 'closed'], true) ? random_int(4, 5) : null,
                    'satisfaction_comment' => in_array($status, ['resolved', 'closed'], true) ? 'Issue was resolved quickly.' : null,
                    'user_last_read_at' => $createdAt->copy()->addHours(6),
                    'admin_last_read_at' => $createdAt->copy()->addHours(4),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }

        if ($blogPosts->isNotEmpty()) {
            $commentStatuses = ['approved', 'pending', 'approved', 'spam'];
            foreach ($blogPosts as $index => $post) {
                $user = $demoUsers[$index % count($demoUsers)];
                $createdAt = now()->subDays(random_int(1, 20))->subHours(random_int(0, 18));

                Comment::create([
                    'commentable_type' => BlogPost::class,
                    'commentable_id' => $post->id,
                    'user_id' => $user->id,
                    'content' => [
                        'This looks polished and useful.',
                        'Great breakdown, thanks for sharing.',
                        'Would love to see more examples like this.',
                        'The workflow is clear and actionable.',
                    ][$index % 4],
                    'status' => $commentStatuses[$index % count($commentStatuses)],
                    'likes_count' => random_int(0, 24),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        $affiliatePairs = [
            [$recentOauthUsers[0] ?? $adminUser, $demoUsers[13] ?? null, 'AFF-DEMO-001'],
            [$recentOauthUsers[1] ?? $adminUser, $demoUsers[14] ?? null, 'AFF-DEMO-002'],
            [$recentOauthUsers[2] ?? $adminUser, $demoUsers[15] ?? null, 'AFF-DEMO-003'],
            [$recentOauthUsers[3] ?? $adminUser, $demoUsers[16] ?? null, 'AFF-DEMO-004'],
        ];

        foreach ($affiliatePairs as [$referrer, $referred, $code]) {
            if (! $referrer || ! $referred) {
                continue;
            }

            $landedAt = now()->subDays(random_int(2, 24));
            AffiliateReferral::updateOrCreate(
                ['referral_code' => $code],
                [
                    'referrer_id' => $referrer->id,
                    'referred_id' => $referred->id,
                    'ip_address' => '172.16.' . random_int(10, 250) . '.' . random_int(10, 250),
                    'landed_at' => $landedAt,
                    'converted_at' => $landedAt->copy()->addHours(random_int(1, 48)),
                    'created_at' => $landedAt,
                    'updated_at' => $landedAt,
                ]
            );
        }

        // ─── 10. Revenue / Subscription Data (last 12 months) ───────────
        for ($m = 0; $m < 12; $m++) {
            $month = now()->subMonths(11 - $m);
            DB::table('settings')->updateOrInsert(
                ['key' => 'demo_revenue_' . $month->format('Y_m')],
                [
                    'value' => (string) random_int(1500, 9000),
                    'type' => 'integer',
                    'group' => 'demo_revenue',
                ]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'demo_signups_' . $month->format('Y_m')],
                [
                    'value' => (string) random_int(20, 150),
                    'type' => 'integer',
                    'group' => 'demo_revenue',
                ]
            );
        }

        // ─── 11. Newsletter Campaigns (5 sample) ────────────────────────
        $campaigns = [
            ['subject' => 'Welcome to MakeAI — Start Creating with AI', 'status' => 'sent'],
            ['subject' => 'New Feature: Advanced Chat Mode Is Here', 'status' => 'sent'],
            ['subject' => 'Monthly AI Tips & Tricks Newsletter', 'status' => 'sent'],
            ['subject' => 'Exclusive: Early Access to GPT-5 Integration', 'status' => 'sent'],
            ['subject' => 'Holiday Special: 30% Off Pro Plans', 'status' => 'sent'],
        ];

        foreach ($campaigns as $i => $campaign) {
            DB::table('newsletter_campaigns')->updateOrInsert(
                ['subject' => $campaign['subject']],
                [
                    'content' => '<h2>' . $campaign['subject'] . '</h2>'
                        . '<p>' . $this->loremParagraph() . '</p>'
                        . '<p>' . $this->loremParagraph() . '</p>',
                    'status' => $campaign['status'],
                    'recipient_count' => random_int(300, 2000),
                    'sent_count' => random_int(280, 1900),
                    'opened_count' => random_int(80, 800),
                    'sent_at' => now()->subDays(($i + 1) * 30),
                    'started_at' => now()->subDays(($i + 1) * 30),
                    'finished_at' => now()->subDays(($i + 1) * 30)->addHours(2),
                    'audience' => 'subscribers',
                    'created_at' => now()->subDays(($i + 1) * 30),
                ]
            );
        }

        // ─── 12. Sample Pages ───────────────────────────────────────────
        Page::updateOrCreate(['slug' => 'about-us'], [
            'title' => 'About MakeAI',
            'content' => '<h1>Empowering Creativity with AI</h1>'
                . '<p>MakeAI is the next generation platform for content creators. We provide cutting-edge AI tools that help businesses, developers, and creators produce high-quality content effortlessly.</p>'
                . '<h2>Our Mission</h2>'
                . '<p>To democratize access to advanced AI technology and enable everyone to create amazing content.</p>',
            'excerpt' => 'Learn more about the MakeAI platform and mission.',
            'meta_title' => 'About MakeAI',
            'meta_description' => 'Learn more about the MakeAI platform and mission.',
            'status' => 'published',
            'published_at' => now()->subDays(30),
            'show_title' => true,
            'show_breadcrumbs' => true,
            'show_featured_image' => false,
            'show_sidebar' => false,
            'container_width' => 'default',
            'is_system' => true,
        ]);

        Page::updateOrCreate(['slug' => 'privacy'], [
            'title' => 'Privacy Policy',
            'content' => '<h1>Privacy Policy</h1><p>We take your privacy seriously. This policy describes how we collect, use, and protect your personal information.</p>',
            'excerpt' => 'How we collect, use, and protect your information.',
            'meta_title' => 'Privacy Policy',
            'meta_description' => 'How we collect, use, and protect your information.',
            'status' => 'published',
            'published_at' => now()->subDays(30),
            'show_title' => true,
            'show_breadcrumbs' => true,
            'show_featured_image' => false,
            'show_sidebar' => false,
            'container_width' => 'default',
            'is_system' => true,
        ]);

        Page::updateOrCreate(['slug' => 'terms'], [
            'title' => 'Terms of Service',
            'content' => '<h1>Terms of Service</h1><p>By using MakeAI, you agree to these terms. Please read them carefully.</p>',
            'excerpt' => 'The terms that govern platform usage.',
            'meta_title' => 'Terms of Service',
            'meta_description' => 'The terms that govern platform usage.',
            'status' => 'published',
            'published_at' => now()->subDays(30),
            'show_title' => true,
            'show_breadcrumbs' => true,
            'show_featured_image' => false,
            'show_sidebar' => false,
            'container_width' => 'default',
            'is_system' => true,
        ]);

        // ─── 13. Sample Ads ─────────────────────────────────────────────
        DB::table('ads')->updateOrInsert(
            ['zone' => 'sidebar_top', 'title' => 'Demo Sidebar Banner'],
            [
                'type' => 'image_link',
                'custom_html' => null,
                'image_url' => 'https://via.placeholder.com/300x250',
                'link_url' => 'https://envato.com',
                'link_target' => '_blank',
                'show_to' => 'all',
                'is_active' => true,
                'start_at' => now()->subDays(7),
                'end_at' => now()->addDays(30),
                'impressions' => random_int(500, 2500),
                'clicks' => random_int(20, 140),
                'sort_order' => 0,
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ]
        );

        DB::table('ads')->updateOrInsert(
            ['zone' => 'between_posts', 'title' => 'Demo Feed Script Ad'],
            [
                'type' => 'custom_html',
                'custom_html' => '<div style="text-align:center;padding:1rem;background:#f3f4f6;border-radius:8px">Sponsored content</div>',
                'image_url' => null,
                'link_url' => null,
                'link_target' => '_self',
                'show_to' => 'all',
                'is_active' => true,
                'start_at' => now()->subDays(14),
                'end_at' => now()->addDays(45),
                'impressions' => random_int(1000, 5000),
                'clicks' => random_int(30, 200),
                'sort_order' => 0,
                'created_at' => now()->subDays(14),
                'updated_at' => now()->subDays(14),
            ]
        );

        // ─── 14. Sample Menu Items ──────────────────────────────────────
        $mainMenu = Menu::firstOrCreate(['slug' => 'main'], [
            'name' => 'Main Menu',
        ]);

        $footerMenu = Menu::firstOrCreate(['slug' => 'footer'], [
            'name' => 'Footer Menu',
        ]);

        $aboutPage = Page::where('slug', 'about-us')->first();

        MenuItem::updateOrCreate(['menu_id' => $mainMenu->id, 'label' => 'Home'], [
            'parent_id' => null,
            'type' => 'route',
            'route_name' => 'home',
            'url' => null,
            'page_id' => null,
            'target' => '_self',
            'icon' => 'ti ti-home',
            'badge_text' => null,
            'badge_color' => null,
            'is_active' => true,
            'requires_auth' => 'none',
            'mega_menu' => false,
            'mega_menu_content' => null,
            'sort_order' => 1,
        ]);

        MenuItem::updateOrCreate(['menu_id' => $mainMenu->id, 'label' => 'About'], [
            'parent_id' => null,
            'type' => 'page',
            'url' => null,
            'page_id' => $aboutPage?->id,
            'route_name' => null,
            'target' => '_self',
            'icon' => 'ti ti-info-circle',
            'badge_text' => 'New',
            'badge_color' => 'green',
            'is_active' => true,
            'requires_auth' => 'none',
            'mega_menu' => false,
            'mega_menu_content' => null,
            'sort_order' => 2,
        ]);

        MenuItem::updateOrCreate(['menu_id' => $footerMenu->id, 'label' => 'Privacy'], [
            'parent_id' => null,
            'type' => 'page',
            'url' => null,
            'page_id' => Page::where('slug', 'privacy')->value('id'),
            'route_name' => null,
            'target' => '_self',
            'icon' => null,
            'badge_text' => null,
            'badge_color' => null,
            'is_active' => true,
            'requires_auth' => 'none',
            'mega_menu' => false,
            'mega_menu_content' => null,
            'sort_order' => 1,
        ]);

        MenuItem::updateOrCreate(['menu_id' => $footerMenu->id, 'label' => 'Terms'], [
            'parent_id' => null,
            'type' => 'page',
            'url' => null,
            'page_id' => Page::where('slug', 'terms')->value('id'),
            'route_name' => null,
            'target' => '_self',
            'icon' => null,
            'badge_text' => null,
            'badge_color' => null,
            'is_active' => true,
            'requires_auth' => 'none',
            'mega_menu' => false,
            'mega_menu_content' => null,
            'sort_order' => 2,
        ]);
    }

    private function loremParagraph(): string
    {
        $sentences = [
            'Artificial intelligence is transforming the way we create, communicate, and solve problems.',
            'The rapid advancement of machine learning models has opened new possibilities for automation and creativity.',
            'Businesses are increasingly adopting AI-powered tools to streamline their workflows and boost productivity.',
            'Content creation has never been easier with the latest natural language processing technologies.',
            'Developers can now integrate sophisticated AI capabilities into their applications with minimal effort.',
            'The future of technology lies in the seamless collaboration between humans and intelligent systems.',
            'Data-driven insights are helping companies make better decisions faster than ever before.',
            'Modern AI models can understand context, nuance, and even creative intent with remarkable accuracy.',
        ];

        return implode(' ', [
            $sentences[array_rand($sentences)],
            $sentences[array_rand($sentences)],
            $sentences[array_rand($sentences)],
        ]);
    }

    private function loremSentence(): string
    {
        $sentences = [
            'Discover how AI can revolutionize your daily workflow.',
            'Learn the best practices for implementing machine learning solutions.',
            'Explore cutting-edge techniques in natural language processing.',
            'Unlock the full potential of generative AI for your business.',
            'Stay ahead with the latest trends in artificial intelligence.',
            'Transform your content strategy with intelligent automation.',
        ];

        return $sentences[array_rand($sentences)];
    }
}
