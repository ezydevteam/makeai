<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Services\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RemoveBgClient
{
    private string $baseUrl = 'https://api.remove.bg/v1.0';

    public function removeBackground(string $imagePath): string
    {
        $response = Http::timeout(60)
            ->withHeader('X-Api-Key', $this->apiKey())
            ->attach('image_file', Storage::disk('public')->get($imagePath), basename($imagePath))
            ->post("{$this->baseUrl}/removebg", [
                'size' => 'auto',
                'format' => 'png',
                'bg_color' => '',
            ]);

        if (! $response->successful()) {
            throw new ImageEditException('Remove.bg failed: ' . $response->body());
        }

        $outputPath = 'image-editor/tmp/' . uniqid('removebg_', true) . '.png';

        return $this->saveToStorage($response->body(), $outputPath);
    }

    private function apiKey(): string
    {
        $key = addon_setting('ai-image-editor', 'remove_bg_api_key');

        if (empty($key)) {
            throw new ImageEditException('Remove.bg API key is not configured.');
        }

        return $key;
    }

    private function saveToStorage(string $binaryContent, string $storagePath): string
    {
        Storage::disk('public')->put($storagePath, $binaryContent);

        return $storagePath;
    }
}
