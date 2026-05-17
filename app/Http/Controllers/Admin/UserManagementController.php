<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $query = User::query()->with('plan');

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('ulid', 'like', "%{$request->search}%");
            });
        }

        // Status Filter
        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        // Plan Filter
        if ($request->plan) {
            $query->where('plan_id', $request->plan);
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'plan']),
            'plans' => Plan::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Show user details and edit form.
     */
    public function show(User $user)
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => $user->load(['plan', 'loginHistory' => fn ($q) => $q->latest()->limit(5)]),
            'plans' => Plan::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'credits' => 'required|numeric|min:0',
            'plan_id' => 'nullable|exists:plans,id',
            'is_active' => 'required|boolean',
            'password' => 'nullable|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'email', 'credits', 'plan_id', 'is_active']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * Bulk actions.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'action' => 'required|string|in:activate,deactivate,add_credits,delete',
            'value' => 'nullable',
        ]);

        $users = User::whereIn('id', $request->ids);

        switch ($request->action) {
            case 'activate':
                $users->update(['is_active' => true]);
                break;
            case 'deactivate':
                $users->update(['is_active' => false]);
                break;
            case 'add_credits':
                $amount = (float) $request->value;
                foreach ($users->get() as $user) {
                    $user->addCredits($amount, 'admin_add', 'Credits added by Administrator');
                }
                break;
            case 'delete':
                $users->delete();
                break;
        }

        return back()->with('success', 'Bulk action completed.');
    }

    /**
     * Impersonate a user.
     */
    public function impersonate(User $user)
    {
        // Save current admin ID in session
        session(['admin_impersonator_id' => Auth::guard('admin')->id()]);

        // Log in as user in web guard
        Auth::guard('web')->login($user);

        return redirect()->route('dashboard')->with('success', "Now impersonating {$user->name}");
    }

    /**
     * Stop impersonating.
     */
    public function stopImpersonating()
    {
        session()->forget('admin_impersonator_id');
        Auth::guard('web')->logout();

        return redirect()->route('admin.dashboard')->with('success', 'Stopped impersonation.');
    }

    /**
     * Export users to CSV.
     */
    public function export()
    {
        $fileName = 'users_'.date('Y-m-d_H-i-s').'.csv';
        $users = User::all();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['ULID', 'Name', 'Email', 'Credits', 'Plan', 'Status', 'Joined At'];

        $callback = function () use ($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->ulid,
                    $user->name,
                    $user->email,
                    $user->credits,
                    $user->plan?->name ?? 'None',
                    $user->is_active ? 'Active' : 'Inactive',
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
