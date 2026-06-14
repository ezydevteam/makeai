<?php

namespace Addons\AiVideoCreator\Http\Controllers\Admin;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class VideoAdminController extends Controller
{
    public function overview(): \Inertia\Response
    {
        return inertia('Addons/ai-video-creator/Admin/Overview', [
            'total_renders' => VcRender::count(),
            'processing' => VcRender::processing()->count(),
            'completed_today' => VcRender::whereDate('completed_at', today())
                ->where('status', 'completed')->count(),
            'failed_today' => VcRender::whereDate('created_at', today())
                ->where('status', 'failed')->count(),
            'total_storage_gb' => round(VcRender::sum('file_size_bytes') / 1024 / 1024 / 1024, 2),
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
}
