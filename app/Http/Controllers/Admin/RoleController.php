<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\AdminRole;
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
        $permissions = AdminPermission::all()->groupBy('group');

        return Inertia::render('Admin/RBAC/Roles', [
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

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($this->filterAssignablePermissions($validated['permissions']));
        } else {
            $role->permissions()->detach();
        }

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
