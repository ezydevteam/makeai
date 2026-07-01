<?php

namespace App\Http\Controllers\Admin\Roles\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendUserNotificationRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Jobs\SendAdminNotificationEmail;
use App\Models\AiUsageLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\InAppNotificationService;
use App\Services\NotificationEventService;
use Carbon\CarbonImmutable;
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

        // Status Filter
        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        // Plan Filter
        if ($request->plan) {
            $query->where('plan_id', $request->plan);
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Roles/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['status', 'plan']),
            'plans' => Plan::active()->get(['id', 'name']),
            'hasTrashedUsers' => User::onlyTrashed()->exists(),
        ]);
    }

    /**
     * Display trashed users.
     */
    public function trash(Request $request)
    {
        $query = User::onlyTrashed()->with('plan');

        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        if ($request->plan) {
            $query->where('plan_id', $request->plan);
        }

        $users = $query
            ->latest('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Users/Trash', [
            'users' => $users,
            'filters' => $request->only(['status', 'plan']),
            'plans' => Plan::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return Inertia::render('Admin/Roles/Users/Create', [
            'plans' => Plan::active()->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'credits' => $validated['credits'],
            'plan_id' => $validated['plan_id'] ?? null,
            'is_active' => $validated['is_active'],
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', translate('User created successfully.'));
    }

    /**
     * Show user details and edit form.
     */
    public function show(User $user)
    {
        return Inertia::render('Admin/Roles/Users/Show', [
            'user' => $user->load(['plan', 'loginHistory' => fn($q) => $q->latest()->limit(5)]),
            'plans' => Plan::active()->get(['id', 'name']),
            'usageHistory' => AiUsageLog::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(10)
                ->get([
                    'id',
                    'tool_slug',
                    'model',
                    'provider',
                    'input_tokens',
                    'output_tokens',
                    'credits_used',
                    'status',
                    'created_at',
                ]),
        ]);
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'credits' => 'required|numeric|min:0',
            'plan_id' => 'nullable|exists:plans,id',
            'is_active' => 'required|boolean',
            'password' => 'nullable|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'email', 'credits', 'plan_id', 'is_active']);

        if ($request->filled('password')) {
            // Check password history (prevent reuse of last 3 passwords)
            $recentPasswords = $user->passwordHistory()->latest()->limit(3)->pluck('password')->toArray();
            foreach ($recentPasswords as $hashedPassword) {
                if (Hash::check($request->password, $hashedPassword)) {
                    return back()->with('error', translate('You cannot reuse a recent password.'));
                }
            }

            // Save current password to history before updating
            $user->passwordHistory()->create(['password' => $user->password]);

            $data['password'] = Hash::make($request->password);
            $data['password_changed_at'] = now();
        }

        $user->update($data);

        if ($request->filled('password')) {
            app(NotificationEventService::class)->passwordChanged($user);
        }

        return back()->with('success', translate('User updated successfully.'));
    }

    public function sendNotification(
        SendUserNotificationRequest $request,
        User $user,
        InAppNotificationService $notifications
    ) {
        $validated = $request->validated();
        $scheduledAt = filled($validated['scheduled_at'] ?? null)
            ? CarbonImmutable::parse($validated['scheduled_at'])
            : null;
        $deliverVia = $validated['deliver_via'];
        $payload = [
            'title' => $validated['title'],
            'message' => $validated['message'],
            'level' => $validated['level'],
            'category' => 'admin_message',
            'action_url' => $validated['action_url'] ?? null,
            'action_label' => $validated['action_label'] ?? null,
            'meta' => [
                'sent_by_admin_id' => auth('admin')->id(),
                'deliver_via' => $deliverVia,
                'scheduled_at' => $scheduledAt?->toISOString(),
            ],
        ];

        if (in_array($deliverVia, ['in_app', 'in_app_email'], true)) {
            $notifications->send($user, $payload, $scheduledAt !== null, $scheduledAt);
        }

        if (in_array($deliverVia, ['email', 'in_app_email'], true)) {
            $dispatch = SendAdminNotificationEmail::dispatch($user->id, [
                'title' => $validated['title'],
                'message' => $validated['message'],
                'action_url' => $validated['action_url'] ?? null,
                'action_label' => $validated['action_label'] ?? null,
            ])->onQueue('emails');

            if ($scheduledAt) {
                $dispatch->delay($scheduledAt);
            }
        }

        return back()->with('success', $scheduledAt
            ? translate('Notification scheduled for :name.', ['name' => $user->name])
            : translate('Notification queued for :name.', ['name' => $user->name]));
    }

    public function disableTwoFactor(User $user)
    {

        if (! $user->hasTotpEnabled()) {
            return back()->with('info', translate('Two-factor authentication is not enabled for this user.'));
        }

        $user->disableTotp();

        return back()->with('success', translate('Two-factor authentication disabled for :name.', ['name' => $user->name]));
    }

    /**
     * Soft delete the specified user.
     */
    public function destroy(User $user)
    {
        $userName = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', translate('User :name moved to trash.', ['name' => $userName]));
    }

    /**
     * Restore the specified user from trash.
     */
    public function restore(User $user)
    {
        $user->restore();

        return back()->with('success', translate('User restored successfully.'));
    }

    /**
     * Permanently delete the specified user.
     */
    public function forceDelete(User $user)
    {
        $userName = $user->name;
        $user->forceDelete();

        return back()->with('success', translate('User :name permanently deleted.', ['name' => $userName]));
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
                    $user->addCredits($amount, 'admin_add', translate('Credits added by Administrator'));
                }
                break;
            case 'delete':
                $users->delete();
                break;
        }

        return back()->with('success', translate('Bulk action completed.'));
    }

    /**
     * Bulk actions for trashed users.
     */
    public function bulkTrashAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'action' => 'required|string|in:restore,force_delete',
        ]);

        $query = User::onlyTrashed()->whereIn('id', $request->ids);

        if ($request->action === 'restore') {
            $query->restore();
        }

        if ($request->action === 'force_delete') {
            $query->forceDelete();
        }

        return back()->with('success', translate('Bulk action completed.'));
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

        return redirect()->route('user.dashboard')->with('success', translate('Now logged in as :name', ['name' => $user->name]));
    }

    /**
     * Stop impersonating.
     */
    public function stopImpersonating()
    {
        $adminId = session('admin_impersonator_id');
        session()->forget('admin_impersonator_id');

        // Log out the web guard (user)
        Auth::guard('web')->logout();

        // Regenerate session to prevent session fixation and clear residual user data
        session()->regenerate();

        // Log the admin back in if we have their ID
        if ($adminId) {
            $admin = \App\Models\Admin::find($adminId);
            if ($admin) {
                Auth::guard('admin')->login($admin);
            }
        }

        return redirect()->route('admin.dashboard')->with('success', translate('Stopped impersonation.'));
    }

    /**
     * Export users to CSV.
     */
    public function export()
    {
        $fileName = 'users_' . date('Y-m-d_H-i-s') . '.csv';
        $users = User::all();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            translate('ULID'),
            translate('Name'),
            translate('Email'),
            translate('Credits'),
            translate('Plan'),
            translate('Status'),
            translate('Joined At'),
        ];

        $callback = function () use ($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->ulid,
                    $user->name,
                    $user->email,
                    $user->credits,
                    $user->plan?->name ?? translate('None'),
                    $user->is_active ? translate('Active') : translate('Inactive'),
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
