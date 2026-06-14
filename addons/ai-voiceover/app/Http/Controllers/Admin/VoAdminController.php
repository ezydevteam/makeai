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
        $totalEpisodes = VoEpisode::count();
        $processing = VoEpisode::where('status', 'processing')->count();
        $completedToday = VoEpisode::where('status', 'completed')
            ->whereDate('updated_at', today())
            ->count();
        $failed = VoEpisode::where('status', 'failed')->count();

        $totalStorage = VoEpisode::sum('file_size_bytes');
        $creditsUsedToday = VoEpisode::whereDate('updated_at', today())->sum('credits_deducted');

        $byProvider = VoEpisode::whereNotNull('provider')
            ->select('provider', DB::raw('count(*) as count'))
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        return Inertia::render('Addons/ai-voiceover/Admin/Overview', [
            'stats' => [
                'total_episodes' => $totalEpisodes,
                'processing' => $processing,
                'completed_today' => $completedToday,
                'failed' => $failed,
                'total_storage' => $totalStorage,
                'credits_used_today' => $creditsUsedToday,
                'by_provider' => $byProvider,
            ],
        ]);
    }
}
