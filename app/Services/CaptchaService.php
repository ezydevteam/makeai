<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class CaptchaService
{
    private ?string $provider;
    private ?string $secretKey;

    public function __construct(?string $provider = null, ?string $secretKey = null)
    {
        $this->provider = $provider;
        $this->secretKey = $secretKey;
    }

    public static function fromSettings(): self
    {
        $provider = settings('external_captcha_provider', 'recaptcha');
        $secretKey = settings("external_captcha_{$provider}_secret_key");

        return new self($provider, $secretKey);
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No secret key configured.'];
        }

        try {
            return ['success' => true, 'message' => "{$this->provider} key configured."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
