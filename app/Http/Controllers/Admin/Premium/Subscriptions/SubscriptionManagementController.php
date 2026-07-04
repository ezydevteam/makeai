<?php

namespace App\Http\Controllers\Admin\Premium\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\GatewaySubscriptionCanceller;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'status' => trim((string) $request->string('status')->value()),
            'gateway' => trim((string) $request->string('gateway')->value()),
            'plan' => trim((string) $request->string('plan')->value()),
            'search' => trim((string) $request->string('search')->value()),
        ];

        // Real subscription rows and "synthetic" rows (users holding a plan without
        // any billing record, e.g. legacy admin grants) are merged with a UNION so
        // pagination happens in the database instead of loading every row.
        $subscriptionQuery = \Illuminate\Support\Facades\DB::table('billing_subscriptions as bs')
            ->leftJoin('users as u', 'u.id', '=', 'bs.user_id')
            ->leftJoin('plans as p', 'p.id', '=', 'bs.plan_id')
            ->select([
                \Illuminate\Support\Facades\DB::raw('0 as is_synthetic'),
                'bs.id as id',
                'bs.user_id as user_id',
                'bs.plan_id as plan_id',
                'bs.billing_cycle as billing_cycle',
                'bs.status as status',
                'bs.gateway as gateway',
                'bs.gateway_subscription_id as gateway_subscription_id',
                'bs.amount as amount',
                'bs.currency as currency',
                'bs.trial_ends_at as trial_ends_at',
                'bs.current_period_start as current_period_start',
                'bs.current_period_end as current_period_end',
                'bs.cancelled_at as cancelled_at',
                'bs.created_at as created_at',
                'u.ulid as user_ulid',
                'u.name as user_name',
                'u.email as user_email',
                'p.name as plan_name',
            ])
            ->when($filters['status'] !== '', fn ($query) => $query->where('bs.status', $filters['status']))
            ->when($filters['plan'] !== '', fn ($query) => $query->where('bs.plan_id', (int) $filters['plan']))
            ->when($filters['gateway'] !== '', fn ($query) => $query->where('bs.gateway', $filters['gateway']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('bs.gateway_subscription_id', 'like', '%'.$filters['search'].'%')
                        ->orWhere('u.name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('u.email', 'like', '%'.$filters['search'].'%');
                });
            });

        $syntheticQuery = \Illuminate\Support\Facades\DB::table('users as u')
            ->leftJoin('plans as p', 'p.id', '=', 'u.plan_id')
            ->whereNotNull('u.plan_id')
            ->whereNotIn('u.id', function ($query) {
                $query->select('user_id')->from('billing_subscriptions')->whereNotNull('user_id');
            })
            ->where(function ($query) {
                $query
                    ->where(function ($planQuery) {
                        $planQuery->where('p.price_monthly', '>', 0)->orWhere('p.price_yearly', '>', 0);
                    })
                    ->orWhereNotNull('u.subscription_status');
            })
            ->select([
                \Illuminate\Support\Facades\DB::raw('1 as is_synthetic'),
                'u.id as id',
                'u.id as user_id',
                'u.plan_id as plan_id',
                \Illuminate\Support\Facades\DB::raw('NULL as billing_cycle'),
                \Illuminate\Support\Facades\DB::raw("COALESCE(u.subscription_status, 'active') as status"),
                \Illuminate\Support\Facades\DB::raw('NULL as gateway'),
                \Illuminate\Support\Facades\DB::raw('NULL as gateway_subscription_id'),
                \Illuminate\Support\Facades\DB::raw('NULL as amount'),
                \Illuminate\Support\Facades\DB::raw('NULL as currency'),
                'u.trial_ends_at as trial_ends_at',
                \Illuminate\Support\Facades\DB::raw('NULL as current_period_start'),
                'u.subscription_ends_at as current_period_end',
                \Illuminate\Support\Facades\DB::raw('NULL as cancelled_at'),
                'u.updated_at as created_at',
                'u.ulid as user_ulid',
                'u.name as user_name',
                'u.email as user_email',
                'p.name as plan_name',
            ])
            ->when($filters['status'] !== '', fn ($query) => $query->where('u.subscription_status', $filters['status']))
            ->when($filters['plan'] !== '', fn ($query) => $query->where('u.plan_id', (int) $filters['plan']))
            ->when($filters['gateway'] !== '', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('u.name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('u.email', 'like', '%'.$filters['search'].'%');
                });
            });

        $paginatedSubscriptions = \Illuminate\Support\Facades\DB::query()
            ->fromSub($subscriptionQuery->unionAll($syntheticQuery), 'subscription_rows')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $toIso = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->toISOString() : null;

        $paginatedSubscriptions->through(fn (object $row) => [
            'id' => $row->is_synthetic ? 'user-'.$row->id : $row->id,
            'user_id' => $row->user_id,
            'plan_id' => $row->plan_id,
            'billing_cycle' => $row->billing_cycle,
            'status' => $row->status,
            'gateway' => $row->gateway,
            'gateway_subscription_id' => $row->gateway_subscription_id,
            'amount' => $row->amount,
            'currency' => $row->currency,
            'trial_ends_at' => $toIso($row->trial_ends_at),
            'current_period_start' => $toIso($row->current_period_start),
            'current_period_end' => $toIso($row->current_period_end),
            'cancelled_at' => $toIso($row->cancelled_at),
            'created_at' => $toIso($row->created_at),
            'user' => $row->user_ulid ? [
                'ulid' => $row->user_ulid,
                'name' => $row->user_name,
                'email' => $row->user_email,
            ] : null,
            'plan' => $row->plan_id ? [
                'id' => $row->plan_id,
                'name' => $row->plan_name,
            ] : null,
        ]);

        $gateways = GatewaySubscription::query()
            ->select('gateway')
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway')
            ->map(fn (string $gateway) => [
                'value' => $gateway,
                'label' => str($gateway)->replace(['_', '-'], ' ')->title()->toString(),
            ])
            ->values();

        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        // 1. Total Subscriptions
        $totalSubscriptionsCurrent = GatewaySubscription::count();
        $totalSubscriptionsPrevious = GatewaySubscription::where('created_at', '<', $sevenDaysAgo)->count();

        // 2. Active Subscriptions
        $activeSubscriptionsCurrent = GatewaySubscription::where('status', 'active')->count();
        $activeSubscriptionsPrevious = GatewaySubscription::where('status', 'active')->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Trialing Subscriptions
        $trialingSubscriptionsCurrent = GatewaySubscription::where('status', 'trialing')->count();
        $trialingSubscriptionsPrevious = GatewaySubscription::where('status', 'trialing')->where('created_at', '<', $sevenDaysAgo)->count();

        // 4. Cancelled Subscriptions
        $cancelledSubscriptionsCurrent = GatewaySubscription::whereIn('status', ['canceled', 'cancelled'])->count();
        $cancelledSubscriptionsPrevious = GatewaySubscription::whereIn('status', ['canceled', 'cancelled'])->where('created_at', '<', $sevenDaysAgo)->count();

        $stats = [
            'total' => [
                'value' => $totalSubscriptionsCurrent,
                'comparison' => $this->calculateComparison($totalSubscriptionsCurrent, $totalSubscriptionsPrevious),
            ],
            'active' => [
                'value' => $activeSubscriptionsCurrent,
                'comparison' => $this->calculateComparison($activeSubscriptionsCurrent, $activeSubscriptionsPrevious),
            ],
            'trialing' => [
                'value' => $trialingSubscriptionsCurrent,
                'comparison' => $this->calculateComparison($trialingSubscriptionsCurrent, $trialingSubscriptionsPrevious),
            ],
            'cancelled' => [
                'value' => $cancelledSubscriptionsCurrent,
                'comparison' => $this->calculateComparison($cancelledSubscriptionsCurrent, $cancelledSubscriptionsPrevious),
            ],
        ];

        return Inertia::render('Admin/Premium/Subscriptions/Index', [
            'subscriptions' => [
                'data' => $paginatedSubscriptions->items(),
                'links' => $paginatedSubscriptions->linkCollection(),
                'from' => $paginatedSubscriptions->firstItem(),
                'to' => $paginatedSubscriptions->lastItem(),
                'total' => $paginatedSubscriptions->total(),
            ],
            'filters' => $filters,
            'plans' => Plan::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'gateways' => $gateways,
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
     * Manually grant a plan to a user (support cases, offline sales, giveaways).
     */
    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly,lifetime'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();
        $plan = Plan::findOrFail((int) $data['plan_id']);

        $periodEnd = match ($data['billing_cycle']) {
            'lifetime' => null,
            'yearly' => now()->addYear(),
            default => now()->addMonth(),
        };

        GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $data['billing_cycle'],
            'status' => GatewaySubscription::STATUS_ACTIVE,
            'gateway' => 'manual',
            'gateway_subscription_id' => null,
            'amount' => 0,
            'currency' => settings('currency_code', 'USD'),
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
        ]);

        $user->update([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => $periodEnd,
        ]);

        return back()->with('success', translate('Subscription granted successfully.'));
    }

    public function deactivate(
        Request $request,
        User $user,
        GatewaySubscriptionCanceller $canceller,
        SubscriptionLifecycleService $lifecycle,
    ): RedirectResponse {
        $mode = $request->input('mode') === 'period_end' ? 'period_end' : 'immediate';

        $subscription = GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing', 'past_due', 'cancelled', 'canceled'])
            ->latest('created_at')
            ->first();

        if ($subscription && $mode === 'period_end' && $subscription->isActive()) {
            $canceller->cancelAtPeriodEnd($subscription);
            $lifecycle->cancelAtPeriodEnd($subscription);

            return back()->with('success', translate('Subscription cancelled. Access remains until the current period ends.'));
        }

        if ($subscription) {
            $canceller->cancelImmediately($subscription);

            GatewaySubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing', 'past_due', 'cancelled', 'canceled'])
                ->update([
                    'status' => GatewaySubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'current_period_end' => now(),
                ]);
        } elseif ($user->plan_id) {
            // No subscription record exists (admin-granted plan) — create a cancelled
            // one so the change is visible in the subscription history.
            GatewaySubscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $user->plan_id,
                'billing_cycle' => 'monthly',
                'status' => GatewaySubscription::STATUS_CANCELLED,
                'gateway' => 'manual',
                'gateway_subscription_id' => null,
                'amount' => 0,
                'currency' => settings('currency_code', 'USD'),
                'trial_ends_at' => $user->trial_ends_at,
                'current_period_start' => null,
                'current_period_end' => now(),
                'cancelled_at' => now(),
            ]);
        }

        $user->update([
            'plan_id' => null,
            'subscription_status' => 'none',
            'subscription_ends_at' => now(),
            'trial_ends_at' => null,
        ]);

        return back()->with('success', translate('Subscription cancelled successfully.'));
    }
}
