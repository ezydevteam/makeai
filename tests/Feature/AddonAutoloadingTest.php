<?php

namespace Tests\Feature;

use App\Services\AddonService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Addon classes are autoloaded at RUNTIME, not from composer.json.
 *
 * The bug this closes: every addon namespace used to be hardcoded into the core
 * composer.json. Addons are sold separately and installed by upload, so an addon released
 * after a buyer's copy of MakeAI was built had no entry in their composer.json — and
 * `composer dump-autoload` is not available to them, since most run on shared hosting with
 * no shell. Its service provider loaded (AddonService require_once's that file directly),
 * then the first route resolving one of its controllers died with a class-not-found.
 *
 * The whole point is that these assertions pass for a namespace composer has never heard
 * of, so the fixture addon below is created on the fly and is deliberately not any real
 * addon. If someone "helpfully" re-adds addon entries to composer.json, these tests keep
 * passing for the wrong reason — hence test_no_addon_namespaces_are_hardcoded_in_composer.
 */
class AddonAutoloadingTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        // A slug no composer.json has ever contained, with a hyphen so the studly-case
        // conversion (zzz-fixture-addon → ZzzFixtureAddon) is exercised too.
        $this->fixturePath = base_path('addons/zzz-fixture-addon');

        File::ensureDirectoryExists($this->fixturePath . '/app/Services');
        File::ensureDirectoryExists($this->fixturePath . '/database/seeders');

        File::put($this->fixturePath . '/addon.json', json_encode([
            'slug' => 'zzz-fixture-addon',
            'name' => 'Fixture Addon',
            'version' => '1.0.0',
        ]));

        File::put($this->fixturePath . '/app/Services/FixtureService.php', <<<'PHP'
            <?php

            namespace Addons\ZzzFixtureAddon\Services;

            class FixtureService
            {
                public function answer(): string
                {
                    return 'loaded from disk';
                }
            }
            PHP);

        // Seeders live OUTSIDE app/, so they need their own prefix. This is the mapping
        // that was missing for ai-knowledge-base and dead for ai-repurposer while the list
        // was maintained by hand.
        File::put($this->fixturePath . '/database/seeders/FixtureSeeder.php', <<<'PHP'
            <?php

            namespace Addons\ZzzFixtureAddon\Database\Seeders;

            use Illuminate\Database\Seeder;

            class FixtureSeeder extends Seeder
            {
                public function run(): void {}
            }
            PHP);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixturePath);

        parent::tearDown();
    }

    public function test_an_addon_class_resolves_without_any_composer_entry(): void
    {
        $class = \Addons\ZzzFixtureAddon\Services\FixtureService::class;

        $this->assertFalse(
            class_exists($class, false),
            'the fixture must not already be loaded, or this test proves nothing'
        );

        app(AddonService::class)->registerAddonAutoloading();

        $this->assertTrue(
            class_exists($class),
            'an addon class must resolve from disk with no composer.json entry'
        );
        $this->assertSame('loaded from disk', (new $class)->answer());
    }

    /** Seeders sit outside the app/ PSR-4 root and need their own, longer prefix. */
    public function test_an_addon_seeder_outside_the_app_root_also_resolves(): void
    {
        app(AddonService::class)->registerAddonAutoloading();

        $this->assertTrue(
            class_exists(\Addons\ZzzFixtureAddon\Database\Seeders\FixtureSeeder::class),
            'Addons\<Name>\Database\Seeders\ must map to database/seeders/, not app/'
        );
    }

    /**
     * Registering twice must not blow up or double-register — boot calls this, and an
     * activation in the same request calls it again.
     */
    public function test_registering_twice_is_harmless(): void
    {
        $service = app(AddonService::class);

        $service->registerAddonAutoloading();
        $service->registerAddonAutoloading();

        $this->assertTrue(class_exists(\Addons\ZzzFixtureAddon\Services\FixtureService::class));
    }

    /** A directory with no addon.json is not an addon and must not be mapped. */
    public function test_a_directory_without_a_manifest_is_ignored(): void
    {
        $stray = base_path('addons/zzz-not-an-addon');
        File::ensureDirectoryExists($stray . '/app');

        try {
            app(AddonService::class)->registerAddonAutoloading();

            $this->assertFalse(
                class_exists(\Addons\ZzzNotAnAddon\Whatever::class),
                'a folder without addon.json must not get a namespace'
            );
        } finally {
            File::deleteDirectory($stray);
        }
    }

    /**
     * The guard that keeps this honest. If addon namespaces creep back into composer.json,
     * the tests above would pass whether or not runtime registration works — and the
     * shipped-addon bug would silently return.
     */
    public function test_no_addon_namespaces_are_hardcoded_in_composer(): void
    {
        $composer = json_decode(File::get(base_path('composer.json')), true);

        $hardcoded = array_filter(
            $composer['autoload']['psr-4'] ?? [],
            fn (string $path): bool => str_starts_with($path, 'addons/')
        );

        $this->assertSame(
            [],
            $hardcoded,
            'addon namespaces must be registered at runtime by AddonService, not in composer.json — '
                . 'a hardcoded entry cannot cover an addon released after the buyer built their copy'
        );
    }
}
