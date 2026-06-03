<?php

namespace App\DTO;

/**
 * StreamChunk — single token or final usage from a streaming completion.
 *
 * Two modes:
 *   - Token chunk:  token is set, usage is null
 *   - Final chunk:  token is null, usage is set
 */
class StreamChunk
{
    public function __construct(
        public readonly ?string $token = null,
        public readonly ?array $usage = null,
        public readonly bool $isFinal = false,
    ) {}

    public static function token(string $token): self
    {
        return new self(token: $token);
    }

    public static function final(array $usage): self
    {
        return new self(usage: $usage, isFinal: true);
    }
}
