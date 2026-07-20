<?php

namespace App\Services\Payment;

use App\Models\GatewaySubscription;
use App\Models\PaymentGateway;
use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Applies plan changes on the payment-gateway side for subscriptions driven by a
 * real recurring subscription (Stripe and PayPal).
 *
 * - Stripe: swap the subscription item's price. A downgrade uses
 *   proration_behavior=none (billed lower at the next renewal); an upgrade uses
 *   always_invoice (charges the prorated difference immediately).
 * - PayPal: revise the subscription to the target plan id. A downgrade applies at
 *   the next cycle; an upgrade (price increase) returns a PayPal approval URL the
 *   caller must redirect to, after which BILLING.SUBSCRIPTION.UPDATED syncs it.
 *
 * One-time-payment gateways never store a gateway_subscription_id and are skipped
 * here (the local applyScheduledChanges cron / cancel-to-Free path drives them).
 * Like the canceller, remote failures are logged but never thrown.
 */
class GatewaySubscriptionModifier
{
    /**
     * Whether this subscription can have its plan changed in place on the gateway.
     * True for Stripe (secret configured) and PayPal (enabled + credentials), both
     * with a gateway_subscription_id. Paddle and one-time gateways return false and
     * fall back to cancel-at-period-end on a downgrade.
     */
    public function supportsInPlace(GatewaySubscription $subscription): bool
    {
        if (! $subscription->gateway_subscription_id) {
            return false;
        }

        return match ($subscription->gateway) {
            'stripe' => (bool) config('cashier.secret'),
            'paypal' => $this->paypalGateway() !== null,
            default => false,
        };
    }

    /**
     * Whether a *downgrade* can be applied on the gateway deterministically, with no
     * buyer-approval step.
     *
     * Stripe swaps the subscription item's price outright. PayPal's revise can hand back
     * an approval link the buyer must accept, and until they do PayPal keeps billing the
     * OLD price — so scheduling a local downgrade off the back of it would promise a
     * lower price the gateway is never going to charge. PayPal downgrades therefore end
     * at period end (→ Free), the same as a one-time gateway.
     *
     * This is narrower than supportsInPlace(), which also covers upgrades — an upgrade
     * CAN handle PayPal's approval step, because the buyer is redirected to it.
     */
    public function supportsInPlaceDowngrade(GatewaySubscription $subscription): bool
    {
        return $subscription->gateway === 'stripe' && $this->supportsInPlace($subscription);
    }

    /**
     * Move the subscription to the target plan, effective at the next renewal (no
     * mid-cycle charge). Used for scheduled downgrades.
     *
     * Returns whether the gateway ACTUALLY applied the swap. The caller must not record
     * a scheduled downgrade when this is false, or local state would bill the customer
     * one price while the gateway charges another.
     */
    public function swapAtPeriodEnd(GatewaySubscription $subscription, Plan $target, string $billingCycle): bool
    {
        if ($subscription->gateway === 'paypal') {
            // '' means applied outright; a URL means the buyer must approve it first, so
            // it is NOT in effect yet and must not be treated as done.
            return $this->revisePayPal($subscription, $this->paypalPlanId($target, $billingCycle)) === '';
        }

        return $this->swapStripe($subscription, $this->stripePriceId($target, $billingCycle));
    }

    /**
     * Revert a previously-swapped subscription back to the given plan (used when a
     * scheduled downgrade is cancelled before it applies).
     */
    public function revertToPlan(GatewaySubscription $subscription, Plan $plan, string $billingCycle): void
    {
        if ($subscription->gateway === 'paypal') {
            $this->revisePayPal($subscription, $this->paypalPlanId($plan, $billingCycle));

            return;
        }

        $this->swapStripe($subscription, $this->stripePriceId($plan, $billingCycle));
    }

    /**
     * Upgrade a live subscription in place.
     *
     * Returns an array describing the outcome, or null when it could not be
     * performed (missing price/plan id or a remote failure) so the caller can
     * surface an error instead of half-applying:
     *   ['amount' => float, 'currency' => string, 'invoice_id' => ?string,
     *    'approval_url' => ?string]
     * For Stripe the difference is charged immediately (amount set, approval_url
     * null). For PayPal, approval_url is the URL the buyer must approve (the plan
     * syncs via webhook afterwards).
     */
    public function upgradeNow(GatewaySubscription $subscription, Plan $target, string $billingCycle): ?array
    {
        if ($subscription->gateway === 'paypal') {
            $url = $this->revisePayPal($subscription, $this->paypalPlanId($target, $billingCycle));

            if ($url === null) {
                return null;
            }

            return [
                'amount' => 0.0,
                'currency' => strtoupper((string) $subscription->currency),
                'invoice_id' => null,
                'approval_url' => $url !== '' ? $url : null,
            ];
        }

        return $this->upgradeStripe($subscription, $this->stripePriceId($target, $billingCycle));
    }

    // ─── Stripe ─────────────────────────────────

    private function upgradeStripe(GatewaySubscription $subscription, ?string $newPriceId): ?array
    {
        if ($subscription->gateway !== 'stripe' || ! $subscription->gateway_subscription_id || ! config('cashier.secret')) {
            return null;
        }

        if (! $newPriceId) {
            Log::warning('Stripe in-place upgrade skipped: target plan has no Stripe price id for this cycle.', [
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
            ]);

            return null;
        }

        try {
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));

