<?php

namespace Addons\SocialScheduler\Http\Controllers\Admin;

use Addons\SocialScheduler\Models\SsScheduledPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class SsApprovalController extends Controller
{
    public function index(): \Inertia\Response
    {
        $posts = SsScheduledPost::pendingApproval()
            ->with(['user:id,name,email', 'media'])
            ->latest()
            ->paginate(20);

        return inertia('Addons/social-scheduler/Admin/Approval', [
            'posts' => $posts->through(fn ($p) => [
                'id' => $p->id,
                'ulid' => $p->ulid,
                'title' => $p->title,
                'caption' => Str::limit($p->caption, 120),
                'platforms' => $p->platforms,
                'post_type' => $p->post_type,
                'scheduled_at' => $p->scheduled_at,
                'media_count' => $p->media->count(),
                'user' => $p->user ? ['name' => $p->user->name, 'email' => $p->user->email] : null,
                'created_at' => $p->created_at,
            ]),
        ]);
    }

    public function approve(SsScheduledPost $post): RedirectResponse
    {
        if ($post->status !== 'pending_approval') {
            return back()->with('error', 'Post is not pending approval.');
        }

        $post->update([
            'status' => 'scheduled',
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        return back()->with('flash', 'Post approved and scheduled.');
    }

    public function reject(Request $request, SsScheduledPost $post): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $post->update([
            'status' => 'draft',
            'rejection_reason' => $validated['reason'],
        ]);

        return back()->with('flash', 'Post rejected.');
    }
}
