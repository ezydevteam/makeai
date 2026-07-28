<?php

namespace Tests\Feature;

use App\Services\UpdateService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * An update has to move the compiled frontend, not just the PHP.
 *
 * The distribution layout is <webroot>/{index.php, build/, storage/, core/}, and the
 * package ships no core/public — the compiled assets live in build/, BESIDE the
 * application directory. copyFiles() only ever writes into base_path(), which is core/,
 * so every update delivered new PHP on top of the buyer's original JavaScript and the
 * frontend never moved at all.
 *
 * That is worse than a failed update: a Vue change and the controller it depends on
 * arrive separately, and the buyer has no way to tell. Envato buyers cannot SSH in and
 * copy build/ by hand, so the updater has to do it.
 */
class UpdateWebrootAssetsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('app/testing/update-webroot-'.getmypid());
        File::deleteDirectory($this->root);
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /**
     * Builds the packaged shape: a package to install, and a live webroot to install
     * over, with public_path() pointed at the latter.
     *
     * @return array{0: string, 1: string} [package core dir, live webroot]
     */
    private function stagePackageAndSite(): array
    {
        $package = $this->root.'/package';
        $site = $this->root.'/site';

        File::ensureDirectoryExists($package.'/core');
        File::ensureDirectoryExists($package.'/build/assets');
        File::put($package.'/core/artisan', '#!/usr/bin/env php');
        File::put($package.'/build/manifest.json', '{"app.js":{"file":"assets/app-NEW.js"}}');
        File::put($package.'/build/assets/app-NEW.js', 'console.log("new")');
        File::put($package.'/index.php', '<?php // new front controller');

        File::ensureDirectoryExists($site.'/build/assets');
        File::put($site.'/build/manifest.json', '{"app.js":{"file":"assets/app-OLD.js"}}');
        File::put($site.'/build/assets/app-OLD.js', 'console.log("old")');
        File::put($site.'/index.php', '<?php // old front controller');

        $this->app->usePublicPath($site);

        return [$package.'/core', $site];
    }

    private function copyWebroot(string $packageCore): void
    {
        (new \ReflectionMethod(UpdateService::class, 'copyWebrootFiles'))
            ->invoke(app(UpdateService::class), $packageCore);
    }

    public function test_compiled_assets_and_the_manifest_reach_the_live_webroot(): void
    {
        [$packageCore, $site] = $this->stagePackageAndSite();

        $this->copyWebroot($packageCore);

        $this->assertFileExists($site.'/build/assets/app-NEW.js');
        $this->assertStringContainsString('app-NEW.js', File::get($site.'/build/manifest.json'),
            'the manifest must be replaced, or the site keeps serving the old bundle');
        $this->assertStringContainsString('new front controller', File::get($site.'/index.php'));
    }

    /**
     * Vite filenames are content-hashed, so old files are harmless once the manifest stops
     * referencing them — and merging rather than replacing means a copy that dies halfway
     * cannot leave the site with no assets at all.
     */
    public function test_existing_assets_are_left_in_place_rather_than_wiped(): void
    {
        [$packageCore, $site] = $this->stagePackageAndSite();

        $this->copyWebroot($packageCore);

        $this->assertFileExists($site.'/build/assets/app-OLD.js');
    }

    /**
     * The allowlist matters as much as the copy. These are buyer property — branding,
     * redirects, security rules, uploads — and an update that silently replaced them
     * would destroy customisation nobody backed up.
     */
    public function test_buyer_owned_webroot_files_are_never_touched(): void
    {
        [$packageCore, $site] = $this->stagePackageAndSite();
        $package = dirname($packageCore);

        foreach (['favicon.ico' => 'BUYER-LOGO', 'robots.txt' => 'BUYER-RULES',
            '.htaccess' => 'BUYER-REDIRECTS', 'web.config' => 'BUYER-IIS'] as $file => $buyerContent) {
            File::put($package.'/'.$file, 'PACKAGE-DEFAULT');
            File::put($site.'/'.$file, $buyerContent);
        }

        File::ensureDirectoryExists($site.'/storage');
        File::put($site.'/storage/upload.txt', 'BUYER-UPLOAD');

        $this->copyWebroot($packageCore);

        $this->assertSame('BUYER-LOGO', File::get($site.'/favicon.ico'));
        $this->assertSame('BUYER-RULES', File::get($site.'/robots.txt'));
        $this->assertSame('BUYER-REDIRECTS', File::get($site.'/.htaccess'));
        $this->assertSame('BUYER-IIS', File::get($site.'/web.config'));
        $this->assertSame('BUYER-UPLOAD', File::get($site.'/storage/upload.txt'));
    }

    /** A package built in the standard Laravel shape keeps its assets in core/public. */
    public function test_a_standard_laravel_shaped_package_is_also_handled(): void
    {
        $package = $this->root.'/std';
        $site = $this->root.'/site2';

        File::ensureDirectoryExists($package.'/core/public/build');
        File::put($package.'/core/artisan', '#!/usr/bin/env php');
        File::put($package.'/core/public/build/manifest.json', '{"from":"public"}');

        File::ensureDirectoryExists($site);
        $this->app->usePublicPath($site);

        $this->copyWebroot($package.'/core');

        $this->assertFileExists($site.'/build/manifest.json');
        $this->assertStringContainsString('"from":"public"', File::get($site.'/build/manifest.json'));
    }

    /** Nothing to copy must be a no-op, not an exception mid-update. */
    public function test_a_package_without_a_webroot_half_is_a_no_op(): void
    {
        $package = $this->root.'/bare';
        $site = $this->root.'/site3';

        File::ensureDirectoryExists($package.'/core');
        File::put($package.'/core/artisan', '#!/usr/bin/env php');
        File::ensureDirectoryExists($site);
        $this->app->usePublicPath($site);

        $this->copyWebroot($package.'/core');

        $this->assertDirectoryDoesNotExist($site.'/build');
    }
}
