<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RunwayClient extends VideoProviderClient
{
    public function submitJob(array $params): string
    {
        if (($params['type'] ?? '') === 'text_to_video') {
            throw new VideoProviderException('Runway requires an image. Use Kling or Pika for text-to-video.');
        }

        $apiKey = $this->getApiKey('runway_api_key');

        $response = Http::withToken($apiKey)
            ->withHeader('X-Runway-Version', '2024-11-06')
            ->timeout(30)
            ->post('https://api.dev.runwayml.com/v1/image_to_video', [
                'promptImage' => $params['image'] ?? '',
                'promptText' => $params['prompt'] ?? '',
                'model' => 'gen3a_turbo',
                'duration' => $params['duration'] ?? 10,
                'ratio' => $params['aspect_ratio'] ?? '16:9',
            ]);

        if (! $response->successful()) {
            throw new VideoProviderException('Runway API error: ' . Str::limit($response->body(), 300));
        }

        $id = $response->json('id');
        if (! $id) {
            throw new VideoProviderException('Runway did not return a task ID.');
        }

        return $id;
    }

    public function checkStatus(string $jobId): ProviderJobStatus
    {
        $apiKey = $this->getApiKey('runway_api_key');

        $response = Http::withToken($apiKey)
            ->withHeader('X-Runway-Version', '2024-11-06')
            ->get("https://api.dev.runwayml.com/v1/tasks/{$jobId}");

        if (! $response->successful()) {
            throw new VideoProviderException('Runway status check failed: ' . $response->body());
        }

        $status = $response->json('status');

        $mappedStatus = match ($status) {
            'SUCCEEDED' => 'completed',
            'FAILED' => 'failed',
            'PENDING', 'RUNNING' => 'processing',
            default => 'processing',
        };

        $videoUrl = $mappedStatus === 'completed'
            ? ($response->json('output')[0] ?? null)
            : null;

        return new ProviderJobStatus(
            status: $mappedStatus,
            videoUrl: $videoUrl,
            error: $mappedStatus === 'failed' ? ($response->json('failure') ?? 'Unknown error') : null,
            metadata: $response->json(),
        );
    }

    public function downloadResult(string $jobId): string
    {
        $status = $this->checkStatus($jobId);

        if ($status->status !== 'completed' || ! $status->videoUrl) {
            throw new VideoProviderException('Video not ready for download.');
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('runway_', true) . '.mp4';
        $response = Http::timeout(60)
            ->withOptions(['sink' => $tempPath])
            ->get($status->videoUrl);

        if (! $response->successful()) {
            throw new VideoProviderException('Failed to download video from Runway.');
        }

        return $tempPath;
    }

    public function supportedTypes(): array
    {
        return ['image_to_video'];
    }
}
