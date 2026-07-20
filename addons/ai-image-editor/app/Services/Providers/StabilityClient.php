<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StabilityClient
{
    private string $baseUrl = 'https://api.stability.ai/v2beta';

    public function inpaint(string $imagePath, string $maskPath, string $prompt, array $options = []): string
    {
        $response = Http::timeout(60)
            ->withToken($this->apiKey())
            // The mask is a transient, user-private upload stored on the `local` disk by
            // ImageEditorController — not on the public media disk.
            ->attach('image', Storage::disk('public')->get($imagePath), basename($imagePath))
            ->attach('mask', Storage::disk('local')->get($maskPath), 'mask.png')
            ->post("{$this->baseUrl}/stable-image/edit/inpaint", [
                'prompt' => $prompt,
                'output_format' => 'png',
            ]);

        if (! $response->successful()) {
            throw new ImageEditException('Stability inpaint failed: ' . $response->body());
        }

        $outputPath = $options['output_path'] ?? 'image-editor/tmp/' . uniqid('inpaint_', true) . '.png';

        return $this->saveToStorage($response->body(), $outputPath);
    }

    public function outpaint(string $imagePath, array $expand, string $prompt = '', array $options = []): string
    {
        $payload = array_merge([
            'output_format' => 'png',
        ], array_filter([
            'left' => $expand['left'] ?? 0,
            'right' => $expand['right'] ?? 0,
            'up' => $expand['up'] ?? 0,
            'down' => $expand['down'] ?? 0,
            'prompt' => $prompt ?: null,
        ]));

        $response = Http::timeout(60)
            ->withToken($this->apiKey())
            ->attach('image', Storage::disk('public')->get($imagePath), basename($imagePath))
            ->post("{$this->baseUrl}/stable-image/edit/outpaint", $payload);

        if (! $response->successful()) {
            throw new ImageEditException('Stability outpaint failed: ' . $response->body());
        }

        $outputPath = $options['output_path'] ?? 'image-editor/tmp/' . uniqid('outpaint_', true) . '.png';

        return $this->saveToStorage($response->body(), $outputPath);
    }

    public function styleTransfer(string $contentPath, string $stylePath, float $fidelity = 0.5): string
    {
        $response = Http::timeout(60)
            ->withToken($this->apiKey())
            // The style reference is a transient, user-private upload on the `local` disk.
            ->attach('image', Storage::disk('public')->get($contentPath), basename($contentPath))
            ->attach('style_image', Storage::disk('local')->get($stylePath), 'style.png')
            ->post("{$this->baseUrl}/stable-image/control/style", [
                'fidelity' => $fidelity,
                'output_format' => 'png',
            ]);

        if (! $response->successful()) {
            throw new ImageEditException('Stability style transfer failed: ' . $response->body());
        }

        $outputPath = 'image-editor/tmp/' . uniqid('style_', true) . '.png';

        return $this->saveToStorage($response->body(), $outputPath);
    }

    public function objectRemove(string $imagePath, string $maskPath): string
    {
        return $this->inpaint($imagePath, $maskPath, 'remove object, fill with background, seamless');
    }

    private function apiKey(): string
    {
        $key = addon_setting('ai-image-editor', 'stability_api_key');

        if (empty($key)) {
            throw new ImageEditException('Stability AI API key is not configured.');
        }

        return $key;
    }

    private function saveToStorage(string $binaryContent, string $storagePath): string
    {
        Storage::disk('public')->put($storagePath, $binaryContent);

        return $storagePath;
    }

    private function imageToBase64(string $storagePath): string
    {
        $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $bytes = Storage::disk('public')->get($storagePath);

        return 'data:image/' . $ext . ';base64,' . base64_encode($bytes);
    }
}
