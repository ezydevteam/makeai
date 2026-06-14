<?php

namespace Addons\SocialScheduler\Http\Controllers\Admin;

use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Models\SsSocialAccount;
use Inertia\Response;
use Illuminate\Routing\Controller;

class SsAdminOverviewController extends Controller
{
    public function index(): Response
    {
        return inertia('Addons/social-scheduler/Admin/Overview', [
            'total_posts' => SsScheduledPost::count(),
            'scheduled_posts' => SsScheduledPost::scheduled()->count(),
            'pending_approval' => SsScheduledPost::pendingApproval()->count(),
            'published_today' => SsScheduledPost::where('status', 'published')
                ->whereDate('published_at', today())->count(),
            'failed_posts' => SsScheduledPost::where('status', 'failed')->count(),
            'connected_accounts' => SsSocialAccount::active()->count(),
            'platform_breakdown' => SsSocialAccount::active()
                ->selectRaw('platform, COUNT(*) as count')
                ->groupBy('platform')->get()
                ->map(fn ($r) => ['platform' => $r->platform, 'count' => $r->count]),
            'recent_posts' => SsScheduledPost::latest()
                ->limit(10)
                ->with(['user:id,name,email', 'postPlatforms'])
                ->get()
                ->map(fn ($p) => [
                    'ulid' => $p->ulid,
                    'title' => $p->title,
                    'caption' => $p->caption,
                    'status' => $p->status,
                    'platforms' => $p->platforms,
                    'scheduled_at' => $p->scheduled_at,
                    'user' => $p->user ? ['name' => $p->user->name] : null,
                ]),
        ]);
    }
}
