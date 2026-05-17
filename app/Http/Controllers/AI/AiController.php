<?php

namespace App\Http\Controllers\AI;

use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\GlobalBudgetExceededException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\AiChat;
use App\Models\AiTemplate;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AiController extends Controller
{
    public function __construct(private AiService $aiService) {}

    /**
     * POST /api/v1/ai/complete — stateless completion.
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:10000',
            'system_prompt' => 'nullable|string|max:5000',
            'provider' => 'nullable|string|in:openai,anthropic,google,xai,deepseek,openrouter',
            'model' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->aiService->complete(
                Auth::user(),
                $request->prompt,
                $request->system_prompt,
                $request->provider,
                $request->model
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/v1/ai/template/{template} — run a template.
     */
    public function runTemplate(Request $request, AiTemplate $template): JsonResponse
    {
        if (! $template->is_active) {
            return response()->json(['success' => false, 'message' => translate('Template is inactive')], 404);
        }

        $request->validate([
            'inputs' => 'required|array',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        try {
            $result = $this->aiService->runTemplate(
                Auth::user(),
                $template,
                $request->inputs,
                $request->provider,
                $request->model
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/v1/ai/chat/{chat}/message — send a message in a chat.
     */
    public function chatMessage(Request $request, AiChat $chat): JsonResponse
    {
        // Authorization: user owns this chat
        if ($chat->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => translate('Unauthorized')], 403);
        }

        $request->validate([
            'message' => 'required|string|max:10000',
            'provider' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        try {
            $response = $this->aiService->chat(
                Auth::user(),
                $chat,
                $request->message,
                $request->provider,
                $request->model
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => $response->content,
                    'role' => $response->role,
                    'tokens' => [
                        'input' => $response->input_tokens,
                        'output' => $response->output_tokens,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/v1/ai/chat — create a new chat.
     */
    public function createChat(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:100',
        ]);

        $chat = AiChat::create([
            'user_id' => Auth::id(),
            'title' => $request->title ?? 'New Chat',
            'model' => $request->model,
        ]);

        return response()->json(['success' => true, 'data' => $chat], 201);
    }

    /**
     * GET /api/v1/ai/chats — list user's chats.
     */
    public function listChats(): JsonResponse
    {
        $chats = AiChat::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'ulid', 'title', 'model', 'is_pinned', 'updated_at']);

        return response()->json(['success' => true, 'data' => $chats]);
    }

    /**
     * GET /api/v1/ai/templates — list active templates.
     */
    public function listTemplates(): JsonResponse
    {
        $templates = AiTemplate::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'category', 'icon', 'color', 'is_premium', 'fields']);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    private function errorResponse(Throwable $exception): JsonResponse
    {
        $status = match (true) {
            $exception instanceof CreditLimitException,
            $exception instanceof InsufficientCreditsException => 402,
            $exception instanceof GlobalBudgetExceededException => 503,
            default => 422,
        };

        return response()->json([
            'success' => false,
            'message' => $status === 422
                ? translate('Generation failed. Please try again or contact support if it continues.')
                : $exception->getMessage(),
            'type' => class_basename($exception),
        ], $status);
    }
}
