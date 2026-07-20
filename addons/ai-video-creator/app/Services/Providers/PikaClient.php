<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PikaClient extends VideoProviderClient
{
    public function submitJob(array $params): string
    {
        $apiKey = $this->getApiKey('pika_api_key');

        $body = [
            'promptText' => $params['prompt'] ?? '',
            'duration' => $params['duration'] ?? 5,
            'aspectRatio' => $params['aspect_ratio'] ?? '16:9',
        ];

        if (! empty($params['image'])) {
            $body['image'] = $params['image'];
        }

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.pika.art/v1/generate', $body);

        if (! $response->successful()) {
            throw new VideoProviderException('Pika API error: ' . Str::limit($response->body(), 300));
        }

        $id = $response->json('id');
        if (! $id) {
            throw new VideoProviderException('Pika did not return a job ID.');
        }

        return $id;
    }

    public function checkStatus(string $jobId): ProviderJobStatus
    {
        $apiKey = $this->getApiKey('pika_api_key');

        $response = Http::withToken($apiKey)
            ->get("https://api.pika.art/v1/jobs/{$jobId}");

        if (! $response->successful()) {
            throw new VideoProviderException('Pika status check failed: ' . $response->body());
        }

        $status = $response->json('status');
        $mappedStatus = match ($status) {
            'succeeded', 'completed' => 'completed',
            'failed' => 'failed',
            'queued', 'running' => 'processing',
            default => 'processing',
        };

        return new ProviderJobStatus(
            status: $mappedStatus,
            videoUrl: $response->json('resultUrl'),
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

        $tempPath = sys_get_temp_dir() . '/' . uniqid('pika_', true) . '.mp4';
        $response = Http::timeout(60)
            ->withOptions(['sink' => $tempPath])
            ->get($status->videoUrl);

        if (! $response->successful()) {
            throw new VideoProviderException('Failed to download video from Pika.');
        }

        return $tempPath;
    }

    public function supportedTypes(): array
    {
        return ['text_to_video', 'image_to_video'];
    }
}
