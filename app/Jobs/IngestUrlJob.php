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

class IngestUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly int $knowledgeBaseId,
        private readonly string $url,
        private readonly int $userId,
        private readonly string $sessionUlid,
    ) {
        $this->onQueue('ai');
    }

    public function handle(DocumentIngestionService $ingestionService): void
    {
        $session = RagSession::find($this->sessionUlid);
        if (! $session) {
            Log::warning('IngestUrlJob: session not found', ['session' => $this->sessionUlid]);
            return;
        }

        try {
            $result = $ingestionService->ingestFromUrl(
                $this->url,
                $this->userId,
                (string) $this->knowledgeBaseId,
                fn (string $stage) => $this->updateStage($session, $stage, match ($stage) {
                    'scraping' => 20,
                    'extracting' => 40,
                    'chunking' => 55,
                    'embedding' => 75,
                    default => 15,
                }),
            );

            $session->update([
                'status' => 'ready',
                'ingest_stage' => 'ready',
                'source_meta' => array_merge($session->source_meta ?? [], [
                    'url' => $this->url,
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
            Log::error('IngestUrlJob failed', [
                'session' => $this->sessionUlid,
                'url' => $this->url,
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
