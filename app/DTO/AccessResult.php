<?php

declare(strict_types=1);

namespace App\DTO;

readonly class AccessResult
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public bool $truncate = false,
        public int $httpStatus = 200,
    ) {}

    public static function allow(bool $truncate = false): self
    {
        return new self(allowed: true, truncate: $truncate);
    }

    public static function deny(string $reason, int $httpStatus = 403): self
    {
        return new self(allowed: false, reason: $reason, httpStatus: $httpStatus);
    }
}
