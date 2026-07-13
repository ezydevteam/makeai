<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\ToolEmbed;
use App\Models\Language;
use App\Models\AiModel;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\ToolAccessService;
use App\Services\AI\TokenGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmbedController extends Controller
{
    private function corsHeaders(Request $request): array
    {
        $origin = $request->headers->get('Origin');

        return [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];
    }

    public function show(string $token): Response
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

        $tool = AiTool::where('slug', $embed->tool_slug)->where('is_active', true)->firstOrFail();

        if (! $tool->is_embeddable) {
            abort(403, 'This tool is not embeddable.');
        }

        $origin = request()->header('Origin');
        if ($origin && ! empty($embed->allowed_origins)) {
            $allowed = false;
            foreach ($embed->allowed_origins as $allowedOrigin) {
                $allowedOrigin = trim($allowedOrigin);
                if ($origin === $allowedOrigin || str_ends_with($origin, '.'.$allowedOrigin)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) abort(403, 'Origin not allowed.');
        }

        $requiresPassword = ! blank($embed->password_hash) && ! session()->has("embed_unlocked_{$token}");

        Redis::incr("embed_usage:{$token}");

        $languages = Language::where('is_active', true)->get(['code', 'name'])->toArray();
        $models = AiModel::active()->ofType('chat')->orderBy('provider')->orderBy('name')->get(['slug', 'name', 'provider'])->toArray();

        return response()->view('embed.tool', [
            'embed' => $embed,
            'tool' => $tool,
            'requiresPassword' => $requiresPassword,
            'appName' => settings('app_name', 'MakeAI'),
            'languages' => $languages,
            'models' => $models,
        ])->header('X-Frame-Options', 'ALLOWALL');
    }

    public function unlockOptions(Request $request, string $token): Response
    {
        return response('', 204)->withHeaders($this->corsHeaders($request));
    }

    public function unlock(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->password, $embed->password_hash)) {
            abort(403, 'Incorrect password.');
        }

        session(["embed_unlocked_{$token}" => true]);

        return response()->json(['unlocked' => true])->withHeaders($this->corsHeaders($request));
    }

    public function runOptions(Request $request, string $token): Response
    {
        return response('', 204)->withHeaders($this->corsHeaders($request));
    }

    public function run(Request $request, string $token): StreamedResponse
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();
        $tool = AiTool::where('slug', $embed->tool_slug)->firstOrFail();

        if (!$tool->is_embeddable) {
            abort(403, 'This tool is not embeddable.');
        }

        $validated = $request->validate([
            'fields' => 'required|array|max:50',
        ]);

        $fields = $validated['fields'];

        // Visitors may only pick models the admin has activated — anything
        // else would be billed to the embed owner at an unknown rate.
        if (isset($fields['model'])) {
            $modelValid = is_string($fields['model'])
                && AiModel::where('slug', $fields['model'])->active()->exists();

            if (! $modelValid) {
                unset($fields['model']);
            }
        }

        // Cap total input size before it inflates token costs
        $maxChars = max(1000, (int) settings('ai_max_input_chars', 30000));
        if (strlen((string) json_encode($fields)) > $maxChars) {
            abort(422, translate('Your input is too long. Please shorten it and try again.'));
        }

        $owner = $embed->user;

        // A public embed runs as (and is billed to) its owner. Gate on the owner's
        // access so a pro/plan-required tool can't be run for free by anonymous
        // visitors through an embed published by a user who lacks access to it.
        app(ToolAccessService::class)->assertCanUse($tool, $owner);

        $promptBuilder = app(PromptBuilder::class);
        $completion = $promptBuilder->build($tool, $fields, $owner);

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
            ...$this->corsHeaders($request),
        ]);
    }
}
