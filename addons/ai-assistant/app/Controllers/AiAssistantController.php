<?php

namespace Addons\AiAssistant\Controllers;

use Addons\AiAssistant\Models\AiAssistantFeedback;
use Addons\AiAssistant\Services\AiAssistantService;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAssistantController extends Controller
{
    public function __construct(
        private AiAssistantService $assistantService,
        private AiService $aiService,
    ) {}

    /**
     * Frontend chat — authenticated users, credit deduction via AiService::stream().
     */
    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
            'session_id' => 'required|string|max:64',
            'context_page' => 'nullable|string|max:255',
        ]);

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        if (! $this->assistantService->isVisibleForUser($user, false)) {
            abort(403, 'AI Assistant is not available.');
        }

        // Try to find a matching automation rule first
        $rule = $this->assistantService->findMatchingAutomationRule($validated['message']);
        if ($rule) {
            $this->assistantService->incrementDailyCount($user, $validated['session_id']);
            $responseText = $this->assistantService->interpolateVariables($rule->response, $user, $validated['context_page'] ?? '');
            return response()->stream(function () use ($responseText) {
                echo "READY\n";
                echo $responseText;
                if (ob_get_level() > 0) ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        }

        if (! $this->assistantService->checkDailyLimit($user, $validated['session_id'])) {
            return new StreamedResponse(function () {
                echo "ERROR:daily_limit_reached\n";
            }, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $systemPrompt = $this->assistantService->buildFrontendSystemPrompt($user, $validated['context_page'] ?? '');

        $prompt = $this->buildPromptWithHistory(
            $validated['message'],
            $validated['history'] ?? [],
        );

        $this->assistantService->incrementDailyCount($user, $validated['session_id']);

        $providerName = addon_setting('ai-assistant', 'provider');
        if (empty($providerName)) {
            $providerName = settings('default_ai_provider', 'openai');
        }

        $modelName = addon_setting('ai-assistant', 'model');
        if (empty($modelName)) {
            $modelName = settings('default_ai_model', 'gpt-4o-mini');
        }

        // Pre-flight credit check BEFORE entering the stream callback
        try {
            TokenGuard::before($user, null, $modelName);
        } catch (\Throwable $e) {
            return new StreamedResponse(function () use ($e) {
                echo "ERROR:" . addslashes($e->getMessage()) . "\n";
            }, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->stream(function () use ($user, $prompt, $systemPrompt, $providerName, $modelName) {
            // Initial heartbeat — confirms stream is alive
            echo "READY\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            try {
                $customApiKey = addon_setting('ai-assistant', 'custom_api_key');
                if (!empty($customApiKey)) {
                    $adapter = ProviderRegistry::resolveWithKey($providerName, $customApiKey);
                } else {
                    $adapter = ProviderRegistry::resolve($providerName);
                }

                $messages = [];
                if ($systemPrompt) {
                    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
                }
                $messages[] = ['role' => 'user', 'content' => $prompt];

                $stream = $adapter->streamChatCompletion($messages, $modelName, [
                    'temperature' => (float) addon_setting('ai-assistant', 'temperature', 0.7),
                    'max_tokens' => (int) addon_setting('ai-assistant', 'max_tokens', 1024),
                ]);

                foreach ($stream as $chunk) {
                    if (is_string($chunk)) {
                        echo $chunk;
                    } elseif (is_array($chunk)) {
                        // Usage stats — record them
                        if (isset($chunk['input_tokens'])) {
                            TokenGuard::after(
                                $user,
                                $chunk['input_tokens'],
                                $chunk['output_tokens'],
                                $chunk['model'] ?? $modelName,
                                $providerName,
                                'assistant_chat',
                            );
                        }
                        // Reasoning events (reasoning_start, reasoning, reasoning_end)
                        // are intentionally skipped — assistant widget shows text only.
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                Log::error('AI Assistant chat error', [
                    'error' => $e->getMessage(),
                    'provider' => $providerName,
                    'model' => $modelName,
                    'trace' => $e->getTraceAsString(),
                ]);
                echo "\nERROR:" . addslashes($e->getMessage()) . "\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Admin chat — no credit deduction, uses ProviderRegistry directly.
     */
    public function adminChat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'history' => 'nullable|array|max:30',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
            'session_id' => 'required|string|max:64',
        ]);

        if (! $this->assistantService->isVisibleForUser(null, true)) {
            abort(403, 'AI Assistant is not available in admin panel.');
        }

        // Try to find a matching automation rule first
        $user = auth('admin')->user();
        $rule = $this->assistantService->findMatchingAutomationRule($validated['message']);
        if ($rule) {
            $responseText = $this->assistantService->interpolateVariables($rule->response, $user, 'Admin Panel');
            return response()->stream(function () use ($responseText) {
                echo "READY\n";
                echo $responseText;
                if (ob_get_level() > 0) ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        }

        $systemPrompt = $this->assistantService->buildAdminSystemPrompt();

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (($validated['history'] ?? []) as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $validated['message']];

        $providerName = addon_setting('ai-assistant', 'provider');
        if (empty($providerName)) {
            $providerName = settings('default_ai_provider', 'openai');
        }

        $modelName = addon_setting('ai-assistant', 'model');
        if (empty($modelName)) {
            $modelName = settings('default_ai_model', 'gpt-4o-mini');
        }

        return response()->stream(function () use ($providerName, $modelName, $messages) {
            echo "READY\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            try {
                $customApiKey = addon_setting('ai-assistant', 'custom_api_key');
                if (!empty($customApiKey)) {
                    $adapter = ProviderRegistry::resolveWithKey($providerName, $customApiKey);
                } else {
                    $adapter = ProviderRegistry::resolve($providerName);
                }

                $stream = $adapter->streamChatCompletion($messages, $modelName, [
                    'temperature' => (float) addon_setting('ai-assistant', 'temperature', 0.7),
                    'max_tokens' => (int) addon_setting('ai-assistant', 'max_tokens', 1024),
                ]);

                foreach ($stream as $chunk) {
                    if (is_string($chunk)) {
                        echo $chunk;
                    }
                    // Array chunks (reasoning events, usage stats) are ignored
                    // in admin chat — no credit deduction for admin.

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                Log::error('AI Assistant admin chat error', [
                    'error' => $e->getMessage(),
                    'provider' => $providerName,
                    'model' => $modelName,
                    'trace' => $e->getTraceAsString(),
                ]);
                echo "\nERROR:" . addslashes($e->getMessage()) . "\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Store feedback (thumbs up/down + optional comment).
     */
    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
            'message_hash' => 'required|string|max:64',
            'rating' => 'required|integer|in:1,-1',
            'comment' => 'nullable|string|max:500',
            'context_page' => 'nullable|string|max:255',
        ]);

        AiAssistantFeedback::updateOrCreate(
            [
                'session_id' => $validated['session_id'],
                'message_hash' => $validated['message_hash'],
            ],
            [
                'user_id' => $request->user()?->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'context_page' => $validated['context_page'] ?? null,
            ]
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Extract text from uploaded document.
     */
    public function extractText(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:' . (int) addon_setting('ai-assistant', 'max_upload_size_kb', 10240), // Configurable limit
        ]);

        $file = $request->file('file');
        if (! $file->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid file upload.',
            ], 400);
        }

        $tempPath = $file->getRealPath();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Validate allowed file types dynamically based on admin setting
        $allowedTypesStr = addon_setting('ai-assistant', 'allowed_file_types', 'pdf,docx,txt,csv,png,jpg');
        $allowedList = array_map('trim', explode(',', strtolower($allowedTypesStr)));
        // Remove leading dots if entered by admin (e.g., .png -> png)
        $allowedList = array_map(fn($ext) => ltrim($ext, '.'), $allowedList);

        if (! in_array($extension, $allowedList)) {
            return response()->json([
                'success' => false,
                'error' => 'File type not allowed: ' . $extension,
            ], 400);
        }

        // Create a temporary file with the correct extension to support Smalot/extension-based extraction
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir . '/' . uniqid('extract_') . '.' . $extension;
        copy($tempPath, $tempFile);

        try {
            $extractor = app(\App\Services\AI\Rag\TextExtractionService::class);
            if (! $extractor->supports($tempFile)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unsupported file type: ' . $extension,
                ], 400);
            }

            $text = $extractor->extract($tempFile);

            return response()->json([
                'success' => true,
                'filename' => $originalName,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Assistant text extraction error', [
                'error' => $e->getMessage(),
                'filename' => $originalName,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to parse file: ' . $e->getMessage(),
            ], 500);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Store new automation rule.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trigger' => 'required|string|max:255',
            'response' => 'required|string',
            'match_type' => 'required|string|in:exact,contains',
            'is_active' => 'required|boolean',
        ]);

        $rule = \Addons\AiAssistant\Models\AiAssistantRule::create($validated);

        return response()->json([
            'success' => true,
            'rule' => $rule,
        ]);
    }

    /**
     * Update automation rule.
     */
    public function updateRule(Request $request, $id): JsonResponse
    {
        $rule = \Addons\AiAssistant\Models\AiAssistantRule::findOrFail($id);

        $validated = $request->validate([
            'trigger' => 'required|string|max:255',
            'response' => 'required|string',
            'match_type' => 'required|string|in:exact,contains',
            'is_active' => 'required|boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'rule' => $rule,
        ]);
    }

    /**
     * Delete automation rule.
     */
    public function deleteRule($id): JsonResponse
    {
        $rule = \Addons\AiAssistant\Models\AiAssistantRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    // ─── helpers ──────────────────────────────────────────

    private function buildPromptWithHistory(string $message, array $history): string
    {
        if (empty($history)) {
            return $message;
        }

        $context = '';
        foreach ($history as $msg) {
            $label = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $context .= "{$label}: {$msg['content']}\n";
        }
        $context .= "User: {$message}";

        return $context;
    }
}
