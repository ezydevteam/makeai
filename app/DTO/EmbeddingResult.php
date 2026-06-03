<?php

namespace App\DTO;

/**
 * EmbeddingResult — vector embedding response.
 */
class EmbeddingResult
{
    /**
     * @param  array<float>  $vector
     */
    public function __construct(
        public readonly array $vector,
        public readonly int $dimensions,
        public readonly string $model,
        public readonly int $tokensUsed,
    ) {}

    public function toArray(): array
    {
        return [
            'vector' => $this->vector,
            'dimensions' => $this->dimensions,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }
}
