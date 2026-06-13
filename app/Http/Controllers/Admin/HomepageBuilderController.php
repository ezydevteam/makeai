<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomepageBuilderRequest;
use App\Models\Setting;
use App\Models\SiteTemplate;
use Illuminate\Http\Request;
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
        'custom_html',
        'template_grid',
        'all_tools',
    ];

    public function index(): Response
    {
        $savedConfig = Setting::getValue('homepage_config');
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
        $validated = $request->validated();

        Setting::setValue('homepage_config', $validated, 'json', 'appearance');

        return back()->with('success', translate('Homepage configuration saved successfully.'));
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
                        'layout' => 'centered',
                        'headline' => translate('One Platform. Every AI Tool.'),
                        'subheadline' => translate('Unleash your creativity with powerful AI tools for content, images, chat, and code.'),
                        'primary_cta_text' => translate('Get Started for Free'),
                        'primary_cta_link' => '/register',
                        'primary_cta_style' => 'filled',
                        'secondary_cta_text' => translate('View Pricing'),
                        'secondary_cta_link' => '/pricing',
                        'secondary_cta_style' => 'outline',
                        'background_type' => 'gradient',
                        'background_value' => '',
                        'hero_media_url' => '',
                        'typing_phrases' => ['Write faster', 'Create images', 'Ship code'],
                        'show_trust_badges' => true,
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
                        'items' => [
                            ['icon' => 'pencil', 'title' => translate('AI Writer'), 'description' => translate('Generate blogs, ads, and emails in seconds.'), 'image_url' => ''],
                            ['icon' => 'photo', 'title' => translate('AI Images'), 'description' => translate('Turn prompts into high-resolution visuals.'), 'image_url' => ''],
                            ['icon' => 'code', 'title' => translate('AI Code'), 'description' => translate('Write, refactor, and debug code faster.'), 'image_url' => ''],
                        ],
                        'cta_text' => '',
                        'cta_link' => '',
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
                        'secondary_text' => translate('See Pricing'),
                        'secondary_link' => '/pricing',
                        'background' => 'gradient',
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
