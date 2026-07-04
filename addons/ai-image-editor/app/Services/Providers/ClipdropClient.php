<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ClipdropClient
{
    private string $baseUrl = 'https://clipdrop-api.co';

    public function removeBackground(string $imagePath): string
    {
        $response = Http::timeout(60)
            ->withHeader('x-api-key', $this->apiKey())
            ->attach('image_file', Storage::disk('public')->get($imagePath), basename($imagePath))
            ->post("{$this->baseUrl}/remove-background/v1");

        if (! $response->successful()) {
            throw new ImageEditException('Clipdrop background removal failed: ' . $response->body());
        }

        $outputPath = 'image-editor/tmp/' . uniqid('clipdrop_bg_', true) . '.png';

        return $this->saveToStorage($response->body(), $outputPath);
    }

    public function removeObject(string $imagePath, string $maskPath): string
    {
        $response = Http::timeout(60)
            ->withHeader('x-api-key', $this->apiKey())
            ->attach('image_file', Storage::disk('public')->get($imagePath), basename($imagePath))
            ->attach('mask_file', Storage::disk('public')->get($maskPath), 'mask.png')
            ->post("{$this->baseUrl}/cleanup/v1");

        if (! $response->successful()) {
            throw new ImageEditException('Clipdrop object removal failed: ' . $response->body());
        }

        $outputPath = 'image-editor/tmp/' . uniqid('clipdrop_cleanup_', true) . '.png';

        return $this->saveToStorage($response->body(), $outputPath);
    }

    private function apiKey(): string
    {
        $key = addon_setting('ai-image-editor', 'clipdrop_api_key');

        if (empty($key)) {
            throw new ImageEditException('Clipdrop API key is not configured.');
        }

        return $key;
    }

    private function saveToStorage(string $binaryContent, string $storagePath): string
    {
        Storage::disk('public')->put($storagePath, $binaryContent);

        return $storagePath;
    }
}
