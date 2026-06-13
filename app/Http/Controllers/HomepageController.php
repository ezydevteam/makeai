<?php

namespace App\Http\Controllers;

use App\Http\Resources\SiteTemplateResource;
use App\Models\AiTool;
use App\Models\Faq;
use App\Models\Setting;
use App\Models\SiteTemplate;
use App\Models\Testimonial;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    public function show(): Response
    {
        $savedConfig = Setting::getValue('homepage_config');
        $templateData = $this->loadTemplateData($savedConfig);

        $testimonials = Testimonial::active()
            ->ordered()
            ->get(['id', 'name', 'role', 'company', 'avatar', 'content', 'rating', 'is_featured', 'source'])
            ->toArray();

        $faqs = Faq::active()
            ->ordered()
            ->with('category:id,name,sort_order')
            ->get(['id', 'question', 'answer', 'category_id', 'sort_order'])
            ->toArray();

        $allTools = [];
        $allToolCategories = [];

        foreach ($savedConfig['sections'] ?? [] as $section) {
            if (($section['type'] ?? '') === 'all_tools' && ($section['enabled'] ?? true)) {
                $allTools = AiTool::active()
                    ->orderBy('name')
                    ->get(['slug', 'name', 'description', 'icon', 'color', 'category', 'tags', 'usage_count', 'avg_rating', 'is_featured', 'created_at'])
                    ->toArray();

                $allToolCategories = $allTools
                    ? array_values(array_unique(array_map(fn ($t) => $t['category'], $allTools)))
                    : [];
                break;
            }
        }

        return Inertia::render('Welcome', [
            'homepage' => is_array($savedConfig) ? $savedConfig : null,
            'templateData' => ! empty($templateData) ? $templateData : null,
            'scrollToTopEnabled' => (bool) settings('scroll_to_top_enabled', true),
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'allTools' => $allTools,
            'allToolCategories' => $allToolCategories,
        ]);
    }

    private function loadTemplateData(?array $config): array
    {
        if (! is_array($config) || empty($config['sections'])) {
            return [];
        }

        $templateSections = array_filter(
            $config['sections'],
            fn (array $s): bool => ($s['type'] ?? '') === 'template_grid'
                && ! empty($s['enabled'])
                && ! empty($s['config']['template_slug'] ?? null)
        );

        if (empty($templateSections)) {
            return [];
        }

        $slugs = array_unique(array_map(fn (array $s): string => $s['config']['template_slug'], $templateSections));

        $templates = SiteTemplate::whereIn('slug', $slugs)
            ->where('is_active', true)
            ->get()
            ->keyBy('slug');

        $result = [];

        foreach ($slugs as $slug) {
            $template = $templates->get($slug);
            if (! $template) {
                continue;
            }

            if ($template->requires_pro && ! isProAvailable()) {
                continue;
            }

            $rawSlugs = $template->bundled_tool_slugs;
            if (is_string($rawSlugs)) {
                $rawSlugs = json_decode($rawSlugs, true);
            }
            $toolSlugs = is_array($rawSlugs) ? $rawSlugs : [];

            $tools = empty($toolSlugs)
                ? collect()
                : AiTool::whereIn('slug', $toolSlugs)
                    ->where('is_active', true)
                    ->orderByRaw('FIELD(slug, '.collect($toolSlugs)->map(fn (string $s): string => "'{$s}'")->join(',').')')
                    ->get(['slug', 'name', 'description', 'icon', 'color', 'tags', 'avg_rating']);

            $data = [
                'templateSlug' => $slug,
                'template' => (new SiteTemplateResource($template))->resolve(),
                'tools' => $tools->toArray(),
            ];

            if ($slug === 'social-media-manager') {
                $data['platformSettings'] = settings('template_social_platforms', []);
                $data['defaultPlatform'] = settings('template_social_default_platform', '');
            }

            if ($slug === 'marketing-suite') {
                $data['stageSettings'] = settings('template_marketing_stages', []);
                $data['defaultStage'] = settings('template_marketing_default_stage', 'awareness');
            }

            if ($slug === 'content-studio') {
                $data['contentTypeSettings'] = settings('template_content_types', []);
                $data['defaultType'] = settings('template_content_default_type', '');
            }

            if ($slug === 'ecommerce-toolkit') {
                $data['ecomStageSettings'] = settings('template_ecom_stages', []);
                $data['defaultStage'] = settings('template_ecom_default_stage', 'product-listing');
                $data['showContextPanel'] = settings('template_ecom_show_context_panel', true);
            }

            if ($slug === 'developer-assistant') {
                $data['devCategorySettings'] = settings('template_dev_categories', []);
                $data['defaultCategory'] = settings('template_dev_default_category', 'generate');
            }

            if ($slug === 'academic-writer') {
                $data['academicStageSettings'] = settings('template_academic_stages', []);
                $data['defaultStage'] = settings('template_academic_default_stage', 'research');
                $data['showContextPanel'] = settings('template_academic_show_context_panel', true);
                $data['academicLevels'] = settings('template_academic_levels', []);
                $data['defaultLevel'] = settings('template_academic_default_level', '');
                $data['academicCitationStyles'] = settings('template_academic_citation_styles', []);
                $data['defaultCitation'] = settings('template_academic_default_citation', '');
            }

            $result[$slug] = $data;
        }

        return $result;
    }
}
