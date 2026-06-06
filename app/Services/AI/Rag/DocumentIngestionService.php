<?php

namespace App\Services\AI\Rag;

use App\Services\AI\AiService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * DocumentIngestionService — orchestrates the full document ingestion pipeline.
 *
 * Pipeline: Upload → Extract text → Chunk → Generate embeddings → Store vectors
 */
class DocumentIngestionService
{
    public function __construct(
        private TextExtractionService $extractor,
        private ChunkingService $chunker,
        private VectorStoreService $vectorStore,
        private AiService $aiService,
    ) {}

    /**
     * Ingest a document file into a knowledge base collection.
     */
    public function ingest(UploadedFile|string $file, int $userId, string $collectionId): array
    {
        $tempPath = null;

        if ($file instanceof UploadedFile) {
            if (! $this->extractor->supports($file->getClientOriginalName())) {
                throw new RuntimeException("Unsupported file type: {$file->getClientOriginalExtension()}");
            }

            $tempPath = $file->store('temp/ingest');
            $filePath = storage_path("app/{$tempPath}");
        } else {
            $filePath = $file;
        }

        try {
            // 1. Extract text
            $text = $this->extractor->extract($filePath);

            // 2. Create document record
            $document = \DB::table('knowledge_base_documents')->insertGetId([
                'knowledge_base_id' => $collectionId,
                'user_id' => $userId,
                'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filePath),
                'filesize' => $file instanceof UploadedFile ? $file->getSize() : filesize($filePath),
                'char_count' => mb_strlen($text),
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Chunk text
            $chunks = $this->chunker->chunkForDocument($text, $document);

            // 4. Generate embeddings in batches
            $batchSize = 20;
            $chunkBatches = array_chunk($chunks, $batchSize);

            foreach ($chunkBatches as $batch) {
                $texts = array_column($batch, 'text');
                $embeddingResults = $this->aiService->embedBatch($texts);

                foreach ($batch as $i => $chunk) {
                    // Save chunk to DB
                    $chunkId = \DB::table('knowledge_base_chunks')->insertGetId([
                        'document_id' => $document,
                        'chunk_index' => $chunk['index'],
                        'text' => $chunk['text'],
                        'char_start' => $chunk['start_char'],
                        'char_end' => $chunk['end_char'],
                        'created_at' => now(),
                    ]);

                    // Store vector
                    $this->vectorStore->store(
                        knowledgeBaseId: $collectionId,
                        documentId: $document,
                        chunkId: $chunkId,
                        userId: $userId,
                        vector: $embeddingResults[$i]->vector,
                        metadata: [
                            'document_id' => $document,
                            'chunk_index' => $chunk['index'],
                            'type' => 'document_chunk',
                        ],
                    );
                }
            }

            // 5. Mark document as completed
            \DB::table('knowledge_base_documents')
                ->where('id', $document)
                ->update([
                    'status' => 'completed',
                    'chunk_count' => count($chunks),
                    'updated_at' => now(),
                ]);

            return [
                'document_id' => $document,
                'chunk_count' => count($chunks),
                'char_count' => mb_strlen($text),
                'status' => 'completed',
            ];
        } finally {
            // Cleanup temp file
            if ($tempPath && \Storage::exists($tempPath)) {
                \Storage::delete($tempPath);
            }
        }
    }

    /**
     * Ingest text from a URL.
     */
    public function ingestFromUrl(string $url, int $userId, string $collectionId): array
    {
        $text = $this->extractor->extractFromUrl($url);

        // Save as temp file for the pipeline
        $tempPath = 'temp/ingest_url_'.md5($url).'.txt';
        \Storage::put($tempPath, $text);

        $filePath = storage_path("app/{$tempPath}");

        try {
            return $this->ingest($filePath, $userId, $collectionId);
        } finally {
            if (\Storage::exists($tempPath)) {
                \Storage::delete($tempPath);
            }
        }
    }

    /**
     * Delete a document and its vectors from a knowledge base.
     */
    public function deleteDocument(int $documentId): void
    {
        // Delete chunks
        \DB::table('knowledge_base_chunks')->where('document_id', $documentId)->delete();

        // Delete vectors
        $this->vectorStore->deleteDocumentVectors($documentId);

        // Delete document record
        \DB::table('knowledge_base_documents')->where('id', $documentId)->update([
            'status' => 'deleted',
            'deleted_at' => now(),
        ]);
    }
}
