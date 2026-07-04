<?php

namespace App\Http\Controllers\Admin\CMS\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommentBulkActionRequest;
use App\Http\Requests\Admin\CommentSettingsRequest;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['user:id,name,email,avatar', 'commentable'])
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

        return Inertia::render('Admin/CMS/Blog/Comments/Index', [
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
                'comments_akismet_configured' => filled(settings('comments_akismet_key')),
            ],
        ]);
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $this->authorizeComments();

        $comment->update(['status' => 'approved']);

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

        match ($validated['action']) {
            'approve' => $query->update(['status' => 'approved']),
            'spam' => $query->update(['status' => 'spam']),
        };

        return back()->with('success', translate('Bulk action applied.'));
    }

    public function updateSettings(CommentSettingsRequest $request): RedirectResponse
    {
        foreach ($request->safe()->except('comments_akismet_key') as $key => $value) {
            settings_set($key, $value, is_bool($value) ? 'boolean' : 'integer', 'comments');
        }

        if ($request->filled('comments_akismet_key')) {
            settings_set('comments_akismet_key', $request->validated('comments_akismet_key'), 'encrypted', 'comments');
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
