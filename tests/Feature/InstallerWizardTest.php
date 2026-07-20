<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Installer\Services\SystemCheckService;
use Tests\TestCase;

/**
 * Covers the parts of the installation wizard a buyer hits on a freshly
 * extracted package — the paths that are hardest to notice breaking, because
 * they only run once, on a machine the developer does not own.
 */
class InstallerWizardTest extends TestCase
{
    use RefreshDatabase;

    /** The wizard is gated by InstallationMiddleware; the test .env has it on. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => false]);

        // The installer is a self-contained package: its Vue components live in
        // resources/installer/js and are resolved by a custom branch in app.ts,
        // not under resources/js/Pages. Inertia's on-disk existence check only
        // knows the default location, so it would fail on a component that
        // resolves correctly in the browser.
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    public function test_wizard_renders_on_a_fresh_install(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Install/Index')
                ->where('currentStep', 1)
                ->has('systemCheck')
            );
    }

    public function test_requirements_step_reports_php_and_writable_directories(): void
    {
        $checks = app(SystemCheckService::class)->check();

        $this->assertArrayHasKey('php_version', $checks);
        $this->assertArrayHasKey('writable_dirs', $checks);
        $this->assertArrayHasKey('extensions', $checks);

        $labels = array_column($checks['writable_dirs'], 'label');
        $this->assertContains('storage', $labels);
        $this->assertContains('bootstrap/cache', $labels);
        $this->assertContains('.env', $labels);
    }

    /**
     * Extractors routinely drop empty directories, and a buyer on shared hosting
     * often cannot recreate them — so the check recreates them itself rather
     * than reporting a failure with no remedy.
     */
    public function test_writable_check_recreates_a_missing_directory(): void
    {
        $missing = storage_path('framework/testing-missing-dir');

        if (is_dir($missing)) {
            rmdir($missing);
        }
        $this->assertDirectoryDoesNotExist($missing);

        // Exercise the repair through the same private helper the check uses.
        $service = app(SystemCheckService::class);
        $repair = (new \ReflectionClass($service))->getMethod('attemptRepair');
        $repair->invoke($service, $missing);

        $this->assertDirectoryExists($missing, 'attemptRepair() should create a missing writable directory');

        rmdir($missing);
    }

    /**
     * Scheduled work (renewals, usage resets, queued mail) silently never runs
     * without cron, and finalising redirects straight to the login screen — so
     * the review step is the last place the buyer can be told.
     */
    public function test_review_step_exposes_the_cron_command_with_a_real_path(): void
    {
        $this->withSession(['install_wizard' => [
            'current_step' => 6,
            'steps_completed' => [1, 2, 3, 4, 5],
            'data' => [
                'step_1' => [],
                'step_2' => ['purchase_code' => 'test-code'],
                'step_3' => ['db_driver' => 'mysql', 'db_host' => '127.0.0.1', 'db_port' => 3306],
                'step_4' => ['site_name' => 'MakeAI', 'site_url' => 'https://example.com'],
                'step_5' => ['admin_name' => 'Admin', 'admin_email' => 'admin@example.com'],
            ],
        ]])
            ->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Install/Index')
                ->where('currentStep', 6)
                ->where('cronCommand', fn (string $cmd) => str_contains($cmd, 'schedule:run')
                    && str_contains($cmd, base_path()))
            );
    }

    /**
     * SQLite must stay rejected. A shipped package bootstraps its data from
     * database/data/data.sql (a mysqldump) and no longer carries the seeder
     * classes, so accepting SQLite would strand the buyer with a migrated but
     * empty database and no way forward.
     */
    public function test_sqlite_is_rejected_by_the_database_step(): void
    {
        $this->withSession(['install_wizard' => [
            'current_step' => 3,
            'steps_completed' => [1, 2],
            'data' => ['step_1' => [], 'step_2' => ['purchase_code' => 'x']],
        ]])
            ->post('/install/step/3', ['db_driver' => 'sqlite'])
            ->assertSessionHasErrors('db_driver');
    }

    public function test_database_step_requires_full_connection_details(): void
    {
        $this->withSession(['install_wizard' => [
            'current_step' => 3,
            'steps_completed' => [1, 2],
            'data' => ['step_1' => [], 'step_2' => ['purchase_code' => 'x']],
        ]])
            ->post('/install/step/3', ['db_driver' => 'mysql'])
            ->assertSessionHasErrors(['db_host', 'db_port', 'db_database', 'db_username']);
    }

    public function test_cron_command_is_absent_before_the_review_step(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('cronCommand'));
    }
}
