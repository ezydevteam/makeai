<?php

namespace App\Http\Controllers;

use App\Http\Requests\Comments\ReportCommentRequest;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\CommentReport;
use App\Services\NotificationEventService;
use App\Services\SpamFilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $commentableClass = $request->commentableClass();
        $commentable = $commentableClass::query()->findOrFail($data['commentable_id']);

        if (method_exists($commentable, 'getAttribute') && $commentable->getAttribute('allow_comments') === false) {
            abort(403, translate('Comments are closed for this content.'));
        }

        if (! empty($data['parent_id'])) {
            $parent = Comment::query()
                ->approved()
                ->whereNull('parent_id')
                ->where('commentable_type', $commentableClass)
                ->where('commentable_id', $commentable->id)
                ->findOrFail($data['parent_id']);
        }

        $requiresApproval = (bool) settings('comments_require_approval', false);
        $autoApproveUsers = (bool) settings('comments_auto_approve_users', true);
        $isAuthenticated = $request->user() !== null;

        $status = (! $requiresApproval && $isAuthenticated && $autoApproveUsers) ? 'approved' : 'pending';

        // Route Akismet-flagged submissions straight to the admin Spam tab so they
        // never auto-publish or clutter the pending-moderation queue.
        $isSpam = SpamFilterService::fromSettings()->check([
            'type' => 'comment',
            'content' => $data['content'],
            'author' => $isAuthenticated ? $request->user()->name : ($data['guest_name'] ?? null),
            'email' => $isAuthenticated ? $request->user()->email : ($data['guest_email'] ?? null),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
        ]);

        if ($isSpam) {
            $status = 'spam';
        }

        $comment = Comment::create([
            'commentable_type' => $commentableClass,
            'commentable_id' => $commentable->id,
            'user_id' => $request->user()?->id,
            'parent_id' => $parent->id ?? null,
            'content' => $data['content'],
            'status' => $status,
            'guest_name' => $isAuthenticated ? null : $data['guest_name'],
            'guest_email' => $isAuthenticated ? null : $data['guest_email'],
        ]);

        if ($comment->status === 'pending') {
            app(NotificationEventService::class)->newCommentPending($comment);
        }

        // Spam is silently accepted from the visitor's side (no feedback that the
        // filter triggered) but held for admin review.
        return back()->with(
            'success',
            $comment->status === 'approved'
                ? translate('Comment posted.')
                : translate('Comment submitted for approval.')
        );
    }

    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->canBeEditedBy($request->user()), 403);

        $comment->update([
            'content' => $request->validated('content'),
            'status' => (bool) settings('comments_require_approval', false) ? 'pending' : $comment->status,
        ]);

        return back()->with('success', translate('Comment updated.'));
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id !== null && $comment->user_id === $request->user()?->id, 403);

        $comment->delete();

        return back()->with('success', translate('Comment deleted.'));
    }

    public function like(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->status === 'approved', 404);

        $attributes = [
            'comment_id' => $comment->id,
            'user_id' => $request->user()?->id,
            'ip_hash' => $request->user() ? null : $this->ipHash($request),
        ];

        DB::transaction(function () use ($comment, $attributes) {
            $query = CommentLike::query()->where('comment_id', $comment->id);

            if ($attributes['user_id']) {
                $query->where('user_id', $attributes['user_id']);
            } else {
                $query->where('ip_hash', $attributes['ip_hash']);
            }

            $existing = $query->first();

            if ($existing) {
                $existing->delete();
                $comment->decrement('likes_count');
            } else {
                CommentLike::create($attributes);
                $comment->increment('likes_count');
            }
        });

        return back();
    }

    public function report(ReportCommentRequest $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->status === 'approved', 404);

        CommentReport::updateOrCreate(
            [
                'comment_id' => $comment->id,
                'user_id' => $request->user()?->id,
                'ip_hash' => $request->user() ? null : $this->ipHash($request),
            ],
            [
                'reason' => $request->validated('reason'),
            ]
        );

        return back()->with('success', translate('Comment reported for review.'));
    }

    private function ipHash(Request $request): string
    {
        return hash('sha256', $request->ip().'|'.config('app.key'));
    }
}
