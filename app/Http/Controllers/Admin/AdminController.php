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

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%");
        }

        $admins = $query->paginate(20)->withQueryString();
        $roles = AdminRole::all();

        return Inertia::render('Admin/RBAC/Admins', [
            'admins' => $admins,
            'roles' => $roles,
            'filters' => $request->only('search'),
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

        // Prevent super-admin modification by non-super-admins
        if ($admin->isSuperAdmin() && ! auth('admin')->user()->isSuperAdmin()) {
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

        // Prevent deactivating or changing role of the last super admin
        if ($admin->isSuperAdmin() && ($validated['role_id'] != $admin->role_id || empty($validated['is_active']))) {
            $superAdminCount = Admin::whereHas('role', function ($q) {
                $q->where('slug', 'super-admin');
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

        return back()->with('success', translate('Administrator deleted successfully.'));
    }
}
