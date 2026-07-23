<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Plan;
use App\Models\User;
use Tests\TestCase;

/**
 * Error contract for admin-panel mutation routes (PUT / DELETE). Guests must be
 * turned away (JSON 401 / web redirect to the admin login) rather than reaching a
 * controller, a missing record must 404, and an admin lacking the permission must
 * get 403 — none of these may surface as a 500.
 */
class AdminMutationErrorContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        // Otherwise the admin license gate redirects to license settings, masking the
        // auth/permission/404 codes we're actually asserting.
        config(['license.require_verified' => false]);
        // The plans route sits behind the `premium` gate (Extended + subscriptions on).
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('subscriptions_enabled', true, 'boolean', 'ai');
    }

    private function superAdmin(): Admin
    {
        $slug = config('auth.providers.admins.super_admin_slug', 'super-admin');
        $role = AdminRole::firstOrCreate(['slug' => $slug], ['name' => 'Super Admin', 'is_system' => true]);

        return Admin::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@makeai.test',
            'password' => bcrypt('password'), 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function limitedAdmin(bool $active = true): Admin
    {
        // A non-super role with no permissions attached.
        $role = AdminRole::create(['slug' => 'support-'.uniqid(), 'name' => 'Support', 'is_system' => false]);

        return Admin::create([
            'name' => 'Limited', 'email' => 'limited-'.uniqid().'@makeai.test',
            'password' => bcrypt('password'), 'role_id' => $role->id, 'is_active' => $active,
        ]);
    }

    private function targetUser(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100, 'vat_percentage' => 0,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);
    }

    // ─── guests ──────────────────────────────────

    public function test_a_guest_delete_as_json_gets_401(): void
    {
        $user = $this->targetUser();

        $this->deleteJson(route('admin.users.delete', $user))->assertStatus(401);
    }

    public function test_a_guest_delete_as_a_web_request_is_redirected_to_admin_login(): void
    {
        $user = $this->targetUser();

        $this->delete(route('admin.users.delete', $user))
            ->assertRedirect(route('admin.login'));
    }

    public function test_a_guest_put_as_json_gets_401(): void
    {
        $plan = $this->plan();

        $this->putJson(route('admin.plans.update', $plan))->assertStatus(401);
    }

    // ─── authenticated but wrong ─────────────────

    public function test_deleting_a_missing_record_returns_404(): void
    {
        $this->actingAs($this->superAdmin(), 'admin')
            ->deleteJson(route('admin.users.delete', 999999))
            ->assertStatus(404);
    }

    public function test_an_admin_without_the_permission_gets_403(): void
    {
        $user = $this->targetUser();

        $this->actingAs($this->limitedAdmin(), 'admin')
            ->deleteJson(route('admin.users.delete', $user))
            ->assertStatus(403);
    }

    public function test_a_deactivated_admin_is_rejected(): void
    {
        $user = $this->targetUser();

        // AdminAuth logs the account out and refuses it (403 for JSON).
        $this->actingAs($this->limitedAdmin(active: false), 'admin')
            ->deleteJson(route('admin.users.delete', $user))
            ->assertStatus(403);
    }

    // ─── wrong method on an admin route ──────────

    public function test_a_disallowed_method_on_an_admin_route_returns_405(): void
    {
        $plan = $this->plan();

        // admin.plans.update is PUT — a DELETE to the same URI is not allowed.
        $this->actingAs($this->superAdmin(), 'admin')
            ->deleteJson(route('admin.plans.update', $plan))
            ->assertStatus(405);
    }
}
