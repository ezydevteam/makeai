<?php

namespace App\Services\AI\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * VectorStoreService — stores and queries vector embeddings.
 *
 * Default backend: database (via vector_embeddings table).
 * Supports: pgvector, Qdrant, Pinecone via config('ai.rag.vector_store_driver').
 */
class VectorStoreService
{
    private string $driver;

    public function __construct()
    {
        $this->driver = settings('rag_vector_store_driver') ?: config('ai.rag.vector_store_driver', 'database');
    }

    /**
     * Store an embedding vector for a document chunk.
     */
    public function store(
        string $knowledgeBaseId,
        int $documentId,
        int $chunkId,
        int $userId,
        array $vector,
        array $metadata = [],
    ): void {
        match ($this->driver) {
            'database' => $this->storeInDatabase($knowledgeBaseId, $documentId, $chunkId, $userId, $vector, $metadata),
            'qdrant' => $this->storeInQdrant($knowledgeBaseId, $documentId, $chunkId, $vector, $metadata),
            'pinecone' => $this->storeInPinecone($knowledgeBaseId, $documentId, $chunkId, $vector, $metadata),
            default => throw new RuntimeException("Unsupported vector store driver: {$this->driver}"),
        };
    }

    /**
     * Search for the top-K most similar chunks.
     *
     * @param  array<float>  $queryVector
     * @return array<int, array{chunk_id: int, document_id: int, score: float, metadata: array}>
     */
    public function search(
        string $knowledgeBaseId,
        array $queryVector,
        int $topK = 5,
        ?string $filter = null,
    ): array {
        return match ($this->driver) {
            'database' => $this->searchInDatabase($knowledgeBaseId, $queryVector, $topK, $filter),
            'qdrant' => $this->searchInQdrant($knowledgeBaseId, $queryVector, $topK, $filter),
            'pinecone' => $this->searchInPinecone($knowledgeBaseId, $queryVector, $topK, $filter),
            default => throw new RuntimeException("Unsupported vector store driver: {$this->driver}"),
        };
    }

    /**
     * Delete all vectors for a document.
     *
     * The external drivers used to fall through to `default => null`, so deleting a
     * session or document removed the local rows but left every vector behind in
     * Qdrant/Pinecone — billable storage holding the user's document content after
     * they deleted it.
     */
    public function deleteDocumentVectors(int $documentId): void
    {
        match ($this->driver) {
            'database' => \DB::table('vector_embeddings')->where('document_id', $documentId)->delete(),
            'qdrant' => $this->deleteFromQdrant($documentId),
            'pinecone' => $this->deleteFromPinecone($documentId),
            default => throw new RuntimeException("Unsupported vector store driver: {$this->driver}"),
        };
    }

