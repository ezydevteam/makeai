<?php

namespace App\Http\Middleware;

use App\Models\AiTemplate;
use App\Models\Announcement;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Setting;
use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),

            'appName' => config('app.name', 'MakeAI'),
            'locale' => app()->getLocale(),
            'isRtl' => fn () => Language::where('code', app()->getLocale())->value('is_rtl') ?? false,
            'isProAvailable' => fn () => isProAvailable(),

            'app' => [
                'demo' => config('app.demo'),
                'name' => config('app.name'),
            ],

            'auth' => fn () => $this->getAuthProps($request),

            'admin' => fn () => $this->getAdminProps($request),

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

            'newsletterSettings' => fn () => Setting::getGroup('newsletter'),

            'headerConfig' => fn () => Setting::getValue('header_config', [
                'layout' => 'classic',
                'sticky' => true,
                'transparent_homepage' => false,
                'height' => 72,
                'hide_on_scroll' => false,
                'blocks' => [
                    ['id' => 'logo', 'type' => 'logo', 'enabled' => true, 'config' => ['image' => null, 'alt' => 'MakeAI', 'link' => '/', 'show_text' => true, 'text' => 'MakeAI']],
                    ['id' => 'nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'main']],
                    ['id' => 'search', 'type' => 'search', 'enabled' => false, 'config' => []],
                    ['id' => 'lang', 'type' => 'language_switcher', 'enabled' => false, 'config' => []],
                    ['id' => 'dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => []],
                    ['id' => 'cta', 'type' => 'cta_button', 'enabled' => true, 'config' => ['text' => 'Get Started', 'link' => '/register', 'style' => 'filled', 'color' => 'primary']],
                    ['id' => 'user', 'type' => 'user_menu', 'enabled' => true, 'config' => ['show_credits' => true, 'show_avatar' => true]],
                    ['id' => 'credits', 'type' => 'credit_balance', 'enabled' => false, 'config' => []],
                    ['id' => 'notify', 'type' => 'notification_bell', 'enabled' => false, 'config' => []],
                    ['id' => 'social', 'type' => 'social_icons', 'enabled' => false, 'config' => ['icons' => []]],
                    ['id' => 'html', 'type' => 'custom_html', 'enabled' => false, 'config' => ['content' => '']],
                ],
                'mobile' => ['menu_slug' => 'mobile', 'show_logo' => true, 'show_hamburger' => true],
            ]),
            'footerConfig' => fn () => Setting::getValue('footer_config', [
                'layout' => 4,
                'columns' => [
                    [
                        ['id' => 'default_about', 'type' => 'about_text', 'config' => ['logo' => null, 'description' => 'The ultimate AI platform for creators, developers, and businesses. Generate anything you can imagine.']],
                    ],
                    [
                        ['id' => 'default_menu_1', 'type' => 'menu_list', 'config' => ['title' => 'Platform', 'menu_slug' => 'footer-1']],
                    ],
                    [
                        ['id' => 'default_menu_2', 'type' => 'menu_list', 'config' => ['title' => 'Support', 'menu_slug' => 'footer-2']],
                    ],
                    [
                        ['id' => 'default_contact', 'type' => 'contact_info', 'config' => ['title' => 'Contact Us', 'address' => '', 'phone' => '', 'email' => 'support@makeai.com']],
                    ],
                ],
                'bottom_bar' => [
                    'copyright_text' => '© {year} MakeAI. All rights reserved.',
                    'menu_slug' => null,
                    'show_payment_icons' => true,
                    'payment_icons' => ['visa', 'mastercard', 'paypal', 'stripe'],
                    'show_back_to_top' => true,
                ],
            ]),

            'sidebarConfig' => fn () => Setting::getValue('sidebar_config', [
                'blocks' => [
                    ['id' => 'b1', 'type' => 'search_box', 'config' => ['title' => 'Search', 'placeholder' => 'Search articles...']],
                    ['id' => 'b2', 'type' => 'categories_list', 'config' => ['title' => 'Categories', 'show_count' => true]],
                    ['id' => 'b3', 'type' => 'recent_posts', 'config' => ['title' => 'Recent Posts', 'count' => 3]],
                ],
                'position' => 'right',
                'sticky' => true,
            ]),

            'sidebarData' => [
                'toolCategories' => fn () => AiTemplate::select('category')->distinct()->pluck('category')->map(function ($cat) {
                    return [
                        'name' => ucfirst($cat),
                        'slug' => $cat,
                        'count' => AiTemplate::where('category', $cat)->count(),
                    ];
                }),
                'recentTools' => fn () => AiTemplate::latest()->limit(5)->get(['id', 'name', 'slug', 'description', 'color', 'icon']),
            ],

            'globalMenus' => fn () => Menu::with(['items' => function ($q) {
                $q->orderBy('sort_order');
            }])->get(),
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

        return [
            'user' => [
                'id' => $user->id,
                'ulid' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'credits' => (float) $user->credits,
                'plan_id' => $user->plan_id,
                'subscription_status' => $user->subscription_status,
                'referral_code' => $user->referral_code,
                'theme_preference' => $user->theme_preference,
                'isImpersonating' => $request->session()->has('admin_impersonator_id'),
            ],
        ];
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
        ];
    }
}
