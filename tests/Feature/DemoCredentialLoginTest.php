<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A demo site publishes its own sign-in credentials — that is what makes it a demo. The
 * hazard is the install that comes afterwards: the demo package ships a seeded database,
 * and turning DEMO_ENABLED off does not change the fact that the super admin's password is
 * printed in the product listing.
 *
 * These pin the gate: with demo mode off, the published credentials are refused before
 * authentication runs, and everyone else signs in exactly as before.
 */
class DemoCredentialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_PASSWORD = 'demo-admin-password';

    private const USER_PASSWORD = 'demo-user-password';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'license.require_verified' => false,
            'inertia.testing.ensure_pages_exist' => false,
            'demo.admin_email' => 'admin@demo.com',
            'demo.user_email' => 'demo@demo.com',
            'demo.admin_password' => self::ADMIN_PASSWORD,
            'demo.user_password' => self::USER_PASSWORD,
        ]);

        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);
    }

    private function user(string $email, string $password): User
    {
        return User::create([
            'name' => 'Demo Creator',
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function admin(string $email, string $password): Admin
    {
        $role = AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::create([
            'name' => 'Demo Administrator',
            'email' => $email,
            'password' => $password,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function signIn(string $email, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    private function signInAsAdmin(string $email, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->post('/admin/login', ['email' => $email, 'password' => $password]);
    }

    // ─── Demo mode off: the published credentials are dead ───

    public function test_demo_user_cannot_sign_in_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);
        $this->user('demo@demo.com', self::USER_PASSWORD);

        $this->signIn('demo@demo.com', self::USER_PASSWORD)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_demo_admin_cannot_sign_in_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);
        $this->admin('admin@demo.com', self::ADMIN_PASSWORD);

        $this->signInAsAdmin('admin@demo.com', self::ADMIN_PASSWORD)->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    /**
     * DemoSeeder gives fifty sample users and three staff admins the same two passwords as
     * the published pair, under ordinary-looking addresses. Matching the password is what
     * closes those without keeping a list of every fixture the seeder happens to create.
     */
    public function test_a_seeded_fixture_sharing_a_demo_password_cannot_sign_in_either(): void
    {
        config(['demo.enabled' => false]);
        $this->user('amelia7@demo.com', self::USER_PASSWORD);
        $this->admin('manager@demo.com', self::ADMIN_PASSWORD);

        $this->signIn('amelia7@demo.com', self::USER_PASSWORD)->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->signInAsAdmin('manager@demo.com', self::ADMIN_PASSWORD)->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    /**
     * The block is on the credentials, not on the account: the demo address with some other
     * password is refused too, so re-pointing DEMO_ADMIN_EMAIL at a live account cannot
     * quietly re-open it.
     */
    public function test_a_demo_address_is_refused_whatever_password_is_offered(): void
    {
        config(['demo.enabled' => false]);
        $this->user('demo@demo.com', 'a-completely-different-password');

        $this->signIn('demo@demo.com', 'a-completely-different-password')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ─── Demo mode on: the credentials are the front door ───

    public function test_demo_user_signs_in_normally_when_demo_mode_is_on(): void
    {
        config(['demo.enabled' => true]);
        $user = $this->user('demo@demo.com', self::USER_PASSWORD);

        $this->signIn('demo@demo.com', self::USER_PASSWORD);

        $this->assertAuthenticatedAs($user);
    }

    public function test_demo_admin_signs_in_normally_when_demo_mode_is_on(): void
    {
        config(['demo.enabled' => true]);
        $admin = $this->admin('admin@demo.com', self::ADMIN_PASSWORD);

        $this->signInAsAdmin('admin@demo.com', self::ADMIN_PASSWORD);

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    // ─── Everyone else is untouched ───

    public function test_a_real_account_still_signs_in_with_demo_mode_off(): void
    {
        config(['demo.enabled' => false]);
        $user = $this->user('owner@example.com', 'a-password-of-their-own');

        $this->signIn('owner@example.com', 'a-password-of-their-own');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_real_admin_still_signs_in_with_demo_mode_off(): void
    {
        config(['demo.enabled' => false]);
        $admin = $this->admin('root@example.com', 'a-password-of-their-own');

        $this->signInAsAdmin('root@example.com', 'a-password-of-their-own');

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * An install that never configured demo passwords — every install except the seller's
     * demo — must not have blank treated as a match, or the first empty password submitted
     * would be refused with a confusing message.
     */
    public function test_unset_demo_passwords_block_nothing(): void
    {
        config([
            'demo.enabled' => false,
            'demo.admin_password' => null,
            'demo.user_password' => null,
        ]);

        $user = $this->user('owner@example.com', '');

        // Not a realistic password, but it proves blank does not match blank.
        $this->assertNotNull($user);
        $this->signIn('owner@example.com', 'wrong')->assertSessionHasErrors('email');
        $this->assertGuest();

        $real = $this->user('second@example.com', 'their-own-password');
        $this->signIn('second@example.com', 'their-own-password');
        $this->assertAuthenticatedAs($real);
    }
}
