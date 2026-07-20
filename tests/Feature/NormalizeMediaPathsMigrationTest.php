<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The normalisation migration must reduce legacy "/storage/..." and URL-format rows to
 * relative disk keys, while never rewriting values that are not ours (external URLs,
 * data URIs) or that aren't media paths at all (emoji / two-letter language flags).
 */
class NormalizeMediaPathsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_07_14_000001_normalize_stored_media_paths.php');
        $migration->up();
    }

    public function test_strips_legacy_storage_prefix_from_media_columns(): void
    {
        $id = DB::table('ads')->insertGetId([
            'title' => 'Legacy ad',
            'type' => 'image_link',
            'zone' => 'header_banner',
            'image_url' => '/storage/ads/banner.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame('ads/banner.png', DB::table('ads')->where('id', $id)->value('image_url'));
    }

    public function test_leaves_external_urls_untouched(): void
    {
        $id = DB::table('ads')->insertGetId([
            'title' => 'Remote ad',
            'type' => 'image_link',
            'zone' => 'header_banner',
            'image_url' => 'https://cdn.partner.com/banner.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame(
            'https://cdn.partner.com/banner.png',
            DB::table('ads')->where('id', $id)->value('image_url'),
            'An external ad image must never be rewritten into a local key.',
        );
    }

    public function test_is_idempotent_for_already_relative_keys(): void
    {
        $id = DB::table('ads')->insertGetId([
            'title' => 'Modern ad',
            'type' => 'image_link',
            'zone' => 'header_banner',
            'image_url' => 'ads/already-good.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runMigration();
        $this->runMigration(); // re-run must be a no-op

        $this->assertSame('ads/already-good.png', DB::table('ads')->where('id', $id)->value('image_url'));
    }

    public function test_leaves_non_path_language_flags_untouched(): void
    {
        $emoji = DB::table('languages')->insertGetId([
            'code' => 'de', 'name' => 'Deutsch', 'flag' => '🇩🇪',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uploaded = DB::table('languages')->insertGetId([
            'code' => 'fr', 'name' => 'Français', 'flag' => '/storage/language-flags/fr.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame('🇩🇪', DB::table('languages')->where('id', $emoji)->value('flag'));
        $this->assertSame('language-flags/fr.png', DB::table('languages')->where('id', $uploaded)->value('flag'));
    }

    public function test_normalizes_branding_settings(): void
    {
        // This one-time backfill migration (2026_07_14) runs before branding was
        // collapsed into a blob (2026_07_17), so it operates on flat settings rows —
        // seed one directly rather than via settings_set(), which now routes site_* into
        // the branding blob. settings() still reads it back via the flat-row fallback.
        \App\Models\Setting::updateOrCreate(
            ['key' => 'site_logo_light'],
            ['value' => '/storage/branding/logo.png', 'type' => 'string', 'group' => 'branding'],
        );
        \App\Models\Setting::flushCache();

        $this->runMigration();
        \App\Models\Setting::flushCache();

        $this->assertSame('branding/logo.png', settings('site_logo_light'));
    }

    public function test_normalized_values_resolve_on_both_drivers(): void
    {
        $id = DB::table('ads')->insertGetId([
            'title' => 'Ad',
            'type' => 'image_link',
            'zone' => 'header_banner',
            'image_url' => '/storage/ads/banner.png',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runMigration();
        $key = DB::table('ads')->where('id', $id)->value('image_url');

        config(['filesystems.disks.public.driver' => 'local']);
        \Illuminate\Support\Facades\Storage::forgetDisk('public');
        $this->assertSame('/storage/ads/banner.png', media_url($key));

        $svc = new \App\Services\CloudStorageService('r2', 'k', 's', 'auto', 'b', 'https://ep.example.com', 'https://cdn.example.com');
        config(['filesystems.disks.public' => $svc->diskConfig()]);
        \Illuminate\Support\Facades\Storage::forgetDisk('public');
        $this->assertSame('https://cdn.example.com/ads/banner.png', media_url($key));
    }
}
