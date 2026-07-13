<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class EmailValidationService
{
    private bool $enabled;
    private ?string $provider;
    private ?string $zeroBounceApiKey;
    private ?string $neverBounceApiKey;
    private int $timeout;

    public function __construct(
        bool $enabled = false,
        ?string $provider = null,
        ?string $zeroBounceApiKey = null,
        ?string $neverBounceApiKey = null,
        int $timeout = 15
    ) {
        $this->enabled = $enabled;
        $this->provider = $provider;
        $this->zeroBounceApiKey = $zeroBounceApiKey;
        $this->neverBounceApiKey = $neverBounceApiKey;
        $this->timeout = $timeout;
    }

    public static function fromSettings(): self
    {
        return new self(
            enabled: (bool) settings('external_email_validation_enabled', false),
            provider: (string) settings('external_email_validation_provider', 'zerobounce'),
            zeroBounceApiKey: settings('external_email_validation_zerobounce_api_key'),
            neverBounceApiKey: settings('external_email_validation_neverbounce_api_key'),
            timeout: (int) settings('external_email_validation_timeout', 15),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->activeApiKey());
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    /**
     * Check whether an email address is deliverable / not disposable.
     *
     * Fails OPEN: returns valid=true, disposable=false when the service is
     * inactive or the provider API errors, so an outage or missing key never
     * blocks legitimate signups.
     *
     * @return array{valid: bool, disposable: bool, reason: string, provider: string}
     */
    public function validate(string $email): array
    {
        if (! $this->isEnabled()) {
            return $this->passThrough();
        }

        try {
            return match ($this->provider) {
                'neverbounce' => $this->validateWithNeverBounce($email),
                default => $this->validateWithZeroBounce($email),
            };
        } catch (Throwable) {
            return $this->passThrough();
        }
    }

    /**
     * Decide whether a signup email should be rejected, honoring the admin's
     * "what to block" setting: `disposable_only` blocks just disposable
     * addresses; `all` (default) also blocks undeliverable/invalid ones.
     * Fails open — a pass-through result (valid, non-disposable) is never rejected.
     */
    public function shouldReject(string $email): bool
    {
        $result = $this->validate($email);
        $mode = (string) settings('external_email_validation_mode', 'all');

        if ($result['disposable']) {
            return true;
        }

        return $mode !== 'disposable_only' && ! $result['valid'];
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('No API key configured.')];
        }

        try {
            return match ($this->provider) {
                'neverbounce' => $this->testNeverBounce(),
                default => $this->testZeroBounce(),
            };
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function validateWithZeroBounce(string $email): array
    {
        $response = Http::timeout(max(5, $this->timeout))
            ->get('https://api.zerobounce.net/v2/validate', [
                'api_key' => $this->zeroBounceApiKey,
                'email' => $email,
            ]);

        $data = $response->json();
        $status = (string) ($data['status'] ?? '');
        $subStatus = (string) ($data['sub_status'] ?? '');

        return [
            'valid' => $status === 'valid',
            'disposable' => str_contains($subStatus, 'disposable'),
            'reason' => $subStatus,
            'provider' => 'zerobounce',
        ];
    }

    private function validateWithNeverBounce(string $email): array
    {
        $response = Http::timeout(max(5, $this->timeout))
            ->get('https://api.neverbounce.com/v4/single/check', [
                'key' => $this->neverBounceApiKey,
                'email' => $email,
            ]);

        $data = $response->json();
        $result = (string) ($data['result'] ?? '');

        return [
            'valid' => $result === 'valid',
            'disposable' => $result === 'disposable',
            'reason' => $result,
            'provider' => 'neverbounce',
        ];
    }

    private function testZeroBounce(): array
    {
        $response = Http::timeout(max(5, $this->timeout))
            ->get('https://api.zerobounce.net/v2/getcredits', [
                'api_key' => $this->zeroBounceApiKey,
            ]);

        $data = $response->json();
        $credits = $data['Credits'] ?? null;

        if ($credits !== null && (int) $credits >= 0) {
            return ['success' => true, 'message' => translate('ZeroBounce API key is valid. Credits remaining: :credits', [
                'credits' => (string) $credits,
            ])];
        }

        return ['success' => false, 'error' => translate('ZeroBounce rejected the API key.')];
    }

    private function testNeverBounce(): array
    {
        $response = Http::timeout(max(5, $this->timeout))
            ->get('https://api.neverbounce.com/v4/account/info', [
                'key' => $this->neverBounceApiKey,
            ]);

        $data = $response->json();
        $status = (string) ($data['status'] ?? '');

        return $status === 'success'
            ? ['success' => true, 'message' => translate('NeverBounce API key is valid.')]
            : ['success' => false, 'error' => translate('NeverBounce rejected the API key.')];
    }

    private function activeApiKey(): ?string
    {
        return $this->provider === 'neverbounce'
            ? $this->neverBounceApiKey
            : $this->zeroBounceApiKey;
    }

    private function passThrough(): array
    {
        return [
            'valid' => true,
            'disposable' => false,
            'reason' => '',
            'provider' => (string) $this->provider,
        ];
    }
}
