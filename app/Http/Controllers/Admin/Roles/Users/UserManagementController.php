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
use App\Services\MailService;
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
        // The internal AI system account is not a customer — it is the account admin AI
        // features bill against. It is excluded from the table and from every stat below, so
        // "Total users" means what an admin thinks it means.
        $query = User::query()->excludingInternal()->with('plan');

        // Status Filter
        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        // Plan Filter
        if ($request->plan) {
            $query->where('plan_id', $request->plan);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('ulid', 'like', "%{$search}%")
                  ->orWhere('profession', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        // 1. Total Users
        $totalUsersCurrent = User::excludingInternal()->count();
        $totalUsersPrevious = User::excludingInternal()->where('created_at', '<', $sevenDaysAgo)->count();

        // 2. New Users (Last 7 Days)
        $newUsersCurrent = User::excludingInternal()->where('created_at', '>=', $sevenDaysAgo)->count();
        $newUsersPrevious = User::excludingInternal()->whereBetween('created_at', [$fourteenDaysAgo, $sevenDaysAgo])->count();

        // 3. Active (7d): users who actually logged in within the last 7 days,
        //    compared against the prior 7-day window — a real engagement trend
        //    (the old "is_active filtered by created_at" measured account age).
        $activeUsersCurrent = User::excludingInternal()->where('last_login_at', '>=', $sevenDaysAgo)->count();
        $activeUsersPrevious = User::excludingInternal()->whereBetween('last_login_at', [$fourteenDaysAgo, $sevenDaysAgo])->count();

        // 4. Banned Users: point-in-time total, trended against the total as it
        //    stood a week ago. banned_at drives the flow; legacy bans (null
        //    banned_at) are treated as pre-existing so they don't skew the delta.
        $bannedUsersCurrent = User::excludingInternal()->where('is_banned', true)->count();
        $bannedUsersPrevious = User::excludingInternal()->where('is_banned', true)
            ->where(function ($q) use ($sevenDaysAgo) {
                $q->whereNull('banned_at')->orWhere('banned_at', '<', $sevenDaysAgo);
            })
            ->count();

        $stats = [
            'total_users' => [
                'value' => $totalUsersCurrent,
                'comparison' => $this->calculateComparison($totalUsersCurrent, $totalUsersPrevious),
            ],
            'new_users' => [
                'value' => $newUsersCurrent,
                'comparison' => $this->calculateComparison($newUsersCurrent, $newUsersPrevious),
            ],
            'active_users' => [
                'value' => $activeUsersCurrent,
                'comparison' => $this->calculateComparison($activeUsersCurrent, $activeUsersPrevious),
            ],
            'banned_users' => [
                'value' => $bannedUsersCurrent,
                'comparison' => $this->calculateComparison($bannedUsersCurrent, $bannedUsersPrevious),
            ],
        ];

        return Inertia::render('Admin/Roles/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['status', 'plan', 'search']),
            'plans' => Plan::active()->get(['id', 'name']),
            'hasTrashedUsers' => User::onlyTrashed()->exists(),
            'stats' => $stats,
        ]);
    }

    private function calculateComparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'label' => $current === 0 ? '0%' : '+100%',
                'type' => $current === 0 ? 'neutral' : 'up',
            ];
        }

        $delta = (($current - $previous) / $previous) * 100;
        $rounded = (int) round(abs($delta));

        if ($rounded === 0) {
            return [
                'label' => '0%',
                'type' => 'neutral',
            ];
        }

        return [
            'label' => ($delta > 0 ? '+' : '-') . $rounded . '%',
            'type' => $delta > 0 ? 'up' : 'down',
        ];
    }

    /**
     * Display trashed users.
     */
    public function trash(Request $request)
    {
        // It can never be trashed (see User::booted), but an install that trashed it before
        // that guard existed would still surface it here — and offer a Force Delete button.
        $query = User::onlyTrashed()->excludingInternal()->with('plan');

        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        if ($request->plan) {
            $query->where('plan_id', $request->plan);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('ulid', 'like', "%{$search}%")
                  ->orWhere('profession', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $users = $query
            ->latest('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Users/Trash', [
            'users' => $users,
            'filters' => $request->only(['status', 'plan', 'search']),
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
            'countries' => collect(\App\Support\CountryCatalog::countries(app()->getLocale()))->map(fn($c) => [
                'value' => $c['code'],
                'label' => $c['name']
            ])->values()->all(),
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
            'country' => $validated['country'] ?? null,
            'profession' => $validated['profession'] ?? null,
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
            'countries' => collect(\App\Support\CountryCatalog::countries(app()->getLocale()))->map(fn($c) => [
                'value' => $c['code'],
                'label' => $c['name']
            ])->values()->all(),
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
            'country' => 'nullable|string|size:2',
            'profession' => 'nullable|string|max:150',
            'password' => 'nullable|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'email', 'credits', 'plan_id', 'is_active', 'country', 'profession']);

        // Capture the pre-update state so we only notify on an actual transition.
        $wasActive = (bool) $user->is_active;

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

        // Email the user when their account is activated or suspended by an admin.
        $isActive = (bool) $user->is_active;
        if ($wasActive !== $isActive) {
            app(MailService::class)->send(
                $isActive ? 'account_activated' : 'account_suspended',
                $user->email,
                ['user_name' => $user->name]
            );
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
        // User::booted() already cancels this delete, but that would be a silent no-op with a
        // "moved to trash" success message on top of it. Say what happened instead.
        if ($user->isInternalAi()) {
            return back()->with('error', translate('The internal AI account is a system account and cannot be deleted.'));
        }

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
        if ($user->isInternalAi()) {
            return back()->with('error', translate('The internal AI account is a system account and cannot be deleted.'));
        }

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
            'ids.*' => 'integer',
            'action' => 'required|string|in:activate,deactivate,add_credits,delete',
            'value' => 'nullable|numeric|min:0|required_if:action,add_credits',
        ]);

        // excludingInternal() is load-bearing here, not belt-and-braces: these are mass-update
        // and mass-delete QUERIES, which do not fire Eloquent model events — so the deleting()
        // guard on the model cannot see them. The system account is filtered out of the set
        // instead, which also stops a hand-crafted POST deactivating or crediting it.
        $users = User::whereIn('id', $request->ids)->excludingInternal();

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

        // Permanent deletion is irreversible — Super Admins only.
        if ($request->action === 'force_delete' && ! auth('admin')->user()->isSuperAdmin()) {
            abort(403, translate('This action is restricted to Super Admins.'));
        }

        // forceDelete() on a query builder bypasses model events too — same reasoning as
        // bulkAction(). The system account is excluded from the set rather than guarded.
        $query = User::onlyTrashed()->whereIn('id', $request->ids)->excludingInternal();

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
        // Nobody signs in as the system account. It has a random password, no owner, and a
        // session as it would be a live login to an account that bypasses credit limits.
        if ($user->isInternalAi()) {
            return back()->with('error', translate('The internal AI account is a system account and cannot be impersonated.'));
        }

        if (! $user->is_active || $user->is_banned) {
            return back()->with('error', translate('You cannot impersonate a deactivated or banned user.'));
        }

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

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // Stream in chunks so exporting a large user base never exhausts memory.
            User::query()->excludingInternal()->with('plan')->orderBy('id')->lazy(500)->each(function (User $user) use ($file) {
                fputcsv($file, [
                    $user->ulid,
                    $user->name,
                    $user->email,
                    $user->credits,
                    $user->plan?->name ?? translate('None'),
                    $user->is_active ? translate('Active') : translate('Inactive'),
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        $statusText = $user->is_active ? translate('User account activated successfully.') : translate('User account deactivated successfully.');
        return back()->with('success', $statusText);
    }

    /**
     * Ban or unban a user. A ban is an app-wide lockout enforced by the
     * NotBanned middleware, TokenGuard, and the comment/review/ticket guards.
     * `banned_at` is stamped/cleared automatically by the User model saving hook.
     */
    public function toggleBan(Request $request, User $user)
    {
        $validated = $request->validate([
            'ban_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $banning = ! $user->is_banned;

        $user->update([
            'is_banned' => $banning,
            'ban_reason' => $banning ? ($validated['ban_reason'] ?? null) : null,
        ]);

        return back()->with('success', $banning
            ? translate('User banned successfully.')
            : translate('User unbanned successfully.'));
    }
}
