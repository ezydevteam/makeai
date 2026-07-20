<?php

namespace App\Jobs;

use App\Events\RagIngestProgressEvent;
use App\Models\RagSession;
use App\Services\AI\AiService;
use App\Services\AI\Rag\ChunkingService;
use App\Services\AI\Rag\VectorStoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestYoutubeTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly int $knowledgeBaseId,
        private readonly string $videoUrl,
        private readonly int $userId,
        private readonly string $sessionUlid,
    ) {
        $this->onQueue('ai');
    }

    public function handle(AiService $aiService, ChunkingService $chunker, VectorStoreService $vectorStore): void
    {
        $session = RagSession::find($this->sessionUlid);
        if (! $session) {
            Log::warning('IngestYoutubeTranscriptJob: session not found', ['session' => $this->sessionUlid]);
            return;
        }

        try {
            $videoId = $this->extractVideoId($this->videoUrl);
            if (! $videoId) {
                throw new \RuntimeException('Could not extract YouTube video ID from URL.');
            }

            $session->update(['ingest_stage' => 'fetching_captions']);
            broadcast(new RagIngestProgressEvent(
                sessionUlid: $this->sessionUlid,
                userId: $this->userId,
                status: 'ingesting',
                progress: 10,
                stage: 'fetching_captions',
            ));

            $transcript = $this->fetchTranscript($videoId);

            if (! $transcript && settings('rag_youtube_whisper_fallback')) {
                $session->update(['ingest_stage' => 'transcribing']);
                broadcast(new RagIngestProgressEvent(
                    sessionUlid: $this->sessionUlid,
                    userId: $this->userId,
                    status: 'ingesting',
                    progress: 15,
                    stage: 'transcribing',
                ));

                $transcript = $this->transcribeAudio($videoId, $aiService);
            }

            if (! $transcript) {
                throw new \RuntimeException('No captions available for this video.');
            }

            $videoTitle = $this->fetchVideoTitle($videoId);
            $duration = $this->extractDurationFromTranscript($transcript);

            $session->update(['ingest_stage' => 'chunking']);
            broadcast(new RagIngestProgressEvent(
                sessionUlid: $this->sessionUlid,
                userId: $this->userId,
                status: 'ingesting',
                progress: 40,
                stage: 'chunking',
            ));

            // Chunk transcript with timestamps preserved
            $chunks = $chunker->chunk($transcript['text'] ?? '');
            $chunksWithTimestamps = [];
            $charOffset = 0;

            foreach ($chunks as $chunk) {
                $startTimestamp = $this->findTimestampAtOffset($transcript['segments'] ?? [], $charOffset);
                $endTimestamp = $this->findTimestampAtOffset($transcript['segments'] ?? [], $charOffset + mb_strlen($chunk['text']));

                $chunksWithTimestamps[] = array_merge($chunk, [
                    'timestamp_start' => $startTimestamp,
                    'timestamp_end' => $endTimestamp,
                ]);

                $charOffset += mb_strlen($chunk['text']);
            }

            // Store document record
            $documentId = \DB::table('knowledge_base_documents')->insertGetId([
                'knowledge_base_id' => $this->knowledgeBaseId,
                'user_id' => $this->userId,
                'filename' => ($videoTitle ?? 'YouTube Video').' (transcript)',
                'char_count' => mb_strlen($transcript['text'] ?? ''),
                'chunk_count' => count($chunksWithTimestamps),
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $session->update(['ingest_stage' => 'embedding']);
            broadcast(new RagIngestProgressEvent(
                sessionUlid: $this->sessionUlid,
                userId: $this->userId,
                status: 'ingesting',
                progress: 60,
                stage: 'embedding',
            ));

            // Embed and store
            $batchSize = 20;
            $chunkBatches = array_chunk($chunksWithTimestamps, $batchSize);

            $progressPerBatch = $chunkBatches ? (30 / count($chunkBatches)) : 30;
            $currentProgress = 65;

            // Bill the transcript's embeddings to the owner — see AiService::embedText().
            $owner = \App\Models\User::find($this->userId);

            foreach ($chunkBatches as $batch) {
                $texts = array_column($batch, 'text');
                $embeddingResults = $aiService->embedBatch($texts, null, $owner);

                foreach ($batch as $i => $chunk) {
                    $chunkId = \DB::table('knowledge_base_chunks')->insertGetId([
                        'document_id' => $documentId,
                        'chunk_index' => $chunk['index'],
                        'text' => $chunk['text'],
                        'char_start' => $chunk['start_char'],
                        'char_end' => $chunk['end_char'],
                        'created_at' => now(),
                    ]);

                    $vectorStore->store(
                        knowledgeBaseId: (string) $this->knowledgeBaseId,
                        documentId: $documentId,
                        chunkId: $chunkId,
                        userId: $this->userId,
                        vector: $embeddingResults[$i]->vector,
                        metadata: [
                            'document_id' => $documentId,
                            'chunk_index' => $chunk['index'],
                            'timestamp_start' => $chunk['timestamp_start'] ?? null,
                            'timestamp_end' => $chunk['timestamp_end'] ?? null,
                            'type' => 'youtube_transcript',
                        ],
                    );
                }

                $currentProgress += (int) $progressPerBatch;
                broadcast(new RagIngestProgressEvent(
                    sessionUlid: $this->sessionUlid,
                    userId: $this->userId,
                    status: 'ingesting',
                    progress: min($currentProgress, 95),
                    stage: 'embedding',
                ));
            }

            // Mark document completed
            \DB::table('knowledge_base_documents')
                ->where('id', $documentId)
                ->update([
                    'status' => 'completed',
                    'chunk_count' => count($chunksWithTimestamps),
                    'updated_at' => now(),
                ]);

            $sourceMeta = [
                'video_id' => $videoId,
                'video_title' => $videoTitle,
                'video_url' => $this->videoUrl,
                'duration' => $duration,
                'chunk_count' => count($chunksWithTimestamps),
                'has_whisper' => isset($transcript['whisper']) && $transcript['whisper'],
            ];

            $session->update([
                'status' => 'ready',
                'ingest_stage' => 'ready',
                'title' => $videoTitle,
                'source_meta' => $sourceMeta,
            ]);

            broadcast(new RagIngestProgressEvent(
                sessionUlid: $this->sessionUlid,
                userId: $this->userId,
                status: 'ready',
                progress: 100,
                stage: 'ready',
                sourceMeta: $sourceMeta,
            ));
        } catch (Throwable $e) {
            Log::error('IngestYoutubeTranscriptJob failed', [
                'session' => $this->sessionUlid,
                'url' => $this->videoUrl,
                'error' => $e->getMessage(),
            ]);

            $session->update([
                'status' => 'failed',
                'ingest_stage' => 'failed',
                'ingest_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            // Nothing usable was produced, so hand back the up-front ingestion charge.
            app(\App\Services\RagToolService::class)->refundIngestionCredits($session->fresh());

            if (isset($documentId)) {
                \DB::table('knowledge_base_documents')
                    ->where('id', $documentId)
                    ->update(['status' => 'failed', 'updated_at' => now()]);
            }

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

    private function extractVideoId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function fetchTranscript(string $videoId): ?array
    {
        try {
            $endpoint = settings('rag_youtube_transcript_endpoint', 'https://youtube-transcript.ai/transcript/{id}.txt');
            $endpoint = str_replace(['{id}', '{videoId}'], $videoId, $endpoint);

            $request = Http::timeout(15);
            if ($apiKey = settings('rag_youtube_transcript_api_key')) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->get($endpoint);

            if ($response->successful()) {
                $markdown = $response->body();
                $parsed = $this->parseTranscriptMarkdown($markdown);
                if (! empty($parsed['segments'])) {
                    return $parsed;
                }
            }
        } catch (\Exception $e) {
            Log::warning('YouTube transcript fetch failed: '.$e->getMessage());
        }

        // Fallback: try YouTube timedtext API directly
        try {
            $response = Http::timeout(10)
                ->get('https://www.youtube.com/watch', ['v' => $videoId]);

            $html = $response->body();

            if (preg_match('/"captions":\{"playerCaptionsTracklistRenderer":\{"captionTracks":\[.*?"baseUrl":"([^"]+)"/s', $html, $matches)) {
                $captionsUrl = json_decode('"' . $matches[1] . '"');
                $captionsResponse = Http::timeout(10)->get($captionsUrl);
                $xml = $captionsResponse->body();

                $segments = [];
                $fullText = '';

                preg_match_all('/<text start="([^"]+)" dur="([^"]+)"[^>]*>(.*?)<\/text>/s', $xml, $txtMatches, PREG_SET_ORDER);

                foreach ($txtMatches as $m) {
                    $segments[] = [
                        'text' => strip_tags(html_entity_decode($m[3])),
                        'start' => (float) $m[1],
                        'duration' => (float) $m[2],
                    ];
                    $fullText .= strip_tags(html_entity_decode($m[3])).' ';
                }

                if (! empty($fullText)) {
                    return ['text' => trim($fullText), 'segments' => $segments];
                }
            }
        } catch (\Exception $e) {
            Log::warning('YouTube timedtext fallback failed: '.$e->getMessage());
        }

        return null;
    }

    private function parseTranscriptMarkdown(string $markdown): array
    {
        $parts = explode('## Transcript', $markdown, 2);
        $transcriptBody = isset($parts[1]) ? $parts[1] : $markdown;

        preg_match_all('/\[([0-9:]{4,8})\]/s', $transcriptBody, $matches, PREG_OFFSET_CAPTURE);

        $segments = [];
        $fullText = '';

        if (! empty($matches[0])) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $currentMatch = $matches[0][$i];
                $currentTimeStr = $matches[1][$i][0];
                $currentOffset = $currentMatch[1];
                
                $timeParts = explode(':', $currentTimeStr);
                $seconds = 0.0;
                if (count($timeParts) === 2) {
                    $seconds = ((int)$timeParts[0] * 60) + (float)$timeParts[1];
                } elseif (count($timeParts) === 3) {
                    $seconds = ((int)$timeParts[0] * 3600) + ((int)$timeParts[1] * 60) + (float)$timeParts[2];
                }

                $startTextPos = $currentOffset + strlen($currentMatch[0]);
                if ($i < $count - 1) {
                    $endTextPos = $matches[0][$i + 1][1];
                    $segmentText = substr($transcriptBody, $startTextPos, $endTextPos - $startTextPos);
                } else {
                    $segmentText = substr($transcriptBody, $startTextPos);
                }

                $segmentText = trim($segmentText);
                $segmentText = preg_replace('/\s+/', ' ', $segmentText);

                if (! empty($segmentText)) {
                    $segments[] = [
                        'text' => $segmentText,
                        'start' => $seconds,
                        'duration' => 0.0,
                    ];
                    $fullText .= $segmentText.' ';
                }
            }

            $segCount = count($segments);
            for ($i = 0; $i < $segCount; $i++) {
                if ($i < $segCount - 1) {
                    $segments[$i]['duration'] = max(0.1, $segments[$i + 1]['start'] - $segments[$i]['start']);
                } else {
                    $segments[$i]['duration'] = 5.0;
                }
            }
        }

        return [
            'text' => trim($fullText),
            'segments' => $segments
        ];
    }


    private function transcribeAudio(string $videoId, AiService $aiService): ?array
    {
        // YouTube audio download + Whisper transcription
        // This is a stub — requires yt-dlp or similar on the server
        Log::info('Whisper fallback triggered for video: '.$videoId);
        return null;
    }

    private function fetchVideoTitle(string $videoId): ?string
    {
        try {
            $response = Http::timeout(10)
                ->get('https://www.youtube.com/oembed', [
                    'url' => 'https://www.youtube.com/watch?v='.$videoId,
                    'format' => 'json',
                ]);

            if ($response->successful()) {
                return $response->json()['title'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning('YouTube title fetch failed: '.$e->getMessage());
        }

        return 'YouTube Video';
    }

    private function findTimestampAtOffset(array $segments, int $charOffset): ?float
    {
        $currentChars = 0;
        foreach ($segments as $segment) {
            $len = mb_strlen($segment['text'] ?? '') + 1; // +1 for space
            if ($charOffset <= $currentChars + $len) {
                return (float) ($segment['start'] ?? 0);
            }
            $currentChars += $len;
        }
        return ! empty($segments) ? (float) ($segments[count($segments) - 1]['start'] ?? 0) : null;
    }

    private function extractDurationFromTranscript(array $transcript): ?float
    {
        $segments = $transcript['segments'] ?? [];
        if (empty($segments)) {
            return null;
        }

        $last = $segments[count($segments) - 1];
        return ($last['start'] ?? 0) + ($last['duration'] ?? 0);
    }
}
