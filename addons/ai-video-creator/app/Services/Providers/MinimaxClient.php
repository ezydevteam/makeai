<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MinimaxClient extends VideoProviderClient
{
    public function submitJob(array $params): string
    {
        $apiKey = $this->getApiKey('minimax_api_key');

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.minimaxi.chat/v1/video_generation', [
                'model' => 'video-01',
                'prompt' => $params['prompt'] ?? '',
            ]);

        if (! $response->successful()) {
            throw new VideoProviderException('Minimax API error: ' . Str::limit($response->body(), 300));
        }

        $taskId = $response->json('task_id');
        if (! $taskId) {
            throw new VideoProviderException('Minimax did not return task_id.');
        }

        return $taskId;
    }

    public function checkStatus(string $jobId): ProviderJobStatus
    {
        $apiKey = $this->getApiKey('minimax_api_key');

        $response = Http::withToken($apiKey)
            ->get("https://api.minimaxi.chat/v1/query/video_generation?task_id={$jobId}");

        if (! $response->successful()) {
            throw new VideoProviderException('Minimax status check failed: ' . $response->body());
        }

        $status = $response->json('status');
        $mappedStatus = match ($status) {
            'Success', 'Succeed' => 'completed',
            'Fail' => 'failed',
            'Queueing', 'Processing' => 'processing',
            default => 'processing',
        };

        $videoUrl = null;
        if ($mappedStatus === 'completed') {
            $fileId = $response->json('file_id');
            $groupId = $response->json('group_id');

            if ($fileId) {
                $fileResp = Http::withToken($apiKey)
                    ->get("https://api.minimaxi.chat/v1/files/retrieve?GroupId={$groupId}&file_id={$fileId}");
                $videoUrl = $fileResp->json('file.download_url');
            }
        }

        return new ProviderJobStatus(
            status: $mappedStatus,
            videoUrl: $videoUrl,
            error: $response->json('error_message'),
            metadata: $response->json(),
        );
    }

    public function downloadResult(string $jobId): string
    {
        $status = $this->checkStatus($jobId);

        if ($status->status !== 'completed' || ! $status->videoUrl) {
            throw new VideoProviderException('Video not ready for download.');
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('minimax_', true) . '.mp4';
        $response = Http::timeout(60)
            ->withOptions(['sink' => $tempPath])
            ->get($status->videoUrl);

        if (! $response->successful()) {
            throw new VideoProviderException('Failed to download video from Minimax.');
        }

        return $tempPath;
    }

    public function supportedTypes(): array
    {
        return ['text_to_video'];
    }
}
