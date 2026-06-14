<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Services\BestTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BestTimeController extends Controller
{
    public function suggest(Request $request, BestTimeService $service): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:instagram,facebook,twitter,linkedin'],
            'content_type' => ['required', 'in:single,carousel,thread,story,reel'],
        ]);

        $result = $service->suggestBestTime(
            auth()->user(),
            $validated['platform'],
            $validated['content_type'],
        );

        return response()->json([
            'suggested_time' => $result['suggested_time']->toISOString(),
            'reasoning' => $result['reasoning'],
            'alternatives' => array_map(fn ($t) => $t->toISOString(), $result['alternatives']),
        ]);
    }
}
