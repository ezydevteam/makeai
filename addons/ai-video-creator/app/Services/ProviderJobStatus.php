<?php

namespace Addons\AiVideoCreator\Services;

readonly class ProviderJobStatus
{
    public function __construct(
        public string $status,
        public ?string $videoUrl = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {}
}
