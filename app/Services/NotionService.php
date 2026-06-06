<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class NotionService
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        return new self(apiKey: settings('external_notion_notion_integration_token'));
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No integration token configured.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->withHeader('Notion-Version', '2022-06-28')
                ->get('https://api.notion.com/v1/users/me');

            return ['success' => $response->successful(), 'message' => 'Notion API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
