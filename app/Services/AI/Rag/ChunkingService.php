<?php

namespace App\Services\AI\Rag;

/**
 * ChunkingService — splits text into overlapping chunks for embedding.
 *
 * Uses configurable chunk size and overlap from config('ai.rag').
 */
class ChunkingService
{
    private int $chunkSize;

    private int $chunkOverlap;

    public function __construct()
    {
        $this->chunkSize = (int) (settings('ai_chunk_size') ?: config('ai.rag.chunk_size', 1000));
        $this->chunkOverlap = (int) (settings('ai_chunk_overlap') ?: config('ai.rag.chunk_overlap', 200));
    }

    /**
     * Split text into chunks.
     *
     * @return array<int, array{text: string, index: int, start_char: int, end_char: int}>
     */
    public function chunk(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        $chunks = [];
        $totalLength = mb_strlen($text);
        $start = 0;
        $index = 0;

        while ($start < $totalLength) {
            $end = min($start + $this->chunkSize, $totalLength);

            // Try to break at a sentence boundary
            if ($end < $totalLength) {
                $chunk = mb_substr($text, $start, $this->chunkSize);
                $lastPeriod = mb_strrpos($chunk, '.');
                $lastNewline = mb_strrpos($chunk, "\n");
                $lastBreak = max(
                    $lastPeriod !== false ? $lastPeriod : -1,
                    $lastNewline !== false ? $lastNewline : -1
                );

                if ($lastBreak > $this->chunkSize * 0.5) {
                    $end = $start + $lastBreak + 1;
                }
            }

            $chunkText = trim(mb_substr($text, $start, $end - $start));

            if (! empty($chunkText)) {
                $chunks[] = [
                    'text' => $chunkText,
                    'index' => $index,
                    'start_char' => $start,
                    'end_char' => $end,
                ];
                $index++;
            }

            if ($end >= $totalLength) {
                break;
            }

            $nextStart = $end - $this->chunkOverlap;
            if ($nextStart <= $start) {
                $start = $end;
            } else {
                $start = $nextStart;
            }
        }

        return $chunks;
    }

    /**
     * Split text into chunks for a specific file/document ID.
     */
    public function chunkForDocument(string $text, int $documentId): array
    {
        $chunks = $this->chunk($text);

        return array_map(function (array $chunk) use ($documentId) {
            return array_merge($chunk, [
                'document_id' => $documentId,
                'embedding' => null, // filled later by VectorStoreService
            ]);
        }, $chunks);
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getChunkOverlap(): int
    {
        return $this->chunkOverlap;
    }
}
