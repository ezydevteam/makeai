<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class BackgroundRemoveService
{
    public function __construct(
        private readonly string $provider = 'removebg',
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        // Honor the admin-selected provider — the background_remover integration
        // offers removebg AND clipdrop; hardcoding removebg would ignore a
        // clipdrop selection and read the wrong (empty) key.
        $provider = settings('external_background_remover_provider', 'removebg');
        $provider = in_array($provider, ['removebg', 'clipdrop'], true) ? $provider : 'removebg';

        return new self(
            provider: $provider,
            apiKey: settings("external_background_remover_{$provider}_api_key"),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No API key configured.'];
        }

        try {
            if ($this->provider === 'clipdrop') {
                // Clipdrop exposes no free account endpoint; a request without an
                // image returns 400 for a valid key vs 401 for a bad one.
                $response = Http::timeout(15)
                    ->withHeader('x-api-key', $this->apiKey)
                    ->post('https://clipdrop-api.co/remove-background/v1');

                return $response->status() === 401
                    ? ['success' => false, 'error' => 'Clipdrop rejected the API key.']
                    : ['success' => true, 'message' => 'Clipdrop API key accepted.'];
            }

            $response = Http::timeout(15)
                ->withHeader('X-Api-Key', $this->apiKey)
                ->get('https://api.remove.bg/v1.0/account');

            return ['success' => $response->successful(), 'message' => 'Remove.bg API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
