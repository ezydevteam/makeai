<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\ToolEmbed;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmbedController extends Controller
{
    public function show(string $token): Response
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

        $tool = AiTool::where('slug', $embed->tool_slug)->firstOrFail();

        if (! $tool->is_embeddable) {
            abort(403, 'This tool is not embeddable.');
        }

        $origin = request()->header('Origin');
        if ($origin && ! empty($embed->allowed_origins)) {
            $allowed = false;
            foreach ($embed->allowed_origins as $allowedOrigin) {
                if ($origin === $allowedOrigin || str_ends_with($origin, $allowedOrigin)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) abort(403, 'Origin not allowed.');
        }

        $requiresPassword = ! empty($embed->password_hash) && ! session("embed_unlocked_{$token}");

        Redis::incr("embed_usage:{$token}");

        return response()->view('embed.tool', [
            'embed' => $embed,
            'tool' => $tool,
            'requiresPassword' => $requiresPassword,
            'appName' => settings('app_name', 'MakeAI'),
        ])->header('X-Frame-Options', 'ALLOWALL');
    }

    public function unlock(Request $request, string $token): Response
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->password, $embed->password_hash)) {
            abort(403, 'Incorrect password.');
        }

        session(["embed_unlocked_{$token}" => true]);

        return response()->json(['unlocked' => true]);
    }

    public function run(Request $request, string $token): StreamedResponse
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();
        $tool = AiTool::where('slug', $embed->tool_slug)->firstOrFail();

        if (! $embed->is_active) abort(403);

        $validated = $request->validate([
            'fields' => 'required|array',
        ]);

        $owner = $embed->user;
        $promptBuilder = app(PromptBuilder::class);
        $completion = $promptBuilder->build($tool, $validated['fields'], $owner);

        $provider = ProviderRegistry::resolve($completion->provider ?? 'openai');
        TokenGuard::before($owner, $tool, $completion->model);

        return response()->stream(function () use ($provider, $completion, $owner) {
            $content = '';
            $usage = null;

            try {
                $stream = $provider->streamChatCompletion(
                    $completion->toMessages(),
                    $completion->model,
                    $completion->toOptions(),
                );

                foreach ($stream as $chunk) {
                    if (connection_aborted()) break;

                    if (is_string($chunk)) {
                        $content .= $chunk;
                        echo 'data: '.json_encode(['token' => $chunk])."\n\n";
                    } elseif (is_array($chunk)) {
                        $usage = $chunk;
                    }

                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }

                if ($usage) {
                    TokenGuard::after($owner, $usage['input_tokens'], $usage['output_tokens'], $usage['model'], $completion->provider ?? 'openai', 'embed');
                }
            } catch (\Throwable $e) {
                echo 'data: '.json_encode(['error' => 'Generation failed'])."\n\n";
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
