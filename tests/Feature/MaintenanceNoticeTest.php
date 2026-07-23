<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\SendMaintenanceNotice;
use App\Jobs\SendTemplatedEmail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\User;
use Closure;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode as MaintenanceModeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaintenanceNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $this->useInMemoryMaintenanceMode();

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /**
     * Real maintenance mode is a file under storage/framework that the whole test
     * suite shares — RefreshDatabase cannot roll it back, and anything left behind
     * 503s every test that runs afterwards. Swapping the driver for an in-process
     * one keeps `artisan down`/`up` and isDownForMaintenance() honest while
     * touching nothing outside this test.
     */
    private function useInMemoryMaintenanceMode(): void
    {
        $this->app->singleton(MaintenanceModeContract::class, fn () => new class implements MaintenanceModeContract
        {
            private ?array $payload = null;

            public function activate(array $payload): void
            {
                $this->payload = $payload;
            }

            public function deactivate(): void
            {
                $this->payload = null;
            }

            public function active(): bool
            {
                return $this->payload !== null;
            }

            public function data(): array
            {
                return $this->payload ?? [];
            }
        });
    }

    protected function tearDown(): void
    {
        // The driver above keeps the down-state in memory, but `artisan down` still
        // drops its bootstrap stub on disk unconditionally. Belt and braces: a
        // failure mid-test must not leave anything behind for the next class.
        @unlink(storage_path('framework/maintenance.php'));
        @unlink(storage_path('framework/down'));

        parent::tearDown();
    }

    private function toggle(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.system.maintenance.toggle'))
            ->assertRedirect();
    }

    /** @return array<int, string> */
    private function dispatchedSlugs(): array
    {
        $slugs = [];

        foreach (Queue::pushedJobs()[SendMaintenanceNotice::class] ?? [] as $pushed) {
            $job = $pushed['job'];
            $slugs[] = Closure::bind(fn () => $this->slug, $job, SendMaintenanceNotice::class)();
        }

        return $slugs;
    }

    private function notify(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.system.maintenance.notify'));
    }

    public function test_toggling_alone_sends_nothing(): void
    {
        Queue::fake();

        $this->toggle();   // down
        $this->toggle();   // up

        Queue::assertNotPushed(SendMaintenanceNotice::class);
    }

    public function test_announcing_then_running_a_window_sends_both_notices(): void
    {
        User::factory()->create(['email_verified_at' => now()]);
        Queue::fake();

        $this->notify()->assertRedirect();
        $this->assertSame(['maintenance_scheduled'], $this->dispatchedSlugs());

        $this->toggle();   // down — sends nothing on its own
        $this->assertSame(['maintenance_scheduled'], $this->dispatchedSlugs());

        $this->toggle();   // up — the all-clear follows the announcement
        $this->assertSame(['maintenance_scheduled', 'maintenance_completed'], $this->dispatchedSlugs());
    }

    /**
     * The whole point of the separate action: queue workers refuse to run while
     * the application is down, so a notice queued then would never be delivered
     * on time. Refuse it outright rather than silently queueing a dead letter.
     */
    public function test_announcing_is_refused_once_maintenance_is_on(): void
    {
        User::factory()->create(['email_verified_at' => now()]);
        Queue::fake();

        $this->toggle();   // down first

        $this->notify()->assertSessionHas('error');

        Queue::assertNotPushed(SendMaintenanceNotice::class);
    }

    public function test_going_live_without_an_announcement_sends_no_all_clear(): void
    {
        Queue::fake();

        $this->toggle();   // down, unannounced
        $this->toggle();   // up

        Queue::assertNotPushed(SendMaintenanceNotice::class);
    }

    public function test_announcing_with_no_audience_is_refused(): void
    {
        Queue::fake();

        $this->notify()->assertSessionHas('error');

        Queue::assertNotPushed(SendMaintenanceNotice::class);
    }

    public function test_notice_reaches_active_verified_users_only(): void
    {
        (new MailTemplateSeeder)->run();

        $wanted = User::factory()->create(['email' => 'wanted@example.com', 'email_verified_at' => now()]);
        User::factory()->create(['email' => 'unverified@example.com', 'email_verified_at' => null]);
        User::factory()->create(['email' => 'banned@example.com', 'email_verified_at' => now(), 'is_banned' => true]);
        User::factory()->create(['email' => 'inactive@example.com', 'email_verified_at' => now(), 'is_active' => false]);

        Queue::fake();

        (new SendMaintenanceNotice('maintenance_completed'))->handle();

        $recipients = [];
        foreach (Queue::pushedJobs()[SendTemplatedEmail::class] ?? [] as $pushed) {
            $job = $pushed['job'];
            $recipients[] = Closure::bind(fn () => $this->to, $job, SendTemplatedEmail::class)();
        }

        $this->assertSame([$wanted->email], $recipients);
    }
}
