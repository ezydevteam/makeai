<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Services\AiCaptionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CaptionController extends Controller
{
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'in:instagram,facebook,twitter,linkedin'],
            'tone' => ['required', 'in:professional,casual,funny,inspirational,educational'],
            'context' => ['nullable', 'string', 'max:300'],
        ]);

        return response()->stream(function () use ($validated) {
            try {
                foreach (app(AiCaptionService::class)->streamCaption(
                    $validated['topic'],
                    $validated['platform'],
                    $validated['tone'],
                    $validated['context'] ?? null,
                    auth()->user(),
                ) as $token) {
                    echo $token;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                // cleanup if needed
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
