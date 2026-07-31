<?php

namespace Database\Seeders;

use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Restores the demo site's live API credentials after demo:reset.
 *
 * demo:reset runs migrate:fresh, so ai_keys, payment_gateways.credentials and every
 * settings row are gone by the time this runs. .env survives the wipe, which makes it
 * the only viable source of truth — and demo mode blocks admin writes, so re-entering
 * these through the UI after a reset is not possible either.
 *
 * Everything here is driven by config('demo.provisioning'); a blank value is skipped, so
 * on an install that sets none of the DEMO_* credential vars this seeder is a no-op. It
 * is called only from DemoReset — never from DatabaseSeeder — so a buyer running db:seed
 * never touches credential tables.
 *
 * @see \App\Console\Commands\DemoReset
 */
class DemoProvisionSeeder extends Seeder
{
    public function run(): void
    {
        // Belt and braces. DemoReset has already checked this, but the seeder is a public
        // class name someone could pass to db:seed --class by hand.
        if (! config('demo.enabled')) {
            $this->note('demo mode is off — nothing provisioned', true);

            return;
        }

        $this->provisionAiKeys();
        $this->provisionGateways();
        $this->provisionOauth();
        $this->provisionExtensions();
        $this->provisionCaptcha();
        $this->provisionBranding();
    }

    /**
     * The demo host's logo and favicon.
     *
     * Same reason as the credentials above: the admin panel cannot set these on a demo
     * (DemoMode blocks the branding save) and nothing set by hand survives demo:reset,
     * which wipes both the settings table and the storage trees before this runs. The
     * source images therefore live in public/demo-assets/ — part of the release, not the
     * writable tree — and are copied onto the public disk on every reset.
     *
     * The setting stores the COPY's relative key, never the source path: media_url()
     * resolves stored keys against the public disk, so a value of 'demo-assets/logo.svg'
     * would render as '/storage/demo-assets/logo.svg' and 404.
     */
    private function provisionBranding(): void
    {
        // Setting key => config entry under demo.provisioning.branding.
        $map = [
            'site_logo_light' => 'logo_light',
            'site_logo_dark' => 'logo_dark',
            'site_favicon_ico' => 'favicon_ico',
            'site_favicon_png' => 'favicon_png',
        ];

        $sourceDir = base_path('public/demo-assets');
        $provisioned = [];
        $missing = [];

        foreach ($map as $settingKey => $configKey) {
            // basename() rather than the raw value: these come from .env on a public host,
            // and a filename is all this is ever meant to be.
            $file = basename(trim((string) config("demo.provisioning.branding.{$configKey}", '')));

            if ($file === '' || $file === '.') {
                continue;
            }

            $source = $sourceDir.DIRECTORY_SEPARATOR.$file;

            if (! is_file($source)) {
                $missing[] = $file;

                continue;
            }

            $key = 'branding/'.$file;
            Storage::disk('public')->put($key, (string) file_get_contents($source));
            settings_set($settingKey, $key, 'string', 'branding');
            $provisioned[] = $file;
        }

        if ($provisioned !== []) {
            $this->note('branding: '.implode(', ', $provisioned).' copied to the public disk');
        }

        if ($missing !== []) {
            $this->note(
                'branding: not found in public/demo-assets — '.implode(', ', $missing)
                .' (that slot stays empty; the header falls back to the site name)',
                true
            );
        }
    }

    /**
     * Platform AI keys. AiKey::$api_key encrypts on write, so these must go through the
     * model rather than a raw insert.
     */
    private function provisionAiKeys(): void
    {
        $keys = array_filter((array) config('demo.provisioning.ai_keys', []), 'filled');

        if ($keys === []) {
            return;
        }

        foreach ($keys as $provider => $key) {
            AiKey::create([
                'provider' => $provider,
                'api_key' => trim((string) $key),
                'label' => 'Demo '.$provider,
                'is_active' => true,
            ]);

            $this->note("ai key: {$provider}");
        }

        $providers = array_keys($keys);

        $this->pointModelAtProvisionedProvider('default', config('demo.provisioning.ai_default_model'), $providers);
        $this->pointModelAtProvisionedProvider('fallback', config('demo.provisioning.ai_fallback_model'), $providers);
    }

