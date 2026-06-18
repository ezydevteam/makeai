<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessageFeedback;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'conversation_ulid' => 'required|string|exists:conversations,ulid',
            'message_id' => 'required|integer|exists:conversation_messages,id',
            'rating' => 'required|in:1,-1',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Verify conversation ownership
        $conversation = Conversation::where('ulid', $validated['conversation_ulid'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Verify message belongs to conversation
        $message = ConversationMessage::where('id', $validated['message_id'])
            ->where('conversation_id', $conversation->id)
            ->firstOrFail();

        // Only allow feedback on assistant messages
        if ($message->role !== 'assistant') {
            return response()->json([
                'success' => false,
                'message' => 'Feedback can only be given on assistant messages.',
            ], 422);
        }

        // Upsert feedback (one per user per message)
        $feedback = ChatMessageFeedback::updateOrCreate(
            [
                'user_id' => $user->id,
                'message_id' => $message->id,
            ],
            [
                'conversation_id' => $conversation->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'ulid' => $feedback->ulid,
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
            ],
        ]);
    }

    public function index(Request $request, string $conversationUlid): JsonResponse
    {
        $user = Auth::user();

        $conversation = Conversation::where('ulid', $conversationUlid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $feedback = ChatMessageFeedback::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn ($f) => [
                'message_id' => $f->message_id,
                'rating' => $f->rating,
                'comment' => $f->comment,
            ]);

        return response()->json([
            'success' => true,
            'data' => $feedback,
        ]);
    }
}
