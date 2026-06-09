<?php

namespace Database\Seeders;

use App\Models\SiteTemplate;
use Illuminate\Database\Seeder;

class SiteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'ai-chatbot',
                'name' => 'AI Chatbot',
                'tagline' => 'ChatGPT / Claude-style conversational AI',
                'description' => 'Full-screen conversational AI experience with product modes, projects, and streaming chat.',
                'icon' => 'ti ti-messages',
                'layout_component' => 'AiChatTemplate',
                'bundled_tool_slugs' => json_encode([]),
                'requires_pro' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'social-media-manager',
                'name' => 'Social Media Manager',
                'tagline' => 'Your all-in-one social media content command center',
                'description' => 'Dashboard for social content creation across platforms.',
                'icon' => 'ti ti-brand-instagram',
                'layout_component' => 'SocialMediaManagerTemplate',
                'bundled_tool_slugs' => json_encode([
                    'instagram-caption', 'twitter-thread', 'linkedin-post',
                    'tiktok-script', 'hashtag-strategy', 'content-calendar',
                ]),
                'requires_pro' => false,
                'sort_order' => 2,
            ],
            [
                'slug' => 'marketing-suite',
                'name' => 'Marketing Suite',
                'tagline' => 'End-to-end marketing content wizard',
                'description' => 'Complete marketing content creation platform.',
                'icon' => 'ti ti-speakerphone',
                'layout_component' => 'MarketingSuiteTemplate',
                'bundled_tool_slugs' => json_encode([
                    'landing-page-copy', 'facebook-ad', 'google-ads-headline',
                    'email-generator', 'value-proposition', 'competitor-analysis',
                ]),
                'requires_pro' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'content-studio',
                'name' => 'Content Studio',
                'tagline' => 'Editorial content creation hub',
                'description' => 'Create, edit, and optimize blog and editorial content.',
                'icon' => 'ti ti-file-text',
                'layout_component' => 'ContentStudioTemplate',
                'bundled_tool_slugs' => json_encode([
                    'blog-article', 'blog-outline', 'article-rewriter',
                    'content-improver', 'seo-blog', 'meta-seo',
                ]),
                'requires_pro' => false,
                'sort_order' => 4,
            ],
            [
                'slug' => 'ecommerce-toolkit',
                'name' => 'eCommerce Toolkit',
                'tagline' => 'Product and store content tools',
                'description' => 'Everything you need for product listings and ecommerce copy.',
                'icon' => 'ti ti-shopping-cart',
                'layout_component' => 'EcommerceToolkitTemplate',
                'bundled_tool_slugs' => json_encode([
                    'product-description', 'amazon-listing', 'review-responder',
                    'abandoned-cart-email', 'upsell-message', 'flash-sale-copy',
                ]),
                'requires_pro' => true,
                'sort_order' => 5,
            ],
            [
                'slug' => 'developer-assistant',
                'name' => 'Developer Assistant',
                'tagline' => 'Code-focused dark-theme IDE experience',
                'description' => 'Developer tools for code generation, debugging, and documentation.',
                'icon' => 'ti ti-code',
                'layout_component' => 'DeveloperAssistantTemplate',
                'bundled_tool_slugs' => json_encode([
                    'code-generator', 'bug-fixer', 'code-optimizer',
                    'unit-test', 'api-docs', 'git-commit',
                ]),
                'requires_pro' => false,
                'sort_order' => 6,
                'color_bg' => '#0d1117',
                'color_surface' => '#161b22',
                'color_text' => '#e6edf3',
            ],

            [
                'slug' => 'academic-writer',
                'name' => 'Academic Writer',
                'tagline' => 'Student and researcher writing tools',
                'description' => 'Academic writing, research, and citation tools.',
                'icon' => 'ti ti-school',
                'layout_component' => 'AcademicWriterTemplate',
                'bundled_tool_slugs' => json_encode([
                    'essay-writer', 'thesis-statement', 'research-outline',
                    'citation-generator', 'study-guide',
                ]),
                'requires_pro' => false,
                'sort_order' => 7,
            ],
        ];

        foreach ($templates as $template) {
            SiteTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
