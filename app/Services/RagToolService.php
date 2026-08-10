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
    /**
     * Disk that holds RAG source documents.
     *
     * Explicitly `local` (storage/app/private), never the default disk. The
     * installer writes FILESYSTEM_DISK=public so media resolves without a
     * storage:link symlink, which makes the default disk's root the WEBROOT —
     * so an unqualified storeAs() published every uploaded document at
     * <app>/storage/rag-uploads/<user>/<hash>, downloadable by anyone with the
     * URL and no session. These files are kept for the life of the session
     * (getSessionFileLocation reads them back for preview), so unlike a scratch
     * copy the exposure did not end when ingestion finished.
     *
     * Uploads written before this change still live on the default disk; the
     * resolver below falls back to it so old sessions keep working.
     */
    private const UPLOAD_DISK = 'local';

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
        $ingestCredits = $this->chargeIngestionCredits($user, $tool, $input, $sourceType);

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

        // Remember what we charged so a failed ingestion can give it back. Recorded
        // after dispatch because the dispatchers own source_meta.
        if ($ingestCredits > 0) {
            $session->refresh();
            $session->update([
                'source_meta' => array_merge($session->source_meta ?? [], [
                    'ingest_credits' => $ingestCredits,
                ]),
            ]);
        }

        return $session;
    }

    /**
     * Give back the up-front ingestion charge when ingestion never produced anything.
     *
     * Mode-correct via User::refundCredits — returns credits to the wallet in metered
     * mode, winds back the daily/monthly counters in quota mode (where the wallet was
     * never drained). Idempotent: a multi-file session has one job per file, and only
     * the first failure should refund.
     */
    public function refundIngestionCredits(RagSession $session): void
    {
        $meta = $session->source_meta ?? [];
        $credits = (float) ($meta['ingest_credits'] ?? 0);

        if ($credits <= 0 || ! empty($meta['ingest_refunded'])) {
            return;
        }

        $user = $session->user;
        if (! $user) {
            return;
        }

        $user->refundCredits($credits, 'RAG ingestion failed: '.$session->tool_slug, [
            'tool_slug' => $session->tool_slug,
            'session_id' => $session->id,
        ]);

        $session->update([
            'source_meta' => array_merge($meta, ['ingest_refunded' => true]),
        ]);
    }

    private function dispatchFileIngestion(RagSession $session, array $input, int $userId, int $kbId): void
    {
        $directory = $this->uploadDirectory($userId);

        // Handle multi-file upload
        if (isset($input['files']) && is_array($input['files'])) {
            $filenames = [];
            $filepaths = [];
            $queued = [];

            foreach ($input['files'] as $file) {
                $path = $file->storeAs($directory, $file->hashName(), self::UPLOAD_DISK);
                $filenames[] = $file->getClientOriginalName();
                $filepaths[] = $path;
                $queued[] = ['path' => $path, 'name' => $file->getClientOriginalName()];
            }

            // Record how many documents this session is waiting on BEFORE dispatching,
            // so a job that finishes first can't declare the session ready while its
            // siblings are still ingesting.
            $session->update([
                'source_meta' => [
                    'filenames' => $filenames,
                    'filepaths' => $filepaths,
                    'file_count' => count($filenames),
                    'expected_documents' => count($queued),
                ],
            ]);

            foreach ($queued as $item) {
                try {
                    IngestDocumentJob::dispatch(
                        $kbId,
                        Storage::disk(self::UPLOAD_DISK)->path($item['path']),
                        $item['name'],
                        $userId,
                        $session->id,
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to dispatch multi-file ingestion for session '.$session->id.': '.$e->getMessage());
                    $session->update([
                        'status' => 'failed',
                        'ingest_error' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                }
            }

            return;
        }

        // Single file upload
        $file = $input['file'] ?? null;
        if (! $file) {
            throw new RuntimeException('No file provided.');
        }

        $path = $file->storeAs($directory, $file->hashName(), self::UPLOAD_DISK);
        $fullPath = Storage::disk(self::UPLOAD_DISK)->path($path);

        $session->update([
            'source_meta' => [
                'filename' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'filepath' => $path,
                'expected_documents' => 1,
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
     *
     * @return float credits actually charged (0 when ingestion is free)
     */
    private function chargeIngestionCredits(User $user, AiTool $tool, array $input, string $sourceType): float
    {
        $credits = 0.0;

        if ($sourceType === 'file') {
            $perMb = (float) settings('rag_ingest_credits_per_mb', 0);
            if ($perMb <= 0) {
                return 0.0;
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
            return 0.0;
        }

        // Mode-correct charge: drains the wallet in metered mode, meters the daily
        // allowance in quota mode (Regular license) where it never fails on balance.
        if (! $user->chargeCredits($credits, "RAG ingestion: {$tool->slug}", [
            'source_type' => $sourceType,
            'tool_slug' => $tool->slug,
        ])) {
            throw new RuntimeException(translate('You do not have enough credits to ingest this content.'));
        }

        return $credits;
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
            $embeddingResult = $this->ai->embedText($message, $provider, $embeddingModel, $user);

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
            $modelName = settings('default_ai_model', config('ai.fallback_model'));
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

                $creditsUsed += $this->chargeRetrievalCredits($user, $session, count($sources));

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
        $modelName = settings('default_ai_model', config('ai.fallback_model'));

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (\Throwable $e) {
            yield ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        try {
            $embeddingModel = settings('rag_embedding_model', '') ?: null;
            $embeddingResult = $this->ai->embedText($aspect, $provider, $embeddingModel, $user);
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

            $creditsUsed = 0.0;

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

                // Compare retrieves chunks like chat does, and must pay for them the
                // same way — it silently didn't.
                $creditsUsed += $this->chargeRetrievalCredits($user, $session, count($allSources));

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
                'input_tokens' => $usageStats['input_tokens'] ?? 0,
                'output_tokens' => $usageStats['output_tokens'] ?? 0,
                'credits_used' => $creditsUsed,
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
    public function summarizeLong(RagSession $session, array $options = [], ?User $user = null): \Generator
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
        $modelName = settings('default_ai_model', config('ai.fallback_model'));

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

            // The map phase makes one LLM call PER BATCH, but the single pre-flight
            // above only estimated one call. Without a re-check, a long document lets
            // an exhausted account (or an install past its daily AI budget) keep
            // spending for the whole map phase and only settle up at the end. Re-check
            // before each call and stop cleanly with what we have.
            try {
                TokenGuard::before($user, null, $modelName);
            } catch (\Throwable $e) {
                Log::warning('RAG summarize stopped mid-map', [
                    'session' => $session->id,
                    'batch' => $batchIdx,
                    'of' => $totalBatches,
                    'reason' => $e->getMessage(),
                ]);

                yield ['type' => 'notice', 'message' => translate('Summarizing stopped early — :done of :total sections were processed.', [
                    'done' => $batchIdx - 1,
                    'total' => $totalBatches,
                ])];

                break;
            }

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

        if (empty($batchSummaries)) {
            yield ['type' => 'error', 'message' => translate('You do not have enough credits to summarize this document.')];

            return;
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
        $modelName = settings('default_ai_model', config('ai.fallback_model'));
        $embeddingModel = settings('rag_embedding_model', '') ?: null;

        try {
            TokenGuard::before($user, null, $modelName);
        } catch (\Throwable $e) {
            yield ['type' => 'error', 'message' => $e->getMessage()];

            return;
        }

        try {
            $embeddingResult = $this->ai->embedText($topic, $provider, $embeddingModel, $user);
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

                $creditsUsed += $this->chargeRetrievalCredits($user, $session, count($sources));

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

    /**
     * Charge for the chunks pulled into context, per `rag_chunks_per_credit`.
     *
     * The setting means "how many retrieved chunks one credit buys", so the cost is
     * proportional. It used to be ceil(chunks / perCredit): with the default top-K of
     * 6 and 50 chunks-per-credit that is ceil(0.12) = 1, so every single query paid a
     * flat extra credit and the setting had no effect at any sane top-K.
     *
     * Mode-correct via chargeCredits — meters the allowance in quota mode (Regular
     * license) instead of draining the un-refillable wallet.
     *
     * @return float credits actually charged
     */
    private function chargeRetrievalCredits(User $user, RagSession $session, int $chunkCount): float
    {
        $chunksPerCredit = (int) settings('rag_chunks_per_credit', 50);

        if ($chunksPerCredit <= 0 || $chunkCount <= 0) {
            return 0.0;
        }

        $credits = round($chunkCount / $chunksPerCredit, 2);

        if ($credits <= 0) {
            return 0.0;
        }

        $charged = $user->chargeCredits($credits, 'RAG context retrieval', [
            'tool_slug' => $session->tool_slug,
            'chunks' => $chunkCount,
        ]);

        return $charged ? $credits : 0.0;
    }

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
     *
     * Only the path this session recorded at upload time is trusted. There used to be
     * a fallback that scanned the whole (shared, flat) rag-uploads directory and
     * matched any file by size + mtime proximity — which could hand a user another
     * user's document, because the caller's ownership check is on the *session*, not
     * on the file it resolves to. A legacy session with no recorded path now simply
     * has no preview.
     */
    public function getSessionFilePath(RagSession $session): ?string
    {
        return $this->getSessionFileLocation($session)['path'] ?? null;
    }

    /**
     * As above, but also names the disk the file is actually on.
     *
     * Uploads now go to UPLOAD_DISK, but sessions created before that change
     * recorded paths on the default disk. Both are checked — same recorded path,
     * two possible homes — so a preview keeps working across the switch. Callers
     * must read through the returned disk rather than the default one, or new
     * uploads 404 and legacy ones resolve against the wrong root.
     *
     * @return array{disk: string, path: string}|null
     */
    public function getSessionFileLocation(RagSession $session): ?array
    {
        $meta = $session->source_meta;
        if (! is_array($meta)) {
            return null;
        }

        $path = $meta['filepath'] ?? null;

        if (! $path || ! is_string($path)) {
            return null;
        }

        // Current location first, then the legacy default disk. Skip the second
        // probe when they are the same disk so this is one lookup on a stock
        // FILESYSTEM_DISK=local install.
        $candidates = array_values(array_unique([self::UPLOAD_DISK, config('filesystems.default')]));

        foreach ($candidates as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return ['disk' => $disk, 'path' => $path];
            }
        }

        return null;
    }

    /**
     * Per-user upload directory. New uploads are namespaced by owner so the
     * directory is no longer a shared flat namespace.
     */
    private function uploadDirectory(int $userId): string
    {
        return "rag-uploads/{$userId}";
    }
}
