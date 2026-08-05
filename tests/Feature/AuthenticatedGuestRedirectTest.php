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
 * Where an already-authenticated visitor lands when they open a guest-only route.
 *
 * The framework's RedirectIfAuthenticated resolves ONE destination for the whole
 * application and is never told which guard matched, so an admin who was already signed in
 * and opened /admin/login was bounced to the public homepage — out of the panel they were
 * standing in. The callback in bootstrap/app.php splits the two by route name.
 */
class AuthenticatedGuestRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'license.require_verified' => false,
            'inertia.testing.ensure_pages_exist' => false,
        ]);

        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);
    }

    private function admin(): Admin
    {
        $role = AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_signed_in_admin_opening_the_admin_login_lands_on_the_dashboard(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * Every guest-only route in the panel, not just the login form — a signed-in admin has
     * no business on the password-reset or two-factor screens either.
     */
    public function test_the_same_holds_for_the_other_admin_guest_routes(): void
    {
        $admin = $this->admin();

        foreach (['/admin/forgot-password', '/admin/reset-password', '/admin/2fa'] as $path) {
            $this->actingAs($admin, 'admin')
                ->get($path)
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    /**
     * The public side is unchanged: a signed-in user opening /login still goes to the
     * homepage, which is where it sent them before the callback existed.
     */
    public function test_a_signed_in_user_opening_the_public_login_still_lands_on_the_homepage(): void
    {
        $this->actingAs($this->user())
            ->get('/login')
            ->assertRedirect(route('home'));
    }

    /**
     * The redirect is the admin GUARD's, not the URL's. A signed-in ordinary user is a guest
     * to that guard and must still be able to reach the admin sign-in form.
     */
    public function test_a_signed_in_user_can_still_open_the_admin_login(): void
    {
        $this->actingAs($this->user())
            ->get('/admin/login')
            ->assertOk();
    }

    /**
     * The branch is on the ROUTE, not on who is signed in: /login is not an admin route, so
     * an admin reaching it is never sent into the panel.
     *
     * It redirects rather than rendering only because actingAs() calls shouldUse('admin'),
     * which makes admin the default guard for the whole test request — so the plain `guest`
     * middleware, which reads the default guard, sees someone authenticated. A browser keeps
     * the two guards on separate sessions and renders the form.
     */
    public function test_an_admin_is_never_sent_into_the_panel_from_the_public_login(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/login')
            ->assertRedirect(route('home'));
    }

    public function test_guests_reach_both_sign_in_forms(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/login')->assertOk();
    }
}
