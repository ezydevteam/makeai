<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Admin;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Services\AddonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Addon routes and admin menus gate themselves with `admin.permission:addon.*` slugs
 * declared in addon.json, but nothing ever created those permission rows — so the pages
 * 403'd for non-super-admins with no way to grant access. And ai-image-pro's permissions
 * (seeded by a bespoke seeder of its own) stayed on the screen after it was deactivated.
 */
class AddonPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
    }

    private function addon(string $slug, bool $active, array $permissions): Addon
    {
        return Addon::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'version' => '1.0.0',
            'is_active' => $active,
            'manifest' => ['name' => ucfirst($slug), 'permissions' => $permissions],
        ]);
    }

    private function superAdmin(): Admin
    {
        $role = AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    public function test_activation_creates_the_permissions_the_addon_declares(): void
    {
        $this->addon('demo-addon', false, [
            ['slug' => 'addon.demo.use', 'name' => 'Use Demo', 'group' => 'Demo'],
            ['slug' => 'addon.demo.settings', 'name' => 'Manage Demo', 'group' => 'Demo'],
        ]);

        $this->assertDatabaseMissing('admin_permissions', ['slug' => 'addon.demo.use']);

        app(AddonService::class)->syncPermissions('demo-addon');

        $this->assertDatabaseHas('admin_permissions', ['slug' => 'addon.demo.use', 'group' => 'Demo']);
        $this->assertDatabaseHas('admin_permissions', ['slug' => 'addon.demo.settings']);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->addon('demo-addon', true, [
            ['slug' => 'addon.demo.use', 'name' => 'Use Demo', 'group' => 'Demo'],
        ]);

        app(AddonService::class)->syncPermissions('demo-addon');
        app(AddonService::class)->syncPermissions('demo-addon');

        $this->assertSame(1, AdminPermission::where('slug', 'addon.demo.use')->count());
    }

    public function test_inactive_addon_permissions_are_hidden_from_the_roles_screen(): void
    {
        $this->addon('active-addon', true, [['slug' => 'addon.act.use', 'name' => 'Use', 'group' => 'ActiveGroup']]);
        $this->addon('dormant-addon', false, [['slug' => 'addon.dor.use', 'name' => 'Use', 'group' => 'DormantGroup']]);

        // Both have rows (dormant kept its row from an earlier activation).
        app(AddonService::class)->syncPermissions('active-addon');
        app(AddonService::class)->syncPermissions('dormant-addon');

        $response = $this->actingAs($this->superAdmin(), 'admin')->get(route('admin.roles.index'));
        $response->assertOk();

        $groups = array_keys($response->viewData('page')['props']['permissions']);

        $this->assertContains('ActiveGroup', $groups, 'An active addon must expose its permissions.');
        $this->assertNotContains('DormantGroup', $groups, 'An inactive addon must not linger on the screen.');
    }

    public function test_saving_a_role_preserves_grants_for_inactive_addons(): void
    {
        $this->addon('dormant-addon', false, [['slug' => 'addon.dor.use', 'name' => 'Use', 'group' => 'DormantGroup']]);
        app(AddonService::class)->syncPermissions('dormant-addon');

        $hidden = AdminPermission::where('slug', 'addon.dor.use')->firstOrFail();
        $visible = AdminPermission::create(['slug' => 'reports.view.x', 'name' => 'View', 'group' => 'reports']);

        $role = AdminRole::create(['name' => 'Editor', 'slug' => 'editor', 'is_system' => false]);
        $role->permissions()->sync([$hidden->id, $visible->id]);

        // The form can only submit what it was shown — the hidden grant is absent.
        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.roles.update', $role), [
                'name' => 'Editor',
                'permissions' => [$visible->id],
            ])
            ->assertSessionHasNoErrors();

        $slugs = $role->fresh()->permissions->pluck('slug')->all();

        $this->assertContains('reports.view.x', $slugs);
        $this->assertContains(
            'addon.dor.use',
            $slugs,
            'A grant for a deactivated addon must survive a role save, or it silently vanishes when the addon is re-enabled.',
        );
    }

    public function test_addon_with_no_declared_permissions_is_a_noop(): void
    {
        $this->addon('bare-addon', true, []);

        app(AddonService::class)->syncPermissions('bare-addon');

        $this->assertSame(0, AdminPermission::where('slug', 'like', 'addon.bare%')->count());
    }
}
