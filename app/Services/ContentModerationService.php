<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ContentModerationService
{
    private bool $enabled;
    private string $provider;
    private string $mode;
    private ?string $openAiApiKey;
    private ?string $sightengineApiUser;
    private ?string $sightengineApiSecret;
    private int $timeout;

    public function __construct(
        bool $enabled = false,
        string $provider = 'openai_moderation',
        string $mode = 'flag',
        ?string $openAiApiKey = null,
        ?string $sightengineApiUser = null,
        ?string $sightengineApiSecret = null,
        int $timeout = 15
    ) {
        $this->enabled = $enabled;
        $this->provider = $provider;
        $this->mode = $mode;
        $this->openAiApiKey = $openAiApiKey;
        $this->sightengineApiUser = $sightengineApiUser;
        $this->sightengineApiSecret = $sightengineApiSecret;
        $this->timeout = $timeout;
    }

    public static function fromSettings(): self
    {
        return new self(
            enabled: (bool) settings('external_content_moderation_enabled', false),
            provider: (string) settings('external_content_moderation_provider', 'openai_moderation'),
            mode: (string) settings('external_content_moderation_mode', 'flag'),
            openAiApiKey: settings('external_content_moderation_openai_moderation_api_key'),
            sightengineApiUser: settings('external_content_moderation_sightengine_api_user'),
            sightengineApiSecret: settings('external_content_moderation_sightengine_api_secret'),
            timeout: (int) settings('external_content_moderation_timeout', 15),
        );
    }

    public function isConfigured(): bool
    {
        if ($this->provider === 'sightengine') {
            return filled($this->sightengineApiUser) && filled($this->sightengineApiSecret);
        }

        return filled($this->openAiApiKey);
    }

    /**
     * Active means the admin turned the extension on AND supplied credentials.
     * When inactive, {@see checkText()} and {@see checkImage()} fail open so
     * they never block legitimate content.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    public function mode(): string
    {
        return in_array($this->mode, ['off', 'flag', 'block'], true) ? $this->mode : 'flag';
    }

    /**
     * Apply the configured moderation policy to a user prompt.
     *
     * Returns true only when the caller MUST reject the request
     * (mode = block AND the text was flagged). In `flag` mode a hit is logged
     * for admin review and false is returned (request proceeds); in `off` mode
     * no network call is made. Callers place this BEFORE charging credits so a
     * blocked request never bills the user.
     */
    public function textViolates(string $text, string $context = 'content'): bool
    {
        if ($this->mode() === 'off' || $text === '') {
            return false;
        }

        $result = $this->checkText($text);

        if (! ($result['flagged'] ?? false)) {
            return false;
        }

        if ($this->mode() === 'block') {
            return true;
        }

        \Illuminate\Support\Facades\Log::warning('Content moderation flagged a submission.', [
            'context' => $context,
            'categories' => $result['categories'] ?? [],
            'provider' => $result['provider'] ?? $this->provider,
        ]);

        return false;
    }

    /**
     * Apply the configured moderation policy to a generated image URL.
     *
     * Same contract as {@see textViolates()}: returns true only when the caller
     * MUST reject the output (mode = block AND the image was flagged). In `flag`
     * mode a hit is logged and false returned; in `off` mode no network call is
     * made. Requires an image-capable provider (Sightengine) and a publicly
     * reachable URL — otherwise it fails open (returns false).
     */
    public function imageViolates(string $url, string $context = 'image'): bool
    {
        if ($this->mode() === 'off' || $url === '') {
            return false;
        }

        $result = $this->checkImage($url);

        if (! ($result['flagged'] ?? false)) {
            return false;
        }

        if ($this->mode() === 'block') {
            return true;
        }

        \Illuminate\Support\Facades\Log::warning('Content moderation flagged a generated image.', [
            'context' => $context,
            'categories' => $result['categories'] ?? [],
            'provider' => $result['provider'] ?? $this->provider,
        ]);

        return false;
    }

    /**
     * Check free-form text (prompts) for unsafe content.
     *
     * Fails OPEN: returns flagged=false when the service is inactive or the
     * API errors, so an outage or a missing key never silently blocks users.
     *
     * @return array{flagged: bool, categories: array, provider: string}
     */
    public function checkText(string $text): array
    {
        if (! $this->isEnabled()) {
            return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
        }

        try {
            if ($this->provider === 'sightengine') {
                // Sightengine's text moderation is not wired here; fail open.
                return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
            }

            $response = Http::withToken((string) $this->openAiApiKey)
                ->timeout(max(5, $this->timeout))
                ->post('https://api.openai.com/v1/moderations', [
                    'model' => 'omni-moderation-latest',
                    'input' => $text,
                ]);

            if (! $response->successful()) {
                return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
            }

            $result = $response->json('results.0');

            if (! is_array($result)) {
                return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
            }

            $flagged = (bool) ($result['flagged'] ?? false);
            $categories = [];

            foreach ((array) ($result['categories'] ?? []) as $category => $isTrue) {
                if ($isTrue) {
                    $categories[] = $category;
                }
            }

            return [
                'flagged' => $flagged,
                'categories' => $categories,
                'provider' => $this->provider,
            ];
        } catch (Throwable) {
            return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
        }
    }

    /**
     * Check an image URL (generated media) for unsafe content.
     *
     * Fails OPEN: returns flagged=false when the service is inactive, the
     * provider has no image support, or the API errors.
     *
     * @return array{flagged: bool, categories: array, provider: string}
     */
    public function checkImage(string $url): array
    {
        if (! $this->isEnabled()) {
            return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
        }

        if ($this->provider !== 'sightengine') {
            // OpenAI's moderations endpoint used here is text-only.
            return [
                'flagged' => false,
                'categories' => [],
                'provider' => $this->provider,
                'note' => translate('The active moderation provider does not support image scanning.'),
            ];
        }

        try {
            $response = Http::timeout(max(5, $this->timeout))
                ->get('https://api.sightengine.com/1.0/check.json', [
                    'url' => $url,
                    'models' => 'nudity-2.1,offensive,gore',
                    'api_user' => $this->sightengineApiUser,
                    'api_secret' => $this->sightengineApiSecret,
                ]);

            if (! $response->successful()) {
                return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
            }

            $data = $response->json();

            if (! is_array($data) || ($data['status'] ?? null) !== 'success') {
                return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
            }

            $categories = [];
            $threshold = 0.5;

            $nudity = (array) ($data['nudity'] ?? []);
            foreach (['sexual_activity', 'sexual_display', 'erotica'] as $key) {
                if ((float) ($nudity[$key] ?? 0) > $threshold) {
                    $categories[] = "nudity.{$key}";
                }
            }

            if ((float) ($data['gore']['prob'] ?? 0) > $threshold) {
                $categories[] = 'gore';
            }

            if ((float) ($data['offensive']['prob'] ?? 0) > $threshold) {
                $categories[] = 'offensive';
            }

            return [
                'flagged' => count($categories) > 0,
                'categories' => $categories,
                'provider' => $this->provider,
            ];
        } catch (Throwable) {
            return ['flagged' => false, 'categories' => [], 'provider' => $this->provider];
        }
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('Content moderation is not fully configured.')];
        }

        try {
            if ($this->provider === 'sightengine') {
                $response = Http::timeout(max(5, $this->timeout))
                    ->get('https://api.sightengine.com/1.0/check.json', [
                        'url' => 'https://sightengine.com/assets/img/examples/example5.jpg',
                        'models' => 'nudity-2.1',
                        'api_user' => $this->sightengineApiUser,
                        'api_secret' => $this->sightengineApiSecret,
                    ]);

                $data = $response->json();

                if ($response->successful() && is_array($data) && ($data['status'] ?? null) === 'success') {
                    return ['success' => true, 'message' => translate('Sightengine API credentials are valid.')];
                }

                return [
                    'success' => false,
                    'error' => $data['error']['message'] ?? translate('Sightengine rejected the API credentials.'),
                ];
            }

            $response = Http::withToken((string) $this->openAiApiKey)
                ->timeout(max(5, $this->timeout))
                ->post('https://api.openai.com/v1/moderations', [
                    'model' => 'omni-moderation-latest',
                    'input' => 'test',
                ]);

            if ($response->successful() && is_array($response->json('results'))) {
                return ['success' => true, 'message' => translate('OpenAI moderation API key is valid.')];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message') ?? translate('OpenAI rejected the API key.'),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
