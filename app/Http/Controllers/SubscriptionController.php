<?php

namespace App\Http\Controllers;

use App\Models\GatewaySubscription;
use App\Services\Payment\GatewaySubscriptionCanceller;
use App\Services\Subscription\SubscriptionLifecycleService;
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
}
