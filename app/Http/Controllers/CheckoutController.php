<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankPaymentProofRequest;
use App\Http\Requests\CheckoutSessionRequest;
use App\Models\Coupon;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\NotificationEventService;
use App\Services\Payment\GatewaySubscriptionModifier;
use App\Services\Payment\PaymentActivationService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Pricing\PlanPriceResolver;
use App\Services\Subscription\SubscriptionLifecycleService;
use App\Services\Subscription\SubscriptionProrationService;
use App\Support\CountryCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Cashier's subscription "type" (its internal name for the subscription slot).
     * A single-subscription store only ever uses one.
     */
    private const STRIPE_SUBSCRIPTION_TYPE = 'default';

    public function show(Request $request, PlanPriceResolver $resolver, PaymentGatewayManager $gateways): Response
    {

        $validated = $request->validate([
            'plan' => ['required', 'string', 'max:120'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
        ]);

        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $plan = Plan::active()
            ->with('countryPrices')
            ->where('slug', $validated['plan'])
            ->firstOrFail();

        $pricing = $resolver->resolve($plan, $countryCode);
        $cycle = $pricing[$validated['billing']];
        abort_unless($this->cycleIsPurchasable($plan, $validated['billing'], $cycle), 404);

        $amount = (float) $cycle['amount'];
        $currency = $pricing['currency_code'];

        // Upgrade proration: show the unused-credit deduction and charge the net.
        // Only one-time gateways prorate through checkout — recurring (Stripe/PayPal)
        // upgrades swap in place and let the gateway charge the prorated difference.
        $currentSub = $this->activeSubscription($request->user());
        $prorationCredit = $this->upgradeProrationCredit($currentSub, $plan, $validated['billing'], (bool) $cycle['is_trial']);
        $netAmount = max(0, round($amount - $prorationCredit, 2));

        return Inertia::render('Checkout', [
            // Strip the site chrome. A checkout competes with every link you give it —
            // navigation, footer, newsletter — so this page keeps only the logo. Set as
            // page props rather than by dropping AppLayout, which would also take the
            // flash toasts and the GDPR banner with it.
            'hide_header' => true,
            'hide_footer' => true,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'credits' => $plan->credits,
                'features' => $plan->features ?: [],
            ],
            'billing' => $validated['billing'],
            'proration' => [
                'credit_amount' => $prorationCredit,
                'credit_formatted' => CountryCatalog::formatMoney($prorationCredit, $currency),
                'from_plan' => $prorationCredit > 0 ? $currentSub?->plan?->name : null,
                'net_amount' => $netAmount,
                'net_formatted' => CountryCatalog::formatMoney($netAmount, $currency),
            ],
            'pricing' => [
                'country_code' => $pricing['country_code'],
                'country_name' => $pricing['country_name'],
                'currency_code' => $currency,
                'display_currency_code' => $pricing['display_currency_code'],
                'is_localized' => $pricing['is_localized'],
                'source' => $pricing['source'],
                'cycle' => $cycle,
            ],
            // Fees are per-gateway AND depend on whether that gateway would bill this
            // purchase recurringly (a fixed gateway price carries no processing fee), so
            // each row is priced the way that gateway will actually charge it.
            'gateways' => $gateways->enabled()->map(function ($gateway) use ($gateways, $plan, $validated, $pricing, $netAmount, $prorationCredit, $currency) {
                $fee = $this->willBeRecurring($gateway, $plan, $validated['billing'], null, $pricing['source'], $prorationCredit)
                    ? 0.0
                    : $gateways->processingFee($gateway, $netAmount);
                $total = round($netAmount + $fee, 2);

                return [
                    'id' => $gateway->id,
                    'slug' => $gateway->slug,
                    'name' => translate($gateway->name),
                    'description' => $gateway->description ? translate($gateway->description) : null,
                    'is_test_mode' => $gateway->is_test_mode,
                    'processing_fee_type' => $gateway->processing_fee_type,
                    'processing_fee_value' => $gateway->processing_fee_value,
                    'fee_amount' => $fee,
                    'total_amount' => $total,
                    'fee_formatted' => CountryCatalog::formatMoney($fee, $currency),
                    'total_formatted' => CountryCatalog::formatMoney($total, $currency),
                ];
            })->values(),
        ]);
    }

    public function createSession(CheckoutSessionRequest $request, PlanPriceResolver $resolver, PaymentGatewayManager $gateways): RedirectResponse
    {

        $data = $request->validated();
        $gateway = PaymentGateway::query()
            ->where('slug', $data['gateway'])
            ->where('is_enabled', true)
            ->firstOrFail();

        $plan = Plan::active()
            ->with('countryPrices')
            ->where('slug', $data['plan'])
            ->firstOrFail();

        // Plan-change routing for a user who already holds a paid plan. These
        // redirects only apply to RECURRING subscriptions (a card on file that
        // auto-renews) — a one-time payer must be able to re-purchase the same
        // plan (renew) or buy another plan through normal checkout.
        $currentSub = $this->activeSubscription($request->user());
        $isRecurring = (bool) $currentSub?->gateway_subscription_id;
        $isUpgrade = false;
        if ($currentSub && $currentSub->plan && ! $currentSub->plan->is_free) {
            $change = app(SubscriptionProrationService::class)->classifyChange($currentSub->plan, $currentSub->billing_cycle, $plan, $data['billing']);

            if ($isRecurring && $change === SubscriptionProrationService::SAME) {
                return redirect()->route('user.dashboard.billing')
                    ->with('info', translate('You are already on this plan.'));
            }

            if ($isRecurring && $change === SubscriptionProrationService::DOWNGRADE) {
                return redirect()->route('user.dashboard.billing')
                    ->with('info', translate('To move to a lower plan, schedule a downgrade from your billing page.'));
            }

            // A recurring, in-place-capable upgrade must NOT go through checkout: the
            // gateway swaps the plan and charges the prorated difference itself. If it
            // fell through here it would create a second subscription at the FULL
            // recurring price, silently dropping the proration credit. Route it to the
            // billing page where the in-place upgrade action lives.
            if ($isRecurring && $change === SubscriptionProrationService::UPGRADE
                && app(GatewaySubscriptionModifier::class)->supportsInPlace($currentSub)) {
                return redirect()->route('user.dashboard.billing')
                    ->with('info', translate('You already have an active subscription. Upgrade from your billing page to keep your renewal date and pay only the prorated difference.'));
            }

            $isUpgrade = $change === SubscriptionProrationService::UPGRADE;
        }

        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $pricing = $resolver->resolve($plan, $countryCode);
        $cycle = $pricing[$data['billing']];

        if (! $this->cycleIsPurchasable($plan, $data['billing'], $cycle)) {
            return back()->with('error', translate('This plan is not available on the selected billing cycle.'));
        }

        $coupon = $this->validCoupon($data['coupon'] ?? null, $plan, $request->user());
        $cycle = $this->discountedCycle($cycle, $coupon);
        $amount = (float) $cycle['amount'];

        // Upgrade proration: credit the unused value of the current plan against
        // today's charge (never below zero). Recurring, in-place-capable upgrades
        // were already redirected above, so this only fires for one-time gateways.
        $prorationCredit = $isUpgrade
            ? $this->upgradeProrationCredit($currentSub, $plan, $data['billing'], (bool) $cycle['is_trial'])
            : 0.0;

        // A recurring subscription bills the FIXED price configured in Stripe/PayPal, so
        // it can no more carry a processing fee or a proration credit than it can carry a
        // coupon or a country price. Decide which kind of purchase this is BEFORE pricing
        // the charge, so the amount we record is the amount the gateway actually takes.
        // Otherwise the payment row (and billing_subscriptions.amount, which it seeds)
        // overstates the charge — and that inflated figure is what the NEXT upgrade's
        // proration credit is computed from.
        $isRecurring = $this->willBeRecurring($gateway, $plan, $data['billing'], $coupon, $pricing['source'], $prorationCredit);

        if ($isRecurring) {
            $fee = 0.0;
            $total = $amount;
        } else {
            if ($prorationCredit > 0) {
                $amount = max(0, round($amount - $prorationCredit, 2));
            }

            $fee = $gateways->processingFee($gateway, $amount);
            $total = $gateways->totalWithFee($gateway, $amount);
        }

        if ($cycle['is_trial'] && $amount <= 0) {
            if ($request->user()->has_trialed) {
                return back()->with('error', translate('You have already used your free trial. Please choose a paid plan.'));
            }

            $subscription = app(SubscriptionLifecycleService::class)->startTrial(
                $request->user(),
                $plan,
                $data['billing'],
                $gateway->slug,
                (int) ($cycle['trial_days'] ?: 30),
                $pricing['currency_code'],
            );

            Payment::create([
                'user_id' => $request->user()->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'gateway' => $gateway->slug,
                'gateway_payment_id' => 'trial-'.$subscription->id,
                'amount' => 0,
                'currency' => $pricing['currency_code'],
                'status' => 'completed',
                'type' => 'subscription',
                'metadata' => $this->paymentMetadata($data['billing'], $pricing, $cycle, $gateway, $fee, $total, $coupon),
            ]);

            return redirect()->route('user.dashboard')->with('success', translate('Trial activated successfully.'));
        }

        // A 100%-off coupon leaves nothing to charge — gateways reject zero-amount
        // sessions, so activate the subscription directly.
        if ($amount <= 0) {
            // Claim a global coupon slot before activating (prevents over-redemption of
            // a max_uses-limited free coupon under concurrency). Reserved here, counted
            // once — activation skips its own increment via 'coupon_global_reserved'.
            if ($coupon && ! $coupon->reserveGlobalUse()) {
                return back()->with('error', translate('This coupon has reached its usage limit.'));
            }

            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'plan_id' => $plan->id,
                'gateway' => $gateway->slug,
                'amount' => 0,
                'currency' => $pricing['currency_code'],
                'status' => 'pending',
                'type' => 'subscription',
                'metadata' => array_merge(
                    $this->paymentMetadata($data['billing'], $pricing, $cycle, $gateway, $fee, $total, $coupon, $prorationCredit),
                    $coupon ? ['coupon_global_reserved' => true] : [],
                ),
            ]);

            // Hard-block duplicate free activations: unlike a paid checkout, there
            // is no gateway charge to deter re-use, so we atomically claim the
            // per-user coupon slot BEFORE granting anything. If the user already
            // redeemed it (or a concurrent request won the race), abort.
            if ($coupon && ! app(PaymentActivationService::class)->claimCouponRedemption($coupon, $payment)) {
                $coupon->releaseGlobalUse();
                $payment->update(['status' => 'failed']);

                return back()->with('error', translate('You have already used this coupon.'));
            }

            app(PaymentActivationService::class)->activateFromPayment($payment, 'coupon-'.$payment->ulid);

            return redirect()->route('user.dashboard')->with('success', translate('Subscription activated successfully.'));
        }

        // Concurrency guard for per-user-limited coupons on PAID checkouts. The DB
        // redemption row is only written at activation, so two checkouts opened
        // before either completes would both pass validCoupon() and both get the
        // discount. Atomically reserve a per-user slot now (self-expiring), and
        // release it if the checkout fails. The zero-amount path above is already
        // guarded by the locking claimCouponRedemption().
        $couponReservationKey = null;
        if ($coupon && $coupon->per_user_limit !== null) {
            $couponReservationKey = $coupon->reserveForUser($request->user());
            if ($couponReservationKey === null) {
                return back()->with('error', translate('You have already used this coupon.'));
            }
        }

        // Claim a GLOBAL coupon slot before charging so a max_uses-limited coupon can't
        // be over-redeemed by concurrent checkouts. Released on gateway failure below /
        // on payment fail/reject; activation skips its own increment ('coupon_global_reserved').
        if ($coupon) {
            if (! $coupon->reserveGlobalUse()) {
                Coupon::releaseReservation($couponReservationKey);

                return back()->with('error', translate('This coupon has reached its usage limit.'));
            }
        }

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'gateway' => $gateway->slug,
            'amount' => $total,
            'currency' => $pricing['currency_code'],
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => array_merge(
                $this->paymentMetadata($data['billing'], $pricing, $cycle, $gateway, $fee, $total, $coupon, $prorationCredit),
                $couponReservationKey ? ['coupon_reservation_key' => $couponReservationKey] : [],
                $coupon ? ['coupon_global_reserved' => true] : [],
            ),
        ]);

        return $this->dispatchToGateway($request, $payment, $plan, $gateway, $data['billing']);
    }

    /**
     * Hand the prepared payment to its gateway's session builder.
     *
     * Wrapped so that a ConnectionException from ANY builder — timeout, DNS, refused
     * outbound connection — becomes a failed session with a readable message instead of a
     * 500 in the middle of checkout. `$response->failed()` does not cover a request that
     * never got a response, and each builder guarding itself is a rule that only holds
     * until someone adds the next gateway.
     */
    private function dispatchToGateway(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway, string $billing): RedirectResponse
    {
        try {
            if ($gateway->slug === 'stripe') {
                return $this->createStripeSession($request, $payment, $plan, $billing);
            }

            if ($gateway->slug === 'bank_transfer') {
                app(NotificationEventService::class)->transactionPending($payment);

                return redirect()->route('checkout.bank.show', $payment);
            }

            if ($gateway->slug === 'paypal') {
                return $this->createPayPalOrder($request, $payment, $gateway);
            }

            if ($gateway->slug === 'paddle') {
                return $this->createPaddlePayLink($request, $payment, $plan, $gateway);
            }

            if ($gateway->slug === 'razorpay') {
                return $this->createRazorpayPaymentLink($request, $payment, $plan, $gateway);
            }

            if ($gateway->slug === 'sslcommerz') {
                return $this->createSslCommerzSession($request, $payment, $plan, $gateway);
            }

            if ($gateway->slug === 'coingate') {
                return $this->createCoinGateOrder($request, $payment, $plan, $gateway);
            }

            if ($gateway->slug === 'paystack') {
                return $this->createPaystackTransaction($request, $payment, $gateway);
            }

            if ($gateway->slug === '2checkout') {
                return $this->createTwoCheckoutUrl($request, $payment, $plan, $gateway);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Gateway session could not be created (connection error)', [
                'gateway' => $gateway->slug,
                'payment_ulid' => $payment->ulid,
                'error' => $e->getMessage(),
            ]);

            return $this->failGatewaySession($payment, translate('Could not reach :gateway. Please try again in a moment.', ['gateway' => $gateway->name]));
        }

        app(NotificationEventService::class)->transactionPending($payment);

        return redirect()->route('checkout.pending', $payment)
            ->with('info', translate(':gateway payment session is pending gateway integration.', ['gateway' => $gateway->name]));
    }

    public function previewCoupon(Request $request, PlanPriceResolver $resolver, PaymentGatewayManager $gateways): JsonResponse
    {

        $data = $request->validate([
            'plan' => ['required', 'string', 'max:120', 'exists:plans,slug'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
            'coupon' => ['nullable', 'string', 'max:50'],
        ]);

        $plan = Plan::active()
            ->with('countryPrices')
            ->where('slug', $data['plan'])
            ->firstOrFail();
        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $pricing = $resolver->resolve($plan, $countryCode);
        $coupon = $this->validCoupon($data['coupon'] ?? null, $plan, $request->user());
        $cycle = $this->discountedCycle($pricing[$data['billing']], $coupon);
        $currency = $pricing['currency_code'];
        $planTotal = (float) $cycle['amount'];

        // Keep proration consistent with the charge computed in createSession —
        // one-time gateways only (recurring upgrades never reach checkout).
        $currentSub = $this->activeSubscription($request->user());
        $prorationCredit = $this->upgradeProrationCredit($currentSub, $plan, $data['billing'], (bool) $cycle['is_trial']);
        if ($prorationCredit > 0) {
            $planTotal = max(0, round($planTotal - $prorationCredit, 2));
        }

        return response()->json([
            'success' => true,
            'coupon' => $coupon ? [
                'code' => $coupon->code,
                'discount_formatted' => CountryCatalog::formatMoney((float) $cycle['discount_amount'], $currency),
            ] : null,
            'summary' => [
                'subtotal_formatted' => CountryCatalog::formatMoney((float) $cycle['subtotal_amount'], $currency),
                'discount_amount' => (float) $cycle['discount_amount'],
                'discount_formatted' => CountryCatalog::formatMoney((float) $cycle['discount_amount'], $currency),
                'vat_amount' => (float) $cycle['vat_amount'],
                'vat_formatted' => CountryCatalog::formatMoney((float) $cycle['vat_amount'], $currency),
                'proration_credit' => $prorationCredit,
                'proration_formatted' => CountryCatalog::formatMoney($prorationCredit, $currency),
                'plan_total_formatted' => CountryCatalog::formatMoney($planTotal, $currency),
            ],
            // Priced per gateway the same way createSession() will charge it: a purchase
            // that becomes a recurring subscription bills the fixed gateway price, with
            // no processing fee on top.
            'gateways' => $gateways->enabled()->mapWithKeys(function ($gateway) use ($gateways, $plan, $data, $pricing, $coupon, $prorationCredit, $planTotal, $currency) {
                $fee = $this->willBeRecurring($gateway, $plan, $data['billing'], $coupon, $pricing['source'], $prorationCredit)
                    ? 0.0
                    : $gateways->processingFee($gateway, $planTotal);
                $total = round($planTotal + $fee, 2);

                return [
                    $gateway->slug => [
                        'fee_amount' => $fee,
                        'fee_formatted' => CountryCatalog::formatMoney($fee, $currency),
                        'total_amount' => $total,
                        'total_formatted' => CountryCatalog::formatMoney($total, $currency),
                    ],
                ];
            }),
        ]);
    }

    public function bankInstructions(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth()->id() && $payment->gateway === 'bank_transfer', 404);

        $gateway = PaymentGateway::where('slug', 'bank_transfer')->where('is_enabled', true)->firstOrFail();

        return Inertia::render('Checkout/BankTransfer', [
            // Same reasoning as the checkout page: this is still a payment step, so it
            // keeps the logo and drops the rest of the site chrome.
            'hide_header' => true,
            'hide_footer' => true,
            'payment' => $this->paymentPayload($payment),
            'instructions' => $gateway->getCredential('instructions', settings('bank_transfer_instructions', '')),
        ]);
    }

    public function uploadBankProof(BankPaymentProofRequest $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->gateway === 'bank_transfer', 404);
        abort_unless($payment->status === 'pending', 422);

        // Store on the PRIVATE disk — payment proofs are financial documents and
        // must not be publicly reachable by URL. They are served to admins via an
        // authenticated route (admin.transactions.proof).
        $path = $request->file('proof')->store('payment-proofs/'.$payment->ulid, 'local');
        $metadata = $payment->metadata ?: [];
        $metadata['bank_transfer'] = [
            'proof_path' => $path,
            'proof_disk' => 'local',
            'original_name' => $request->file('proof')->getClientOriginalName(),
            'reference' => $request->validated('reference'),
            'note' => $request->validated('note'),
            'uploaded_at' => now()->toISOString(),
        ];

        $payment->update(['metadata' => $metadata]);

        app(NotificationEventService::class)->transactionPending($payment);

        return redirect()->route('checkout.pending', $payment)
            ->with('success', translate('Payment proof uploaded. Your transaction is pending review.'));
    }

    /**
     * How long to wait on a gateway lookup made while a buyer is watching.
     *
     * Laravel's default is 30s, which on these routes means the buyer stares at a blank
     * tab for half a minute before anything happens. These lookups are confirmations of a
     * charge the webhook also reports, so giving up early costs nothing.
     */
    private const GATEWAY_LOOKUP_TIMEOUT = 8;

    /**
     * Run a gateway lookup that must never take the page down with it.
     *
     * `$response->failed()` covers an HTTP error *response*; it does not cover a request
     * that never got one. A DNS failure, a refused connection or a timeout throws
     * ConnectionException instead, and an uncaught one turned a slow Paddle call into a
     * 500 on the return page — for a buyer whose money had already been taken. These
     * lookups are a convenience over the webhook, so a failure degrades to "still
     * pending" rather than to an error screen.
     *
     * @return \Illuminate\Http\Client\Response|null null when the request never completed
     */
    private function gatewayLookup(callable $request, string $context, array $logContext = []): ?\Illuminate\Http\Client\Response
    {
        try {
            $response = $request();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning($context.' (connection error)', [...$logContext, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning($context, [...$logContext, 'status' => $response->status()]);
        }

        return $response;
    }

    /**
     * Gateways whose settled state can be read back from an API using what the payment
     * row already stores. These are the ones the pending screen can usefully poll and the
     * ones whose return route can confirm a charge without waiting for the webhook.
     *
     * Not listed: Stripe and PayPal (settle before the buyer returns, and have their own
     * return handlers), bank transfer (no API at all), and 2Checkout — its buy-link flow
     * needs no API call at checkout, so we hold no credential that could query one back.
     */
    private const VERIFIABLE_GATEWAYS = ['paddle', 'paystack', 'coingate', 'sslcommerz'];

    private function supportsStatusPolling(Payment $payment): bool
    {
        if (! in_array($payment->gateway, self::VERIFIABLE_GATEWAYS, true) || ! $payment->gateway_payment_id) {
            return false;
        }

        $gateway = PaymentGateway::where('slug', $payment->gateway)->first();

        if (! $gateway) {
            return false;
        }

        return match ($payment->gateway) {
            'paddle' => (bool) $gateway->getCredential('api_key'),
            'paystack' => (bool) $gateway->getCredential('secret_key'),
            'coingate' => (bool) $gateway->getCredential('auth_token'),
            'sslcommerz' => (bool) $gateway->getCredential('store_id') && (bool) $gateway->getCredential('store_password'),
            default => false,
        };
    }

    /**
     * Re-check a still-pending payment directly with its gateway, and activate it if the
     * gateway says it is paid.
     *
     * The webhook remains the primary path; this is the fallback for when it is missing,
     * misconfigured or merely slow. Safe to call repeatedly — the activation services
     * row-lock and re-read status, so a webhook arriving mid-poll is not double-counted.
     *
     * Each per-gateway probe answers one question — "is this settled, and for how much?" —
     * and the amount guard and activation are shared, so a gateway added here cannot
     * accidentally skip the underpayment check.
     */
    private function confirmPendingPaymentWithGateway(Payment $payment, PaymentActivationService $activation): void
    {
        if ($payment->status !== 'pending' || ! $this->supportsStatusPolling($payment)) {
            return;
        }

        $gateway = PaymentGateway::where('slug', $payment->gateway)->first();

        $settled = match ($payment->gateway) {
            'paddle' => $this->probePaddle($gateway, $payment),
            'paystack' => $this->probePaystack($gateway, $payment),
            'coingate' => $this->probeCoinGate($gateway, $payment),
            'sslcommerz' => $this->probeSslCommerz($gateway, $payment),
            default => null,
        };

        if (! $settled) {
            return;
        }

        // Same rule as ProcessPaymentWebhookJob::paidAmountCovers() — overpayment is fine,
        // a 1-cent tolerance absorbs rounding, and a currency swap is always a rejection.
        $expected = (float) $payment->amount;

        if ($expected > 0.0) {
            $paid = (float) $settled['amount'];
            $paidCurrency = strtoupper((string) $settled['currency']);

            if ($paid <= 0.0 || $paidCurrency !== strtoupper((string) $payment->currency) || ($paid + 0.01) < $expected) {
                Log::warning('Gateway confirmation rejected: amount or currency mismatch', [
                    'gateway' => $payment->gateway,
                    'payment_ulid' => $payment->ulid,
                    'expected_amount' => $expected,
                    'expected_currency' => $payment->currency,
                    'paid_amount' => $paid,
                    'paid_currency' => $paidCurrency,
                ]);

                return;
            }
        }

        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $settled['payment_id']);
        } else {
            $activation->activateFromPayment($payment, $settled['payment_id'], $settled['subscription_id'] ?? null);
        }
    }

    /**
     * Minor units back to major, the inverse of minorAmount(). Paddle and Paystack both
     * report totals in minor units while `payments.amount` is major.
     */
    private function majorAmount(int $minor, string $currency): float
    {
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];

        return in_array(strtoupper($currency), $zeroDecimal, true) ? (float) $minor : $minor / 100;
    }

    /** @return array{payment_id:string,amount:float,currency:string,subscription_id:?string}|null */
    private function probePaddle(PaymentGateway $gateway, Payment $payment): ?array
    {
        $transactionId = (string) $payment->gateway_payment_id;

        $response = $this->gatewayLookup(
            fn () => Http::withToken((string) $gateway->getCredential('api_key'))->acceptJson()
                ->timeout(self::GATEWAY_LOOKUP_TIMEOUT)
                ->get($this->paddleBaseUrl($gateway).'/transactions/'.$transactionId),
            'Paddle transaction lookup failed',
            ['payment_ulid' => $payment->ulid, 'transaction_id' => $transactionId],
        );

        // `completed` is fully processed; `paid` is collected but still settling
        // internally. The money is taken in both, and a buyer watching this page should
        // not have to wait out Paddle's internal processing to get what they bought.
        if (! $response || $response->failed() || ! in_array($response->json('data.status'), ['paid', 'completed'], true)) {
            return null;
        }

        $currency = (string) $response->json('data.currency_code', $payment->currency);

        return [
            'payment_id' => $transactionId,
            'amount' => $this->majorAmount((int) $response->json('data.details.totals.total', 0), $currency),
            'currency' => $currency,
            'subscription_id' => $response->json('data.subscription_id') ? (string) $response->json('data.subscription_id') : null,
        ];
    }

    /** @return array{payment_id:string,amount:float,currency:string,subscription_id:?string}|null */
    private function probePaystack(PaymentGateway $gateway, Payment $payment): ?array
    {
        // Paystack keys the transaction on the reference we chose, which is the ulid.
        $response = $this->gatewayLookup(
            fn () => Http::withToken((string) $gateway->getCredential('secret_key'))->acceptJson()
                ->timeout(self::GATEWAY_LOOKUP_TIMEOUT)
                ->get('https://api.paystack.co/transaction/verify/'.urlencode($payment->ulid)),
            'Paystack transaction verify failed',
            ['payment_ulid' => $payment->ulid],
        );

        if (! $response || $response->failed() || ! $response->json('status') || $response->json('data.status') !== 'success') {
            return null;
        }

        $currency = (string) $response->json('data.currency', $payment->currency);

        return [
            'payment_id' => (string) $response->json('data.id', $payment->ulid),
            'amount' => $this->majorAmount((int) $response->json('data.amount', 0), $currency),
            'currency' => $currency,
            'subscription_id' => null,
        ];
    }

    /** @return array{payment_id:string,amount:float,currency:string,subscription_id:?string}|null */
    private function probeCoinGate(PaymentGateway $gateway, Payment $payment): ?array
    {
        $orderId = (string) $payment->gateway_payment_id;
        $baseUrl = $gateway->is_test_mode ? 'https://api-sandbox.coingate.com/v2' : 'https://api.coingate.com/v2';

        $response = $this->gatewayLookup(
            fn () => Http::withToken((string) $gateway->getCredential('auth_token'))->acceptJson()
                ->timeout(self::GATEWAY_LOOKUP_TIMEOUT)
                ->get($baseUrl.'/orders/'.$orderId),
            'CoinGate order lookup failed',
            ['payment_ulid' => $payment->ulid, 'order_id' => $orderId],
        );

        if (! $response || $response->failed() || $response->json('status') !== 'paid') {
            return null;
        }

        // price_* is the order priced in OUR currency; receive_* is what the merchant is
        // paid out in after conversion, which is not what we charged.
        return [
            'payment_id' => $orderId,
            'amount' => (float) $response->json('price_amount', 0),
            'currency' => (string) $response->json('price_currency', $payment->currency),
            'subscription_id' => null,
        ];
    }

    /** @return array{payment_id:string,amount:float,currency:string,subscription_id:?string}|null */
    private function probeSslCommerz(PaymentGateway $gateway, Payment $payment): ?array
    {
        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';

        // Queried by OUR transaction id rather than SSLCommerz's val_id: val_id only
        // exists in the IPN/return payload, and the whole point here is to work without
        // one having arrived.
        $response = $this->gatewayLookup(
            fn () => Http::acceptJson()->timeout(self::GATEWAY_LOOKUP_TIMEOUT)
                ->get($baseUrl.'/validator/api/merchantTransIDvalidationAPI.php', [
                    'tran_id' => $payment->ulid,
                    'store_id' => $gateway->getCredential('store_id'),
                    'store_passwd' => $gateway->getCredential('store_password'),
                    'format' => 'json',
                ]),
            'SSLCommerz transaction lookup failed',
            ['payment_ulid' => $payment->ulid],
        );

        if (! $response || $response->failed()) {
            return null;
        }

        // The API answers with a list; take the settled element for our ulid.
        $element = collect($response->json('element') ?: [])
            ->first(fn ($row) => in_array(data_get($row, 'status'), ['VALID', 'VALIDATED'], true)
                && (string) data_get($row, 'tran_id') === $payment->ulid);

        if (! $element) {
            return null;
        }

        // currency_amount/currency_type echo what we asked for; amount/currency may be
        // the post-conversion store currency on a multi-currency store.
        return [
            'payment_id' => (string) (data_get($element, 'bank_tran_id') ?: data_get($element, 'val_id') ?: ''),
            'amount' => (float) (data_get($element, 'currency_amount') ?: data_get($element, 'amount')),
            'currency' => (string) (data_get($element, 'currency_type') ?: data_get($element, 'currency') ?: $payment->currency),
            'subscription_id' => null,
        ];
    }

    /**
     * Confirm-on-return for gateways that hand the buyer back with nothing we can trust.
     *
     * SSLCommerz, Paystack and CoinGate all used to point their success URL straight at
     * the pending screen, which only renders the row's current status — so activation was
     * webhook-only and a webhook that never fired left a paying customer reading "waiting
     * for confirmation" forever. That was the Razorpay bug, three more times.
     *
     * Nothing in the request is trusted: the payment comes from the route binding and the
     * verdict from a server-to-server lookup. SSLCommerz in particular arrives as a POST
     * from their domain (hence the CSRF exemption), and none of that body is read.
     */
    public function gatewayReturn(Request $request, Payment $payment, PaymentActivationService $activation): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);
        abort_unless(in_array($payment->gateway, self::VERIFIABLE_GATEWAYS, true), 404);

        $this->confirmPendingPaymentWithGateway($payment, $activation);

        return redirect()->route('checkout.pending', $payment);
    }

    /**
     * Polling endpoint for the pending screen. Re-checks with the gateway, then reports
     * where the payment actually stands.
     */
    public function status(Request $request, Payment $payment, PaymentActivationService $activation): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $this->confirmPendingPaymentWithGateway($payment, $activation);

        $status = $payment->fresh()->status;

        return response()->json([
            'status' => $status,
            'settled' => $status !== 'pending',
        ]);
    }

    /**
     * Paddle's default payment link — the page Paddle turns a transaction into a checkout on.
     *
     * Unlike every other gateway here, Paddle does not host the checkout for us. Its
     * "default payment link" (dashboard → Checkout → Checkout settings) has to be a URL on
     * OUR domain running Paddle.js; Paddle builds `checkout.url` by appending `?_ptxn=<txn>`
     * to it, and Paddle.js opens the overlay for that transaction when the page loads.
     * Point the dashboard setting at this route.
     *
     * The client-side token is a public credential — it is designed to ship to the browser
     * and cannot be used for API calls. The secret API key never leaves the server.
     */
    public function paddlePay(Request $request): Response
    {
        $gateway = PaymentGateway::where('slug', 'paddle')->first();
        $clientToken = $gateway?->getCredential('client_token');

        abort_unless($gateway && $clientToken, 404);

        $transactionId = (string) $request->query('_ptxn', '');

        $payment = $transactionId !== ''
            ? Payment::where('user_id', $request->user()->id)
                ->where('gateway', 'paddle')
                ->where('gateway_payment_id', $transactionId)
                ->first()
            : null;

        return Inertia::render('Checkout/Paddle', [
            // Same stripped chrome as the rest of the payment flow.
            'hide_header' => true,
            'hide_footer' => true,
            'transactionId' => $transactionId,
            'clientToken' => $clientToken,
            'environment' => $gateway->is_test_mode ? 'sandbox' : 'production',
            // Where Paddle sends the buyer once they have paid. paddleReturn() confirms
            // the transaction server-side before showing them anything.
            'returnUrl' => route('checkout.paddle.return'),
            // If the overlay cannot open at all, the buyer still has somewhere to go that
            // tells them the truth about their payment.
            'fallbackUrl' => $payment
                ? route('checkout.pending', $payment)
                : route('user.dashboard.billing'),
        ]);
    }

    /**
     * Landing route for Paddle's hosted checkout.
     *
     * Paddle Billing's success URL is configured once in their dashboard and is the same
     * for every buyer, so it cannot carry a payment ulid the way other gateways' return
     * URLs do. Resolve from `_ptxn` when Paddle appends it, and otherwise fall back to
     * this buyer's own most recent pending Paddle payment — which is correct because the
     * route is authenticated and a buyer only has one checkout in flight at a time.
     */
    public function paddleReturn(Request $request, PaymentActivationService $activation): RedirectResponse
    {
        $transactionId = (string) $request->query('_ptxn', '');

        $payment = $transactionId !== ''
            ? Payment::where('user_id', $request->user()->id)->where('gateway', 'paddle')
                ->where('gateway_payment_id', $transactionId)->first()
            : null;

        $payment ??= Payment::where('user_id', $request->user()->id)
            ->where('gateway', 'paddle')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $payment) {
            return redirect()->route('user.dashboard.billing');
        }

        $this->confirmPendingPaymentWithGateway($payment, $activation);

        return redirect()->route('checkout.pending', $payment);
    }

    public function pending(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth()->id(), 404);

        // Every gateway lands here, PayPal included, whatever the outcome. The page is
        // status-aware, so a completed payment gets a confirmation rather than the
        // "waiting for confirmation" heading it used to contradict itself with — and a
        // buyer who has just parted with money gets a moment that says so, instead of a
        // flash message on a dashboard that may be gone before they look up.
        return Inertia::render('Checkout/Pending', [
            // Last screen of the payment flow, so it keeps the same stripped chrome as the
            // two before it rather than dropping the buyer back into the full site mid-flow.
            'hide_header' => true,
            'hide_footer' => true,
            'payment' => $this->paymentPayload($payment),
            // Set only while there is something worth asking about: a pending payment on a
            // gateway we can query. The page polls it as a fallback for a webhook that
            // never lands, and stops the moment the payment settles.
            'statusUrl' => $payment->status === 'pending' && $this->supportsStatusPolling($payment)
                ? route('checkout.status', $payment)
                : null,
            // Where a confirmed payment sends the buyer next. A subscriber wants the plan
            // they just bought; someone who topped up wants the credits they just bought.
            'continueUrl' => $payment->type === 'credit_topup'
                ? route('user.dashboard.usage.index')
                : route('user.dashboard.billing'),
            'continueLabel' => $payment->type === 'credit_topup'
                ? translate('Go to my usage')
                : translate('Go to billing'),
        ]);
    }

    public function paypalReturn(Request $request, Payment $payment, PaymentActivationService $activation): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->gateway === 'paypal', 404);

        // Already settled — usually the webhook beat the browser back. Still goes to the
        // confirmation screen: PayPal buyers get the same landing as every other gateway.
        if ($payment->status === 'completed') {
            return redirect()->route('checkout.pending', $payment);
        }

        $gateway = PaymentGateway::where('slug', 'paypal')->where('is_enabled', true)->firstOrFail();
        $token = $this->payPalAccessToken($gateway);

        if (! $token || ! $payment->gateway_payment_id) {
            return redirect()->route('checkout.pending', $payment)
                ->with('error', translate('PayPal payment could not be captured.'));
        }

        // Recurring subscription: confirm it is active, then activate locally. The
        // BILLING.SUBSCRIPTION.ACTIVATED webhook is the reliable server-side path;
        // this handles the common case where the buyer returns to the browser.
        if (data_get($payment->metadata, 'is_subscription')) {
            $subscriptionId = $payment->gateway_payment_id;
            $subscription = Http::withToken($token)->acceptJson()
                ->get($this->payPalBaseUrl($gateway).'/v1/billing/subscriptions/'.$subscriptionId);

            if ($subscription->successful() && in_array($subscription->json('status'), ['ACTIVE', 'APPROVED'], true)) {
                $activation->activateFromPayment($payment, $subscriptionId, $subscriptionId);

                return redirect()->route('checkout.pending', $payment)
                    ->with('success', translate('Subscription activated successfully.'));
            }

            return redirect()->route('checkout.pending', $payment)
                ->with('info', translate('Your PayPal subscription is being confirmed. This can take a moment.'));
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($this->payPalBaseUrl($gateway).'/v2/checkout/orders/'.$payment->gateway_payment_id.'/capture');

        if ($response->failed()) {
            return redirect()->route('checkout.pending', $payment)
                ->with('error', $response->json('message', translate('PayPal payment could not be captured.')));
        }

        $captureId = data_get($response->json(), 'purchase_units.0.payments.captures.0.id', $payment->gateway_payment_id);

        // Credit top-ups come back through THIS handler too — CreditTopupController points
        // its PayPal return_url at this same route — so activation has to branch on what
        // was actually bought. Sending a top-up through activateFromPayment() tried to open
        // a billing_subscriptions row with the payment's plan_id, which is null for a
        // top-up: "Column 'plan_id' cannot be null", a 500 shown to the buyer *after*
        // PayPal had already taken the money.
        //
        // ProcessPaymentWebhookJob::activatePayment() has always branched this way; this
        // path simply never did, so it only broke for buyers who returned to the browser
        // before the webhook landed.
        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $captureId);
        } else {
            $activation->activateFromPayment($payment, $captureId);
        }

        // Captured and activated. Lands on the same confirmation screen every other
        // gateway uses, which then forwards on by payment type.
        return redirect()->route('checkout.pending', $payment)
            ->with('success', translate('Payment confirmed successfully.'));
    }

    /**
     * Confirm a Razorpay payment link when the buyer returns to the browser.
     *
     * Razorpay used to send the buyer straight to checkout.pending, which only renders
     * whatever status the row already had. That made the `payment_link.paid` webhook the
     * SOLE path to activation, so any webhook that was never configured, never delivered,
     * or rejected for a signature mismatch left a paid buyer staring at "waiting for
     * confirmation" forever, with nothing in the UI to say so.
     *
     * The decision here is made from a server-to-server read of the payment link, never
     * from the query string: Razorpay appends razorpay_payment_id/_signature to the
     * callback, but those arrive via the buyer's browser, and a redirect a user can
     * replay is not something to grant a plan on. The API answer cannot be forged.
     *
     * Both this and the webhook can land; activateFromPayment()/activateCreditTopup()
     * row-lock and re-read status inside their transaction, so whichever arrives second
     * is a no-op rather than a double activation.
     */
    public function razorpayReturn(Request $request, Payment $payment, PaymentActivationService $activation): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->gateway === 'razorpay', 404);

        // Already settled — usually the webhook won the race. Straight to the confirmation.
        if ($payment->status === 'completed') {
            return redirect()->route('checkout.pending', $payment);
        }

        // Deliberately not filtered by is_enabled, matching ProcessPaymentWebhookJob: money
        // already taken must still activate even if the admin disabled the gateway after.
        $gateway = PaymentGateway::where('slug', 'razorpay')->first();
        $keyId = $gateway?->getCredential('key_id');
        $keySecret = $gateway?->getCredential('key_secret');
        $paymentLinkId = (string) $payment->gateway_payment_id;

        if (! $keyId || ! $keySecret || $paymentLinkId === '') {
            return redirect()->route('checkout.pending', $payment)
                ->with('info', translate('Your payment is being confirmed. This can take a moment.'));
        }

        $response = $this->gatewayLookup(
            fn () => Http::withBasicAuth($keyId, $keySecret)->acceptJson()
                ->timeout(self::GATEWAY_LOOKUP_TIMEOUT)
                ->get('https://api.razorpay.com/v1/payment_links/'.$paymentLinkId),
            'Razorpay return: payment link lookup failed',
            ['payment_ulid' => $payment->ulid, 'payment_link_id' => $paymentLinkId],
        );

        // No response at all, or an error one — the webhook is still the authoritative
        // path, so send the buyer to a page that tells them so rather than to a 500.
        if (! $response || $response->failed()) {
            return redirect()->route('checkout.pending', $payment)
                ->with('info', translate('Your payment is being confirmed. This can take a moment.'));
        }

        // "paid" is the only status that means settled. A link can also come back created,
        // partially_paid, cancelled or expired — none of which have earned a plan.
        if ($response->json('status') !== 'paid') {
            return redirect()->route('checkout.pending', $payment);
        }

        // A signature proves authenticity but does not bind the amount, and Razorpay links
        // accept partial payment. Compare in minor units against what we meant to charge,
        // the same shape as ProcessPaymentWebhookJob::paidAmountCovers().
        $expectedMinor = $this->minorAmount((float) $payment->amount, $payment->currency);
        $paidMinor = (int) $response->json('amount_paid', 0);
        $paidCurrency = strtoupper((string) $response->json('currency', ''));

        if ($expectedMinor > 0 && ($paidMinor < $expectedMinor || $paidCurrency !== strtoupper((string) $payment->currency))) {
            Log::warning('Razorpay return rejected: amount or currency mismatch', [
                'payment_ulid' => $payment->ulid,
                'expected_minor' => $expectedMinor,
                'paid_minor' => $paidMinor,
                'expected_currency' => $payment->currency,
                'paid_currency' => $paidCurrency,
            ]);

            return redirect()->route('checkout.pending', $payment)
                ->with('error', translate('The amount received did not match this order. Please contact support.'));
        }

        // The real pay_… id lives in the link's payments array; the link id is only a
        // container. Falls back to the callback's value, then the link id, so the record
        // always carries something traceable.
        $gatewayPaymentId = (string) (
            collect($response->json('payments') ?: [])
                ->firstWhere('status', 'captured')['payment_id']
            ?? $response->json('payments.0.payment_id')
            ?? $request->query('razorpay_payment_id')
            ?? $paymentLinkId
        );

        // Top-ups come through this same handler (CreditTopupController points its Razorpay
        // callback here too), and they have no plan_id — sending one through
        // activateFromPayment() would fail on a null plan_id after the money was taken.
        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $gatewayPaymentId);
        } else {
            $activation->activateFromPayment($payment, $gatewayPaymentId);
        }

        return redirect()->route('checkout.pending', $payment)
            ->with('success', translate('Payment confirmed successfully.'));
    }

    /**
     * Whether the plan is actually sold on this billing cycle.
     *
     * This gates the zero-amount activation path in createSession(). A cycle the
     * admin never priced (typically `price_lifetime`, which is nullable) resolves to
     * an amount of 0, so without this check any user could check that cycle out and
     * be granted the plan for free — permanently, since a lifetime period never
     * expires. It also enforces the storefront's cycle toggles server-side; they are
     * otherwise display-only and trivially bypassed by posting the cycle directly.
     *
     * `list_amount` is the cycle's configured price BEFORE trial zeroing, so a
     * legitimately free checkout (free trial, 100%-off coupon) still passes.
     */
    private function cycleIsPurchasable(Plan $plan, string $billing, array $cycle): bool
    {
        if ($plan->is_free) {
            return false;
        }

        if (! settings('pricing_show_'.$billing, true)) {
            return false;
        }

        return (float) ($cycle['list_amount'] ?? 0) > 0;
    }

    /**
     * The user's current active (or trialing) paid subscription, if any — used
     * to route/prorate plan changes.
     */
    private function activeSubscription(?User $user): ?GatewaySubscription
    {
        if (! $user) {
            return null;
        }

        return GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->with('plan')
            ->latest()
            ->first();
    }

    private function paymentMetadata(string $billing, array $pricing, array $cycle, PaymentGateway $gateway, float $fee, float $total, ?Coupon $coupon = null, float $prorationCredit = 0.0): array
    {
        return [
            'billing_cycle' => $billing,
            'pricing_country' => $pricing['country_code'],
            'pricing_source' => $pricing['source'],
            'discount_amount' => $cycle['discount_amount'] ?? 0,
            'proration_credit' => round($prorationCredit, 2),
            // Flat key read by activateFromPayment (usage counting) and by
            // renewFromGatewaySubscription (recurring discounts).
            'coupon_code' => $coupon?->code,
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ] : null,
            'subtotal_amount' => $cycle['subtotal_amount'],
            'vat_amount' => $cycle['vat_amount'],
            'vat_percentage' => $cycle['vat_percentage'],
            'plan_total_amount' => $cycle['amount'],
            'processing_fee_amount' => $fee,
            'processing_fee_type' => $gateway->processing_fee_type,
            'processing_fee_value' => $gateway->processing_fee_value,
            'total_amount' => $total,
            'is_test_mode' => $gateway->is_test_mode,
            'created_from' => 'checkout_session',
        ];
    }

    private function paymentPayload(Payment $payment): array
    {
        $payment->loadMissing('plan');
        $metadata = $payment->metadata ?: [];
        $currency = $payment->currency;

        return [
            'ulid' => $payment->ulid,
            'status' => $payment->status,
            'gateway' => $payment->gateway,
            'amount' => (float) $payment->amount,
            'currency' => $currency,
            'formatted_amount' => CountryCatalog::formatMoney((float) $payment->amount, $currency),
            'subtotal_amount' => (float) ($metadata['subtotal_amount'] ?? 0),
            'subtotal_formatted' => CountryCatalog::formatMoney((float) ($metadata['subtotal_amount'] ?? 0), $currency),
            'discount_amount' => (float) ($metadata['discount_amount'] ?? 0),
            'discount_formatted' => CountryCatalog::formatMoney((float) ($metadata['discount_amount'] ?? 0), $currency),
            'vat_amount' => (float) ($metadata['vat_amount'] ?? 0),
            'vat_percentage' => (float) ($metadata['vat_percentage'] ?? 0),
            'vat_formatted' => CountryCatalog::formatMoney((float) ($metadata['vat_amount'] ?? 0), $currency),
            'plan_total_amount' => (float) ($metadata['plan_total_amount'] ?? $payment->amount),
            'plan_total_formatted' => CountryCatalog::formatMoney((float) ($metadata['plan_total_amount'] ?? $payment->amount), $currency),
            'processing_fee_amount' => (float) ($metadata['processing_fee_amount'] ?? 0),
            'processing_fee_formatted' => CountryCatalog::formatMoney((float) ($metadata['processing_fee_amount'] ?? 0), $currency),
            'plan' => ['name' => $payment->plan?->name, 'slug' => $payment->plan?->slug],
            'billing_cycle' => $metadata['billing_cycle'] ?? null,
            'proof_uploaded' => filled(data_get($metadata, 'bank_transfer.proof_path')),
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }

    private function createPayPalOrder(Request $request, Payment $payment, PaymentGateway $gateway): RedirectResponse
    {
        $token = $this->payPalAccessToken($gateway);
        if (! $token) { $this->releaseCouponReservation($payment); $payment->update(['status' => 'failed']); return back()->with('error', translate('PayPal is not configured.')); }

        // Recurring: when the plan has a PayPal plan id for this cycle (and no
        // coupon / country-price override forces a one-time charge), create a
        // real PayPal subscription instead of a one-time order.
        if ($paypalPlanId = $this->paypalRecurringPlanId($payment)) {
            return $this->createPayPalSubscription($request, $payment, $gateway, $token, $paypalPlanId);
        }

        $baseUrl = $this->payPalBaseUrl($gateway);
        $response = Http::withToken($token)->acceptJson()->post($baseUrl.'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payment->ulid,
                'custom_id' => $payment->ulid,
                'amount' => ['currency_code' => strtoupper($payment->currency), 'value' => number_format((float) $payment->amount, 2, '.', '')],
            ]],
            'application_context' => [
                'return_url' => $this->absoluteUrl($request, '/checkout/paypal/return/'.$payment->ulid),
                'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$payment->plan?->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
                'user_action' => 'PAY_NOW',
            ],
        ]);

        if ($response->failed()) {
            $payment->update(['status' => 'failed', 'metadata' => [...($payment->metadata ?: []), 'gateway_error' => $response->json('message', 'PayPal order failed.')]]);
            return back()->with('error', $response->json('message', translate('PayPal order failed.')));
        }

        $approveLink = collect($response->json('links', []))->firstWhere('rel', 'approve');
        $approveUrl = is_array($approveLink) ? ($approveLink['href'] ?? null) : null;
        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'gateway_order_id' => $response->json('id')]]);

        if (! $approveUrl) { return redirect()->route('checkout.pending', $payment)->with('warning', translate('PayPal order was created, but approval URL was not returned.')); }
        return redirect()->away($approveUrl);
    }

    /**
     * Whether a payment qualifies for a recurring gateway subscription. A fixed-price
     * recurring plan can't honour a coupon, a country-specific price, an upgrade
     * proration credit or a processing fee, and lifetime is one-time — those all fall
     * back to a one-time charge.
     *
     * This reads the decision back off the stored payment. willBeRecurring() below makes
     * the same decision from the raw inputs at checkout time; the two MUST agree, or the
     * charge is priced as one thing and taken as another.
     */
    private function isRecurringEligible(Payment $payment): bool
    {
        $meta = $payment->metadata ?: [];
        $cycle = $meta['billing_cycle'] ?? 'monthly';

        return in_array($cycle, ['monthly', 'yearly'], true)
            && empty($meta['coupon_code'])
            && ($meta['pricing_source'] ?? 'default') === 'default'
            && (float) ($meta['proration_credit'] ?? 0) <= 0;
    }

    /**
     * Whether this checkout will become a real recurring gateway subscription — the same
     * rule as isRecurringEligible(), but from the raw inputs, so createSession() can
     * price the charge before the payment row exists.
     */
    private function willBeRecurring(PaymentGateway $gateway, Plan $plan, string $billing, ?Coupon $coupon, string $pricingSource, float $prorationCredit): bool
    {
        if (! in_array($billing, ['monthly', 'yearly'], true)) {
            return false;
        }

        if ($coupon || $pricingSource !== 'default' || $prorationCredit > 0) {
            return false;
        }

        return filled($this->gatewayRecurringPlanId($gateway->slug, $plan, $billing));
    }

    /**
     * The gateway-side recurring price/plan id for a plan + cycle, or null when the
     * gateway doesn't do recurring billing or the admin hasn't configured one (in which
     * case the plan still sells — as a one-time charge).
     */
    private function gatewayRecurringPlanId(string $gatewaySlug, ?Plan $plan, string $billing): ?string
    {
        if (! $plan) {
            return null;
        }

        return match ($gatewaySlug) {
            'stripe' => $billing === 'yearly' ? $plan->stripe_price_yearly_id : $plan->stripe_price_monthly_id,
            'paypal' => $billing === 'yearly' ? $plan->paypal_plan_yearly_id : $plan->paypal_plan_monthly_id,
            default => null,
        };
    }

    /**
     * The PayPal plan id to subscribe this payment to, or null for a one-time order.
     */
    private function paypalRecurringPlanId(Payment $payment): ?string
    {
        if (! $this->isRecurringEligible($payment)) {
            return null;
        }

        return $this->gatewayRecurringPlanId('paypal', $payment->plan, $payment->metadata['billing_cycle'] ?? 'monthly');
    }

    /**
     * The Stripe recurring price id for this payment, or null for a one-time charge.
     */
    private function stripeRecurringPriceId(Payment $payment): ?string
    {
        if (! $this->isRecurringEligible($payment)) {
            return null;
        }

        return $this->gatewayRecurringPlanId('stripe', $payment->plan, $payment->metadata['billing_cycle'] ?? 'monthly');
    }

    /**
     * Create a Stripe Checkout session (Cashier). A recurring price when the plan
     * has one and no coupon/country override applies; otherwise a one-time charge
     * for the payment total (which already includes any processing fee/coupon).
     */
    private function createStripeSession(Request $request, Payment $payment, Plan $plan, string $billing): RedirectResponse
    {
        if (! config('cashier.secret')) {
            $this->releaseCouponReservation($payment);
            $payment->update(['status' => 'failed']);

            return back()->with('error', translate('Stripe is not configured.'));
        }

        $user = $request->user();
        $options = [
            // The same confirmation screen every other gateway returns to. Stripe used to
            // drop the buyer on the dashboard with a ?checkout=success nobody read — no
            // confirmation, no payment ID, and a different ending to the flow depending on
            // which gateway they happened to pick.
            'success_url' => route('checkout.pending', $payment),
            'cancel_url' => route('checkout.show', ['plan' => $plan->slug, 'billing' => $billing]),
            'metadata' => [
                'plan_slug' => $plan->slug,
                'billing_cycle' => $billing,
                'payment_id' => $payment->ulid,
            ],
        ];

        $priceId = $this->stripeRecurringPriceId($payment);

        if ($priceId) {
            // A recurring price must be checked out in SUBSCRIPTION mode. Cashier's
            // checkout() always creates the session in `payment` mode, which Stripe
            // rejects for a recurring price — newSubscription()->checkout() is the one
            // that sets mode=subscription.
            $checkout = $user->newSubscription(self::STRIPE_SUBSCRIPTION_TYPE, $priceId)->checkout($options);

            return $checkout->redirect();
        }

        // Stamp our reference onto the PaymentIntent so it lands on the resulting Charge.
        // A refund webhook only ever carries the charge, so without this there is nothing
        // to trace `charge.refunded` back to this payment.
        $options['payment_intent_data'] = ['metadata' => ['payment_id' => $payment->ulid]];

        return $user->checkout([$this->stripeOneOffLineItem($payment, $plan->name)], $options)->redirect();
    }

    /**
     * An inline Stripe line item for a one-off charge of the payment's exact amount
     * and currency.
     *
     * Cashier maps a STRING array key to a Stripe *price id*, so the intuitive
     * `[$currency => $minorAmount]` shape silently sends `price: "usd"` and Stripe
     * fails with "No such price". An ad-hoc amount has to be an inline price_data
     * item. We build it here rather than using Cashier's checkoutCharge() because that
     * helper hard-codes the currency to config('cashier.currency'), which is wrong for
     * a country-specific price denominated in another currency.
     */
    private function stripeOneOffLineItem(Payment $payment, string $productName): array
    {
        $currency = strtolower($payment->currency ?: (string) (config('cashier.currency') ?: 'usd'));

        return [
            'price_data' => [
                'currency' => $currency,
                'product_data' => ['name' => $productName],
                'unit_amount' => $this->minorAmount((float) $payment->amount, $currency),
            ],
            'quantity' => 1,
        ];
    }

    private function createPayPalSubscription(Request $request, Payment $payment, PaymentGateway $gateway, string $token, string $planId): RedirectResponse
    {
        $response = Http::withToken($token)->acceptJson()->post($this->payPalBaseUrl($gateway).'/v1/billing/subscriptions', [
            'plan_id' => $planId,
            'custom_id' => $payment->ulid,
            'subscriber' => ['email_address' => $request->user()->email],
            'application_context' => [
                'brand_name' => settings('app_name', config('app.name')),
                'return_url' => $this->absoluteUrl($request, '/checkout/paypal/return/'.$payment->ulid),
                'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$payment->plan?->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
                'user_action' => 'SUBSCRIBE_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ]);

        if ($response->failed()) {
            $payment->update(['status' => 'failed', 'metadata' => [...($payment->metadata ?: []), 'gateway_error' => $response->json('message', 'PayPal subscription failed.')]]);

            return back()->with('error', $response->json('message', translate('PayPal subscription could not be created.')));
        }

        $subscriptionId = (string) $response->json('id');
        $approveLink = collect($response->json('links', []))->firstWhere('rel', 'approve');
        $approveUrl = is_array($approveLink) ? ($approveLink['href'] ?? null) : null;

        $payment->update([
            'gateway_payment_id' => $subscriptionId,
            'metadata' => [...($payment->metadata ?: []), 'paypal_subscription_id' => $subscriptionId, 'is_subscription' => true],
        ]);

        if (! $approveUrl) {
            return redirect()->route('checkout.pending', $payment)->with('warning', translate('PayPal subscription was created, but the approval URL was not returned.'));
        }

        return redirect()->away($approveUrl);
    }

    private function payPalAccessToken(PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');
        if (! $clientId || ! $clientSecret) { return null; }
        $response = Http::withBasicAuth($clientId, $clientSecret)->asForm()->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    /**
     * Paddle base URL for the current mode. Billing only — Classic has its own hosts.
     */
    private function paddleBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
    }

    /**
     * Open a Paddle checkout.
     *
     * Create a transaction server-side, then send the buyer to the checkout URL Paddle
     * hands back. The amount is an inline non-catalog price, so plans do not have to be
     * mirrored into Paddle's catalogue, and `custom_data` carries our ulid through to the
     * webhook.
     */
    private function createPaddlePayLink(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $apiKey = (string) $gateway->getCredential('api_key');

        if ($apiKey === '') {
            return $this->failGatewaySession($payment, translate('Paddle is not configured.'));
        }

        // A longer budget than a lookup: this one creates the transaction, and there is
        // nothing else that will do it if this request is abandoned.
        $response = $this->gatewayLookup(fn () => Http::withToken($apiKey)->acceptJson()->timeout(20)->post($this->paddleBaseUrl($gateway).'/transactions', [
            'items' => [[
                'quantity' => 1,
                'price' => [
                    'description' => $plan->name,
                    // Minor units as a STRING — Paddle rejects an integer here.
                    'unit_price' => [
                        'amount' => (string) $this->minorAmount((float) $payment->amount, $payment->currency),
                        'currency_code' => strtoupper($payment->currency),
                    ],
                    'product' => ['name' => $plan->name, 'tax_category' => 'standard'],
                ],
            ]],
            'custom_data' => ['payment_ulid' => $payment->ulid],
            'collection_mode' => 'automatic',
        ]), 'Paddle transaction could not be created', ['payment_ulid' => $payment->ulid]);

        // Never reached Paddle at all — a timeout, DNS or a blocked outbound connection.
        if (! $response) {
            return $this->failGatewaySession($payment, translate('Could not reach Paddle. Please try again in a moment.'));
        }

        if ($response->failed()) {
            // Paddle nests its message under error.detail, not error.message.
            $error = $response->json('error.detail')
                ?? $response->json('error.message')
                ?? translate('Paddle checkout could not be started.');

            return $this->failGatewaySession($payment, (string) $error);
        }

        $transactionId = (string) $response->json('data.id');
        $checkoutUrl = (string) $response->json('data.checkout.url');

        // Paddle only fills checkout.url from the seller's DEFAULT PAYMENT LINK (Paddle
        // dashboard → Checkout → Checkout settings). Without it the transaction is created
        // and then there is nowhere to send the buyer — so say exactly that rather than
        // redirecting to an empty string.
        if ($checkoutUrl === '') {
            Log::warning('Paddle returned no checkout URL — default payment link is probably unset', [
                'payment_ulid' => $payment->ulid,
                'transaction_id' => $transactionId,
            ]);

            return $this->failGatewaySession($payment, translate('Paddle has no default payment link configured. Set one in the Paddle dashboard under Checkout → Checkout settings.'));
        }

        $payment->update([
            'gateway_payment_id' => $transactionId,
            'metadata' => [...($payment->metadata ?: []), 'gateway_pay_link' => $checkoutUrl],
        ]);

        return redirect()->away($checkoutUrl);
    }

    private function createRazorpayPaymentLink(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $keyId = $gateway->getCredential('key_id');
        $keySecret = $gateway->getCredential('key_secret');
        if (! $keyId || ! $keySecret) { return $this->failGatewaySession($payment, 'Razorpay is not configured.'); }

        $response = Http::withBasicAuth($keyId, $keySecret)->acceptJson()->post('https://api.razorpay.com/v1/payment_links', [
            'amount' => $this->minorAmount((float) $payment->amount, $payment->currency), 'currency' => strtoupper($payment->currency),
            'description' => $plan->name, 'reference_id' => $payment->ulid,
            // Returns through razorpayReturn(), which confirms the charge with Razorpay
            // server-side. Landing on checkout.pending directly left activation entirely
            // dependent on the webhook.
            'callback_url' => $this->absoluteUrl($request, '/checkout/razorpay/return/'.$payment->ulid), 'callback_method' => 'get',
            'customer' => ['name' => $request->user()->name, 'email' => $request->user()->email],
            'notes' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($response->failed()) { return $this->failGatewaySession($payment, $response->json('error.description', 'Razorpay payment link failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'gateway_payment_link' => $response->json('short_url')]]);
        return redirect()->away($response->json('short_url'));
    }

    private function createSslCommerzSession(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $storeId = $gateway->getCredential('store_id');
        $storePassword = $gateway->getCredential('store_password');
        if (! $storeId || ! $storePassword) { return $this->failGatewaySession($payment, translate('SSLCommerz is not configured.')); }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
        $response = $this->gatewayLookup(fn () => Http::asForm()->timeout(20)->post($baseUrl.'/gwprocess/v4/api.php', [
            'store_id' => $storeId, 'store_passwd' => $storePassword, 'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper($payment->currency), 'tran_id' => $payment->ulid,
            // Returns through gatewayReturn(), which confirms against SSLCommerz's own API.
            // Note SSLCommerz POSTs the buyer back here, so that route accepts POST.
            'success_url' => $this->absoluteUrl($request, '/checkout/sslcommerz/return/'.$payment->ulid),
            'fail_url' => $this->absoluteUrl($request, '/checkout/sslcommerz/return/'.$payment->ulid),
            'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$plan->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
            'ipn_url' => $this->absoluteUrl($request, '/webhooks/sslcommerz'),
            'cus_name' => $request->user()->name, 'cus_email' => $request->user()->email,
            'cus_add1' => 'N/A', 'cus_city' => 'N/A', 'cus_country' => 'N/A', 'cus_phone' => 'N/A',
            'shipping_method' => 'NO', 'product_name' => $plan->name, 'product_category' => 'Subscription', 'product_profile' => 'non-physical-goods',
        ]), 'SSLCommerz session could not be created', ['payment_ulid' => $payment->ulid]);

        if (! $response) { return $this->failGatewaySession($payment, translate('Could not reach SSLCommerz. Please try again in a moment.')); }
        if ($response->failed() || ! $response->json('GatewayPageURL')) { return $this->failGatewaySession($payment, $response->json('failedreason', 'SSLCommerz session failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('sessionkey'), 'metadata' => [...($payment->metadata ?: []), 'gateway_session_id' => $response->json('sessionkey')]]);
        return redirect()->away($response->json('GatewayPageURL'));
    }

    private function createCoinGateOrder(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $token = $gateway->getCredential('auth_token');
        if (! $token) { return $this->failGatewaySession($payment, 'CoinGate is not configured.'); }

        $baseUrl = $gateway->is_test_mode ? 'https://api-sandbox.coingate.com/v2' : 'https://api.coingate.com/v2';
        $webhookToken = bin2hex(random_bytes(16));
        $response = $this->gatewayLookup(fn () => Http::withToken($token)->acceptJson()->asForm()->timeout(20)->post($baseUrl.'/orders', [
            'order_id' => $payment->ulid, 'price_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'price_currency' => strtoupper($payment->currency), 'receive_currency' => strtoupper($payment->currency),
            'title' => $plan->name, 'callback_url' => $this->absoluteUrl($request, '/webhooks/coingate?token='.$webhookToken),
            'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$plan->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
            // Confirms against CoinGate's order API on return rather than trusting the redirect.
            'success_url' => $this->absoluteUrl($request, '/checkout/coingate/return/'.$payment->ulid),
        ]), 'CoinGate order could not be created', ['payment_ulid' => $payment->ulid]);

        if (! $response) { return $this->failGatewaySession($payment, translate('Could not reach CoinGate. Please try again in a moment.')); }
        if ($response->failed()) { return $this->failGatewaySession($payment, $response->json('message', 'CoinGate order failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'coingate_webhook_token' => $webhookToken]]);
        return redirect()->away($response->json('payment_url'));
    }

    private function createPaystackTransaction(Request $request, Payment $payment, PaymentGateway $gateway): RedirectResponse
    {
        $secretKey = $gateway->getCredential('secret_key');
        if (! $secretKey) { return $this->failGatewaySession($payment, 'Paystack is not configured.'); }

        $response = $this->gatewayLookup(fn () => Http::withToken($secretKey)->acceptJson()->timeout(20)->post('https://api.paystack.co/transaction/initialize', [
            'email' => $request->user()->email, 'amount' => $this->minorAmount((float) $payment->amount, $payment->currency),
            'currency' => strtoupper($payment->currency), 'reference' => $payment->ulid,
            // Confirms via Paystack's verify endpoint on return rather than trusting the redirect.
            'callback_url' => $this->absoluteUrl($request, '/checkout/paystack/return/'.$payment->ulid),
            'metadata' => ['payment_ulid' => $payment->ulid],
        ]), 'Paystack transaction could not be initialised', ['payment_ulid' => $payment->ulid]);

        if (! $response) { return $this->failGatewaySession($payment, translate('Could not reach Paystack. Please try again in a moment.')); }
        if ($response->failed() || ! $response->json('status')) { return $this->failGatewaySession($payment, $response->json('message', 'Paystack transaction failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('data.reference'), 'metadata' => [...($payment->metadata ?: []), 'gateway_access_code' => $response->json('data.access_code')]]);
        return redirect()->away($response->json('data.authorization_url'));
    }

    /**
     * The string 2Checkout signs for a ConvertPlus buy link.
     *
     * Parameters sorted by name, each value serialised as its BYTE length followed by the
     * raw (un-encoded) value, concatenated with no separator. 2Checkout's own worked
     * example — currency=USD, expiration=1893456000, price=10, prod=Software, qty=1,
     * type=digital — must produce `3USD1018934560002108Software117digital`, which is what
     * the test asserts.
     */
    private function twoCheckoutSignaturePayload(array $params): string
    {
        ksort($params);

        return collect($params)
            // strlen(), not mb_strlen(): the prefix counts UTF-8 bytes, so a plan name
            // with an accent or a currency symbol must not be counted as characters.
            ->map(fn ($value) => strlen((string) $value).(string) $value)
            ->implode('');
    }

    private function twoCheckoutSignature(array $params, string $secretWord): string
    {
        return hash_hmac('sha256', $this->twoCheckoutSignaturePayload($params), $secretWord);
    }

    /**
     * 2Checkout (Verifone) ConvertPlus buy link.
     *
     * Three things this used to get wrong, all of which end with 2Checkout bouncing the
     * buyer to its documentation site instead of a payment form:
     *
     *  - **No signature.** Dynamic (ad-hoc price) buy links MUST be signed, or the link is
     *    rejected outright — otherwise anyone could edit `price` in the URL.
     *  - **No `type`.** It is required for dynamic products.
     *  - **A sandbox hostname that does not exist.** Both environments are served from
     *    secure.2checkout.com; test mode is the `test=1` parameter, not a different host.
     *    `test` itself is explicitly NOT part of the signature.
     *  - **Legacy parameter names.** `merchant_order_id` and `x_receipt_link_url` belong to
     *    the old buy-link format, not ConvertPlus.
     */
    private function createTwoCheckoutUrl(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $merchantCode = $gateway->getCredential('merchant_code');
        $secretWord = $gateway->getCredential('secret_key');

        if (! $merchantCode || ! $secretWord) {
            return $this->failGatewaySession($payment, translate('2Checkout is not configured.'));
        }

        // Everything 2Checkout requires in the signature: the general set (return-url,
        // return-type, order-ext-ref) plus the dynamic-product set. Raw values — the
        // signature is computed before URL encoding.
        //
        // `order-ext-ref` is the ConvertPlus name for our own reference; the legacy
        // `merchant_order_id` this used to send is not a ConvertPlus parameter. It comes
        // back as `vendor_order_id` on the INS notification, which is what
        // ProcessPaymentWebhookJob::processTwoCheckout() matches the payment on — so
        // getting this name wrong also meant a paid order could never be matched to a user.
        $returnUrl = $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid);

        $signed = [
            'currency' => strtoupper($payment->currency),
            'order-ext-ref' => $payment->ulid,
            'price' => number_format((float) $payment->amount, 2, '.', ''),
            'prod' => $plan->name,
            'qty' => '1',
            'return-type' => 'redirect',
            'return-url' => $returnUrl,
            'type' => 'digital',
        ];

        $query = [
            ...$signed,
            'merchant' => $merchantCode,
            'dynamic' => '1',
            'signature' => $this->twoCheckoutSignature($signed, $secretWord),
        ];

        // Test orders run on the same host, flagged rather than routed elsewhere.
        if ($gateway->is_test_mode) {
            $query['test'] = '1';
        }

        $payment->update(['gateway_payment_id' => $payment->ulid]);

        return redirect()->away('https://secure.2checkout.com/checkout/buy/?'.http_build_query($query));
    }

    private function failGatewaySession(Payment $payment, string $message): RedirectResponse
    {
        $this->releaseCouponReservation($payment);
        $payment->update(['status' => 'failed', 'metadata' => [...($payment->metadata ?: []), 'gateway_error' => $message]]);
        return back()->with('error', translate($message));
    }

    /**
     * Release a paid checkout's held coupon slot (see reserveForUser) so an
     * abandoned/failed checkout doesn't lock the buyer out of retrying. Reservations
     * self-expire, so this is a best-effort early release.
     */
    private function releaseCouponReservation(Payment $payment): void
    {
        if ($key = data_get($payment->metadata, 'coupon_reservation_key')) {
            Coupon::releaseReservation((string) $key);
        }

        // Also return the global slot claimed at checkout, so a failed gateway session
        // doesn't permanently consume one of a max_uses-limited coupon's uses.
        if (data_get($payment->metadata, 'coupon_global_reserved') && ($code = data_get($payment->metadata, 'coupon_code'))) {
            Coupon::where('code', $code)->first()?->releaseGlobalUse();
        }
    }

    /**
     * The unused-plan credit to deduct from a checkout upgrade, or 0.0 when it
     * doesn't apply. Returns 0.0 for recurring, in-place-capable subscriptions:
     * those upgrade through the gateway's own proration (Stripe prorated
     * difference / PayPal revise), never through a checkout charge.
     */
    private function upgradeProrationCredit(?GatewaySubscription $currentSub, Plan $plan, string $billing, bool $isTrial): float
    {
        if ($isTrial || ! $currentSub || ! $currentSub->plan || $currentSub->plan->is_free) {
            return 0.0;
        }

        if (app(GatewaySubscriptionModifier::class)->supportsInPlace($currentSub)) {
            return 0.0;
        }

        $proration = app(SubscriptionProrationService::class);

        if ($proration->classifyChange($currentSub->plan, $currentSub->billing_cycle, $plan, $billing) !== SubscriptionProrationService::UPGRADE) {
            return 0.0;
        }

        return $proration->prorationCredit($currentSub);
    }

    private function minorAmount(float $amount, string $currency): int
    {
        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];
        return in_array(strtoupper($currency), $zeroDecimal, true) ? (int) round($amount) : (int) round($amount * 100);
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    private function validCoupon(?string $code, Plan $plan, User $user): ?Coupon
    {
        // Coupon system disabled by the admin — ignore any submitted code so no
        // discount can be applied even via a hand-crafted POST. Enforced here
        // because both createSession() and previewCoupon() resolve through it.
        if (! coupons_enabled()) { return null; }
        if (! $code) { return null; }
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $coupon || ! $coupon->isValid()) { abort(422, translate('Coupon is invalid or expired.')); }
        if ($coupon->plan_id && (int) $coupon->plan_id !== (int) $plan->id) { abort(422, translate('Coupon is not valid for this plan.')); }
        if (! $coupon->isEligibleForUser($user)) { abort(422, translate('Coupon is not valid for your account.')); }
        if ($coupon->hasReachedUserLimit($user)) { abort(422, translate('You have already used this coupon.')); }
        return $coupon;
    }

    private function discountedCycle(array $cycle, ?Coupon $coupon): array
    {
        $subtotal = (float) $cycle['subtotal_amount'];
        $discount = $coupon ? $coupon->calculateDiscount($subtotal) : 0.0;
        $discountedSubtotal = max(0, $subtotal - $discount);
        $vatAmount = round(($discountedSubtotal * (float) $cycle['vat_percentage']) / 100, 2);

        return [
            ...$cycle,
            // Keep the ORIGINAL subtotal for display — the discount is shown as its
            // own line, so overwriting the subtotal with the net made the breakdown
            // read inconsistently (Subtotal 8 / Discount 2 / Total 8). VAT and the
            // charge total are still computed on the post-discount base below.
            'subtotal_amount' => $subtotal,
            'discounted_subtotal_amount' => $discountedSubtotal,
            'vat_amount' => $vatAmount,
            'amount' => $cycle['is_trial'] ? 0.0 : round($discountedSubtotal + $vatAmount, 2),
            'discount_amount' => $discount,
        ];
    }
}
