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
 * Social Proof brand logos never appeared. The upload handler looked for a section of type
 * 'brands' holding `items[].image_url` — but brand logos live on the `stats_bar` section under
 * `brands[].image`, so nothing matched and the stored path was silently dropped. The image
 * carousel had the same shape of bug ('carousel' vs the real 'image_carousel').
 */
class HomepageRepeaterImageUploadTest extends TestCase
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

    private function save(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.themes.settings.simple.save', ['slug' => 'default']), $payload);
    }

    private function sectionConfig(string $type): array
    {
        $config = app(ThemeSettingsService::class)->getResolvedFrontendHomepageConfig();

        foreach ($config['sections'] ?? [] as $section) {
            if (($section['type'] ?? '') === $type) {
                return $section['config'] ?? [];
            }
        }

        return [];
    }

    public function test_brand_logo_upload_lands_on_the_stats_bar_brands_item(): void
    {
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'hero', 'config' => []],
                    ['type' => 'stats_bar', 'config' => [
                        'brands' => [
                            ['name' => 'Acme', 'image' => ''],
                            ['name' => 'Globex', 'image' => ''],
                        ],
                    ]],
                ],
            ]),
            'brand_logo_1' => UploadedFile::fake()->image('globex.png'),
        ])->assertSessionHasNoErrors();

        $brands = $this->sectionConfig('stats_bar')['brands'] ?? [];

        $this->assertSame('', $brands[0]['image'] ?? null, 'the brand with no upload should be untouched');
        $this->assertNotEmpty($brands[1]['image'] ?? '', 'brands[1].image was never written');
        Storage::disk('public')->assertExists($brands[1]['image']);
    }

    public function test_carousel_image_upload_lands_on_the_image_carousel_item(): void
    {
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'hero', 'config' => []],
                    ['type' => 'image_carousel', 'config' => [
                        'items' => [['caption' => 'One', 'image_url' => '']],
                    ]],
                ],
            ]),
            'carousel_item_image_0' => UploadedFile::fake()->image('slide.png'),
        ])->assertSessionHasNoErrors();

        $items = $this->sectionConfig('image_carousel')['items'] ?? [];

        $this->assertNotEmpty($items[0]['image_url'] ?? '', 'items[0].image_url was never written');
        Storage::disk('public')->assertExists($items[0]['image_url']);
    }

    /** Sections are drag-reorderable, so the upload must follow the section, not an index. */
    public function test_upload_follows_the_section_when_sections_are_reordered(): void
    {
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'image_carousel', 'config' => ['items' => [['image_url' => '']]]],
                    ['type' => 'stats_bar', 'config' => ['brands' => [['name' => 'Acme', 'image' => '']]]],
                    ['type' => 'hero', 'config' => []],
                ],
            ]),
            'brand_logo_0' => UploadedFile::fake()->image('acme.png'),
        ])->assertSessionHasNoErrors();

        $brandImage = $this->sectionConfig('stats_bar')['brands'][0]['image'] ?? '';
        $carouselImage = $this->sectionConfig('image_carousel')['items'][0]['image_url'] ?? '';

        $this->assertNotEmpty($brandImage, 'the logo did not follow stats_bar to its new position');
        $this->assertSame('', $carouselImage, 'the logo leaked into the section sitting at the old index');
    }

    /**
     * A real browser upload read off the raw Symfony bag arrives as the base UploadedFile, which
     * has no store() — the fakes above are Laravel's subclass, so they never exercised that path
     * and the crash only showed up in the browser.
     */
    public function test_store_public_upload_accepts_a_base_symfony_uploaded_file(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($source, 'png-bytes');

        $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $source, 'logo.png', 'image/png', null, true
        );

        $this->assertNotInstanceOf(
            \Illuminate\Http\UploadedFile::class,
            $symfonyFile,
            'this test is pointless unless the file really is the base Symfony class',
        );

        $path = store_public_upload($symfonyFile, 'theme/brands');

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_removing_a_brand_logo_deletes_the_stored_file(): void
    {
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'hero', 'config' => []],
                    ['type' => 'stats_bar', 'config' => ['brands' => [['name' => 'Acme', 'image' => '']]]],
                ],
            ]),
            'brand_logo_0' => UploadedFile::fake()->image('acme.png'),
        ]);

        $path = $this->sectionConfig('stats_bar')['brands'][0]['image'];
        Storage::disk('public')->assertExists($path);

        // Admin clears the logo and saves with no file attached.
        $this->save([
            'section' => 'homepage',
            'settings' => ['show_hero' => '1'],
            'homepage_config' => json_encode([
                'sections' => [
                    ['type' => 'hero', 'config' => []],
                    ['type' => 'stats_bar', 'config' => ['brands' => [['name' => 'Acme', 'image' => '']]]],
                ],
            ]),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($path);
    }
}
