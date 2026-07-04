<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\AiTool;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $query = $request->query('q', '');

        if (strlen($query) < 2) {
            return Inertia::render('User/Search', [
                'query' => $query,
                'conversations' => [],
                'documents' => [],
                'tools' => [],
            ]);
        }

        $conversations = [];
        if (is_addon_active('ai-chatbot') && \Illuminate\Support\Facades\Schema::hasTable('conversations')) {
            $conversations = \Addons\AiChatbot\Models\Conversation::where('user_id', $user->id)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhereHas('messages', function ($mq) use ($query) {
                          $mq->where('content', 'like', "%{$query}%");
                      });
                })
                ->latest('last_message_at')
                ->take(10)
                ->get()
                ->map(fn ($conv) => [
                    'id' => $conv->id,
                    'ulid' => $conv->ulid,
                    'title' => $conv->title ?: 'Untitled',
                    'model' => $conv->model,
                    'message_count' => $conv->message_count,
                    'last_message_at' => optional($conv->last_message_at)->toISOString(),
                ]);
        }

        $documents = Document::where('user_id', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Document $doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'tool_slug' => $doc->tool_slug,
                'word_count' => $doc->word_count,
                'created_at' => $doc->created_at->toISOString(),
            ]);

        $tools = AiTool::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('sort_order')
            ->take(10)
            ->get()
            ->map(fn (AiTool $tool) => [
                'name' => $tool->name,
                'slug' => $tool->slug,
                'description' => $tool->description,
                'icon' => $tool->icon,
                'color' => $tool->color,
                'requires_pro' => (bool) $tool->requires_pro,
            ]);

        return Inertia::render('User/Search', [
            'query' => $query,
            'conversations' => $conversations,
            'documents' => $documents,
            'tools' => $tools,
        ]);
    }
}
