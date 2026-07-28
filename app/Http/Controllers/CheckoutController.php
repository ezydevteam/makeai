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

        if ($gateway->slug === 'stripe') {
            return $this->createStripeSession($request, $payment, $plan, $data['billing']);
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

    public function pending(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth()->id(), 404);

        return Inertia::render('Checkout/Pending', [
            // Last screen of the payment flow, so it keeps the same stripped chrome as the
            // two before it rather than dropping the buyer back into the full site mid-flow.
            'hide_header' => true,
            'hide_footer' => true,
            'payment' => $this->paymentPayload($payment),
        ]);
    }

    public function paypalReturn(Request $request, Payment $payment, PaymentActivationService $activation): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->gateway === 'paypal', 404);

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
        $activation->activateFromPayment($payment, $captureId);

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
            'success_url' => route('user.dashboard').'?checkout=success',
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

    private function createPaddlePayLink(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $vendorId = $gateway->getCredential('vendor_id');
        $authCode = $gateway->getCredential('api_key');
        if (! $vendorId || ! $authCode) { return $this->failGatewaySession($payment, 'Paddle is not configured.'); }

        $response = Http::asForm()->post('https://vendors.paddle.com/api/2.0/product/generate_pay_link', [
            'vendor_id' => $vendorId, 'vendor_auth_code' => $authCode, 'title' => $plan->name,
            'prices' => [strtoupper($payment->currency).':'.number_format((float) $payment->amount, 2, '.', '')],
            'quantity' => 1, 'return_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'webhook_url' => $this->absoluteUrl($request, '/webhooks/paddle'),
            'passthrough' => json_encode(['payment_ulid' => $payment->ulid]),
        ]);

        if ($response->failed() || ! $response->json('success')) { return $this->failGatewaySession($payment, $response->json('error.message', 'Paddle pay link failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('response.product_id'), 'metadata' => [...($payment->metadata ?: []), 'gateway_pay_link' => $response->json('response.url')]]);
        return redirect()->away($response->json('response.url'));
    }

    private function createRazorpayPaymentLink(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $keyId = $gateway->getCredential('key_id');
        $keySecret = $gateway->getCredential('key_secret');
        if (! $keyId || ! $keySecret) { return $this->failGatewaySession($payment, 'Razorpay is not configured.'); }

        $response = Http::withBasicAuth($keyId, $keySecret)->acceptJson()->post('https://api.razorpay.com/v1/payment_links', [
            'amount' => $this->minorAmount((float) $payment->amount, $payment->currency), 'currency' => strtoupper($payment->currency),
            'description' => $plan->name, 'reference_id' => $payment->ulid,
            'callback_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid), 'callback_method' => 'get',
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
        $response = Http::asForm()->post($baseUrl.'/gwprocess/v4/api.php', [
            'store_id' => $storeId, 'store_passwd' => $storePassword, 'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper($payment->currency), 'tran_id' => $payment->ulid,
            'success_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'fail_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$plan->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
            'ipn_url' => $this->absoluteUrl($request, '/webhooks/sslcommerz'),
            'cus_name' => $request->user()->name, 'cus_email' => $request->user()->email,
            'cus_add1' => 'N/A', 'cus_city' => 'N/A', 'cus_country' => 'N/A', 'cus_phone' => 'N/A',
            'shipping_method' => 'NO', 'product_name' => $plan->name, 'product_category' => 'Subscription', 'product_profile' => 'non-physical-goods',
        ]);

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
        $response = Http::withToken($token)->acceptJson()->asForm()->post($baseUrl.'/orders', [
            'order_id' => $payment->ulid, 'price_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'price_currency' => strtoupper($payment->currency), 'receive_currency' => strtoupper($payment->currency),
            'title' => $plan->name, 'callback_url' => $this->absoluteUrl($request, '/webhooks/coingate?token='.$webhookToken),
            'cancel_url' => $this->absoluteUrl($request, '/checkout?plan='.$plan->slug.'&billing='.data_get($payment->metadata, 'billing_cycle', 'monthly')),
            'success_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
        ]);

        if ($response->failed()) { return $this->failGatewaySession($payment, $response->json('message', 'CoinGate order failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('id'), 'metadata' => [...($payment->metadata ?: []), 'coingate_webhook_token' => $webhookToken]]);
        return redirect()->away($response->json('payment_url'));
    }

    private function createPaystackTransaction(Request $request, Payment $payment, PaymentGateway $gateway): RedirectResponse
    {
        $secretKey = $gateway->getCredential('secret_key');
        if (! $secretKey) { return $this->failGatewaySession($payment, 'Paystack is not configured.'); }

        $response = Http::withToken($secretKey)->acceptJson()->post('https://api.paystack.co/transaction/initialize', [
            'email' => $request->user()->email, 'amount' => $this->minorAmount((float) $payment->amount, $payment->currency),
            'currency' => strtoupper($payment->currency), 'reference' => $payment->ulid,
            'callback_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'metadata' => ['payment_ulid' => $payment->ulid],
        ]);

        if ($response->failed() || ! $response->json('status')) { return $this->failGatewaySession($payment, $response->json('message', 'Paystack transaction failed.')); }

        $payment->update(['gateway_payment_id' => $response->json('data.reference'), 'metadata' => [...($payment->metadata ?: []), 'gateway_access_code' => $response->json('data.access_code')]]);
        return redirect()->away($response->json('data.authorization_url'));
    }

    private function createTwoCheckoutUrl(Request $request, Payment $payment, Plan $plan, PaymentGateway $gateway): RedirectResponse
    {
        $merchantCode = $gateway->getCredential('merchant_code');
        if (! $merchantCode) { return $this->failGatewaySession($payment, '2Checkout is not configured.'); }

        $baseUrl = $gateway->is_test_mode ? 'https://sandbox.2checkout.com/checkout/buy' : 'https://secure.2checkout.com/checkout/buy';
        $query = http_build_query([
            'merchant' => $merchantCode, 'dynamic' => 1, 'prod' => $plan->name,
            'price' => number_format((float) $payment->amount, 2, '.', ''), 'qty' => 1,
            'currency' => strtoupper($payment->currency), 'merchant_order_id' => $payment->ulid,
            'return-url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
            'x_receipt_link_url' => $this->absoluteUrl($request, '/checkout/pending/'.$payment->ulid),
        ]);

        $payment->update(['gateway_payment_id' => $payment->ulid]);
        return redirect()->away($baseUrl.'?'.$query);
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
