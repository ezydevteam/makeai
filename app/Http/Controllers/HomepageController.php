<?php

namespace App\Http\Controllers;

use App\Http\Resources\SiteTemplateResource;
use App\Models\AiTool;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\SiteTemplate;
use App\Models\Testimonial;
use App\Services\Pricing\PlanPriceResolver;
use App\Services\Pricing\PricingCountryDetector;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class HomepageController extends Controller
{
    public function show(): Response
    {
        $savedConfig = Setting::getValue('homepage_config');
        $savedConfig = is_array($savedConfig) ? $this->normalizeHomepageConfig($savedConfig) : [];
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
        $pricingPlans = [];
        $pricingCountry = null;
        $pricingSettings = [
            'pricing_show_monthly' => settings('pricing_show_monthly', true),
            'pricing_show_yearly' => settings('pricing_show_yearly', true),
            'pricing_show_lifetime' => settings('pricing_show_lifetime', true),
            'pricing_currency_code' => settings('pricing_currency_code', 'USD'),
            'pricing_trial_button_text' => settings('pricing_trial_button_text', 'Start Trial'),
            'pricing_featured_label_text' => settings('pricing_featured_label_text', 'Recommended'),
            'pricing_checkout_button_text' => settings('pricing_checkout_button_text', 'Choose Plan'),
        ];

        foreach ($savedConfig['sections'] ?? [] as $section) {
            if (in_array(($section['type'] ?? ''), ['tools_showcase', 'all_tools'], true) && ($section['enabled'] ?? true)) {
                $allTools = AiTool::active()
                    ->with('category:id,name')
                    ->orderBy('name')
                    ->get(['slug', 'name', 'description', 'icon', 'color', 'category_id', 'tags', 'usage_count', 'avg_rating', 'is_featured', 'created_at'])
                    ->map(function (AiTool $tool): array {
                        $data = $tool->toArray();
                        $data['category'] = $tool->category?->name;

                        return $data;
                    })
                    ->toArray();

                $allToolCategories = $allTools
                    ? array_values(array_unique(array_values(array_filter(array_map(fn ($t) => $t['category'] ?? null, $allTools)))))
                    : [];
            }

            if (($section['type'] ?? '') === 'pricing' && ($section['enabled'] ?? true) && empty($pricingPlans)) {
                $pricingCountry = app(PricingCountryDetector::class)->detect(request());

                $pricingPlans = app(PlanPriceResolver::class)->resolveCollection(
                    Plan::active()
                        ->with(['countryPrices' => fn ($query) => $query->orderBy('country_code')])
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(),
                    $pricingCountry
                )->values()->toArray();
            }

            if (! empty($allTools) && ! empty($pricingPlans)) {
                break;
            }
        }

        return Inertia::render('Welcome', [
            'homepage' => $savedConfig ?: null,
            'templateData' => ! empty($templateData) ? $templateData : null,
            'scrollToTopEnabled' => (bool) settings('scroll_to_top_enabled', true),
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'allTools' => $allTools,
            'allToolCategories' => $allToolCategories,
            'pricingPlans' => $pricingPlans,
            'pricingCountry' => $pricingCountry,
            'pricingSettings' => $pricingSettings,
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

    private function normalizeHomepageConfig(array $config): array
    {
        $changed = false;

        if (! isset($config['sections']) || ! is_array($config['sections'])) {
            return $config;
        }

        foreach ($config['sections'] as $index => $section) {
            if (! is_array($section) || ($section['type'] ?? null) !== 'features') {
                if (! is_array($section) || ($section['type'] ?? null) !== 'cta_banner') {
                    if (! is_array($section) || ($section['type'] ?? null) !== 'tools_showcase') {
                        continue;
                    }

                    $toolsConfig = $config['sections'][$index]['config'] ?? [];

                    if (isset($toolsConfig['button_text']) && ! isset($toolsConfig['primary_text'])) {
                        $config['sections'][$index]['config']['primary_text'] = $toolsConfig['button_text'];
                    }

                    if (isset($toolsConfig['button_link']) && ! isset($toolsConfig['primary_link'])) {
                        $config['sections'][$index]['config']['primary_link'] = $toolsConfig['button_link'];
                    }

                    if (isset($toolsConfig['button_icon']) && ! isset($toolsConfig['primary_icon'])) {
                        $config['sections'][$index]['config']['primary_icon'] = $toolsConfig['button_icon'];
                    }

                    if (isset($toolsConfig['button_style']) && ! isset($toolsConfig['primary_style'])) {
                        $config['sections'][$index]['config']['primary_style'] = $toolsConfig['button_style'];
                    }

                    if (! isset($toolsConfig['primary_style'])) {
                        $config['sections'][$index]['config']['primary_style'] = 'primary_filled';
                    }

                    if (! isset($toolsConfig['background_style'])) {
                        $config['sections'][$index]['config']['background_style'] = 'gradient-1';
                    }

                    if (! isset($toolsConfig['width'])) {
                        $config['sections'][$index]['config']['width'] = 'contained';
                    }

                    if (isset($toolsConfig['background_image_url']) && is_string($toolsConfig['background_image_url']) && str_starts_with($toolsConfig['background_image_url'], 'data:')) {
                        $storedImagePath = $this->storeDataUriMedia($toolsConfig['background_image_url'], 'image');

                        if ($storedImagePath !== null) {
                            $config['sections'][$index]['config']['background_image_url'] = $storedImagePath;
                        }
                    }

                    unset(
                        $config['sections'][$index]['config']['button_text'],
                        $config['sections'][$index]['config']['button_link'],
                        $config['sections'][$index]['config']['button_style'],
                        $config['sections'][$index]['config']['button_icon'],
                        $config['sections'][$index]['config']['secondary_text'],
                        $config['sections'][$index]['config']['secondary_link'],
                        $config['sections'][$index]['config']['secondary_icon'],
                        $config['sections'][$index]['config']['secondary_style'],
                    );
                    $changed = true;
                    continue;
                }

                $ctaConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($ctaConfig['primary_cta_text']) && ! isset($ctaConfig['primary_text'])) {
                    $config['sections'][$index]['config']['primary_text'] = $ctaConfig['primary_cta_text'];
                }

                if (isset($ctaConfig['primary_cta_link']) && ! isset($ctaConfig['primary_link'])) {
                    $config['sections'][$index]['config']['primary_link'] = $ctaConfig['primary_cta_link'];
                }

                if (isset($ctaConfig['primary_cta_icon']) && ! isset($ctaConfig['primary_icon'])) {
                    $config['sections'][$index]['config']['primary_icon'] = $ctaConfig['primary_cta_icon'];
                }

                if (isset($ctaConfig['primary_cta_style']) && ! isset($ctaConfig['primary_style'])) {
                    $config['sections'][$index]['config']['primary_style'] = $ctaConfig['primary_cta_style'];
                }

                if (isset($ctaConfig['secondary_cta_text']) && ! isset($ctaConfig['secondary_text'])) {
                    $config['sections'][$index]['config']['secondary_text'] = $ctaConfig['secondary_cta_text'];
                }

                if (isset($ctaConfig['secondary_cta_link']) && ! isset($ctaConfig['secondary_link'])) {
                    $config['sections'][$index]['config']['secondary_link'] = $ctaConfig['secondary_cta_link'];
                }

                if (isset($ctaConfig['secondary_cta_icon']) && ! isset($ctaConfig['secondary_icon'])) {
                    $config['sections'][$index]['config']['secondary_icon'] = $ctaConfig['secondary_cta_icon'];
                }

                if (isset($ctaConfig['secondary_cta_style']) && ! isset($ctaConfig['secondary_style'])) {
                    $config['sections'][$index]['config']['secondary_style'] = $ctaConfig['secondary_cta_style'];
                }

                if (isset($ctaConfig['background']) && ! isset($ctaConfig['background_style'])) {
                    $config['sections'][$index]['config']['background_style'] = (string) $ctaConfig['background'];
                }

                if (! isset($ctaConfig['background_style'])) {
                    $config['sections'][$index]['config']['background_style'] = 'gradient-1';
                }

                if (! isset($ctaConfig['width'])) {
                    $config['sections'][$index]['config']['width'] = 'contained';
                }

                if (! isset($ctaConfig['access']) || ! in_array($ctaConfig['access'], ['everyone', 'logged_in', 'pro'], true)) {
                    $config['sections'][$index]['config']['access'] = 'everyone';
                }

                $backgroundImage = $ctaConfig['background_image_url'] ?? null;

                if (is_string($backgroundImage) && str_starts_with($backgroundImage, 'data:')) {
                    $storedImagePath = $this->storeDataUriMedia($backgroundImage, 'image');

                    if ($storedImagePath !== null) {
                        $config['sections'][$index]['config']['background_image_url'] = $storedImagePath;
                    }
                }

                unset(
                    $config['sections'][$index]['config']['background'],
                    $config['sections'][$index]['config']['primary_cta_text'],
                    $config['sections'][$index]['config']['primary_cta_link'],
                    $config['sections'][$index]['config']['primary_cta_icon'],
                    $config['sections'][$index]['config']['primary_cta_style'],
                    $config['sections'][$index]['config']['secondary_cta_text'],
                    $config['sections'][$index]['config']['secondary_cta_link'],
                    $config['sections'][$index]['config']['secondary_cta_icon'],
                    $config['sections'][$index]['config']['secondary_cta_style'],
                );
                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'announcement') {
                $announcementConfig = $config['sections'][$index]['config'] ?? [];

                if (! isset($announcementConfig['title'])) {
                    $config['sections'][$index]['config']['title'] = translate('Announcements');
                }

                if (! isset($announcementConfig['subtitle'])) {
                    $config['sections'][$index]['config']['subtitle'] = translate('Show active announcements from the announcements manager.');
                }

                if (! isset($announcementConfig['announcement_type']) || ! in_array($announcementConfig['announcement_type'], ['topbar', 'popup', 'notification', 'all'], true)) {
                    $config['sections'][$index]['config']['announcement_type'] = 'topbar';
                }

                if (! isset($announcementConfig['style']) || ! in_array($announcementConfig['style'], ['cards', 'compact'], true)) {
                    $config['sections'][$index]['config']['style'] = 'cards';
                }

                if (! isset($announcementConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 3;
                }

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'template_grid') {
                $templateGridConfig = $config['sections'][$index]['config'] ?? [];

                if (! isset($templateGridConfig['title'])) {
                    $config['sections'][$index]['config']['title'] = translate('Template Tool Grid');
                }

                if (! isset($templateGridConfig['subtitle'])) {
                    $config['sections'][$index]['config']['subtitle'] = translate('Embed a tool grid from any site template with filters and cards.');
                }

                if (! isset($templateGridConfig['template_slug'])) {
                    $config['sections'][$index]['config']['template_slug'] = '';
                }

                if (! isset($templateGridConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 12;
                }

                if (! array_key_exists('show_filter', $templateGridConfig)) {
                    $config['sections'][$index]['config']['show_filter'] = true;
                }

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'all_tools') {
                $allToolsConfig = $config['sections'][$index]['config'] ?? [];

                if (! isset($allToolsConfig['title'])) {
                    $config['sections'][$index]['config']['title'] = translate('All Tools Browser');
                }

                if (! isset($allToolsConfig['subtitle'])) {
                    $config['sections'][$index]['config']['subtitle'] = translate('Browse, search, and filter every tool in one place.');
                }

                if (! isset($allToolsConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 12;
                }

                if (! isset($allToolsConfig['default_tab'])) {
                    $config['sections'][$index]['config']['default_tab'] = 'popular';
                }

                if (! array_key_exists('show_search', $allToolsConfig)) {
                    $config['sections'][$index]['config']['show_search'] = true;
                }

                if (! array_key_exists('show_categories', $allToolsConfig)) {
                    $config['sections'][$index]['config']['show_categories'] = true;
                }

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'pricing') {
                $pricingConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($pricingConfig['title']) && ! isset($pricingConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $pricingConfig['title'];
                }

                if (isset($pricingConfig['subtitle']) && ! isset($pricingConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $pricingConfig['subtitle'];
                }

                if (! isset($pricingConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('Pricing');
                }

                if (! isset($pricingConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Show your available plans with a clean, buyer-friendly layout.');
                }

                if (! isset($pricingConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-credit-card';
                }

                if (! isset($pricingConfig['source'])) {
                    $config['sections'][$index]['config']['source'] = 'all';
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'testimonials') {
                $testimonialConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($testimonialConfig['title']) && ! isset($testimonialConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $testimonialConfig['title'];
                }

                if (isset($testimonialConfig['subtitle']) && ! isset($testimonialConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $testimonialConfig['subtitle'];
                }

                if (! isset($testimonialConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('What Our Users Say');
                }

                if (! isset($testimonialConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Show real customer feedback to build trust.');
                }

                if (! isset($testimonialConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-message-2-heart';
                }

                if (isset($testimonialConfig['source'])) {
                    $config['sections'][$index]['config']['source'] = (string) $testimonialConfig['source'];
                } elseif (! empty($testimonialConfig['featured_only'])) {
                    $config['sections'][$index]['config']['source'] = 'featured';
                } else {
                    $config['sections'][$index]['config']['source'] = 'all';
                }

                if (! isset($testimonialConfig['card_style'])) {
                    $config['sections'][$index]['config']['card_style'] = 'bordered';
                }

                if (! isset($testimonialConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 6;
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                    $config['sections'][$index]['config']['featured_only'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'faq') {
                $faqConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($faqConfig['title']) && ! isset($faqConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $faqConfig['title'];
                }

                if (isset($faqConfig['subtitle']) && ! isset($faqConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $faqConfig['subtitle'];
                }

                if (! isset($faqConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('Frequently Asked Questions');
                }

                if (! isset($faqConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Answer common questions in a clean accordion layout.');
                }

                if (! isset($faqConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-help-circle';
                }

                if (! isset($faqConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 8;
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'stats_bar') {
                $statsConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($statsConfig['title']) && ! isset($statsConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $statsConfig['title'];
                }

                if (isset($statsConfig['subtitle']) && ! isset($statsConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $statsConfig['subtitle'];
                }

                if (! isset($statsConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('Social Proof');
                }

                if (! isset($statsConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Show your best numbers and proof points in a clean row.');
                }

                if (! isset($statsConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-chart-bar';
                }

                if (! array_key_exists('show_stats_separator', $statsConfig)) {
                    $config['sections'][$index]['config']['show_stats_separator'] = true;
                }

                if (! isset($statsConfig['stats_number_color'])) {
                    $config['sections'][$index]['config']['stats_number_color'] = 'dark';
                }

                if (! isset($statsConfig['stats_label_color'])) {
                    $config['sections'][$index]['config']['stats_label_color'] = 'light';
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'newsletter') {
                $newsletterConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($newsletterConfig['title']) && ! isset($newsletterConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $newsletterConfig['title'];
                }

                if (isset($newsletterConfig['subtitle']) && ! isset($newsletterConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $newsletterConfig['subtitle'];
                }

                if (! isset($newsletterConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('Stay in the loop');
                }

                if (! isset($newsletterConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Collect newsletter subscribers with a clear, buyer-friendly signup form.');
                }

                if (! isset($newsletterConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-mail';
                }

                if (! isset($newsletterConfig['layout'])) {
                    $config['sections'][$index]['config']['layout'] = 'inline';
                }

                if (! isset($newsletterConfig['placeholder_text'])) {
                    $config['sections'][$index]['config']['placeholder_text'] = translate('Enter your email');
                }

                if (! isset($newsletterConfig['button_text'])) {
                    $config['sections'][$index]['config']['button_text'] = translate('Subscribe');
                }

                if (! isset($newsletterConfig['button_link'])) {
                    $config['sections'][$index]['config']['button_link'] = '/newsletter/subscribe';
                }

                if (! isset($newsletterConfig['button_style'])) {
                    $config['sections'][$index]['config']['button_style'] = 'primary_filled';
                }

                if (! isset($newsletterConfig['privacy_text'])) {
                    $config['sections'][$index]['config']['privacy_text'] = translate('We respect your inbox. Unsubscribe at any time.');
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'latest_posts') {
                $postsConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($postsConfig['title']) && ! isset($postsConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $postsConfig['title'];
                }

                if (isset($postsConfig['subtitle']) && ! isset($postsConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $postsConfig['subtitle'];
                }

                if (! isset($postsConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('Latest Posts');
                }

                if (! isset($postsConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Show your latest blog posts in a clean, buyer-friendly layout.');
                }

                if (! isset($postsConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-article';
                }

                if (! isset($postsConfig['layout'])) {
                    $config['sections'][$index]['config']['layout'] = 'grid';
                }

                if (! isset($postsConfig['source'])) {
                    $config['sections'][$index]['config']['source'] = 'recent';
                }

                if (! isset($postsConfig['card_style'])) {
                    $config['sections'][$index]['config']['card_style'] = 'bordered';
                }

                if (! isset($postsConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 3;
                }

                if (! isset($postsConfig['button_text'])) {
                    $config['sections'][$index]['config']['button_text'] = translate('View all posts');
                }

                if (! isset($postsConfig['button_link'])) {
                    $config['sections'][$index]['config']['button_link'] = '/blog';
                }

                if (! isset($postsConfig['button_style'])) {
                    $config['sections'][$index]['config']['button_style'] = 'outline';
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'integrations') {
                $integrationConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($integrationConfig['title']) && ! isset($integrationConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $integrationConfig['title'];
                }

                if (isset($integrationConfig['subtitle']) && ! isset($integrationConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $integrationConfig['subtitle'];
                }

                if (! isset($integrationConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('Technology Logos');
                }

                if (! isset($integrationConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Show the platforms, models, and integrations your product works with.');
                }

                if (! isset($integrationConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-plug-connected';
                }

                if (! isset($integrationConfig['layout'])) {
                    $config['sections'][$index]['config']['layout'] = 'grid';
                }

                if (! isset($integrationConfig['max_items'])) {
                    $config['sections'][$index]['config']['max_items'] = 6;
                }

                $normalizedItems = [];
                foreach (($integrationConfig['items'] ?? []) as $itemIndex => $item) {
                    if (is_string($item)) {
                        $normalizedItems[] = [
                            'title' => $item !== '' ? $item : translate('Logo :count', ['count' => str_pad((string) ($itemIndex + 1), 2, '0', STR_PAD_LEFT)]),
                            'image_url' => '',
                            'link_url' => '',
                            'link_open_new_tab' => false,
                        ];
                        continue;
                    }

                    if (! is_array($item)) {
                        continue;
                    }

                    $normalizedItems[] = [
                        'title' => $item['title'] ?? $item['label'] ?? $item['name'] ?? translate('Logo :count', ['count' => str_pad((string) ($itemIndex + 1), 2, '0', STR_PAD_LEFT)]),
                        'image_url' => $item['image_url'] ?? '',
                        'link_url' => $item['link_url'] ?? '',
                        'link_open_new_tab' => filter_var($item['link_open_new_tab'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                    ] + $item;
                }

                $config['sections'][$index]['config']['items'] = $normalizedItems;

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                );

                $changed = true;
                continue;
            }

            $featureConfig = $config['sections'][$index]['config'] ?? [];

            if (isset($featureConfig['cta_text']) && ! isset($featureConfig['button_text'])) {
                $config['sections'][$index]['config']['button_text'] = $featureConfig['cta_text'];
            }

            if (isset($featureConfig['cta_link']) && ! isset($featureConfig['button_link'])) {
                $config['sections'][$index]['config']['button_link'] = $featureConfig['cta_link'];
            }

            if (isset($featureConfig['cta_style']) && ! isset($featureConfig['button_style'])) {
                $config['sections'][$index]['config']['button_style'] = $featureConfig['cta_style'];
            }

            if (isset($featureConfig['cta_icon']) && ! isset($featureConfig['button_icon'])) {
                $config['sections'][$index]['config']['button_icon'] = $featureConfig['cta_icon'];
            }

            if (! isset($featureConfig['learn_more_text'])) {
                $config['sections'][$index]['config']['learn_more_text'] = translate('Learn more');
            }

            unset(
                $config['sections'][$index]['config']['cta_text'],
                $config['sections'][$index]['config']['cta_link'],
                $config['sections'][$index]['config']['cta_style'],
                $config['sections'][$index]['config']['cta_icon'],
            );

            foreach (($config['sections'][$index]['config']['items'] ?? []) as $itemIndex => $item) {
                if (! is_array($item) || ! isset($item['icon']) || ! is_string($item['icon']) || $item['icon'] === '') {
                    continue;
                }

                $icon = trim($item['icon']);

                if (Str::startsWith($icon, 'ti ')) {
                    continue;
                }

                $config['sections'][$index]['config']['items'][$itemIndex]['icon'] = Str::startsWith($icon, 'ti-')
                    ? 'ti '.$icon
                    : 'ti ti-'.$icon;

                $imageUrl = $config['sections'][$index]['config']['items'][$itemIndex]['image_url'] ?? null;

                if (is_string($imageUrl) && str_starts_with($imageUrl, 'data:')) {
                    $storedImagePath = $this->storeDataUriMedia($imageUrl, 'image');

                    if ($storedImagePath !== null) {
                        $config['sections'][$index]['config']['items'][$itemIndex]['image_url'] = $storedImagePath;
                    }
                }

                $linkTabValue = $config['sections'][$index]['config']['items'][$itemIndex]['link_open_new_tab'] ?? false;
                $config['sections'][$index]['config']['items'][$itemIndex]['link_open_new_tab'] = filter_var($linkTabValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
            }

            $changed = true;
        }

        $hasHowItWorks = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'how_it_works');
        if (! $hasHowItWorks) {
            $howItWorksSection = [
                'id' => 'how_it_works',
                'type' => 'how_it_works',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('How It Works'),
                    'subheading' => translate('Show each step in a buyer-friendly layout that is easy to edit.'),
                    'icon' => 'ti ti-route',
                    'step_layout' => 'cards',
                    'step_card_style' => 'bordered',
                    'section_vertical_padding' => 96,
                    'items' => [
                        ['title' => translate('Choose a tool'), 'icon' => 'ti ti-click', 'description' => translate('Pick the AI tool that matches your task.'), 'link' => ''],
                        ['title' => translate('Enter your prompt'), 'icon' => 'ti ti-message-2', 'description' => translate('Describe what you want in plain language.'), 'link' => ''],
                        ['title' => translate('Get your result'), 'icon' => 'ti ti-bolt', 'description' => translate('Review, edit, and publish the generated output.'), 'link' => ''],
                    ],
                ],
            ];

            $config['sections'] = array_values(array_merge(
                array_slice($config['sections'], 0, 2),
                [$howItWorksSection],
                array_slice($config['sections'], 2),
            ));
            $changed = true;
        }

        $hasTestimonials = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'testimonials');
        if (! $hasTestimonials) {
            $testimonialsSection = [
                'id' => 'testimonials',
                'type' => 'testimonials',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('What Our Users Say'),
                    'subheading' => translate('Show real customer feedback to build trust.'),
                    'icon' => 'ti ti-message-2-heart',
                    'source' => 'all',
                    'card_style' => 'bordered',
                    'max_items' => 6,
                ],
            ];

            $pricingIndex = collect($config['sections'])->search(fn (array $section): bool => ($section['type'] ?? null) === 'pricing');
            $insertAt = is_int($pricingIndex) ? $pricingIndex + 1 : count($config['sections']);

            array_splice($config['sections'], $insertAt, 0, [$testimonialsSection]);
            $config['sections'] = array_values($config['sections']);
            $changed = true;
        }

        $hasFaq = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'faq');
        if (! $hasFaq) {
            $faqSection = [
                'id' => 'faq',
                'type' => 'faq',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('Frequently Asked Questions'),
                    'subheading' => translate('Answer common questions in a clean accordion layout.'),
                    'icon' => 'ti ti-help-circle',
                    'max_items' => 8,
                ],
            ];

            $testimonialsIndex = collect($config['sections'])->search(fn (array $section): bool => ($section['type'] ?? null) === 'testimonials');
            $insertAt = is_int($testimonialsIndex) ? $testimonialsIndex + 1 : count($config['sections']);

            array_splice($config['sections'], $insertAt, 0, [$faqSection]);
            $config['sections'] = array_values($config['sections']);
            $changed = true;
        }

        $hasStatsBar = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'stats_bar');
        if (! $hasStatsBar) {
            $statsSection = [
                'id' => 'stats_bar',
                'type' => 'stats_bar',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('Social Proof'),
                    'subheading' => translate('Show your best numbers and proof points in a clean row.'),
                    'icon' => 'ti ti-chart-bar',
                    'show_stats_separator' => true,
                    'stats_number_color' => 'dark',
                    'stats_label_color' => 'light',
                    'stats' => [
                        ['number' => '50K+', 'label' => translate('Users Trusted')],
                        ['number' => '10M+', 'label' => translate('Assets Generated')],
                        ['number' => '99.9%', 'label' => translate('Uptime SLA')],
                    ],
                ],
            ];

            $faqIndex = collect($config['sections'])->search(fn (array $section): bool => ($section['type'] ?? null) === 'faq');
            $insertAt = is_int($faqIndex) ? $faqIndex + 1 : count($config['sections']);

            array_splice($config['sections'], $insertAt, 0, [$statsSection]);
            $config['sections'] = array_values($config['sections']);
            $changed = true;
        }

        $hasLatestPosts = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'latest_posts');
        if (! $hasLatestPosts) {
            $latestPostsSection = [
                'id' => 'latest_posts',
                'type' => 'latest_posts',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('Latest Posts'),
                    'subheading' => translate('Show your latest blog posts in a clean, buyer-friendly layout.'),
                    'icon' => 'ti ti-article',
                    'layout' => 'grid',
                    'source' => 'recent',
                    'card_style' => 'bordered',
                    'max_items' => 3,
                    'button_text' => translate('View all posts'),
                    'button_link' => '/blog',
                    'button_icon' => '',
                    'button_style' => 'outline',
                ],
            ];

            $statsIndex = collect($config['sections'])->search(fn (array $section): bool => ($section['type'] ?? null) === 'stats_bar');
            $insertAt = is_int($statsIndex) ? $statsIndex + 1 : count($config['sections']);

            array_splice($config['sections'], $insertAt, 0, [$latestPostsSection]);
            $config['sections'] = array_values($config['sections']);
            $changed = true;
        }

        $hasIntegrations = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'integrations');
        if (! $hasIntegrations) {
            $integrationsSection = [
                'id' => 'integrations',
                'type' => 'integrations',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('Technology Logos'),
                    'subheading' => translate('Show the platforms, models, and integrations your product works with.'),
                    'icon' => 'ti ti-plug-connected',
                    'layout' => 'grid',
                    'max_items' => 6,
                    'items' => [
                        ['title' => translate('OpenAI'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                        ['title' => translate('Anthropic'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                        ['title' => translate('Google'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                    ],
                ],
            ];

            $latestPostsIndex = collect($config['sections'])->search(fn (array $section): bool => ($section['type'] ?? null) === 'latest_posts');
            $insertAt = is_int($latestPostsIndex) ? $latestPostsIndex + 1 : count($config['sections']);

            array_splice($config['sections'], $insertAt, 0, [$integrationsSection]);
            $config['sections'] = array_values($config['sections']);
            $changed = true;
        }

        $hasNewsletter = collect($config['sections'])->contains(fn (array $section): bool => ($section['type'] ?? null) === 'newsletter');
        if (! $hasNewsletter) {
            $newsletterSection = [
                'id' => 'newsletter',
                'type' => 'newsletter',
                'enabled' => true,
                'core' => true,
                'config' => [
                    'heading' => translate('Stay in the loop'),
                    'subheading' => translate('Collect newsletter subscribers with a clear, buyer-friendly signup form.'),
                    'icon' => 'ti ti-mail',
                    'layout' => 'inline',
                    'placeholder_text' => translate('Enter your email'),
                    'button_text' => translate('Subscribe'),
                    'button_link' => '/newsletter/subscribe',
                    'button_icon' => '',
                    'button_style' => 'primary_filled',
                    'privacy_text' => translate('We respect your inbox. Unsubscribe at any time.'),
                ],
            ];

            $latestPostsIndex = collect($config['sections'])->search(fn (array $section): bool => ($section['type'] ?? null) === 'latest_posts');
            $insertAt = is_int($latestPostsIndex) ? $latestPostsIndex + 1 : count($config['sections']);

            array_splice($config['sections'], $insertAt, 0, [$newsletterSection]);
            $config['sections'] = array_values($config['sections']);
            $changed = true;
        }

        if ($changed) {
            Setting::setValue('homepage_config', $config, 'json', 'appearance');
        }

        return $config;
    }

    private function storeDataUriMedia(string $dataUri, string $mediaType): ?string
    {
        if (! preg_match('/^data:(?<mime>[-\w.+\/]+);base64,(?<data>.+)$/', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode($matches['data'], true);

        if ($binary === false) {
            return null;
        }

        $mime = strtolower($matches['mime']);
        $extension = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => $mediaType === 'video' ? 'mp4' : 'png',
        };

        $path = 'homepage/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
