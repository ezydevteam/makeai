<?php

namespace App\Services\AI\Rag;

use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DocumentIngestionService — orchestrates the full document ingestion pipeline.
 *
 * Pipeline: Upload → Extract text → Chunk → Generate embeddings → Store vectors
 */
class DocumentIngestionService
{
    /**
     * Scratch space for files being ingested.
     *
     * Pinned to `local` (storage/app/private) rather than the default disk on
     * purpose. filesystems.default ships as `public`, whose root IS the webroot
     * (public/storage) — so writing scratch copies there published every document
     * a user ingested at a guessable-shaped URL, unauthenticated, for as long as
     * the pipeline ran. `local` is outside the document root, and unlike a remote
     * default disk it can answer path(), which the extractors need to read the
     * file off the filesystem.
     */
    private const TEMP_DISK = 'local';

    public function __construct(
        private TextExtractionService $extractor,
        private SemanticChunkingService $chunker,
        private VectorStoreService $vectorStore,
        private AiService $aiService,
    ) {}

    /**
     * Ingest a document file into a knowledge base collection.
     */
    public function ingest(UploadedFile|string $file, int $userId, string $collectionId, ?\Closure $onStageChange = null, ?string $filename = null): array
    {
        $tempPath = null;
        $notify = $onStageChange ?? fn (string $stage) => null;

        if ($file instanceof UploadedFile) {
            if (! $this->extractor->supports($file->getClientOriginalName())) {
                throw new RuntimeException("Unsupported file type: {$file->getClientOriginalExtension()}");
            }

            $tempPath = $file->store('temp/ingest', self::TEMP_DISK);

            if ($tempPath === false) {
                throw new RuntimeException('Could not write the upload to temporary storage. Check that storage/app/private is writable.');
            }

            $filePath = Storage::disk(self::TEMP_DISK)->path($tempPath);
        } else {
            $filePath = $file;
        }

        // Owner of everything this pipeline generates — embeddings are billed to them.
        $owner = User::find($userId);

        try {
            // 1. Extract text
            $notify('extracting');
            $text = $this->extractor->extract($filePath);

            // 2. Create document record
            $document = \DB::table('knowledge_base_documents')->insertGetId([
                'knowledge_base_id' => $collectionId,
                'user_id' => $userId,
                'filename' => $filename ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($filePath)),
                'filesize' => $file instanceof UploadedFile ? $file->getSize() : filesize($filePath),
                'char_count' => mb_strlen($text),
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Chunk text (semantic mode embeds sentences — bill those to the owner)
            $notify('chunking');
            $chunks = $this->chunker->billTo($owner)->chunkForDocument($text, $document);

            if (empty($chunks)) {
                throw new RuntimeException('No readable text could be extracted from this document. The file may be image-only, encrypted, or in an unsupported format.');
            }

            // 4. Generate embeddings in batches
            $notify('embedding');
            $batchSize = 20;
            $chunkBatches = array_chunk($chunks, $batchSize);

            // Embedding a document is the bulk of its provider cost — bill it to the
            // owner. TokenGuard::before() inside embedBatch() also re-checks the
            // balance and the global budget between batches, so a long document can't
            // run away on an exhausted account.
            foreach ($chunkBatches as $batch) {
                $texts = array_column($batch, 'text');
                $embeddingResults = $this->aiService->embedBatch($texts, null, $owner);

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
        } catch (\Throwable $e) {
            // Mark document as failed if any step errors
            if (isset($document)) {
                \DB::table('knowledge_base_documents')
                    ->where('id', $document)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);
            }
            throw $e;
        } finally {
            // Cleanup temp file. Must name the same disk it was written to — the
            // old default-disk delete() silently no-opped whenever the two
            // differed, leaving every ingested document behind under the webroot.
            if ($tempPath && Storage::disk(self::TEMP_DISK)->exists($tempPath)) {
                Storage::disk(self::TEMP_DISK)->delete($tempPath);
            }
        }
    }

    /**
     * Ingest text from a URL.
     */
    public function ingestFromUrl(string $url, int $userId, string $collectionId, ?\Closure $onStageChange = null): array
    {
        $notify = $onStageChange ?? fn (string $stage) => null;

        $notify('scraping');
        $text = $this->extractor->extractFromUrl($url);

        // Save as temp file for the pipeline. Random name, not md5($url): on the
        // old public default disk the path was both web-served and derivable by
        // anyone who knew which URL had been scraped. Random keeps the name from
        // being a lookup key even if this ever lands somewhere reachable again,
        // and stops two concurrent ingests of the same URL sharing one file —
        // whichever finished first deleted it out from under the other.
        $tempPath = 'temp/ingest_url_'.Str::random(40).'.txt';
        Storage::disk(self::TEMP_DISK)->put($tempPath, $text);

        $filePath = Storage::disk(self::TEMP_DISK)->path($tempPath);

        // Derive a readable name from the URL
        $parsedUrl = parse_url($url);
        $readableName = ($parsedUrl['host'] ?? 'website')
            .($parsedUrl['path'] ?? '');
        $readableName = mb_substr(preg_replace('/[^a-zA-Z0-9._\-\/]/', '', $readableName), 0, 100);

        try {
            // Override the basename by creating the document record with the URL-derived name
            $result = $this->ingest($filePath, $userId, $collectionId, $onStageChange);

            // Update the document record with a readable filename
            if (isset($result['document_id'])) {
                \DB::table('knowledge_base_documents')
                    ->where('id', $result['document_id'])
                    ->update(['filename' => $readableName ?: $url]);
            }

            return $result;
        } finally {
            if (Storage::disk(self::TEMP_DISK)->exists($tempPath)) {
                Storage::disk(self::TEMP_DISK)->delete($tempPath);
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
