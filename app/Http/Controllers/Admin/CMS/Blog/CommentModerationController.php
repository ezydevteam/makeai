<?php

namespace App\Http\Controllers\Admin\CMS\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommentBulkActionRequest;
use App\Http\Requests\Admin\CommentSettingsRequest;
use App\Models\Comment;
use App\Services\NotificationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CommentModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeComments();

        $status = $request->string('status')->toString();
        $search = trim((string) $request->string('search')->value());

        $comments = Comment::query()
            ->with([
                'user:id,name,email,avatar', 
                'commentable',
                'reports' => fn ($query) => $query->select('id', 'comment_id', 'user_id', 'reason', 'created_at')->with('user:id,name'),
            ])
            ->withCount('reports')
            ->when(in_array($status, ['pending', 'approved', 'spam'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('content', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/CMS/Blog/Comments', [
            'comments' => $comments,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'pendingCount' => Comment::where('status', 'pending')->count(),
            'settings' => [
                'comments_enabled' => (bool) settings('comments_enabled', true),
                'comments_auto_approve_users' => (bool) settings('comments_auto_approve_users', true),
                'comments_allow_guests' => (bool) settings('comments_allow_guests', false),
                'comments_require_approval' => (bool) settings('comments_require_approval', false),
                'comments_notify_admin' => (bool) settings('comments_notify_admin', false),
                'comments_poll_seconds' => (int) settings('comments_poll_seconds', 60),
            ],
        ]);
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $this->authorizeComments();

        // Only notify on a real transition — re-approving an already-approved
        // comment must not send the author a second "you're live" email.
        $wasApproved = $comment->status === 'approved';

        $comment->update(['status' => 'approved']);

        if (! $wasApproved) {
            $this->notifyApproved($comment);
        }

        return back()->with('success', translate('Comment approved.'));
    }

    public function spam(Comment $comment): RedirectResponse
    {
        $this->authorizeComments();

        $comment->update(['status' => 'spam']);

        return back()->with('success', translate('Comment marked as spam.'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorizeComments();

        $comment->delete();

        return back()->with('success', translate('Comment deleted.'));
    }

    public function bulk(CommentBulkActionRequest $request): RedirectResponse
    {
        $this->authorizeComments();

        $validated = $request->validated();

        $query = Comment::query()->whereIn('id', $validated['ids']);

        if ($validated['action'] === 'approve') {
            // Capture the not-yet-approved rows before the mass update, so only
            // genuine transitions are notified (mass update fires no model events).
            $newlyApproved = (clone $query)->where('status', '!=', 'approved')->get();

            $query->update(['status' => 'approved']);

            foreach ($newlyApproved as $comment) {
                $this->notifyApproved($comment);
            }

            return back()->with('success', translate('Bulk action applied.'));
        }

        $query->update(['status' => 'spam']);

        return back()->with('success', translate('Bulk action applied.'));
    }

    /**
     * Approval mail is a courtesy, not part of the moderation action — a mail
     * failure must not roll back or 500 an otherwise successful approval.
     */
    private function notifyApproved(Comment $comment): void
    {
        try {
            app(NotificationEventService::class)->commentApproved($comment);
        } catch (\Throwable $e) {
            Log::error('Failed to send comment approval notification: '.$e->getMessage(), [
                'comment_id' => $comment->id,
            ]);
        }
    }

    public function updateSettings(CommentSettingsRequest $request): RedirectResponse
    {
        foreach ($request->safe()->all() as $key => $value) {
            settings_set($key, $value, is_bool($value) ? 'boolean' : 'integer', 'comments');
        }

        return back()->with('success', translate('Comment settings saved.'));
    }

    private function authorizeComments(): void
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin?->hasPermission('content.comments')
                || $admin?->hasPermission('content.blog')
                || $admin?->hasPermission('content.pages'),
            403
        );
    }
}
