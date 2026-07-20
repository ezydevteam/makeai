<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Public;

use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbArticleVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KbVoteController extends Controller
{
    public function store(Request $request, KbArticle $article): JsonResponse
    {
        $validated = $request->validate([
            'vote' => ['required', 'integer', 'in:1,-1'],
        ]);

        $sessionId = $request->session()->getId();

        KbArticleVote::updateOrCreate(
            ['kb_article_id' => $article->id, 'session_id' => $sessionId],
            ['user_id' => auth()->id(), 'vote' => $validated['vote']],
        );

        $helpful = KbArticleVote::where('kb_article_id', $article->id)->where('vote', 1)->count();
        $notHelpful = KbArticleVote::where('kb_article_id', $article->id)->where('vote', -1)->count();
        $article->update(['helpful_count' => $helpful, 'not_helpful_count' => $notHelpful]);

        return response()->json([
            'helpful_count' => $helpful,
            'not_helpful_count' => $notHelpful,
            'your_vote' => $validated['vote'],
        ]);
    }
}
