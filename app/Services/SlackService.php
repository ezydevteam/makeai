<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SlackService
{
    private ?string $botToken;

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken;
    }

    public static function fromSettings(): self
    {
        return new self(botToken: settings('external_slack_slack_bot_token'));
    }

    public function isConfigured(): bool
    {
        return filled($this->botToken);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No bot token configured.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($this->botToken)
                ->get('https://slack.com/api/auth.test');

            return ['success' => $response->successful() && $response->json('ok'), 'message' => 'Slack API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
