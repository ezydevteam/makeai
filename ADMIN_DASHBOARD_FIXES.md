# Admin Dashboard Fixes - Implementation Plan

> **Status:** Ready for implementation
> **Created:** 2026-06-18
> **Priority:** Critical → High → Medium → Low

---

## Phase 1: Critical Security Fixes

### 1.1 Add Permission Middleware to Protected Routes

**File:** `routes/admin.php`

**Changes:**
```php
// Wrap user management routes (lines ~130-152)
Route::middleware('admin.permission:users.manage')->group(function () {
    Route::get('roles/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::post('roles/users', [UserManagementController::class, 'store'])->name('admin.users.store');
    Route::delete('roles/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.delete');
    // ... other user routes
});

// Wrap admin management routes (lines ~154-161)
Route::middleware('admin.permission:admins.manage')->group(function () {
    Route::get('roles/admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::post('roles/admins', [AdminController::class, 'store'])->name('admin.admins.store');
    Route::delete('roles/admins/{admin}', [AdminController::class, 'destroy'])->name('admin.admins.delete');
});

// Wrap role/permission routes (lines ~168-175)
Route::middleware('admin.permission:roles.manage')->group(function () {
    Route::get('roles/admins/permissions', [RoleController::class, 'index'])->name('admin.roles.index');
    Route::post('roles/admins/permissions', [RoleController::class, 'store'])->name('admin.roles.store');
});

// Wrap settings routes (lines ~312-320)
Route::middleware('admin.permission:settings.manage')->group(function () {
    Route::get('settings', [GeneralSettingsController::class, 'edit'])->name('admin.settings.edit');
    Route::put('settings', [GeneralSettingsController::class, 'update'])->name('admin.settings.update');

    Route::get('settings/features', [FeatureSettingsController::class, 'edit'])->name('admin.settings.features');
    Route::put('settings/features', [FeatureSettingsController::class, 'update'])->name('admin.settings.features.update');

    Route::get('settings/gdpr', [GdprSettingsController::class, 'edit'])->name('admin.settings.gdpr');
    Route::put('settings/gdpr', [GdprSettingsController::class, 'update'])->name('admin.settings.gdpr.update');
});

// Wrap dashboard routes (lines ~100-101)
Route::middleware('admin.permission:dashboard.view')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('activity', [AdminDashboardController::class, 'activity'])->name('admin.activity');
});

// Wrap admin notes routes (lines ~104-108)
Route::middleware('admin.permission:notes.manage')->group(function () {
    Route::get('notes', [AdminNoteController::class, 'index'])->name('admin.notes.index');
    Route::post('notes', [AdminNoteController::class, 'store'])->name('admin.notes.store');
    Route::delete('notes/{note}', [AdminNoteController::class, 'destroy'])->name('admin.notes.delete');
});
```

**New Permissions to Add:**
Update `AdminRole::defaultPermissionSlugsMap()` to include:
- `admins.manage`
- `roles.manage`
- `notes.view`
- `notes.manage`

**Files to Update:**
- `app/Models/AdminRole.php` - Add new permission slugs
- `database/seeders/AdminRoleSeeder.php` - Seed new permissions

**Verification:**
- Test that non-super-admin users without permissions get 403
- Test that users with permissions can access routes
- Check existing admin accounts still work

---

### 1.2 Add GDPR Settings Permission Check

**File:** `app/Http/Controllers/Admin/GdprSettingsController.php`

**Changes:**
```php
public function edit()
{
    abort_unless(
        auth('admin')->user()?->hasAnyPermission(['settings.gdpr', 'settings.manage']),
        403
    );

    return Inertia::render('Admin/Settings/Gdpr', [
        'settings' => [
            'gdpr_cookie_banner_enabled' => (bool) settings('gdpr_cookie_banner_enabled', true),
            'gdpr_cookie_banner_message' => settings('gdpr_cookie_banner_message', ''),
            'gdpr_data_retention_days' => (int) settings('gdpr_data_retention_days', 365),
        ],
    ]);
}

public function update(Request $request)
{
    abort_unless(
        auth('admin')->user()?->hasAnyPermission(['settings.gdpr', 'settings.manage']),
        403
    );

    $validated = $request->validate([
        'gdpr_cookie_banner_enabled' => 'boolean',
        'gdpr_cookie_banner_message' => 'nullable|string|max:1000',
        'gdpr_data_retention_days' => 'integer|min:30|max:3650',
    ]);

    foreach ($validated as $key => $value) {
        settings_set($key, $value);
    }

    return redirect()->back()->with('success', 'GDPR settings updated.');
}
```

