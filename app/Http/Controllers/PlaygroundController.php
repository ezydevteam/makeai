<?php

namespace App\Http\Controllers;

use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use App\Services\PlaygroundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlaygroundController extends Controller
{
    public function __construct(
        private readonly PlaygroundService $playground,
    ) {}

    public function index(): Response
    {
        $enabled = ProviderRegistry::getEnabledProviders();

        // Build provider → models map for the frontend
        $providers = [];
        foreach ($enabled as $provider => $models) {
            $providers[] = [
                'slug' => $provider,
                'name' => ucfirst($provider),
                'models' => array_map(fn ($m) => ['slug' => $m, 'name' => $m], array_values($models)),
            ];
        }

        return Inertia::render('User/Playground/Index', [
            'providers' => $providers,
            'defaultProvider' => settings('default_ai_provider', 'openai'),
            'defaultModel' => settings('default_ai_model', 'gpt-4o-mini'),
        ]);
    }

    public function run(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:50',
            'model' => 'required|string|max:100',
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'temperature' => 'numeric|min:0|max:2',
            'max_tokens' => 'integer|min:1|max:32000',
            'top_p' => 'numeric|min:0|max:1',
        ]);

        $user = Auth::user();

        // Estimate credits
        $estimatedInput = collect($validated['messages'])->sum(fn ($m) => mb_strlen($m['content']) / 4);
        TokenGuard::before($user, null, $validated['model']);

        $providerName = $validated['provider'];
        $modelName = $validated['model'];
        $temperature = $validated['temperature'] ?? 0.7;
        $maxTokens = $validated['max_tokens'] ?? 1000;
        $topP = $validated['top_p'] ?? 1.0;

        $provider = ProviderRegistry::resolve($providerName);

        return response()->stream(function () use ($provider, $modelName, $validated, $temperature, $maxTokens, $topP, $user, $providerName) {
            $inputTokens = 0;
            $outputTokens = 0;
            $content = '';

            try {
                $stream = $provider->streamChatCompletion(
                    $validated['messages'],
                    $modelName,
                    ['temperature' => $temperature, 'max_tokens' => $maxTokens, 'top_p' => $topP],
                );

                foreach ($stream as $chunk) {
                    if (connection_aborted()) {
                        break;
                    }

                    if (is_string($chunk)) {
                        $content .= $chunk;
                        echo 'data: '.json_encode(['token' => $chunk])."\n\n";
                    } elseif (is_array($chunk) && isset($chunk['input_tokens'])) {
                        $inputTokens = $chunk['input_tokens'];
                        $outputTokens = $chunk['output_tokens'];
                        echo 'data: '.json_encode(['usage' => $chunk])."\n\n";
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if (! connection_aborted()) {
                    $credits = TokenGuard::after(
                        $user, $inputTokens, $outputTokens,
                        $modelName, $providerName, 'playground',
                        [], true, true
                    );

                    echo 'data: '.json_encode([
                        'done' => true,
                        'credits_used' => $credits,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                    ])."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                } else {
                    TokenGuard::after(
                        $user, $inputTokens, $outputTokens,
                        $modelName, $providerName, 'playground',
                        ['aborted' => true], false, false
                    );
                }
            } catch (\Throwable $e) {
                TokenGuard::recordFailure($user, $providerName, $modelName, 'playground', 0, 0, [
                    'error' => $e->getMessage(),
                ]);
                echo 'data: '.json_encode(['error' => 'Generation failed. Please try again.'])."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function share(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:5000',
            'params_left' => 'required|array',
            'params_right' => 'required|array',
            'output_left' => 'present|nullable|string|max:5000',
            'output_right' => 'present|nullable|string|max:5000',
        ]);

        $uuid = $this->playground->share($validated);

        return response()->json(['url' => '/playground/s/'.$uuid]);
    }

    public function showShare(string $uuid): Response
    {
        $snapshot = $this->playground->getShare($uuid);

        if (! $snapshot) {
            abort(404);
        }

        return Inertia::render('User/Playground/Share', ['snapshot' => $snapshot]);
    }
}
