<?php

namespace Tests\Feature;

use App\Models\AddonSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 2 of the settings refactor: per-addon config now lives in the slug-scoped
 * addon_settings table instead of flat addon_{slug}_{key} rows in settings.
 */
class AddonSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_and_get_round_trips_every_type(): void
    {
        AddonSetting::set('ai-chatbot', 'greeting', 'hi', 'string');
        AddonSetting::set('ai-chatbot', 'max', 42, 'integer');
        AddonSetting::set('ai-chatbot', 'enabled', true, 'boolean');
        AddonSetting::set('ai-chatbot', 'models', ['a', 'b'], 'json');
        AddonSetting::set('ai-chatbot', 'secret', 'token', 'encrypted');

        $this->assertSame('hi', addon_setting('ai-chatbot', 'greeting'));
        $this->assertSame(42, addon_setting('ai-chatbot', 'max'));
        $this->assertSame(true, addon_setting('ai-chatbot', 'enabled'));
        $this->assertSame(['a', 'b'], addon_setting('ai-chatbot', 'models'));
        $this->assertSame('token', addon_setting('ai-chatbot', 'secret'));
    }

    public function test_encrypted_value_is_not_stored_in_plaintext(): void
    {
        AddonSetting::set('ai-chatbot', 'secret', 'token', 'encrypted');

        $raw = AddonSetting::where('addon_slug', 'ai-chatbot')->where('key', 'secret')->value('value');
        $this->assertNotSame('token', $raw);
        $this->assertSame('token', addon_setting('ai-chatbot', 'secret'));
    }

    public function test_get_returns_default_when_unset_and_isPersisted_is_precise(): void
    {
        $this->assertSame('fallback', addon_setting('ai-chatbot', 'missing', 'fallback'));
        $this->assertFalse(AddonSetting::isPersisted('ai-chatbot', 'missing'));

        AddonSetting::set('ai-chatbot', 'present', '0', 'string');
        $this->assertTrue(AddonSetting::isPersisted('ai-chatbot', 'present'));
    }

    public function test_write_busts_the_per_slug_cache(): void
    {
        AddonSetting::set('ai-chatbot', 'greeting', 'one', 'string');
        $this->assertSame('one', addon_setting('ai-chatbot', 'greeting'));
        $this->assertTrue(Cache::has('addon_settings:ai-chatbot'));

        AddonSetting::set('ai-chatbot', 'greeting', 'two', 'string');
        $this->assertSame('two', addon_setting('ai-chatbot', 'greeting'));
    }

    public function test_forget_removes_only_that_addons_rows(): void
    {
        AddonSetting::set('ai-chatbot', 'a', '1', 'string');
        AddonSetting::set('ai-image-pro', 'b', '2', 'string');

        AddonSetting::forget('ai-chatbot');

        $this->assertSame(0, AddonSetting::where('addon_slug', 'ai-chatbot')->count());
        $this->assertSame('2', addon_setting('ai-image-pro', 'b'));
    }

    public function test_settings_are_scoped_per_addon(): void
    {
        AddonSetting::set('ai-chatbot', 'enabled', true, 'boolean');
        AddonSetting::set('ai-image-pro', 'enabled', false, 'boolean');

        $this->assertTrue(addon_setting('ai-chatbot', 'enabled'));
        $this->assertFalse(addon_setting('ai-image-pro', 'enabled'));
    }
}
