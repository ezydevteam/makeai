<?php

namespace Addons\AiRepurposer\Http\Controllers\Admin;

use Addons\AiRepurposer\Models\RpJob;
use Addons\AiRepurposer\Models\RpOutput;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function index(): Response
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Jobs
        $totalJobsCurrent = RpJob::count();
        $totalJobsPrevious = RpJob::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Queued
        $queuedCurrent = RpJob::where('status', 'queued')->count();
        $queuedPrevious = RpJob::where('status', 'queued')->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Processing
        $processingCurrent = RpJob::whereIn('status', ['transcribing', 'generating'])->count();
        $processingPrevious = RpJob::whereIn('status', ['transcribing', 'generating'])->where('created_at', '<', $sevenDaysAgo)->count();

        // 4. Completed
        $completedCurrent = RpJob::whereIn('status', ['completed', 'partial'])->count();
        $completedPrevious = RpJob::whereIn('status', ['completed', 'partial'])->where('created_at', '<', $sevenDaysAgo)->count();

        // 5. Failed
        $failedCurrent = RpJob::where('status', 'failed')->count();
        $failedPrevious = RpJob::where('status', 'failed')->where('created_at', '<', $sevenDaysAgo)->count();

        // 6. Completed Today
        $completedTodayCurrent = RpJob::whereIn('status', ['completed', 'partial'])
            ->whereDate('updated_at', today())
            ->count();
        $completedTodayPrevious = RpJob::whereIn('status', ['completed', 'partial'])
            ->whereDate('updated_at', today()->subDays(7))
            ->count();

        // 7. Total Outputs
        $totalOutputsCurrent = RpOutput::count();
        $totalOutputsPrevious = RpOutput::where('created_at', '<', $sevenDaysAgo)->count();

        // 8. Saved Outputs
        $savedOutputsCurrent = RpOutput::where('is_saved', true)->count();
        $savedOutputsPrevious = RpOutput::where('is_saved', true)->where('created_at', '<', $sevenDaysAgo)->count();

        // 9. Credits Used
        $totalCreditsCurrent = (float) RpJob::sum('credits_deducted');
        $totalCreditsPrevious = (float) RpJob::where('created_at', '<', $sevenDaysAgo)->sum('credits_deducted');

        // 10. Words Processed
        $totalWordsCurrent = (int) RpJob::sum('word_count');
        $totalWordsPrevious = (int) RpJob::where('created_at', '<', $sevenDaysAgo)->sum('word_count');

        $byFormat = RpOutput::query()
            ->select('format', DB::raw('count(*) as total'))
            ->groupBy('format')
            ->orderByDesc('total')
            ->pluck('total', 'format')
            ->toArray();

        $recentJobs = RpJob::with(['user:id,name'])
            ->withCount('outputs')
            ->latest()
            ->limit(8)
            ->get();

        return Inertia::render('Addons/ai-repurposer/Admin/Overview', [
            'stats' => [
                'total_jobs' => [
                    'value' => $totalJobsCurrent,
                    'comparison' => $this->calculateComparison($totalJobsCurrent, $totalJobsPrevious),
                ],
                'queued_jobs' => [
                    'value' => $queuedCurrent,
                    'comparison' => $this->calculateComparison($queuedCurrent, $queuedPrevious),
                ],
                'processing_jobs' => [
                    'value' => $processingCurrent,
                    'comparison' => $this->calculateComparison($processingCurrent, $processingPrevious),
                ],
                'completed_jobs' => [
                    'value' => $completedCurrent,
                    'comparison' => $this->calculateComparison($completedCurrent, $completedPrevious),
                ],
                'failed_jobs' => [
                    'value' => $failedCurrent,
                    'comparison' => $this->calculateComparison($failedCurrent, $failedPrevious),
                ],
                'completed_today' => [
                    'value' => $completedTodayCurrent,
                    'comparison' => $this->calculateComparison($completedTodayCurrent, $completedTodayPrevious),
                ],
                'total_outputs' => [
                    'value' => $totalOutputsCurrent,
                    'comparison' => $this->calculateComparison($totalOutputsCurrent, $totalOutputsPrevious),
                ],
                'saved_outputs' => [
                    'value' => $savedOutputsCurrent,
                    'comparison' => $this->calculateComparison($savedOutputsCurrent, $savedOutputsPrevious),
                ],
                'total_credits' => [
                    'value' => $totalCreditsCurrent,
                    'comparison' => $this->calculateComparison($totalCreditsCurrent, $totalCreditsPrevious),
                ],
                'total_words' => [
                    'value' => $totalWordsCurrent,
                    'comparison' => $this->calculateComparison($totalWordsCurrent, $totalWordsPrevious),
                ],
                'by_format' => $byFormat,
            ],
            'recentJobs' => $recentJobs,
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
