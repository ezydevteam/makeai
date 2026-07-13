<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Seeder;

class AiToolCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Blog & Content', 'blog-content', 'ti ti-file-text', '#10b981', 'Blog posts, articles, rewriting, summaries, and editorial content.'],
            ['Social Media', 'social-media', 'ti ti-brand-instagram', '#ec4899', 'Posts, captions, hashtags, scripts, bios, and platform-specific content.'],
            ['Advertising', 'advertising', 'ti ti-speakerphone', '#f59e0b', 'Ad headlines, scripts, copywriting formulas, and paid campaign assets.'],
            ['Email Marketing', 'email-marketing', 'ti ti-mail', '#3b82f6', 'Cold emails, newsletters, sequences, sales emails, and lifecycle messaging.'],
            ['Ecommerce', 'ecommerce', 'ti ti-shopping-cart', '#8b5cf6', 'Product descriptions, listings, reviews, carts, and store copy.'],
            ['Business', 'business', 'ti ti-briefcase', '#6366f1', 'Plans, strategy, operations, reports, OKRs, and professional workflows.'],
            ['Academic', 'academic', 'ti ti-school', '#14b8a6', 'Essays, lessons, quizzes, research help, and educational materials.'],
            ['Development', 'development', 'ti ti-code', '#f97316', 'Code generation, debugging, tests, documentation, and developer productivity.'],
            ['Website & SEO', 'website-seo', 'ti ti-world', '#06b6d4', 'SEO, landing pages, site copy, schema, and legal web pages.'],
            ['Creative Writing', 'creative-writing', 'ti ti-pencil', '#a855f7', 'Stories, poems, lyrics, scripts, dialogue, and creative ideation.'],
            ['Personal & Career', 'personal-career', 'ti ti-user', '#64748b', 'Bios, resumes, letters, career negotiation, and personal branding.'],
            ['Health & Fitness', 'health-fitness', 'ti ti-heart', '#ef4444', 'Fitness plans, meals, recipes, wellness, and habit support.'],
            ['Real Estate', 'real-estate', 'ti ti-building', '#78716c', 'Listings, guides, market notes, and investment analysis.'],
            ['Entertainment', 'entertainment', 'ti ti-device-gamepad', '#84cc16', 'Travel, events, trivia, gifts, games, and leisure ideas.'],
            ['Language', 'language', 'ti ti-language', '#0ea5e9', 'Translation, grammar, tone, vocabulary, and language learning.'],
            ['Marketing Strategy', 'marketing-strategy', 'ti ti-chart-arrows', '#d946ef', 'Brand voice, audiences, competitors, positioning, and GTM planning.'],
            ['Customer Support', 'customer-support', 'ti ti-headset', '#22c55e', 'Ticket replies, help docs, onboarding, scripts, and support workflows.'],
            ['Legal & Finance', 'legal-finance', 'ti ti-scale', '#94a3b8', 'Legal summaries, policies, disclaimers, finance, and fundraising copy.'],
            ['Productivity', 'productivity', 'ti ti-checklist', '#fb923c', 'Planning, goals, decisions, action plans, guides, and prompts.'],
            ['Sales', 'sales', 'ti ti-businessplan', '#0d9488', 'Cold outreach, proposals, objection handling, follow-ups, and closing.'],
            ['HR & Recruiting', 'hr-recruiting', 'ti ti-users-group', '#7c3aed', 'Job ads, interviews, offers, onboarding, reviews, and people ops.'],
            ['Podcast & Audio', 'podcast', 'ti ti-microphone-2', '#db2777', 'Episode outlines, show notes, titles, descriptions, and interview questions.'],
            ['AI Prompts', 'ai-prompts', 'ti ti-sparkles', '#2563eb', 'Prompts for image models, ChatGPT, and AI agents.'],
            ['Video', 'video', 'ti ti-video', '#e11d48', 'Scripts, storyboards, shot lists, briefs, and video production planning.'],
            ['Image & Design', 'image-design', 'ti ti-photo', '#7c3aed', 'Captions, design briefs, moodboards, palettes, and image metadata.'],
        ];

        foreach ($categories as $index => [$name, $slug, $icon, $color, $description]) {
            Category::updateOrCreate(
                ['slug' => $slug, 'type' => 'ai_tool'],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'color' => $color,
                    'is_active' => true,
                    'is_system' => true,
                    'requires_pro' => false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        Category::where('type', 'ai_tool')->whereIn('slug', [
            'writer', 'marketing', 'social', 'email', 'seo', 'copywriting', 'code',
            'education', 'legal', 'creative', 'personal', 'translation', 'chat',
        ])->update(['is_active' => false]);

        ToolCatalogCacheService::invalidateAll();
    }
}
