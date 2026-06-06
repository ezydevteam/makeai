<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatbotProduct;
use App\Models\Conversation;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private AiService $ai,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $isPro = $user->is_pro === true || ($user->plan?->type ?? '') === 'pro';

        $query = $user->conversations()->latest('last_message_at');

        $limit = $isPro ? 200 : (int) settings('free_max_chat_history', 30);
        $query->limit($limit);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        } else {
            $query->whereNull('project_id');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'product_slug' => 'nullable|string|exists:chatbot_products,slug',
            'model' => 'nullable|string|max:150',
            'project_id' => 'nullable|integer|exists:chat_projects,id',
        ]);

        $conversation = $user->conversations()->create([
            'product_slug' => $validated['product_slug'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'model' => $validated['model'] ?? null,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $conversation->messages()->get(),
            ],
        ]);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer|exists:chat_projects,id',
            'model' => 'nullable|string|max:150',
        ]);

        $conversation->update($validated);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => translate('Conversation deleted.'),
        ]);
    }

    public function sendMessage(Request $request, string $ulid): StreamedResponse
    {
        $user = Auth::user();
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:16000',
            'product_slug' => 'nullable|string|exists:chatbot_products,slug',
            'model' => 'nullable|string|max:150',
        ]);

        $product = null;
        if ($validated['product_slug'] ?? null) {
            $product = ChatbotProduct::where('slug', $validated['product_slug'])->active()->first();
        } elseif ($conversation->product_slug) {
            $product = ChatbotProduct::where('slug', $conversation->product_slug)->active()->first();
        }

        $model = $validated['model'] ?? $conversation->model ?? $product?->default_model ?? 'gpt-4o-mini';
        $systemPrompt = $product?->system_prompt ?? null;

        $productSwitch = null;
        if ($product && $conversation->product_slug !== $product->slug) {
            $productSwitch = $product->slug;
            $conversation->update(['product_slug' => $product->slug]);
        }

        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['content'],
            'model' => $model,
            'product_switch' => $productSwitch,
        ]);

        if (! $conversation->title) {
            $conversation->update([
                'title' => mb_substr($validated['content'], 0, 60),
            ]);
        }

        $history = $conversation->messages()
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->reverse();

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        $provider = $model ? $this->resolveProvider($model) : 'openai';

        try {
            TokenGuard::before($user, null, $model);
        } catch (\Throwable $e) {
            return response()->stream(function () use ($e) {
                echo 'data: '.json_encode(['type' => 'error', 'message' => $e->getMessage()])."\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
            ]);
        }

        return response()->stream(function () use (
            $conversation, $user, $model, $provider, $messages

        ) {
            $fullContent = '';

            try {
                $adapter = ProviderRegistry::resolve($provider);

                $stream = $adapter->streamChatCompletion(
                    $messages,
                    $model,
                    ['temperature' => 0.7, 'max_tokens' => 4096]
                );

                $startTime = microtime(true);

                foreach ($stream as $chunk) {
                    if (is_string($chunk)) {
                        $token = $chunk;
                        $fullContent .= $token;
                        echo 'data: '.json_encode(['type' => 'token', 'content' => $token])."\n\n";
                    } elseif (is_array($chunk)) {
                        if (isset($chunk['reasoning_start'])) {
                            echo 'data: '.json_encode(['type' => 'reasoning_start'])."\n\n";
                        } elseif (isset($chunk['reasoning_end'])) {
                            echo 'data: '.json_encode(['type' => 'reasoning_end'])."\n\n";
                        } elseif (isset($chunk['reasoning'])) {
                            echo 'data: '.json_encode(['type' => 'reasoning', 'content' => $chunk['reasoning']])."\n\n";
                        }
                        // usage stats array is yielded at the end, consumed via yield $usage below
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    if (connection_aborted()) {
                        break;
                    }
                }

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                $success = ! connection_aborted();

                $assistantMsg = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'model' => $model,
                ]);

                $inputTokens = $this->estimateTokens($messages);
                $outputTokens = $this->estimateTokens([['role' => 'assistant', 'content' => $fullContent]]);

                $credits = TokenGuard::after(
                    $user,
                    $inputTokens,
                    $outputTokens,
                    $model,
                    $provider,
                    'chat',
                    ['conversation_ulid' => $conversation->ulid],
                    true,
                    $success,
                    null,
                    $responseTime
                );

                $conversation->increment('message_count', 2);
                $conversation->increment('total_tokens', $inputTokens + $outputTokens);
                $conversation->increment('total_credits', $credits);
                $conversation->update(['last_message_at' => now(), 'model' => $model]);

                $status = $success ? 'success' : 'cancelled';

                echo 'data: '.json_encode([
                    'type' => 'usage',
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'credits' => $credits,
                    'model' => $model,
                    'status' => $status,
                ])."\n\n";

            } catch (\Throwable $e) {
                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ])."\n\n";

                if (! empty($fullContent)) {
                    $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $fullContent,
                        'model' => $model,
                    ]);
                }
            }

            echo 'data: '.json_encode(['type' => 'done'])."\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    private function resolveProvider(string $model): string
    {
        return match (true) {
            str_contains($model, 'claude') => 'anthropic',
            str_contains($model, 'gemini') => 'google',
            str_contains($model, 'deepseek') => 'deepseek',
            str_contains($model, 'perplexity') => 'perplexity',
            str_contains($model, 'mistral') => 'mistral',
            str_contains($model, 'dall-e') => 'openai',
            default => 'openai',
        };
    }

    private function estimateTokens(array $messages): int
    {
        $text = '';
        foreach ($messages as $msg) {
            $text .= ($msg['content'] ?? '').' ';
        }

        return max(1, (int) (str_word_count($text, 0) * 1.3));
    }
}
