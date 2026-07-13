<?php

namespace Addons\PublicKnowledgeBase\Services;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbEmbedding;
use Addons\PublicKnowledgeBase\Models\KbSearch;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use Generator;
use Illuminate\Support\Collection;

/**
 * Handles the full RAG pipeline: embed query → cosine search → AI answer streaming.
 *
 * Streaming protocol (JSON-line):
 *   {"type":"sources","articles":[...]}
 *   {"type":"delta","text":"Hello"}
 *   {"type":"done","query_id":123}
 */
class KbSearchService
{
    public function __construct(
        private AiService $ai,
    ) {}

    public function searchAndAnswer(
        string $query,
        ?int $userId,
        string $sessionId,
    ): Generator {
        $embeddingResult = $this->ai->embedText($query);
        $queryVector = $embeddingResult->vector;

        $candidates = KbEmbedding::query()
            ->join('kb_articles', 'kb_embeddings.kb_article_id', '=', 'kb_articles.id')
            ->where('kb_articles.status', 'published')
            ->where('kb_articles.published_at', '<=', now())
            ->where('kb_articles.embed_status', 'done')
            ->select(
                'kb_embeddings.id',
                'kb_embeddings.kb_article_id',
                'kb_embeddings.chunk_index',
                'kb_embeddings.chunk_text',
                'kb_embeddings.embedding',
                'kb_articles.title',
                'kb_articles.slug',
                'kb_articles.ulid',
                'kb_articles.excerpt',
            )
            ->limit(500)
            ->orderBy('kb_embeddings.kb_article_id')
            ->get();

        if ($candidates->isEmpty()) {
            yield json_encode(['type' => 'sources', 'articles' => []]) . "\n";
            yield json_encode(['type' => 'delta', 'text' => "I couldn't find any matching articles. Please try rephrasing your question."]) . "\n";

            $log = KbSearch::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'query' => $query,
                'results_count' => 0,
                'was_answered' => false,
            ]);

