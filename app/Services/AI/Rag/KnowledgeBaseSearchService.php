<?php

namespace App\Services\AI\Rag;

use App\DTO\RagResult;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;

/**
 * KnowledgeBaseSearchService — semantic search and QA over knowledge bases.
 */
class KnowledgeBaseSearchService
{
    public function __construct(
        private VectorStoreService $vectorStore,
        private AiService $aiService,
    ) {}

    /**
     * Search a knowledge base and return relevant chunks.
     */
    public function search(
        string $query,
        string $knowledgeBaseId,
        int $topK = 5,
    ): RagResult {
        // 1. Generate embedding for the query
        $embeddingResult = $this->aiService->embedText($query);

        // 2. Search vector store
        $matches = $this->vectorStore->search(
            knowledgeBaseId: $knowledgeBaseId,
            queryVector: $embeddingResult->vector,
            topK: $topK,
        );

        // 3. Fetch chunk texts
        $sources = [];
        $contextChunks = [];

        foreach ($matches as $match) {
            $chunk = \DB::table('knowledge_base_chunks')
                ->where('id', $match['chunk_id'])
                ->first();

            if ($chunk) {
                $contextChunks[] = $chunk->text;
                $sources[] = [
                    'chunk_id' => $chunk->id,
                    'document_id' => $chunk->document_id,
                    'score' => $match['score'],
                    'text' => \Illuminate\Support\Str::limit($chunk->text, 200),
                ];
            }
        }

        // Calculate average similarity as confidence
        $avgScore = ! empty($matches)
            ? array_sum(array_column($matches, 'score')) / count($matches)
            : 0.0;

        return new RagResult(
            answer: implode("\n\n---\n\n", $contextChunks),
            sources: $sources,
            confidence: round($avgScore, 4),
        );
    }

    /**
     * Answer a question using knowledge base context + AI generation.
     */
    public function answer(
        string $query,
        string $knowledgeBaseId,
        string $provider,
        string $model,
        int $topK = 5,
    ): RagResult {
        // 1. Search for relevant context
        $searchResult = $this->search($query, $knowledgeBaseId, $topK);

        if (empty($searchResult->sources)) {
            return new RagResult(
                answer: translate('No relevant information found in the knowledge base.'),
                sources: [],
                confidence: 0.0,
            );
        }

        // 2. Build prompt with context
        $context = $searchResult->answer; // contains chunk texts
        $systemPrompt = "You are a helpful AI assistant. Answer the user's question using ONLY the provided context below. If the answer is not in the context, say so honestly.\n\nContext:\n{$context}";

        // 3. Generate answer
        $adapter = ProviderRegistry::resolve($provider);

        $maxContextChunks = (int) (settings('ai_max_context_chunks') ?: config('ai.rag.max_context_chunks', 10));

        $result = $adapter->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $query],
        ], $model);

        return new RagResult(
            answer: $result['content'],
            sources: $searchResult->sources,
            confidence: $searchResult->confidence,
        );
    }

    /**
     * Get stored knowledge bases accessible to a user.
     */
    public function listKnowledgeBases(int $userId): array
    {
        return \DB::table('knowledge_bases')
            ->where('user_id', $userId)
            ->orWhere('is_public', true)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Create a new knowledge base.
     */
    public function createKnowledgeBase(string $name, int $userId, ?string $description = null, bool $isPublic = false): int
    {
        return \DB::table('knowledge_bases')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'is_public' => $isPublic,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete a knowledge base and all its data.
     */
    public function deleteKnowledgeBase(int $knowledgeBaseId, int $userId): void
    {
        $kb = \DB::table('knowledge_bases')
            ->where('id', $knowledgeBaseId)
            ->where('user_id', $userId)
            ->first();

        if (! $kb) {
            throw new \RuntimeException('Knowledge base not found or access denied.');
        }

        // Get all documents
        $documents = \DB::table('knowledge_base_documents')
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->pluck('id');

        // Delete vectors
        foreach ($documents as $docId) {
            $this->vectorStore->deleteDocumentVectors($docId);
        }

        // Delete chunks
        \DB::table('knowledge_base_chunks')
            ->whereIn('document_id', $documents)
            ->delete();

        // Delete documents
        \DB::table('knowledge_base_documents')
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->delete();

        // Delete knowledge base
        \DB::table('knowledge_bases')
            ->where('id', $knowledgeBaseId)
            ->delete();
    }
}
