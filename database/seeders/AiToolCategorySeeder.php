<?php

namespace Database\Seeders;

use App\Models\AiToolCategory;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Seeder;

/**
 * Seeds the 19 AI tool categories from AI_SaaS_Master_Prompt Part 14.7.
 */
class AiToolCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Blog & Content', 'blog-content', 'pencil', '#6366f1', 'Blog posts, articles, rewriting, summaries, and editorial content.'],
            ['Social Media', 'social-media', 'share', '#8b5cf6', 'Posts, captions, hashtags, scripts, bios, and platform-specific content.'],
            ['Advertising', 'advertising', 'speakerphone', '#ec4899', 'Ad headlines, scripts, copywriting formulas, and paid campaign assets.'],
            ['Email Marketing', 'email-marketing', 'mail', '#22c55e', 'Cold emails, newsletters, sequences, sales emails, and lifecycle messaging.'],
            ['Ecommerce', 'ecommerce', 'shopping-cart', '#f97316', 'Product descriptions, listings, reviews, carts, and store copy.'],
            ['Business', 'business', 'briefcase', '#0ea5e9', 'Plans, strategy, operations, reports, OKRs, and professional workflows.'],
            ['Academic', 'academic', 'school', '#a855f7', 'Essays, lessons, quizzes, research help, and educational materials.'],
            ['Development', 'development', 'code', '#ef4444', 'Code generation, debugging, tests, documentation, and developer productivity.'],
            ['Website & SEO', 'website-seo', 'search', '#14b8a6', 'SEO, landing pages, site copy, schema, and legal web pages.'],
            ['Creative Writing', 'creative-writing', 'palette', '#d946ef', 'Stories, poems, lyrics, scripts, dialogue, and creative ideation.'],
            ['Personal & Career', 'personal-career', 'user', '#10b981', 'Bios, resumes, letters, career negotiation, and personal branding.'],
            ['Health & Fitness', 'health-fitness', 'heartbeat', '#06b6d4', 'Fitness plans, meals, recipes, wellness, and habit support.'],
            ['Real Estate', 'real-estate', 'home', '#64748b', 'Listings, guides, market notes, and investment analysis.'],
            ['Entertainment', 'entertainment', 'sparkles', '#f59e0b', 'Travel, events, trivia, gifts, games, and leisure ideas.'],
            ['Language', 'language', 'language', '#2563eb', 'Translation, grammar, tone, vocabulary, and language learning.'],
            ['Marketing Strategy', 'marketing-strategy', 'chart-line', '#db2777', 'Brand voice, audiences, competitors, positioning, and GTM planning.'],
            ['Customer Support', 'customer-support', 'headset', '#059669', 'Ticket replies, help docs, onboarding, scripts, and support workflows.'],
            ['Legal & Finance', 'legal-finance', 'gavel', '#475569', 'Legal summaries, policies, disclaimers, finance, and fundraising copy.'],
            ['Productivity', 'productivity', 'checkup-list', '#7c3aed', 'Planning, goals, decisions, action plans, guides, and prompts.'],
        ];

        foreach ($categories as $index => [$name, $slug, $icon, $color, $description]) {
            AiToolCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'color' => $color,
                    'is_active' => true,
                    'requires_pro' => false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        AiToolCategory::whereIn('slug', [
            'writer', 'marketing', 'social', 'email', 'seo', 'copywriting', 'code',
            'education', 'legal', 'creative', 'personal', 'translation', 'chat',
        ])->update(['is_active' => false]);

        ToolCatalogCacheService::invalidateAll();
    }
}
