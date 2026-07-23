<?php

namespace App\Http\Controllers\Admin\Roles\Users;

use App\Http\Controllers\Concerns\AuthorizesAdminActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendUserNotificationRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Jobs\SendAdminNotificationEmail;
use App\Jobs\SendAdminNotificationSms;
use App\Models\AiUsageLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\InAppNotificationService;
use App\Services\MailService;
use App\Services\NotificationEventService;
use App\Support\CountryCatalog;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    use AuthorizesAdminActions;

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
     * Store a newly created user.
     *
     * User creation is served by the modal on the index page (Index.vue); there is no
     * standalone create page/route anymore.
     */
    public function store(StoreUserRequest $request)
    {
        // Creation requires the explicit users.create permission — users.manage (the
        // read/bulk umbrella) does not grant it. Matches the granular delete/edit gates.
        $this->authorizeAdmin('users.create');

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'credits' => $validated['credits'],
            // Never assign a plan on a Regular license, whatever the form posted.
            // Mirrors the same guard in update().
            'plan_id' => isProAvailable() ? ($validated['plan_id'] ?? null) : null,
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
            'canAssignPlan' => isProAvailable(),
            'plans' => isProAvailable() ? Plan::active()->get(['id', 'name']) : [],
            'countries' => collect(\App\Support\CountryCatalog::countries(app()->getLocale()))->map(fn($c) => [
                'value' => $c['code'],
                'label' => $c['name']
            ])->values()->all(),
            'timezones' => collect(timezone_identifiers_list())
                ->map(fn(string $tz) => ['value' => $tz, 'label' => $tz])
                ->values()->all(),
            // Enables the "Text message" / "In-app + text" delivery options in the
            // notification modal: active SMS gateway AND a verified phone on file.
            'smsDeliveryAvailable' => user_can_receive_sms($user),
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
        // Normalize the phone to the national number for its region (same rules as
        // the self-service profile): strips the dial code/formatting, keeps leading
        // zeros. phone_country is only kept when a phone is actually present. Done
        // BEFORE validation so the uniqueness rule below checks the exact
        // (national number, country) pair we persist rather than the raw input.
        $phoneCountry = filled($request->input('phone_country')) ? strtoupper((string) $request->input('phone_country')) : null;
        $normalizedPhone = PhoneNumber::nationalNumber($request->input('phone'), $phoneCountry);
        $request->merge([
            'phone' => $normalizedPhone,
            'phone_country' => $normalizedPhone !== null ? $phoneCountry : null,
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'credits' => 'required|numeric|min:0',
            'plan_id' => 'nullable|exists:plans,id',
            'is_active' => 'required|boolean',
            'country' => 'nullable|string|size:2',
            'profession' => 'nullable|string|max:150',
            'phone' => [
                'nullable', 'string', 'max:32',
                function ($attribute, $value, $fail) use ($request) {
                    if (filled($value) && ! PhoneNumber::isValid($value, $request->input('phone_country'))) {
                        $fail(translate('The phone number is not valid for the selected country.'));
                    }
                },
                // A given number (national digits + country) may belong to one user
                // only; scoped to phone_country because the same national digits are
                // a different real number in another country. Null phones are exempt.
                Rule::unique('users', 'phone')
                    ->where(fn ($query) => $query->where('phone_country', $request->input('phone_country')))
                    ->ignore($user->id),
            ],
            'phone_country' => 'nullable|required_with:phone|string|size:2',
            'timezone' => 'required|string|timezone',
            'daily_limit' => 'nullable|numeric|min:0',
            'monthly_limit' => 'nullable|numeric|min:0',
            'password' => 'nullable|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'email', 'credits', 'plan_id', 'is_active', 'country', 'profession', 'timezone', 'daily_limit', 'monthly_limit']);
        $data['phone'] = $normalizedPhone;
        $data['phone_country'] = $normalizedPhone !== null ? $phoneCountry : null;

        // Subscription/billing fields (the purchasable wallet + plan) are only
        // meaningful when Pro is available. In quota mode they must not be mutated
        // via this endpoint even if the request carries them.
        if (! isProAvailable()) {
            unset($data['credits'], $data['plan_id']);
        }

        // A changed phone (number or country) invalidates any prior verification.
        $phoneChanged = $data['phone'] !== $user->phone || $data['phone_country'] !== $user->phone_country;

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

        if ($phoneChanged && $user->phone_verified_at !== null) {
            $user->forceFill(['phone_verified_at' => null])->save();
        }

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

    /**
     * Force-sign this user out of all devices.
     *
     * Rotating the remember token is what actually terminates access: without it, a
     * deleted session simply re-authenticates from the user's "remember me" cookie on
     * the next request. We then drop their stored sessions (database driver) but skip
     * the acting admin's current session — in a shared browser the admin and user
     * share one session row, and deleting it would sign the admin out and swallow the
     * success flash.
     */
    public function logoutAllSessions(Request $request, User $user): RedirectResponse
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', translate(':name has been signed out of all devices.', ['name' => $user->name]));
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

        // Validate the SMS gate up-front so a combined "In-app + text" delivery never
        // sends the in-app half and then fails on the SMS half.
        $wantsSms = in_array($deliverVia, ['sms', 'in_app_sms'], true);
        if ($wantsSms && ! user_can_receive_sms($user)) {
            return back()->with('error', translate('SMS delivery is unavailable for this user.'));
        }

        if (in_array($deliverVia, ['in_app', 'in_app_email', 'in_app_sms'], true)) {
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

        if ($wantsSms) {
            $dispatch = SendAdminNotificationSms::dispatch($user->id, [
                'title' => $validated['title'],
                'message' => $validated['message'],
            ])->onQueue('default');

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
        // Deletion requires the explicit users.delete permission. The broad users.manage
        // grant intentionally does NOT cover it — the built-in "manager" role has
        // users.manage but not users.delete, and must not be able to delete users.
        $this->authorizeAdmin('users.delete');

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
        $this->authorizeAdmin('users.delete');

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

        // UserObserver::forceDeleting() cancels the gateway subscription and anonymises the
        // user's comments as part of this. Transactional so a failure part-way cannot leave
        // the account half-purged.
        DB::transaction(fn () => $user->forceDelete());

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

        // Each bulk sub-action requires the same granular permission as its single-user
        // equivalent — users.manage does not blanket-grant them. activate/deactivate are
        // edits, add_credits needs users.credits, delete needs users.delete.
        match ($request->action) {
            'delete' => $this->authorizeAdmin('users.delete'),
            'add_credits' => $this->authorizeAdmin('users.credits'),
            default => $this->authorizeAdmin('users.edit'), // activate, deactivate
        };

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
        $this->authorizeAdmin('users.delete');

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
        // Impersonation requires the explicit users.impersonate permission — users.manage
        // does not grant account takeover (the "manager" role has manage but not this).
        $this->authorizeAdmin('users.impersonate');

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

        // Plans/billing only exist on the Extended license — drop the Plan column
        // entirely on a Regular license, where every user is on the Free plan.
        $includePlan = isProAvailable();

        $columns = array_values(array_filter([
            translate('ULID'),
            translate('Name'),
            translate('Email'),
            translate('Phone'),
            translate('Country'),
            translate('Timezone'),
            translate('Credits'),
            translate('Daily Usage'),
            translate('Monthly Usage'),
            $includePlan ? translate('Plan') : null,
            translate('Status'),
            translate('Joined At'),
        ], fn ($column) => $column !== null));

        $callback = function () use ($columns, $includePlan) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // Stream in chunks so exporting a large user base never exhausts memory.
            User::query()->excludingInternal()->with('plan')->orderBy('id')->lazy(500)->each(function (User $user) use ($file, $includePlan) {
                $row = [
                    $user->ulid,
                    $user->name,
                    $user->email,
                    // Dialable E.164 form (falls back to the stored national number).
                    PhoneNumber::e164($user->phone, $user->phone_country) ?? $user->phone,
                    CountryCatalog::countryName($user->country) ?? $user->country,
                    $user->timezone,
                    $user->credits,
                    $user->credits_used_today,
                    $user->credits_used_month,
                ];

                if ($includePlan) {
                    $row[] = $user->plan?->name ?? translate('None');
                }

                $row[] = $user->is_active ? translate('Active') : translate('Inactive');
                $row[] = $user->created_at->format('Y-m-d H:i:s');

                fputcsv($file, $row);
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
        $this->authorizeAdmin('users.edit');

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
