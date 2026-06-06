<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class YoutubeService
{
    public function isConfigured(): bool
    {
        return true;
    }

    public static function fromSettings(): self
    {
        return new self();
    }

    public function testConnection(): array
    {
        try {
            $response = Http::timeout(15)->get('https://www.youtube.com/oembed', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'format' => 'json',
            ]);

            return ['success' => $response->successful(), 'message' => 'YouTube oEmbed API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
