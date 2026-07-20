<?php

namespace App\Http\Controllers\Admin\Roles\Admins;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Services\AddonService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Permission prefixes that only a Super Admin can grant.
     * These control admin/role management, impersonation, and license.
     */
    private const PRIVILEGED_PREFIXES = [
        'admins.',
        'roles.',
        'users.impersonate',
        'settings.license',
    ];

    /**
     * Strip permissions the current admin is not allowed to assign.
     * Non-super-admins cannot grant privileged permissions, nor
     * permissions they don't themselves possess.
     */
    private function filterAssignablePermissions(array $permissionIds): array
    {
        $admin = auth('admin')->user();

        if ($admin->isSuperAdmin()) {
            return $permissionIds;
        }

        $permissions = AdminPermission::whereIn('id', $permissionIds)->get();
        $adminSlugs = $admin->getAllPermissions();

        return $permissions->filter(function (AdminPermission $perm) use ($adminSlugs) {
            // Block privileged prefixes entirely
            foreach (self::PRIVILEGED_PREFIXES as $prefix) {
                if (str_starts_with($perm->slug, $prefix)) {
                    return false;
                }
            }

            // Only allow permissions the admin themselves have
            return in_array($perm->slug, $adminSlugs, true);
        })->pluck('id')->toArray();
    }

    /**
     * Permission slugs belonging to an addon that is not currently active.
     *
     * These are hidden from the screen (an inactive addon's permissions are noise, and its
     * routes are unreachable anyway) but their rows and grants are KEPT, so deactivating and
     * re-activating an addon does not silently strip a role's access.
     *
     * @return string[]
     */
    private function hiddenPermissionSlugs(): array
    {
        return app(AddonService::class)->inactivePermissionSlugs();
    }

    /**
     * IDs of permissions a role already holds that are hidden from the form.
     *
     * The form only submits the permissions it can see, so a plain sync() would detach every
     * hidden one. These are merged back in on save.
     *
     * @return int[]
     */
    private function preservedPermissionIds(AdminRole $role): array
    {
        $hidden = $this->hiddenPermissionSlugs();

        if ($hidden === []) {
            return [];
        }

        return $role->permissions()
            ->whereIn('slug', $hidden)
            ->pluck('admin_permissions.id')
            ->all();
    }

    /**
     * Display a listing of the roles.
     */
    public function index()
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.view'), 403);

        $roles = AdminRole::with(['permissions:id'])->withCount('admins')->get()
            ->map(function (AdminRole $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'admins_count' => $role->admins_count,
                    'permissions' => $role->permissions->map(fn (AdminPermission $permission) => ['id' => $permission->id])->values(),
                    'default_permission_slugs' => $role->defaultPermissionSlugs(),
                    'has_default_permissions' => $role->hasDefaultPermissions(),
                ];
            });
        // Only show permissions that are actually reachable: core ones, plus those belonging
        // to an ACTIVE addon. An inactive addon's permissions gate routes that cannot be hit,
        // so listing them just invites an operator to grant access that does nothing.
        $permissions = AdminPermission::query()
            ->when(
                $hidden = $this->hiddenPermissionSlugs(),
                fn ($query) => $query->whereNotIn('slug', $hidden),
            )
            ->get()
            ->groupBy('group');

        return Inertia::render('Admin/Roles/Admins/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.create'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:admin_roles',
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:admin_permissions,id',
        ]);

        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');

        // Block creating a role with the super-admin slug
        if (Str::slug($validated['name']) === $superSlug) {
            return back()->with('error', translate('This role name is reserved.'));
        }

        $role = AdminRole::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'],
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($this->filterAssignablePermissions($validated['permissions']));
        }

        return back()->with('success', translate('Role created successfully.'));
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, AdminRole $role)
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.edit'), 403);

        if ($role->is_system) {
            return back()->with('error', translate('System roles cannot be modified.'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:admin_roles,name,'.$role->id,
            'description' => 'nullable|string|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:admin_permissions,id',
        ]);

        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');

        // Block renaming a role to the super-admin slug
        if (Str::slug($validated['name']) === $superSlug && $role->slug !== $superSlug) {
            return back()->with('error', translate('This role name is reserved.'));
        }

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? $role->description,
        ]);

        // The form can only submit permissions it was shown, so merge back any the role holds
        // for a currently-inactive addon — otherwise saving a role here would silently revoke
        // access that reappears (missing) the moment that addon is switched back on.
        $submitted = isset($validated['permissions'])
            ? $this->filterAssignablePermissions($validated['permissions'])
            : [];

        $role->permissions()->sync(array_values(array_unique(array_merge(
            $submitted,
            $this->preservedPermissionIds($role),
        ))));

        return back()->with('success', translate('Role updated successfully.'));
    }

    /**
     * Restore a role's seeded default permissions.
     */
    public function restoreDefault(AdminRole $role)
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.edit'), 403);

        if (! $role->hasDefaultPermissions()) {
            return back()->with('error', translate('This role does not have a default permission set.'));
        }

        $role->syncDefaultPermissions();

        return back()->with('success', translate('Default permissions restored successfully.'));
    }

    /**
     * Remove the specified role.
     */
    public function destroy(AdminRole $role)
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.delete'), 403);

        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');

        if ($role->slug === $superSlug) {
            return back()->with('error', translate('The Super Admin role cannot be deleted.'));
        }

        if ($role->is_system) {
            return back()->with('error', translate('System roles cannot be deleted.'));
        }

        if ($role->admins()->count() > 0) {
            return back()->with('error', translate('Cannot delete role because it is assigned to one or more administrators.'));
        }

        $role->delete();

        return back()->with('success', translate('Role deleted successfully.'));
    }
}
