<?php

namespace App\Http\Middleware;

use App\Models\AffiliateProgram;
use App\Models\AiTool;
use App\Models\Announcement;
use App\Models\AppearanceSetting;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Coupon;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Setting;
use App\Services\BroadcastingService;
use App\Services\CountryDetectionService;
use App\Services\InAppNotificationService;
use App\Services\SocialService;
use App\Services\TranslationService;
use App\Support\CountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // During installation, provide minimal props — no DB is available
        if ($request->is('install', 'install/*')) {
            return [
                ...parent::share($request),
                'appName' => translate('Installation'),
                'locale' => ['code' => 'en', 'name' => 'English', 'flag' => null, 'is_rtl' => false],
                'translations' => [],
                'languages' => [],
                'licenseTestMode' => config('app.license_test_mode', false),
                'flash' => [
                    'success' => fn () => $request->session()->get('success'),
                    'error' => fn () => $request->session()->get('error'),
                    'warning' => fn () => $request->session()->get('warning'),
                    'info' => fn () => $request->session()->get('info'),
                ],
            ];
        }

        $defaultLocale = settings('default_language', 'en');
        $requestedLocale = session('locale_manually_selected')
            ? session('locale', $defaultLocale)
            : $defaultLocale;
        $requestedLocale = $request->user()?->locale ?: $requestedLocale;
        $language = Language::query()
            ->where('code', $requestedLocale)
            ->where('is_active', true)
            ->first() ?: Language::getDefault();
        $locale = $language?->code ?? settings('default_language', 'en');
        $siteName = settings('site_name', translate('Application'));
        $copyrightText = settings('site_copyright_text', '') ?: translate('© {year} :app. All rights reserved.', ['app' => $siteName]);

        $resolveImage = fn (?string $path) => $path ? (str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path)) : '';

        return [
            ...parent::share($request),

            'appName' => $siteName,
            'licenseTestMode' => config('app.license_test_mode', false),

            'branding' => [
                'site_name'          => $siteName,
                'site_tagline'       => settings('site_tagline', ''),
                'site_description'   => settings('site_description', ''),
                'site_logo_light'    => $resolveImage(settings('site_logo_light', '')),
                'site_logo_dark'     => $resolveImage(settings('site_logo_dark', '')),
                'site_favicon_ico'   => $resolveImage(settings('site_favicon_ico', '')),
                'site_favicon_png'   => $resolveImage(settings('site_favicon_png', '')),
                'site_og_image'      => $resolveImage(settings('site_og_image', '')),
                'site_copyright_text'=> settings('site_copyright_text', ''),
                'site_support_email' => settings('site_support_email', ''),
                'site_support_url'   => settings('site_support_url', ''),
                'site_terms_url'     => settings('site_terms_url', ''),
                'site_privacy_url'   => settings('site_privacy_url', ''),
            ],

            'locale' => [
                'code' => $locale,
                'name' => $language?->name ?? translate('English'),
                'flag' => $language?->flag,
                'is_rtl' => (bool) ($language?->is_rtl ?? false),
                'date_format' => $language?->date_format ?? 'MMM D, YYYY',
                'time_format' => $language?->time_format ?? 'h:mm A',
                'number_format' => [
                    'decimal' => $language?->decimal_separator ?? '.',
                    'thousands' => $language?->thousands_separator ?? ',',
                    'system' => $language?->number_system ?? 'latn',
                ],
                'currency_position' => $language?->currency_position ?? settings('currency_position', 'before'),
            ],
            'isRtl' => (bool) ($language?->is_rtl ?? false),
            'translations' => fn () => TranslationService::getForLocale($locale),
            'languages' => fn () => Language::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['code', 'name', 'flag', 'is_rtl']),
            'currency' => [
                'code' => settings('default_currency', 'USD'),
                'symbol' => settings('currency_symbol', '$'),
                'position' => $language?->currency_position ?? settings('currency_position', 'before'),
                'decimals' => (int) settings('currency_decimals', 2),
            ],
            'isProAvailable' => fn () => isProAvailable(),
            'isExtendedLicense' => fn () => is_extended_license(),
            'licenseBlocked' => fn () => $this->isLicenseBlocked(),
            'socialLoginProviders' => fn () => $this->getSocialLoginProviders(),

            'app' => [
                'demo' => config('demo.enabled'),
                'demo_banner_color' => config('demo.banner_color', 'amber'),
                'envato_url' => config('demo.envato_url', 'https://codecanyon.net'),
                'demo_credentials' => config('demo.enabled') ? [
                    'admin' => ['email' => config('demo.admin_email', 'admin@demo.com'), 'password' => config('demo.admin_password', 'demo12345')],
                    'user' => ['email' => config('demo.user_email', 'user@demo.com'), 'password' => config('demo.user_password', 'demo12345')],
                ] : null,
                'name' => $siteName,
            ],

            'gdpr' => fn (Request $request) => [
                'enabled' => (bool) settings('gdpr_enabled', false),
                'eu_only' => (bool) settings('gdpr_eu_only', true),
                'is_eu' => app(CountryDetectionService::class)->isEuEea($request),
                'banner_position' => settings('gdpr_banner_position', 'bottom'),
                'banner_title' => settings('gdpr_banner_title', 'Cookie Preferences'),
                'banner_description' => settings('gdpr_banner_description', 'We use cookies to enhance your experience, analyze site usage, and show relevant content.'),
                'banner_accept_all_text' => settings('gdpr_banner_accept_all_text', 'Accept All'),
                'banner_customize_text' => settings('gdpr_banner_customize_text', 'Customize'),
                'banner_necessary_text' => settings('gdpr_banner_necessary_text', 'Necessary Only'),
                'banner_save_text' => settings('gdpr_banner_save_text', 'Save Preferences'),
                'banner_bg_color' => settings('gdpr_banner_bg_color', '#ffffff'),
                'banner_text_color' => settings('gdpr_banner_text_color', '#374151'),
                'banner_button_color' => settings('gdpr_banner_button_color', '#4f46e5'),
                'banner_button_text_color' => settings('gdpr_banner_button_text_color', '#ffffff'),
                'show_policy_links' => (bool) settings('gdpr_show_policy_links', true),
                'privacy_policy_url' => settings('site_privacy_url', '/privacy-policy'),
                'cookie_policy_url' => settings('gdpr_cookie_policy_url', '/cookie-policy'),
            ],

            'updateAvailable' => fn () => (bool) settings('update_available'),

            'auth' => fn () => $this->getAuthProps($request),

            'admin' => fn () => $this->getAdminProps($request),

            'notifications' => fn () => $this->getNotificationProps($request),

            'broadcasting' => fn () => app(BroadcastingService::class)->frontendConfig(),

            'cronStatus' => fn () => $this->getCronStatus($request),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],

            'announcements' => fn () => Announcement::active()
                ->get()
                ->filter(function ($announcement) use ($request) {
                    $user = $request->user();
                    if ($announcement->target_audience === 'guests' && $user) {
                        return false;
                    }
                    if ($announcement->target_audience === 'auth' && ! $user) {
                        return false;
                    }
                    if ($announcement->target_audience === 'free' && (! $user || $user->subscription_status === 'active')) {
                        return false;
                    }
                    if ($announcement->target_audience === 'pro' && (! $user || $user->subscription_status !== 'active')) {
                        return false;
                    }

                    return true;
                })->values(),

            'headerCoupon' => fn () => $this->getHeaderCoupon($request),

            'newsletterSettings' => fn () => Setting::getGroup('newsletter'),

            'socialFollow' => fn () => app(SocialService::class)->followPayload(),

            'headerConfig' => fn () => Setting::getValue('header_config', [
                'layout' => 'classic',
                'top' => [
                    'enabled' => false,
                    'sticky' => false,
                    'transparent_homepage' => false,
                    'height' => 40,
                    'hide_on_scroll' => false,
                    'container_width' => 'default',
                    'sticky_behavior' => 'none',
                    'upscroll_offset' => 80,
                    'downscroll_offset' => 80,
                    'transition_enabled' => true,
                    'shadow' => false,
                    'progressbar' => false,
                    'background' => ['color' => '', 'image_url' => '', 'overlay_opacity' => 0],
                    'custom_css' => '',
                    'blocks' => [
                        ['id' => 'top_nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'top', 'alignment' => 'center', 'text_color' => '', 'hover_color' => '', 'hover_style' => 'underline', 'submenu_bg_color' => '', 'submenu_text_color' => '']],
                        ['id' => 'top_lang', 'type' => 'language_switcher', 'enabled' => true, 'config' => []],
                    ],
                ],
                'main' => [
                    'enabled' => true,
                    'sticky' => true,
                    'transparent_homepage' => false,
                    'height' => 72,
                    'hide_on_scroll' => false,
                    'container_width' => 'default',
                    'sticky_behavior' => 'always',
                    'upscroll_offset' => 80,
                    'downscroll_offset' => 80,
                    'transition_enabled' => true,
                    'shadow' => false,
                    'progressbar' => false,
                    'background' => ['color' => '', 'image_url' => '', 'overlay_opacity' => 0],
                    'custom_css' => '',
                    'blocks' => [
                        ['id' => 'logo', 'type' => 'logo', 'enabled' => true, 'config' => ['block_align' => 'left']],
                        ['id' => 'nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'main', 'alignment' => 'center', 'text_color' => '', 'hover_color' => '', 'hover_style' => 'underline', 'submenu_bg_color' => '', 'submenu_text_color' => '']],
                        ['id' => 'search', 'type' => 'search', 'enabled' => false, 'config' => ['compact' => false, 'search_style' => 'box', 'enable_live_search' => true, 'show_suggestions' => true, 'icon_class' => 'ti ti-search', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                        ['id' => 'lang', 'type' => 'language_switcher', 'enabled' => false, 'config' => []],
                        ['id' => 'dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => []],
                        ['id' => 'cta', 'type' => 'cta_button', 'enabled' => true, 'config' => ['text' => translate('Get Started'), 'link' => '/register', 'style' => 'filled', 'color' => 'primary', 'icon_class' => '', 'icon_only' => false, 'icon_color' => '', 'bg_style' => 'filled', 'bg_color' => '', 'text_color' => '']],
                        ['id' => 'user', 'type' => 'user_menu', 'enabled' => true, 'config' => ['show_credits' => true, 'show_avatar' => true]],
                        ['id' => 'credits', 'type' => 'credit_balance', 'enabled' => false, 'config' => ['label' => translate('Credits'), 'icon_class' => 'ti ti-bolt', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                        ['id' => 'notify', 'type' => 'notification_bell', 'enabled' => true, 'config' => []],
                        ['id' => 'social', 'type' => 'social_icons', 'enabled' => false, 'config' => ['icons' => []]],
                        ['id' => 'html', 'type' => 'custom_html', 'enabled' => false, 'config' => ['content' => '']],
                    ],
                ],
                'mobile' => [
                    'enabled' => true,
                    'sticky' => true,
                    'transparent_homepage' => false,
                    'height' => 64,
                    'hide_on_scroll' => false,
                    'container_width' => 'default',
                    'sticky_behavior' => 'always',
                    'upscroll_offset' => 80,
                    'downscroll_offset' => 80,
                    'transition_enabled' => true,
                    'shadow' => false,
                    'progressbar' => false,
                    'background' => ['color' => '', 'image_url' => '', 'overlay_opacity' => 0],
                    'custom_css' => '',
                    'blocks' => [
                        ['id' => 'mobile_hamburger', 'type' => 'hamburger', 'enabled' => true, 'config' => ['menu_slug' => 'mobile', 'label' => translate('Menu'), 'icon_class' => 'ti ti-menu-2', 'show_label' => true, 'drawer_title' => '', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                        ['id' => 'mobile_logo', 'type' => 'logo', 'enabled' => true, 'config' => ['block_align' => 'left']],
                        ['id' => 'mobile_notify', 'type' => 'notification_bell', 'enabled' => true, 'config' => []],
                        ['id' => 'mobile_dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => ['label' => translate('Theme'), 'icon_class' => '', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                    ],
                ],
                'mobile_bottom' => [
                    'enabled' => false,
                    'sticky' => true,
                    'transparent_homepage' => false,
                    'height' => 64,
                    'hide_on_scroll' => false,
                    'container_width' => 'default',
                    'sticky_behavior' => 'always',
                    'upscroll_offset' => 80,
                    'downscroll_offset' => 80,
                    'transition_enabled' => true,
                    'shadow' => true,
                    'progressbar' => false,
                    'background' => ['color' => '', 'image_url' => '', 'overlay_opacity' => 0],
                    'custom_css' => '',
                    'blocks' => [
                        ['id' => 'mobile_bottom_home', 'type' => 'home_link', 'enabled' => true, 'config' => ['link' => '/', 'label' => translate('Home'), 'icon_class' => 'ti ti-home', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                        ['id' => 'mobile_bottom_search', 'type' => 'search_icon', 'enabled' => true, 'config' => ['label' => translate('Search'), 'icon_class' => 'ti ti-search', 'show_label' => true, 'enable_live_search' => true, 'show_suggestions' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                        ['id' => 'mobile_bottom_user', 'type' => 'user_menu_icon', 'enabled' => true, 'config' => ['label' => translate('Account'), 'guest_label' => translate('Sign In'), 'icon_class' => 'ti ti-user', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                    ],
                ],
            ]),
            'footerConfig' => fn () => Setting::getValue('footer_config', [
                'layout' => 4,
                'columns' => [
                    [
                        'id' => 'footer_column_1',
                        'width' => 25,
                        'title' => '',
                        'subtitle' => '',
                        'heading_style' => 'default',
                        'blocks' => [
                            ['id' => 'default_about', 'type' => 'about_text', 'enabled' => true, 'config' => ['logo' => null, 'description' => translate('The ultimate AI platform for creators, developers, and businesses. Generate anything you can imagine.')]],
                        ],
                    ],
                    [
                        'id' => 'footer_column_2',
                        'width' => 25,
                        'title' => '',
                        'subtitle' => '',
                        'heading_style' => 'default',
                        'blocks' => [
                            ['id' => 'default_menu_1', 'type' => 'menu_list', 'enabled' => true, 'config' => ['title' => translate('Platform'), 'menu_slug' => 'footer-1']],
                        ],
                    ],
                    [
                        'id' => 'footer_column_3',
                        'width' => 25,
                        'title' => '',
                        'subtitle' => '',
                        'heading_style' => 'default',
                        'blocks' => [
                            ['id' => 'default_menu_2', 'type' => 'menu_list', 'enabled' => true, 'config' => ['title' => translate('Support'), 'menu_slug' => 'footer-2']],
                        ],
                    ],
                    [
                        'id' => 'footer_column_4',
                        'width' => 25,
                        'title' => '',
                        'subtitle' => '',
                        'heading_style' => 'default',
                        'blocks' => [
                            ['id' => 'default_contact', 'type' => 'contact_info', 'enabled' => true, 'config' => ['title' => translate('Contact Us'), 'address' => '', 'phone' => '', 'email' => '']],
                        ],
                    ],
                ],
                'bottom_blocks' => [
                    ['id' => 'bottom_copyright', 'type' => 'copyright_text', 'enabled' => true, 'config' => ['text' => $copyrightText]],
                    ['id' => 'bottom_payment_icons', 'type' => 'payment_icons', 'enabled' => true, 'config' => ['icons' => ['visa', 'mastercard', 'paypal', 'stripe']]],
                    ['id' => 'bottom_back_to_top', 'type' => 'back_to_top', 'enabled' => true, 'config' => ['label' => translate('Back to top')]],
                ],
                'bottom_columns' => [
                    [
                        'id' => 'left',
                        'title' => translate('Left Column'),
                        'blocks' => [
                            ['id' => 'bottom_copyright', 'type' => 'copyright_text', 'enabled' => true, 'config' => ['text' => $copyrightText]],
                        ],
                    ],
                    [
                        'id' => 'right',
                        'title' => translate('Right Column'),
                        'blocks' => [
                            ['id' => 'bottom_payment_icons', 'type' => 'payment_icons', 'enabled' => true, 'config' => ['icons' => ['visa', 'mastercard', 'paypal', 'stripe']]],
                            ['id' => 'bottom_back_to_top', 'type' => 'back_to_top', 'enabled' => true, 'config' => ['label' => translate('Back to top')]],
                        ],
                    ],
                ],
                'bottom_bar' => [
                    'copyright_text' => $copyrightText,
                    'menu_slug' => null,
                    'show_payment_icons' => true,
                    'payment_icons' => ['visa', 'mastercard', 'paypal', 'stripe'],
                    'show_back_to_top' => true,
                    'layout_desktop' => 2,
                    'layout_tablet' => 2,
                    'layout_mobile' => 2,
                    'alignment_desktop' => 'between',
                    'alignment_tablet' => 'center',
                    'alignment_mobile' => 'center',
                    'padding_desktop' => 32,
                    'padding_tablet' => 24,
                    'padding_mobile' => 20,
                    'border_top' => true,
                ],
            ]),
            'footerData' => fn () => [
                'recentPosts' => BlogPost::published()
                    ->latest('published_at')
                    ->limit(12)
                    ->get(['title', 'slug', 'published_at'])
                    ->map(fn (BlogPost $post) => [
                        'title' => $post->title,
                        'slug' => $post->slug,
                        'published_at' => $post->published_at?->toDateString(),
                    ]),
                'aiCategories' => Category::aiTools()->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->limit(12)
                    ->get(['name', 'slug', 'tools_count']),
            ],

            'sidebarConfig' => fn () => Setting::getValue('sidebar_config', [
                'blocks' => [
                    ['id' => 'b1', 'type' => 'search_box', 'config' => ['title' => translate('Search'), 'placeholder' => translate('Search articles...')]],
                    ['id' => 'b2', 'type' => 'categories_list', 'config' => ['title' => translate('Categories'), 'show_count' => true]],
                    ['id' => 'b3', 'type' => 'recent_posts', 'config' => ['title' => translate('Recent Posts'), 'count' => 3]],
                ],
                'position' => 'right',
                'sticky' => true,
                'show_on_pages' => [],
            ]),

            'sidebarData' => [
                'toolCategories' => fn () => Category::aiTools()->active()
                    ->withCount('activeTools')
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug'])
                    ->map(fn (Category $cat) => [
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'tools_count' => $cat->active_tools_count ?? $cat->tools_count,
                    ]),
                'recentTools' => fn () => AiTool::active()->latest()->limit(5)->get(['id', 'name', 'slug', 'description', 'color', 'icon']),
                'popularTools' => fn () => AiTool::active()->orderByDesc('usage_count')->limit(10)->get(['id', 'name', 'slug', 'description', 'color', 'icon', 'usage_count']),
                'recentPosts' => fn () => BlogPost::published()->latest('published_at')->limit(5)->get(['title', 'slug', 'published_at', 'featured_image', 'is_featured'])->map(fn (BlogPost $post) => [
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'published_at' => $post->published_at?->toDateString(),
                    'image' => $post->featured_image,
                    'is_featured' => $post->is_featured,
                ]),
                'tags' => fn () => BlogTag::where('posts_count', '>', 0)->orderByDesc('posts_count')->limit(30)->get(['name', 'slug', 'posts_count'])->map(fn (BlogTag $tag) => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'url' => route('blog.tag', $tag->slug),
                    'count' => $tag->posts_count,
                ]),
            ],

            'globalMenus' => fn () => Menu::with(['items' => function ($q) {
                $q->orderBy('sort_order');
            }])->get(),

            'affiliateEnabled' => fn () => isProAvailable() && (bool) AffiliateProgram::current()->is_active,

            'addonMenuItems' => fn () => app(\App\Services\AddonService::class)->getActiveAddonMenuItems(),

            'appearanceAdminSettings' => fn () => auth('admin')->check()
                ? AppearanceSetting::getForScope('admin')
                : null,
        ];
    }

    /**
     * Get enabled social login providers for guest auth pages.
     */
    private function getSocialLoginProviders(): array
    {
        $displayMode = (string) settings('social_share_blog_style', 'icon-label');

        return collect([
            'google' => translate('Google'),
            'github' => translate('GitHub'),
            'facebook' => translate('Facebook'),
            'reddit' => translate('Reddit'),
            'twitter' => translate('Twitter'),
        ])
            ->filter(fn (string $label, string $provider) => (bool) settings("social_login_{$provider}_enabled", false)
                && filled(settings("social_login_{$provider}_client_id", ''))
                && filled(settings("social_login_{$provider}_client_secret", '')))
            ->map(fn (string $label, string $provider) => [
                'provider' => $provider,
                'label' => $label,
                'url' => route('social.redirect', $provider),
                'display_mode' => in_array($displayMode, ['icon', 'icon-label'], true) ? $displayMode : 'icon-label',
            ])
            ->values()
            ->all();
    }

    /**
     * Get authenticated user props with credits + subscription info.
     */
    private function getAuthProps(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return ['user' => null];
        }

        if ($user instanceof \App\Models\Admin) {
            return ['user' => null];
        }

        $user->loadMissing('plan');
        $plan = $user->plan;
        $features = [
            'plan_slug' => $plan?->slug,
            'plan_name' => $plan?->name,
            'features' => $plan?->features ?: [],
            'ai_models' => $plan?->ai_models ?: [],
            'max_tokens_per_request' => $plan?->max_tokens_per_request,
            'daily_token_limit' => $plan?->daily_token_limit,
            'max_images_per_day' => $plan?->max_images_per_day,
            'max_chats' => $plan?->max_chats,
        ];
        $request->session()->put('subscription_features', $features);

        return [
            'user' => [
                'ulid' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'credits' => (float) $user->credits,
                'plan_id' => $user->plan_id,
                'subscription_status' => $user->subscription_status,
                'subscription_ends_at' => $user->subscription_ends_at?->toISOString(),
                'trial_ends_at' => $user->trial_ends_at?->toISOString(),
                'subscription_features' => $features,
                'referral_code' => $user->referral_code,
                'referral_earnings' => (float) $user->referral_earnings,
                'referral_count' => (int) $user->referral_count,
                'referral_link' => $user->referral_code ? url('/ref/'.$user->referral_code) : null,
                'affiliate_custom_slug' => $user->affiliate_custom_slug,
                'theme_preference' => $user->theme_preference,
                'isImpersonating' => $request->session()->has('admin_impersonator_id'),
                'onboarding_completed_at' => $user->onboarding_completed_at?->toISOString(),
                'use_case' => $user->use_case,
                'dismissed_tooltips' => $user->dismissed_tooltips,
            ],
            'paletteTools' => fn () => \App\Models\AiTool::where('is_active', true)
                ->select('name', 'slug', 'description', 'icon', 'color', 'category_id')
                ->with('category:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\AiTool $tool) => [
                    'name' => $tool->name,
                    'slug' => $tool->slug,
                    'description' => $tool->description,
                    'icon' => $tool->icon,
                    'color' => $tool->color,
                    'category' => $tool->category?->name,
                ])
                ->all(),
            'paletteDocuments' => fn () => $user->documents()
                ->latest()
                ->take(20)
                ->get(['id', 'title', 'tool_slug']),
            'paletteChats' => fn () => $user->conversations()
                ->latest('last_message_at')
                ->take(10)
                ->get(['id', 'ulid', 'title']),
        ];
    }

    private function getHeaderCoupon(Request $request): ?array
    {
        if (! isProAvailable()) {
            return null;
        }

        $coupon = Coupon::query()
            ->where('show_in_header', true)
            ->first();

        if (! $coupon || ! $coupon->isValid()) {
            return null;
        }

        $user = $request->user();
        if ($user && ! $coupon->isEligibleForUser($user)) {
            return null;
        }

        if (! $user && ! in_array($coupon->user_limit, ['all', 'free'], true)) {
            return null;
        }

        return [
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'discount_label' => $this->formatHeaderCouponDiscount($coupon),
            'expires_at' => $coupon->expires_at?->toISOString(),
            'pricing_url' => route('pricing'),
        ];
    }

    private function formatHeaderCouponDiscount(Coupon $coupon): string
    {
        if ($coupon->type === 'percent') {
            return rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.').'%';
        }

        return CountryCatalog::formatMoney((float) $coupon->value, settings('pricing_currency_code', 'USD'));
    }

    private function getNotificationProps(Request $request): array
    {
        if (! Schema::hasTable('notifications')) {
            return [
                'enabled' => false,
                'driver' => 'disabled',
                'polling_interval' => 30000,
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $notifiable = auth('admin')->user() ?: $request->user();
        if (! $notifiable) {
            return [
                'enabled' => (bool) settings('notifications_enabled', true),
                'driver' => settings('notifications_driver', 'reverb'),
                'polling_interval' => (int) settings('notifications_polling_interval', 30000),
                'unread_count' => 0,
                'items' => [],
            ];
        }

        return app(InAppNotificationService::class)->summary($notifiable);
    }

    /**
     * Get admin-specific shared props.
     */
    private function getAdminProps(Request $request): ?array
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return null;
        }

        return [
            'user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'avatar' => $admin->avatar,
            ],
            'isSuperAdmin' => $admin->isSuperAdmin(),
            'permissions' => $admin->getAllPermissions(),
            'role' => $admin->role?->name,
            'pendingCommentsCount' => Comment::where('status', 'pending')->count(),
        ];
    }

    private function getCronStatus(Request $request): ?array
    {
        if (! auth('admin')->check()) {
            return null;
        }

        $lastRun = settings('last_scheduler_run');
        $lastRunAt = null;

        if ($lastRun) {
            try {
                $lastRunAt = Carbon::parse($lastRun);
            } catch (\Throwable) {
                $lastRunAt = null;
            }
        }

        $isConfigured = $lastRunAt?->greaterThan(now()->subMinutes(5)) ?? false;

        return [
            'is_configured' => $isConfigured,
            'last_run_at' => $lastRunAt?->toDateTimeString(),
            'setup_url' => route('admin.system.index').'#cron-jobs',
        ];
    }

    /**
     * Determine if the license is invalid and should block the frontend.
     * Returns null if license is fine, or an array with status details for the blocking banner.
     */
    private function isLicenseBlocked(): ?array
    {
        if (license_verified()) {
            return null;
        }

        $graceStart = settings('license_grace_started_at');

        if (filled($graceStart)) {
            $graceHours = config('license.grace_period', 72);
            $startedAt = Carbon::parse($graceStart);
            $expiresAt = $startedAt->copy()->addHours($graceHours);

            if (! now()->greaterThan($expiresAt)) {
                // In grace period — don't block, but the admin page will show a warning
                return null;
            }
        }

        // Grace expired or never activated — block everything
        $startedAt = filled($graceStart) ? Carbon::parse($graceStart) : null;

        return [
            'blocked' => true,
            'reason' => 'license_invalid',
            'message' => translate('License verification is required. Please activate your license to restore all features.'),
            'action_url' => route('admin.license.settings'),
            'action_text' => translate('Activate License'),
            'grace_expired' => filled($graceStart),
            'blocked_since' => $startedAt?->toISOString(),
        ];
    }
}
