<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\ThemeSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The hero "Right Side Image" could never save. The upload was stored, but its path was
 * written to $settings['hero_split_image'] — a key absent from the homepage defaults, so
 * filterHomepageSettings' array_intersect_key dropped it on the way to the database. Both
 * the admin UI and HeroSection read sections.hero.config.hero_split_image_url, which
 * nothing wrote. The hero background image was broken the same way.
 *
 * The homepage form is multipart (it carries the uploads), and multipart has no boolean
 * type, so every toggle also arrived as "1"/"0".
 */
class HomepageHeroImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        Storage::fake('public');
    }

    private function superAdmin(): Admin
    {
        $role = AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::firstOrCreate(
            ['email' => 'root@example.com'],
            ['name' => 'Root', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true],
        );
    }

    /** The browser sends the config as JSON under multipart so booleans survive encoding. */
    private function save(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.themes.settings.simple.save', ['slug' => 'default']), $payload);
    }

    private function heroConfig(): array
    {
        $config = app(ThemeSettingsService::class)->getResolvedFrontendHomepageConfig();

        foreach ($config['sections'] ?? [] as $section) {
            if (($section['type'] ?? '') === 'hero') {
                return $section['config'] ?? [];
            }
        }

        return [];
    }

    public function test_right_side_image_upload_is_saved_where_the_hero_reads_it(): void
    {
        $response = $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'hero', 'config' => ['hero_variant' => 'split-gradient']],
                ],
            ]),
            'hero_split_image_file' => UploadedFile::fake()->image('product.png', 800, 600),
        ]);

        $response->assertSessionHasNoErrors();

        $path = $this->heroConfig()['hero_split_image_url'] ?? null;

        $this->assertNotNull($path, 'hero_split_image_url was never written to the hero section config');
        Storage::disk('public')->assertExists($path);
    }

    public function test_hero_background_upload_is_saved_where_the_hero_reads_it(): void
    {
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'hero', 'config' => ['show_hero_background' => true]],
                ],
            ]),
            'hero_background_file' => UploadedFile::fake()->image('bg.jpg', 1920, 1080),
        ])->assertSessionHasNoErrors();

        $path = $this->heroConfig()['hero_background_url'] ?? null;

        $this->assertNotNull($path, 'hero_background_url was never written to the hero section config');
        Storage::disk('public')->assertExists($path);
    }

    public function test_clearing_the_image_deletes_the_stored_file(): void
    {
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode(['sections' => [['type' => 'hero', 'config' => []]]]),
            'hero_split_image_file' => UploadedFile::fake()->image('product.png'),
        ]);

        $path = $this->heroConfig()['hero_split_image_url'];
        Storage::disk('public')->assertExists($path);

        // The admin's "Remove" button blanks the config value and sends no file.
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [['type' => 'hero', 'config' => ['hero_split_image_url' => '']]],
            ]),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_multipart_toggles_are_restored_to_booleans(): void
    {
        $this->save([
            'section' => 'homepage',
            // Exactly what multipart encoding produces from true/false.
            'settings' => ['show_hero' => '1', 'show_pricing' => '0'],
            'homepage_config' => json_encode(['sections' => [['type' => 'hero', 'config' => []]]]),
            'hero_split_image_file' => UploadedFile::fake()->image('product.png'),
        ])->assertSessionHasNoErrors();

        $resolved = app(ThemeSettingsService::class)->getResolvedFrontendHomepage();

        $this->assertTrue($resolved['show_hero'], 'show_hero should be boolean true, not the string "1"');
        $this->assertFalse($resolved['show_pricing'], 'show_pricing should be boolean false, not the string "0"');
    }
}
