<?php

namespace App\Http\Middleware;

use App\Models\AiTool;
use App\Models\Announcement;
use App\Models\AppearanceSetting;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Coupon;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\User;
use App\Services\BroadcastingService;
use App\Services\CaptchaService;
use App\Services\CountryDetectionService;
use App\Support\CurrencyCatalog;
use App\Services\ThemeSettingsService;
use App\Services\InAppNotificationService;
use App\Services\SocialService;
use App\Services\TranslationService;
use App\Support\CountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
                'appName' => translate('MakeAI Installation'),
                'locale' => ['code' => 'en', 'name' => 'English', 'flag' => null, 'is_rtl' => false],
                'translations' => [],
                'languages' => [],
                'licenseTestMode' => config('app.license_test_mode', false),
                'purchaseCodeFormat' => \App\Support\PurchaseCode::frontendConfig(),
                'flash' => [
                    'success' => fn () => $request->session()->get('success'),
                    'error' => fn () => $request->session()->get('error'),
                    'warning' => fn () => $request->session()->get('warning'),
                    'info' => fn () => $request->session()->get('info'),
                ],
            ];
        }

        $defaultLocale = Language::defaultCode();
        $requestedLocale = session('locale_manually_selected')
            ? session('locale', $defaultLocale)
            : $defaultLocale;
        $requestedLocale = $request->user()?->locale ?: $requestedLocale;
        $hasLanguagesTable = Schema::hasTable('languages');
        $hasTranslationsTable = Schema::hasTable('translations');
        $language = $hasLanguagesTable
            ? (Language::query()
                ->where('code', $requestedLocale)
                ->where('is_active', true)
                ->first() ?: Language::getDefault())
            : null;
        $locale = $language?->code ?? $defaultLocale;
        $siteName = settings('site_name', 'MakeAI');
        $frontendPresetService = app(ThemeSettingsService::class);
        $frontendThemeSettings = $frontendPresetService->getResolvedFrontendTheme();
        $frontendHeaderSettings = $frontendPresetService->getResolvedFrontendHeader();
        $frontendFooterSettings = $frontendPresetService->getResolvedFrontendFooter();
        $frontendHomepageSettings = $frontendPresetService->getResolvedFrontendHomepage();
        $frontendHomepageConfig = $frontendPresetService->getResolvedFrontendHomepageConfig();
        $frontendCustomCodeSettings = $frontendPresetService->getStoredCustomCodeSettings();
        $frontendToolPageSettings = $frontendPresetService->getResolvedFrontendToolPage();

        // Demo bar nav: a ?demo_hero / ?demo_tool query param picks a page-level layout
        // variant only (never the preset — that is the modal's job). Gated on demo mode
        // so a production query string can never reshuffle the homepage or tool page.
        if (config('demo.enabled')) {
            if ($heroKey = $request->query('demo_hero')) {
                $item = collect(config('demo.nav.hero.items', []))->firstWhere('key', (string) $heroKey);
                if ($item && ! empty($item['hero_variant'])) {
                    $frontendHomepageSettings['hero_variant'] = $item['hero_variant'];
                }
            }
            if ($toolKey = $request->query('demo_tool')) {
                $item = collect(config('demo.nav.tools.items', []))->firstWhere('key', (string) $toolKey);
                if ($item && ! empty($item['layout'])) {
                    $frontendToolPageSettings['layout'] = $item['layout'];
                }
            }
        }

        // Driver-aware media resolver: root-relative /storage/... on the local disk
        // (mixed-content safe), fully-qualified bucket/CDN URL on any cloud driver.
        $resolveImageUrl = fn (?string $path) => media_url($path);

        return [
            ...parent::share($request),

            'appName' => $siteName,
            'licenseTestMode' => config('app.license_test_mode', false),
            'purchaseCodeFormat' => \App\Support\PurchaseCode::frontendConfig(),

            // Base URL the client prepends to a stored media key (see resources/js/lib/media.ts).
            // '/storage' on the local disk; the bucket/CDN origin when a cloud driver is active,
            // so every Vue `:src` resolves correctly without hardcoding '/storage/'.
            'mediaBase' => config('filesystems.disks.public.driver', 'local') === 'local'
                ? '/storage'
                : rtrim(Storage::disk('public')->url(''), '/'),

            'branding' => [
                'site_name'          => $siteName,
                'site_tagline'       => settings('site_tagline', ''),
                'site_description'   => settings('site_description', ''),
                'site_logo_light'    => $resolveImageUrl(settings('site_logo_light', '')),
                'site_logo_dark'     => $resolveImageUrl(settings('site_logo_dark', '')),
                'site_favicon_ico'   => $resolveImageUrl(settings('site_favicon_ico', '')),
                'site_favicon_png'   => $resolveImageUrl(settings('site_favicon_png', '')),
                'site_og_image'      => $resolveImageUrl(settings('site_og_image', '')),
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
                // Not a language trait, but it rides with the other formatting config
                // because it is the third input to rendering an instant. Every date the
                // client shows or edits is resolved in this zone, not the browser's, so
                // the site reads the same clock for everyone (see resources/js/lib/timezone.ts).
                'timezone' => display_tz(),
                'number_format' => [
                    // Separators are fixed platform-wide; only the digit system varies
                    // per language. The number system still renders native digits.
                    'decimal' => '.',
                    'thousands' => ',',
                    'system' => $language?->number_system ?? 'latn',
                ],
                // Currency position is exposed on the `currency` prop (below), which
                // is what the frontend reads — no duplicate under `locale`.
            ],
            'isRtl' => (bool) ($language?->is_rtl ?? false),
            'translations' => fn () => $hasTranslationsTable && $hasLanguagesTable
                ? TranslationService::getForLocale($locale)
                : [],
            'languages' => fn () => $hasLanguagesTable
                ? Language::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get(['code', 'name', 'flag', 'is_rtl'])
                : [],
            // Derived from the ONE base currency so the frontend symbol/decimals can
            // never go stale: explicit setting → static catalog → sane default. This
            // keeps every frontend money display in sync when the base currency changes.
            'currency' => (function () {
                $code = base_currency();
                $catalog = CurrencyCatalog::get($code);

                return [
                    'code' => $code,
                    'symbol' => settings('currency_symbol') ?: ($catalog['symbol'] ?? '$'),
                    // Currency display is a global concern (one base currency), configured
                    // under General settings — no longer overridden per language.
                    'position' => settings('currency_position') ?: ($catalog['position'] ?? 'before'),
                    'decimals' => (int) (settings('currency_decimals') ?? $catalog['decimals'] ?? 2),
                ];
            })(),
            'isProAvailable' => fn () => isProAvailable(),
            // Effective resetting-allowance limits (per-user override ?? global),
            // mirroring TokenGuard, so the sidebar shows a Regular-license user's real
            // remaining quota rather than the never-drained wallet balance.
            'userDailyCreditLimit' => fn () => (float) (optional($request->user())->daily_limit ?? settings('user_daily_credit_limit', 0)),
            'userMonthlyCreditLimit' => fn () => (float) (optional($request->user())->monthly_limit ?? settings('user_monthly_credit_limit', 0)),
            'creditsUsedToday' => fn () => (float) (optional($request->user())->credits_used_today ?? 0),
            'creditsUsedMonth' => fn () => (float) (optional($request->user())->credits_used_month ?? 0),
            'isExtendedLicense' => fn () => is_extended_license(),
            'licenseBlocked' => fn () => $this->isLicenseBlocked(),
            'socialLoginProviders' => fn () => $this->getSocialLoginProviders(),
            'captcha' => fn () => CaptchaService::fromSettings()->frontendConfig(),

            'app' => [
                'demo' => config('demo.enabled'),
                'demo_banner_color' => config('demo.banner_color', 'amber'),
                'envato_url' => config('demo.envato_url', 'https://codecanyon.net'),
                // Shown on the sign-in page, so each pair is published only once
                // its password is actually configured. No hardcoded fallback: that
                // would put a known password back on a demo site whose operator
                // never set DEMO_*_PASSWORD. Login.vue/Register.vue already treat
                // null as "no demo credentials to show".
                'demo_credentials' => config('demo.enabled')
                    ? array_filter([
                        'admin' => filled(config('demo.admin_password')) ? [
                            'email' => config('demo.admin_email'),
                            'password' => config('demo.admin_password'),
                        ] : null,
                        'user' => filled(config('demo.user_password')) ? [
                            'email' => config('demo.user_email'),
                            'password' => config('demo.user_password'),
                        ] : null,
                    ]) ?: null
                    : null,
                'name' => $siteName,
            ],

            // Demo style-selector payload — null (and thus absent) unless demo mode is on,
            // so the floating brush picker never renders on a real install. The nav itself
            // rides in globalMenus (see above); this is just the preset/addon catalog and
            // which one is currently active.
            'demoBar' => fn () => config('demo.enabled') ? [
                'selectable' => app(\App\Services\DemoSelectionResolver::class)->catalog(),
                'active' => (string) ($request->cookie('demo_selection') ?? ''),
            ] : null,

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

            'ai' => [
                'model_names' => config('ai.model_names', []),
                'provider_names' => config('ai.provider_names', []),
            ],

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

            'frontendThemeSettings' => fn () => $frontendThemeSettings,
            'frontendHeaderSettings' => fn () => $frontendHeaderSettings,
            'frontendFooterSettings' => fn () => $frontendFooterSettings,
            'frontendHomepageSettings' => fn () => $frontendHomepageSettings,
            'frontendHomepageConfig' => fn () => $frontendHomepageConfig,
            'frontendCustomCodeSettings' => fn () => $frontendCustomCodeSettings,
            'frontendToolPageSettings' => fn () => $frontendToolPageSettings,

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
                'areas' => [
                    'main' => [],
                    'blog' => [],
                    'page' => [
                        ['id' => 'b1', 'type' => 'search_box', 'config' => ['title' => translate('Search'), 'placeholder' => translate('Search articles...')]],
                        ['id' => 'b2', 'type' => 'categories_list', 'config' => ['title' => translate('Categories'), 'show_count' => true]],
                        ['id' => 'b3', 'type' => 'recent_posts', 'config' => ['title' => translate('Recent Posts'), 'count' => 3]],
                    ],
                ],
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
                'recentPosts' => fn () => BlogPost::published()
                    ->with('categories:id,name')
                    ->latest('published_at')
                    ->limit(5)
                    ->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'featured_image', 'is_featured'])
                    ->map(fn (BlogPost $post) => [
                        'title' => $post->title,
                        'slug' => $post->slug,
                        'excerpt' => $post->excerpt,
                        'published_at' => $post->published_at?->toDateString(),
                        'image' => $post->featured_image,
                        'is_featured' => $post->is_featured,
                        'category' => $post->categories->first()?->name,
                    ]),
                'tags' => fn () => BlogTag::where('posts_count', '>', 0)->orderByDesc('posts_count')->limit(30)->get(['name', 'slug', 'posts_count'])->map(fn (BlogTag $tag) => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'url' => route('blog.tag', $tag->slug),
                    'count' => $tag->posts_count,
                ]),
                'blogCategories' => fn () => BlogCategory::active()
                    ->where('posts_count', '>', 0)
                    ->orderBy('sort_order')
                    ->get(['name', 'slug', 'icon', 'color', 'posts_count'])
                    ->map(fn (BlogCategory $cat) => [
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'icon' => $cat->icon,
                        'color' => $cat->color,
                        'posts_count' => $cat->posts_count,
                    ]),
            ],

            // Menus change rarely but are read on nearly every request. Cache the
            // eager-loaded tree and key it on the latest menu/item timestamp so any
            // edit invalidates it automatically (two cheap MAX() lookups replace the
            // full with(items, items.page) load on a cache hit).
            //
            // Cache the ARRAY, not the Eloquent Collection: the database/file cache
            // stores PHP-serialized values, and a serialized Collection can round-trip
            // back as __PHP_Incomplete_Class (which json_encodes to an OBJECT `{}`
            // instead of an array `[]`, breaking `globalMenus.find(...)` on the client).
            // toArray() yields the exact same JSON the Collection would have and
            // serializes cleanly.
            'globalMenus' => function () {
                $menus = \Illuminate\Support\Facades\Cache::remember(
                    'makeai:menus:' . (Menu::max('updated_at') ?? '0') . ':' . (\App\Models\MenuItem::max('updated_at') ?? '0'),
                    3600,
                    fn () => Menu::with(['items' => function ($q) {
                        $q->orderBy('sort_order');
                    }, 'items.page'])->get()->toArray()
                );

                // In demo mode, fold the demo nav (Hero ▸ Hero 1–6, Tool Page ▸ Page 1–4)
                // into the header's own menu so it renders through the normal menu-builder
                // markup — same dropdowns, same styling — rather than a separate bar. Done
                // outside the cache so it never persists into a buyer's real menu.
                return config('demo.enabled') ? $this->injectDemoNavMenu($menus) : $menus;
            },

            'affiliateEnabled' => fn () => is_extended_license() && (bool) settings('affiliate_enabled', true),
            'couponsEnabled' => fn () => coupons_enabled(),
            'byokEnabled' => fn () => (bool) settings('byok_enabled', true),
            'accountDeletionEnabled' => fn () => (bool) settings('account_deletion_enabled', true),
            'playgroundEnabled' => fn () => (bool) settings('playground_enabled', true),
            'chainsEnabled' => fn () => (bool) settings('chains_enabled', true),
            'toolEmbedsEnabled' => fn () => (bool) settings('tool_embeds_enabled', true),
            'ticketsEnabled' => fn () => (bool) settings('tickets_enabled', true),
            'contactEnabled' => fn () => (bool) settings('contact_enabled', true),
            'blogEnabled' => fn () => (bool) settings('blog_enabled', true),
            'notificationsEnabled' => fn () => (bool) settings('notifications_enabled', true),
            'kbEnabled' => fn () => is_addon_active('ai-knowledge-base') && (bool) addon_setting('ai-knowledge-base', 'enabled', true),
            'chatbotEnabled' => fn () => is_addon_active('ai-chatbot'),

            'addonMenuItems' => fn () => app(\App\Services\AddonService::class)->getActiveAddonMenuItems(),

            'appearanceAdminSettings' => fn () => AppearanceSetting::getForScope('admin'),

            'globalToolSettings' => fn () => [
                'brand_voice' => (bool) settings('global_tools_brand_voice_enabled', true),
                'variations' => (bool) settings('global_tools_variations_enabled', true),
                'regenerate' => (bool) settings('global_tools_regenerate_enabled', true),
                'improve' => (bool) settings('global_tools_improve_enabled', true),
                'editor' => (bool) settings('global_tools_editor_enabled', true),
                'show_about' => (bool) settings('global_tools_show_about_enabled', true),
                'show_how_it_works' => (bool) settings('global_tools_show_how_it_works_enabled', true),
                'show_usage_examples' => (bool) settings('global_tools_show_usage_examples_enabled', true),
                'show_faqs' => (bool) settings('global_tools_show_faqs_enabled', true),
                'show_reviews' => (bool) settings('global_tools_show_reviews_enabled', true),
                'embeddable' => (bool) settings('global_tools_embeddable_enabled', true),
                'show_credit_costs' => (bool) settings('show_tool_credit_costs', true),
            ],

            'appearanceThemeSettings' => fn () => $frontendThemeSettings,
            'appearanceToolPageSettings' => fn () => $frontendToolPageSettings,
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
            'linkedin' => translate('LinkedIn'),
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
     * Fold the demo nav groups into the header's menu (slug 'main'). If a real 'main'
     * menu exists the demo parents are prepended to it — the demo switcher is what a
     * buyer is meant to try first, so it leads the header rather than trailing the
     * operator's own links; otherwise a synthetic 'main' menu is added so the header
     * has something to render. Never cached / persisted.
     *
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    private function injectDemoNavMenu(array $menus): array
    {
        $items = $this->buildDemoMenuItems();

        if ($items === []) {
            return $menus;
        }

        foreach ($menus as &$menu) {
            if (($menu['slug'] ?? null) === 'main') {
                $menu['items'] = array_merge($items, $menu['items'] ?? []);

                return $menus;
            }
        }
        unset($menu);

        // No 'main' menu configured yet — supply one so the demo nav still shows.
        $menus[] = [
            'id' => 'demo-main',
            'slug' => 'main',
            'name' => 'Main',
            'location' => 'main',
            'items' => $items,
        ];

        return $menus;
    }

    /**
     * The demo nav as menu-builder-shaped items: a parent per group (Hero, Tool Page, Blog,
     * Pages), each with child links carrying the query param that group is driven by. Tool
     * children target a real sample tool so "Page 2" opens an actual tool page in the chosen
     * layout. Theme presets are not here — the selector modal is where a buyer picks those.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDemoMenuItems(): array
    {
        $sampleSlug = \App\Models\AiTool::active()->orderBy('name')->value('slug');
        $toolBase = $sampleSlug ? "/ai-tools/{$sampleSlug}" : '/ai-tools';

        $groups = [
            ['id' => 'demo-hero', 'label' => (string) config('demo.nav.hero.label', 'Hero'), 'icon' => 'ti ti-home', 'items' => collect(config('demo.nav.hero.items', []))->map(fn (array $i) => ['key' => $i['key'], 'label' => $i['label'], 'url' => '/?demo_hero=' . $i['key']])->all()],
            ['id' => 'demo-tools', 'label' => (string) config('demo.nav.tools.label', 'Tool Page'), 'icon' => 'ti ti-adjustments', 'items' => collect(config('demo.nav.tools.items', []))->map(fn (array $i) => ['key' => $i['key'], 'label' => $i['label'], 'url' => $toolBase . '?demo_tool=' . $i['key']])->all()],
            ['id' => 'demo-blog', 'label' => (string) config('demo.nav.blog.label', 'Blog'), 'icon' => 'ti ti-article', 'items' => collect(config('demo.nav.blog.items', []))->map(fn (array $i) => ['key' => $i['key'], 'label' => $i['label'], 'url' => $this->demoBlogUrl($i)])->all()],
            ['id' => 'demo-pages', 'label' => (string) config('demo.nav.pages.label', 'Pages'), 'icon' => 'ti ti-files', 'items' => $this->demoPageLinks()],
        ];

        $items = [];
        // Negative so any surface that re-sorts by sort_order keeps the demo groups
        // ahead of the operator's own items, matching the injected array order.
        $sort = -9000;

        foreach ($groups as $group) {
            if ($group['items'] === []) {
                continue;
            }

            $items[] = $this->demoMenuItem($group['id'], $group['label'], '#', null, $sort++, $group['icon']);

            foreach ($group['items'] as $child) {
                $items[] = $this->demoMenuItem('demo-' . $child['key'], $child['label'], $child['url'], $group['id'], $sort++);
            }
        }

        return $items;
    }

    /**
     * Where a Blog nav item points. The archive by default, since that is where a sidebar
     * position reads; `'target' => 'post'` sends it to a real article instead, for a layout
     * that only exists on the post itself. Falls back to the archive when the blog has no
     * published post to link at.
     *
     * @param  array<string, mixed>  $item
     */
    private function demoBlogUrl(array $item): string
    {
        $base = '/blog';

        if (($item['target'] ?? null) === 'post') {
            $slug = \App\Models\BlogPost::published()->latest('published_at')->value('slug');

            if ($slug) {
                $base = "/blog/{$slug}";
            }
        }

        return $base . '?demo_blog=' . $item['key'];
    }

    /**
     * The Pages group: config order, minus any CMS page that is not there to be linked to.
     *
     * An item naming a `page` slug is dropped when no published page answers to it — a demo
     * nav is a tour, and a tour that opens a 404 is worse than one item shorter. Items
     * carrying a plain `url` (a route, an on-page anchor) are taken as written.
     *
     * @return array<int, array{key: string, label: string, url: string}>
     */
    private function demoPageLinks(): array
    {
        $items = collect(config('demo.nav.pages.items', []));

        $slugs = $items->pluck('page')->filter()->map(fn ($slug) => (string) $slug)->all();

        $published = $slugs === []
            ? collect()
            : \App\Models\Page::published()->whereIn('slug', $slugs)->pluck('slug');

        return $items
            ->filter(fn (array $item) => ! isset($item['page']) || $published->contains($item['page']))
            ->map(fn (array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'url' => $item['url'] ?? '/' . $item['page'],
            ])
            ->values()
            ->all();
    }

    /**
     * One demo menu item shaped exactly like a MenuItem row the header renders.
     *
     * @return array<string, mixed>
     */
    private function demoMenuItem(string $id, string $label, string $url, ?string $parentId, int $sort, string $icon = ''): array
    {
        return [
            'id' => $id,
            'parent_id' => $parentId,
            'title' => $label,
            'label' => $label,
            'url' => $url,
            'final_url' => $url,
            'icon' => $icon,
            'target' => '_self',
            'is_active' => true,
            'requires_auth' => 'none',
            'sort_order' => $sort,
            'mega_menu' => false,
            // Marks the item as demo chrome rather than the operator's own navigation, so a
            // surface that is not the theme header (the Help Center, for one) can leave it
            // out. The site header shows it; nothing else has to.
            'is_demo' => true,
        ];
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

        return [
            'user' => [
                'ulid' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'country' => $user->country,
                'profession' => $user->profession,
                'phone' => $user->phone,
                'phone_country' => $user->phone_country,
                'phone_verified' => $user->hasVerifiedPhone(),
                'sms_marketing_opt_in' => (bool) $user->sms_marketing_opt_in,
                'timezone' => $user->timezone,
                'credits' => (float) $user->credits,
                'plan_id' => $user->plan_id,
                // Plan display name (used e.g. under the chat sidebar username for pro
                // users). The `plan` relation is already resolved by isPro() below, so
                // this adds no extra query.
                'plan_name' => $user->plan?->name,
                'is_pro' => isProAvailable() && $user->isPro(),
                'subscription_status' => $user->subscription_status,
                'subscription_ends_at' => $user->subscription_ends_at?->toISOString(),
                'trial_ends_at' => $user->trial_ends_at?->toISOString(),
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
                'preferences' => (array) ($user->preferences ?? []),
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
            'paletteChats' => fn () => (is_addon_active('ai-chatbot') && \Illuminate\Support\Facades\Schema::hasTable('conversations'))
                ? $user->conversations()
                    ->latest('last_message_at')
                    ->take(10)
                    ->get(['id', 'ulid', 'title'])
                : [],
        ];
    }

    private function getHeaderCoupon(Request $request): ?array
    {
        if (! isProAvailable() || ! coupons_enabled()) {
            return null;
        }

        $coupon = Coupon::query()
            ->where('show_in_header', true)
            ->first();

        if (! $coupon || ! $coupon->isValid()) {
            return null;
        }

        /* `$request->user()` resolves the DEFAULT guard, and a route group protected with
           `auth:admin` (rather than the core `admin.auth`) calls shouldUse('admin') — so on
           those pages this returns an App\Models\Admin and the User-typed eligibility check
           blew up with a 500. Coupon rules are user-scoped, so anything that is not a User
           is treated as no user at all. */
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

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

        return CountryCatalog::formatMoney((float) $coupon->value, base_currency());
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

        $isAdminRequest = $request->is('admin') || $request->is('admin/*');
        $notifiable = $isAdminRequest ? auth('admin')->user() : $request->user();
        if (! $notifiable) {
            // In-app notifications are per-user; a guest has none. Keep the bell
            // disabled so it neither renders nor polls the auth-only endpoints
            // (which would 401 on every tick for a logged-out visitor).
            return [
                'enabled' => false,
                'driver' => 'disabled',
                'polling_interval' => (int) settings('notifications_polling_interval', 30000),
                'unread_count' => 0,
                'items' => [],
                'realtime' => null,
            ];
        }

        return app(InAppNotificationService::class)->summary($notifiable);
    }

    /**
     * Get admin-specific shared props.
     */
    /**
     * A site-wide banner for the one misconfiguration that leaks credentials silently.
     *
     * The distribution layout keeps the app in <webroot>/core, private by deny rules
     * rather than by being unreachable. nginx reads none of those rules, so a buyer who
     * unzipped onto a VPS and never applied core/deploy/nginx.conf.example is serving
     * core/.env — database password, APP_KEY, every API credential — in the clear.
     *
     * Deliberately reads the cached verdict only, never probing: this runs on every
     * admin request, and an outbound HTTP call here would tax the whole panel. The probe
     * itself belongs to the System Health page, which writes the cache entry. Nothing
     * cached yet simply means no banner until Health has been opened once.
     */
    private function getSecurityAlert(): ?array
    {
        $verdict = Cache::get('system.app_dir_exposed');

        if (! is_array($verdict) || ($verdict['state'] ?? null) !== 'exposed') {
            return null;
        }

        $appDir = basename(base_path());

        return [
            'level' => 'critical',
            'title' => translate('Your configuration file is publicly readable'),
            'body' => translate('Anyone can open :path and read your database password and APP_KEY. Deny the :dir directory in your web server configuration, then change those credentials.', [
                'path' => url("/{$appDir}/.env"),
                'dir' => $appDir,
            ]),
            'action' => translate('Open System Health'),
            'href' => '/admin/system/health',
        ];
    }

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
            'coreUpdate' => $this->getCoreUpdateStatus($admin),
            'securityAlert' => $this->getSecurityAlert(),
            // Shown in the admin footer. Kept to the admin props because it is the
            // operator's build number, not something a visitor has any use for.
            'version' => (string) settings('app_version', '1.0.0'),
            'pendingCommentsCount' => Comment::where('status', 'pending')->count(),
            'sidebarCounts' => [
                'premium' => [
                    'subscriptions' => \App\Models\GatewaySubscription::where('status', 'past_due')->count(),
                    'transactions' => \App\Models\Payment::where('status', 'pending')->count(),
                ],
                'blog' => [
                    'comments' => Comment::where('status', 'pending')->count(),
                ],
                'tool_reviews' => \App\Models\ToolReview::where('is_approved', false)->count(),
                'messages' => \App\Models\ContactMessage::where('is_read', false)->count(),
                'tickets' => \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
                'affiliates' => [
                    'payouts' => \App\Models\AffiliatePayout::where('status', 'pending')->count(),
                    'commissions' => \App\Models\AffiliateCommission::where('status', 'pending')->count(),
                ],
                'newsletter' => [
                    'subscribers' => \App\Models\NewsletterSubscriber::where('status', 'subscribed')->where('created_at', '>=', now()->subDay())->count(),
                ],
            ]
        ];
    }

    /**
     * Core update status for the sidebar badge + header banner. Only surfaced to
     * admins who can actually manage the system. `available` drives the badge;
     * `show_banner` also respects the snooze (24h) / dismiss (this version) state.
     */
    /**
     * Ask the License Server again when the answer has gone stale.
     *
     * update_available only ever moved in two places: the "Check for updates" button, and
     * the updates:check command scheduled daily at 03:00. That schedule is the whole
     * mechanism, so on any install where cron is not actually running — routine on shared
     * hosting, and the reason the Scheduler health check exists — the header banner and
     * the sidebar dot never appeared at all. The operator had to already suspect there was
     * an update in order to find out there was one.
     *
     * Runs after the response is flushed, so no admin page waits on an outbound HTTP call,
     * and takes a short cache lock so concurrent requests cannot each start their own.
     */
    private function refreshUpdateFlagIfStale(): void
    {
        $lastChecked = settings('update_last_checked');

        try {
            $stale = blank($lastChecked) || Carbon::parse($lastChecked)->lt(now()->subHours(6));
        } catch (\Throwable) {
            $stale = true;
        }

        if (! $stale) {
            return;
        }

        // add() is atomic: the first request through claims it, the rest return.
        if (! Cache::add('core_update_check_lock', true, now()->addMinutes(10))) {
            return;
        }

        dispatch(function () {
            try {
                app(\App\Services\UpdateService::class)->checkForUpdate();
            } catch (\Throwable) {
                // No license yet, server unreachable, key unconfigured — all transient and
                // none of them the admin's problem right now. Stamp the attempt anyway, or
                // a License Server having a bad afternoon means every single admin request
                // starts another check.
                settings_set('update_last_checked', now()->toDateTimeString(), 'string', 'system');
            }
        })->afterResponse();
    }

    private function getCoreUpdateStatus($admin): array
    {
        if (! $admin->hasPermission('settings.manage')) {
            return ['available' => false, 'show_banner' => false];
        }

        $this->refreshUpdateFlagIfStale();

        $available = (bool) settings('update_available', false);
        $version = settings('update_version');

        if (! $available || blank($version)) {
            return ['available' => false, 'show_banner' => false];
        }

        // Test mode invents the version by bumping the current one — the License
        // Server is never asked and the release does not exist. Announcing it on
        // every admin screen would be telling the operator a plain untruth; the
        // Updates page explains the simulation instead.
        if (\App\Support\PurchaseCode::testModeActive()) {
            return ['available' => false, 'show_banner' => false];
        }

        $dismissed = settings('core_update_dismissed_version') === $version;
        $snoozedUntil = settings('core_update_snoozed_until');
        $snoozed = filled($snoozedUntil) && now()->lt(Carbon::parse($snoozedUntil));

        return [
            'available' => true,
            'version' => $version,
            'changelog' => settings('update_changelog'),
            'show_banner' => ! $dismissed && ! $snoozed,
            'updates_url' => route('admin.system.updates'),
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
            'setup_url' => route('admin.system.cron-jobs'),
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
