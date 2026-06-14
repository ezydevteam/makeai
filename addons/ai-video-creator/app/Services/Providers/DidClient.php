<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DidClient extends VideoProviderClient
{
    public function submitJob(array $params): string
    {
        $apiKey = $this->getApiKey('did_api_key');
        $auth = base64_encode("{$apiKey}:");

        $body = [
            'script' => [
                'type' => 'text',
                'subtitles' => false,
                'provider' => ['type' => 'microsoft', 'voice_id' => 'en-US-JennyNeural'],
                'input' => $params['script'],
            ],
            'presenter_id' => $params['presenter_id'] ?? 'rian-pbMoTzs7an',
            'driver_id' => 'uM00QurTBs',
        ];

        $response = Http::withHeader('Authorization', "Basic {$auth}")
            ->timeout(30)
            ->post('https://api.d-id.com/talks', $body);

        if (! $response->successful()) {
            throw new VideoProviderException('D-ID API error: ' . Str::limit($response->body(), 300));
        }

        $id = $response->json('id');
        if (! $id) {
            throw new VideoProviderException('D-ID did not return an ID.');
        }

        return $id;
    }

    public function checkStatus(string $jobId): ProviderJobStatus
    {
        $apiKey = $this->getApiKey('did_api_key');
        $auth = base64_encode("{$apiKey}:");

        $response = Http::withHeader('Authorization', "Basic {$auth}")
            ->get("https://api.d-id.com/talks/{$jobId}");

        if (! $response->successful()) {
            throw new VideoProviderException('D-ID status check failed: ' . $response->body());
        }

        $status = $response->json('status');
        $mappedStatus = match ($status) {
            'done' => 'completed',
            'error' => 'failed',
            'created', 'started' => 'processing',
            default => 'processing',
        };

        return new ProviderJobStatus(
            status: $mappedStatus,
            videoUrl: $response->json('result_url'),
            error: $response->json('error'),
            metadata: $response->json(),
        );
    }

    public function downloadResult(string $jobId): string
    {
        $status = $this->checkStatus($jobId);

        if ($status->status !== 'completed' || ! $status->videoUrl) {
            throw new VideoProviderException('Video not ready for download.');
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('did_', true) . '.mp4';
        $response = Http::timeout(60)
            ->withOptions(['sink' => $tempPath])
            ->get($status->videoUrl);

        if (! $response->successful()) {
            throw new VideoProviderException('Failed to download video from D-ID.');
        }

        return $tempPath;
    }

    public function supportedTypes(): array
    {
        return ['avatar_video'];
    }
}
