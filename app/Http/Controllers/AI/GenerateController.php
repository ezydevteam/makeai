<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\RecordGenerationHistoryJob;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $refineContent = $request->input('refine_content') ?? data_get($fields, 'refine_content');
        $refineInstruction = $request->input('refine_instruction') ?? data_get($fields, 'refine_instruction');
        $isRefine = ($request->input('action') === 'refine') || (! empty($refineContent) && ! empty($refineInstruction));

        if ($isRefine && ! $template->show_improve) {
            abort(403, translate('Improve feature is disabled for this tool.'));
        }

        $variationIndex = data_get($fields, 'variation_index');
        if ($variationIndex !== null && (int) $variationIndex > 0 && $template->max_variants <= 1) {
            abort(403, translate('Variations are disabled for this tool.'));
        }

        if ($isRefine) {
            if (empty($refineContent) || empty($refineInstruction)) {
                abort(422, translate('Content and refinement instructions are required.'));
            }
            $model = $validated['model']
                ?? $template->model_override
                ?? settings('default_ai_model', 'gpt-4o-mini');
            $completion = $this->promptBuilder->buildRefine($template, $refineContent, $refineInstruction, $model, $user);
        } else {
            $completion = $this->promptBuilder->build($template, $fields, $user);
        }

        // Resolve provider
        $provider = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($completion->provider ?? 'openai', $completion->apiKey)
            : ProviderRegistry::resolve($completion->provider ?? 'openai');

        $isGuestPublic = ! $user && $template->getEffectiveAccessLevel() === 'public';
        $publicMaxChars = (int) settings('public_tool_max_output_chars', 1200);

        return response()->stream(function () use ($completion, $provider, $user, $template, $fields, $isGuestPublic, $publicMaxChars) {
            // Start streaming
            $usageStats = null;
            $sentChars = 0;
            $truncated = false;
            $content = '';
            $completed = false;

            try {
                $stream = $provider->streamChatCompletion(
                    $completion->toMessages(),
                    $completion->model,
                    $completion->toOptions()
                );

                foreach ($stream as $chunk) {
                    if (connection_aborted()) {
                        break;
                    }

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
                        if (isset($chunk['reasoning_start'])) {
                            echo 'data: '.json_encode(['reasoning_start' => true])."\n\n";
                        } elseif (isset($chunk['reasoning_end'])) {
                            echo 'data: '.json_encode(['reasoning_end' => true])."\n\n";
                        } elseif (isset($chunk['reasoning'])) {
                            echo 'data: '.json_encode(['reasoning' => $chunk['reasoning']])."\n\n";
                        } else {
                            // Final chunk with usage stats
                            $usageStats = $chunk;
                        }
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                }

                $completed = ! connection_aborted();

                // Record usage after stream completes
                if ($usageStats && $completed) {
                    $creditsUsed = TokenGuard::after(
                        $user,
                        $usageStats['input_tokens'],
                        $usageStats['output_tokens'],
                        $usageStats['model'] ?? $completion->model,
                        $completion->provider ?? 'openai',
                        'template',
                        ['template_slug' => $template->slug, 'personal_api_key' => (bool) $completion->apiKey],
                        ! $completion->apiKey && ! $isGuestPublic,
                        true
                    );

                    // Send usage info as final SSE event
                    echo 'data: '.json_encode([
                        'usage' => [
                            'input_tokens' => $usageStats['input_tokens'],
                            'output_tokens' => $usageStats['output_tokens'],
                            'reasoning_tokens' => $usageStats['reasoning_tokens'] ?? 0,
                            'credits_used' => $creditsUsed,
                        ],
                    ])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if (! $completed && $usageStats) {
                    TokenGuard::after(
                        $user,
                        $usageStats['input_tokens'] ?? 0,
                        $usageStats['output_tokens'] ?? 0,
                        $usageStats['model'] ?? $completion->model,
                        $completion->provider ?? 'openai',
                        'template',
                        ['template_slug' => $template->slug, 'aborted' => true],
                        false,
                        false
                    );
                }

                // Increment template usage counter
                if ($completed) {
                    $template->incrementUsage();
                }

                if ($completed && $truncated) {
                    $upsell = "\n\n---\n".translate('Public preview limited. Sign in to generate the full output.');
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

                $document = ($completed && ! $isGuestPublic && $user && $content !== '')
                    ? $this->saveGeneratedDocument($template, $user, $content)
                    : null;

                if ($document && $user) {
                    RecordGenerationHistoryJob::dispatch($user, [
                        'tool_slug' => $template->slug,
                        'document_id' => $document->id,
                        'prompt_system' => $completion->systemPrompt,
                        'prompt_user' => $completion->userMessage,
                        'field_values' => $fields,
                        'model' => $completion->model,
                        'provider' => $completion->provider ?? 'openai',
                        'temperature' => $completion->temperature,
                        'max_tokens' => $completion->maxTokens,
                        'output_preview' => Str::limit($content, 500),
                        'tokens_input' => $usageStats['input_tokens'] ?? 0,
                        'tokens_output' => $usageStats['output_tokens'] ?? 0,
                    ])->onQueue('ai');
                }

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
                // Check if we can failover to the fallback provider
                $fallback = $this->resolveFallback($completion->provider ?? 'unknown');

                if ($fallback) {
                    Log::warning('AI generation failed, attempting fallback', [
                        'primary_provider' => $completion->provider,
                        'primary_model' => $completion->model,
                        'fallback_provider' => $fallback['provider'],
                        'fallback_model' => $fallback['model'],
                        'error' => $e->getMessage(),
                    ]);

                    // Notify the client about the fallback
                    echo 'data: '.json_encode(['info' => translate('Primary provider unavailable, switching to fallback...')])."\n\n";
                    if (ob_get_level() > 0) { ob_flush(); }
                    flush();

                    try {
                        $fbProvider = ProviderRegistry::resolve($fallback['provider']);
                        $fbStream = $fbProvider->streamChatCompletion(
                            $completion->toMessages(),
                            $fallback['model'],
                            $completion->toOptions()
                        );

                        foreach ($fbStream as $chunk) {
                            if (connection_aborted()) { break; }

                            if (is_string($chunk)) {
                                if ($isGuestPublic && $publicMaxChars > 0) {
                                    $remaining = max(0, $publicMaxChars - $sentChars);
                                    if ($remaining <= 0) { $truncated = true; continue; }
                                    $chunk = mb_substr($chunk, 0, $remaining);
                                    $sentChars += mb_strlen($chunk);
                                    $truncated = $sentChars >= $publicMaxChars;
                                }

                                if ($chunk !== '') {
                                    $content .= $chunk;
                                    echo 'data: '.json_encode(['token' => $chunk])."\n\n";
                                }
                                if (ob_get_level() > 0) { ob_flush(); }
                                flush();
                            } elseif (is_array($chunk)) {
                                if (isset($chunk['reasoning_start'])) {
                                    echo 'data: '.json_encode(['reasoning_start' => true])."\n\n";
                                } elseif (isset($chunk['reasoning_end'])) {
                                    echo 'data: '.json_encode(['reasoning_end' => true])."\n\n";
                                } elseif (isset($chunk['reasoning'])) {
                                    echo 'data: '.json_encode(['reasoning' => $chunk['reasoning']])."\n\n";
                                } else {
                                    $usageStats = $chunk;
                                }
                                if (ob_get_level() > 0) { ob_flush(); }
                                flush();
                            }
                        }

                        $completed = ! connection_aborted();

                        // Record usage with fallback provider info
                        if ($usageStats && $completed) {
                            $creditsUsed = TokenGuard::after(
                                $user,
                                $usageStats['input_tokens'],
                                $usageStats['output_tokens'],
                                $usageStats['model'] ?? $fallback['model'],
                                $fallback['provider'],
                                'template',
                                ['template_slug' => $template->slug, 'fallback' => true],
                                ! $completion->apiKey && ! $isGuestPublic,
                                true
                            );

                            echo 'data: '.json_encode([
                                'usage' => [
                                    'input_tokens' => $usageStats['input_tokens'],
                                    'output_tokens' => $usageStats['output_tokens'],
                                    'reasoning_tokens' => $usageStats['reasoning_tokens'] ?? 0,
                                    'credits_used' => $creditsUsed,
                                ],
                            ])."\n\n";
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                        }

                        if ($completed) { $template->incrementUsage(); }

                        if ($completed && $truncated) {
                            $upsell = "\n\n---\n".translate('Public preview limited. Sign in to generate the full output.');
                            $content .= $upsell;
                            echo 'data: '.json_encode(['token' => $upsell, 'truncated' => true])."\n\n";
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                        }

                        $document = ($completed && ! $isGuestPublic && $user && $content !== '')
                            ? $this->saveGeneratedDocument($template, $user, $content)
                            : null;

                        if ($document && $user) {
                            RecordGenerationHistoryJob::dispatch($user, [
                                'tool_slug' => $template->slug,
                                'document_id' => $document->id,
                                'prompt_system' => $completion->systemPrompt,
                                'prompt_user' => $completion->userMessage,
                                'field_values' => $fields,
                                'model' => $fallback['model'],
                                'provider' => $fallback['provider'],
                                'temperature' => $completion->temperature,
                                'max_tokens' => $completion->maxTokens,
                                'output_preview' => Str::limit($content, 500),
                                'tokens_input' => $usageStats['input_tokens'] ?? 0,
                                'tokens_output' => $usageStats['output_tokens'] ?? 0,
                            ])->onQueue('ai');
                        }

                        if ($document) {
                            echo 'data: '.json_encode([
                                'document' => ['id' => $document->id, 'title' => $document->title],
                            ])."\n\n";
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                        }

                    } catch (\Throwable $fbError) {
                        Log::error('AI fallback also failed', [
                            'fallback_provider' => $fallback['provider'],
                            'fallback_model' => $fallback['model'],
                            'error' => $fbError->getMessage(),
                        ]);

                        TokenGuard::recordFailure(
                            $user,
                            $fallback['provider'],
                            $fallback['model'],
                            'template',
                            0, 0,
                            ['template_slug' => $template->slug, 'error' => $fbError->getMessage(), 'fallback' => true]
                        );

                        echo 'data: '.json_encode(['error' => translate('Generation failed. Please try again or contact support if it continues.')])."\n\n";
                        if (ob_get_level() > 0) { ob_flush(); }
                        flush();
                    }
                } else {
                    // No fallback configured or non-failoverable error
                    TokenGuard::recordFailure(
                        $user,
                        $completion->provider ?? 'openai',
                        $completion->model,
                        'template',
                        0, 0,
                        ['template_slug' => $template->slug, 'error' => $e->getMessage()]
                    );

                    echo 'data: '.json_encode(['error' => translate('Generation failed. Please try again or contact support if it continues.')])."\n\n";
                    if (ob_get_level() > 0) { ob_flush(); }
                    flush();
                }
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

        $refineContent = $request->input('refine_content') ?? data_get($fields, 'refine_content');
        $refineInstruction = $request->input('refine_instruction') ?? data_get($fields, 'refine_instruction');
        $isRefine = ($request->input('action') === 'refine') || (! empty($refineContent) && ! empty($refineInstruction));

        if ($isRefine && ! $template->show_improve) {
            abort(403, translate('Improve feature is disabled for this tool.'));
        }

        $variationIndex = data_get($fields, 'variation_index');
        if ($variationIndex !== null && (int) $variationIndex > 0 && $template->max_variants <= 1) {
            abort(403, translate('Variations are disabled for this tool.'));
        }

        if ($isRefine) {
            if (empty($refineContent) || empty($refineInstruction)) {
                abort(422, translate('Content and refinement instructions are required.'));
            }
            $model = $validated['model']
                ?? $template->model_override
                ?? settings('default_ai_model', 'gpt-4o-mini');
            $completion = $this->promptBuilder->buildRefine($template, $refineContent, $refineInstruction, $model, $user);
        } else {
            $completion = $this->promptBuilder->build($template, $fields, $user);
        }

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
                    ."\n\n---\n".translate('Public preview limited. Sign in to generate the full output.');
            }

            $document = (! $isGuestPublic && $user)
                ? $this->saveGeneratedDocument($template, $user, $content)
                : null;

            if ($document && $user) {
                RecordGenerationHistoryJob::dispatch($user, [
                    'tool_slug' => $template->slug,
                    'document_id' => $document->id,
                    'prompt_system' => $completion->systemPrompt,
                    'prompt_user' => $completion->userMessage,
                    'field_values' => $validated['fields'],
                    'model' => $completion->model,
                    'provider' => $completion->provider ?? 'openai',
                    'temperature' => $completion->temperature,
                    'max_tokens' => $completion->maxTokens,
                    'output_preview' => Str::limit($content, 500),
                    'tokens_input' => $result['input_tokens'],
                    'tokens_output' => $result['output_tokens'],
                ])->onQueue('ai');
            }

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
                        'reasoning_tokens' => $result['reasoning_tokens'] ?? 0,
                        'credits_used' => $creditsUsed,
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            // Check if we can failover to the fallback provider
            $fallback = $this->resolveFallback($completion->provider ?? 'unknown');

            if ($e instanceof FailoverableException && $fallback) {
                Log::warning('AI text generation failed, attempting fallback', [
                    'primary_provider' => $completion->provider,
                    'fallback_provider' => $fallback['provider'],
                    'fallback_model' => $fallback['model'],
                    'error' => $e->getMessage(),
                ]);

                try {
                    $fbProvider = ProviderRegistry::resolve($fallback['provider']);
                    $result = $fbProvider->chatCompletion(
                        $completion->toMessages(),
                        $fallback['model'],
                        $completion->toOptions()
                    );

                    $creditsUsed = TokenGuard::after(
                        $user,
                        $result['input_tokens'],
                        $result['output_tokens'],
                        $result['model'],
                        $fallback['provider'],
                        'template',
                        ['template_slug' => $template->slug, 'personal_api_key' => (bool) $completion->apiKey, 'fallback' => true],
                        ! $completion->apiKey && ! $isGuestPublic
                    );

                    $template->incrementUsage();
                    $content = $result['content'];

                    if ($isGuestPublic && $publicMaxChars > 0 && mb_strlen($content) > $publicMaxChars) {
                        $content = mb_substr($content, 0, $publicMaxChars)
                            ."\n\n---\n".translate('Public preview limited. Sign in to generate the full output.');
                    }

                    $document = (! $isGuestPublic && $user)
                        ? $this->saveGeneratedDocument($template, $user, $content)
                        : null;

                    if ($document && $user) {
                        RecordGenerationHistoryJob::dispatch($user, [
                            'tool_slug' => $template->slug,
                            'document_id' => $document->id,
                            'prompt_system' => $completion->systemPrompt,
                            'prompt_user' => $completion->userMessage,
                            'field_values' => $validated['fields'],
                            'model' => $fallback['model'],
                            'provider' => $fallback['provider'],
                            'temperature' => $completion->temperature,
                            'max_tokens' => $completion->maxTokens,
                            'output_preview' => Str::limit($content, 500),
                            'tokens_input' => $result['input_tokens'],
                            'tokens_output' => $result['output_tokens'],
                        ])->onQueue('ai');
                    }

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
                                'reasoning_tokens' => $result['reasoning_tokens'] ?? 0,
                                'credits_used' => $creditsUsed,
                            ],
                        ],
                    ]);

                } catch (\Throwable $fbError) {
                    Log::error('AI text fallback also failed', [
                        'fallback_provider' => $fallback['provider'],
                        'error' => $fbError->getMessage(),
                    ]);

                    TokenGuard::recordFailure(
                        $user,
                        $fallback['provider'],
                        $fallback['model'],
                        'template',
                        0, 0,
                        ['template_slug' => $template->slug, 'error' => $fbError->getMessage(), 'fallback' => true]
                    );
                }
            } else {
                TokenGuard::recordFailure(
                    $user,
                    $completion->provider ?? 'openai',
                    $completion->model,
                    'template',
                    0, 0,
                    ['template_slug' => $template->slug, 'error' => $e->getMessage()]
                );
            }

            return response()->json([
                'success' => false,
                'message' => translate('Generation failed. Please try again or contact support if it continues.'),
            ], 422);
        }
    }

    /**
     * GET /api/v1/generate/estimate — get credit cost estimate with optional output length.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100',
            'model' => 'nullable|string|max:100',
            'output_length' => 'nullable|string|in:short,medium,long,very_long',
        ]);

        $template = AiTool::where('slug', $validated['slug'])->where('is_active', true)->firstOrFail();
        $model = $validated['model'] ?? $template->model_override ?? settings('default_ai_model', 'gpt-4o-mini');

        $estimate = $this->promptBuilder->estimateCost(
            $template,
            $model,
            $validated['output_length'] ?? null,
        );

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
            'word_count' => Document::calculateWordCount($content),
        ]);

        app(NotificationEventService::class)->documentReady($document);

        return $document;
    }

    private function currentUser(): ?User
    {
        // CheckCredits middleware may have already resolved and set the user via Auth::setUser()
        $user = Auth::guard('web')->user();
        if ($user) {
            return $user;
        }

        // Fallback: try sanctum if configured
        if (array_key_exists('sanctum', config('auth.guards', []))) {
            return Auth::guard('sanctum')->user();
        }

        return null;
    }

    /**
     * Resolve fallback provider/model from admin settings.
     *
     * Returns null if no fallback is configured or if the fallback
     * matches the primary provider (to avoid infinite loops).
     *
     * @return array{provider: string, model: string}|null
     */
    private function resolveFallback(string $primaryProvider): ?array
    {
        $fbProvider = settings('fallback_ai_provider', '');
        $fbModel = settings('fallback_ai_model', '');

        if (empty($fbProvider) || empty($fbModel)) {
            return null;
        }

        // Don't fallback to the same provider
        if ($fbProvider === $primaryProvider) {
            return null;
        }

        return [
            'provider' => $fbProvider,
            'model' => $fbModel,
        ];
    }

    /**
     * Sanitize raw provider error messages before sending to the client.
     *
     * @param  string  $message  Raw exception message from provider SDK
     */
    private function sanitizeError(string $message): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/rate.?limit|too many requests|quota exceeded|insufficient_?quota|credits? exhausted|429/i', $lower)) {
            return (string) translate('Rate limit reached. Please try again in a moment.');
        }
        if (preg_match('/content.?policy|content.?filter|safety filter|flagged by|inappropriate|violates.*policy/i', $lower)) {
            return (string) translate('Your request was flagged. Please modify your input and try again.');
        }
        if (preg_match('/context.?length|token.?limit|max.?tokens|too long|exceed.*token|exceed.*context/i', $lower)) {
            return (string) translate('Your input is too long. Please shorten it and try again.');
        }
        if (preg_match('/timeout|timed.?out/i', $lower)) {
            return (string) translate('Generation timed out. Try a shorter length or a different model.');
        }
        if (preg_match('/api.?key|invalid.?key|authentication|unauthorized|401|not.?authorized/i', $lower)) {
            return (string) translate('This AI provider is not configured. Please contact support.');
        }
        if (preg_match('/model.?not.?found|model.?unavailable|invalid.?model|unsupported.?model|no.?such.?model|deprecated/i', $lower)) {
            return (string) translate('The selected model is unavailable. Please try a different one.');
        }
        if (preg_match('/network.?error|connection.?refused|connection.?reset|econnrefused|econnreset|enotfound|etimedout/i', $lower)) {
            return (string) translate('Connection error. Please check your internet and try again.');
        }
        if (preg_match('/internal.?server.?error|bad.?gateway|gateway.?timeout|service.?unavailable|overloaded|500|502|503|504/i', $lower)) {
            return (string) translate('The AI service is temporarily unavailable. Please try again later.');
        }
        if (preg_match('/stream.?error|sse.?error/i', $lower)) {
            return (string) translate('Generation interrupted. Please try again.');
        }
        if (preg_match('/exception|stack.?trace/i', $lower)) {
            return (string) translate('Something went wrong. Please try again or contact support.');
        }

        return $message;
    }
}
