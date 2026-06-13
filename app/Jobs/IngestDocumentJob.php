<?php

namespace App\Jobs;

use App\Events\RagIngestProgressEvent;
use App\Models\RagSession;
use App\Services\AI\Rag\DocumentIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly int $knowledgeBaseId,
        private readonly string $filePath,
        private readonly string $filename,
        private readonly int $userId,
        private readonly string $sessionUlid,
    ) {
        $this->onQueue('ai');
    }

    public function handle(DocumentIngestionService $ingestionService): void
    {
        $session = RagSession::find($this->sessionUlid);
        if (! $session) {
            Log::warning('IngestDocumentJob: session not found', ['session' => $this->sessionUlid]);
            return;
        }

        try {
            // Set initial uploading stage
            $this->updateStage($session, 'uploading', 10);

            // Ingest with real stage callbacks — stages fire when actual work begins
            $result = $ingestionService->ingest(
                $this->filePath,
                $this->userId,
                (string) $this->knowledgeBaseId,
                fn (string $stage) => $this->updateStage($session, $stage, match ($stage) {
                    'extracting' => 30,
                    'chunking' => 50,
                    'embedding' => 70,
                    default => 15,
                }),
                $this->filename,
            );

            // Mark session as ready
            $session->update([
                'status' => 'ready',
                'ingest_stage' => 'ready',
                'source_meta' => array_merge($session->source_meta ?? [], [
                    'chunk_count' => $result['chunk_count'],
                    'char_count' => $result['char_count'],
                ]),
            ]);

            broadcast(new RagIngestProgressEvent(
                sessionUlid: $this->sessionUlid,
                userId: $this->userId,
                status: 'ready',
                progress: 100,
                stage: 'ready',
                sourceMeta: $session->fresh()->source_meta,
            ));
        } catch (Throwable $e) {
            Log::error('IngestDocumentJob failed', [
                'session' => $this->sessionUlid,
                'error' => $e->getMessage(),
            ]);

            $session->update([
                'status' => 'failed',
                'ingest_stage' => 'failed',
                'ingest_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            broadcast(new RagIngestProgressEvent(
                sessionUlid: $this->sessionUlid,
                userId: $this->userId,
                status: 'failed',
                progress: 0,
                stage: 'failed',
                error: mb_substr($e->getMessage(), 0, 500),
            ));

            throw $e;
        }
    }

    /**
     * Update session stage in DB and broadcast to frontend.
     */
    private function updateStage(RagSession $session, string $stage, int $progress): void
    {
        $session->update(['ingest_stage' => $stage]);

        broadcast(new RagIngestProgressEvent(
            sessionUlid: $this->sessionUlid,
            userId: $this->userId,
            status: 'ingesting',
            progress: $progress,
            stage: $stage,
        ));
    }
}
