<?php

declare(strict_types=1);

namespace App\Support;

class IntegrationSettings
{
    /**
     * Resolve the admin-selected provider for an external integration and read
     * ALL of its declared secrets and options from settings.
     *
     * Reading every declared secret (rather than assuming a single "api_key")
     * is what makes multi-secret providers work — e.g. Amazon Polly
     * (access_key + secret_key), PlayHT (user_id + api_key), Google TTS
     * (service_account_json), Kling (api_key + secret).
     *
     * @return array{provider: ?string, secrets: array<string, ?string>, options: array<string, string>}
     */
    public static function forSelectedProvider(string $integration, ?string $default = null): array
    {
        $providers = config("external-tools.integrations.{$integration}.providers", []);
        $providerKeys = array_keys($providers);
        $fallback = $default ?? ($providerKeys[0] ?? null);

        $provider = settings("external_{$integration}_provider", $fallback);
        if (! in_array($provider, $providerKeys, true)) {
            $provider = $fallback;
        }

        $providerConfig = $providers[$provider] ?? [];

        $secrets = [];
        foreach ($providerConfig['secrets'] ?? [] as $key) {
            $secrets[$key] = settings("external_{$integration}_{$provider}_{$key}");
        }

        $options = [];
        foreach ($providerConfig['options'] ?? [] as $key) {
            $options[$key] = (string) settings("external_{$integration}_{$provider}_{$key}", '');
        }

        return ['provider' => $provider, 'secrets' => $secrets, 'options' => $options];
    }

    /**
     * Whether every declared secret for the selected provider is present.
     *
     * @param  array<string, ?string>  $secrets
     */
    public static function allSecretsPresent(array $secrets): bool
    {
        if ($secrets === []) {
            return false;
        }

        foreach ($secrets as $value) {
            if (blank($value)) {
                return false;
            }
        }

        return true;
    }
}
