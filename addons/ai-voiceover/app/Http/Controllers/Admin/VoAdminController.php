<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Controllers\Admin;

use Addons\AiVoiceover\Models\VoEpisode;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VoAdminController extends \App\Http\Controllers\Controller
{
    public function overview(): Response
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // 1. Total Episodes
        $episodesCurrent = VoEpisode::count();
        $episodesPrevious = VoEpisode::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Processing
        $processingCurrent = VoEpisode::where('status', 'processing')->count();
        $processingPrevious = VoEpisode::where('status', 'processing')->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Completed Today
        $completedTodayCurrent = VoEpisode::where('status', 'completed')
            ->whereDate('updated_at', today())
            ->count();
        $completedTodayPrevious = VoEpisode::where('status', 'completed')
            ->whereDate('updated_at', today()->subDays(7))
            ->count();

        // 4. Failed
        $failedCurrent = VoEpisode::where('status', 'failed')->count();
        $failedPrevious = VoEpisode::where('status', 'failed')->where('created_at', '<', $sevenDaysAgo)->count();

        // 5. Total Storage
        $storageCurrent = (float) VoEpisode::sum('file_size_bytes');
        $storagePrevious = (float) VoEpisode::where('created_at', '<', $sevenDaysAgo)->sum('file_size_bytes');

        // 6. Credits Used Today
        $creditsCurrent = (float) VoEpisode::whereDate('updated_at', today())->sum('credits_deducted');
        $creditsPrevious = (float) VoEpisode::whereDate('updated_at', today()->subDays(7))->sum('credits_deducted');

        $byProvider = VoEpisode::whereNotNull('provider')
            ->select('provider', DB::raw('count(*) as count'))
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        return Inertia::render('Addons/ai-voiceover/Admin/Overview', [
            'stats' => [
                'total_episodes' => [
                    'value' => $episodesCurrent,
                    'comparison' => $this->calculateComparison($episodesCurrent, $episodesPrevious),
                ],
                'processing' => [
                    'value' => $processingCurrent,
                    'comparison' => $this->calculateComparison($processingCurrent, $processingPrevious),
                ],
                'completed_today' => [
                    'value' => $completedTodayCurrent,
                    'comparison' => $this->calculateComparison($completedTodayCurrent, $completedTodayPrevious),
                ],
                'failed' => [
                    'value' => $failedCurrent,
                    'comparison' => $this->calculateComparison($failedCurrent, $failedPrevious),
                ],
                'total_storage' => [
                    'value' => $storageCurrent,
                    'comparison' => $this->calculateComparison($storageCurrent, $storagePrevious),
                ],
                'credits_used_today' => [
                    'value' => $creditsCurrent,
                    'comparison' => $this->calculateComparison($creditsCurrent, $creditsPrevious),
                ],
                'by_provider' => $byProvider,
            ],
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
