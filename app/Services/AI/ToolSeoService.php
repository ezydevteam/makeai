<?php

namespace App\Services\AI;

use App\Models\AiTool;

/**
 * ToolSeoService — generates SEO meta tags and Schema.org JSON-LD for tool pages.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.15
 *
 * Generates:
 * 1. Meta tags (title, description, canonical, OG, Twitter Card)
 * 2. SoftwareApplication schema (with aggregate rating when reviews >= 5)
 * 3. FAQPage schema (when show_faqs = true AND faq_items not empty)
 * 4. HowTo schema (when show_how_it_works = true AND steps exist)
 * 5. BreadcrumbList schema (always present on tool pages)
 */
class ToolSeoService
{
    /**
     * Generate meta tags for a tool page.
     */
    public function getMeta(AiTool $tool): array
    {
        $name = settings('app_name', config('app.name', ''));
        $baseUrl = rtrim(config('app.url'), '/');

        return [
            'title' => $tool->meta_title
                                ?? translate(':tool — Free AI Tool | :app', ['tool' => $tool->name, 'app' => $name]),
            'description' => $tool->meta_description
                                ?? translate('Use :tool for free. :description', [
                                    'tool' => $tool->name,
                                    'description' => $tool->description,
                                ]),
            'keywords' => $this->generateKeywords($tool),
            'canonical' => "{$baseUrl}/ai-tools/{$tool->slug}",
            'og_title' => $tool->meta_title ?? "{$tool->name} | {$name}",
            'og_description' => $tool->meta_description ?? $tool->description,
            'og_image' => $tool->og_image ?? settings('app_og_image', ''),
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
        ];
    }

    /**
     * Generate all applicable Schema.org JSON-LD schemas.
     */
    public function getSchemas(AiTool $tool): array
    {
        $schemas = [];
        $baseUrl = rtrim(config('app.url'), '/');

        // 1. SoftwareApplication — always present
        $schemas[] = $this->buildSoftwareApplicationSchema($tool, $baseUrl);

        // 2. FAQPage — only when show_faqs = true AND faq_items not empty
        if ($tool->show_faqs && ! empty($tool->faq_items)) {
            $schemas[] = $this->buildFaqSchema($tool);
        }

        // 3. HowTo — only when show_how_it_works = true AND steps exist
        if ($tool->show_how_it_works && ! empty($tool->how_it_works)) {
            $schemas[] = $this->buildHowToSchema($tool);
        }

        // 4. BreadcrumbList — always present
        $schemas[] = $this->buildBreadcrumbSchema($tool, $baseUrl);

        return $schemas;
    }

    /**
     * SoftwareApplication schema — makes Google show ratings in search results.
     */
    private function buildSoftwareApplicationSchema(AiTool $tool, string $baseUrl): array
    {
        $appName = settings('app_name', config('app.name', ''));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $tool->name,
            'description' => $tool->description,
            'url' => "{$baseUrl}/ai-tools/{$tool->slug}",
            'applicationCategory' => 'WebApplication',
            'operatingSystem' => 'Web',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD',
            ],
            'provider' => [
                '@type' => 'Organization',
                'name' => $appName,
                'url' => $baseUrl,
            ],
        ];

        // Add aggregate rating only when review_count >= 5 (Google's minimum threshold)
        $minReviews = (int) settings('tool_page_min_reviews_for_schema', 5);
        if ($tool->review_count >= $minReviews && $tool->avg_rating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($tool->avg_rating, 1),
                'reviewCount' => (string) $tool->review_count,
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        return $schema;
    }

    /**
     * FAQPage schema — auto-generated from faq_items JSON.
     */
    private function buildFaqSchema(AiTool $tool): array
    {
        $faqItems = is_string($tool->faq_items)
            ? json_decode($tool->faq_items, true)
            : ($tool->faq_items ?? []);

        $mainEntity = [];
        foreach ($faqItems as $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }

            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($item['answer']),
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * HowTo schema — auto-generated from how_it_works JSON.
     */
    private function buildHowToSchema(AiTool $tool): array
    {
        $steps = is_string($tool->how_it_works)
            ? json_decode($tool->how_it_works, true)
            : ($tool->how_it_works ?? []);

        $schemaSteps = [];
        foreach ($steps as $step) {
            $schemaSteps[] = [
                '@type' => 'HowToStep',
                'position' => $step['step'] ?? count($schemaSteps) + 1,
                'name' => $step['title'] ?? '',
                'text' => $step['description'] ?? '',
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => translate('How to use :tool', ['tool' => $tool->name]),
            'step' => $schemaSteps,
        ];
    }

    /**
     * BreadcrumbList schema.
     */
    private function buildBreadcrumbSchema(AiTool $tool, string $baseUrl): array
    {
        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => translate('Home'),     'item' => $baseUrl],
            ['@type' => 'ListItem', 'position' => 2, 'name' => translate('AI Tools'), 'item' => "{$baseUrl}/ai-tools"],
        ];

        // Add category if tool has one
        if ($tool->category_id && $tool->category) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $tool->category->name,
                'item' => "{$baseUrl}/ai-tools/category/{$tool->category->slug}",
            ];
            $items[] = [
                '@type' => 'ListItem',
                'position' => 4,
                'name' => $tool->name,
            ];
        } else {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $tool->name,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Generate keywords for a tool.
     */
    private function generateKeywords(AiTool $tool): string
    {
        $categoryName = $tool->category?->name ?? '';
        $keywords = [
            $tool->name,
            "AI {$categoryName}",
            'free AI tool',
            'AI content generator',
            settings('app_name', config('app.name', '')),
        ];

        return implode(', ', array_filter($keywords));
    }
}
