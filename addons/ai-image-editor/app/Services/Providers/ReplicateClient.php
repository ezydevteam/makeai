<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ReplicateClient
{
    private string $baseUrl = 'https://api.replicate.com/v1';

    public function upscale(string $imagePath, int $scaleFactor = 4): string
    {
        $base64 = $this->imageToBase64($imagePath);

        $response = Http::timeout(60)
            ->withToken($this->apiKey())
            ->post("{$this->baseUrl}/predictions", [
                'version' => '42fed1c4974146d4d2414e2be2c5277c7fcf05fcc3a73abf41610695738c1d7b',
                'input' => [
                    'image' => $base64,
                    'scale' => $scaleFactor,
                    'face_enhance' => false,
                ],
            ]);

        if (! $response->successful()) {
            throw new ImageEditException('Replicate upscale submission failed: ' . $response->body());
        }

        $predictionId = $response->json('id');

        return $this->pollAndDownload($predictionId, $scaleFactor);
    }

    public function sdInpaint(string $imagePath, string $maskPath, string $prompt): string
    {
        $base64 = $this->imageToBase64($imagePath);
        $maskBase64 = $this->imageToBase64($maskPath);

        $response = Http::timeout(60)
            ->withToken($this->apiKey())
            ->post("{$this->baseUrl}/predictions", [
                'version' => 'stability-ai/stable-diffusion-inpainting',
                'input' => [
                    'image' => $base64,
                    'mask' => $maskBase64,
                    'prompt' => $prompt,
                    'num_outputs' => 1,
                ],
            ]);

        if (! $response->successful()) {
            throw new ImageEditException('Replicate SD inpaint submission failed: ' . $response->body());
        }

        $predictionId = $response->json('id');

        return $this->pollAndDownload($predictionId);
    }

    private function pollAndDownload(string $predictionId, ?int $scale = null): string
    {
        $maxAttempts = 30;
        $delay = 3;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($delay);

            $response = Http::timeout(30)
                ->withToken($this->apiKey())
                ->get("{$this->baseUrl}/predictions/{$predictionId}");

            if (! $response->successful()) {
                throw new ImageEditException('Replicate poll failed: ' . $response->body());
            }

            $status = $response->json('status');

            if ($status === 'succeeded') {
                $output = $response->json('output');
                $url = is_array($output) ? ($output[0] ?? null) : $output;

                if (! $url) {
                    throw new ImageEditException('Replicate prediction succeeded but no output URL found.');
                }

                $suffix = $scale ? 'upscale_' . $scale . 'x_' : 'sd_inpaint_';

                return $this->downloadImage($url, $suffix . uniqid('', true) . '.png');
            }

            if (in_array($status, ['failed', 'canceled'], true)) {
                $error = $response->json('error') ?? 'Unknown error';
                throw new ImageEditException("Replicate prediction {$status}: {$error}");
            }
        }

        throw new ImageEditException('Replicate prediction timed out after ' . ($maxAttempts * $delay) . ' seconds.');
    }

    private function downloadImage(string $url, string $filename): string
    {
        $tempPath = sys_get_temp_dir() . '/' . $filename;

        Http::withOptions(['sink' => $tempPath])
            ->timeout(120)
            ->get($url);

        if (! file_exists($tempPath)) {
            throw new ImageEditException('Failed to download Replicate output image.');
        }

        $storagePath = 'image-editor/tmp/' . $filename;
        Storage::disk('public')->put($storagePath, file_get_contents($tempPath));
        @unlink($tempPath);

        return $storagePath;
    }

    private function apiKey(): string
    {
        $key = addon_setting('ai-image-editor', 'replicate_api_key');

        if (empty($key)) {
            throw new ImageEditException('Replicate API key is not configured.');
        }

        return $key;
    }

    private function imageToBase64(string $storagePath): string
    {
        $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $bytes = Storage::disk('public')->get($storagePath);

        return 'data:image/' . $ext . ';base64,' . base64_encode($bytes);
    }
}
