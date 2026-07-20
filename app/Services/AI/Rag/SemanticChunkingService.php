<?php

namespace App\Services\AI\Rag;

use App\Models\User;
use App\Services\AI\AiService;
use RuntimeException;

class SemanticChunkingService extends ChunkingService
{
    private string $mode;

    /**
     * Who to bill the sentence embeddings to in semantic mode.
     *
     * Set by the caller rather than passed down through chunk()/chunkForDocument(),
     * whose signatures are shared with the base ChunkingService and other callers.
     */
    private ?User $billTo = null;

    public function __construct(private ?AiService $aiService = null)
    {
        parent::__construct();
        $this->mode = settings('rag_chunking_mode', 'fixed');
    }

    /**
     * Bill any embeddings this chunker makes to $user. Semantic mode embeds every
     * sentence, which is a real provider cost that was previously charged to nobody.
     */
    public function billTo(?User $user): self
    {
        $this->billTo = $user;

        return $this;
    }

    /**
     * Chunk text, optionally using semantic boundary detection.
     */
    public function chunk(string $text): array
    {
        if ($this->mode !== 'semantic') {
            return parent::chunk($text);
        }

        return $this->semanticChunk($text);
    }

    private function semanticChunk(string $text): array
    {
        if (! $this->aiService) {
            // Fall back to fixed-size chunking if AI service not injected
            return parent::chunk($text);
        }

        // Split into sentences
        $sentences = $this->splitSentences($text);
        if (count($sentences) <= 1) {
            return parent::chunk($text);
        }

        // Compute embeddings for each sentence in batches
        $embeddings = [];
        $batchSize = 20;
        $sentenceBatches = array_chunk($sentences, $batchSize);

        foreach ($sentenceBatches as $batch) {
            try {
                $results = $this->aiService->embedBatch($batch, null, $this->billTo);
                foreach ($results as $i => $result) {
                    $embeddings[] = $result->vector;
                }
            } catch (\Throwable $e) {
                // If embedding fails for a batch, fall back to fixed-size for this segment
                return parent::chunk($text);
            }
        }

        // Find topic boundaries where cosine similarity drops
        $boundaries = [0];
        $threshold = 0.6; // similarity threshold for topic change

        for ($i = 0; $i < count($embeddings) - 1; $i++) {
            $similarity = $this->cosineSimilarity($embeddings[$i], $embeddings[$i + 1]);
            if ($similarity < $threshold) {
                $boundaries[] = $i + 1;
            }
        }

        // Build chunks starting at topic boundaries, still capped by chunk size
        $chunks = [];
        $chunkIndex = 0;
        $charPointer = 0;

        foreach ($boundaries as $boundaryIdx) {
            $segmentSentences = array_slice($sentences, $boundaryIdx, $this->findSegmentLength($boundaries, $boundaryIdx));
            $segmentText = implode(' ', $segmentSentences);

            // If segment is still larger than chunk size, sub-chunk it
            if (mb_strlen($segmentText) <= $this->getChunkSize()) {
                $chunks[] = [
                    'text' => trim($segmentText),
                    'index' => $chunkIndex,
                    'start_char' => $charPointer,
                    'end_char' => $charPointer + mb_strlen($segmentText),
                ];
                $chunkIndex++;
            } else {
                // Fall back to parent chunking for oversized segments
                $subChunks = parent::chunk($segmentText);
                foreach ($subChunks as $sc) {
                    $chunks[] = [
                        'text' => $sc['text'],
                        'index' => $chunkIndex,
                        'start_char' => $charPointer + $sc['start_char'],
                        'end_char' => $charPointer + $sc['end_char'],
                    ];
                    $chunkIndex++;
                }
            }

            $charPointer += mb_strlen($segmentText) + 1;
        }

        return $chunks;
    }

    private function splitSentences(string $text): array
    {
        // Split on sentence-ending punctuation followed by a space or end
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($text));

        return array_filter(array_map('trim', $sentences), fn ($s) => ! empty($s));
    }

    private function findSegmentLength(array $boundaries, int $currentIdx): int
    {
        $totalSentences = $currentIdx + 1;
        foreach ($boundaries as $b) {
            if ($b > $currentIdx) {
                $totalSentences = $b - $currentIdx;
                break;
            }
        }
        return min($totalSentences, 100); // safety cap
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
