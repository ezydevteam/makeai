<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Display a listing of the administrators.
     */
    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.view'), 403);

        $query = Admin::with('role');

        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        if ($request->role) {
            $query->where('role_id', $request->role);
        }

        $admins = $query->paginate(20)->withQueryString();
        $roles = AdminRole::all();

        return Inertia::render('Admin/RBAC/Admins', [
            'admins' => $admins,
            'roles' => $roles,
            'filters' => $request->only(['status', 'role']),
            'hasTrashedAdmins' => Admin::onlyTrashed()->exists(),
        ]);
    }

    /**
     * Display trashed administrators.
     */
    public function trash(Request $request)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.view'), 403);

        $query = Admin::onlyTrashed()->with('role');

        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        if ($request->role) {
            $query->where('role_id', $request->role);
        }

        $admins = $query->latest('deleted_at')->paginate(20)->withQueryString();

        return Inertia::render('Admin/RBAC/AdminTrash', [
            'admins' => $admins,
            'roles' => AdminRole::all(),
            'filters' => $request->only(['status', 'role']),
        ]);
    }

    /**
     * Store a newly created administrator.
     */
    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.create'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:admin_roles,id',
            'is_active' => 'boolean',
        ]);

        // Prevent non-super-admins from assigning the super-admin role
        $superRoleId = AdminRole::where('slug', config('auth.providers.admins.super_admin_slug', 'super-admin'))->value('id');

        if ((int) $validated['role_id'] === $superRoleId && ! auth('admin')->user()->isSuperAdmin()) {
            return back()->with('error', translate('Only a Super Admin can assign the Super Admin role.'));
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->input('is_active', true);

        Admin::create($validated);

        return back()->with('success', translate('Administrator created successfully.'));
    }

    /**
     * Update the specified administrator.
     */
    public function update(Request $request, Admin $admin)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.edit'), 403);

        $currentAdmin = auth('admin')->user();

        // Prevent super-admin modification by non-super-admins
        if ($admin->isSuperAdmin() && ! $currentAdmin->isSuperAdmin()) {
            return back()->with('error', translate('You cannot modify a Super Admin.'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins')->ignore($admin->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:admin_roles,id',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Prevent self-demotion: super admin can't change their own role
        if ($currentAdmin->id === $admin->id && $admin->isSuperAdmin()) {
            $superRoleId = AdminRole::where('slug', config('auth.providers.admins.super_admin_slug', 'super-admin'))->value('id');

            if ((int) $validated['role_id'] !== $superRoleId) {
                return back()->with('error', translate('You cannot change your own Super Admin role.'));
            }

            // Prevent self-deactivation
            if (empty($validated['is_active'])) {
                return back()->with('error', translate('You cannot deactivate your own account.'));
            }
        }

        // Prevent deactivating or changing role of the last super admin
        if ($admin->isSuperAdmin() && ((int) $validated['role_id'] !== $admin->role_id || empty($validated['is_active']))) {
            $superAdminCount = Admin::whereHas('role', function ($q) {
                $q->where('slug', config('auth.providers.admins.super_admin_slug', 'super-admin'));
            })->where('is_active', true)->count();

            if ($superAdminCount <= 1) {
                return back()->with('error', translate('Cannot modify the role or deactivate the last active Super Admin.'));
            }
        }

        $admin->update($validated);

        return back()->with('success', translate('Administrator updated successfully.'));
    }

    /**
     * Remove the specified administrator.
     */
    public function destroy(Admin $admin)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.delete'), 403);

        // Prevent super admin deletion by non-super admin
        if ($admin->isSuperAdmin() && ! auth('admin')->user()->isSuperAdmin()) {
            return back()->with('error', translate('You cannot delete a Super Admin.'));
        }

        // Prevent deleting yourself
        if (auth('admin')->id() === $admin->id) {
            return back()->with('error', translate('You cannot delete your own account.'));
        }

        $admin->delete();

        return back()->with('success', translate('Administrator moved to trash.'));
    }

    /**
     * Restore a trashed administrator.
     */
    public function restore(Admin $admin)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.delete'), 403);

        $admin->restore();

        return back()->with('success', translate('Administrator restored successfully.'));
    }

    /**
     * Permanently delete a trashed administrator.
     */
    public function forceDelete(Admin $admin)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.delete'), 403);

        if ($admin->isSuperAdmin()) {
            return back()->with('error', translate('Super Admin cannot be permanently deleted.'));
        }

        if (auth('admin')->id() === $admin->id) {
            return back()->with('error', translate('You cannot permanently delete your own account.'));
        }

        $admin->forceDelete();

        return back()->with('success', translate('Administrator permanently deleted.'));
    }

    /**
     * Bulk actions for trashed administrators.
     */
    public function bulkTrashAction(Request $request)
    {
        abort_unless(auth('admin')->user()->hasPermission('admins.delete'), 403);

        $validated = $request->validate([
            'ids' => 'required|array',
            'action' => 'required|string|in:restore,force_delete',
        ]);

        $query = Admin::onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->whereDoesntHave('role', function ($roleQuery) {
                $roleQuery->where('slug', config('auth.providers.admins.super_admin_slug', 'super-admin'));
            });

        if ($validated['action'] === 'restore') {
            $query->restore();
        }

        if ($validated['action'] === 'force_delete') {
            $query->whereKeyNot(auth('admin')->id())->forceDelete();
        }

        return back()->with('success', translate('Bulk action completed.'));
    }
}
