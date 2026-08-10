<?php

namespace Resources\Themes\Default\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Testimonial;
use App\Services\ThemeSettingsService;
use App\Services\Pricing\PlanPriceResolver;
use App\Services\Pricing\PricingCountryDetector;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    public function show(): Response
    {
        $resolvedHomepageSettings = app(ThemeSettingsService::class)->getResolvedFrontendHomepage();

        $testimonials = Testimonial::active()
            ->ordered()
            ->get(['id', 'name', 'role', 'company', 'avatar', 'content', 'rating', 'is_featured', 'source', 'show_source'])
            ->toArray();

        $faqs = Faq::active()
            ->ordered()
            ->with('category:id,name,sort_order')
            ->get(['id', 'question', 'answer', 'category_id', 'sort_order'])
            ->toArray();

        $allTools = [];
        $allToolCategories = [];
        $recentPosts = [];
        $pricingPlans = [];
        $pricingCountry = null;
        $pricingSettings = [
            'pricing_show_monthly' => settings('pricing_show_monthly', true),
            'pricing_show_yearly' => settings('pricing_show_yearly', true),
            'pricing_show_lifetime' => settings('pricing_show_lifetime', true),
            'pricing_currency_code' => base_currency(),
            'pricing_auto_localize' => (bool) settings('pricing_auto_localize', true),
            'pricing_trial_button_text' => settings('pricing_trial_button_text', 'Start Trial'),
            'pricing_featured_label_text' => settings('pricing_featured_label_text', 'Recommended'),
            'pricing_checkout_button_text' => settings('pricing_checkout_button_text', 'Choose Plan'),
        ];

        $shouldLoadAllTools = (bool) ($resolvedHomepageSettings['show_tools'] ?? true);
        $heroVariant = $resolvedHomepageSettings['hero_variant'] ?? 'centered-gradient';

        // Always load allTools when the hero variant is tools-grid or featured — they need tool data
        if ($heroVariant === 'tools-grid' || $heroVariant === 'featured') {
            $shouldLoadAllTools = true;
        }
        // Plans are only purchasable when billing is available (Extended licence + subscriptions
        // enabled), so do not ship them to a homepage that cannot sell them.
        $shouldLoadPricing = isProAvailable() && (bool) ($resolvedHomepageSettings['show_pricing'] ?? false);
        $shouldLoadBlog = (bool) ($resolvedHomepageSettings['show_blog'] ?? true);

        if ($shouldLoadAllTools) {
            $userId = auth()->id();
            $userFavoriteToolIds = $userId
                ? \App\Models\Favorite::where('user_id', $userId)
                    ->where('favoriteable_type', \App\Models\AiTool::class)
                    ->pluck('favoriteable_id')
                    ->toArray()
                : [];

            // The column list MUST be applied with select() rather than passed to get().
            // withCount() sets `select ai_tools.*` on the underlying query, and the query
            // builder only honours get()'s columns when none are set yet — so a get([...])
            // list here is silently discarded and every column (including the private
            // prompt_system / prompt_user) would be serialised into the page HTML.
            $allTools = AiTool::active()
                ->select(['id', 'slug', 'name', 'description', 'icon', 'color', 'category_id', 'tags', 'usage_count', 'avg_rating', 'is_featured', 'created_at'])
                ->with('category:id,name')
                ->withCount('favorites')
                ->orderBy('name')
                ->get()
                ->map(function (AiTool $tool) use ($userFavoriteToolIds): array {
                    return [
                        'id' => $tool->id,
                        'slug' => $tool->slug,
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'icon' => $tool->icon,
                        'color' => $tool->color,
                        'category' => $tool->category?->name,
                        'tags' => $tool->tags,
                        'usage_count' => $tool->usage_count,
                        'avg_rating' => $tool->avg_rating,
                        'is_featured' => $tool->is_featured,
                        'created_at' => $tool->created_at?->toDateString(),
                        'is_favorited' => in_array($tool->id, $userFavoriteToolIds),
                        'favorites_count' => $tool->favorites_count ?? 0,
                    ];
                })
                ->toArray();

            $allToolCategories = $allTools
                ? array_values(array_unique(array_values(array_filter(array_map(fn ($tool) => $tool['category'] ?? null, $allTools)))))
                : [];
        }

        if ($shouldLoadPricing) {
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

        if ($shouldLoadBlog) {
            $recentPosts = BlogPost::published()
                ->with('categories:id,name')
                ->latest('published_at')
                ->limit(12)
                ->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'featured_image', 'is_featured'])
                ->map(fn (BlogPost $post) => [
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt,
                    'published_at' => $post->published_at?->toDateString(),
                    'image' => $post->featured_image,
                    'is_featured' => $post->is_featured,
                    'category' => $post->categories->first()?->name,
                ])
                ->toArray();
        }

        return Inertia::render('Home', [
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'allTools' => $allTools,
            'allToolCategories' => $allToolCategories,
            'recentPosts' => $recentPosts,
            'pricingPlans' => $pricingPlans,
            'pricingCountry' => $pricingCountry,
            'pricingSettings' => $pricingSettings,
            'seo' => $this->buildSeo(),
        ]);
    }

    /**
     * Server-rendered SEO payload for the homepage (read by app.blade.php). Carries
     * the admin-set homepage meta title/description/OG plus the site-wide
     * Organization and WebSite JSON-LD so crawlers see them without running JS.
     */
    private function buildSeo(): array
    {
        $baseUrl  = rtrim(config('app.url'), '/');
        $siteName = settings('site_name', config('app.name', ''));

        $homepageSeo = data_get(
            app(ThemeSettingsService::class)->getResolvedFrontendHomepageConfig(),
            'settings.seo',
            []
        );

        // Homepage title is "<site> <separator> <tagline>" — the site name leads here, so
        // the admin's separator sits between site and tagline rather than before the site.
        // An explicit homepage meta_title is a complete, admin-authored string and wins
        // outright (no separator applied). Home.vue hands this value straight to the
        // title callback, so the tab and this server tag are the same string.
        $tagline = settings('site_tagline', '') ?: translate('One platform. Every AI tool.');
        $title = data_get($homepageSeo, 'meta_title')
            ?: $siteName.' '.app(ThemeSettingsService::class)->getTitleSeparator().' '.$tagline;
        $description = data_get($homepageSeo, 'meta_description')
            ?: settings('site_description', '')
            ?: translate(':app — One platform. Every AI tool.', ['app' => $siteName]);

        // Social scrapers require absolute image URLs; media_url() is root-relative
        // on the local disk, so absolutize against the configured app URL.
        $ogImage = media_url(data_get($homepageSeo, 'og_image') ?: settings('site_og_image', ''));
        if ($ogImage !== '' && str_starts_with($ogImage, '/')) {
            $ogImage = $baseUrl.$ogImage;
        }

        $logo = media_url(settings('site_logo_light', '') ?: settings('site_favicon_png', ''));
        if ($logo !== '' && str_starts_with($logo, '/')) {
            $logo = $baseUrl.$logo;
        }

        $sameAs = collect(app(\App\Services\SocialService::class)->configuredFollowProfiles())
            ->pluck('profile_url')
            ->filter()
            ->values()
            ->all();

        $organization = array_filter([
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $siteName,
            'url'      => $baseUrl,
            'logo'     => $logo ?: null,
            'sameAs'   => $sameAs ?: null,
        ]);

        $website = [
            '@context'      => 'https://schema.org',
            '@type'         => 'WebSite',
            'name'          => $siteName,
            'url'           => $baseUrl,
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $baseUrl.'/ai-tools?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $baseUrl.'/',
            'og'          => [
                'title'       => $title,
                'description' => $description,
                'image'       => $ogImage ?: null,
                'type'        => 'website',
                'url'         => $baseUrl.'/',
            ],
            'twitter'     => [
                'card'        => $ogImage ? 'summary_large_image' : 'summary',
                'title'       => $title,
                'description' => $description,
                'image'       => $ogImage ?: null,
            ],
            'schemas'     => [$organization, $website],
        ];
    }
}
