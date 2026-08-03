<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\ThemePresetService;
use App\Services\ThemeSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The bundled presets are large hand-written JSON files whose keys are filtered against
 * settings.json on save — a typo does not error, it silently drops the value. These tests
 * pin the two things that failure mode breaks: every declared key must survive the round
 * trip, and applying a preset must not clobber settings no preset owns.
 */
class ThemePresetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Data providers run before the application boots, so resource_path() is not available
     * here — resolve the directory relative to this file instead.
     *
     * @return array<string, array{0: string}>
     */
    public static function presetProvider(): array
    {
        $files = glob(__DIR__ . '/../../resources/themes/default/presets/*.json') ?: [];

        $cases = [];
        foreach ($files as $path) {
            $id = basename($path, '.json');
            $cases[$id] = [$id];
        }

        return $cases;
    }

    #[DataProvider('presetProvider')]
    public function test_preset_applies_and_every_declared_key_survives(string $id): void
    {
        $presets = app(ThemePresetService::class);
        $settings = app(ThemeSettingsService::class);

        $file = json_decode((string) File::get(resource_path("themes/default/presets/{$id}.json")), true);

        $this->assertTrue($presets->applyPreset('default', $id), "Preset [{$id}] failed to apply.");

        $resolved = [
            'theme' => $settings->getResolvedFrontendTheme(),
            'footer' => $settings->getResolvedFrontendFooter(),
            'homepage' => $settings->getResolvedFrontendHomepage(),
            'tool_page' => $settings->getResolvedFrontendToolPage(),
        ];

        foreach ($resolved as $section => $values) {
            foreach ($file['settings'][$section] ?? [] as $key => $expected) {
                $this->assertSame($expected, $values[$key], "[{$id}] {$section}.{$key} did not survive the save.");
            }
        }

        $header = $settings->getResolvedFrontendHeader();

        foreach (['desktop', 'mobile_top', 'mobile_bottom'] as $group) {
            foreach ($file['settings']['header'][$group] ?? [] as $key => $expected) {
                $this->assertSame($expected, $header[$group][$key], "[{$id}] header.{$group}.{$key} did not survive the save.");
            }
        }

        // Section configs are merged over the settings.json defaults, so only the keys the
        // preset declares are asserted — the rest are expected to fall back.
        $configByType = [];
        foreach ($settings->getResolvedFrontendHomepageConfig()['sections'] as $section) {
            $configByType[$section['type']] = $section['config'];
        }

        foreach ($file['settings']['homepage_config']['sections'] ?? [] as $section) {
            foreach ($section['config'] as $key => $expected) {
                $this->assertSame(
                    $expected,
                    $configByType[$section['type']][$key] ?? null,
                    "[{$id}] homepage_config.{$section['type']}.{$key} did not survive the save."
                );
            }
        }
    }

    public function test_a_preset_without_a_preview_inherits_the_theme_palette(): void
    {
        $theme = json_decode((string) File::get(resource_path('themes/default/settings.json')), true)['defaults']['theme'];

        $presets = collect(app(ThemePresetService::class)->listPresets('default'))->keyBy('id');

        // default.json declares no preview at all — the card still paints the real palette.
        $this->assertArrayNotHasKey(
            'preview',
            json_decode((string) File::get(resource_path('themes/default/presets/default.json')), true),
            'default.json should inherit its swatches, not restate them.'
        );

        $this->assertSame($theme['primary_color'], $presets['default']['preview']['primary']);
        $this->assertSame($theme['secondary_color'], $presets['default']['preview']['secondary']);
        $this->assertSame($theme['bg_color'], $presets['default']['preview']['bg']);
        $this->assertSame($theme['theme_default_mode'], $presets['default']['preview']['mode']);

        // A preset that declares its own palette keeps every one of its values.
        $file = json_decode((string) File::get(resource_path('themes/default/presets/creative-studio.json')), true);
        foreach ($file['preview'] as $key => $value) {
            $this->assertSame($value, $presets['creative-studio']['preview'][$key]);
        }
    }

    // ─── Snapshot / restore ───

    public function test_applying_a_preset_snapshots_the_look_it_replaced(): void
    {
        $presets = app(ThemePresetService::class);

        $this->assertNull($presets->backupSummary(), 'Nothing has been applied yet, so there is nothing to undo.');

        $presets->applyPreset('default', 'saas-startup');

        $backup = $presets->backupSummary();

        $this->assertNotNull($backup);
        $this->assertSame('saas-startup', $backup['replaced_by']);
        $this->assertSame('SaaS Startup', $backup['replaced_by_name']);
        // No preset was active beforehand — the snapshot holds the admin's own settings.
        $this->assertNull($backup['previous_preset']);
        $this->assertNotNull($backup['captured_at']);
    }

    public function test_restoring_puts_back_the_previous_preset(): void
    {
        $presets = app(ThemePresetService::class);
        $settings = app(ThemeSettingsService::class);

        $presets->applyPreset('default', 'corporate-enterprise');
        $corporate = [
            'theme' => $settings->getResolvedFrontendTheme(),
            'header' => $settings->getResolvedFrontendHeader(),
            'footer' => $settings->getResolvedFrontendFooter(),
            'homepage' => $settings->getResolvedFrontendHomepage(),
            'tool_page' => $settings->getResolvedFrontendToolPage(),
            'homepage_config' => $settings->getResolvedFrontendHomepageConfig(),
        ];

        $presets->applyPreset('default', 'creative-studio');
        $this->assertSame('dark', $settings->getResolvedFrontendTheme()['theme_default_mode']);

        $summary = $presets->backupSummary();
        $this->assertSame('corporate-enterprise', $summary['previous_preset']);
        $this->assertSame('Corporate Enterprise', $summary['previous_preset_name']);

        $this->assertTrue($presets->restoreBackup());

        $this->assertSame($corporate['theme'], $settings->getResolvedFrontendTheme());
        $this->assertSame($corporate['header'], $settings->getResolvedFrontendHeader());
        $this->assertSame($corporate['footer'], $settings->getResolvedFrontendFooter());
        $this->assertSame($corporate['homepage'], $settings->getResolvedFrontendHomepage());
        $this->assertSame($corporate['tool_page'], $settings->getResolvedFrontendToolPage());
        $this->assertSame($corporate['homepage_config'], $settings->getResolvedFrontendHomepageConfig());
        $this->assertSame('corporate-enterprise', settings('active_theme_preset'));
    }

    public function test_restoring_puts_back_hand_made_edits_when_no_preset_was_active(): void
    {
        $presets = app(ThemePresetService::class);
        $settings = app(ThemeSettingsService::class);

        $settings->saveThemeSettings(['primary_color' => '#123456', 'font_body' => 'Lato']);
        $settings->saveToolPageSettings(['layout' => 'minimalist']);

        $presets->applyPreset('default', 'marketing-agency');
        $this->assertSame('#e11d48', $settings->getResolvedFrontendTheme()['primary_color']);

        $this->assertTrue($presets->restoreBackup());

        $this->assertSame('#123456', $settings->getResolvedFrontendTheme()['primary_color']);
        $this->assertSame('Lato', $settings->getResolvedFrontendTheme()['font_body']);
        $this->assertSame('minimalist', $settings->getResolvedFrontendToolPage()['layout']);
        $this->assertNull(settings('active_theme_preset'));
    }

    public function test_restore_is_a_single_level_undo(): void
    {
        $presets = app(ThemePresetService::class);

        $presets->applyPreset('default', 'saas-startup');
        $this->assertTrue($presets->restoreBackup());

        // Consumed: the banner goes away and a second undo has nothing to act on.
        $this->assertNull($presets->backupSummary());
        $this->assertFalse($presets->restoreBackup());
    }

    public function test_the_snapshot_lives_in_one_blob_row_of_its_own(): void
    {
        $presets = app(ThemePresetService::class);

        // Nothing before the first apply, so a fresh install carries no extra row.
        $this->assertSame(0, Setting::where('key', 'group:preset_backup')->count());

        $presets->applyPreset('default', 'midnight');

        $this->assertSame(1, Setting::where('key', 'group:preset_backup')->count());
        // Routed to the blob, never left behind as a flat row.
        $this->assertSame(0, Setting::where('key', 'theme_preset_backup')->count());
        // And kept out of the appearance blob, which every theme save rewrites.
        $appearance = json_decode((string) Setting::where('key', 'group:appearance')->value('value'), true);
        $this->assertArrayNotHasKey('theme_preset_backup', is_array($appearance) ? $appearance : []);
    }

    public function test_restore_leaves_custom_code_alone(): void
    {
        $presets = app(ThemePresetService::class);
        $settings = app(ThemeSettingsService::class);

        $settings->saveCustomCodeSettings(['custom_css' => '.brand { color: red; }']);

        $presets->applyPreset('default', 'tools-directory');
        $presets->restoreBackup();

        $this->assertSame('.brand { color: red; }', $settings->getStoredCustomCodeSettings()['custom_css']);
    }

    public function test_applying_a_preset_keeps_the_admins_homepage_seo(): void
    {
        $settings = app(ThemeSettingsService::class);

        $settings->saveHomepageConfig([
            'settings' => ['seo' => ['meta_title' => 'My Own Title', 'meta_description' => 'My own description.']],
            'sections' => [],
        ]);

        app(ThemePresetService::class)->applyPreset('default', 'saas-startup');

        $resolved = $settings->getResolvedFrontendHomepageConfig();

        $this->assertSame('My Own Title', $resolved['settings']['seo']['meta_title']);
        $this->assertSame('My own description.', $resolved['settings']['seo']['meta_description']);
    }

    public function test_the_default_preset_clears_every_stored_override(): void
    {
        $settings = app(ThemeSettingsService::class);
        $presets = app(ThemePresetService::class);

        $presets->applyPreset('default', 'creative-studio');
        $this->assertSame('dark', $settings->getResolvedFrontendTheme()['theme_default_mode']);

        $presets->applyPreset('default', 'default');

        $factory = json_decode((string) File::get(resource_path('themes/default/settings.json')), true)['defaults'];

        $this->assertSame($factory['theme'], $settings->getResolvedFrontendTheme());
        $this->assertSame($factory['header'], $settings->getResolvedFrontendHeader());
        $this->assertSame($factory['footer'], $settings->getResolvedFrontendFooter());
        $this->assertSame($factory['homepage'], $settings->getResolvedFrontendHomepage());
        $this->assertSame($factory['tool_page'], $settings->getResolvedFrontendToolPage());
        $this->assertSame($factory['homepage_config']['sections'], $settings->getResolvedFrontendHomepageConfig()['sections']);
    }
}