**Add Permission:**
Update `AdminRole::defaultPermissionSlugsMap()`:
```php
'settings.gdpr' => 'Manage GDPR settings',
```

**Verification:**
- Test 403 response without permission
- Test successful update with permission

---

### 1.3 Implement Super Admin Audit Logging

**File:** `app/Http/Middleware/AdminAuditLog.php` (NEW)

**Create middleware:**
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $admin = auth('admin')->user();

        if ($admin && $admin->isSuperAdmin() && in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            DB::table('admin_audit_logs')->insert([
                'admin_id' => $admin->id,
                'action' => $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => json_encode($request->except(['password', 'password_confirmation'])),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
```

**Create Migration:**
```bash
php artisan make:migration create_admin_audit_logs_table
```

```php
Schema::create('admin_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
    $table->string('action');
    $table->string('ip_address', 45);
    $table->text('user_agent')->nullable();
    $table->json('payload')->nullable();
    $table->timestamp('created_at');

    $table->index(['admin_id', 'created_at']);
});
```

**Register Middleware:**
In `app/Http/Kernel.php` or `bootstrap/app.php`:
```php
'admin.audit' => \App\Http\Middleware\AdminAuditLog::class,
```

**Apply to Routes:**
In `routes/admin.php`:
```php
Route::middleware(['admin.auth', 'admin.audit'])->group(function () {
    // All admin routes
});
```

**Create Admin Page:**
`app/Http/Controllers/Admin/AuditLogController.php`
```php
public function index()
{
    abort_unless(auth('admin')->user()?->isSuperAdmin(), 403);

    $logs = DB::table('admin_audit_logs')
        ->join('admins', 'admin_audit_logs.admin_id', '=', 'admins.id')
        ->select('admin_audit_logs.*', 'admins.name as admin_name', 'admins.email as admin_email')
        ->orderBy('created_at', 'desc')
        ->paginate(50);

    return Inertia::render('Admin/AuditLog/Index', ['logs' => $logs]);
}
```

**Create Vue Page:**
`resources/js/Pages/Admin/AuditLog/Index.vue` - Display audit logs with filters

**Verification:**
- Check logs are created for super admin POST/PUT/DELETE
- Verify non-super-admin actions are not logged
- Test audit log page displays correctly

---

## Phase 2: Performance Fixes

### 2.1 Refactor Dashboard Time Series Queries

**File:** `app/Http/Controllers/Admin/DashboardController.php`

**Current Problem:**
```php
// Lines ~170-450 - Executes 2,934 queries!
$signupsChart = $this->timeSeries(function ($date, $hour, $endDate = null) {
    return User::whereDate('created_at', $date)->count();
}, $now, $lifetimeStart);
```

**Solution - Replace `timeSeries()` method:**
```php
private function getTimeSeriesData(string $model, string $dateColumn, array $filters = []): array
{
    $now = now();
    $periods = [
        'today' => ['start' => $now->copy()->startOfDay(), 'interval' => 'hour', 'format' => 'H:00'],
        '7d' => ['start' => $now->copy()->subDays(7), 'interval' => 'day', 'format' => 'Y-m-d'],
        '30d' => ['start' => $now->copy()->subDays(30), 'interval' => 'day', 'format' => 'Y-m-d'],
        '90d' => ['start' => $now->copy()->subDays(90), 'interval' => 'day', 'format' => 'Y-m-d'],
        'lifetime' => ['start' => $now->copy()->subMonths(12), 'interval' => 'month', 'format' => 'Y-m'],
    ];

    $result = [];

    foreach ($periods as $key => $config) {
        $query = $model::query()
            ->where($dateColumn, '>=', $config['start'])
            ->where($filters);

        if ($config['interval'] === 'hour') {
            $data = $query->selectRaw('HOUR(' . $dateColumn . ') as period, COUNT(*) as count')
                ->groupBy('period')
                ->pluck('count', 'period');

            $result[$key] = collect(range(0, 23))->map(fn($hour) => [
                'label' => str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00',
                'value' => $data[$hour] ?? 0,
            ])->toArray();
        } else {
            $groupExpr = $config['interval'] === 'day'
                ? 'DATE(' . $dateColumn . ')'
                : 'DATE_FORMAT(' . $dateColumn . ', "%Y-%m")';

            $data = $query->selectRaw($groupExpr . ' as period, COUNT(*) as count')
                ->groupBy('period')
                ->pluck('count', 'period');

            // Fill in missing dates
            $period = \Carbon\CarbonPeriod::create(
                $config['start'],
                '1 ' . $config['interval'],
                $now
            );

            $result[$key] = collect($period)->map(function ($date) use ($data, $config) {
                $key = $date->format($config['format']);
                return [
                    'label' => $key,
                    'value' => $data[$key] ?? 0,
                ];
            })->toArray();
        }
    }

    return $result;
}
```

**Update Dashboard Controller:**
```php
public function index()
{
    // Card stats - use single queries with conditional aggregation
    $now = now();
    $today = $now->copy()->startOfDay();
    $monthStart = $now->copy()->startOfMonth();
    $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
    $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

    $userStats = User::selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as month,
        SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as last_month
    ', [$today, $monthStart, $lastMonthStart, $lastMonthEnd])->first();

    // Time series - now uses 1 query per chart instead of 163!
    $signupsChart = $this->getTimeSeriesData(User::class, 'created_at');
    $revenueChart = $this->getTimeSeriesData(Payment::class, 'created_at', ['status' => 'completed']);
    // ... other charts

    return Inertia::render('Admin/Dashboard', [
        'dashboardStats' => [...],
        'dashboardCharts' => [
            'signupsChart' => $signupsChart,
            'revenueChart' => $revenueChart,
            // ...
        ],
    ]);
}
```

**Expected Improvement:**
- Before: 3,000+ queries
- After: ~50 queries
- Load time: 5-10s → 0.5-1s

**Verification:**
- Compare dashboard data before/after (should be identical)
- Check query count in debug bar
- Test all time period filters

---

### 2.2 Add Dashboard Caching

**File:** `app/Http/Controllers/Admin/DashboardController.php`

**Changes:**
```php
use Illuminate\Support\Facades\Cache;

public function index()
{
    $period = request('period', '30d');
    $cacheKey = "admin.dashboard.{$period}";

    $data = Cache::remember($cacheKey, 300, function () use ($period) {
        // All dashboard queries here
        return [
            'dashboardStats' => $this->getStats(),
            'dashboardCharts' => $this->getCharts(),
            'dashboardTopLists' => $this->getTopLists(),
        ];
    });

    return Inertia::render('Admin/Dashboard', $data);
}

// Add cache invalidation when data changes
public function clearDashboardCache(): void
{
    Cache::tags(['admin.dashboard'])->flush();
}
```

**In Models/Services - Add cache clearing:**
```php
// In PaymentObserver, UserObserver, etc.
public function created($model)
{
    Cache::tags(['admin.dashboard'])->flush();
}
```

**Verification:**
- Check cache is created after first load
- Verify subsequent loads are faster
- Test cache invalidation on data changes

---

### 2.3 Fix MRR Calculation

**File:** `app/Http/Controllers/Admin/DashboardController.php`

**Current (Wrong):**
```php
$mrr = Payment::where('status', 'completed')
    ->where('type', 'subscription')
    ->where('created_at', '>=', $monthStart)
    ->sum('amount');
```

**Fixed:**
```php
$mrr = User::where('subscription_status', 'active')
    ->where('subscription_ends_at', '>', now())
    ->join('plans', 'users.plan_id', '=', 'plans.id')
    ->sum('plans.price');
```

**Verification:**
- Compare with Stripe dashboard MRR
- Test with users on different plans

---

### 2.4 Fix Active Subscription Check

**File:** `app/Http/Controllers/Admin/DashboardController.php`

**Current (Wrong):**
```php
$activeSubscriptions = User::where('subscription_status', 'active')->count();
```

**Fixed:**
```php
$activeSubscriptions = User::where('subscription_status', 'active')
    ->where('subscription_ends_at', '>', now())
    ->count();
```

**Verification:**
- Check count matches actual active subscriptions
- Test with expired subscriptions

---

### 2.5 Add Database Indexes

**Create Migration:**
```bash
php artisan make:migration add_dashboard_performance_indexes
```

```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->index('created_at');
        $table->index(['subscription_status', 'subscription_ends_at']);
    });

    Schema::table('payments', function (Blueprint $table) {
        $table->index(['created_at', 'status']);
        $table->index(['type', 'status', 'created_at']);
    });

    Schema::table('ai_usage_logs', function (Blueprint $table) {
        $table->index(['created_at', 'tool_slug']);
        $table->index(['created_at', 'model']);
    });

    Schema::table('admin_activity_logs', function (Blueprint $table) {
        $table->index('created_at');
    });
}
```

**Verification:**
- Run EXPLAIN on dashboard queries
- Check query execution times

---

## Phase 3: UI/UX Fixes

### 3.1 Add Loading States to Operations

**Files to Update:**

**`resources/js/Pages/Admin/Themes.vue`:**
```vue
<script setup>
const activating = ref<string | null>(null);

