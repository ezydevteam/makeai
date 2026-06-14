<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Public;

use Addons\PublicKnowledgeBase\Services\KbSearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KbSearchController extends Controller
{
    public function search(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $sessionId = $request->session()->getId();
        $userId = auth()->id();

        return response()->stream(function () use ($validated, $sessionId, $userId) {
            try {
                $service = app(KbSearchService::class);

                foreach ($service->searchAndAnswer($validated['query'], $userId, $sessionId) as $chunk) {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                echo json_encode(['type' => 'delta', 'text' => "\n[Search unavailable. Please try again.]"]) . "\n";
                echo json_encode(['type' => 'done', 'query_id' => null]) . "\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
