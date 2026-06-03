<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\Document;
use App\Models\User;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use App\Services\AI\ToolAccessService;
use App\Services\NotificationEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GenerateController — handles all AI text generation requests.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.5
 *
 * Two endpoints:
 *   POST /api/v1/generate/stream → SSE streaming
 *   POST /api/v1/generate/text   → sync JSON
 */
class GenerateController extends Controller
{
    public function __construct(
        private PromptBuilder $promptBuilder,
        private ToolAccessService $toolAccess,
    ) {}

    /**
     * POST /api/v1/generate/stream — SSE streaming generation.
     */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100',
            'fields' => 'required|array',
            'model' => 'nullable|string|max:100',
        ]);

        $user = $this->currentUser();
        $template = AiTool::where('slug', $validated['slug'])->where('is_active', true)->firstOrFail();

        // Access control check
        $this->checkToolAccess($template, $user);

        // Build prompt
        $fields = $validated['fields'];
        if (! empty($validated['model'])) {
            $fields['model'] = $validated['model'];
        }
        $completion = $this->promptBuilder->build($template, $fields, $user);

        // Resolve provider
        $provider = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($completion->provider ?? 'openai', $completion->apiKey)
            : ProviderRegistry::resolve($completion->provider ?? 'openai');

        $isGuestPublic = ! $user && $template->getEffectiveAccessLevel() === 'public';
        $publicMaxChars = (int) settings('public_tool_max_output_chars', 1200);

        return response()->stream(function () use ($completion, $provider, $user, $template, $isGuestPublic, $publicMaxChars) {
            // Start streaming
            $usageStats = null;
            $sentChars = 0;
            $truncated = false;
            $content = '';

            try {
                $stream = $provider->streamChatCompletion(
                    $completion->toMessages(),
                    $completion->model,
                    $completion->toOptions()
                );

                foreach ($stream as $chunk) {
                    if (is_string($chunk)) {
                        if ($isGuestPublic && $publicMaxChars > 0) {
                            $remaining = max(0, $publicMaxChars - $sentChars);
                            if ($remaining <= 0) {
                                $truncated = true;

                                continue;
                            }

                            $chunk = mb_substr($chunk, 0, $remaining);
                            $sentChars += mb_strlen($chunk);
                            $truncated = $sentChars >= $publicMaxChars;
                        }

                        if ($chunk !== '') {
                            $content .= $chunk;
                            echo 'data: '.json_encode(['token' => $chunk])."\n\n";
                        }
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    } elseif (is_array($chunk)) {
                        // Final chunk with usage stats
                        $usageStats = $chunk;
                    }
                }

                // Record usage after stream completes
                if ($usageStats) {
                    $creditsUsed = TokenGuard::after(
                        $user,
                        $usageStats['input_tokens'],
                        $usageStats['output_tokens'],
                        $usageStats['model'] ?? $completion->model,
                        $completion->provider ?? 'openai',
                        'template',
                        ['template_slug' => $template->slug, 'personal_api_key' => (bool) $completion->apiKey],
                        ! $completion->apiKey && ! $isGuestPublic
                    );

                    // Send usage info as final SSE event
                    echo 'data: '.json_encode([
                        'usage' => [
                            'input_tokens' => $usageStats['input_tokens'],
                            'output_tokens' => $usageStats['output_tokens'],
                            'credits_used' => $creditsUsed,
                        ],
                    ])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Increment template usage counter
                $template->incrementUsage();

                if ($truncated) {
                    $upsell = "\n\n---\nPublic preview limited. Sign in to generate the full output.";
                    $content .= $upsell;

                    echo 'data: '.json_encode([
                        'token' => $upsell,
                        'truncated' => true,
                    ])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                $document = (! $isGuestPublic && $user && $content !== '')
                    ? $this->saveGeneratedDocument($template, $user, $content)
                    : null;

                if ($document) {
                    echo 'data: '.json_encode([
                        'document' => [
                            'id' => $document->id,
                            'title' => $document->title,
                        ],
                    ])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

            } catch (\Throwable $e) {
                // Record failed attempt
                TokenGuard::recordFailure(
                    $user,
                    $completion->provider ?? 'openai',
                    $completion->model,
                    'template',
                    0, 0,
                    ['template_slug' => $template->slug, 'error' => $e->getMessage()]
                );

                echo 'data: '.json_encode(['error' => translate('Generation failed. Please try again or contact support if it continues.')])."\n\n";
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
            'X-Accel-Buffering' => 'no', // Critical for Nginx
        ]);
    }

    /**
     * POST /api/v1/generate/text — synchronous JSON response.
     */
    public function text(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100',
            'fields' => 'required|array',
            'model' => 'nullable|string|max:100',
        ]);

        $user = $this->currentUser();
        $template = AiTool::where('slug', $validated['slug'])->where('is_active', true)->firstOrFail();

        // Access control check
        $this->checkToolAccess($template, $user);

        // Build prompt
        $fields = $validated['fields'];
        if (! empty($validated['model'])) {
            $fields['model'] = $validated['model'];
        }
        $completion = $this->promptBuilder->build($template, $fields, $user);

        $isGuestPublic = ! $user && $template->getEffectiveAccessLevel() === 'public';
        $publicMaxChars = (int) settings('public_tool_max_output_chars', 1200);

        // Resolve provider
        $provider = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($completion->provider ?? 'openai', $completion->apiKey)
            : ProviderRegistry::resolve($completion->provider ?? 'openai');

        try {
            $result = $provider->chatCompletion(
                $completion->toMessages(),
                $completion->model,
                $completion->toOptions()
            );

            // Record usage
            $creditsUsed = TokenGuard::after(
                $user,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $completion->provider ?? 'openai',
                'template',
                ['template_slug' => $template->slug, 'personal_api_key' => (bool) $completion->apiKey],
                ! $completion->apiKey && ! $isGuestPublic
            );

            $template->incrementUsage();
            $content = $result['content'];

            if ($isGuestPublic && $publicMaxChars > 0 && mb_strlen($content) > $publicMaxChars) {
                $content = mb_substr($content, 0, $publicMaxChars)
                    ."\n\n---\nPublic preview limited. Sign in to generate the full output.";
            }

            $document = (! $isGuestPublic && $user)
                ? $this->saveGeneratedDocument($template, $user, $content)
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'output_type' => $template->output_type ?? 'markdown',
                    'document' => $document ? [
                        'id' => $document->id,
                        'title' => $document->title,
                    ] : null,
                    'usage' => [
                        'input_tokens' => $result['input_tokens'],
                        'output_tokens' => $result['output_tokens'],
                        'credits_used' => $creditsUsed,
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            TokenGuard::recordFailure(
                $user,
                $completion->provider ?? 'openai',
                $completion->model,
                'template',
                0, 0,
                ['template_slug' => $template->slug, 'error' => $e->getMessage()]
            );

            return response()->json([
                'success' => false,
                'message' => translate('Generation failed. Please try again or contact support if it continues.'),
            ], 422);
        }
    }

    /**
     * GET /api/v1/generate/estimate — get credit cost estimate.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100',
            'model' => 'nullable|string|max:100',
        ]);

        $template = AiTool::where('slug', $validated['slug'])->where('is_active', true)->firstOrFail();
        $model = $validated['model'] ?? $template->model_override ?? settings('default_ai_model', 'gpt-4o-mini');

        $estimate = $this->promptBuilder->estimateCost($template, $model);

        $user = $this->currentUser();

        return response()->json([
            'success' => true,
            'data' => array_merge($estimate, [
                'user_balance' => $user ? (float) $user->credits : 0.0,
            ]),
        ]);
    }

    /**
     * Check tool access level (P13 — Part 35).
     */
    private function checkToolAccess(AiTool $template, $user): void
    {
        $this->toolAccess->assertCanUse($template, $user);
    }

    private function saveGeneratedDocument(AiTool $template, User $user, string $content): Document
    {
        $document = Document::create([
            'user_id' => $user->id,
            'title' => $template->name.' Output',
            'content' => $content,
            'tool_slug' => $template->slug,
            'word_count' => str_word_count(strip_tags($content)),
        ]);

        app(NotificationEventService::class)->documentReady($document);

        return $document;
    }

    private function currentUser(): ?User
    {
        if (array_key_exists('sanctum', config('auth.guards', []))) {
            return Auth::guard('sanctum')->user() ?? Auth::user();
        }

        return Auth::user();
    }
}