async function activate(slug: string) {
    activating.value = slug;
    try {
        await router.post(route('admin.themes.activate', { slug }));
        toast.success(t('Theme activated'));
    } catch (e) {
        toast.error(t('Failed to activate theme'));
    } finally {
        activating.value = null;
    }
}
</script>

<template>
    <button
        :disabled="activating === theme.slug"
        @click="activate(theme.slug)"
    >
        <span v-if="activating === theme.slug" class="spinner"></span>
        {{ activating === theme.slug ? t('Activating...') : t('Activate') }}
    </button>
</template>
```

**`resources/js/Pages/Admin/Addons.vue`:**
```vue
<script setup>
const processing = ref<Record<string, boolean>>({});

async function toggleAddon(slug: string, activate: boolean) {
    processing.value[slug] = true;
    try {
        await router.post(route(`admin.addons.${activate ? 'activate' : 'deactivate'}`, { slug }));
        toast.success(t(`Addon ${activate ? 'activated' : 'deactivated'}`));
    } catch (e) {
        toast.error(t('Operation failed'));
    } finally {
        processing.value[slug] = false;
    }
}
</script>
```

**`resources/js/Pages/Admin/Affiliate/Index.vue`:**
```vue
<script setup>
const processing = ref<Record<number, boolean>>({});

