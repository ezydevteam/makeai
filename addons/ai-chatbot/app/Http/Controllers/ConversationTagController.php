<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use Addons\AiChatbot\Models\Conversation;
use Addons\AiChatbot\Models\ConversationTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationTagController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $tags = $user->conversationTags()->withCount('conversations')->get();

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sign in to create tags.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tag = $user->conversationTags()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $tag,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Sign in to manage tags.'], 403);
        }

        $tag = $user->conversationTags()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tag->update($validated);

        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Sign in to manage tags.'], 403);
        }

        $tag = $user->conversationTags()->findOrFail($id);
        $tag->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function tagConversation(Request $request, string $ulid): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Sign in to manage tags.'], 403);
        }

        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'integer|exists:conversation_tags,id',
        ]);

        // Verify tags belong to user
        $validTagIds = $user->conversationTags()
            ->whereIn('id', $validated['tag_ids'])
            ->pluck('id')
            ->toArray();

        $conversation->tags()->sync($validTagIds);

        return response()->json([
            'success' => true,
        ]);
    }
}
