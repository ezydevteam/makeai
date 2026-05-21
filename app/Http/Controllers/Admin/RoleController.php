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
     * Display a listing of the roles.
     */
    public function index()
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.view'), 403);

        $roles = AdminRole::withCount('admins')->get();
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

        $role = AdminRole::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'],
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
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

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? $role->description,
        ]);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        } else {
            $role->permissions()->detach();
        }

        return back()->with('success', translate('Role updated successfully.'));
    }

    /**
     * Remove the specified role.
     */
    public function destroy(AdminRole $role)
    {
        abort_unless(auth('admin')->user()->hasPermission('roles.delete'), 403);

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
