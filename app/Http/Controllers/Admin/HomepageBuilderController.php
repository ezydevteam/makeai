<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    ];

    public function index(): Response
    {
        $savedConfig = Setting::getValue('homepage_config');
        $config = is_array($savedConfig) ? array_replace_recursive($this->getDefaults(), $savedConfig) : $this->getDefaults();

        return Inertia::render('Admin/Appearance/HomepageBuilder', [
            'config' => $config,
            'sectionTypes' => self::SECTION_TYPES,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'string', 'max:80'],
            'sections.*.type' => ['required', 'string', Rule::in(self::SECTION_TYPES)],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.core' => ['required', 'boolean'],
            'sections.*.config' => ['required', 'array'],
            'settings' => ['required', 'array'],
            'settings.seo' => ['required', 'array'],
            'settings.seo.meta_title' => ['nullable', 'string', 'max:160'],
            'settings.seo.meta_description' => ['nullable', 'string', 'max:255'],
            'settings.seo.og_image' => ['nullable', 'string', 'max:2048'],
            'settings.preloader' => ['required', 'array'],
            'settings.preloader.enabled' => ['required', 'boolean'],
            'settings.preloader.animation_url' => ['nullable', 'string', 'max:2048'],
            'settings.scroll_to_top' => ['required', 'array'],
            'settings.scroll_to_top.enabled' => ['required', 'boolean'],
            'settings.scroll_to_top.position' => ['required', 'string', Rule::in(['left', 'right'])],
            'settings.scroll_to_top.show_after_px' => ['required', 'integer', 'min:0', 'max:5000'],
            'settings.cookie_consent' => ['required', 'array'],
            'settings.cookie_consent.enabled' => ['required', 'boolean'],
            'settings.cookie_consent.message' => ['nullable', 'string', 'max:500'],
            'settings.cookie_consent.accept_text' => ['nullable', 'string', 'max:80'],
            'settings.cookie_consent.policy_url' => ['nullable', 'string', 'max:2048'],
            'settings.chat_widget_embed' => ['nullable', 'string', 'max:20000'],
        ]);

        Setting::setValue('homepage_config', $validated, 'json', 'appearance');

        return back()->with('success', 'Homepage configuration saved successfully.');
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
                        'headline' => 'One Platform. Every AI Tool.',
                        'subheadline' => 'Unleash your creativity with powerful AI tools for content, images, chat, and code.',
                        'primary_cta_text' => 'Get Started for Free',
                        'primary_cta_link' => '/register',
                        'primary_cta_style' => 'filled',
                        'secondary_cta_text' => 'View Pricing',
                        'secondary_cta_link' => '/pricing',
                        'secondary_cta_style' => 'outline',
                        'background_type' => 'gradient',
                        'background_value' => '',
                        'hero_media_url' => '',
                        'typing_phrases' => ['Write faster', 'Create images', 'Ship code'],
                        'show_trust_badges' => true,
                        'trust_badge_text' => 'Trusted by 50,000+ creators',
                        'stats' => [
                            ['number' => '50K+', 'label' => 'Users Trusted'],
                            ['number' => '10M+', 'label' => 'Assets Generated'],
                            ['number' => '99.9%', 'label' => 'Uptime SLA'],
                        ],
                    ],
                ],
                [
                    'id' => 'features',
                    'type' => 'features',
                    'enabled' => true,
                    'core' => true,
                    'config' => [
                        'title' => 'Supercharge your workflow',
                        'subtitle' => 'Everything you need to build the future, powered by AI.',
                        'layout' => '3-column',
                        'items' => [
                            ['icon' => 'pencil', 'title' => 'AI Writer', 'description' => 'Generate blogs, ads, and emails in seconds.', 'image_url' => ''],
                            ['icon' => 'photo', 'title' => 'AI Images', 'description' => 'Turn prompts into high-resolution visuals.', 'image_url' => ''],
                            ['icon' => 'code', 'title' => 'AI Code', 'description' => 'Write, refactor, and debug code faster.', 'image_url' => ''],
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
                        'headline' => 'Ready to build with AI?',
                        'subheadline' => 'Start creating content, images, and code today.',
                        'primary_text' => 'Create Account',
                        'primary_link' => '/register',
                        'secondary_text' => 'See Pricing',
                        'secondary_link' => '/pricing',
                        'background' => 'gradient',
                        'width' => 'contained',
                    ],
                ],
            ],
            'settings' => [
                'seo' => [
                    'meta_title' => 'MakeAI — The Ultimate AI Platform',
                    'meta_description' => 'Create content, images, chat responses, and code with one powerful AI platform.',
                    'og_image' => '',
                ],
                'preloader' => [
                    'enabled' => false,
                    'animation_url' => '',
                ],
                'scroll_to_top' => [
                    'enabled' => true,
                    'position' => 'right',
                    'show_after_px' => 500,
                ],
                'cookie_consent' => [
                    'enabled' => false,
                    'message' => 'We use cookies to improve your experience.',
                    'accept_text' => 'Accept',
                    'policy_url' => '/privacy-policy',
                ],
                'chat_widget_embed' => '',
            ],
        ];
    }
}
