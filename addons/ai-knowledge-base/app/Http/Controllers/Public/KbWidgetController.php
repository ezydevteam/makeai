<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Public;

use Addons\AiKnowledgeBase\Services\KbSearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KbWidgetController extends Controller
{
    public function search(Request $request): StreamedResponse
    {
        if (! addon_setting('ai-knowledge-base', 'widget_enabled', false)) {
            abort(403);
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'widget_session' => ['nullable', 'string', 'max:64'],
        ]);

        $widgetSession = $validated['widget_session'] ?? uniqid();
        $sessionId = hash('sha256', $request->ip() . $widgetSession);

        return response()->stream(function () use ($validated, $sessionId) {
            try {
                $service = app(KbSearchService::class);

                foreach ($service->searchAndAnswer($validated['query'], null, $sessionId) as $chunk) {
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
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
