<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    private const USE_CASE_TOOLS = [
        'content_creator' => ['blog-intro', 'social-media-caption', 'email-writer', 'seo-meta', 'headline-generator', 'article-rewriter'],
        'marketer' => ['ad-copy', 'email-writer', 'landing-page', 'social-media-caption', 'seo-meta', 'brand-messaging'],
        'developer' => ['code-explain', 'code-generator', 'documentation', 'readme-generator', 'sql-query', 'api-docs'],
        'student' => ['essay-writer', 'study-notes', 'summarize', 'research-summary', 'flashcards', 'quiz-generator'],
        'ecommerce' => ['product-description', 'ad-copy', 'email-writer', 'seo-meta', 'shopping-ad', 'review-response'],
        'business_owner' => ['business-plan', 'email-writer', 'meeting-notes', 'press-release', 'swot-analysis', 'proposal'],
    ];

    public function skip(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $user->update(['onboarding_completed_at' => now()]);

        return back()->with('success', __('Onboarding skipped. You can complete it later from your dashboard.'));
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'use_case' => ['nullable', 'string', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update([
            'onboarding_completed_at' => now(),
            'use_case' => $validated['use_case'] ?? $user->use_case,
        ]);

        return back()->with('success', __('Welcome aboard! Your dashboard is ready.'));
    }

    public function recommendedTools(string $useCase)
    {
        $slugs = self::USE_CASE_TOOLS[$useCase] ?? self::USE_CASE_TOOLS['content_creator'];

        $tools = AiTool::whereIn('slug', $slugs)
            ->where('is_active', true)
            ->get()
            ->map(fn (AiTool $tool) => [
                'name' => $tool->name,
                'slug' => $tool->slug,
                'description' => $tool->description,
                'icon' => $tool->icon,
                'color' => $tool->color,
                'requires_pro' => (bool) $tool->requires_pro,
                'category' => optional($tool->category)->name,
            ])
            ->values()
            ->all();

        return response()->json(['tools' => $tools]);
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
}
