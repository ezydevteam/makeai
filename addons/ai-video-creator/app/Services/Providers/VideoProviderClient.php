<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;

abstract class VideoProviderClient
{
    abstract public function submitJob(array $params): string;

    abstract public function checkStatus(string $jobId): ProviderJobStatus;

    abstract public function downloadResult(string $jobId): string;

    abstract public function supportedTypes(): array;

    protected function getApiKey(string $key): string
    {
        $value = addon_setting('ai-video-creator', $key);
        if (empty($value)) {
            throw new VideoProviderException("Provider API key '{$key}' is not configured.");
        }

        return $value;
    }
}
