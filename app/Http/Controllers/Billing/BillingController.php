<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Billing is only meaningful when purchasing is available, or the user has
        // history to view/manage (existing subscriber on a toggled-off install, or a
        // past payer). A premium-off user who never transacted has nothing here.
        if (! isProAvailable()
            && ! Payment::where('user_id', $user->id)->exists()
            && ! GatewaySubscription::where('user_id', $user->id)->exists()
        ) {
            return redirect()->route('user.dashboard');
        }

        $payments = Payment::where('user_id', $user->id)
            ->with('plan:id,name,slug')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'ulid' => $payment->ulid,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'type' => $payment->type,
                'gateway' => $payment->gateway,
                'plan_name' => $payment->plan?->name,
                'created_at' => $payment->created_at->toISOString(),
            ]);

        $activeSubscription = GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->with('scheduledPlan:id,name,slug')
            ->latest()
            ->first();

        $resumableSubscription = $activeSubscription ? null : GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->where('status', GatewaySubscription::STATUS_CANCELLED)
            ->where(function ($query) {
                $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now());
            })
            ->latest('cancelled_at')
            ->first();

        $canResume = $resumableSubscription
            && (! $resumableSubscription->gateway_subscription_id
                || in_array($resumableSubscription->gateway, ['stripe', 'paypal'], true));

        // Only a recurring subscription (a gateway subscription id on file) auto-
        // renews and can be cancelled. A one-time payment just runs to its period
        // end and stops — nothing to cancel.
        $isRecurring = (bool) $activeSubscription?->gateway_subscription_id;

        // A premium plan with no gateway subscription at all = admin-granted /
        // comped access. There's nothing for the user to cancel or resume; only an
        // admin can change it. Surface a note instead of missing actions.
        $isManagedExternally = (bool) ($user->plan
            && ! $user->plan->is_free
            && ! $activeSubscription
            && ! $resumableSubscription);

        // A paid, non-recurring, non-lifetime subscription: reassure the user they
        // won't be charged again.
        $isOneTime = (bool) ($activeSubscription
            && ! $isRecurring
            && $activeSubscription->billing_cycle !== 'lifetime');

        return Inertia::render('User/Billing', [
            'payments' => $payments,
            'plan' => $this->planData($user),
            'subscription' => [
                'status' => $user->subscription_status,
                'ends_at' => $user->subscription_ends_at?->toISOString(),
                'trial_ends_at' => $user->trial_ends_at?->toISOString(),
                'gateway' => $activeSubscription?->gateway ?? $resumableSubscription?->gateway,
                'billing_cycle' => $activeSubscription?->billing_cycle,
                'can_cancel' => (bool) ($activeSubscription && $isRecurring && $activeSubscription->billing_cycle !== 'lifetime'),
                'can_resume' => (bool) $canResume,
                'has_billing_portal' => $activeSubscription?->gateway === 'stripe' && (bool) config('cashier.secret'),
                'is_managed_externally' => $isManagedExternally,
                'is_one_time' => $isOneTime,
                'scheduled_plan_name' => $activeSubscription?->scheduledPlan?->name,
                'scheduled_change_at' => $activeSubscription?->scheduled_change_at?->toISOString(),
            ],
        ]);
    }

    private function planData(User $user): ?array
    {
        $plan = $user->plan;

        if (! $plan) {
            return null;
        }

        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'is_free' => (bool) $plan->is_free,
            'features' => $this->normalizePlanFeatures($plan->features),
            'subscription_status' => $user->subscription_status,
            'subscription_ends_at' => optional($user->subscription_ends_at)->toISOString(),
            'trial_ends_at' => optional($user->trial_ends_at)->toISOString(),
        ];
    }

    private function normalizePlanFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return array_values(array_filter(array_map(
                static fn (mixed $feature): string => trim((string) $feature),
                $features
            )));
        }

        if (is_string($features)) {
            $decoded = json_decode($features, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map(
                    static fn (mixed $feature): string => trim((string) $feature),
                    $decoded
                )));
            }

            return array_values(array_filter(array_map(
                static fn (string $feature): string => trim($feature),
                preg_split('/[\r\n,]+/', $features) ?: []
            )));
        }

        return [];
    }
}
