<?php

namespace Tests\Feature;

use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\PaymentGateway;
use Database\Seeders\DemoProvisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * demo:reset wipes the database every six hours, so DemoProvisionSeeder is the only thing
 * standing between a reset and a demo with no AI key, no gateways and no social login.
 * It reads .env, which is unverifiable from a test — so what is locked here is the
 * contract: which rows it writes, that secrets land encrypted, and that it stays inert
 * on a normal install.
 */
class DemoProvisionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function provision(array $provisioning, bool $demoEnabled = true): void
    {
        config([
            'demo.enabled' => $demoEnabled,
            'demo.provisioning' => $provisioning,
        ]);

        $this->seed(DemoProvisionSeeder::class);
    }

    public function test_it_does_nothing_when_demo_mode_is_off(): void
    {
        PaymentGateway::create(['slug' => 'stripe', 'name' => 'Stripe']);

        $this->provision([
            'ai_keys' => ['openrouter' => 'sk-or-live'],
            'gateways' => ['stripe' => [
                'publishable_key' => 'pk', 'secret_key' => 'sk', 'webhook_secret' => 'whsec',
            ]],
            'oauth' => ['google' => ['client_id' => 'gid', 'client_secret' => 'gsecret']],
            'captcha' => ['site_key' => 'site', 'secret_key' => 'secret'],
        ], demoEnabled: false);

        $this->assertSame(0, AiKey::count());
        $this->assertFalse(PaymentGateway::where('slug', 'stripe')->value('is_enabled'));
        $this->assertBlank(settings('social_login_google_client_id'));
        $this->assertFalse((bool) settings('external_captcha_enabled', false));
    }

    public function test_blank_credentials_provision_nothing(): void
    {
        PaymentGateway::create(['slug' => 'stripe', 'name' => 'Stripe']);

        $this->provision([
            'ai_keys' => ['openrouter' => null],
            'gateways' => ['stripe' => [
                'publishable_key' => '', 'secret_key' => '', 'webhook_secret' => '',
            ]],
            'oauth' => ['google' => ['client_id' => '', 'client_secret' => '']],
            'captcha' => ['site_key' => '', 'secret_key' => ''],
        ]);

        $this->assertSame(0, AiKey::count());
        $this->assertFalse(PaymentGateway::where('slug', 'stripe')->value('is_enabled'));
        $this->assertFalse((bool) settings('external_captcha_enabled', false));
    }

    public function test_it_creates_encrypted_ai_keys_and_repoints_the_default_and_fallback_models(): void
    {
        AiModel::create(['slug' => 'deepseek/deepseek-v4-flash', 'name' => 'DeepSeek Flash', 'provider' => 'openrouter']);
        AiModel::create(['slug' => 'gemini-3.1-flash-lite', 'name' => 'Flash Lite', 'provider' => 'google']);

        $this->provision([
            'ai_keys' => ['openrouter' => 'sk-or-v1-demo', 'google' => 'AIza-demo'],
            'ai_default_model' => 'deepseek/deepseek-v4-flash',
            'ai_fallback_model' => 'gemini-3.1-flash-lite',
        ]);

        $key = AiKey::firstWhere('provider', 'openrouter');
        $this->assertNotNull($key);
        $this->assertTrue((bool) $key->is_active);
        $this->assertSame('sk-or-v1-demo', $key->api_key, 'the accessor should decrypt back to the raw key');
        $this->assertNotSame('sk-or-v1-demo', $key->getRawOriginal('api_key'), 'the key must not be stored in plaintext');
        $this->assertSame('AIza-demo', AiKey::firstWhere('provider', 'google')?->api_key);

        $this->assertSame('openrouter', settings('default_ai_provider'));
        $this->assertSame('deepseek/deepseek-v4-flash', settings('default_ai_model'));
        $this->assertSame('google', settings('fallback_ai_provider'));
        $this->assertSame('gemini-3.1-flash-lite', settings('fallback_ai_model'));
    }

    public function test_it_leaves_a_model_alone_when_that_provider_has_no_key(): void
    {
        settings_set('default_ai_provider', 'openai', 'string', 'ai');
        settings_set('fallback_ai_provider', 'openai', 'string', 'ai');
        AiModel::create(['slug' => 'gpt-5.4-mini', 'name' => 'Mini', 'provider' => 'openai']);
        AiModel::create(['slug' => 'deepseek/deepseek-v4-flash', 'name' => 'DeepSeek Flash', 'provider' => 'openrouter']);

        $this->provision([
            'ai_keys' => ['openrouter' => 'sk-or-v1-demo'],
            'ai_default_model' => 'deepseek/deepseek-v4-flash',
            // No google key was provisioned, so this must not be applied.
            'ai_fallback_model' => 'gemini-3.1-flash-lite',
        ]);

        $this->assertSame('openrouter', settings('default_ai_provider'), 'the default still applies on its own');
        $this->assertSame('openai', settings('fallback_ai_provider'), 'the keyless fallback must stay as seeded');
    }

    public function test_the_shipped_default_and_fallback_slugs_exist_in_the_seeded_catalog(): void
    {
        $this->seed(\Database\Seeders\AiModelSeeder::class);

        foreach (['ai_default_model', 'ai_fallback_model'] as $role) {
            $slug = config("demo.provisioning.{$role}");
            $model = AiModel::firstWhere('slug', $slug);

            $this->assertNotNull($model, "demo.provisioning.{$role} '{$slug}' is not a seeded ai_models slug");
            $this->assertArrayHasKey(
                $model->provider,
                config('demo.provisioning.ai_keys'),
                "'{$slug}' is a {$model->provider} model but there is no {$model->provider} key slot to provision"
            );
        }
    }

    public function test_a_fully_configured_gateway_is_enabled_in_test_mode_with_encrypted_credentials(): void
    {
        PaymentGateway::create(['slug' => 'stripe', 'name' => 'Stripe', 'is_test_mode' => false]);

        $this->provision([
            'gateways' => ['stripe' => [
                'publishable_key' => 'pk_test_1',
                'secret_key' => 'sk_test_1',
                'webhook_secret' => 'whsec_1',
            ]],
        ]);

        $gateway = PaymentGateway::firstWhere('slug', 'stripe');
        $this->assertTrue((bool) $gateway->is_enabled);
        $this->assertTrue((bool) $gateway->is_test_mode);
        $this->assertSame('sk_test_1', $gateway->getCredential('secret_key'));
        $this->assertStringNotContainsString('sk_test_1', json_encode($gateway->getRawOriginal('credentials')));
    }

    public function test_a_partially_configured_gateway_is_skipped(): void
    {
        PaymentGateway::create(['slug' => 'paddle', 'name' => 'Paddle']);

        $this->provision([
            'gateways' => ['paddle' => [
                'api_key' => 'pdl_key',
                'client_token' => 'live_token',
                'webhook_secret' => '',
            ]],
        ]);

        $gateway = PaymentGateway::firstWhere('slug', 'paddle');
        $this->assertFalse((bool) $gateway->is_enabled);
        $this->assertBlank($gateway->credentials);
    }

    public function test_oauth_needs_both_halves_before_a_provider_is_enabled(): void
    {
        $this->provision([
            'oauth' => [
                'google' => ['client_id' => 'google-id', 'client_secret' => 'google-secret'],
                'twitter' => ['client_id' => 'twitter-id', 'client_secret' => ''],
            ],
        ]);

        $this->assertSame('google-id', settings('social_login_google_client_id'));
        $this->assertSame('google-secret', settings('social_login_google_client_secret'));
        $this->assertTrue((bool) settings('social_login_google_enabled'));

        $this->assertBlank(settings('social_login_twitter_client_id'));
        $this->assertFalse((bool) settings('social_login_twitter_enabled', false));
    }

    public function test_an_extension_is_configured_selected_and_switched_on(): void
    {
        $this->provision([
            'extensions' => [
                'ip_geolocation' => ['ipinfo' => ['token' => 'ipinfo-token']],
                'spam_filter' => ['akismet' => ['api_key' => 'akismet-key', 'site_url' => 'https://demo.test']],
            ],
        ]);

        $this->assertSame('ipinfo', settings('external_ip_geolocation_provider'));
        $this->assertTrue((bool) settings('external_ip_geolocation_enabled'));
        $this->assertSame('ipinfo-token', settings('external_ip_geolocation_ipinfo_token'));

        $this->assertSame('akismet-key', settings('external_spam_filter_akismet_api_key'));
        $this->assertSame('https://demo.test', settings('external_spam_filter_akismet_site_url'));

        // Declared secrets are encrypted at rest, options are not — as the admin writes them.
        $blob = \App\Models\Setting::where('key', 'like', '%external_apis%')->orWhere('key', 'like', 'external_%')->pluck('value')->implode(' ');
        $this->assertStringNotContainsString('akismet-key', $blob, 'a declared secret must not be stored in plaintext');
    }

    public function test_the_first_fully_configured_provider_wins_and_partial_ones_are_skipped(): void
    {
        $this->provision([
            'extensions' => [
                'currency_rates' => [
                    // Declared first but incomplete — must not win, and must not be enabled.
                    'exchangerate' => ['api_key' => ''],
                    'fixer' => ['api_key' => 'fixer-key'],
                ],
                'sms_gateway' => [
                    'twilio' => ['account_sid' => 'sid', 'auth_token' => ''],
                ],
            ],
        ]);

        $this->assertSame('fixer', settings('external_currency_rates_provider'));
        $this->assertTrue((bool) settings('external_currency_rates_enabled'));

        $this->assertBlank(settings('external_sms_gateway_provider'));
        $this->assertFalse((bool) settings('external_sms_gateway_enabled', false));
        $this->assertBlank(settings('external_sms_gateway_twilio_account_sid'));
    }

    public function test_a_provider_with_no_secrets_is_configured_from_its_options_alone(): void
    {
        $this->provision([
            'extensions' => [
                'web_analytics' => ['ga4' => ['measurement_id' => 'G-DEMO123']],
            ],
        ]);

        $this->assertSame('ga4', settings('external_web_analytics_provider'));
        $this->assertTrue((bool) settings('external_web_analytics_enabled'));
        $this->assertSame('G-DEMO123', settings('external_web_analytics_ga4_measurement_id'));
    }

    /**
     * Every extension on the admin's Extensions screen must be provisionable, or it quietly
     * stays unconfigured on the demo forever — nobody notices a missing integration the way
     * they notice a broken one. `captcha` is the documented exception: it keeps its own
     * block so it is never auto-enabled.
     */
    public function test_every_catalog_integration_and_provider_is_provisionable(): void
    {
        $provisioning = config('demo.provisioning.extensions');

        foreach (config('external-tools.integrations') as $integration => $definition) {
            if ($integration === 'captcha') {
                $this->assertArrayNotHasKey($integration, $provisioning, 'captcha must stay in its own block');

                continue;
            }

            $this->assertArrayHasKey($integration, $provisioning, "integration '{$integration}' has no demo provisioning entry");

            $declared = array_keys((array) ($definition['providers'] ?? []));
            $provisioned = array_keys($provisioning[$integration]);
            sort($declared);
            sort($provisioned);

            $this->assertSame($declared, $provisioned, "'{$integration}' does not offer every provider for demo provisioning");
        }
    }

    public function test_every_extension_field_matches_the_external_tools_catalog(): void
    {
        foreach (config('demo.provisioning.extensions') as $integration => $providers) {
            $declared = config("external-tools.integrations.{$integration}.providers");

            $this->assertNotNull($declared, "integration '{$integration}' is not in external-tools.php");

            foreach ($providers as $provider => $fields) {
                $this->assertArrayHasKey($provider, $declared, "'{$integration}' has no provider '{$provider}'");

                $known = array_merge((array) ($declared[$provider]['secrets'] ?? []), (array) ($declared[$provider]['options'] ?? []));
                sort($known);
                $provisioned = array_keys($fields);
                sort($provisioned);

                $this->assertSame($known, $provisioned, "demo provisioning for '{$integration}/{$provider}' does not match its declared fields");
            }
        }
    }

    public function test_recaptcha_keys_are_stored_but_the_guard_stays_off_by_default(): void
    {
        $this->provision([
            'captcha' => ['site_key' => 'site-key-1', 'secret_key' => 'secret-key-1'],
        ]);

        $this->assertSame('recaptcha', settings('external_captcha_provider'));
        $this->assertSame('site-key-1', settings('external_captcha_recaptcha_site_key'));
        $this->assertSame('secret-key-1', settings('external_captcha_recaptcha_secret_key'));
        $this->assertFalse((bool) settings('external_captcha_enabled'));

        $this->assertFalse(
            \App\Services\CaptchaService::fromSettings()->isEnabled(),
            'a wrong-domain site key must not be able to lock the demo out of sign-in'
        );
    }

    public function test_recaptcha_guard_switches_on_when_explicitly_enabled(): void
    {
        $this->provision([
            'captcha' => ['enabled' => true, 'site_key' => 'site-key-1', 'secret_key' => 'secret-key-1'],
        ]);

        $this->assertTrue((bool) settings('external_captcha_enabled'));

        $captcha = \App\Services\CaptchaService::fromSettings();
        $this->assertTrue($captcha->isEnabled(), 'CaptchaService must read back what the seeder wrote');
    }

    public function test_enabling_recaptcha_without_keys_provisions_nothing(): void
    {
        $this->provision([
            'captcha' => ['enabled' => true, 'site_key' => '', 'secret_key' => ''],
        ]);

        $this->assertBlank(settings('external_captcha_provider'));
        $this->assertFalse((bool) settings('external_captcha_enabled', false));
    }

    /**
     * The provisioning map is hand-written, so nothing stops it drifting from the gateway
     * definitions — a renamed field key would provision a gateway that then fails at
     * checkout, six hours after anyone last looked at it.
     */
    public function test_every_gateway_is_provisionable_with_the_field_names_it_declares(): void
    {
        $provisioning = config('demo.provisioning.gateways');

        foreach (config('payment-gateways') as $slug => $gateway) {
            $this->assertArrayHasKey($slug, $provisioning, "gateway '{$slug}' has no demo provisioning entry");

            $declared = array_column($gateway['fields'], 'key');
            sort($declared);
            $provisioned = array_keys($provisioning[$slug]);
            sort($provisioned);

            $this->assertSame($declared, $provisioned, "demo provisioning for '{$slug}' does not match its declared fields");
        }
    }

    /**
     * The --demo build uncomments the DEMO_* block in .env.example so the demo host gets a
     * fill-in-the-blanks checklist. A key the regex misses never reaches the seeder.
     */
    public function test_every_provisioning_env_var_is_listed_in_env_example(): void
    {
        $example = file_get_contents(base_path('.env.example'));
        preg_match_all('/^# (DEMO_[A-Z0-9_]+)=/m', $example, $matches);
        $listed = $matches[1];

        $expected = [
            'DEMO_AI_KEY_OPENROUTER', 'DEMO_AI_KEY_GOOGLE',
            'DEMO_AI_DEFAULT_MODEL', 'DEMO_AI_FALLBACK_MODEL',
            'DEMO_RECAPTCHA_ENABLED',
            'DEMO_LOGO_LIGHT', 'DEMO_LOGO_DARK', 'DEMO_FAVICON_ICO', 'DEMO_FAVICON_PNG',
        ];

        foreach (array_keys(config('payment-gateways')) as $slug) {
            foreach (config("demo.provisioning.gateways.{$slug}") as $field => $unused) {
                $expected[] = 'DEMO_'.strtoupper($slug).'_'.strtoupper($field);
            }
        }

        foreach (config('demo.provisioning.extensions') as $providers) {
            foreach ($providers as $provider => $fields) {
                foreach (array_keys($fields) as $field) {
                    $expected[] = 'DEMO_EXT_'.strtoupper($provider).'_'.strtoupper($field);
                }
            }
        }

        foreach ($expected as $key) {
            $this->assertContains($key, $listed, "{$key} is missing from the .env.example DEMO_ block");
        }
    }

    /**
     * The reset runs every six hours forever, so "works once" is not good enough — this is
     * the shape of bug that took down the old migrate:refresh reset on its second run.
     */
    public function test_it_is_safe_to_run_twice(): void
    {
        PaymentGateway::create(['slug' => 'stripe', 'name' => 'Stripe']);

        $provisioning = [
            'ai_keys' => ['openrouter' => 'sk-or-v1-demo'],
            'gateways' => ['stripe' => [
                'publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_1',
            ]],
            'oauth' => ['google' => ['client_id' => 'gid', 'client_secret' => 'gsecret']],
            'captcha' => ['site_key' => 'site', 'secret_key' => 'secret'],
        ];

        $this->provision($provisioning);
        $this->provision($provisioning);

        $this->assertSame('sk_test_1', PaymentGateway::firstWhere('slug', 'stripe')->getCredential('secret_key'));
        $this->assertSame('gsecret', settings('social_login_google_client_secret'));
        $this->assertSame('sk-or-v1-demo', AiKey::firstWhere('provider', 'openrouter')->api_key);
    }

    /**
     * Branding is the one thing here whose source is a FILE rather than an env string, so
     * these cover the copy as well as the setting: the demo cannot upload a logo through
     * the admin panel (DemoMode blocks the write) and demo:sweep-uploads deletes the
     * storage trees on every reset, so the copy has to happen again each time.
     */
    public function test_it_copies_branding_images_onto_the_public_disk_and_points_the_settings_at_them(): void
    {
        Storage::fake('public');
        $logo = $this->writeDemoAsset('logo-light-'.getmypid().'.svg', '<svg/>');
        $icon = $this->writeDemoAsset('favicon-'.getmypid().'.png', 'PNG');

        $this->provision(['branding' => [
            'logo_light' => basename($logo),
            'favicon_png' => basename($icon),
        ]]);

        // The setting stores the COPY's key, not the source path — media_url() resolves
        // stored keys against the public disk, so 'demo-assets/…' would 404.
        $this->assertSame('branding/'.basename($logo), settings('site_logo_light'));
        $this->assertSame('branding/'.basename($icon), settings('site_favicon_png'));
        Storage::disk('public')->assertExists('branding/'.basename($logo));
        $this->assertSame('<svg/>', Storage::disk('public')->get('branding/'.basename($logo)));

        // Untouched slots stay empty rather than pointing at a file that is not there.
        $this->assertBlank(settings('site_logo_dark'));
    }

    public function test_a_branding_filename_with_no_file_behind_it_is_skipped(): void
    {
        Storage::fake('public');

        $this->provision(['branding' => ['logo_light' => 'does-not-exist-'.getmypid().'.svg']]);

        $this->assertBlank(settings('site_logo_light'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_branding_value_cannot_escape_the_demo_assets_directory(): void
    {
        Storage::fake('public');

        // A demo host's .env is the least trusted config in the product; the value is
        // reduced to a filename, so a traversal resolves to a name that does not exist.
        $this->provision(['branding' => ['logo_light' => '../../.env']]);

        $this->assertBlank(settings('site_logo_light'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_branding_is_not_provisioned_when_demo_mode_is_off(): void
    {
        Storage::fake('public');
        $logo = $this->writeDemoAsset('logo-off-'.getmypid().'.svg', '<svg/>');

        $this->provision(['branding' => ['logo_light' => basename($logo)]], demoEnabled: false);

        $this->assertBlank(settings('site_logo_light'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** The filenames config ships as defaults must be the ones the README documents. */
    public function test_the_shipped_branding_defaults_match_the_documented_filenames(): void
    {
        $readme = file_get_contents(base_path('public/demo-assets/README.md'));

        foreach (config('demo.provisioning.branding') as $slot => $filename) {
            $this->assertNotEmpty($filename, "demo.provisioning.branding.{$slot} has no default filename");
            $this->assertStringContainsString(
                $filename,
                $readme,
                "public/demo-assets/README.md does not document {$filename}"
            );
        }
    }

    /**
     * Written into the real directory (the seeder resolves it from base_path) and removed
     * again, so a failed run cannot leave an image behind that a later demo build ships.
     */
    private function writeDemoAsset(string $name, string $contents): string
    {
        $path = base_path('public/demo-assets/'.$name);
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    private function assertBlank(mixed $value): void
    {
        $this->assertTrue(blank($value), 'Expected a blank value, got: '.var_export($value, true));
    }
}
