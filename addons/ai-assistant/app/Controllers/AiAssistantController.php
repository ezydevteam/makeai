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

        $providerName = settings('default_ai_provider', 'openai');
        $modelName = addon_setting('ai-assistant', 'model', 'gpt-4o-mini');

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
            // Flush all output buffers so streaming works
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Initial heartbeat — confirms stream is alive
            echo "READY\n";
            flush();

            try {
                $adapter = ProviderRegistry::resolve($providerName);

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
                        flush();
                    } elseif (is_array($chunk)) {
                        // usage stats — record them
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
                    }
                }
            } catch (\Throwable $e) {
                Log::error('AI Assistant chat error: ' . $e->getMessage());
                echo "\nERROR:" . addslashes($e->getMessage()) . "\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Transfer-Encoding' => 'chunked',
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

        $systemPrompt = $this->assistantService->buildAdminSystemPrompt();

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (($validated['history'] ?? []) as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $validated['message']];

        $providerName = settings('default_ai_provider', 'openai');
        $modelName = addon_setting('ai-assistant', 'model', 'gpt-4o-mini');

        return response()->stream(function () use ($providerName, $modelName, $messages) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            echo "READY\n";
            flush();

            try {
                $adapter = ProviderRegistry::resolve($providerName);

                $stream = $adapter->streamChatCompletion($messages, $modelName, [
                    'temperature' => (float) addon_setting('ai-assistant', 'temperature', 0.7),
                    'max_tokens' => (int) addon_setting('ai-assistant', 'max_tokens', 1024),
                ]);

                foreach ($stream as $chunk) {
                    if (is_string($chunk)) {
                        echo $chunk;
                        flush();
                    }
                }
            } catch (\Throwable $e) {
                Log::error('AI Assistant admin chat error: ' . $e->getMessage());
                echo "\nERROR:" . addslashes($e->getMessage()) . "\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Transfer-Encoding' => 'chunked',
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
