<?php

namespace Database\Seeders;

use App\Models\AiTemplate;
use App\Models\AiToolCategory;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds the 255 predefined AI tools from AI_SaaS_Master_Prompt Part 14.7.
 */
class AiTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $categories = AiToolCategory::where('is_active', true)->get()->keyBy('slug');
        $catalog = $this->catalog();
        $expectedTotal = 255;
        $actualTotal = collect($catalog)->flatten(1)->count();

        if ($actualTotal !== $expectedTotal) {
            throw new RuntimeException("AI tool catalog must contain {$expectedTotal} tools; {$actualTotal} found.");
        }

        $sortOrder = 1;
        $activeSlugs = [];

        foreach ($catalog as $categorySlug => $tools) {
            $category = $categories->get($categorySlug);

            if (! $category) {
                throw new RuntimeException("Missing AI tool category: {$categorySlug}");
            }

            foreach ($tools as $tool) {
                $activeSlugs[] = $tool['slug'];

                AiTemplate::updateOrCreate(
                    ['slug' => $tool['slug']],
                    $this->payload($tool, $category, $sortOrder++)
                );
            }

            $category->updateToolsCount();
        }

        AiTemplate::whereNotIn('slug', $activeSlugs)->update(['is_active' => false]);

        foreach ($categories as $category) {
            $category->updateToolsCount();
        }

        ToolCatalogCacheService::invalidateAll();
    }

    private function payload(array $tool, AiToolCategory $category, int $sortOrder): array
    {
        $name = $tool['name'] ?? Str::headline($tool['slug']);
        $description = $tool['description'] ?? $this->description($name, $category->name);
        $outputType = $tool['output_type'] ?? $this->outputType($tool['slug'], $category->slug);

        return [
            'name' => $name,
            'description' => $description,
            'prompt' => $this->promptUser($name),
            'prompt_system' => $this->promptSystem($name, $category->name, $outputType),
            'prompt_user' => $this->promptUser($name),
            'category' => $category->slug,
            'category_id' => $category->id,
            'icon' => $tool['icon'] ?? $category->icon,
            'color' => $tool['color'] ?? $category->color,
            'fields' => json_encode($this->fieldsFor($category->slug, $tool['slug'])),
            'output_type' => $outputType,
            'model_override' => null,
            'max_tokens_override' => $this->maxTokens($category->slug, $tool['slug']),
            'access_level' => 'inherit',
            'is_premium' => false,
            'is_active' => true,
            'is_featured' => in_array($tool['slug'], $this->featuredSlugs(), true),
            'requires_pro' => in_array($category->slug, ['legal-finance', 'business', 'development'], true)
                && str_contains($tool['slug'], 'analysis'),
            'supports_brand_voice' => ! in_array($category->slug, ['development', 'academic', 'language'], true),
            'avg_output_tokens' => $this->avgOutputTokens($category->slug, $tool['slug']),
            'sort_order' => $sortOrder,
            'about_content' => "{$name} helps users create polished {$category->name} output from a simple guided form.",
            'how_it_works' => json_encode([
                ['step' => 1, 'title' => 'Add details', 'description' => 'Fill in the main topic, audience, and any required context.'],
                ['step' => 2, 'title' => 'Choose style', 'description' => 'Select tone, language, format, and output length where needed.'],
                ['step' => 3, 'title' => 'Generate', 'description' => 'Review the AI output, then copy, export, save, or regenerate it.'],
            ]),
            'usage_examples' => json_encode([
                [
                    'title' => "Example {$category->name} request",
                    'input' => ['topic' => 'Launch plan for a new AI writing app', 'audience' => 'Small business owners'],
                    'output' => "A structured, ready-to-edit {$category->name} response tailored to the request.",
                ],
            ]),
            'faq_items' => json_encode([
                ['question' => "Can I edit the {$name} output?", 'answer' => 'Yes. Generated content is a draft that you can copy, save, export, and refine.'],
                ['question' => 'Can admins change this tool?', 'answer' => 'Yes. Admins can edit prompts, fields, SEO data, and page content from the template editor.'],
            ]),
            'show_about' => true,
            'show_how_it_works' => true,
            'show_usage_examples' => true,
            'show_faqs' => true,
            'show_reviews' => true,
            'show_related_tools' => true,
            'meta_title' => "{$name} | Free AI Tool",
            'meta_description' => $description,
            'og_image' => null,
        ];
    }

    private function promptSystem(string $name, string $categoryName, string $outputType): string
    {
        return <<<PROMPT
You are an expert {$categoryName} specialist using the "{$name}" tool.

Rules:
1. Produce practical, specific, ready-to-use output.
2. Follow the requested tone, audience, language, length, and format.
3. Avoid filler, vague claims, hallucinated facts, and unsupported legal/medical guarantees.
4. If data is missing, make reasonable assumptions and keep the result editable.
5. Format the response as clean {$outputType}.

Always respond in {language}.
PROMPT;
    }

    private function promptUser(string $name): string
    {
        return <<<PROMPT
Tool: {$name}
Topic or task: {topic}
Description or context: {description}
Target audience: {audience}
Tone: {tone}
Output language: {language}
Output length: {length}
Preferred format: {format}
Keywords or key points: {keywords}
Additional instructions: {additional}
PROMPT;
    }

    private function fieldsFor(string $categorySlug, string $toolSlug): array
    {
        if ($categorySlug === 'development') {
            return [
                ['name' => 'topic', 'key' => 'topic', 'label' => 'Task', 'type' => 'textarea', 'required' => true, 'rows' => 5, 'placeholder' => 'Describe the code, bug, API, test, or developer task.'],
                ['name' => 'description', 'key' => 'description', 'label' => 'Existing Code or Context', 'type' => 'code_input', 'required' => false],
                ['name' => 'format', 'key' => 'format', 'label' => 'Programming Language', 'type' => 'select', 'options' => ['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust', 'SQL', 'Java', 'C#'], 'default' => 'PHP'],
                ['name' => 'language', 'key' => 'language', 'label' => 'Response Language', 'type' => 'language_select', 'default' => 'English'],
                ['name' => 'additional', 'key' => 'additional', 'label' => 'Extra Requirements', 'type' => 'textarea', 'required' => false, 'rows' => 3],
            ];
        }

        if ($categorySlug === 'language') {
            return [
                ['name' => 'topic', 'key' => 'topic', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'rows' => 6, 'placeholder' => 'Paste the text to translate, rewrite, explain, or improve.'],
                ['name' => 'language', 'key' => 'language', 'label' => 'Target Language', 'type' => 'language_select', 'default' => 'English'],
                ['name' => 'tone', 'key' => 'tone', 'label' => 'Tone', 'type' => 'tone_select', 'default' => 'Professional'],
                ['name' => 'format', 'key' => 'format', 'label' => 'Output Format', 'type' => 'select', 'options' => ['Paragraph', 'Bullet Points', 'Table', 'Side-by-side'], 'default' => 'Paragraph'],
                ['name' => 'additional', 'key' => 'additional', 'label' => 'Extra Instructions', 'type' => 'textarea', 'required' => false, 'rows' => 3],
            ];
        }

        $fields = [
            ['name' => 'topic', 'key' => 'topic', 'label' => 'Topic or Task', 'type' => 'text', 'required' => true, 'max_length' => 200],
            ['name' => 'description', 'key' => 'description', 'label' => 'Context', 'type' => 'textarea', 'required' => false, 'rows' => 4],
            ['name' => 'audience', 'key' => 'audience', 'label' => 'Target Audience', 'type' => 'text', 'required' => false],
            ['name' => 'tone', 'key' => 'tone', 'label' => 'Tone of Voice', 'type' => 'tone_select', 'default' => 'Professional'],
            ['name' => 'language', 'key' => 'language', 'label' => 'Output Language', 'type' => 'language_select', 'default' => 'English'],
            ['name' => 'length', 'key' => 'length', 'label' => 'Output Length', 'type' => 'length_select', 'default' => 'medium'],
            ['name' => 'format', 'key' => 'format', 'label' => 'Format', 'type' => 'select', 'options' => ['Paragraph', 'Bullet Points', 'Numbered List', 'Table', 'Markdown'], 'default' => 'Markdown'],
            ['name' => 'keywords', 'key' => 'keywords', 'label' => 'Keywords or Key Points', 'type' => 'tags_input', 'required' => false],
            ['name' => 'additional', 'key' => 'additional', 'label' => 'Additional Instructions', 'type' => 'textarea', 'required' => false, 'rows' => 3],
        ];

        if (str_contains($toolSlug, 'email')) {
            array_splice($fields, 2, 0, [
                ['name' => 'recipient', 'key' => 'recipient', 'label' => 'Recipient', 'type' => 'text', 'required' => false],
            ]);
        }

        if (str_contains($toolSlug, 'ads') || str_contains($toolSlug, 'ad-') || str_contains($toolSlug, '-ad')) {
            array_splice($fields, 2, 0, [
                ['name' => 'platform', 'key' => 'platform', 'label' => 'Platform', 'type' => 'select', 'options' => ['Google', 'Facebook', 'Instagram', 'LinkedIn', 'TikTok', 'YouTube'], 'default' => 'Google'],
            ]);
        }

        return $fields;
    }

    private function outputType(string $slug, string $categorySlug): string
    {
        if ($categorySlug === 'development' && in_array($slug, ['code-generator', 'bug-fixer', 'unit-test', 'regex-generator', 'sql-query-generator', 'refactor-code', 'dockerfile-generator'], true)) {
            return 'code';
        }

        if (str_contains($slug, 'ideas') || str_contains($slug, 'generator') || str_contains($slug, 'questions') || str_contains($slug, 'hashtags')) {
            return 'list';
        }

        return 'markdown';
    }

    private function maxTokens(string $categorySlug, string $slug): int
    {
        if (in_array($categorySlug, ['business', 'academic', 'legal-finance'], true)) {
            return 3000;
        }
        if (in_array($slug, ['blog-article', 'ebook-generator', 'business-plan', 'privacy-policy', 'terms-conditions'], true)) {
            return 4096;
        }

        return 2048;
    }

    private function avgOutputTokens(string $categorySlug, string $slug): int
    {
        if (in_array($slug, ['blog-article', 'ebook-generator', 'business-plan'], true)) {
            return 1600;
        }
        if (in_array($categorySlug, ['social-media', 'advertising', 'email-marketing'], true)) {
            return 350;
        }
        if ($categorySlug === 'development') {
            return 900;
        }

        return 700;
    }

    private function description(string $name, string $categoryName): string
    {
        return "Generate polished {$categoryName} content for {$name} with structured prompts, editable output, and production-ready formatting.";
    }

    private function featuredSlugs(): array
    {
        return [
            'blog-article', 'instagram-caption', 'cold-email', 'product-description',
            'business-plan', 'code-generator', 'meta-seo', 'translator',
        ];
    }

    private function catalog(): array
    {
        return [
            'blog-content' => $this->tools([
                'blog-article', 'blog-intro', 'blog-outline', 'blog-conclusion', 'blog-section',
                'blog-ideas', 'article-rewriter', 'content-improver', 'paragraph-generator',
                'tldr-summary', 'explain-to-child', 'news-article', 'press-release',
                'ebook-generator', 'undetectable-rewriter', 'proofreading', 'rephrase',
                'text-expander', 'text-summarizer', 'rss-content', 'youtube-to-blog',
                'wiki-to-article',
            ]),
            'social-media' => $this->tools([
                'instagram-caption', 'instagram-hashtags', 'instagram-reel-script', 'tiktok-script',
                'tiktok-caption', 'facebook-post', 'facebook-headline', 'facebook-video-script',
                'twitter-tweet', 'twitter-thread', 'viral-tweet-ideas', 'linkedin-post',
                'linkedin-summary', 'youtube-title', 'youtube-description', 'youtube-tags',
                'youtube-to-blog-post', 'pinterest-description', 'social-media-reply', 'ama-post',
                'trending-ideas', 'clickbait-title', 'social-bio', 'content-calendar',
                'hashtag-strategy', 'video-description', 'video-ideas',
            ]),
            'advertising' => $this->tools([
                'google-ads-headline', 'google-ads-description', 'facebook-ad', 'instagram-ad',
                'linkedin-ad', 'youtube-ads-script', 'tiktok-ad-script', 'display-ad-copy',
                'native-ad-copy', 'aida', 'pas', 'before-after-bridge', 'ad-hook-generator',
                'retargeting-ad', 'product-ad-copy', 'app-install-ad', 'classified-ad',
            ]),
            'email-marketing' => $this->tools([
                'cold-email', 'follow-up-email', 'welcome-email', 'newsletter-content',
                'sales-email', 'subject-lines', 'email-sequence', 'drip-campaign',
                'reactivation-email', 'thank-you-email', 'apology-email', 'event-invitation-email',
                'product-launch-email', 'testimonial-request-email', 'referral-email',
            ]),
            'ecommerce' => $this->tools([
                'product-description', 'product-features', 'product-name-ideas',
                'why-choose-product', 'customer-review', 'review-responder', 'amazon-listing',
                'shopify-product', 'product-comparison', 'upsell-message', 'flash-sale-copy',
                'abandoned-cart-email',
            ]),
            'business' => $this->tools([
                'business-plan', 'swot-analysis', 'pitch-deck-script', 'meeting-minutes',
                'okr-generator', 'executive-summary', 'mission-statement', 'vision-statement',
                'company-bio', 'project-proposal', 'grant-proposal', 'market-analysis',
                'business-model-canvas', 'kpi-dashboard-copy', 'quarterly-report',
                'investor-update', 'job-description', 'sop-generator', 'risk-assessment',
                'stakeholder-email',
            ]),
            'academic' => $this->tools([
                'essay-writer', 'academic-outline', 'research-summary', 'thesis-statement',
                'citation-generator', 'lesson-plan', 'quiz-generator', 'flashcards',
                'study-notes', 'discussion-post', 'lab-report', 'scholarship-essay',
                'academic-cover-letter', 'lecture-summary', 'multiple-choice-questions',
                'rubric-generator',
            ]),
            'development' => $this->tools([
                'code-generator', 'bug-fixer', 'unit-test', 'api-docs', 'git-commit',
                'code-explainer', 'regex-generator', 'sql-query-generator', 'code-review',
                'refactor-code', 'docstring-generator', 'readme-generator', 'dockerfile-generator',
                'cli-command-helper', 'algorithm-explainer', 'error-message-explainer',
            ]),
            'website-seo' => $this->tools([
                'meta-seo', 'landing-page-copy', 'faq-generator', 'schema-markup',
                'privacy-policy', 'terms-conditions', 'about-us-page', 'homepage-copy',
                'feature-page-copy', 'service-page-copy', 'seo-title-generator',
                'meta-description', 'keyword-cluster', 'content-brief',
                'internal-link-suggestions', 'robots-txt-generator', 'sitemap-plan',
                'local-seo-page', 'product-page-seo',
            ]),
            'creative-writing' => $this->tools([
                'story-generator', 'song-lyrics', 'poem-writer', 'dialogue-writer',
                'script-writer', 'character-bio', 'plot-ideas', 'worldbuilding',
                'comedy-sketch', 'children-story', 'screenplay-scene', 'metaphor-generator',
                'writing-prompts', 'game-storyline',
            ]),
            'personal-career' => $this->tools([
                'personal-bio', 'cover-letter', 'resume-builder', 'resignation-letter',
                'salary-negotiation', 'linkedin-profile', 'personal-statement',
                'recommendation-letter', 'interview-answers', 'networking-message',
            ]),
            'health-fitness' => $this->tools([
                'workout-plan', 'meal-plan', 'recipe-generator', 'mental-health-tips',
                'habit-tracker-plan', 'sleep-improvement-plan', 'wellness-newsletter',
            ]),
            'real-estate' => $this->tools([
                'property-listing', 'neighborhood-guide', 'investment-analysis',
                'open-house-description', 'real-estate-email',
            ]),
            'entertainment' => $this->tools([
                'travel-planner', 'gift-ideas', 'trivia-questions', 'event-planner',
                'party-theme-ideas', 'movie-recommendations', 'game-night-ideas',
                'itinerary-generator',
            ]),
            'language' => $this->tools([
                'translator', 'grammar-checker', 'tone-changer', 'synonym-finder',
                'language-tutor', 'idiom-explainer', 'text-simplifier',
            ]),
            'marketing-strategy' => $this->tools([
                'brand-voice-guide', 'competitor-analysis', 'audience-profile',
                'gtm-strategy', 'positioning-statement', 'marketing-plan',
                'campaign-brief', 'buyer-persona', 'value-proposition', 'brand-story',
                'content-strategy', 'channel-strategy', 'survey-questions',
            ]),
            'customer-support' => $this->tools([
                'ticket-reply', 'kb-article', 'onboarding-guide', 'chatbot-script',
                'refund-response', 'complaint-apology', 'troubleshooting-guide',
                'canned-responses',
            ]),
            'legal-finance' => $this->tools([
                'contract-summary', 'legal-privacy-policy', 'disclaimer',
                'fundraising-pitch', 'budget-plan', 'invoice-email', 'investment-memo',
            ]),
            'productivity' => $this->tools([
                'pros-cons', 'smart-goals', 'action-plan', 'how-to-guide',
                'prompt-generator', 'checklist-generator', 'decision-matrix',
                'meeting-agenda', 'weekly-plan', 'project-timeline', 'brainstorming-ideas',
                'productivity-system',
            ]),
        ];
    }

    private function tools(array $slugs): array
    {
        return array_map(fn (string $slug): array => [
            'slug' => $slug,
            'name' => $this->nameFor($slug),
        ], $slugs);
    }

    private function nameFor(string $slug): string
    {
        $names = [
            'tldr-summary' => 'TL;DR Summarization',
            'rss-content' => 'Content from RSS Feed',
            'youtube-to-blog' => 'YouTube to Blog Post',
            'youtube-to-blog-post' => 'YouTube Video to Blog',
            'twitter-tweet' => 'X / Twitter Tweet',
            'twitter-thread' => 'X / Twitter Thread',
            'ama-post' => 'AMA Post',
            'aida' => 'AIDA Copywriting Formula',
            'pas' => 'PAS Copywriting Formula',
            'okr-generator' => 'OKR Generator',
            'api-docs' => 'API Documentation',
            'readme-generator' => 'README Generator',
            'gtm-strategy' => 'Go-to-Market Strategy',
            'kb-article' => 'Knowledge Base Article',
        ];

        return $names[$slug] ?? Str::headline($slug);
    }
}
