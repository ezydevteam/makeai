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
        $totalJobs = RpJob::count();
        $queuedJobs = RpJob::where('status', 'queued')->count();
        $processingJobs = RpJob::whereIn('status', ['transcribing', 'generating'])->count();
        $completedJobs = RpJob::whereIn('status', ['completed', 'partial'])->count();
        $failedJobs = RpJob::where('status', 'failed')->count();

        $completedToday = RpJob::whereIn('status', ['completed', 'partial'])
            ->whereDate('updated_at', today())
            ->count();

        $totalOutputs = RpOutput::count();
        $savedOutputs = RpOutput::where('is_saved', true)->count();
        $totalCredits = (float) RpJob::sum('credits_deducted');
        $totalWords = (int) RpJob::sum('word_count');

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
                'total_jobs' => $totalJobs,
                'queued_jobs' => $queuedJobs,
                'processing_jobs' => $processingJobs,
                'completed_jobs' => $completedJobs,
                'failed_jobs' => $failedJobs,
                'completed_today' => $completedToday,
                'total_outputs' => $totalOutputs,
                'saved_outputs' => $savedOutputs,
                'total_credits' => $totalCredits,
                'total_words' => $totalWords,
                'by_format' => $byFormat,
            ],
            'recentJobs' => $recentJobs,
        ]);
    }
}