async function approveCommission(id: number) {
    processing.value[id] = true;
    try {
        await router.post(route('admin.affiliate.commission.approve', { id }));
        toast.success(t('Commission approved'));
    } catch (e) {
        toast.error(t('Failed to approve commission'));
    } finally {
        processing.value[id] = false;
    }
}
</script>
```

**`resources/js/Pages/Admin/Comments/Index.vue`:**
```vue
<script setup>
const processing = ref<Record<number, boolean>>({});

async function approveComment(id: number) {
    processing.value[id] = true;
    try {
        await router.post(route('admin.comments.approve', { id }));
        toast.success(t('Comment approved'));
    } catch (e) {
        toast.error(t('Failed to approve comment'));
    } finally {
        processing.value[id] = false;
    }
}
</script>
```

**Verification:**
- Test all operations show loading state
- Verify buttons are disabled during operation
- Check success/error toasts appear

---

### 3.2 Add Confirmation Dialogs for Destructive Actions

**Files to Update:**

**`resources/js/Pages/Admin/Themes.vue`:**
```vue
<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const showActivateConfirm = ref(false);
const themeToActivate = ref<string | null>(null);

function confirmActivate(slug: string) {
    themeToActivate.value = slug;
    showActivateConfirm.value = true;
}

async function activateConfirmed() {
    if (themeToActivate.value) {
        await activate(themeToActivate.value);
        showActivateConfirm.value = false;
    }
}
</script>

<template>
    <button @click="confirmActivate(theme.slug)">
        {{ t('Activate') }}
    </button>

    <ConfirmDialog
        v-model="showActivateConfirm"
        :title="t('Activate Theme')"
        :message="t('Are you sure you want to activate this theme? The current theme will be deactivated.')"
        @confirm="activateConfirmed"
    />
</template>
```

**`resources/js/Pages/Admin/Affiliate/Index.vue`:**
```vue
<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const showPayoutConfirm = ref(false);
const payoutToProcess = ref<number | null>(null);

