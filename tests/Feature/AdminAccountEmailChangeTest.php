<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\SendTemplatedEmail;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Closure;
use Tests\TestCase;

class AdminAccountEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'old@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /**
     * The controller used to null `email_verified_at`, a column the `admins`
     * table does not have, so every email change 500'd after already having
     * saved the new address.
     */
    public function test_changing_the_email_succeeds(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.account.profile.update'), [
                'name' => 'Root',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('admin.account.settings'));

        $this->assertSame('new@example.com', $this->admin->fresh()->email);
    }

    public function test_changing_the_email_alerts_the_previous_address(): void
    {
        Queue::fake();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.account.profile.update'), [
                'name' => 'Root',
                'email' => 'new@example.com',
            ]);

        Queue::assertPushed(SendTemplatedEmail::class, function (SendTemplatedEmail $job) {
            // The job's slug/to are protected, so read them in the job's scope.
            $read = fn (string $property) => Closure::bind(
                fn () => $this->{$property}, $job, SendTemplatedEmail::class
            )();

            return $read('slug') === 'email_changed' && $read('to') === 'old@example.com';
        });
    }

    public function test_saving_without_changing_the_email_sends_no_alert(): void
    {
        Queue::fake();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.account.profile.update'), [
                'name' => 'Renamed',
                'email' => 'old@example.com',
            ])
            ->assertRedirect(route('admin.account.settings'));

        Queue::assertNotPushed(SendTemplatedEmail::class);
        $this->assertSame('Renamed', $this->admin->fresh()->name);
    }
}
