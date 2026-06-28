<?php

namespace App\Http\Controllers;

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
            ->get(['id', 'name', 'role', 'company', 'avatar', 'content', 'rating', 'is_featured', 'source'])
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
            'pricing_currency_code' => settings('pricing_currency_code', 'USD'),
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
        $shouldLoadPricing = (bool) ($resolvedHomepageSettings['show_pricing'] ?? false);
        $shouldLoadBlog = (bool) ($resolvedHomepageSettings['show_blog'] ?? true);

        if ($shouldLoadAllTools) {
            $userId = auth()->id();
            $userFavoriteToolIds = $userId
                ? \App\Models\Favorite::where('user_id', $userId)
                    ->where('favoriteable_type', \App\Models\AiTool::class)
                    ->pluck('favoriteable_id')
                    ->toArray()
                : [];

            $allTools = AiTool::active()
                ->with('category:id,name')
                ->withCount('favorites')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'description', 'icon', 'color', 'category_id', 'tags', 'usage_count', 'avg_rating', 'is_featured', 'created_at'])
                ->map(function (AiTool $tool) use ($userFavoriteToolIds): array {
                    $data = $tool->toArray();
                    $data['category'] = $tool->category?->name;
                    $data['is_favorited'] = in_array($tool->id, $userFavoriteToolIds);
                    $data['favorites_count'] = $tool->favorites_count ?? 0;

                    return $data;
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

        return Inertia::render('Welcome', [
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'allTools' => $allTools,
            'allToolCategories' => $allToolCategories,
            'recentPosts' => $recentPosts,
            'pricingPlans' => $pricingPlans,
            'pricingCountry' => $pricingCountry,
            'pricingSettings' => $pricingSettings,
        ]);
    }

}
