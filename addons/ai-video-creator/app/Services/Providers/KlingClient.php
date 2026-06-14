<?php

namespace Addons\AiVideoCreator\Services\Providers;

use Addons\AiVideoCreator\Exceptions\VideoProviderException;
use Addons\AiVideoCreator\Services\ProviderJobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KlingClient extends VideoProviderClient
{
    public function submitJob(array $params): string
    {
        $apiKey = $this->getApiKey('kling_api_key');
        $apiSecret = $this->getApiKey('kling_api_secret');
        $jwt = $this->generateJwt($apiKey, $apiSecret);

        $endpoint = $params['type'] === 'image_to_video'
            ? 'https://api.klingai.com/v1/videos/image2video'
            : 'https://api.klingai.com/v1/videos/text2video';

        $body = [
            'model_name' => 'kling-v1-5',
            'duration' => $params['duration'] ?? 10,
            'mode' => 'std',
            'cfg_scale' => 0.5,
        ];

        if ($params['type'] === 'image_to_video') {
            $body['image'] = $params['image'] ?? '';
        } else {
            $body['prompt'] = $params['prompt'] ?? '';
            $body['aspect_ratio'] = $params['aspect_ratio'] ?? '16:9';
        }

        $response = Http::withToken($jwt)
            ->timeout(30)
            ->post($endpoint, $body);

        if (! $response->successful()) {
            throw new VideoProviderException('Kling API error: ' . Str::limit($response->body(), 300));
        }

        $taskId = $response->json('data.task_id');
        if (! $taskId) {
            throw new VideoProviderException('Kling did not return a task_id.');
        }

        return $taskId;
    }

    public function checkStatus(string $jobId): ProviderJobStatus
    {
        $apiKey = $this->getApiKey('kling_api_key');
        $apiSecret = $this->getApiKey('kling_api_secret');
        $jwt = $this->generateJwt($apiKey, $apiSecret);

        $response = Http::withToken($jwt)
            ->get("https://api.klingai.com/v1/videos/text2video/{$jobId}");

        if (! $response->successful()) {
            throw new VideoProviderException('Kling status check failed: ' . $response->body());
        }

        $data = $response->json('data');
        $taskStatus = $data['task_status'] ?? 'failed';

        $mappedStatus = match ($taskStatus) {
            'succeed', 'completed' => 'completed',
            'failed' => 'failed',
            default => 'processing',
        };

        $videoUrl = null;
        if ($mappedStatus === 'completed') {
            $videoUrl = $data['task_result']['videos'][0]['url'] ?? null;
        }

        return new ProviderJobStatus(
            status: $mappedStatus,
            videoUrl: $videoUrl,
            error: $data['task_status_msg'] ?? null,
            metadata: $data,
        );
    }

    public function downloadResult(string $jobId): string
    {
        $status = $this->checkStatus($jobId);

        if ($status->status !== 'completed' || ! $status->videoUrl) {
            throw new VideoProviderException('Video not ready for download.');
        }

        $tempPath = sys_get_temp_dir() . '/' . uniqid('kling_', true) . '.mp4';
        $response = Http::timeout(60)
            ->withOptions(['sink' => $tempPath])
            ->get($status->videoUrl);

        if (! $response->successful()) {
            throw new VideoProviderException('Failed to download video from Kling.');
        }

        return $tempPath;
    }

    public function supportedTypes(): array
    {
        return ['text_to_video', 'image_to_video'];
    }

    private function generateJwt(string $apiKey, string $apiSecret): string
    {
        $header = $this->base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64urlEncode(json_encode([
            'iss' => $apiKey,
            'exp' => time() + 1800,
            'nbf' => time() - 5,
        ]));
        $signature = $this->base64urlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $apiSecret, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
