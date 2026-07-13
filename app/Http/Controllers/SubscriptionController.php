<?php

namespace App\Http\Controllers;

use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\NotificationEventService;
use App\Services\Payment\GatewaySubscriptionCanceller;
use App\Services\Payment\GatewaySubscriptionModifier;
use App\Services\Subscription\SubscriptionLifecycleService;
use App\Services\Subscription\SubscriptionProrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function cancel(
        Request $request,
        GatewaySubscriptionCanceller $canceller,
        SubscriptionLifecycleService $lifecycle,
    ): RedirectResponse {
        $subscription = GatewaySubscription::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->latest()
            ->first();

        if (! $subscription) {
            return back()->with('error', translate('No active subscription found.'));
        }

        $canceller->cancelAtPeriodEnd($subscription);
        $lifecycle->cancelAtPeriodEnd($subscription);

        return back()->with('success', translate('Subscription cancelled. Access remains until the current period ends.'));
    }

    public function resume(
        Request $request,
        GatewaySubscriptionCanceller $canceller,
        SubscriptionLifecycleService $lifecycle,
    ): RedirectResponse {
        $subscription = GatewaySubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('status', GatewaySubscription::STATUS_CANCELLED)
            ->where(function ($query) {
                $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now());
            })
            ->latest('cancelled_at')
            ->first();

        if (! $subscription) {
            return back()->with('error', translate('No resumable subscription found.'));
        }

        if (! $canceller->resume($subscription)) {
            return back()->with('error', translate('This subscription cannot be resumed. Please subscribe again from the pricing page.'));
        }

        $lifecycle->resume($subscription);

        return back()->with('success', translate('Subscription resumed successfully.'));
    }

    /**
     * Schedule a downgrade to a lower plan, applied at the end of the current
     * period (no charge, no refund).
     */
    public function downgrade(
        Request $request,
        SubscriptionLifecycleService $lifecycle,
        SubscriptionProrationService $proration,
        GatewaySubscriptionCanceller $canceller,
        GatewaySubscriptionModifier $modifier,
    ): RedirectResponse {
        $data = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
        ]);

        $subscription = GatewaySubscription::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->with('plan')
            ->latest()
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return back()->with('error', translate('No active subscription found.'));
        }

        $target = Plan::active()->where('slug', $data['plan'])->firstOrFail();

        if ($proration->classifyChange($subscription->plan, $subscription->billing_cycle, $target, $data['billing']) !== SubscriptionProrationService::DOWNGRADE) {
            return back()->with('error', translate('Select a lower plan or shorter billing cycle to downgrade to.'));
        }

        // Only an in-place-capable recurring gateway (card on file + a plan-swap
        // API — Stripe today) can auto-bill a lower paid plan. Everything else
        // (free target, one-time gateway, or a recurring gateway we can't
        // plan-swap) ends at period end → Free.
        $currentPlanName = $subscription->plan->name;
        $isCycleChange = $target->id === $subscription->plan_id;
        $inPlace = ! $target->is_free && $modifier->supportsInPlace($subscription);
        $date = $subscription->current_period_end?->toFormattedDateString();

        if ($inPlace) {
            // Schedule locally + swap the gateway item to the lower price (billed
            // at the next renewal, no mid-cycle charge).
            $lifecycle->scheduleDowngrade($subscription, $target, $data['billing']);
            $modifier->swapAtPeriodEnd($subscription, $target, $data['billing']);

            if ($isCycleChange) {
                $message = $date
                    ? translate('Your billing will switch to :cycle at your next renewal on :date.', ['cycle' => translate($data['billing']), 'date' => $date])
                    : translate('Your billing will switch to :cycle at your next renewal.', ['cycle' => translate($data['billing'])]);
            } else {
                $message = $date
                    ? translate('Downgrade scheduled. You will move to the :plan plan on :date.', ['plan' => $target->name, 'date' => $date])
                    : translate('Downgrade to the :plan plan scheduled.', ['plan' => $target->name]);
            }
        } else {
            // End the plan at period end and cancel any remote recurring sub.
            $lifecycle->cancelAtPeriodEnd($subscription);
            $canceller->cancelAtPeriodEnd($subscription);

            if ($target->is_free) {
                $message = $date
                    ? translate('Your plan will move to Free on :date. Your current features stay active until then.', ['date' => $date])
                    : translate('Your plan will move to Free at the end of the current period.');
            } else {
                $message = $date
                    ? translate('Your :current plan stays active until :date, then your account moves to Free. You can purchase the :target plan any time.', ['current' => $currentPlanName, 'target' => $target->name, 'date' => $date])
                    : translate('Your plan ends at the current period, then your account moves to Free. You can purchase the :target plan any time.', ['target' => $target->name]);
            }
        }

        return back()->with('success', $message);
    }

    /**
     * Upgrade a live recurring (Stripe) subscription in place: swap the plan and
     * charge the prorated difference immediately, keeping the same renewal date.
     * One-time gateways don't reach here — their upgrades go through checkout.
     */
    public function upgrade(
        Request $request,
        SubscriptionProrationService $proration,
        GatewaySubscriptionModifier $modifier,
    ): RedirectResponse {
        $data = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
        ]);

        $subscription = GatewaySubscription::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->with('plan')
            ->latest()
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return back()->with('error', translate('No active subscription found.'));
        }

        // Lifetime is a one-time purchase (no recurring price to swap) — always
        // route it through checkout; the old recurring sub is cancelled on
        // activation.
        if ($data['billing'] === 'lifetime') {
            return redirect()->route('checkout.show', ['plan' => $data['plan'], 'billing' => 'lifetime']);
        }

        // In-place upgrade only applies to an in-place-capable recurring
        // subscription (Stripe/PayPal). Anything else goes through the normal
        // checkout.
        if (! $modifier->supportsInPlace($subscription)) {
            return redirect()->route('checkout.show', ['plan' => $data['plan'], 'billing' => $data['billing']]);
        }

        $target = Plan::active()->where('slug', $data['plan'])->firstOrFail();

        if ($proration->classifyChange($subscription->plan, $subscription->billing_cycle, $target, $data['billing']) !== SubscriptionProrationService::UPGRADE) {
            return back()->with('error', translate('Select a higher plan or longer billing cycle to upgrade to.'));
        }

        $isCycleChange = $target->id === $subscription->plan_id;

        $oldPlan = $subscription->plan;
        $charge = $modifier->upgradeNow($subscription, $target, $data['billing']);

        if ($charge === null) {
            return back()->with('error', translate('We could not process the upgrade with your current payment method. Please contact support.'));
        }

        // PayPal requires the buyer to approve a price increase — redirect them.
        // The plan syncs locally on the BILLING.SUBSCRIPTION.UPDATED webhook.
        if (! empty($charge['approval_url'])) {
            return redirect()->away($charge['approval_url']);
        }

        // The Stripe billing anchor is unchanged, so keep the existing period.
        // Clear any pending scheduled downgrade — upgrading supersedes it.
        $subscription->update([
            'plan_id' => $target->id,
            'billing_cycle' => $data['billing'],
            'scheduled_plan_id' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
        ]);

        $subscription->user()->update([
            'plan_id' => $target->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => $subscription->current_period_end,
        ]);

        // Record the prorated upgrade charge for the billing history. The Stripe
        // renewal webhook only handles subscription_cycle invoices, so this
        // proration invoice is logged here.
        if ($charge['amount'] > 0) {
            Payment::create([
                'user_id' => $subscription->user_id,
                'plan_id' => $target->id,
                'subscription_id' => $subscription->id,
                'gateway' => 'stripe',
                'gateway_payment_id' => $charge['invoice_id'] ?? ('upgrade-'.$subscription->id.'-'.now()->timestamp),
                'amount' => $charge['amount'],
                'currency' => $charge['currency'],
                'status' => 'completed',
                'type' => 'subscription',
                'metadata' => [
                    'billing_cycle' => $data['billing'],
                    'created_from' => 'in_place_upgrade',
                    'from_plan' => $oldPlan->slug,
                ],
            ]);
        }

        app(NotificationEventService::class)->planChanged($subscription->user, $oldPlan, $target, $subscription);

        $message = $isCycleChange
            ? translate('Your billing was switched to :cycle. You were charged the prorated difference.', ['cycle' => translate($data['billing'])])
            : translate('Upgraded to the :plan plan. You were charged the prorated difference.', ['plan' => $target->name]);

        return back()->with('success', $message);
    }

    public function cancelScheduledChange(
        Request $request,
        SubscriptionLifecycleService $lifecycle,
        GatewaySubscriptionModifier $modifier,
    ): RedirectResponse {
        $subscription = GatewaySubscription::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('scheduled_plan_id')
            ->with('plan')
            ->latest()
            ->first();

        if (! $subscription) {
            return back()->with('error', translate('No scheduled plan change to cancel.'));
        }

        $currentPlan = $subscription->plan;
        $cycle = $subscription->billing_cycle ?? 'monthly';

        $lifecycle->cancelScheduledDowngrade($subscription);

        // Undo the gateway-side price swap so the recurring subscription keeps
        // billing the current plan.
        if ($currentPlan) {
            $modifier->revertToPlan($subscription, $currentPlan, $cycle);
        }

        return back()->with('success', translate('Scheduled plan change cancelled.'));
    }
}