    private function deleteFromQdrant(int $documentId): void
    {
        $qdrantUrl = rtrim(config('services.qdrant.url', ''), '/');
        $qdrantKey = config('services.qdrant.api_key', '');

        if (empty($qdrantUrl)) {
            throw new RuntimeException('Qdrant URL is not configured.');
        }

        // The collection is the knowledge base, which we don't have here — resolve the
        // affected collections from the local chunk rows that mirror the vectors.
        $knowledgeBaseIds = \DB::table('knowledge_base_documents')
            ->where('id', $documentId)
            ->pluck('knowledge_base_id');

        foreach ($knowledgeBaseIds as $knowledgeBaseId) {
            $response = Http::withHeaders(['api-key' => $qdrantKey])
                ->post("{$qdrantUrl}/collections/{$knowledgeBaseId}/points/delete", [
                    'filter' => [
                        'must' => [[
                            'key' => 'document_id',
                            'match' => ['value' => $documentId],
                        ]],
                    ],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException("Qdrant vector delete failed: {$response->body()}");
            }
        }
    }

    private function deleteFromPinecone(int $documentId): void
    {
        $pineconeKey = config('services.pinecone.api_key', '');
        $pineconeHost = config('services.pinecone.host', '');

        if (empty($pineconeKey) || empty($pineconeHost)) {
            throw new RuntimeException('Pinecone is not configured.');
        }

        $knowledgeBaseIds = \DB::table('knowledge_base_documents')
            ->where('id', $documentId)
            ->pluck('knowledge_base_id');

        foreach ($knowledgeBaseIds as $knowledgeBaseId) {
            $response = Http::withHeaders(['Api-Key' => $pineconeKey])
                ->post("https://{$pineconeHost}/vectors/delete", [
                    'filter' => ['document_id' => ['$eq' => $documentId]],
                    'namespace' => (string) $knowledgeBaseId,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException("Pinecone vector delete failed: {$response->body()}");
            }
        }
    }

    // ─── Database Backend ────────────────────────────────────────

    private function storeInDatabase(
        string $knowledgeBaseId,
        int $documentId,
        int $chunkId,
        int $userId,
        array $vector,
        array $metadata = [],
    ): void {
        \DB::table('vector_embeddings')->insert([
            'knowledge_base_id' => $knowledgeBaseId,
            'document_id' => $documentId,
            'chunk_id' => $chunkId,
            'user_id' => $userId,
            'embedding' => json_encode($vector),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function searchInDatabase(
        string $knowledgeBaseId,
        array $queryVector,
        int $topK = 5,
        ?string $filter = null,
    ): array {
        // The database driver has no vector index, so similarity is computed in PHP.
        // Stream the rows with a cursor and keep only the running top-K: loading them
        // all up front (->get()) held every chunk's full float vector in memory at
        // once, which a few hundred pages of PDF turns into hundreds of megabytes.
        // Use pgvector/Qdrant for real scale.
        $query = \DB::table('vector_embeddings')
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->select('chunk_id', 'document_id', 'embedding', 'metadata');

        if ($filter) {
            $query->where('metadata->type', $filter);
        }

        $top = [];

        foreach ($query->cursor() as $row) {
            $storedVector = json_decode($row->embedding, true);
            if (! is_array($storedVector)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($queryVector, $storedVector);
            unset($storedVector);

            if (count($top) < $topK) {
                $top[] = [
                    'chunk_id' => $row->chunk_id,
                    'document_id' => $row->document_id,
                    'score' => $similarity,
                    'metadata' => json_decode($row->metadata, true) ?? [],
                ];

                if (count($top) === $topK) {
                    usort($top, fn ($a, $b) => $b['score'] <=> $a['score']);
                }

                continue;
            }

            // $top is kept sorted descending once full, so the last entry is the
            // weakest one still in the running.
            if ($similarity <= $top[$topK - 1]['score']) {
                continue;
            }

            $top[$topK - 1] = [
                'chunk_id' => $row->chunk_id,
                'document_id' => $row->document_id,
                'score' => $similarity,
                'metadata' => json_decode($row->metadata, true) ?? [],
            ];

            usort($top, fn ($a, $b) => $b['score'] <=> $a['score']);
        }

        // Fewer rows than topK never triggered the sort above.
        usort($top, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $top;
    }

    // ─── Qdrant Backend ──────────────────────────────────────────

    private function storeInQdrant(
        string $knowledgeBaseId,
        int $documentId,
        int $chunkId,
        array $vector,
        array $metadata = [],
    ): void {
        $qdrantUrl = rtrim(config('services.qdrant.url', ''), '/');
        $qdrantKey = config('services.qdrant.api_key', '');

        if (empty($qdrantUrl)) {
            throw new RuntimeException('Qdrant URL is not configured. Set QDRANT_URL in .env.');
        }

        Http::withHeaders([
            'api-key' => $qdrantKey,
        ])->put("{$qdrantUrl}/collections/{$knowledgeBaseId}/points", [
            'points' => [[
                'id' => $chunkId,
                'vector' => $vector,
                'payload' => array_merge($metadata, [
                    'document_id' => $documentId,
                    'knowledge_base_id' => $knowledgeBaseId,
                ]),
            ]],
        ]);
    }

    private function searchInQdrant(
        string $knowledgeBaseId,
        array $queryVector,
        int $topK = 5,
        ?string $filter = null,
    ): array {
        $qdrantUrl = rtrim(config('services.qdrant.url', ''), '/');
        $qdrantKey = config('services.qdrant.api_key', '');

        if (empty($qdrantUrl)) {
            throw new RuntimeException('Qdrant URL is not configured.');
        }

        $payload = [
            'vector' => $queryVector,
            'limit' => $topK,
            'with_payload' => true,
        ];

        if ($filter) {
            $payload['filter'] = [
                'must' => [['key' => 'type', 'match' => ['value' => $filter]]],
            ];
        }

        $response = Http::withHeaders([
            'api-key' => $qdrantKey,
        ])->post("{$qdrantUrl}/collections/{$knowledgeBaseId}/points/search", $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Qdrant search failed: {$response->body()}");
        }

        $results = [];
        foreach ($response->json('result', []) as $point) {
            $results[] = [
                'chunk_id' => $point['id'],
                'document_id' => $point['payload']['document_id'] ?? null,
                'score' => $point['score'],
                'metadata' => $point['payload'] ?? [],
            ];
        }

        return $results;
    }

    // ─── Pinecone Backend ────────────────────────────────────────

    private function storeInPinecone(
        string $knowledgeBaseId,
        int $documentId,
        int $chunkId,
        array $vector,
        array $metadata = [],
    ): void {
        $pineconeKey = config('services.pinecone.api_key', '');
        $pineconeHost = config('services.pinecone.host', '');

        if (empty($pineconeKey) || empty($pineconeHost)) {
            throw new RuntimeException('Pinecone is not configured.');
        }

        Http::withHeaders([
            'Api-Key' => $pineconeKey,
        ])->post("https://{$pineconeHost}/vectors/upsert", [
            'vectors' => [[
                'id' => (string) $chunkId,
                'values' => $vector,
                'metadata' => array_merge($metadata, [
                    'document_id' => $documentId,
                    'knowledge_base_id' => $knowledgeBaseId,
                ]),
            ]],
            'namespace' => $knowledgeBaseId,
        ]);
    }

    private function searchInPinecone(
        string $knowledgeBaseId,
        array $queryVector,
        int $topK = 5,
        ?string $filter = null,
    ): array {
        $pineconeKey = config('services.pinecone.api_key', '');
        $pineconeHost = config('services.pinecone.host', '');

        if (empty($pineconeKey) || empty($pineconeHost)) {
            throw new RuntimeException('Pinecone is not configured.');
        }

        $payload = [
            'vector' => $queryVector,
            'topK' => $topK,
            'includeMetadata' => true,
            'namespace' => $knowledgeBaseId,
        ];

        if ($filter) {
            $payload['filter'] = ['type' => ['$eq' => $filter]];
        }

        $response = Http::withHeaders([
            'Api-Key' => $pineconeKey,
        ])->post("https://{$pineconeHost}/query", $payload);

        $results = [];
        foreach ($response->json('matches', []) as $match) {
            $results[] = [
                'chunk_id' => (int) $match['id'],
                'document_id' => $match['metadata']['document_id'] ?? null,
                'score' => $match['score'],
                'metadata' => $match['metadata'] ?? [],
            ];
        }

        return $results;
    }

    // ─── Math ────────────────────────────────────────────────────

    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i] ?? 0.0;
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }
}
