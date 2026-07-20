<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    /**
     * How many tools to surface in the onboarding "recommended" step.
     */
    private const RECOMMENDATION_LIMIT = 6;

    /**
     * Map each onboarding use case to the AI-tool CATEGORIES it draws from,
     * most-relevant first. Category slugs are stable (seeded in
     * AiToolCategorySeeder) whereas individual tool slugs are not — matching by
     * category keeps recommendations meaningful even when the catalog is
     * customised, and empty/unknown mappings fall back to globally popular
     * tools in recommendedTools(). Keys here MUST match the use-case `value`s in
     * OnboardingModal.vue.
     */
    private const USE_CASE_CATEGORIES = [
        'content_creator' => ['blog-content', 'creative-writing', 'website-seo'],
        'social_media' => ['social-media', 'video', 'image-design'],
        'marketer' => ['marketing-strategy', 'advertising', 'email-marketing'],
        'copywriter' => ['advertising', 'website-seo', 'email-marketing'],
        'seo_specialist' => ['website-seo', 'blog-content'],
        'developer' => ['development', 'productivity'],
        'ecommerce' => ['ecommerce', 'advertising', 'email-marketing'],
        'business_owner' => ['business', 'productivity', 'legal-finance'],
        'entrepreneur' => ['business', 'marketing-strategy', 'sales'],
        'sales' => ['sales', 'email-marketing', 'business'],
        'customer_support' => ['customer-support', 'email-marketing'],
        'hr_recruiter' => ['hr-recruiting', 'business'],
        'student' => ['academic', 'language', 'productivity'],
        'educator' => ['academic', 'productivity', 'language'],
        'creative_writer' => ['creative-writing', 'entertainment'],
        // Catch-all: no category filter, so recommendedTools() returns the
        // platform's most popular tools.
        'explore' => [],
    ];

    /**
     * Human-readable profession seeded onto the user from their onboarding use
     * case (see complete()). Labels mirror the option labels in
     * OnboardingModal.vue. 'explore' is intentionally absent — "Just Exploring"
     * is not a profession, so it seeds nothing.
     */
    private const USE_CASE_PROFESSIONS = [
        'content_creator' => 'Content Creator',
        'social_media' => 'Social Influencer',
        'marketer' => 'Marketer',
        'copywriter' => 'Copywriter',
        'seo_specialist' => 'SEO Specialist',
        'developer' => 'Developer',
        'ecommerce' => 'Online Seller',
        'business_owner' => 'Business Owner',
        'entrepreneur' => 'Entrepreneur',
        'sales' => 'Salesperson',
        'customer_support' => 'Support Agent',
        'hr_recruiter' => 'Recruiter',
        'student' => 'Student',
        'educator' => 'Educator',
        'creative_writer' => 'Creative Writer',
    ];

    public function skip(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $user->update(['onboarding_completed_at' => now()]);

        if ($redirect = $this->phoneSetupRedirect($user)) {
            return $redirect;
        }

        return back()->with('success', __('Onboarding skipped. You can complete it later from your dashboard.'));
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'use_case' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $useCase = $validated['use_case'] ?? $user->use_case;
        $attributes = [
            'onboarding_completed_at' => now(),
            'use_case' => $useCase,
        ];

        // Seed the profession from the chosen use case as a helpful default, but
        // never overwrite a profession the user already set (e.g. in their
        // profile). "Just exploring" maps to nothing.
        $profession = self::USE_CASE_PROFESSIONS[$useCase] ?? null;
        if ($profession !== null && blank($user->profession)) {
            $attributes['profession'] = translate($profession);
        }

        $user->update($attributes);

        if ($redirect = $this->phoneSetupRedirect($user)) {
            return $redirect;
        }

        return back()->with('success', __('Welcome aboard! Your dashboard is ready.'));
    }

    /**
     * Once onboarding is done, send the user to their profile when the admin requires
     * a phone number — the phone gate (EnsurePhoneProvided) stands down until then, so
     * this is what hands them over to it.
     */
    private function phoneSetupRedirect(User $user): ?RedirectResponse
    {
        if (phone_requirement_met($user)) {
            return null;
        }

        return redirect()->route('user.dashboard.profile')
            ->with('warning', translate('Please add and verify your phone number to continue.'));
    }

    public function recommendedTools(string $useCase)
    {
        $categorySlugs = self::USE_CASE_CATEGORIES[$useCase] ?? [];

        $categoryIds = empty($categorySlugs)
            ? collect()
            : Category::aiTools()->whereIn('slug', $categorySlugs)->pluck('id');

        // Rank the same way across every query: featured first, then by real
        // traction (usage, then views), then the admin's manual sort order — so
        // recommendations are the tools people actually use for this use case,
        // not an arbitrary alphabetical slice.
        $ranked = fn () => AiTool::query()
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderByDesc('is_featured')
            ->orderByDesc('usage_count')
            ->orderByDesc('views_count')
            ->orderBy('sort_order');

        $picked = $ranked()
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->take(self::RECOMMENDATION_LIMIT)
            ->get();

        // Top up with globally popular tools when the mapped categories don't
        // exist on this install (customised catalog) or hold too few active
        // tools — the step should never render half-empty.
        if ($picked->count() < self::RECOMMENDATION_LIMIT) {
            $fill = $ranked()
                ->whereNotIn('id', $picked->pluck('id')->all())
                ->take(self::RECOMMENDATION_LIMIT - $picked->count())
                ->get();

            $picked = $picked->concat($fill);
        }

        return response()->json(['tools' => $this->presentTools($picked)]);
    }

    /**
     * Shape tools for the onboarding UI.
     *
     * @param  EloquentCollection<int, AiTool>  $tools
     */
    private function presentTools(EloquentCollection $tools): array
    {
        return $tools
            ->map(fn (AiTool $tool) => [
                'name' => $tool->name,
                'slug' => $tool->slug,
                'description' => $tool->description,
                'icon' => $tool->icon,
                'color' => $tool->color,
                'requires_pro' => $tool->isProRequired(),
                'category' => optional($tool->category)->name,
            ])
            ->values()
            ->all();
    }

    public function checklistState(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'checked' => ! is_null($user->onboarding_completed_at),
            'items' => [
                'create_account' => true,
                'verify_email' => ! is_null($user->email_verified_at),
                'complete_profile' => ! empty($user->name) && ! empty($user->avatar),
                'first_document' => $user->documents()->exists(),
                'saved_favorite' => $user->favorites()->exists(),
                'brand_voice' => ! empty($user->brand_voice),
            ],
        ]);
    }

    public function dismissTooltip(Request $request)
    {
        $validated = $request->validate([
            'tooltip_key' => ['required', 'string', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $dismissed = (array) ($user->dismissed_tooltips ?? []);
        $dismissed[] = $validated['tooltip_key'];
        $user->update(['dismissed_tooltips' => array_unique($dismissed)]);

        return back();
    }

    /**
     * Merge a small patch into the user's UI preferences bag (e.g. dismissing the
     * credit-low banner). Scalar values only; merged so unrelated keys survive.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'max:30'],
            'preferences.*' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $preferences = array_merge((array) ($user->preferences ?? []), $validated['preferences']);
        $user->update(['preferences' => $preferences]);

        return back();
    }
}
