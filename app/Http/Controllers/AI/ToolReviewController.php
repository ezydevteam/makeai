<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\ToolReview;
use App\Models\ToolReviewVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * ToolReviewController — handles tool reviews and votes.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.14.5
 */
class ToolReviewController extends Controller
{
    /**
     * GET /api/v1/tools/{slug}/reviews — list approved reviews for a tool.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $template = AiTool::where('slug', $slug)->where('is_active', true)->firstOrFail();

        if (! $template->show_reviews) {
            return response()->json([
                'success' => false,
                'message' => translate('Reviews are disabled for this tool.'),
            ], 403);
        }

        $validated = $request->validate([
            'sort' => ['nullable', Rule::in(['recent', 'helpful', 'highest', 'lowest'])],
        ]);

        $query = ToolReview::where('tool_slug', $slug)
            ->approved()
            ->with(['user:id,name,avatar']);

        match ($validated['sort'] ?? 'helpful') {
            'recent' => $query->orderByDesc('created_at'),
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'lowest' => $query->orderBy('rating')->orderByDesc('created_at'),
            default => $query->orderByDesc('helpful_count')->orderByDesc('created_at'),
        };

        $reviews = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'meta' => [
                'distribution' => $this->ratingDistribution($slug),
            ],
        ]);
    }

    /**
     * POST /api/v1/tools/{slug}/reviews — submit a review.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $template = AiTool::where('slug', $slug)->where('is_active', true)->firstOrFail();

        if (! $template->show_reviews) {
            return response()->json([
                'success' => false,
                'message' => translate('Reviews are disabled for this tool.'),
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();

        if ($user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => translate('Your account has been suspended.'),
            ], 403);
        }

        $hasUsedTool = AiUsageLog::where('user_id', $user->id)
            ->where('type', 'template')
            ->where('status', 'completed')
            ->where('metadata->template_slug', $slug)
            ->exists();

        if (! $hasUsedTool) {
            return response()->json([
                'success' => false,
                'message' => translate('You can review this tool after using it at least once.'),
            ], 422);
        }

        // Check for existing review
        $existing = ToolReview::where('user_id', $user->id)
            ->where('tool_slug', $slug)
            ->first();

        if ($existing) {
            if ($existing->created_at->lt(now()->subDays(30))) {
                return response()->json([
                    'success' => false,
                    'message' => translate('Reviews can only be edited within 30 days.'),
                ], 422);
            }

            $existing->update([
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? $existing->comment,
                'is_approved' => ! settings('tools_review_approval_enabled', true) || $existing->shouldAutoApprove(),
            ]);

            return response()->json([
                'success' => true,
                'message' => translate('Review updated successfully.'),
                'data' => $existing->fresh(),
            ]);
        }

        $review = ToolReview::create([
            'tool_slug' => $slug,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'is_approved' => ! settings('tools_review_approval_enabled', true),
        ]);

        // Check auto-approve
        if ($review->is_approved === false && $review->shouldAutoApprove()) {
            $review->update(['is_approved' => true]);
        }

        // Notify Admins
        try {
            app(\App\Services\NotificationEventService::class)->newToolReviewSubmitted($review);
        } catch (\Exception $e) {
            \Log::error('Failed to send tool review notification to admin: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $review->is_approved
                ? translate('Thank you for your review!')
                : translate('Your review has been submitted and is pending approval.'),
            'data' => $review,
        ], 201);
    }

    /**
     * POST /api/v1/tools/reviews/{review}/vote — vote on a review.
     */
    public function vote(Request $request, ToolReview $review): JsonResponse
    {
        $template = AiTool::where('slug', $review->tool_slug)->first();

        if (! $template || ! $template->show_reviews) {
            return response()->json([
                'success' => false,
                'message' => translate('Reviews are disabled for this tool.'),
            ], 403);
        }

        $validated = $request->validate([
            'is_helpful' => 'required|boolean',
        ]);

        $user = Auth::user();

        if ($user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => translate('Your account has been suspended.'),
            ], 403);
        }

        // Prevent voting on own review
        if ($review->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => translate('You cannot vote on your own review.'),
            ], 422);
        }

        // Upsert vote
        ToolReviewVote::updateOrCreate(
            ['review_id' => $review->id, 'user_id' => $user->id],
            ['is_helpful' => $validated['is_helpful']]
        );

        $review->update([
            'helpful_count' => $review->votes()->where('is_helpful', true)->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Vote recorded.'),
            'data' => [
                'helpful_count' => $review->fresh()->helpful_count,
            ],
        ]);
    }

    private function ratingDistribution(string $slug): array
    {
        $counts = ToolReview::where('tool_slug', $slug)
            ->approved()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $total = max(1, (int) $counts->sum());

        return collect(range(5, 1))->mapWithKeys(function (int $rating) use ($counts, $total): array {
            $count = (int) ($counts[$rating] ?? 0);

            return [$rating => [
                'count' => $count,
                'percent' => round(($count / $total) * 100),
            ]];
        })->toArray();
    }
}
