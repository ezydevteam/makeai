<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HeyGenClient extends VideoProviderClient
{
    public function submitJob(array $params): string
    {
        $apiKey = $this->getApiKey('heygen_api_key');

        $response = Http::withHeader('X-Api-Key', $apiKey)
            ->timeout(30)
            ->post('https://api.heygen.com/v2/video/generate', [
                'video_inputs' => [[
                    'character' => [
                        'type' => 'avatar',
                        'avatar_id' => $params['avatar_id'] ?? 'Vanessa-invest-20240827',
                        'avatar_style' => 'normal',
                    ],
                    'voice' => [
                        'type' => 'text',
                        'input_text' => $params['script'],
                        'voice_id' => $params['voice_id'] ?? '2d5b0e6cf36f460aa7fc47e3eee4ba54',
                    ],
                    'background' => ['type' => 'color', 'value' => '#FFFFFF'],
                ]],
                'dimension' => ['width' => 1280, 'height' => 720],
            ]);

        if (! $response->successful()) {
            throw new VideoProviderException('HeyGen API error: ' . Str::limit($response->body(), 300));
        }

        $videoId = $response->json('data.video_id');
        if (! $videoId) {
            throw new VideoProviderException('HeyGen did not return a video_id.');
        }

        return $videoId;
    }

    public function checkStatus(string $jobId): ProviderJobStatus
    {
        $apiKey = $this->getApiKey('heygen_api_key');

        $response = Http::withHeader('X-Api-Key', $apiKey)
            ->get("https://api.heygen.com/v1/video_status.get?video_id={$jobId}");

        if (! $response->successful()) {
            throw new VideoProviderException('HeyGen status check failed: ' . $response->body());
        }

        $status = $response->json('data.status');
        $mappedStatus = match ($status) {
            'completed' => 'completed',
            'failed' => 'failed',
            'processing' => 'processing',
            default => 'processing',
        };

        return new ProviderJobStatus(
            status: $mappedStatus,
            videoUrl: $response->json('data.video_url'),
            error: $response->json('data.error.message'),
            metadata: $response->json('data'),
        );
    }

    public function downloadResult(string $jobId): string
    {
        $status = $this->checkStatus($jobId);

        if ($status->status !== 'completed' || ! $status->videoUrl) {
            throw new VideoProviderException('Video not ready for download.');
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('heygen_', true) . '.mp4';
        $response = Http::timeout(60)
            ->withOptions(['sink' => $tempPath])
            ->get($status->videoUrl);

        if (! $response->successful()) {
            throw new VideoProviderException('Failed to download video from HeyGen.');
        }

        return $tempPath;
    }

    public function supportedTypes(): array
    {
        return ['avatar_video'];
    }

    public function listAvatars(): array
    {
        $apiKey = $this->getApiKey('heygen_api_key');

        $response = Http::withHeader('X-Api-Key', $apiKey)
            ->get('https://api.heygen.com/v2/avatars');

        return $response->json('data.avatars') ?? [];
    }

    public function listVoices(): array
    {
        $apiKey = $this->getApiKey('heygen_api_key');

        $response = Http::withHeader('X-Api-Key', $apiKey)
            ->get('https://api.heygen.com/v2/voices');

        return $response->json('data.voices') ?? [];
    }
}
