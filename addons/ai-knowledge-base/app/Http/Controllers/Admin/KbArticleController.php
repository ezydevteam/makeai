<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Admin;

use Addons\AiKnowledgeBase\Http\Requests\Admin\KbArticleRequest;
use Addons\AiKnowledgeBase\Jobs\IngestKbArticle;
use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class KbArticleController extends Controller
{
    public function index()
    {
        $filters = request()->only(['search', 'status', 'category_id', 'embed_status']);

        $baseQuery = KbArticle::with(['category', 'creator'])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('kb_category_id', $v))
            ->when($filters['embed_status'] ?? null, fn ($q, $v) => $q->where('embed_status', $v));

        $articles = (clone $baseQuery)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $categories = KbCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Addons/ai-knowledge-base/Admin/Articles/Index', [
            'articles' => $articles,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $categories = KbCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Addons/ai-knowledge-base/Admin/Articles/Form', [
            'article' => null,
            'categories' => $categories,
        ]);
    }

    public function store(KbArticleRequest $request)
    {
        $article = KbArticle::create([
            ...$request->validated(),
            'created_by' => auth('admin')->id(),
            'published_at' => $request->status === 'published' ? now() : null,
        ]);

        if ($article->status === 'published') {
            IngestKbArticle::dispatch($article->id)->onQueue('embeddings');
        }

        return to_route('addon.kb.admin.articles.edit', $article)
            ->with('success', 'Article created.');
    }

    public function edit(KbArticle $article)
    {
        $article->load('category');
        $categories = KbCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Addons/ai-knowledge-base/Admin/Articles/Form', [
            'article' => $article,
            'categories' => $categories,
        ]);
    }

    public function update(KbArticleRequest $request, KbArticle $article)
    {
        $wasPublished = $article->status === 'published';
        $article->update($request->validated());

        if ($article->status === 'published' && $article->embed_status === 'pending') {
            IngestKbArticle::dispatch($article->id)->onQueue('embeddings');
        }

        return back()->with('success', 'Article updated.');
    }

    public function destroy(KbArticle $article)
    {
        $article->delete();

        return to_route('addon.kb.admin.articles.index')
            ->with('success', 'Article deleted.');
    }

    public function reEmbed(KbArticle $article): JsonResponse
    {
        $article->update(['embed_status' => 'pending', 'embed_error' => null]);
        IngestKbArticle::dispatch($article->id)->onQueue('embeddings');

        return response()->json(['message' => 'Re-embedding queued']);
    }
}