            yield json_encode(['type' => 'done', 'query_id' => $log->id]) . "\n";
            return;
        }

        $scored = [];
        foreach ($candidates as $c) {
            $emb = $c->embedding;
            if (is_string($emb)) {
                $emb = json_decode($emb, true);
            }
            if (! is_array($emb) || empty($emb)) {
                continue;
            }
            $sim = $this->cosineSimilarity($queryVector, $emb);
            if ($sim < 0.3) {
                continue;
            }
            $scored[] = [
                'similarity' => $sim,
                'row' => $c,
            ];
        }

        usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        $topK = (int) addon_setting('public-knowledge-base', 'top_k', 5);
        $topChunks = array_slice($scored, 0, $topK);

        if (empty($topChunks)) {
            yield json_encode(['type' => 'sources', 'articles' => []]) . "\n";
            yield json_encode(['type' => 'delta', 'text' => "I couldn't find a relevant answer. Please try rephrasing your question or browse the categories below."]) . "\n";

            $log = KbSearch::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'query' => $query,
                'results_count' => 0,
                'was_answered' => false,
            ]);

            yield json_encode(['type' => 'done', 'query_id' => $log->id]) . "\n";
            return;
        }

        $seen = [];
        $uniqueArticles = [];
        foreach ($topChunks as $sc) {
            $id = $sc['row']->kb_article_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $uniqueArticles[] = [
                'ulid' => $sc['row']->ulid,
                'title' => $sc['row']->title,
                'slug' => $sc['row']->slug,
                'excerpt' => $sc['row']->excerpt,
            ];
            if (count($uniqueArticles) >= 3) {
                break;
            }
        }

        yield json_encode(['type' => 'sources', 'articles' => $uniqueArticles]) . "\n";

        $contextChunks = collect($topChunks)->map(fn ($c) =>
            "Article: {$c['row']->title}\n{$c['row']->chunk_text}"
        )->implode("\n\n---\n\n");

        $appName = settings('app_name', 'MakeAI');
        $maxTokens = (int) addon_setting('public-knowledge-base', 'max_answer_tokens', 512);

        // Optional admin-authored persona/tone. The RAG grounding rules are always
        // appended so a custom prompt can't disable "answer only from context / cite
        // sources" — it only shapes voice.
        $persona = trim((string) addon_setting('public-knowledge-base', 'system_prompt', ''));
        if ($persona === '') {
            $persona = "You are a helpful support assistant for {$appName}.";
        }

        $systemPrompt = $persona . " "
            . "Answer the user's question using ONLY the provided knowledge base context. "
            . "At the end of your answer, list the source articles you referenced as: Sources: [Article Title 1], [Article Title 2] "
            . "If the context does not contain the answer, say so honestly. "
            . "Be concise. Max {$maxTokens} tokens.";

        $userPrompt = "Context:\n{$contextChunks}\n\nQuestion: {$query}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $providerName = addon_setting('public-knowledge-base', 'provider');
        if (empty($providerName)) {
            $providerName = settings('default_ai_provider', 'openai');
        }

        $modelName = addon_setting('public-knowledge-base', 'ai_model');
        if (empty($modelName)) {
            $modelName = settings('default_ai_model', 'gpt-4o-mini');
        }

        // Cost governance: KB answers are free to end users, but the operator still
        // pays the provider. Gate on the global daily AI budget kill-switch (graceful,
        // not an exception), then record the spend afterward so the budget stays
        // accurate — WITHOUT charging any user (deductCredits: false). Behaves
        // identically under Regular and Extended licenses (no per-user credit involved).
        if (TokenGuard::globalBudgetExceeded()) {
            yield json_encode(['type' => 'delta', 'text' => 'The assistant is temporarily unavailable due to high demand. Please browse the articles below or try again later.']) . "\n";

            $log = KbSearch::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'query' => $query,
                'results_count' => count($uniqueArticles),
                'was_answered' => false,
                'article_ids' => collect($uniqueArticles)->pluck('ulid')->toArray(),
            ]);

            yield json_encode(['type' => 'done', 'query_id' => $log->id]) . "\n";
            return;
        }

        $fullAnswer = '';
        $answered = true;

        try {
            $adapter = ProviderRegistry::resolve($providerName);
            $stream = $adapter->streamChatCompletion($messages, $modelName, [
                'temperature' => 0.3,
                'max_tokens' => $maxTokens,
            ]);

            foreach ($stream as $chunk) {
                if (is_string($chunk)) {
                    $fullAnswer .= $chunk;
                    yield json_encode(['type' => 'delta', 'text' => $chunk]) . "\n";
                }
            }
        } catch (\Throwable $e) {
            $answered = false;
            yield json_encode(['type' => 'delta', 'text' => "\n[Error generating answer. Please try again.]"]) . "\n";
        }

        // Track the answer-generation spend against the global AI budget so the
        // kill-switch above stays accurate. No user is charged (deductCredits: false).
        if ($fullAnswer !== '') {
            $inputTokens = (int) ceil(mb_strlen($systemPrompt . $userPrompt) / 4);
            $outputTokens = (int) ceil(mb_strlen($fullAnswer) / 4);
            try {
                TokenGuard::after(
                    null,
                    $inputTokens,
                    $outputTokens,
                    $modelName,
                    $providerName,
                    'kb_answer',
                    ['tool_slug' => 'kb-answer'],
                    false,      // deductCredits — never charge end users for public help
                    $answered,
                );
            } catch (\Throwable $e) {
                // Cost accounting must never break the user-facing answer.
            }
        }

        $log = KbSearch::create([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'query' => $query,
            'results_count' => count($uniqueArticles),
            'was_answered' => $answered,
            'article_ids' => collect($uniqueArticles)->pluck('ulid')->toArray(),
        ]);

        yield json_encode(['type' => 'done', 'query_id' => $log->id]) . "\n";
    }

    /**
     * Retrieve relevant KB context chunks for a query (no AI generation).
     *
     * Returns ['context' => string, 'sources' => array].
     */
    public function getRelevantContext(string $query, int $topK = 5): array
    {
        $embeddingResult = $this->ai->embedText($query);
        $queryVector = $embeddingResult->vector;

        $candidates = KbEmbedding::query()
            ->join('kb_articles', 'kb_embeddings.kb_article_id', '=', 'kb_articles.id')
            ->where('kb_articles.status', 'published')
            ->where('kb_articles.published_at', '<=', now())
            ->where('kb_articles.embed_status', 'done')
            ->select(
                'kb_embeddings.id',
                'kb_embeddings.kb_article_id',
                'kb_embeddings.chunk_text',
                'kb_embeddings.embedding',
                'kb_articles.title',
                'kb_articles.slug',
                'kb_articles.ulid',
            )
            ->limit(500)
            ->orderBy('kb_embeddings.kb_article_id')
            ->get();

        if ($candidates->isEmpty()) {
            return ['context' => '', 'sources' => []];
        }

        $scored = [];
        foreach ($candidates as $c) {
            $emb = $c->embedding;
            if (is_string($emb)) {
                $emb = json_decode($emb, true);
            }
            if (! is_array($emb) || empty($emb)) {
                continue;
            }
            $sim = $this->cosineSimilarity($queryVector, $emb);
            if ($sim < 0.3) {
                continue;
            }
            $scored[] = ['similarity' => $sim, 'row' => $c];
        }

        usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
        $topChunks = array_slice($scored, 0, $topK);

        if (empty($topChunks)) {
            return ['context' => '', 'sources' => []];
        }

        // Build context string
        $contextChunks = collect($topChunks)->map(fn ($c) =>
            "Article: {$c['row']->title}\n{$c['row']->chunk_text}"
        )->implode("\n\n---\n\n");

        // Build unique source articles
        $seen = [];
        $sources = [];
        foreach ($topChunks as $sc) {
            $id = $sc['row']->kb_article_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $sources[] = [
                'ulid' => $sc['row']->ulid,
                'title' => $sc['row']->title,
                'slug' => $sc['row']->slug,
            ];
            if (count($sources) >= 5) {
                break;
            }
        }

        return ['context' => $contextChunks, 'sources' => $sources];
    }

    public function getRelatedArticles(KbArticle $article, int $limit = 4): Collection
    {
        $embeddings = KbEmbedding::where('kb_article_id', $article->id)->pluck('embedding');
        if ($embeddings->isEmpty()) {
            return collect();
        }

        $avgVector = [];
        $count = 0;
        foreach ($embeddings as $emb) {
            if (is_string($emb)) {
                $emb = json_decode($emb, true);
            }
            if (! is_array($emb)) {
                continue;
            }
            foreach ($emb as $i => $v) {
                $avgVector[$i] = ($avgVector[$i] ?? 0) + $v;
            }
            $count++;
        }

        if ($count === 0) {
            return collect();
        }

        foreach ($avgVector as $i => $v) {
            $avgVector[$i] = $v / $count;
        }

        $candidates = KbEmbedding::query()
            ->join('kb_articles', 'kb_embeddings.kb_article_id', '=', 'kb_articles.id')
            ->where('kb_articles.id', '!=', $article->id)
            ->where('kb_articles.status', 'published')
            ->where('kb_articles.embed_status', 'done')
            ->select('kb_embeddings.embedding', 'kb_articles.id', 'kb_articles.ulid', 'kb_articles.title', 'kb_articles.slug', 'kb_articles.excerpt')
            ->limit(200)
            ->get();

        $scored = [];
        foreach ($candidates as $c) {
            $emb = $c->embedding;
            if (is_string($emb)) {
                $emb = json_decode($emb, true);
            }
            if (! is_array($emb)) {
                continue;
            }
            $scored[] = [
                'similarity' => $this->cosineSimilarity($avgVector, $emb),
                'article_id' => $c->id,
                'ulid' => $c->ulid,
                'title' => $c->title,
                'slug' => $c->slug,
                'excerpt' => $c->excerpt,
            ];
        }

        usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        $seen = [];
        $results = [];
        foreach ($scored as $s) {
            if (isset($seen[$s['article_id']])) {
                continue;
            }
            $seen[$s['article_id']] = true;
            $results[] = $s;
            if (count($results) >= $limit) {
                break;
            }
        }

        return collect($results)->map(fn ($r) => [
            'ulid' => $r['ulid'],
            'title' => $r['title'],
            'slug' => $r['slug'],
            'excerpt' => $r['excerpt'],
        ]);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $bVal = $b[$i] ?? 0.0;
            $dot += $val * $bVal;
            $normA += $val * $val;
            $normB += $bVal * $bVal;
        }

        if ($normA > 0 && $normB > 0) {
            return $dot / (sqrt($normA) * sqrt($normB));
        }

        return 0.0;
    }
}
