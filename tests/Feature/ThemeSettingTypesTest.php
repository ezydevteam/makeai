<?php

namespace Tests\Feature;

use App\Services\ThemeSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer and homepage forms upload images, so they post as multipart — an encoding with
 * no boolean type, which writes every toggle as the string "1"/"0". A stored "0" is truthy in
 * JavaScript, so any frontend reading it without a hand-rolled truthiness check inverts the
 * setting. These tests pin the fix at the boundary: whatever shape a value is written in, the
 * resolver hands back the type settings.json declares.
 */
class ThemeSettingTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_booleans_resolve_as_booleans_however_they_were_written(): void
    {
        $settings = app(ThemeSettingsService::class);

        // Exactly what a multipart save leaves behind.
        $settings->saveFooterSettings([
            'show_newsletter' => '1',
            'show_social_icons' => '0',
            'enable_card_style' => 'true',
            'bottom_bar_centered' => '0',
            'show_back_to_top' => 1,
        ]);

        $resolved = $settings->getResolvedFrontendFooter();

        $this->assertTrue($resolved['show_newsletter']);
        $this->assertFalse($resolved['show_social_icons']);
        $this->assertTrue($resolved['enable_card_style']);
        // The dangerous one: "0" is truthy in JS, so this must not survive as a string.
        $this->assertFalse($resolved['bottom_bar_centered']);
        $this->assertTrue($resolved['show_back_to_top']);
    }

    public function test_header_booleans_resolve_as_booleans_in_every_group(): void
    {
        $settings = app(ThemeSettingsService::class);

        $settings->saveHeaderSettings([
            'desktop' => ['sticky' => '0', 'transparent_on_hero' => '1', 'show_border' => 'false'],
            'mobile_top' => ['enabled' => '0'],
            'mobile_bottom' => ['enabled' => '1', 'show_glassmorphism' => '0'],
        ]);

        $header = $settings->getResolvedFrontendHeader();

        $this->assertFalse($header['desktop']['sticky']);
        $this->assertTrue($header['desktop']['transparent_on_hero']);
        $this->assertFalse($header['desktop']['show_border']);
        $this->assertFalse($header['mobile_top']['enabled']);
        $this->assertTrue($header['mobile_bottom']['enabled']);
        $this->assertFalse($header['mobile_bottom']['show_glassmorphism']);
    }

    public function test_non_boolean_settings_keep_their_own_types(): void
    {
        $settings = app(ThemeSettingsService::class);

        $settings->saveHeaderSettings(['desktop' => ['height' => '84', 'cta_text' => 'Start free']]);
        $settings->saveFooterSettings(['footer_vertical_padding' => '72', 'copyright_text' => '© 2026']);

        $header = $settings->getResolvedFrontendHeader();
        $footer = $settings->getResolvedFrontendFooter();

        // Numbers are interpolated into CSS lengths, where a numeric string behaves the same —
        // coercing them would turn a cleared field into 0 instead of letting it fall back.
        $this->assertSame('84', $header['desktop']['height']);
        $this->assertSame('Start free', $header['desktop']['cta_text']);
        $this->assertSame('72', $footer['footer_vertical_padding']);
        $this->assertSame('© 2026', $footer['copyright_text']);
    }

    public function test_untouched_sections_still_resolve_to_their_declared_defaults(): void
    {
        $settings = app(ThemeSettingsService::class);

        $header = $settings->getResolvedFrontendHeader();
        $footer = $settings->getResolvedFrontendFooter();

        $this->assertIsBool($header['desktop']['sticky']);
        $this->assertIsBool($header['mobile_bottom']['enabled']);
        $this->assertSame(72, $header['desktop']['height']);
        $this->assertIsBool($footer['show_newsletter']);
        $this->assertSame(['desktop', 'mobile_top', 'mobile_bottom'], array_keys($header));
    }
}
