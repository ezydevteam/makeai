<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomepageBuilderRequest;
use App\Models\Setting;
use App\Models\SiteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomepageBuilderController extends Controller
{
    private const SECTION_TYPES = [
        'hero',
        'features',
        'tools_showcase',
        'how_it_works',
        'pricing',
        'testimonials',
        'faq',
        'stats_bar',
        'cta_banner',
        'latest_posts',
        'newsletter',
        'integrations',
        'richtext',
        'image_carousel',
        'ad_slot',
        'announcement',
        'custom_html',
        'template_grid',
        'all_tools',
    ];

    public function index(): Response
    {
        $savedConfig = Setting::getValue('homepage_config');
        $savedConfig = is_array($savedConfig) ? $this->normalizeStoredHomepageConfig($savedConfig) : null;
        $config = is_array($savedConfig) ? array_replace_recursive($this->getDefaults(), $savedConfig) : $this->getDefaults();

        $activeHomepageTemplate = settings('homepage_template', 'default');

        $availableTemplates = SiteTemplate::active()
            ->where('slug', 'ai-chatbot')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name', 'requires_pro'])
            ->map(fn ($t) => [
                'slug' => $t->slug,
                'name' => $t->name,
                'requires_pro' => (bool) $t->requires_pro,
            ])
            ->values();

        return Inertia::render('Admin/Appearance/HomepageBuilder', [
            'config' => $config,
            'sectionTypes' => self::SECTION_TYPES,
            'activeHomepageTemplate' => $activeHomepageTemplate,
            'availableTemplates' => $availableTemplates,
            'gridTemplates' => SiteTemplate::active()
                ->where('slug', '!=', 'ai-chatbot')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name', 'requires_pro'])
                ->values(),
        ]);
    }

    public function setHomepage(Request $request)
    {
        $validated = $request->validate([
            'homepage_template' => ['required', 'string', 'max:100'],
        ]);

        settings_set('homepage_template', $validated['homepage_template'], 'string', 'general');

        return back()->with('success', translate('Homepage setting updated.'));
    }

    public function update(HomepageBuilderRequest $request)
    {
        $validated = $this->normalizeStoredHomepageConfig($request->validated());

        Setting::setValue('homepage_config', $validated, 'json', 'appearance');

        return back()->with('success', translate('Homepage configuration saved successfully.'));
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/ogg,video/quicktime', 'max:51200'],
            'directory' => ['nullable', 'string', 'max:100'],
        ]);

        $dir = $request->input('directory', 'homepage');
        $path = $request->file('file')->store($dir, 'public');
        $url = Storage::disk('public')->url($path);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = $request->getSchemeAndHttpHost().$url;
        } elseif ($request->isSecure()) {
            $url = preg_replace('/^http:\/\//i', 'https://', $url) ?? $url;
        }

        return response()->json(['url' => $url, 'path' => $path]);
    }

    private function normalizeStoredHomepageConfig(array $config): array
    {
        $changed = false;

        if (! isset($config['sections']) || ! is_array($config['sections'])) {
            return $config;
        }

        foreach ($config['sections'] as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            if (($section['type'] ?? null) === 'tools_showcase') {
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

            if (($section['type'] ?? null) === 'features') {
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

                    if (str_starts_with($icon, 'ti ')) {
                        continue;
                    }

                    $config['sections'][$index]['config']['items'][$itemIndex]['icon'] = str_starts_with($icon, 'ti-')
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

                continue;
            }

            if (($section['type'] ?? null) === 'how_it_works') {
                $howItWorksConfig = $config['sections'][$index]['config'] ?? [];

                if (isset($howItWorksConfig['title']) && ! isset($howItWorksConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = $howItWorksConfig['title'];
                }

                if (isset($howItWorksConfig['subtitle']) && ! isset($howItWorksConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = $howItWorksConfig['subtitle'];
                }

                if (! isset($howItWorksConfig['heading'])) {
                    $config['sections'][$index]['config']['heading'] = translate('How It Works');
                }

                if (! isset($howItWorksConfig['subheading'])) {
                    $config['sections'][$index]['config']['subheading'] = translate('Show each step in a buyer-friendly layout that is easy to edit.');
                }

                if (! isset($howItWorksConfig['icon'])) {
                    $config['sections'][$index]['config']['icon'] = 'ti ti-route';
                }

                if (! isset($howItWorksConfig['step_layout']) && isset($howItWorksConfig['layout'])) {
                    $config['sections'][$index]['config']['step_layout'] = (string) $howItWorksConfig['layout'];
                }

                if (! isset($howItWorksConfig['step_layout'])) {
                    $config['sections'][$index]['config']['step_layout'] = 'cards';
                }

                if (! isset($howItWorksConfig['step_card_style'])) {
                    $config['sections'][$index]['config']['step_card_style'] = 'bordered';
                }

                if (! isset($howItWorksConfig['section_vertical_padding'])) {
                    $config['sections'][$index]['config']['section_vertical_padding'] = 96;
                }

                $items = $config['sections'][$index]['config']['items'] ?? [];
                if (is_array($items)) {
                    $normalizedItems = [];

                    foreach (array_values($items) as $itemIndex => $item) {
                        if (is_string($item)) {
                            $normalizedItems[] = [
                                'title' => $item,
                                'icon' => 'ti ti-route',
                                'description' => '',
                                'link' => '',
                            ];
                            continue;
                        }

                        $normalizedItems[] = [
                            'title' => $item['title'] ?? $item['label'] ?? $item['name'] ?? translate('Step :count', ['count' => str_pad((string) ($itemIndex + 1), 2, '0', STR_PAD_LEFT)]),
                            'icon' => $item['icon'] ?? 'ti ti-route',
                            'description' => $item['description'] ?? $item['text'] ?? '',
                            'link' => $item['link'] ?? '',
                        ] + $item;
                    }

                    $config['sections'][$index]['config']['items'] = $normalizedItems;
                }

                unset(
                    $config['sections'][$index]['config']['title'],
                    $config['sections'][$index]['config']['subtitle'],
                    $config['sections'][$index]['config']['layout'],
                );

                $changed = true;
                continue;
            }

            if (($section['type'] ?? null) === 'cta_banner') {
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

            if (($section['type'] ?? null) !== 'hero') {
                continue;
            }

            $background = $section['config']['hero_background_url'] ?? null;

            if (! is_string($background) || ! str_starts_with($background, 'data:')) {
                continue;
            }

            $storedPath = $this->storeDataUriMedia($background, (string) ($section['config']['hero_background_type'] ?? 'image'));

            if ($storedPath === null) {
                continue;
            }

            $config['sections'][$index]['config']['hero_background_url'] = $storedPath;
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
            $insertAt = is_int($pricingIndex) ? $pricingIndex + 1 : count($config['sections']) - 1;

            array_splice($config['sections'], max(0, $insertAt), 0, [$testimonialsSection]);
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
            $insertAt = is_int($testimonialsIndex) ? $testimonialsIndex + 1 : count($config['sections']) - 1;

            array_splice($config['sections'], max(0, $insertAt), 0, [$faqSection]);
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
            $insertAt = is_int($faqIndex) ? $faqIndex + 1 : count($config['sections']) - 1;

            array_splice($config['sections'], max(0, $insertAt), 0, [$statsSection]);
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
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            default => $mediaType === 'video' ? 'mp4' : 'png',
        };

        $path = 'homepage/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function getDefaults(): array
    {
        return [
            'sections' => [
                [
                    'id' => 'hero',
                    'type' => 'hero',
                    'enabled' => true,
                    'core' => true,
                    'config' => [
                        'layout' => 'center',
                        'headline' => translate('One Platform. Every AI Tool.'),
                        'subheadline' => translate('Unleash your creativity with powerful AI tools for content, images, chat, and code.'),
                        'primary_cta_text' => translate('Get Started for Free'),
                        'primary_cta_link' => '/register',
                        'primary_cta_style' => 'primary_filled',
                        'primary_cta_icon' => '',
                        'primary_cta_icon_position' => 'left',
                        'secondary_cta_text' => translate('View Pricing'),
                        'secondary_cta_link' => '/pricing',
                        'secondary_cta_style' => 'outline',
                        'secondary_cta_icon' => '',
                        'secondary_cta_icon_position' => 'left',
                        'hero_background_type' => 'image',
                        'hero_background_url' => '',
                        'show_hero_gradient_overlay' => true,
                        'show_stats_separator' => true,
                        'hero_section_height' => 'default',
                        'hero_vertical_padding' => 48,
                        'hero_heading_size' => 'lg',
                        'hero_heading_color' => 'dark',
                        'hero_subheading_color' => 'light',
                        'stats_number_color' => 'dark',
                        'stats_label_color' => 'light',
                        'trust_badge_text' => translate('Trusted by 50,000+ creators'),
                        'stats' => [
                            ['number' => '50K+', 'label' => translate('Users Trusted')],
                            ['number' => '10M+', 'label' => translate('Assets Generated')],
                            ['number' => '99.9%', 'label' => translate('Uptime SLA')],
                        ],
                    ],
                ],
                [
                    'id' => 'features',
                    'type' => 'features',
                    'enabled' => true,
                    'core' => true,
                    'config' => [
                        'title' => translate('Supercharge your workflow'),
                        'subtitle' => translate('Everything you need to build the future, powered by AI.'),
                        'layout' => '3-column',
                        'feature_vertical_padding' => 96,
                        'card_style' => 'bordered',
                        'items' => [
                            ['icon' => 'ti ti-pencil', 'title' => translate('AI Writer'), 'description' => translate('Generate blogs, ads, and emails in seconds.'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                            ['icon' => 'ti ti-photo', 'title' => translate('AI Images'), 'description' => translate('Turn prompts into high-resolution visuals.'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                            ['icon' => 'ti ti-code', 'title' => translate('AI Code'), 'description' => translate('Write, refactor, and debug code faster.'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                        ],
                        'heading_color' => 'dark',
                        'subheading_color' => 'light',
                        'learn_more_text' => translate('Learn more'),
                        'button_text' => '',
                        'button_link' => '',
                        'button_style' => 'primary_filled',
                        'button_icon' => '',
                    ],
                ],
                [
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
                ],
                [
                    'id' => 'tools_showcase',
                    'type' => 'tools_showcase',
                    'enabled' => true,
                    'core' => true,
                'config' => [
                    'title' => translate('AI Tools Showcase'),
                    'subtitle' => translate('Explore the tools buyers can launch immediately after signup.'),
                    'layout' => '3-column',
                    'card_style' => 'bordered',
                    'section_vertical_padding' => 96,
                    'source' => 'all',
                    'max_items' => 6,
                    'heading_color' => 'white',
                    'subheading_color' => 'white',
                    'primary_text' => translate('View all tools'),
                    'primary_link' => '/ai-tools',
                    'primary_icon' => '',
                    'primary_style' => 'primary_filled',
                    'background_style' => 'gradient-1',
                    'background_image_url' => '',
                    'width' => 'contained',
                    ],
                ],
                [
                    'id' => 'pricing',
                    'type' => 'pricing',
                    'enabled' => true,
                    'core' => true,
                    'config' => [
                        'heading' => translate('Pricing'),
                        'subheading' => translate('Show your available plans with a clean, buyer-friendly layout.'),
                        'icon' => 'ti ti-credit-card',
                        'source' => 'all',
                    ],
                ],
                [
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
                ],
                [
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
                ],
                [
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
                ],
                [
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
                ],
                [
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
                ],
                [
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
                ],
                [
                    'id' => 'cta_banner',
                    'type' => 'cta_banner',
                    'enabled' => true,
                    'core' => false,
                    'config' => [
                        'headline' => translate('Ready to build with AI?'),
                        'subheadline' => translate('Start creating content, images, and code today.'),
                        'primary_text' => translate('Create Account'),
                        'primary_link' => '/register',
                        'primary_icon' => '',
                        'primary_style' => 'primary_filled',
                        'secondary_text' => translate('See Pricing'),
                        'secondary_link' => '/pricing',
                        'secondary_icon' => '',
                        'secondary_style' => 'outline',
                        'access' => 'everyone',
                        'background_style' => 'gradient-1',
                        'background_image_url' => '',
                        'width' => 'contained',
                    ],
                ],
            ],
            'settings' => [
                'seo' => [
                    'meta_title' => translate(':app — The Ultimate AI Platform', ['app' => translate('Application')]),
                    'meta_description' => translate('Create content, images, chat responses, and code with one powerful AI platform.'),
                    'og_image' => '',
                ],
                'scroll_to_top' => [
                    'enabled' => true,
                    'position' => 'right',
                    'show_after_px' => 500,
                ],
                'chat_widget_embed' => '',
            ],
        ];
    }
}