function confirmPayout(id: number) {
    payoutToProcess.value = id;
    showPayoutConfirm.value = true;
}

async function processPayoutConfirmed() {
    if (payoutToProcess.value) {
        await processPayout(payoutToProcess.value);
        showPayoutConfirm.value = false;
    }
}
</script>

<template>
    <button @click="confirmPayout(payout.id)">
        {{ t('Process Payout') }}
    </button>

    <ConfirmDialog
        v-model="showPayoutConfirm"
        :title="t('Process Payout')"
        :message="t('Are you sure you want to process this payout? This action cannot be undone.')"
        @confirm="processPayoutConfirmed"
    />
</template>
```

**`resources/js/Pages/Admin/Comments/Index.vue`:**
```vue
<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const showSpamConfirm = ref(false);
const commentToMarkSpam = ref<number | null>(null);

function confirmMarkSpam(id: number) {
    commentToMarkSpam.value = id;
    showSpamConfirm.value = true;
}

async function markSpamConfirmed() {
    if (commentToMarkSpam.value) {
        await markSpam(commentToMarkSpam.value);
        showSpamConfirm.value = false;
    }
}
</script>

<template>
    <button @click="confirmMarkSpam(comment.id)">
        {{ t('Mark as Spam') }}
    </button>

    <ConfirmDialog
        v-model="showSpamConfirm"
        :title="t('Mark as Spam')"
        :message="t('Are you sure you want to mark this comment as spam?')"
        @confirm="markSpamConfirmed"
    />
</template>
```

**Verification:**
- Test all destructive actions show confirmation
- Verify actions only execute after confirmation
- Check cancel button works

---

### 3.3 Add Accessibility Attributes

**Files to Update:**

**`resources/js/Pages/Admin/Themes.vue`:**
```vue
<button
    :aria-label="t('Activate theme')"
    @click="activate(theme.slug)"
>
    {{ t('Activate') }}
</button>

<Link
    :aria-label="t('Theme settings')"
    :href="route('admin.themes.settings', { slug: theme.slug })"
>
    <i class="ti ti-settings"></i>
</Link>
```

**`resources/js/Pages/Admin/Addons.vue`:**
```vue
<button
    :aria-label="t('Addon settings')"
    @click="openSettings(addon.slug)"
>
    <i class="ti ti-settings"></i>
</button>

<button
    :aria-label="activate ? t('Deactivate addon') : t('Activate addon')"
    @click="toggleAddon(addon.slug, !addon.is_active)"
>
    {{ addon.is_active ? t('Deactivate') : t('Activate') }}
</button>

<button
    :aria-label="t('Delete addon')"
    @click="deleteAddon(addon.slug)"
>
    <i class="ti ti-trash"></i>
</button>
```

**`resources/js/Pages/Admin/Affiliate/Index.vue`:**
```vue
<button
    :aria-label="t('Approve commission')"
    @click="approveCommission(commission.id)"
>
    {{ t('Approve') }}
</button>

<button
    :aria-label="t('Process payout')"
    @click="processPayout(payout.id)"
>
    {{ t('Process') }}
</button>
```

**`resources/js/Pages/Admin/Comments/Index.vue`:**
```vue
<button
    :aria-label="t('Approve comment')"
    @click="approveComment(comment.id)"
>
    {{ t('Approve') }}
</button>

<button
    :aria-label="t('Mark comment as spam')"
    @click="markSpam(comment.id)"
>
    {{ t('Spam') }}
</button>
```

**Verification:**
- Run accessibility audit (Lighthouse)
- Test with screen reader
- Check all interactive elements have labels

---

### 3.4 Standardize Button Styles

**Create Design System:**
`resources/css/admin-buttons.css` (or add to existing admin CSS):
```css
/* Primary Action */
.btn-admin-primary {
    @apply inline-flex items-center gap-2 rounded-xl bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed;
}

/* Secondary Action */
.btn-admin-secondary {
    @apply inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 disabled:opacity-50 disabled:cursor-not-allowed;
}

/* Danger Action */
.btn-admin-danger {
    @apply inline-flex items-center gap-2 rounded-xl bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-500 transition-colors hover:bg-red-500/20 disabled:opacity-50 disabled:cursor-not-allowed;
}

