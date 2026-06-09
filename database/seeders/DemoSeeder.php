<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Admin;
use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiUsageLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Plan;
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
            'role' => 'admin',
            'credits' => 9999,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Also ensure admin account exists in admins table for blog posts
        Admin::updateOrCreate(['email' => 'admin@demo.com'], [
            'name' => 'Demo Administrator',
            'password' => Hash::make('demo12345'),
            'is_active' => true,
        ]);

        // ─── 2. Demo User ──────────────────────────────────────────────
        User::updateOrCreate(['email' => 'user@demo.com'], [
            'name' => 'Demo User',
            'password' => Hash::make('demo12345'),
            'role' => 'user',
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
                'role' => 'user',
                'credits' => match ($planNames[$planIdx]) {
                    'free' => random_int(10, 100),
                    'pro' => random_int(1000, 5000),
                    'unlimited' => 99999,
                },
                'plan_id' => $planId,
                'subscription_status' => $planNames[$planIdx] === 'free' ? 'inactive' : 'active',
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
            $inputTokens = random_int(50, 4000);
            $outputTokens = random_int(20, 2000);
            $creditsUsed = round(($inputTokens * 0.001) + ($outputTokens * 0.003), 2);

            AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model,
                'type' => $type,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'credits_used' => max(1, $creditsUsed),
                'status' => 'completed',
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

        // ─── 9. Revenue / Subscription Data (last 12 months) ────────────
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

        // ─── 10. Newsletter Campaigns (5 sample) ────────────────────────
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

        // ─── 11. Sample Pages ───────────────────────────────────────────
        Page::updateOrCreate(['slug' => 'about-us'], [
            'title' => 'About MakeAI',
            'content' => '<h1>Empowering Creativity with AI</h1>'
                . '<p>MakeAI is the next generation platform for content creators. We provide cutting-edge AI tools that help businesses, developers, and creators produce high-quality content effortlessly.</p>'
                . '<h2>Our Mission</h2>'
                . '<p>To democratize access to advanced AI technology and enable everyone to create amazing content.</p>',
            'is_active' => true,
        ]);

        Page::updateOrCreate(['slug' => 'privacy'], [
            'title' => 'Privacy Policy',
            'content' => '<h1>Privacy Policy</h1><p>We take your privacy seriously. This policy describes how we collect, use, and protect your personal information.</p>',
            'is_active' => true,
        ]);

        Page::updateOrCreate(['slug' => 'terms'], [
            'title' => 'Terms of Service',
            'content' => '<h1>Terms of Service</h1><p>By using MakeAI, you agree to these terms. Please read them carefully.</p>',
            'is_active' => true,
        ]);

        // ─── 12. Sample Ads ─────────────────────────────────────────────
        Ad::updateOrCreate(['zone' => 'sidebar_top', 'title' => 'Demo Banner'], [
            'type' => 'image_link',
            'image_url' => 'https://via.placeholder.com/300x250',
            'link_url' => 'https://envato.com',
            'link_target' => '_blank',
            'show_to' => 'all',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Ad::updateOrCreate(['zone' => 'content_top', 'title' => 'Demo HTML Ad'], [
            'type' => 'custom_html',
            'custom_html' => '<div style="text-align:center;padding:1rem;background:#f3f4f6;border-radius:8px">Sponsored content</div>',
            'show_to' => 'all',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // ─── 13. Sample Menu Items ──────────────────────────────────────
        MenuItem::updateOrCreate(['label' => 'Home'], [
            'type' => 'route',
            'value' => 'home',
            'order' => 1,
        ]);

        MenuItem::updateOrCreate(['label' => 'About'], [
            'type' => 'page',
            'value' => 'about-us',
            'order' => 2,
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
