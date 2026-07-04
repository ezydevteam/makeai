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
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Posts
        $totalCurrent = SsScheduledPost::count();
        $totalPrevious = SsScheduledPost::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Scheduled
        $scheduledCurrent = SsScheduledPost::scheduled()->count();
        $scheduledPrevious = SsScheduledPost::scheduled()->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Pending Approval
        $pendingCurrent = SsScheduledPost::pendingApproval()->count();
        $pendingPrevious = SsScheduledPost::pendingApproval()->where('created_at', '<', $sevenDaysAgo)->count();

        // 4. Published Today
        $publishedTodayCurrent = SsScheduledPost::where('status', 'published')
            ->whereDate('published_at', today())->count();
        $publishedTodayPrevious = SsScheduledPost::where('status', 'published')
            ->whereDate('published_at', today()->subDays(7))->count();

        // 5. Failed
        $failedCurrent = SsScheduledPost::where('status', 'failed')->count();
        $failedPrevious = SsScheduledPost::where('status', 'failed')->where('created_at', '<', $sevenDaysAgo)->count();

        return inertia('Addons/social-scheduler/Admin/Overview', [
            'total_posts' => [
                'value' => $totalCurrent,
                'comparison' => $this->calculateComparison($totalCurrent, $totalPrevious),
            ],
            'scheduled_posts' => [
                'value' => $scheduledCurrent,
                'comparison' => $this->calculateComparison($scheduledCurrent, $scheduledPrevious),
            ],
            'pending_approval' => [
                'value' => $pendingCurrent,
                'comparison' => $this->calculateComparison($pendingCurrent, $pendingPrevious),
            ],
            'published_today' => [
                'value' => $publishedTodayCurrent,
                'comparison' => $this->calculateComparison($publishedTodayCurrent, $publishedTodayPrevious),
            ],
            'failed_posts' => [
                'value' => $failedCurrent,
                'comparison' => $this->calculateComparison($failedCurrent, $failedPrevious),
            ],
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

    private function calculateComparison(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            return [
                'label' => $current == 0 ? '0%' : '+100%',
                'type' => $current == 0 ? 'neutral' : 'up',
            ];
        }

        $delta = (($current - $previous) / $previous) * 100;
        $rounded = (int) round(abs($delta));

        if ($rounded === 0) {
            return [
                'label' => '0%',
                'type' => 'neutral',
            ];
        }

        return [
            'label' => ($delta > 0 ? '+' : '-') . $rounded . '%',
            'type' => $delta > 0 ? 'up' : 'down',
        ];
    }
}
