<?php

namespace App\DTO;

/**
 * RagResult — result from a RAG knowledge base query.
 */
class RagResult
{
    public function __construct(
        public readonly string $answer,
        public readonly array $sources,
        public readonly float $confidence,
    ) {}

    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'sources' => $this->sources,
            'confidence' => $this->confidence,
        ];
    }
}
