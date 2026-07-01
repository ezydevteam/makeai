<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\ToolReview;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ToolReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = ToolReview::with(['user:id,name,email,avatar', 'tool:id,slug,name,icon,color']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tool', function ($tq) use ($search) {
                      $tq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'pending') {
                $query->where('is_approved', false);
            }
        }

        // Rating filter
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return Inertia::render('Admin/AI/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['search', 'status', 'rating']),
        ]);
    }

    public function approve(ToolReview $review)
    {
        $review->update(['is_approved' => true]);

        try {
            app(\App\Services\NotificationEventService::class)->toolReviewApproved($review);
        } catch (\Exception $e) {
            \Log::error('Failed to send review approval notification: ' . $e->getMessage());
        }

        return back()->with('success', translate('Review approved successfully.'));
    }

    public function disapprove(ToolReview $review)
    {
        $review->update(['is_approved' => false]);
        return back()->with('success', translate('Review disapproved successfully.'));
    }

    public function reply(Request $request, ToolReview $review)
    {
        $validated = $request->validate([
            'reply' => 'nullable|string|max:2000',
            'approve' => 'nullable|boolean',
        ]);

        $wasApprovedBefore = $review->is_approved;

        $updateData = ['admin_reply' => $validated['reply']];
        if (isset($validated['approve'])) {
            $updateData['is_approved'] = $validated['approve'];
        }

        $review->update($updateData);

        // Notify user if admin reply was added/updated
        if (!empty($validated['reply'])) {
            try {
                app(\App\Services\NotificationEventService::class)->toolReviewReply($review);
            } catch (\Exception $e) {
                \Log::error('Failed to send review reply notification: ' . $e->getMessage());
            }
        }

        // Notify user if review was approved just now
        if (!$wasApprovedBefore && $review->is_approved) {
            try {
                app(\App\Services\NotificationEventService::class)->toolReviewApproved($review);
            } catch (\Exception $e) {
                \Log::error('Failed to send review approval notification on reply: ' . $e->getMessage());
            }
        }

        return back()->with('success', translate('Admin reply updated.'));
    }

    public function destroy(ToolReview $review)
    {
        $review->delete();
        return back()->with('success', translate('Review deleted successfully.'));
    }
}