/* Success Action */
.btn-admin-success {
    @apply inline-flex items-center gap-2 rounded-xl bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-500 transition-colors hover:bg-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed;
}
```

**Update Files to Use Consistent Classes:**

**`resources/js/Pages/Admin/Themes.vue`:**
```vue
<button class="btn-admin-primary">
    {{ t('Activate') }}
</button>

<Link class="btn-admin-secondary">
    {{ t('Settings') }}
</Link>
```

**`resources/js/Pages/Admin/Addons.vue`:**
```vue
<button class="btn-admin-primary">
    {{ t('Activate') }}
</button>

<button class="btn-admin-danger">
    {{ t('Deactivate') }}
</button>

<button class="btn-admin-danger">
    {{ t('Delete') }}
</button>

<Link class="btn-admin-secondary">
    {{ t('Settings') }}
</Link>
```

**Verification:**
- Visual review of all admin pages
- Check dark mode compatibility
- Test disabled states

---

## Phase 4: Addon Integration Improvements

### 4.1 Allow Settings Access for Inactive Addons

**File:** `resources/js/Pages/Admin/Addons.vue`

**Current:**
```vue
<Link
    v-if="addon.is_active && addon.settings?.length"
    :href="route('admin.addons.settings', { slug: addon.slug })"
>
```

**Fixed:**
```vue
<Link
    v-if="addon.settings?.length"
    :href="route('admin.addons.settings', { slug: addon.slug })"
    class="btn-admin-secondary"
    :aria-label="t('Addon settings')"
>
    <i class="ti ti-settings"></i>
    {{ t('Settings') }}
</Link>
```

**Verification:**
- Test settings accessible for inactive addons
- Verify settings can be saved
- Check addon works after activation with pre-configured settings

---

### 4.2 Add Addon Dashboard Widget

**File:** `app/Http/Controllers/Admin/DashboardController.php`

**Add to `index()` method:**
```php
$addonStats = [
    'total_addons' => count(glob(base_path('addons/*'), GLOB_ONLYDIR)),
    'active_addons' => count(array_filter(
        glob(base_path('addons/*/addon.json'), GLOB_BRACE),
        fn($file) => json_decode(file_get_contents($file))->is_active ?? false
    )),
    'recently_activated' => DB::table('admin_activity_logs')
        ->where('action', 'like', 'addon_activated%')
        ->where('created_at', '>=', now()->subDays(7))
        ->count(),
];

return Inertia::render('Admin/Dashboard', [
    // ... existing data
    'addonStats' => $addonStats,
]);
```

**File:** `resources/js/Pages/Admin/Dashboard.vue`

**Add widget:**
```vue
<div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/[0.03]">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
        {{ t('Addons') }}
    </h3>

    <div class="mt-4 grid grid-cols-3 gap-4">
        <div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                {{ addonStats.total_addons }}
            </div>
            <div class="text-sm text-gray-500">{{ t('Total') }}</div>
        </div>

        <div>
            <div class="text-2xl font-bold text-emerald-500">
                {{ addonStats.active_addons }}
            </div>
            <div class="text-sm text-gray-500">{{ t('Active') }}</div>
        </div>

        <div>
            <div class="text-2xl font-bold text-primary-500">
                {{ addonStats.recently_activated }}
            </div>
            <div class="text-sm text-gray-500">{{ t('This Week') }}</div>
        </div>
    </div>

    <Link
        :href="route('admin.addons')"
        class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary-500 hover:text-primary-600"
    >
        {{ t('Manage Addons') }}
        <i class="ti ti-arrow-right"></i>
    </Link>
