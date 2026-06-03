<?php

namespace App\DTO;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * CompletionResponse — typed result from AI completion.
 *
 * Ref: AI_SaaS_Master_Prompt Part 14C.1
 */
class CompletionResponse implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly string $model,
        public readonly ?string $finishReason = null,
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'model' => $this->model,
            'finish_reason' => $this->finishReason,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
