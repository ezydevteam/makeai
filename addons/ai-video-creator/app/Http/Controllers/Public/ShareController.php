<?php

namespace Addons\AiVideoCreator\Http\Controllers\Public;

use Addons\AiVideoCreator\Models\VcRender;
use Illuminate\Routing\Controller;

class ShareController extends Controller
{
    public function show(string $token): \Inertia\Response
    {
        $render = VcRender::where('share_token', $token)
            ->where('share_enabled', true)
            ->where('status', 'completed')
            ->firstOrFail();

        return inertia('Addons/ai-video-creator/Public/Share', [
            'render' => [
                'title' => $render->title,
                'file_url' => $render->file_url,
                'thumbnail_url' => $render->thumbnail_url,
                'duration_actual' => $render->duration_actual,
            ],
        ]);
    }
}