</div>
```

**Verification:**
- Check widget displays on dashboard
- Verify counts are accurate
- Test link to addon manager

---

### 4.3 Add Addon Activity Logging

**File:** `app/Http/Controllers/Admin/ThemeAddonController.php`

**Add to `activateAddon()`:**
```php
public function activateAddon(string $slug)
{
    // ... existing activation logic

    // Log activity
    DB::table('admin_activity_logs')->insert([
        'admin_id' => auth('admin')->id(),
        'action' => 'addon_activated',
        'description' => "Activated addon: {$slug}",
        'metadata' => json_encode(['addon_slug' => $slug]),
        'created_at' => now(),
    ]);

    return redirect()->back()->with('success', t('Addon activated'));
}
```

**Add to `deactivateAddon()`:**
```php
public function deactivateAddon(string $slug)
{
    // ... existing deactivation logic

    DB::table('admin_activity_logs')->insert([
        'admin_id' => auth('admin')->id(),
        'action' => 'addon_deactivated',
        'description' => "Deactivated addon: {$slug}",
        'metadata' => json_encode(['addon_slug' => $slug]),
        'created_at' => now(),
    ]);

    return redirect()->back()->with('success', t('Addon deactivated'));
}
```

**Verification:**
- Activate/deactivate addon
- Check activity log entry created
- Verify entry appears in activity feed

---

## Phase 5: Missing Features

### 5.1 Expand Feature Settings

**File:** `app/Http/Controllers/Admin/FeatureSettingsController.php`

**Add more feature toggles:**
```php
public function edit()
{
    return Inertia::render('Admin/Settings/Features', [
        'features' => [
            // Existing
            'scroll_to_top_enabled' => (bool) settings('scroll_to_top_enabled', true),

            // New features
            'ai_chat_enabled' => (bool) settings('ai_chat_enabled', true),
            'ai_variations_enabled' => (bool) settings('ai_variations_enabled', true),
            'social_sharing_enabled' => (bool) settings('social_sharing_enabled', true),
            'document_editor_enabled' => (bool) settings('document_editor_enabled', true),
            'favorites_enabled' => (bool) settings('favorites_enabled', true),
            'reviews_enabled' => (bool) settings('reviews_enabled', true),
            'recently_used_tools_enabled' => (bool) settings('recently_used_tools_enabled', true),
            'estimated_generation_time_enabled' => (bool) settings('estimated_generation_time_enabled', true),
        ],
    ]);
}

public function update(Request $request)
{
    $validated = $request->validate([
        'features' => 'array',
        'features.*' => 'boolean',
    ]);

    foreach ($validated['features'] as $key => $value) {
        settings_set($key, $value);
    }

    return redirect()->back()->with('success', t('Feature settings updated'));
}
```

**File:** `resources/js/Pages/Admin/Settings/Features.vue`

**Update form to include new toggles:**
```vue
<div class="space-y-6">
    <FeatureToggle
        v-model="form.features.scroll_to_top_enabled"
        :label="t('Scroll to Top Button')"
        :description="t('Show a scroll to top button on all pages')"
    />

    <FeatureToggle
        v-model="form.features.ai_chat_enabled"
        :label="t('AI Chat Assistant')"
        :description="t('Enable the AI chat assistant feature')"
    />

    <FeatureToggle
        v-model="form.features.ai_variations_enabled"
        :label="t('AI Variations')"
        :description="t('Allow users to generate multiple variations of AI output')"
    />

    <!-- ... more toggles -->
</div>
```

**Verification:**
- Test all toggles save correctly
- Verify features are enabled/disabled based on settings
- Check settings page UI

---

### 5.2 Add Bulk Actions to Addon Manager

**File:** `resources/js/Pages/Admin/Addons.vue`

**Add bulk selection:**
```vue
<script setup>
const selectedAddons = ref<string[]>([]);
const processing = ref(false);

function toggleSelectAll() {
    if (selectedAddons.value.length === props.addons.length) {
        selectedAddons.value = [];
    } else {
        selectedAddons.value = props.addons.map((a: any) => a.slug);
    }
}

async function bulkActivate() {
    if (!selectedAddons.value.length) return;

    processing.value = true;
    try {
        await router.post(route('admin.addons.bulk-activate'), {
            slugs: selectedAddons.value,
        });
        toast.success(t('Addons activated'));
        selectedAddons.value = [];
    } catch (e) {
        toast.error(t('Failed to activate addons'));
    } finally {
        processing.value = false;
    }
}

