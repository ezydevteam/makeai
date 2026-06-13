<?php

namespace App\Services\AI\Rag;

use App\DTO\RagResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HybridSearchService
{
    private string $mode;

    public function __construct()
    {
        $this->mode = settings('rag_search_mode', 'vector');
    }

    /**
     * Search using hybrid BM25 + vector fusion, or fall back to pure vector.
     *
     * @param  array<float>  $queryVector
     * @param  string  $queryText  raw query for BM25
     * @return array<int, array{chunk_id: int, document_id: int, score: float, metadata: array}>
     */
    public function search(
        string $knowledgeBaseId,
        array $queryVector,
        string $queryText,
        int $topK = 6,
    ): array {
        if ($this->mode === 'hybrid') {
            return $this->hybridSearch($knowledgeBaseId, $queryVector, $queryText, $topK);
        }

        return app(VectorStoreService::class)->search($knowledgeBaseId, $queryVector, $topK);
    }

    private function hybridSearch(
        string $knowledgeBaseId,
        array $queryVector,
        string $queryText,
        int $topK,
    ): array {
        // Get vector results
        $vectorResults = app(VectorStoreService::class)->search(
            $knowledgeBaseId,
            $queryVector,
            max($topK * 2, 20),
        );

        // Get BM25 (FULLTEXT) results
        $bm25Results = $this->bm25Search($knowledgeBaseId, $queryText, max($topK * 2, 20));

        // Reciprocal Rank Fusion
        return $this->reciprocalRankFusion($vectorResults, $bm25Results, $topK);
    }

    private function bm25Search(string $knowledgeBaseId, string $queryText, int $limit): array
    {
        $documentIds = DB::table('knowledge_base_documents')
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->pluck('id');

        if ($documentIds->isEmpty()) {
            return [];
        }

        $results = DB::table('knowledge_base_chunks')
            ->whereIn('document_id', $documentIds)
            ->whereFullText('text', $queryText, ['mode' => 'boolean'])
            ->select('id', 'document_id', DB::raw('MATCH(text) AGAINST(? IN BOOLEAN MODE) as relevance'))
            ->addBinding($queryText, 'select')
            ->orderByDesc('relevance')
            ->limit($limit)
            ->get();

        return $results->map(fn ($row) => [
            'chunk_id' => $row->id,
            'document_id' => $row->document_id,
            'score' => (float) $row->relevance,
        ])->toArray();
    }

    /**
     * Reciprocal Rank Fusion (k=60).
     */
    private function reciprocalRankFusion(array $vectorResults, array $bm25Results, int $topK): array
    {
        $k = 60;
        $scores = [];
        $metaCache = [];

        foreach ($vectorResults as $rank => $result) {
            $id = $result['chunk_id'];
            $scores[$id] = ($scores[$id] ?? 0) + (1 / ($k + $rank + 1));
            if (! isset($metaCache[$id])) {
                $metaCache[$id] = $result;
            }
        }

        foreach ($bm25Results as $rank => $result) {
            $id = $result['chunk_id'];
            $scores[$id] = ($scores[$id] ?? 0) + (1 / ($k + $rank + 1));
            if (! isset($metaCache[$id])) {
                $metaCache[$id] = $result;
            }
        }

        arsort($scores);

        $fused = [];
        foreach (array_slice($scores, 0, $topK, true) as $chunkId => $score) {
            $fused[] = array_merge($metaCache[$chunkId], ['score' => round($score, 4)]);
        }

        return $fused;
    }
}
