<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Http\Middleware\CheckExtendedLicense;
use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Currency;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Pricing\PlanPriceResolver;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for the 2026-07-14 payment audit.
 *
 * Each test reproduces a concrete defect found in the audit and asserts the fixed
 * behaviour, so a future refactor cannot silently reopen the hole.
 */
class PaymentAuditFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckPremium::class, CheckExtendedLicense::class, LicenseMiddleware::class]);

        // Lifecycle events broadcast; there is no realtime server in the test env.
        config(['broadcasting.default' => 'null']);

        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
        settings_set('pricing_show_monthly', true, 'boolean', 'pricing');
        settings_set('pricing_show_yearly', true, 'boolean', 'pricing');
        settings_set('pricing_show_lifetime', true, 'boolean', 'pricing');
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    /** A plan sold monthly/yearly but with NO lifetime price configured. */
    private function planWithoutLifetimePrice(): Plan
    {
        return Plan::create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10,
            'price_yearly' => 100,
            'price_lifetime' => null,
            'vat_percentage' => 0,
            'credits' => 1000,
            'is_active' => true,
            'is_free' => false,
            'sort_order' => 2,
        ]);
    }

    private function bankTransferGateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'bank_transfer',
            'name' => 'Bank Transfer',
            'is_enabled' => true,
            'is_test_mode' => false,
            'processing_fee_type' => 'none',
            'processing_fee_value' => 0,
            'sort_order' => 1,
        ]);
    }

    // ─── Fix 1: free permanent plan via an unpriced billing cycle ───

    public function test_resolver_exposes_the_configured_list_price_per_cycle(): void
    {
        $resolved = (new PlanPriceResolver)->resolve($this->planWithoutLifetimePrice());

        $this->assertSame(10.0, $resolved['monthly']['list_amount']);
        // Never priced → 0, which is how checkout tells "not sold" from "free".
        $this->assertSame(0.0, $resolved['lifetime']['list_amount']);
    }

    public function test_checkout_page_404s_for_a_cycle_the_plan_is_not_sold_on(): void
    {
        $plan = $this->planWithoutLifetimePrice();

        $this->actingAs($this->user())
            ->get('/checkout?plan='.$plan->slug.'&billing=lifetime')
            ->assertNotFound();
    }

    public function test_checkout_session_refuses_to_grant_an_unpriced_lifetime_plan_for_free(): void
    {
        $plan = $this->planWithoutLifetimePrice();
        $gateway = $this->bankTransferGateway();
        $user = $this->user();

        $this->actingAs($user)
            ->post('/checkout/session', [
                'plan' => $plan->slug,
                'billing' => 'lifetime',
                'gateway' => $gateway->slug,
            ])
            ->assertSessionHas('error');

        // The whole point: no subscription, no payment, no plan granted — and in
        // particular no NULL-period (never-expiring) subscription.
        $this->assertDatabaseCount('billing_subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($user->fresh()->plan_id);
    }

    public function test_a_priced_cycle_still_checks_out_normally(): void
    {
        $plan = $this->planWithoutLifetimePrice();
        $gateway = $this->bankTransferGateway();
        $user = $this->user();

        $this->actingAs($user)
            ->post('/checkout/session', [
                'plan' => $plan->slug,
                'billing' => 'monthly',
                'gateway' => $gateway->slug,
            ])
            ->assertRedirect();

        // Bank transfer creates a pending payment awaiting admin approval.
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_disabled_billing_cycle_cannot_be_purchased_by_posting_it_directly(): void
    {
        settings_set('pricing_show_yearly', false, 'boolean', 'pricing');

        $plan = $this->planWithoutLifetimePrice();
        $gateway = $this->bankTransferGateway();

        $this->actingAs($this->user())
            ->post('/checkout/session', [
                'plan' => $plan->slug,
                'billing' => 'yearly',
                'gateway' => $gateway->slug,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    // ─── Fix 4: free permanent plan via a "downgrade" to lifetime ───

    private function activeSubscriptionFor(User $user, Plan $plan): GatewaySubscription
    {
        $subscription = GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => GatewaySubscription::STATUS_ACTIVE,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_123',
            'amount' => 20,
            'currency' => 'USD',
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);

        $user->update(['plan_id' => $plan->id, 'subscription_status' => 'active', 'subscription_ends_at' => now()->addDays(25)]);

        return $subscription;
    }

    public function test_downgrade_to_a_lifetime_cycle_is_rejected(): void
    {
        $user = $this->user();
        $current = Plan::create([
            'name' => 'Business', 'slug' => 'business-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'credits' => 5000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 3, 'vat_percentage' => 0,
        ]);
        $lower = $this->planWithoutLifetimePrice(); // sort_order 2 → a downgrade

        $this->activeSubscriptionFor($user, $current);

        $this->actingAs($user)
            ->post('/subscription/downgrade', ['plan' => $lower->slug, 'billing' => 'lifetime'])
            ->assertSessionHas('error');

        // No schedule was written, so the cron can never grant a free lifetime plan.
        $this->assertDatabaseMissing('billing_subscriptions', ['scheduled_plan_id' => $lower->id]);
        $this->assertSame($current->id, $user->fresh()->plan_id);
    }

    public function test_scheduling_a_lifetime_downgrade_is_refused_by_the_service(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $plan);

        $this->expectException(\InvalidArgumentException::class);

        app(SubscriptionLifecycleService::class)->scheduleDowngrade($subscription, $plan, 'lifetime');
    }

    public function test_cron_discards_a_legacy_lifetime_schedule_instead_of_granting_it(): void
    {
        $user = $this->user();
        $current = Plan::create([
            'name' => 'Business', 'slug' => 'business-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'credits' => 5000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 3, 'vat_percentage' => 0,
        ]);
        $target = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $current);

        // A row exactly as the old code would have written it: due, and pointing at a
        // lifetime cycle. Applying it would set a NULL period end with no charge.
        $subscription->forceFill([
            'scheduled_plan_id' => $target->id,
            'scheduled_billing_cycle' => 'lifetime',
            'scheduled_change_at' => now()->subMinute(),
        ])->save();

        app(SubscriptionLifecycleService::class)->applyScheduledChanges();

        $subscription->refresh();

        // Schedule discarded; the user keeps their real plan and a real period end.
        $this->assertNull($subscription->scheduled_plan_id);
        $this->assertSame($current->id, $subscription->plan_id);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertSame($current->id, $user->fresh()->plan_id);
    }

    public function test_a_normal_monthly_downgrade_still_schedules(): void
    {
        $user = $this->user();
        $current = Plan::create([
            'name' => 'Business', 'slug' => 'business-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'credits' => 5000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 3, 'vat_percentage' => 0,
        ]);
        $lower = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $current);

        app(SubscriptionLifecycleService::class)->scheduleDowngrade($subscription, $lower, 'monthly');

        $this->assertSame($lower->id, $subscription->fresh()->scheduled_plan_id);
    }

    // ─── Fix 2: Stripe one-off line item ───

    public function test_stripe_one_off_line_item_uses_inline_price_data_not_a_currency_key(): void
    {
        $payment = new Payment(['amount' => 29.99, 'currency' => 'EUR']);

        $method = new ReflectionMethod(CheckoutController::class, 'stripeOneOffLineItem');
        $item = $method->invoke(app(CheckoutController::class), $payment, 'Pro plan');

        // The bug was passing ['eur' => 2999], which Cashier turns into
        // ['price' => 'eur'] — Stripe then fails with "No such price: eur".
        $this->assertArrayNotHasKey('price', $item);
        $this->assertSame('eur', $item['price_data']['currency']);
        $this->assertSame(2999, $item['price_data']['unit_amount']);
        $this->assertSame(1, $item['quantity']);
        $this->assertSame('Pro plan', $item['price_data']['product_data']['name']);
    }

    public function test_stripe_one_off_line_item_handles_zero_decimal_currencies(): void
    {
        $payment = new Payment(['amount' => 5000, 'currency' => 'JPY']);

        $method = new ReflectionMethod(CheckoutController::class, 'stripeOneOffLineItem');
        $item = $method->invoke(app(CheckoutController::class), $payment, 'Pro plan');

        // JPY has no minor unit — 5000 yen is 5000, not 500000.
        $this->assertSame(5000, $item['price_data']['unit_amount']);
    }

    // ─── Fix 3: Stripe webhook against the pinned "basil" API shape ───

    private function postStripeWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        config(['cashier.webhook.secret' => 'whsec_test', 'cashier.webhook.tolerance' => 300]);

        $payload = json_encode($event);
        $timestamp = now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call(
            'POST',
            '/webhooks/stripe',
            [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );
    }

    public function test_renewal_is_applied_for_a_basil_shaped_invoice(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $plan);

        $originalEnd = $subscription->current_period_end;

        // Stripe's 2025 "basil" API (the version this SDK pins) has no
        // invoice.subscription — it lives under invoice.parent. The old handler read
        // only the legacy key, so renewals were silently dropped.
        $this->postStripeWebhook([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_test_1',
                'billing_reason' => 'subscription_cycle',
                'amount_paid' => 1000,
                'currency' => 'usd',
                'parent' => [
                    'subscription_details' => ['subscription' => 'sub_test_123'],
                ],
            ]],
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame(GatewaySubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->current_period_end->greaterThan($originalEnd));
        $this->assertDatabaseHas('payments', [
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_1',
            'status' => 'completed',
        ]);
    }

    public function test_renewal_is_still_applied_for_a_legacy_shaped_invoice(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $plan);
        $originalEnd = $subscription->current_period_end;

        $this->postStripeWebhook([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_test_2',
                'billing_reason' => 'subscription_cycle',
                'amount_paid' => 1000,
                'currency' => 'usd',
                'subscription' => 'sub_test_123',
            ]],
        ])->assertOk();

        $this->assertTrue($subscription->fresh()->current_period_end->greaterThan($originalEnd));
    }

    public function test_failed_renewal_marks_past_due_on_a_basil_shaped_invoice(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $plan);

        $this->postStripeWebhook([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_test_3',
                'parent' => ['subscription_details' => ['subscription' => 'sub_test_123']],
            ]],
        ])->assertOk();

        $this->assertSame(GatewaySubscription::STATUS_PAST_DUE, $subscription->fresh()->status);
    }

    public function test_subscription_period_end_is_read_from_the_item_on_basil(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $subscription = $this->activeSubscriptionFor($user, $plan);

        $newEnd = now()->addDays(60)->startOfSecond();

        // basil moved current_period_end off the subscription and onto its items.
        $this->postStripeWebhook([
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_test_123',
                'status' => 'active',
                'items' => ['data' => [['current_period_end' => $newEnd->getTimestamp()]]],
            ]],
        ])->assertOk();

        $this->assertSame(
            $newEnd->getTimestamp(),
            $subscription->fresh()->current_period_end->getTimestamp(),
        );
    }

    // ─── processing_fee_type = 'none' must not charge a fee ───

    private function gatewayWithFee(string $type, float $value): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'bank_transfer',
            'name' => 'Bank Transfer',
            'is_enabled' => true,
            'is_test_mode' => false,
            'processing_fee_type' => $type,
            'processing_fee_value' => $value,
            'sort_order' => 1,
        ]);
    }

    public function test_fee_type_none_charges_nothing_even_with_a_leftover_value(): void
    {
        // 'none' is truthy, so the old guard fell through to the fixed-fee branch and
        // silently added this value to every order.
        $gateway = $this->gatewayWithFee('none', 5);

        $this->assertSame(0.0, app(PaymentGatewayManager::class)->processingFee($gateway, 100));
        $this->assertSame(100.0, app(PaymentGatewayManager::class)->totalWithFee($gateway, 100));
    }

    public function test_percentage_and_fixed_fees_still_apply(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertSame(2.5, $manager->processingFee($this->gatewayWithFee('percentage', 2.5), 100));

        PaymentGateway::query()->delete();

        $this->assertSame(5.0, $manager->processingFee($this->gatewayWithFee('fixed', 5), 100));
    }

    public function test_no_fee_is_charged_on_a_zero_amount(): void
    {
        // A free trial / 100%-off coupon must not be turned into a fixed-fee charge.
        $this->assertSame(0.0, app(PaymentGatewayManager::class)->processingFee($this->gatewayWithFee('fixed', 5), 0));
    }

    // ─── A recurring purchase records what the gateway actually bills ───

    private function payPalGateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'paypal',
            'name' => 'PayPal',
            'is_enabled' => true,
            'is_test_mode' => true,
            'processing_fee_type' => 'fixed',
            'processing_fee_value' => 3,
            'credentials' => PaymentGateway::encryptCredentials(['client_id' => 'cid', 'client_secret' => 'sec']),
            'sort_order' => 2,
        ]);
    }

    private function fakePayPal(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/billing/subscriptions' => Http::response([
                'id' => 'I-SUB123',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve']],
            ]),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER123',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve-order']],
            ]),
            '*' => Http::response([]),
        ]);
    }

    public function test_a_recurring_purchase_records_the_gateway_price_with_no_processing_fee(): void
    {
        $this->fakePayPal();
        $gateway = $this->payPalGateway();
        $user = $this->user();

        $plan = $this->planWithoutLifetimePrice();
        $plan->update(['paypal_plan_monthly_id' => 'P-RECURRING']);

        $this->actingAs($user)
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => 'paypal'])
            ->assertRedirect('https://paypal.test/approve');

        $payment = Payment::where('user_id', $user->id)->firstOrFail();

        // PayPal bills the fixed price defined in its own plan — the $3 processing fee is
        // never taken, so recording 13.00 would overstate the charge (and inflate the next
        // upgrade's proration credit, which is derived from this amount).
        $this->assertSame(10.0, (float) $payment->amount);
        $this->assertSame(0.0, (float) $payment->metadata['processing_fee_amount']);
        $this->assertSame(10.0, (float) $payment->metadata['total_amount']);
    }

    public function test_a_one_time_purchase_still_adds_the_processing_fee(): void
    {
        $this->fakePayPal();
        $this->payPalGateway();
        $user = $this->user();

        // No PayPal plan id → a one-time order, which does carry the fee.
        $plan = $this->planWithoutLifetimePrice();

        $this->actingAs($user)
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => 'paypal'])
            ->assertRedirect('https://paypal.test/approve-order');

        $payment = Payment::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(13.0, (float) $payment->amount);
        $this->assertSame(3.0, (float) $payment->metadata['processing_fee_amount']);
    }

    // ─── Renewing early must not forfeit the days already paid for ───

    private function pendingPaymentFor(User $user, Plan $plan, string $cycle = 'monthly'): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'bank_transfer',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => ['billing_cycle' => $cycle],
        ]);
    }

    public function test_renewing_the_same_plan_early_extends_the_remaining_period(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $existing = $this->activeSubscriptionFor($user, $plan); // 25 days left, monthly

        $subscription = app(SubscriptionLifecycleService::class)
            ->activateFromPayment($this->pendingPaymentFor($user, $plan), 'manual-renewal-1');

        // The 25 unused days are carried over, not confiscated: the new period runs a month
        // on from where the old one ended.
        $this->assertSame(
            $existing->current_period_end->copy()->addMonth()->toDateString(),
            $subscription->current_period_end->toDateString(),
        );
        $this->assertTrue($user->fresh()->subscription_ends_at->greaterThan(now()->addMonth()));
    }

    public function test_renewing_after_expiry_starts_from_today(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();

        $lapsed = $this->activeSubscriptionFor($user, $plan);
        $lapsed->forceFill(['status' => 'expired', 'current_period_end' => now()->subDay()])->save();

        $subscription = app(SubscriptionLifecycleService::class)
            ->activateFromPayment($this->pendingPaymentFor($user, $plan), 'manual-renewal-2');

        // Nothing left to carry over — a lapsed period must not extend anything.
        $this->assertSame(now()->addMonth()->toDateString(), $subscription->current_period_end->toDateString());
    }

    public function test_switching_to_a_different_plan_starts_a_fresh_period(): void
    {
        $user = $this->user();
        $current = $this->planWithoutLifetimePrice();
        $target = Plan::create([
            'name' => 'Business', 'slug' => 'business-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'credits' => 5000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 3, 'vat_percentage' => 0,
        ]);

        $this->activeSubscriptionFor($user, $current);

        $subscription = app(SubscriptionLifecycleService::class)
            ->activateFromPayment($this->pendingPaymentFor($user, $target), 'manual-upgrade-1');

        // A plan CHANGE starts fresh — its unused value comes back as a proration credit at
        // checkout, so extending as well would pay the customer twice for the same days.
        $this->assertSame(now()->addMonth()->toDateString(), $subscription->current_period_end->toDateString());
    }

    public function test_switching_billing_cycle_starts_a_fresh_period(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();

        $this->activeSubscriptionFor($user, $plan); // monthly

        $subscription = app(SubscriptionLifecycleService::class)
            ->activateFromPayment($this->pendingPaymentFor($user, $plan, 'yearly'), 'manual-cycle-1');

        $this->assertSame(now()->addYear()->toDateString(), $subscription->current_period_end->toDateString());
    }

    // ─── Stripe must not activate an unpaid checkout session ───

    private function pendingStripePayment(User $user, Plan $plan): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => ['billing_cycle' => 'monthly'],
        ]);
    }

    public function test_an_unpaid_checkout_session_does_not_grant_the_plan(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $payment = $this->pendingStripePayment($user, $plan);

        // Delayed-notification methods complete the session BEFORE the money arrives.
        $this->postStripeWebhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_1',
                'payment_status' => 'unpaid',
                'metadata' => ['payment_id' => $payment->ulid],
            ]],
        ])->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($user->fresh()->plan_id);
        $this->assertDatabaseCount('billing_subscriptions', 0);
    }

    public function test_the_plan_is_granted_once_the_async_payment_succeeds(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $payment = $this->pendingStripePayment($user, $plan);

        $this->postStripeWebhook([
            'type' => 'checkout.session.async_payment_succeeded',
            'data' => ['object' => [
                'id' => 'cs_test_2',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_2',
                'metadata' => ['payment_id' => $payment->ulid],
            ]],
        ])->assertOk();

        $payment->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertSame($plan->id, $user->fresh()->plan_id);
        // The PaymentIntent is recorded so a later refund can be traced back here.
        $this->assertSame('pi_test_2', $payment->metadata['stripe_payment_intent']);
    }

    public function test_a_failed_async_payment_fails_the_pending_payment(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $payment = $this->pendingStripePayment($user, $plan);

        $this->postStripeWebhook([
            'type' => 'checkout.session.async_payment_failed',
            'data' => ['object' => [
                'id' => 'cs_test_3',
                'metadata' => ['payment_id' => $payment->ulid],
            ]],
        ])->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertNull($user->fresh()->plan_id);
    }

    // ─── Stripe refunds must reconcile ───

    private function completedStripePayment(User $user, Plan $plan): Payment
    {
        $subscription = $this->activeSubscriptionFor($user, $plan);

        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'cs_test_session',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'metadata' => ['billing_cycle' => 'monthly', 'stripe_payment_intent' => 'pi_test_9'],
        ]);
    }

    public function test_a_full_refund_revokes_access(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $payment = $this->completedStripePayment($user, $plan);

        // A charge carries no session id, so the old lookup (on gateway_payment_id) never
        // matched and refunds were silently ignored. It resolves via the ulid we now stamp
        // onto the PaymentIntent.
        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_1',
                'amount' => 1000,
                'amount_refunded' => 1000,
                'currency' => 'usd',
                'payment_intent' => 'pi_test_9',
                'metadata' => ['payment_id' => $payment->ulid],
            ]],
        ])->assertOk();

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('expired', $payment->subscription->fresh()->status);
        $this->assertNull($user->fresh()->plan_id);
    }

    public function test_a_refund_resolves_via_the_payment_intent_when_charge_metadata_is_absent(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $payment = $this->completedStripePayment($user, $plan);

        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_2',
                'amount' => 1000,
                'amount_refunded' => 1000,
                'currency' => 'usd',
                'payment_intent' => 'pi_test_9',
            ]],
        ])->assertOk();

        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_a_partial_refund_does_not_revoke_access(): void
    {
        $user = $this->user();
        $plan = $this->planWithoutLifetimePrice();
        $payment = $this->completedStripePayment($user, $plan);

        $this->postStripeWebhook([
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_3',
                'amount' => 1000,
                'amount_refunded' => 250,
                'currency' => 'usd',
                'payment_intent' => 'pi_test_9',
            ]],
        ])->assertOk();

        $payment->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertSame(2.5, $payment->metadata['partial_refund']['amount_refunded']);
        $this->assertSame($plan->id, $user->fresh()->plan_id);
    }

    // ─── PayPal downgrades must not promise a price PayPal won't bill ───

    public function test_a_paypal_downgrade_ends_at_period_end_instead_of_scheduling(): void
    {
        $this->fakePayPal();
        $this->payPalGateway();

        $user = $this->user();
        $current = Plan::create([
            'name' => 'Business', 'slug' => 'business-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'credits' => 5000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 3, 'vat_percentage' => 0,
        ]);
        $lower = $this->planWithoutLifetimePrice();

        $subscription = $this->activeSubscriptionFor($user, $current);
        $subscription->update(['gateway' => 'paypal', 'gateway_subscription_id' => 'I-SUB123']);

        $this->actingAs($user)
            ->post('/subscription/downgrade', ['plan' => $lower->slug, 'billing' => 'monthly'])
            ->assertSessionHas('success');

        $subscription->refresh();

        // PayPal's revise can require buyer approval; until then PayPal keeps billing the
        // OLD price. Scheduling would have flipped the plan locally while the customer was
        // still charged the higher amount — so this must cancel at period end instead.
        $this->assertNull($subscription->scheduled_plan_id);
        $this->assertSame(GatewaySubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertSame($current->id, $subscription->plan_id);
    }
}