async function bulkDeactivate() {
    if (!selectedAddons.value.length) return;

    processing.value = true;
    try {
        await router.post(route('admin.addons.bulk-deactivate'), {
            slugs: selectedAddons.value,
        });
        toast.success(t('Addons deactivated'));
        selectedAddons.value = [];
    } catch (e) {
        toast.error(t('Failed to deactivate addons'));
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div v-if="selectedAddons.length" class="mb-4 flex items-center gap-4">
        <span class="text-sm text-gray-600">
            {{ t('{count} selected', { count: selectedAddons.length }) }}
        </span>

        <button
            class="btn-admin-success"
            :disabled="processing"
            @click="bulkActivate"
        >
            {{ t('Activate Selected') }}
        </button>

        <button
            class="btn-admin-danger"
            :disabled="processing"
            @click="bulkDeactivate"
        >
            {{ t('Deactivate Selected') }}
        </button>
    </div>

    <table>
        <thead>
            <tr>
                <th>
                    <input
                        type="checkbox"
                        :checked="selectedAddons.length === addons.length"
                        @change="toggleSelectAll"
                    />
                </th>
                <!-- ... other columns -->
            </tr>
        </thead>

        <tbody>
            <tr v-for="addon in addons" :key="addon.slug">
                <td>
                    <input
                        type="checkbox"
                        :value="addon.slug"
                        v-model="selectedAddons"
                    />
                </td>
                <!-- ... other columns -->
            </tr>
        </tbody>
    </table>
</template>
```

**File:** `app/Http/Controllers/Admin/ThemeAddonController.php`

**Add bulk routes:**
```php
public function bulkActivate(Request $request)
{
    $validated = $request->validate([
        'slugs' => 'required|array',
        'slugs.*' => 'string',
    ]);

    foreach ($validated['slugs'] as $slug) {
        $this->activateAddon($slug);
    }

    return redirect()->back()->with('success', t('Addons activated'));
}

public function bulkDeactivate(Request $request)
{
    $validated = $request->validate([
        'slugs' => 'required|array',
        'slugs.*' => 'string',
    ]);

    foreach ($validated['slugs'] as $slug) {
        $this->deactivateAddon($slug);
    }

    return redirect()->back()->with('success', t('Addons deactivated'));
}
```

**File:** `routes/admin.php`

**Add routes:**
```php
Route::post('addons/bulk-activate', [ThemeAddonController::class, 'bulkActivate'])->name('admin.addons.bulk-activate');
Route::post('addons/bulk-deactivate', [ThemeAddonController::class, 'bulkDeactivate'])->name('admin.addons.bulk-deactivate');
```

**Verification:**
- Test select all/none
- Test bulk activate/deactivate
- Verify checkboxes work correctly

---

## Testing Checklist

### Security Tests
- [ ] Non-super-admin without permissions gets 403 on protected routes
- [ ] Super-admin audit logs created for POST/PUT/DELETE
- [ ] GDPR settings require permission
- [ ] All permission middleware working correctly

### Performance Tests
- [ ] Dashboard loads in < 2 seconds
- [ ] Query count < 100 per dashboard load
- [ ] Cache working (check Redis/Memcached)
- [ ] MRR calculation matches Stripe
- [ ] Active subscription count accurate

### UI/UX Tests
- [ ] All operations show loading states
- [ ] All destructive actions show confirmation
- [ ] All buttons have aria-labels
- [ ] Button styles consistent across pages
- [ ] Dark mode working on all pages

### Addon Tests
- [ ] Settings accessible for inactive addons
- [ ] Addon widget shows on dashboard
- [ ] Addon activity logged
- [ ] Bulk actions working
- [ ] Feature toggles working

### Integration Tests
- [ ] All routes resolve correctly
- [ ] All forms submit successfully
- [ ] All toasts appear correctly
- [ ] All redirects work
- [ ] All error messages display

---

## Deployment Notes

### Migration Order
1. Create `admin_audit_logs` table
2. Add database indexes
3. Run permission seeder
4. Deploy code changes

### Cache Clearing
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Monitoring
- Watch for slow queries after deployment
- Monitor cache hit rate
- Check audit log size (add cleanup job if needed)

---

## Future Enhancements (Not in Scope)

- Real-time dashboard updates via WebSocket
- Advanced chart types (pie, heatmap, funnel)
- Export all tables to CSV/Excel
- Addon marketplace integration
- Automated addon updates
- Admin dashboard customization (drag-and-drop widgets)
- Multi-language admin interface
- Admin notification center

---

## Success Metrics

- Dashboard load time: < 2s (from 5-10s)
- Query count: < 100 (from 3,000+)
- Security: 0 unauthorized access incidents
- Accessibility: 95+ Lighthouse score
- User satisfaction: Positive feedback on UX improvements

---

**End of Implementation Plan**
