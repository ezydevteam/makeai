<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\SendTemplatedEmail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Changing a password from inside an authenticated session used to send nothing
 * — only the forgot-password reset flow alerted the account owner. That left the
 * one case an attacker with a live session would actually use unannounced.
 */
class PasswordChangeAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);
    }

    private function assertAlertQueuedTo(string $email): void
    {
        Queue::assertPushed(SendTemplatedEmail::class, function (SendTemplatedEmail $job) use ($email) {
            // The job's slug/to are protected, so read them in the job's scope.
            $read = fn (string $property) => Closure::bind(
                fn () => $this->{$property}, $job, SendTemplatedEmail::class
            )();

            return $read('slug') === 'password_changed' && $read('to') === $email;
        });
    }

    public function test_user_password_change_alerts_the_account(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'old-password',
        ]);

        $this->actingAs($user)
            ->put(route('user.dashboard.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('user.dashboard.profile'));

        $this->assertAlertQueuedTo('user@example.com');
    }

    public function test_user_password_change_sends_nothing_when_it_fails(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'old-password',
        ]);

        $this->actingAs($user)
            ->put(route('user.dashboard.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        Queue::assertNotPushed(SendTemplatedEmail::class);
    }

    public function test_admin_password_change_alerts_the_account(): void
    {
        Queue::fake();

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'old-password',
            'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.account.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('admin.account.settings'));

        $this->assertAlertQueuedTo('root@example.com');
    }

    public function test_admin_password_change_sends_nothing_when_it_fails(): void
    {
        Queue::fake();

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'old-password',
            'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        Queue::assertNotPushed(SendTemplatedEmail::class);
    }
}
