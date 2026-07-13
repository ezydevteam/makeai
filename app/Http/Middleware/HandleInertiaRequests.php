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
        $siteName = settings('site_name', translate('MakeAI'));
        $frontendPresetService = app(ThemeSettingsService::class);
        $frontendThemeSettings = $frontendPresetService->getResolvedFrontendTheme();
        $frontendHeaderSettings = $frontendPresetService->getResolvedFrontendHeader();
        $frontendFooterSettings = $frontendPresetService->getResolvedFrontendFooter();
        $frontendHomepageSettings = $frontendPresetService->getResolvedFrontendHomepage();
        $frontendHomepageConfig = $frontendPresetService->getResolvedFrontendHomepageConfig();
        $frontendCustomCodeSettings = $frontendPresetService->getStoredCustomCodeSettings();
        $frontendToolPageSettings = $frontendPresetService->getResolvedFrontendToolPage();

        // Driver-aware media resolver: root-relative /storage/... on the local disk
        // (mixed-content safe), fully-qualified bucket/CDN URL on any cloud driver.
        $resolveImageUrl = fn (?string $path) => media_url($path);

        return [
            ...parent::share($request),

            'appName' => $siteName,
            'licenseTestMode' => config('app.license_test_mode', false),
            'purchaseCodeFormat' => \App\Support\PurchaseCode::frontendConfig(),

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
                'number_format' => [
                    'decimal' => $language?->decimal_separator ?? '.',
                    'thousands' => $language?->thousands_separator ?? ',',
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
            'currency' => (function () use ($language) {
                $code = base_currency();
                $catalog = CurrencyCatalog::get($code);

                return [
                    'code' => $code,
                    'symbol' => settings('currency_symbol') ?: ($catalog['symbol'] ?? '$'),
                    'position' => $language?->currency_position
                        ?? (settings('currency_position') ?: ($catalog['position'] ?? 'before')),
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
                'demo_credentials' => config('demo.enabled') ? [
                    'admin' => ['email' => config('demo.admin_email', 'admin@demo.com'), 'password' => config('demo.admin_password', 'demo12345')],
                    'user' => ['email' => config('demo.user_email', 'demo@demo.com'), 'password' => config('demo.user_password', 'demo12345')],
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
                'blocks' => [
                    ['id' => 'b1', 'type' => 'search_box', 'config' => ['title' => translate('Search'), 'placeholder' => translate('Search articles...')]],
                    ['id' => 'b2', 'type' => 'categories_list', 'config' => ['title' => translate('Categories'), 'show_count' => true]],
                    ['id' => 'b3', 'type' => 'recent_posts', 'config' => ['title' => translate('Recent Posts'), 'count' => 3]],
                ],
                'sticky' => true,
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
            ],

            'globalMenus' => fn () => Menu::with(['items' => function ($q) {
                $q->orderBy('sort_order');
            }, 'items.page'])->get(),

            'affiliateEnabled' => fn () => is_extended_license() && (bool) settings('affiliate_enabled', false),
            'ticketsEnabled' => fn () => (bool) settings('tickets_enabled', true),
            'contactEnabled' => fn () => (bool) settings('contact_enabled', true),
            'blogEnabled' => fn () => (bool) settings('blog_enabled', true),
            'notificationsEnabled' => fn () => (bool) settings('notifications_enabled', true),

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
            'coreUpdate' => $this->getCoreUpdateStatus($admin),
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
    private function getCoreUpdateStatus($admin): array
    {
        if (! $admin->hasPermission('settings.manage')) {
            return ['available' => false, 'show_banner' => false];
        }

        $available = (bool) settings('update_available', false);
        $version = settings('update_version');

        if (! $available || blank($version)) {
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
