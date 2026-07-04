<?php

namespace App\Services;

use App\DTO\CompletionRequest;
use App\Jobs\IngestDocumentJob;
use App\Jobs\IngestUrlJob;
use App\Jobs\IngestYoutubeTranscriptJob;
use App\Models\AiTool;
use App\Models\KnowledgeBase;
use App\Models\RagMessage;
use App\Models\RagSession;
use App\Models\User;
use App\Services\AI\AiErrors;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\Rag\HybridSearchService;
use App\Services\AI\Rag\VectorStoreService;
use App\Services\AI\TokenGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RagToolService
{
    public function __construct(
        private AiService $ai,
    ) {}

    // ── Session Lifecycle ──────────────────────────────────────────

    /**
     * Create a session + ephemeral KB, dispatch the appropriate ingestion job.
     */
    public function createSession(User $user, AiTool $tool, array $input): RagSession
    {
        $fields = $tool->fields ?? [];
        $sourceType = $fields['source_type'] ?? 'file';

        // Charge ingestion credits up front (admin-configurable, defaults to free)
        $this->chargeIngestionCredits($user, $tool, $input, $sourceType);

        // Create ephemeral knowledge base
        $kb = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => $input['title'] ?? ('RAG: '.$tool->name.' — '.now()->format('M d, H:i')),
            'is_ephemeral' => true,
            'source_tool' => $tool->slug,
            'expires_at' => now()->addDays((int) settings('rag_ephemeral_retention_days', 7)),
        ]);

        // Create session
        $session = RagSession::create([
            'user_id' => $user->id,
            'tool_slug' => $tool->slug,
            'knowledge_base_id' => $kb->id,
            'title' => $input['title'] ?? null,
            'status' => 'ingesting',
        ]);

        // Dispatch ingestion based on source type
        match ($sourceType) {
            'file' => $this->dispatchFileIngestion($session, $input, $user->id, $kb->id),
            'url' => $this->dispatchUrlIngestion($session, $input, $user->id, $kb->id),
            'youtube' => $this->dispatchYoutubeIngestion($session, $input, $user->id, $kb->id),
            'collection' => $this->bindCollection($session, $input, $user),
            default => throw new RuntimeException("Unsupported source type: {$sourceType}"),
        };

        return $session;
    }

    private function dispatchFileIngestion(RagSession $session, array $input, int $userId, int $kbId): void
    {
        // Handle multi-file upload
        if (isset($input['files']) && is_array($input['files'])) {
            $filenames = [];
            $filepaths = [];
            foreach ($input['files'] as $file) {
                $path = $file->storeAs('rag-uploads', $file->hashName());
                $fullPath = Storage::path($path);
                $filenames[] = $file->getClientOriginalName();
                $filepaths[] = $path;

                try {
                    IngestDocumentJob::dispatch(
                        $kbId,
                        $fullPath,
                        $file->getClientOriginalName(),
                        $userId,
                        $session->id,
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to dispatch multi-file ingestion for session '.$session->id.': '.$e->getMessage());
                }
            }

            $session->update([
                'source_meta' => [
                    'filenames' => $filenames,
                    'filepaths' => $filepaths,
                    'file_count' => count($filenames),
                ],
            ]);

            return;
        }

        // Single file upload
        $file = $input['file'] ?? null;
        if (! $file) {
            throw new RuntimeException('No file provided.');
        }

        $path = $file->storeAs('rag-uploads', $file->hashName());
        $fullPath = Storage::path($path);

        $session->update([
            'source_meta' => [
                'filename' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'filepath' => $path,
            ],
        ]);

        try {
            IngestDocumentJob::dispatch(
                $kbId,
                $fullPath,
                $file->getClientOriginalName(),
                $userId,
                $session->id,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch file ingestion for session '.$session->id.': '.$e->getMessage());
            $session->update([
                'status' => 'failed',
                'ingest_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    private function dispatchUrlIngestion(RagSession $session, array $input, int $userId, int $kbId): void
    {
        $url = $input['url'] ?? null;
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('A valid URL is required.');
        }

        $session->update([
            'source_meta' => ['url' => $url],
        ]);

        try {
            IngestUrlJob::dispatch($kbId, $url, $userId, $session->id);
        } catch (\Throwable $e) {
            Log::error('URL ingestion failed for session '.$session->id.': '.$e->getMessage());
            $session->update([
                'status' => 'failed',
                'ingest_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    private function dispatchYoutubeIngestion(RagSession $session, array $input, int $userId, int $kbId): void
    {
        $url = $input['url'] ?? null;
        if (! $url) {
            throw new RuntimeException('A YouTube URL is required.');
        }

        $session->update([
            'source_meta' => ['video_url' => $url],
        ]);

        try {
            IngestYoutubeTranscriptJob::dispatch($kbId, $url, $userId, $session->id);
        } catch (\Throwable $e) {
            Log::error('YouTube ingestion failed for session '.$session->id.': '.$e->getMessage());
            $session->update([
                'status' => 'failed',
                'ingest_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    private function bindCollection(RagSession $session, array $input, User $user): void
    {
        $kbId = $input['knowledge_base_id'] ?? null;
        if (! $kbId) {
            throw new RuntimeException('A knowledge base collection must be selected.');
        }

        $kb = KnowledgeBase::where('id', $kbId)
            ->where('user_id', $user->id)
            ->where('is_ephemeral', false)
            ->first();

        if (! $kb) {
            throw new RuntimeException('Knowledge base not found.');
        }

        $session->update([
            'knowledge_base_id' => $kb->id,
            'status' => 'ready',
            'source_meta' => ['collection_name' => $kb->name],
        ]);
    }

    /**
     * Charge credits for ingesting new content (admin-configurable, default 0 = free).
     *
     * File sources are billed per MB (rag_ingest_credits_per_mb); URL and
     * YouTube sources are billed a flat amount (rag_ingest_credits_url).
     * Re-using an existing collection is free — it was billed at ingest time.
     */
    private function chargeIngestionCredits(User $user, AiTool $tool, array $input, string $sourceType): void
    {
        $credits = 0.0;

        if ($sourceType === 'file') {
            $perMb = (float) settings('rag_ingest_credits_per_mb', 0);
            if ($perMb <= 0) {
                return;
            }

            $bytes = 0;
            if (isset($input['files']) && is_array($input['files'])) {
                foreach ($input['files'] as $file) {
                    $bytes += (int) $file->getSize();
                }
            } elseif (isset($input['file'])) {
                $bytes = (int) $input['file']->getSize();
            }

            $credits = round(($bytes / 1_048_576) * $perMb, 2);
        } elseif (in_array($sourceType, ['url', 'youtube'], true)) {
            $credits = (float) settings('rag_ingest_credits_url', 0);
        }

        if ($credits <= 0) {
            return;
        }

        if (! $user->deductCredits($credits, "RAG ingestion: {$tool->slug}", [
            'source_type' => $sourceType,
            'tool_slug' => $tool->slug,
        ])) {
            throw new RuntimeException(translate('You do not have enough credits to ingest this content.'));
        }
    }

    /**
     * Poll-able status for ingestion progress UI.
     */
    public function sessionStatus(RagSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'title' => $session->title,
            'source_meta' => $session->source_meta,
            'ingest_error' => $session->ingest_error,
            'ingest_stage' => $session->ingest_stage,
            'saved_to_kb' => $session->saved_to_kb,
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }

    /**
     * Promote ephemeral KB → permanent.
     */
    public function saveToKnowledgeBase(RagSession $session, string $name): KnowledgeBase
    {
        $kb = $session->knowledgeBase;
        if (! $kb) {
            throw new RuntimeException('Knowledge base not found for this session.');
        }

        $kb->update([
            'name' => $name,
            'is_ephemeral' => false,
            'expires_at' => null,
        ]);

        $session->update(['saved_to_kb' => true]);

        return $kb;
    }

    /**
     * Delete session and its ephemeral KB.
     */
    public function deleteSession(RagSession $session): void
    {
        $kb = $session->knowledgeBase;

        // Delete vectors via existing service
        if ($kb && $kb->is_ephemeral) {
            $documents = DB::table('knowledge_base_documents')
                ->where('knowledge_base_id', $kb->id)
                ->pluck('id');

            $vectorStore = app(VectorStoreService::class);
            foreach ($documents as $docId) {
                $vectorStore->deleteDocumentVectors($docId);
            }

            DB::table('knowledge_base_chunks')
                ->whereIn('document_id', $documents)
                ->delete();

            DB::table('knowledge_base_documents')
                ->where('knowledge_base_id', $kb->id)
                ->delete();

            $kb->delete();
        }

        $session->messages()->delete();
        $session->delete();
    }

    /**
     * Create a share token for read-only access.
     */
    public function createShareToken(RagSession $session): string
    {
        $token = (string) Str::ulid();
        $session->update(['share_token' => $token]);
        return $token;
    }

    // ── Query (Streaming) ──────────────────────────────────────────

    /**
     * Main RAG chat streaming turn.
     */
    public function streamQuery(RagSession $session, string $message, User $user): \Generator
    {
        if ($session->status !== 'ready') {
            yield ['type' => 'error', 'message' => 'Session is not ready yet. Please wait for ingestion to complete.'];
            return;
        }

        $kb = $session->knowledgeBase;
        if (! $kb) {
            yield ['type' => 'error', 'message' => 'Knowledge base not found.'];
            return;
        }

        $topK = (int) settings('rag_top_k', 6);
        $embeddingModel = settings('rag_embedding_model', '') ?: null;
        $provider = settings('default_ai_provider', 'openai');

        try {
            // 1. Embed query
            $embeddingResult = $this->ai->embedText($message, $provider, $embeddingModel);

            // 2. Retrieve relevant chunks (hybrid search if enabled)
            $hybridSearch = app(HybridSearchService::class);
            $matches = $hybridSearch->search(
                (string) $kb->id,
                $embeddingResult->vector,
                $message,
                $topK,
            );

            // 3. Fetch chunk texts and build sources
            $contextChunks = [];
            $sources = [];

            foreach ($matches as $i => $match) {
                $chunk = DB::table('knowledge_base_chunks')
                    ->where('id', $match['chunk_id'])
                    ->first();

                if ($chunk) {
                    $doc = DB::table('knowledge_base_documents')
                        ->where('id', $chunk->document_id)
                        ->first();

                    $displayIdx = $i + 1;
                    $contextChunks[] = "[{$displayIdx}] {$chunk->text}";
                    $sources[] = [
                        'doc' => $doc->filename ?? 'Unknown',
                        'chunk' => $chunk->chunk_index,
                        'score' => round($match['score'], 4),
                        'snippet' => Str::limit($chunk->text, 200),
                    ];
                }
            }

            // 4. Guard: if no relevant chunks found, warn user
            if (empty($contextChunks)) {
                yield ['type' => 'sources', 'items' => []];
                yield ['type' => 'token', 'content' => 'I could not find any relevant content in the document for your question. Please try rephrasing your question, or ask about a topic that is covered in the uploaded document.'];
                RagMessage::create([
                    'session_id' => $session->id,
                    'role' => 'user',
                    'content' => $message,
                ]);
                RagMessage::create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'content' => 'I could not find any relevant content in the document for your question. Please try rephrasing your question, or ask about a topic that is covered in the uploaded document.',
                ]);
                yield ['type' => 'done'];
                return;
            }

            // 5. Emit sources event
            yield ['type' => 'sources', 'items' => $sources];

            // 6. Build grounded prompt
            $contextText = implode("\n\n", $contextChunks);
            $systemPrompt = settings('rag_system_prompt', $this->defaultGroundingPrompt());

            // Inject context into system prompt — check for placeholder BEFORE replacement
            if (str_contains($systemPrompt, '{context}')) {
                $systemPrompt = str_replace('{context}', $contextText, $systemPrompt);
            } else {
                $systemPrompt .= "\n\nContext:\n{$contextText}";
            }

            // 6. Load chat history (last N turns)
            $history = $this->buildChatHistory($session);
            $messages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $history,
                [['role' => 'user', 'content' => $message]],
            );

            // 7. Pre-flight credit check
            $modelName = settings('default_ai_model', 'gpt-4o-mini');
            try {
                TokenGuard::before($user, null, $modelName);
            } catch (\Throwable $e) {
                yield ['type' => 'error', 'message' => $e->getMessage()];
                return;
            }

            // 8. Stream completion
            $adapter = ProviderRegistry::resolve($provider);
            $fullContent = '';
            $usageStats = null;

            foreach ($adapter->streamChatCompletion($messages, $modelName) as $chunk) {
                if (is_string($chunk)) {
                    $fullContent .= $chunk;
                    yield ['type' => 'token', 'content' => $chunk];
                } elseif (is_array($chunk)) {
                    if (isset($chunk['reasoning_start']) || isset($chunk['reasoning_end']) || isset($chunk['reasoning'])) {
                        yield $chunk;
                    } else {
                        $usageStats = $chunk;
                    }
                }
            }

            // 9. Deduct credits & log usage
            $creditsUsed = 0;
            $inputTokens = 0;
            $outputTokens = 0;

            if ($usageStats) {
                $creditsUsed = TokenGuard::after(
                    $user,
                    $usageStats['input_tokens'],
                    $usageStats['output_tokens'],
                    $usageStats['model'] ?? $modelName,
                    $provider,
                    'rag',
                    ['tool_slug' => $session->tool_slug],
                );

                // Add chunk-based credits — through the ledger so the balance
                // check, transaction record and usage counters all apply.
                $chunksPerCredit = (int) settings('rag_chunks_per_credit', 50);
                if ($chunksPerCredit > 0 && count($sources) > 0) {
                    $chunkCredits = (float) ceil(count($sources) / $chunksPerCredit);
                    if ($user->deductCredits($chunkCredits, 'RAG context retrieval', [
                        'tool_slug' => $session->tool_slug,
                        'chunks' => count($sources),
                    ])) {
                        $creditsUsed += $chunkCredits;
                    }
                }

                $inputTokens = $usageStats['input_tokens'] ?? 0;
                $outputTokens = $usageStats['output_tokens'] ?? 0;

                yield [
                    'type' => 'usage',
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'credits' => $creditsUsed,
                    'model' => $usageStats['model'] ?? $modelName,
                ];
            }

            // 10. Persist messages
            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'content' => $message,
            ]);

            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $fullContent,
                'sources' => $sources,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'credits_used' => $creditsUsed,
            ]);

            yield ['type' => 'done'];
        } catch (\Throwable $e) {
            Log::error('RAG query failed', ['session' => $session->id, 'error' => $e->getMessage()]);
            yield ['type' => 'error', 'message' => AiErrors::sanitize($e->getMessage())];
        }
    }

    /**
     * Multi-document comparator.
     */
    public function streamCompare(RagSession $session, string $aspect, User $user): \Generator
    {
        if ($session->status !== 'ready') {
            yield ['type' => 'error', 'message' => 'Session is not ready.'];
            return;
        }

        $kb = $session->knowledgeBase;
        if (! $kb) {
            yield ['type' => 'error', 'message' => 'Knowledge base not found.'];
            return;
        }

        $documents = DB::table('knowledge_base_documents')
            ->where('knowledge_base_id', $kb->id)
            ->where('status', 'completed')
            ->get();

        if ($documents->count() < 2) {
            yield ['type' => 'error', 'message' => 'At least 2 documents are required for comparison.'];
            return;
        }

        $labels = ['A', 'B', 'C'];
        $topK = (int) settings('rag_top_k', 6);
        $provider = settings('default_ai_provider', 'openai');
        $modelName = settings('default_ai_model', 'gpt-4o-mini');

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (\Throwable $e) {
            yield ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        try {
            $embeddingModel = settings('rag_embedding_model', '') ?: null;
            $embeddingResult = $this->ai->embedText($aspect, $provider, $embeddingModel);
            $hybridSearch = app(HybridSearchService::class);

            // One search sized for all documents, grouped per document afterwards
            $matches = $hybridSearch->search(
                (string) $kb->id,
                $embeddingResult->vector,
                $aspect,
                $topK * max(1, $documents->count()),
            );

            $matchesByDocument = [];
            foreach ($matches as $match) {
                $chunk = DB::table('knowledge_base_chunks')
                    ->where('id', $match['chunk_id'])
                    ->first();

                if ($chunk) {
                    $matchesByDocument[(int) $chunk->document_id][] = ['match' => $match, 'chunk' => $chunk];
                }
            }

            $allSources = [];
            $contextBlocks = [];

            foreach ($documents as $idx => $doc) {
                $label = $labels[$idx] ?? chr(68 + $idx);
                $docMatches = array_slice($matchesByDocument[(int) $doc->id] ?? [], 0, $topK);

                foreach ($docMatches as $entry) {
                    $chunk = $entry['chunk'];
                    $contextBlocks[] = "[{$label}] {$chunk->text}";
                    $allSources[] = [
                        'doc' => $doc->filename,
                        'doc_label' => $label,
                        'chunk' => $chunk->chunk_index,
                        'score' => round($entry['match']['score'], 4),
                        'snippet' => Str::limit($chunk->text, 200),
                    ];
                }
            }

            yield ['type' => 'sources', 'items' => $allSources];

            $systemPrompt = "You are comparing documents. Cite sources using labels like [A], [B], [C]. Compare the following aspect: {$aspect}. Use ONLY the provided context.\n\n".implode("\n\n", $contextBlocks);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Compare these documents regarding: {$aspect}"],
            ];

            $adapter = ProviderRegistry::resolve($provider);

            $fullContent = '';
            $usageStats = null;
            foreach ($adapter->streamChatCompletion($messages, $modelName) as $chunk) {
                if (is_string($chunk)) {
                    $fullContent .= $chunk;
                    yield ['type' => 'token', 'content' => $chunk];
                } elseif (is_array($chunk) && ! isset($chunk['reasoning']) && ! isset($chunk['reasoning_start']) && ! isset($chunk['reasoning_end'])) {
                    $usageStats = $chunk;
                }
            }

            if ($usageStats) {
                $creditsUsed = TokenGuard::after(
                    $user,
                    $usageStats['input_tokens'] ?? 0,
                    $usageStats['output_tokens'] ?? 0,
                    $usageStats['model'] ?? $modelName,
                    $provider,
                    'rag',
                    ['tool_slug' => $session->tool_slug, 'mode' => 'compare'],
                );

                yield ['type' => 'usage',
                    'input_tokens' => $usageStats['input_tokens'] ?? 0,
                    'output_tokens' => $usageStats['output_tokens'] ?? 0,
                    'credits' => $creditsUsed,
                    'model' => $usageStats['model'] ?? $modelName,
                ];
            }

            // Persist
            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'content' => "Compare regarding: {$aspect}",
            ]);

            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $fullContent,
                'sources' => $allSources,
            ]);

            yield ['type' => 'done'];
        } catch (\Throwable $e) {
            Log::error('RAG compare failed', ['session' => $session->id, 'error' => $e->getMessage()]);
            yield ['type' => 'error', 'message' => AiErrors::sanitize($e->getMessage())];
        }
    }

    /**
     * Long document summarizer — map-reduce.
     */
    public function summarizeLong(RagSession $session, array $options = [], User $user = null): \Generator
    {
        if ($session->status !== 'ready') {
            yield ['type' => 'error', 'message' => 'Session is not ready.'];
            return;
        }

        $kb = $session->knowledgeBase;
        if (! $kb) {
            yield ['type' => 'error', 'message' => 'Knowledge base not found.'];
            return;
        }

        $provider = settings('default_ai_provider', 'openai');
        $modelName = settings('default_ai_model', 'gpt-4o-mini');

        // Get all chunks, sorted by document+index
        $chunks = DB::table('knowledge_base_chunks')
            ->join('knowledge_base_documents', 'knowledge_base_chunks.document_id', '=', 'knowledge_base_documents.id')
            ->where('knowledge_base_documents.knowledge_base_id', $kb->id)
            ->where('knowledge_base_documents.status', 'completed')
            ->whereNull('knowledge_base_documents.deleted_at')
            ->orderBy('knowledge_base_chunks.document_id')
            ->orderBy('knowledge_base_chunks.chunk_index')
            ->select('knowledge_base_chunks.*')
            ->get();

        if ($chunks->isEmpty()) {
            yield ['type' => 'error', 'message' => 'No content found in this document.'];
            return;
        }

        $length = $options['length'] ?? 'medium';

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (\Throwable $e) {
            yield ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        $adapter = ProviderRegistry::resolve($provider);

        // Phase 1: Summarize chunks in batches (map)
        $batchSummaries = [];
        $batchSize = (int) settings('rag_map_reduce_batch_size', 10);
        $totalBatches = (int) ceil($chunks->count() / $batchSize);
        $batchIdx = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        yield ['type' => 'progress', 'stage' => 'mapping', 'total' => $totalBatches, 'current' => 0];

        foreach ($chunks->chunk($batchSize) as $batch) {
            $batchText = $batch->pluck('text')->implode("\n\n");
            $batchIdx++;

            $prompt = "Summarize the following text concisely, preserving key facts, names, numbers, and dates:\n\n{$batchText}\n\nSummary:";

            try {
                $result = $adapter->chatCompletion([
                    ['role' => 'user', 'content' => $prompt],
                ], $modelName);

                $batchSummaries[] = $result['content'];
                $totalInputTokens += (int) ($result['input_tokens'] ?? 0);
                $totalOutputTokens += (int) ($result['output_tokens'] ?? 0);
            } catch (\Throwable $e) {
                // If a batch fails, include the raw text as fallback
                $batchSummaries[] = "Batch {$batchIdx}: ".Str::limit($batchText, 500);
            }

            yield ['type' => 'progress', 'stage' => 'mapping', 'total' => $totalBatches, 'current' => $batchIdx];
        }

        yield ['type' => 'sources', 'items' => [
            ['doc' => $session->source_meta['filename'] ?? 'Document', 'chunks' => $chunks->count()],
        ]];

        // Phase 2: Merge summaries (reduce)
        yield ['type' => 'progress', 'stage' => 'reducing'];

        $combinedSummary = implode("\n\n", $batchSummaries);
        $lengthInstructions = match ($length) {
            'short' => 'Keep it under 200 words.',
            'long' => 'Be comprehensive and detailed.',
            default => 'Aim for 3-5 paragraphs.',
        };

        $finalPrompt = "The following is a set of summaries of sections of a document. Combine them into a single coherent summary. {$lengthInstructions}\n\nSection summaries:\n{$combinedSummary}\n\nFinal summary:";

        try {
            $fullContent = '';
            $usageStats = null;
            foreach ($adapter->streamChatCompletion([
                ['role' => 'user', 'content' => $finalPrompt],
            ], $modelName) as $chunk) {
                if (is_string($chunk)) {
                    $fullContent .= $chunk;
                    yield ['type' => 'token', 'content' => $chunk];
                } elseif (is_array($chunk) && ! isset($chunk['reasoning']) && ! isset($chunk['reasoning_start']) && ! isset($chunk['reasoning_end'])) {
                    $usageStats = $chunk;
                }
            }

            $totalInputTokens += (int) ($usageStats['input_tokens'] ?? 0);
            $totalOutputTokens += (int) ($usageStats['output_tokens'] ?? 0);

            $creditsUsed = TokenGuard::after(
                $user,
                $totalInputTokens,
                $totalOutputTokens,
                $usageStats['model'] ?? $modelName,
                $provider,
                'rag',
                ['tool_slug' => $session->tool_slug, 'mode' => 'summarize', 'batches' => $totalBatches],
            );

            yield ['type' => 'usage',
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
                'credits' => $creditsUsed,
                'model' => $usageStats['model'] ?? $modelName,
            ];

            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $fullContent !== '' ? $fullContent : 'Summary generated.',
                'sources' => [['doc' => $session->source_meta['filename'] ?? 'Document']],
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
                'credits_used' => $creditsUsed,
            ]);
        } catch (\Throwable $e) {
            Log::error('RAG summarize failed', ['session' => $session->id, 'error' => $e->getMessage()]);

            // Bill the map-phase tokens that were already consumed
            TokenGuard::recordFailure($user, $provider, $modelName, 'rag', $totalInputTokens, $totalOutputTokens, [
                'tool_slug' => $session->tool_slug,
                'mode' => 'summarize',
                'error' => $e->getMessage(),
            ]);

            yield ['type' => 'error', 'message' => AiErrors::sanitize($e->getMessage())];

            return;
        }

        yield ['type' => 'done'];
    }

    /**
     * KB Writer — retrieval-grounded long-form generation.
     */
    public function streamKbWrite(RagSession $session, array $brief, User $user): \Generator
    {
        if ($session->status !== 'ready') {
            yield ['type' => 'error', 'message' => 'Session is not ready.'];
            return;
        }

        $topic = $brief['topic'] ?? '';
        $contentType = $brief['content_type'] ?? 'article';
        $tone = $brief['tone'] ?? 'professional';
        $length = $brief['length'] ?? 'medium';

        if (! $topic) {
            yield ['type' => 'error', 'message' => 'A topic is required.'];
            return;
        }

        $kb = $session->knowledgeBase;
        if (! $kb) {
            yield ['type' => 'error', 'message' => 'Knowledge base not found.'];

            return;
        }

        $topK = (int) settings('rag_top_k', 6);
        $provider = settings('default_ai_provider', 'openai');
        $modelName = settings('default_ai_model', 'gpt-4o-mini');
        $embeddingModel = settings('rag_embedding_model', '') ?: null;

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (\Throwable $e) {
            yield ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        try {
            $embeddingResult = $this->ai->embedText($topic, $provider, $embeddingModel);
            $hybridSearch = app(HybridSearchService::class);
            $matches = $hybridSearch->search((string) $kb->id, $embeddingResult->vector, $topic, $topK);

            $contextChunks = [];
            $sources = [];

            foreach ($matches as $i => $match) {
                $chunk = DB::table('knowledge_base_chunks')->where('id', $match['chunk_id'])->first();
                if ($chunk) {
                    $doc = DB::table('knowledge_base_documents')->where('id', $chunk->document_id)->first();
                    $displayIdx = $i + 1;
                    $contextChunks[] = "[{$displayIdx}] {$chunk->text}";
                    $sources[] = [
                        'doc' => $doc->filename ?? 'Unknown',
                        'chunk' => $chunk->chunk_index,
                        'score' => round($match['score'], 4),
                        'snippet' => Str::limit($chunk->text, 200),
                    ];
                }
            }

            yield ['type' => 'sources', 'items' => $sources];

            $context = implode("\n\n", $contextChunks);

            $systemPrompt = "You are a professional {$contentType} writer. Write about the following topic using ONLY the provided context from the knowledge base. If something is not found in the context, explicitly say 'This information was not found in your knowledge base.' Do not hallucinate or invent facts.\n\nContext:\n{$context}\n\nWrite in a {$tone} tone. Length: {$length}.";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Write a {$contentType} about: {$topic}"],
            ];

            $adapter = ProviderRegistry::resolve($provider);

            $fullContent = '';
            $usageStats = null;
            foreach ($adapter->streamChatCompletion($messages, $modelName) as $chunk) {
                if (is_string($chunk)) {
                    $fullContent .= $chunk;
                    yield ['type' => 'token', 'content' => $chunk];
                } elseif (is_array($chunk) && ! isset($chunk['reasoning']) && ! isset($chunk['reasoning_start']) && ! isset($chunk['reasoning_end'])) {
                    $usageStats = $chunk;
                }
            }

            $creditsUsed = 0.0;
            if ($usageStats) {
                $creditsUsed = TokenGuard::after(
                    $user,
                    $usageStats['input_tokens'] ?? 0,
                    $usageStats['output_tokens'] ?? 0,
                    $usageStats['model'] ?? $modelName,
                    $provider,
                    'rag',
                    ['tool_slug' => $session->tool_slug, 'mode' => 'kb_write'],
                );

                yield ['type' => 'usage',
                    'input_tokens' => $usageStats['input_tokens'] ?? 0,
                    'output_tokens' => $usageStats['output_tokens'] ?? 0,
                    'credits' => $creditsUsed,
                    'model' => $usageStats['model'] ?? $modelName,
                ];
            }

            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'content' => "Write about: {$topic}",
            ]);

            RagMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $fullContent,
                'sources' => $sources,
                'input_tokens' => $usageStats['input_tokens'] ?? 0,
                'output_tokens' => $usageStats['output_tokens'] ?? 0,
                'credits_used' => $creditsUsed,
            ]);

            yield ['type' => 'done'];
        } catch (\Throwable $e) {
            Log::error('RAG kb_write failed', ['session' => $session->id, 'error' => $e->getMessage()]);
            yield ['type' => 'error', 'message' => AiErrors::sanitize($e->getMessage())];
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function buildChatHistory(RagSession $session, int $turns = 5): array
    {
        $messages = $session->messages()
            ->orderByDesc('created_at')
            ->limit($turns * 2)
            ->get()
            ->reverse();

        $history = [];
        foreach ($messages as $msg) {
            $history[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        return $history;
    }

    private function defaultGroundingPrompt(): string
    {
        return 'You are a helpful document assistant. Below is content extracted from the user\'s document. Answer questions using ONLY this content. If something is not covered, explain what the document IS about instead. Never invent facts.

{context}

Cited as [1], [2], etc.';
    }

    /**
     * Resolve the storage file path for a session's document.
     */
    public function getSessionFilePath(RagSession $session): ?string
    {
        $meta = $session->source_meta;
        if (! is_array($meta)) {
            return null;
        }

        // 1. Check if filepath is explicitly saved
        $path = $meta['filepath'] ?? null;
        if ($path && \Illuminate\Support\Facades\Storage::exists($path)) {
            return $path;
        }

        // 2. Fallback: match by filesize and creation time proximity (for old files)
        $filesize = $meta['filesize'] ?? null;
        if (! $filesize) {
            return null;
        }

        $allFiles = \Illuminate\Support\Facades\Storage::files('rag-uploads');
        $bestMatch = null;
        $minDiff = null;
        $sessionTime = $session->created_at?->timestamp ?? time();

        foreach ($allFiles as $file) {
            if (\Illuminate\Support\Facades\Storage::size($file) === (int) $filesize) {
                $fileTime = \Illuminate\Support\Facades\Storage::lastModified($file);
                $diff = abs($fileTime - $sessionTime);
                if ($minDiff === null || $diff < $minDiff) {
                    $minDiff = $diff;
                    $bestMatch = $file;
                }
            }
        }

        // If matched within a 1-day window, assume it's the correct file
        if ($bestMatch && $minDiff < 86400) {
            return $bestMatch;
        }

        return null;
    }
}