            $remote = $stripe->subscriptions->retrieve($subscription->gateway_subscription_id, ['expand' => ['items']]);
            $itemId = $remote->items->data[0]->id ?? null;

            if (! $itemId) {
                return null;
            }

            $updated = $stripe->subscriptions->update($subscription->gateway_subscription_id, [
                'items' => [[
                    'id' => $itemId,
                    'price' => $newPriceId,
                ]],
                // Prorate AND immediately invoice the difference.
                'proration_behavior' => 'always_invoice',
                'expand' => ['latest_invoice'],
            ]);

            $invoice = $updated->latest_invoice ?? null;

            return [
                'amount' => is_object($invoice) ? (int) ($invoice->amount_paid ?? 0) / 100 : 0.0,
                'currency' => strtoupper(is_object($invoice) ? ($invoice->currency ?? $subscription->currency) : $subscription->currency),
                'invoice_id' => is_object($invoice) ? ($invoice->id ?? null) : (is_string($invoice) ? $invoice : null),
                'approval_url' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe in-place upgrade failed', [
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return bool whether Stripe accepted the price swap
     */
    private function swapStripe(GatewaySubscription $subscription, ?string $newPriceId): bool
    {
        if ($subscription->gateway !== 'stripe' || ! $subscription->gateway_subscription_id || ! config('cashier.secret')) {
            return false;
        }

        if (! $newPriceId) {
            Log::warning('Stripe plan swap skipped: target plan has no Stripe price id for this cycle.', [
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
            ]);

            return false;
        }

        try {
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));

            $remote = $stripe->subscriptions->retrieve($subscription->gateway_subscription_id, ['expand' => ['items']]);
            $itemId = $remote->items->data[0]->id ?? null;

            if (! $itemId) {
                Log::warning('Stripe plan swap skipped: no subscription item found.', [
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                ]);

                return false;
            }

            $stripe->subscriptions->update($subscription->gateway_subscription_id, [
                'items' => [[
                    'id' => $itemId,
                    'price' => $newPriceId,
                ]],
                // Defer to the next billing cycle — no mid-period proration.
                'proration_behavior' => 'none',
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Stripe subscription plan swap failed', [
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function stripePriceId(Plan $plan, string $billingCycle): ?string
    {
        return match ($billingCycle) {
            'monthly' => $plan->stripe_price_monthly_id,
            'yearly' => $plan->stripe_price_yearly_id,
            default => null, // lifetime is not a recurring price
        };
    }

    // ─── PayPal ─────────────────────────────────

    /**
     * Revise a PayPal subscription to a new plan id.
     *
     * Returns:
     *   - null  → could not revise (not a PayPal sub, missing plan id, or failure)
     *   - ''    → applied without buyer approval (downgrade / price decrease)
     *   - <url> → the buyer must approve at PayPal (upgrade / price increase)
     */
    private function revisePayPal(GatewaySubscription $subscription, ?string $newPlanId): ?string
    {
        if ($subscription->gateway !== 'paypal' || ! $subscription->gateway_subscription_id || ! $newPlanId) {
            if (! $newPlanId) {
                Log::warning('PayPal plan revise skipped: target plan has no PayPal plan id for this cycle.', [
                    'subscription_id' => $subscription->id,
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                ]);
            }

            return null;
        }

        $gateway = $this->paypalGateway();

        if (! $gateway) {
            return null;
        }

        $token = $this->payPalToken($gateway);

        if (! $token) {
            return null;
        }

        try {
            $response = Http::withToken($token)->acceptJson()
                ->post($this->payPalBaseUrl($gateway).'/v1/billing/subscriptions/'.$subscription->gateway_subscription_id.'/revise', [
                    'plan_id' => $newPlanId,
                ]);

            if ($response->failed()) {
                Log::error('PayPal subscription revise failed', [
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                    'error' => $response->json('message', $response->body()),
                ]);

                return null;
            }

            $approve = collect($response->json('links', []))->firstWhere('rel', 'approve');
            $approveUrl = is_array($approve) ? ($approve['href'] ?? '') : '';

            return $approveUrl ?: '';
        } catch (\Throwable $e) {
            Log::error('PayPal subscription revise errored', [
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function paypalPlanId(Plan $plan, string $billingCycle): ?string
    {
        return match ($billingCycle) {
            'monthly' => $plan->paypal_plan_monthly_id,
            'yearly' => $plan->paypal_plan_yearly_id,
            default => null,
        };
    }

    /**
     * The enabled PayPal gateway record, only if credentials are present.
     */
    private function paypalGateway(): ?PaymentGateway
    {
        $gateway = PaymentGateway::where('slug', 'paypal')->where('is_enabled', true)->first();

        if (! $gateway || ! $gateway->getCredential('client_id') || ! $gateway->getCredential('client_secret')) {
            return null;
        }

        return $gateway;
    }

    private function payPalToken(PaymentGateway $gateway): ?string
    {
        $response = Http::withBasicAuth($gateway->getCredential('client_id'), $gateway->getCredential('client_secret'))
            ->asForm()
            ->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }
}
