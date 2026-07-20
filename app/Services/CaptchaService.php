<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class CaptchaService
{
    private bool $enabled;
    private ?string $provider;
    private ?string $siteKey;
    private ?string $secretKey;

    public function __construct(bool $enabled = false, ?string $provider = null, ?string $siteKey = null, ?string $secretKey = null)
    {
        $this->enabled = $enabled;
        $this->provider = $provider;
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
    }

    public static function fromSettings(): self
    {
        $provider = (string) settings('external_captcha_provider', 'recaptcha');
        $enabled = (bool) settings('external_captcha_enabled', false);
        $siteKey = (string) settings("external_captcha_{$provider}_site_key", '');
        $secretKey = (string) settings("external_captcha_{$provider}_secret_key", '');

        return new self($enabled, $provider, $siteKey, $secretKey);
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return filled($this->siteKey) && filled($this->secretKey);
    }

    public function frontendConfig(): array
    {
        if (! $this->isEnabled()) {
            return [
                'enabled' => false,
                'provider' => $this->provider ?: 'recaptcha',
                'site_key' => '',
            ];
        }

        return [
            'enabled' => true,
            'provider' => $this->provider,
            'site_key' => $this->siteKey,
        ];
    }

    public function verifyToken(?string $token, ?string $ip = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => true];
        }

        if (! filled($token)) {
            return [
                'success' => false,
                'error' => translate('Please complete the captcha challenge.'),
            ];
        }

        $endpoint = $this->provider === 'hcaptcha'
            ? 'https://hcaptcha.com/siteverify'
            : 'https://www.google.com/recaptcha/api/siteverify';

        $payload = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];

        if (filled($ip)) {
            $payload['remoteip'] = $ip;
        }

        try {
            $response = Http::asForm()
                ->timeout(max(5, (int) settings('external_captcha_timeout', 30)))
                ->post($endpoint, $payload);

            $data = $response->json();
            $passed = (bool) ($data['success'] ?? false);

            if ($passed) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => translate('Captcha verification failed. Please try again.'),
            ];
        } catch (Throwable) {
            return [
                'success' => false,
                'error' => translate('Captcha verification failed. Please try again.'),
            ];
        }
    }

    public function ensureValidToken(?string $token, ?string $ip = null, string $field = 'captcha_token'): void
    {
        $result = $this->verifyToken($token, $ip);

        if ($result['success'] ?? false) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [$result['error'] ?? translate('Captcha verification failed. Please try again.')],
        ]);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('Captcha is not fully configured.')];
        }

        try {
            return ['success' => true, 'message' => translate(':provider captcha keys configured.', [
                'provider' => ucfirst((string) $this->provider),
            ])];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
