<?php

namespace Addons\AiVideoCreator\Http\Controllers\Admin;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class VideoAdminController extends Controller
{
    public function overview(): \Inertia\Response
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Renders
        $totalCurrent = VcRender::count();
        $totalPrevious = VcRender::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Processing
        $processingCurrent = VcRender::processing()->count();
        $processingPrevious = VcRender::processing()->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Completed Today
        $completedTodayCurrent = VcRender::whereDate('completed_at', today())->where('status', 'completed')->count();
        $completedTodayPrevious = VcRender::whereDate('completed_at', today()->subDays(7))->where('status', 'completed')->count();

        // 4. Failed Today
        $failedTodayCurrent = VcRender::whereDate('created_at', today())->where('status', 'failed')->count();
        $failedTodayPrevious = VcRender::whereDate('created_at', today()->subDays(7))->where('status', 'failed')->count();

        // 5. Storage (GB)
        $storageCurrent = (float) VcRender::sum('file_size_bytes');
        $storagePrevious = (float) VcRender::where('created_at', '<', $sevenDaysAgo)->sum('file_size_bytes');

        $storageCurrentGb = round($storageCurrent / 1024 / 1024 / 1024, 2);
        $storagePreviousGb = round($storagePrevious / 1024 / 1024 / 1024, 2);

        return inertia('Addons/ai-video-creator/Admin/Overview', [
            'total_renders' => [
                'value' => $totalCurrent,
                'comparison' => $this->calculateComparison($totalCurrent, $totalPrevious),
            ],
            'processing' => [
                'value' => $processingCurrent,
                'comparison' => $this->calculateComparison($processingCurrent, $processingPrevious),
            ],
            'completed_today' => [
                'value' => $completedTodayCurrent,
                'comparison' => $this->calculateComparison($completedTodayCurrent, $completedTodayPrevious),
            ],
            'failed_today' => [
                'value' => $failedTodayCurrent,
                'comparison' => $this->calculateComparison($failedTodayCurrent, $failedTodayPrevious),
            ],
            'total_storage_gb' => [
                'value' => $storageCurrentGb,
                'comparison' => $this->calculateComparison($storageCurrentGb, $storagePreviousGb),
            ],
            'by_type' => VcRender::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')->get(),
            'by_provider' => VcRender::selectRaw('provider, COUNT(*) as count, AVG(poll_attempts) as avg_polls')
                ->groupBy('provider')->get(),
            'top_users' => VcRender::selectRaw(
                'user_id, COUNT(*) as renders, SUM(credits_deducted) as credits',
            )
                ->groupBy('user_id')
                ->orderByDesc('renders')
                ->limit(10)
                ->with('user:id,name,email')
                ->get()
                ->map(fn ($r) => [
                    'user_name' => $r->user?->name ?? 'Unknown',
                    'user_email' => $r->user?->email ?? '',
                    'renders' => $r->renders,
                    'credits' => $r->credits,
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
