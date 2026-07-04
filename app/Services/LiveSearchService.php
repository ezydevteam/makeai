<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AiTool;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class LiveSearchService
{
    private const DEFAULT_SOURCES = [
        'tools' => true,
        'blog' => true,
        'pages' => true,
    ];

    public function searchPublic(string $term): array
    {
        $term = trim(Str::squish($term));

        if (mb_strlen($term) < 3) {
            return [];
        }

        $sources = $this->enabledSources();
        $groups = [];

        if ($sources['tools']) {
            $groups[] = $this->group('tools', translate('AI Tools'), $this->searchTools($term));
        }

        if ($sources['blog']) {
            $groups[] = $this->group('blog', translate('Blog Posts'), $this->searchBlogPosts($term));
        }

        if ($sources['pages']) {
            $groups[] = $this->group('pages', translate('Pages'), $this->searchPages($term));
        }

        return $this->normalizeGroups($groups);
    }

    public function searchAdmin(string $term, ?Admin $admin): array
    {
        $term = trim(Str::squish($term));

        if (! $admin || mb_strlen($term) < 3) {
            return [];
        }

        return $this->normalizeGroups([
            $this->group('admin_menus', translate('Admin Menus'), $this->searchAdminMenus($term, $admin)),
            $this->group('users', translate('Users'), $this->searchUsers($term)),
            $this->group('admin_tools', translate('AI Tool Templates'), $this->searchAdminTools($term)),
            $this->group('admin_blog', translate('Blog Posts'), $this->searchAdminBlogPosts($term)),
            $this->group('admin_pages', translate('Pages'), $this->searchAdminPages($term)),
        ]);
    }

    public function suggestions(string $context = 'public'): array
    {
        $configured = settings($context === 'admin' ? 'admin_live_search_suggestions' : 'live_search_suggestions', []);

        if (is_array($configured) && filled($configured)) {
            return collect($configured)
                ->map(fn ($suggestion) => trim((string) $suggestion))
                ->filter()
                ->take(6)
                ->values()
                ->all();
        }

        if ($context === 'admin') {
            return [
                translate('Users'),
                translate('AI tools'),
                translate('Blog posts'),
                translate('Payment gateways'),
                translate('Translations'),
                translate('System tools'),
            ];
        }

        return [
            translate('Blog article generator'),
            translate('Social media captions'),
            translate('Pricing'),
            translate('Support'),
        ];
    }

    private function enabledSources(): array
    {
        $configured = settings('live_search_sources', []);

        if (! is_array($configured)) {
            return self::DEFAULT_SOURCES;
        }

        return array_merge(self::DEFAULT_SOURCES, array_intersect_key($configured, self::DEFAULT_SOURCES));
    }

    private function searchTools(string $term): Collection
    {
        $query = AiTool::query()
            ->active()
            ->with('category:id,name,slug')
            ->select(['id', 'name', 'slug', 'description', 'icon', 'category_id', 'usage_count']);

        return $this->searchWithFullTextFallback($query, ['name', 'description'], $term, function (Builder $query, string $term): void {
            $query->orderByDesc('usage_count');
        })->map(fn (AiTool $tool): array => [
            'key' => 'tool-'.$tool->slug,
            'title' => $tool->name,
            'excerpt' => $this->highlight($tool->description ?: translate('AI tool template'), $term),
            'url' => route('ai.tools.show', $tool->slug),
            'meta' => $tool->category?->name ?? translate('AI Tool'),
        ]);
    }

    private function searchBlogPosts(string $term): Collection
    {
        $query = BlogPost::query()
            ->published()
            ->select(['id', 'ulid', 'title', 'slug', 'excerpt', 'content', 'reading_time', 'published_at']);

        return $this->searchWithFullTextFallback($query, ['title', 'excerpt', 'content'], $term, function (Builder $query, string $term): void {
            $query->latest('published_at');
        })->map(fn (BlogPost $post): array => [
            'key' => 'blog-'.$post->ulid,
            'title' => $post->title,
            'excerpt' => $this->highlight($post->excerpt ?: Str::limit(strip_tags($post->content), 160), $term),
            'url' => route('blog.show', $post->slug),
            'meta' => translate(':count min read', ['count' => (string) max(1, (int) $post->reading_time)]),
        ]);
    }

    private function searchPages(string $term): Collection
    {
        $query = Page::query()
            ->published()
            ->whereNull('password')
            ->select(['id', 'title', 'slug', 'excerpt']);

        return $this->searchWithFullTextFallback($query, ['title'], $term, function (Builder $query, string $term): void {
            $query->orderBy('sort_order')->orderBy('title');
        })->map(fn (Page $page): array => [
            'key' => 'page-'.$page->slug,
            'title' => $page->title,
            'excerpt' => $this->highlight($page->excerpt ?: translate('Site page'), $term),
            'url' => route('page.show', $page->slug),
            'meta' => translate('Page'),
        ]);
    }

    private function searchAdminMenus(string $term, Admin $admin): Collection
    {
        return collect($this->adminMenuItems())
            ->filter(fn (array $item): bool => $this->canSeeAdminMenu($item, $admin))
            ->filter(function (array $item) use ($term): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $item['title'],
                    $item['section'],
                    $item['keywords'] ?? '',
                ]));

                return str_contains($haystack, mb_strtolower($term));
            })
            ->take(8)
            ->map(fn (array $item): array => [
                'key' => 'admin-menu-'.$item['route'],
                'title' => translate($item['title']),
                'excerpt' => $this->highlight(translate($item['description']), $term),
                'url' => route($item['route']),
                'meta' => translate($item['section']),
            ])
            ->values();
    }

    private function searchUsers(string $term): Collection
    {
        $query = User::query()
            ->select(['id', 'ulid', 'name', 'email', 'subscription_status', 'is_active']);

        return $this->searchWithFullTextFallback($query, ['name', 'email'], $term, function (Builder $query, string $term): void {
            $query->latest('id');
        })->map(fn (User $user): array => [
            'key' => 'user-'.$user->ulid,
            'title' => $user->name,
            'excerpt' => $this->highlight($user->email, $term),
            'url' => route('admin.users.show', $user->ulid),
            'meta' => $user->is_active ? translate('Active user') : translate('Inactive user'),
        ]);
    }

    private function searchAdminTools(string $term): Collection
    {
        $query = AiTool::query()
            ->with('category:id,name,slug')
            ->select(['id', 'name', 'slug', 'description', 'category_id', 'is_active', 'usage_count']);

        return $this->searchWithFullTextFallback($query, ['name', 'description'], $term, function (Builder $query, string $term): void {
            $query->orderByDesc('usage_count');
        })->map(fn (AiTool $tool): array => [
            'key' => 'admin-tool-'.$tool->id,
            'title' => $tool->name,
            'excerpt' => $this->highlight($tool->description ?: translate('AI tool template'), $term),
            'url' => route('admin.ai.tools.edit', $tool->id),
            'meta' => $tool->is_active ? translate('Active template') : translate('Inactive template'),
        ]);
    }

    private function searchAdminBlogPosts(string $term): Collection
    {
        $query = BlogPost::query()
            ->select(['id', 'ulid', 'title', 'slug', 'excerpt', 'content', 'status', 'updated_at']);

        return $this->searchWithFullTextFallback($query, ['title', 'excerpt', 'content'], $term, function (Builder $query, string $term): void {
            $query->latest('updated_at');
        })->map(fn (BlogPost $post): array => [
            'key' => 'admin-blog-'.$post->ulid,
            'title' => $post->title,
            'excerpt' => $this->highlight($post->excerpt ?: Str::limit(strip_tags($post->content), 160), $term),
            'url' => route('admin.blog.posts.edit', $post->ulid),
            'meta' => translate(Str::headline($post->status)),
        ]);
    }

    private function searchAdminPages(string $term): Collection
    {
        $query = Page::query()
            ->select(['id', 'title', 'slug', 'excerpt', 'status', 'updated_at']);

        return $this->searchWithFullTextFallback($query, ['title'], $term, function (Builder $query, string $term): void {
            $query->latest('updated_at');
        })->map(fn (Page $page): array => [
            'key' => 'admin-page-'.$page->id,
            'title' => $page->title,
            'excerpt' => $this->highlight($page->excerpt ?: translate('CMS page'), $term),
            'url' => route('admin.pages.edit', $page->id),
            'meta' => translate(Str::headline($page->status)),
        ]);
    }

    private function group(string $type, string $label, Collection $results): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'results' => $results,
        ];
    }

    private function normalizeGroups(array $groups): array
    {
        return collect($groups)
            ->filter(fn (array $group): bool => $group['results']->isNotEmpty())
            ->map(fn (array $group): array => [
                ...$group,
                'results' => $group['results']->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function canSeeAdminMenu(array $item, Admin $admin): bool
    {
        if (! empty($item['pro_only']) && ! isProAvailable()) {
            return false;
        }

        if (! Route::has($item['route'])) {
            return false;
        }

        $permissions = $item['permissions'] ?? [];

        if ($permissions === []) {
            return true;
        }

        return $admin->hasAnyPermission($permissions);
    }

    private function searchWithFullTextFallback(Builder $query, array $columns, string $term, callable $fallbackOrder): Collection
    {
        if ($this->canUseFullText()) {
            try {
                $columnList = collect($columns)
                    ->map(fn (string $column): string => DB::getQueryGrammar()->wrap($column))
                    ->implode(', ');

                return (clone $query)
                    ->selectRaw("MATCH ({$columnList}) AGAINST (? IN NATURAL LANGUAGE MODE) AS search_relevance", [$term])
                    ->whereRaw("MATCH ({$columnList}) AGAINST (? IN NATURAL LANGUAGE MODE)", [$term])
                    ->orderByDesc('search_relevance')
                    ->limit(5)
                    ->get();
            } catch (QueryException) {
                // Some installs may not have FULLTEXT indexes yet; LIKE keeps search available.
            }
        }

        $fallbackQuery = (clone $query)->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });

        $fallbackOrder($fallbackQuery, $term);

        return $fallbackQuery->limit(5)->get();
    }

    private function canUseFullText(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function highlight(?string $value, string $term): string
    {
        $plain = Str::limit(Str::squish(strip_tags((string) $value)), 180);
        $escaped = e($plain);
        $escapedTerm = preg_quote(e($term), '/');

        if ($escapedTerm === '') {
            return $escaped;
        }

        return preg_replace('/('.$escapedTerm.')/iu', '<mark>$1</mark>', $escaped) ?? $escaped;
    }

    private function adminMenuItems(): array
    {
        return [
            ['title' => 'Dashboard', 'section' => 'Admin', 'route' => 'admin.dashboard', 'permissions' => ['dashboard.view'], 'description' => 'Open the admin dashboard overview.', 'keywords' => 'home overview stats'],
            ['title' => 'Users', 'section' => 'Admin', 'route' => 'admin.users.index', 'permissions' => ['users.view'], 'description' => 'Manage customer accounts, credits, status, and profile details.', 'keywords' => 'customers members accounts'],
            ['title' => 'Administrators', 'section' => 'Admin', 'route' => 'admin.admins.index', 'permissions' => ['admins.view'], 'description' => 'Manage admin accounts and staff access.', 'keywords' => 'staff admins'],
            ['title' => 'Roles & Permissions', 'section' => 'Admin', 'route' => 'admin.roles.index', 'permissions' => ['roles.view'], 'description' => 'Control role permissions for admin users.', 'keywords' => 'rbac access security'],
            ['title' => 'AI Providers & Keys', 'section' => 'AI Tools', 'route' => 'admin.ai.index', 'permissions' => ['ai.tools'], 'description' => 'Configure AI providers, models, and API keys.', 'keywords' => 'openai anthropic gemini model provider'],
            ['title' => 'Integrations', 'section' => 'Settings', 'route' => 'admin.ai.integrations.index', 'permissions' => ['ai.tools'], 'description' => 'Configure third-party service integrations.', 'keywords' => 'pexels unsplash search transcript api'],
            ['title' => 'AI Categories', 'section' => 'AI Tools', 'route' => 'admin.ai.categories.index', 'permissions' => ['ai.tools'], 'description' => 'Manage AI tool categories.', 'keywords' => 'tool category'],
            ['title' => 'AI Tools', 'section' => 'AI Tools', 'route' => 'admin.ai.tools.index', 'permissions' => ['ai.tools'], 'description' => 'Manage AI tools, prompts, fields, and SEO.', 'keywords' => 'tools prompts generation'],
            ['title' => 'AI Access Settings', 'section' => 'AI Tools', 'route' => 'admin.ai.access.index', 'permissions' => ['ai.tools'], 'description' => 'Control public, login, free, and pro access for tools.', 'keywords' => 'permissions access level'],
            ['title' => 'AI Usage & Logs', 'section' => 'AI Tools', 'route' => 'admin.ai.logs.index', 'permissions' => ['ai.tools'], 'description' => 'Review AI usage logs and generation activity.', 'keywords' => 'tokens credits logs'],
            ['title' => 'Plans & Pricing', 'section' => 'Payments', 'route' => 'admin.plans.index', 'permissions' => ['plans.view'], 'description' => 'Manage subscription plans and pricing.', 'keywords' => 'subscriptions billing price', 'pro_only' => true],
            ['title' => 'Payment Gateways', 'section' => 'Payments', 'route' => 'admin.payment-gateways.index', 'permissions' => ['payments.view', 'payments.gateways'], 'description' => 'Configure Stripe, PayPal, bank transfer, and other gateways.', 'keywords' => 'checkout gateway billing', 'pro_only' => true],
            ['title' => 'Coupons', 'section' => 'Payments', 'route' => 'admin.coupons.index', 'permissions' => ['payments.gateways'], 'description' => 'Manage coupon codes, discounts, and header promotions.', 'keywords' => 'discount promo', 'pro_only' => true],
            ['title' => 'Affiliate', 'section' => 'Payments', 'route' => 'admin.affiliate.index', 'permissions' => ['payments.gateways'], 'description' => 'Review affiliate reports, commissions, and payouts.', 'keywords' => 'referral commission payout', 'pro_only' => true],
            ['title' => 'Pages', 'section' => 'Content & CMS', 'route' => 'admin.pages.index', 'permissions' => ['content.pages'], 'description' => 'Manage CMS pages and page SEO.', 'keywords' => 'cms content'],
            ['title' => 'Blog Posts', 'section' => 'Content & CMS', 'route' => 'admin.blog.posts.index', 'permissions' => ['content.blog'], 'description' => 'Manage blog posts, drafts, publishing, and SEO.', 'keywords' => 'articles content'],
            ['title' => 'Blog Categories', 'section' => 'Content & CMS', 'route' => 'admin.blog.categories.index', 'permissions' => ['content.blog'], 'description' => 'Manage blog categories.', 'keywords' => 'blog taxonomy'],
            ['title' => 'Blog Tags', 'section' => 'Content & CMS', 'route' => 'admin.blog.tags.index', 'permissions' => ['content.blog'], 'description' => 'Manage blog tags.', 'keywords' => 'labels taxonomy'],
            ['title' => 'Blog Settings', 'section' => 'Content & CMS', 'route' => 'admin.blog.settings.edit', 'permissions' => ['content.blog'], 'description' => 'Configure blog display, SEO, comments, and RSS settings.', 'keywords' => 'blog configuration'],
            ['title' => 'Testimonials', 'section' => 'Content & CMS', 'route' => 'admin.testimonials.index', 'permissions' => ['content.testimonials'], 'description' => 'Manage homepage and marketing testimonials.', 'keywords' => 'reviews social proof'],
            ['title' => 'FAQs', 'section' => 'Content & CMS', 'route' => 'admin.faqs.index', 'permissions' => ['content.faq'], 'description' => 'Manage frequently asked questions.', 'keywords' => 'questions answers'],
            ['title' => 'Comments', 'section' => 'Content & CMS', 'route' => 'admin.comments.index', 'permissions' => ['content.comments', 'content.blog', 'content.pages'], 'description' => 'Moderate user comments and reports.', 'keywords' => 'moderation community'],
            ['title' => 'Announcements', 'section' => 'Content & CMS', 'route' => 'admin.announcements.index', 'permissions' => ['content.pages'], 'description' => 'Manage site-wide announcement banners.', 'keywords' => 'banner notice'],
            ['title' => 'Themes', 'section' => 'Appearance', 'route' => 'admin.themes', 'permissions' => ['addons.view'], 'description' => 'Manage installed themes and theme settings.', 'keywords' => 'design template'],
            ['title' => 'Addons', 'section' => 'Appearance', 'route' => 'admin.addons', 'permissions' => ['addons.view'], 'description' => 'Manage installed addons and extensions.', 'keywords' => 'plugins extensions modules'],
            ['title' => 'Menus', 'section' => 'Appearance', 'route' => 'admin.menus.index', 'permissions' => ['settings.manage'], 'description' => 'Build public navigation menus.', 'keywords' => 'navigation menu'],
            ['title' => 'Appearance Settings', 'section' => 'Appearance', 'route' => 'admin.appearance.index', 'permissions' => ['settings.manage'], 'description' => 'Configure site appearance options.', 'keywords' => 'branding style design'],
            ['title' => 'Sidebar Builder', 'section' => 'Appearance', 'route' => 'admin.sidebar.index', 'permissions' => ['settings.manage'], 'description' => 'Edit public sidebar widgets and blocks.', 'keywords' => 'widgets'],
            ['title' => 'General Settings', 'section' => 'Settings', 'route' => 'admin.settings.index', 'permissions' => ['settings.general'], 'description' => 'Configure platform name, locale, currency, and timezone.', 'keywords' => 'app name language currency timezone general settings'],
            ['title' => 'System', 'section' => 'System', 'route' => 'admin.system.index', 'permissions' => ['settings.manage'], 'description' => 'Health check, updates, cron jobs, maintenance mode, and admin appearance.', 'keywords' => 'system health update cron maintenance appearance'],
            ['title' => 'Mail Settings', 'section' => 'Settings', 'route' => 'admin.mail.index', 'permissions' => ['settings.manage'], 'description' => 'Configure mail delivery settings.', 'keywords' => 'smtp email'],
            ['title' => 'Mail Templates', 'section' => 'Settings', 'route' => 'admin.mail.templates.index', 'permissions' => ['settings.manage'], 'description' => 'Manage database email templates.', 'keywords' => 'email template'],
            ['title' => 'Mail Logs', 'section' => 'Settings', 'route' => 'admin.mail.logs.index', 'permissions' => ['settings.manage'], 'description' => 'Review sent email logs and resend failures.', 'keywords' => 'email logs'],
            ['title' => 'Notification Settings', 'section' => 'Settings', 'route' => 'admin.notifications.settings', 'permissions' => ['settings.general'], 'description' => 'Configure in-app notification channels.', 'keywords' => 'reverb bell alerts'],
            ['title' => 'Advertisements', 'section' => 'Marketing', 'route' => 'admin.ads.index', 'permissions' => ['settings.manage'], 'description' => 'Manage ad zones, campaigns, and tracking.', 'keywords' => 'ads banners'],
            ['title' => 'Social Counters', 'section' => 'Settings', 'route' => 'admin.social-counters.settings.edit', 'permissions' => ['settings.manage'], 'description' => 'Configure social profiles and follower counters.', 'keywords' => 'social counters links followers'],
            ['title' => 'Newsletter', 'section' => 'Community', 'route' => 'admin.newsletter.index', 'permissions' => ['settings.manage'], 'description' => 'Manage subscribers and newsletter campaigns.', 'keywords' => 'email marketing campaign'],
            ['title' => 'Support Tickets', 'section' => 'Support', 'route' => 'admin.support.tickets.index', 'permissions' => ['settings.manage'], 'description' => 'Review and reply to customer support tickets.', 'keywords' => 'helpdesk tickets'],
            ['title' => 'Support Departments', 'section' => 'Support', 'route' => 'admin.support.departments.index', 'permissions' => ['settings.manage'], 'description' => 'Manage support departments and assignment rules.', 'keywords' => 'helpdesk teams'],
            ['title' => 'Canned Responses', 'section' => 'Support', 'route' => 'admin.support.canned-responses.index', 'permissions' => ['settings.manage'], 'description' => 'Manage reusable support replies.', 'keywords' => 'saved replies'],
            ['title' => 'Support Settings', 'section' => 'Support', 'route' => 'admin.support.settings.edit', 'permissions' => ['settings.manage'], 'description' => 'Configure support ticket behavior.', 'keywords' => 'helpdesk settings'],
            ['title' => 'Languages', 'section' => 'Localization', 'route' => 'admin.languages.index', 'permissions' => ['settings.manage'], 'description' => 'Manage active languages and locale metadata.', 'keywords' => 'translation locale'],
        ];
    }
}
