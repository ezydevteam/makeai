<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * Admin "Log out all sessions" must terminate every stored session for the target
 * user (database session driver) without touching other users' sessions.
 * See UserManagementController::logoutAllSessions.
 */
class AdminUserLogoutSessionsTest extends TestCase
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
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function seedSession(?int $userId, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => 1_700_000_000,
        ]);
    }

    public function test_logs_out_only_the_target_users_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->seedSession($user->id, 'sess-a');
        $this->seedSession($user->id, 'sess-b');
        $this->seedSession($other->id, 'sess-other');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.logout-sessions', $user->ulid))
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
    }

    public function test_rotates_the_remember_token_so_remembered_devices_cant_re_auth(): void
    {
        $user = User::factory()->create(['remember_token' => 'original-token']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.logout-sessions', $user->ulid))
            ->assertSessionHas('success');

        $this->assertNotSame('original-token', $user->fresh()->remember_token);
    }

    public function test_always_reports_success_even_with_no_stored_sessions(): void
    {
        // The remember-token rotation is a real action, so this is always meaningful.
        $user = User::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.logout-sessions', $user->ulid))
            ->assertSessionHas('success');
    }
}