    /**
     * Repoint the default (or fallback) model at a provider that actually has a key.
     *
     * Without this the demo keeps FoundationSeeder's openai defaults and every generation
     * fails with "no API key" — on a demo whose whole point is the generation flow. The two
     * roles are resolved independently so the fallback can sit on a different provider,
     * which is the only arrangement where a fallback is worth having: if the default
     * provider is down or rate-limited, a fallback on the same key pool is down too.
     *
     * @param  'default'|'fallback'  $role
     * @param  array<int, string>  $providers  provider slugs that just received a key
     */
    private function pointModelAtProvisionedProvider(string $role, mixed $slug, array $providers): void
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return;
        }

        $model = AiModel::where('slug', $slug)->first();

        if (! $model) {
            $this->note("{$role} model '{$slug}' not in the catalog — left as seeded", true);

            return;
        }

        if (! in_array($model->provider, $providers, true)) {
            $this->note("{$role} model '{$slug}' needs a {$model->provider} key — left as seeded", true);

            return;
        }

        settings_set("{$role}_ai_provider", $model->provider, 'string', 'ai');
        settings_set("{$role}_ai_model", $model->slug, 'string', 'ai');

        $this->note("{$role} model: {$model->provider} / {$model->slug}");
    }

    /**
     * Payment gateway credentials, always in test mode.
     *
     * A gateway is enabled only when every field config/payment-gateways.php declares for
     * it is present — a half-configured gateway that is switched on fails at checkout,
     * which is worse for a demo than the gateway simply being absent.
     */
    private function provisionGateways(): void
    {
        foreach ((array) config('demo.provisioning.gateways', []) as $slug => $fields) {
            $fields = (array) $fields;
            $present = array_filter($fields, 'filled');

            if ($present === []) {
                continue;
            }

            if (count($present) !== count($fields)) {
                $missing = implode(', ', array_keys(array_diff_key($fields, $present)));
                $this->note("gateway {$slug} skipped — missing: {$missing}", true);

                continue;
            }

            $gateway = PaymentGateway::where('slug', $slug)->first();

            if (! $gateway) {
                $this->note("gateway {$slug} skipped — no such row in payment_gateways", true);

                continue;
            }

            $gateway->update([
                'credentials' => PaymentGateway::encryptCredentials(
                    array_map(fn ($value) => trim((string) $value), $present)
                ),
                'is_test_mode' => true,
                'is_enabled' => true,
            ]);

            $this->note("gateway: {$slug} (test mode)");
        }
    }

    /**
     * Social login apps. Mirrors what OauthSettingsController::update() writes, including
     * its rule that a provider needs both halves before it may be enabled.
     */
    private function provisionOauth(): void
    {
        foreach ((array) config('demo.provisioning.oauth', []) as $provider => $credentials) {
            $clientId = trim((string) (($credentials['client_id'] ?? '')));
            $clientSecret = trim((string) (($credentials['client_secret'] ?? '')));

            if ($clientId === '' || $clientSecret === '') {
                if ($clientId !== '' || $clientSecret !== '') {
                    $this->note("oauth {$provider} skipped — needs both client id and secret", true);
                }

                continue;
            }

            settings_set("social_login_{$provider}_client_id", $clientId, 'string', 'social');
            settings_set("social_login_{$provider}_client_secret", $clientSecret, 'encrypted', 'social');
            settings_set("social_login_{$provider}_enabled", true, 'boolean', 'social');

            $this->note("oauth: {$provider}");
        }
    }

    /**
     * Everything on Settings → Extensions.
     *
     * For each integration the first provider whose declared secrets are all present is
     * selected and switched on, so the operator configures an integration by filling in
     * whichever account they actually hold — no provider-picking env var to keep in step.
     * Field names and secret/option classification come from config/external-tools.php
     * rather than the demo config, so a provider that renames a field cannot be silently
     * provisioned into settings keys nothing reads.
     */
    private function provisionExtensions(): void
    {
        foreach ((array) config('demo.provisioning.extensions', []) as $integration => $providers) {
            $declared = (array) config("external-tools.integrations.{$integration}.providers", []);

            foreach ((array) $providers as $provider => $fields) {
                $values = array_map(fn ($value) => trim((string) $value), array_filter((array) $fields, 'filled'));

                if ($values === []) {
                    continue;
                }

                $secrets = (array) ($declared[$provider]['secrets'] ?? []);
                $options = (array) ($declared[$provider]['options'] ?? []);

                if (! isset($declared[$provider])) {
                    $this->note("extension {$integration}/{$provider} skipped — not in external-tools.php", true);

                    continue;
                }

                $missing = array_diff($secrets, array_keys($values));

                if ($missing !== []) {
                    $this->note("extension {$integration}/{$provider} skipped — missing: ".implode(', ', $missing), true);

                    continue;
                }

                foreach ($values as $field => $value) {
                    $isSecret = in_array($field, $secrets, true);

                    if (! $isSecret && ! in_array($field, $options, true)) {
                        $this->note("extension {$integration}/{$provider}: '{$field}' is not a declared field — ignored", true);

                        continue;
                    }

                    settings_set(
                        "external_{$integration}_{$provider}_{$field}",
                        $value,
                        $isSecret ? 'encrypted' : 'string',
                        'external_apis'
                    );
                }

                settings_set("external_{$integration}_provider", $provider, 'string', 'external_apis');
                settings_set("external_{$integration}_enabled", true, 'boolean', 'external_apis');

                $this->note("extension: {$integration} → {$provider}");

                // First fully-configured provider wins; the rest of this integration's
                // credentials would only fight over the same _provider setting.
                break;
            }
        }
    }

    /**
     * Google reCAPTCHA, stored the way AiManagementController writes external tool
     * secrets so CaptchaService::fromSettings() reads it back unchanged.
     *
     * Provisioning the keys and turning the guard on are deliberately separate steps: a
     * site key that does not list the demo domain fails every challenge, and captcha
     * guards sign-in — which would lock visitors out of the demo until the next reset.
     */
    private function provisionCaptcha(): void
    {
        $siteKey = trim((string) config('demo.provisioning.captcha.site_key', ''));
        $secretKey = trim((string) config('demo.provisioning.captcha.secret_key', ''));

        if ($siteKey === '' || $secretKey === '') {
            return;
        }

        $enabled = (bool) config('demo.provisioning.captcha.enabled', false);

        settings_set('external_captcha_provider', 'recaptcha', 'string', 'external_apis');
        settings_set('external_captcha_recaptcha_site_key', $siteKey, 'encrypted', 'external_apis');
        settings_set('external_captcha_recaptcha_secret_key', $secretKey, 'encrypted', 'external_apis');
        settings_set('external_captcha_enabled', $enabled, 'boolean', 'external_apis');

        $this->note('captcha: recaptcha keys stored, guard '.($enabled ? 'ON' : 'off (set DEMO_RECAPTCHA_ENABLED=true to switch on)'));
    }

    private function note(string $message, bool $warn = false): void
    {
        if ($warn) {
            $this->command?->warn("  {$message}");

            return;
        }

        $this->command?->line("  {$message}");
    }
}
