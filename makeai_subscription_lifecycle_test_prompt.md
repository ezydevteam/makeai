# DeepSeek Implementation Prompt
# MakeAI — Subscription & Payment Lifecycle: End-to-End AI Agent Test Suite

---

## CONTEXT

You are implementing a **complete automated test suite** for MakeAI's subscription and payment
lifecycle. This is a production-grade Laravel AI SaaS platform. The tests must cover the **entire
lifecycle** of a subscription from creation → AI usage → renewal → cancellation → expiry →
termination — using PestPHP.

**Stack:**
- PHP 8.3+, Laravel 13+, PestPHP (latest), SQLite in-memory for tests
- Queue connection: `sync` during tests
- All AI calls mocked — never hit real API
- All payment gateway calls mocked — never hit Stripe/PayPal in tests

**Architecture invariants you MUST follow:**
- AI calls use `laravel/ai` SDK via `AiService` with `CompletionRequest` DTO — never raw HTTP
- Credit deduction uses `TokenGuard::before()` + `TokenGuard::after()` pattern
- `CreditService` handles credit add/deduct/refill operations
- `SubscriptionService` handles plan changes, cancellations, terminations
- Webhooks processed by gateway-specific jobs: `ProcessStripeWebhook`, `ProcessPayPalWebhook`
- `settings()` helper reads from `settings` table (Redis-cached) — never `config()` or `env()`
- `isProAvailable()` = `is_extended_license() AND settings('subscriptions_enabled') === true`
- `get_license_type()` returns `1` (regular) or `2` (extended)
- `users.ulid` is the only public-facing user ID — never expose `users.id`
- Named queues: `otp, ai, media, emails, webhooks, social, embeddings, default, low`

---

## TASK

Create the following files **completely** — no placeholders, no "// TODO", no skeleton stubs.
Every test must be a real, runnable assertion.

---

## FILE 1: `tests/Feature/Payment/SubscriptionLifecycleTest.php`

This is the **primary test file** — one continuous lifecycle, plus edge cases.

### Lifecycle Flow to test (in order):

```
1. SETUP: Extended license + subscriptions_enabled
2. SUBSCRIBE: User subscribes to "Pro" plan (monthly) via Stripe
3. AI USAGE: User generates text → credits deducted correctly
4. CREDIT GUARD: User with 0 credits cannot generate → 402 returned
5. RENEWAL: invoice.payment_succeeded webhook → credits refill to plan amount
6. PLAN UPGRADE: User upgrades from Pro → Business (mid-cycle)
7. CANCEL: User cancels → subscription_status = 'pending_cancellation', access until period end
8. POST-CANCEL ACCESS: User can still generate during grace period
9. EXPIRY: subscription_ends_at passes → subscription_status = 'expired', generation blocked
10. REINSTATE: Admin reinstates subscription manually
11. TERMINATE: customer.subscription.deleted webhook → immediate lock
12. REFUND: Admin issues refund → payment status updated
13. LICENSE GATE: Regular license → subscription endpoint returns 403
14. TRIAL: Plan with trial_days=7 → trialing status, no immediate charge
15. TRIAL END: trial_ends_at passes → auto-charge, status = 'active'
```

### Test file structure:

```php
<?php

use App\Models\User;
use App\Models\Plan;
use App\Models\Payment;
use App\Services\Payment\SubscriptionService;
use App\Services\Payment\CreditService;
use App\Services\AI\TokenGuard;
use App\Jobs\Payment\ProcessStripeWebhook;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

// Helper: build a realistic Stripe webhook payload
function stripeWebhookPayload(string $event, array $data): array
{
    return [
        'id'   => 'evt_test_' . uniqid(),
        'type' => $event,
        'data' => ['object' => $data],
    ];
}

// Helper: set license type via settings table
function setLicense(int $type): void
{
    settings_set('license_type', $type);
    settings_set('subscriptions_enabled', $type === 2);
    Cache::forget('setting_license_type');
    Cache::forget('setting_subscriptions_enabled');
}

beforeEach(function () {
    // Always start with extended license + subscriptions enabled
    setLicense(2);
});

// ─── STEP 1: Setup assertion ───────────────────────────────────────────────

it('confirms extended license makes isProAvailable() true', function () {
    setLicense(2);
    expect(isProAvailable())->toBeTrue();
});

it('confirms regular license makes isProAvailable() false', function () {
    setLicense(1);
    expect(isProAvailable())->toBeFalse();
});

// ─── STEP 2: Subscribe ─────────────────────────────────────────────────────

it('creates a stripe subscription and allocates plan credits', function () {
    $user = User::factory()->create(['credits' => 0]);
    $plan = Plan::factory()->create([
        'slug'            => 'pro-monthly',
        'price_monthly'   => 29.00,
        'credits_monthly' => 5000,
        'trial_days'      => 0,
    ]);

    // Mock Stripe service — never call real Stripe
    $this->mock(\App\Services\Payment\StripeService::class, function ($mock) use ($plan) {
        $mock->shouldReceive('createSubscription')
            ->once()
            ->andReturn(new \App\DTOs\Payment\SubscriptionResult(
                success: true,
                subscriptionId: 'sub_test_123',
                customerId: 'cus_test_456',
                status: 'active',
            ));
    });

    $service = app(SubscriptionService::class);
    $result  = $service->subscribe($user, $plan, 'stripe', 'monthly', ['payment_method' => 'pm_card_visa']);

    $user->refresh();

    expect($result->success)->toBeTrue();
    expect($user->subscription_status)->toBe('active');
    expect($user->plan_id)->toBe($plan->id);
    expect($user->credits)->toBe(5000.0);
    expect($user->stripe_subscription_id)->toBe('sub_test_123');
    expect($user->subscription_ends_at)->toBeGreaterThan(now());

    // Payment record created
    expect(Payment::where('user_id', $user->id)->where('status', 'paid')->exists())->toBeTrue();
});

// ─── STEP 3: AI Usage ──────────────────────────────────────────────────────

it('deducts credits after successful AI generation', function () {
    $user = User::factory()->withCredits(5000)->withPlan('pro-monthly')->create();
    $tool = \App\Models\AiTool::factory()->create(['slug' => 'blog-writer', 'avg_output_tokens' => 500]);

    // Mock laravel/ai SDK — never hit real API
    $this->mock(\App\Services\AI\AiService::class, function ($mock) {
        $mock->shouldReceive('complete')
            ->once()
            ->andReturn(new \App\DTOs\AI\CompletionResult(
                content: 'Generated blog content here...',
                inputTokens: 120,
                outputTokens: 480,
                model: 'gpt-4o-mini',
                provider: 'openai',
            ));
    });

    $response = $this->actingAs($user)
        ->postJson('/api/v1/generate/text', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'AI in 2025', 'tone' => 'professional'],
        ]);

    $response->assertStatus(200);

    $user->refresh();
    expect($user->credits)->toBeLessThan(5000); // credits deducted

    // AI usage log created
    expect(\App\Models\AiUsageLog::where('user_id', $user->id)
        ->where('tool', 'blog-writer')
        ->where('status', 'success')
        ->exists()
    )->toBeTrue();
});

// ─── STEP 4: Credit Guard ──────────────────────────────────────────────────

it('blocks AI generation when user has 0 credits', function () {
    $user = User::factory()->withCredits(0)->create();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/generate/text', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'test'],
        ]);

    $response->assertStatus(402)
        ->assertJsonFragment(['code' => 'INSUFFICIENT_CREDITS']);

    // No usage log for failed attempt (or failed status)
    expect(\App\Models\AiUsageLog::where('user_id', $user->id)
        ->where('status', 'success')
        ->exists()
    )->toBeFalse();
});

it('blocks AI generation when daily credit limit is reached', function () {
    settings_set('user_daily_credit_limit', 100);

    $user = User::factory()->withCredits(5000)->create([
        'credits_used_today' => 100, // already at daily limit
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/generate/text', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'test'],
        ]);

    $response->assertStatus(402)
        ->assertJsonFragment(['code' => 'CREDIT_LIMIT']);
});

// ─── STEP 5: Renewal Webhook ───────────────────────────────────────────────

it('refills credits on invoice.payment_succeeded webhook', function () {
    $plan = Plan::factory()->create(['credits_monthly' => 5000]);
    $user = User::factory()->create([
        'plan_id'                  => $plan->id,
        'credits'                  => 200, // nearly empty after a month of use
        'subscription_status'      => 'active',
        'stripe_subscription_id'   => 'sub_test_123',
    ]);

    $payload = stripeWebhookPayload('invoice.payment_succeeded', [
        'subscription' => 'sub_test_123',
        'customer'     => 'cus_test_456',
        'amount_paid'  => 2900,
        'currency'     => 'usd',
        'id'           => 'in_test_789',
    ]);

    // Process webhook synchronously (QUEUE_CONNECTION=sync in .env.testing)
    $this->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => $this->generateStripeSignature($payload),
    ])->assertStatus(200);

    $user->refresh();

    expect($user->credits)->toBe(5000.0); // refilled to plan amount
    expect($user->credits_used_today)->toBe(0.0); // reset
    expect($user->credits_used_month)->toBe(0.0); // reset

    // New payment record
    expect(Payment::where('user_id', $user->id)
        ->where('transaction_id', 'in_test_789')
        ->where('status', 'paid')
        ->exists()
    )->toBeTrue();
});

// ─── STEP 6: Plan Upgrade ──────────────────────────────────────────────────

it('upgrades plan mid-cycle and prorates credits', function () {
    $proPlan      = Plan::factory()->create(['slug' => 'pro', 'credits_monthly' => 5000, 'price_monthly' => 29]);
    $businessPlan = Plan::factory()->create(['slug' => 'business', 'credits_monthly' => 15000, 'price_monthly' => 79]);

    $user = User::factory()->create([
        'plan_id'             => $proPlan->id,
        'credits'             => 3000, // used 2000 so far this month
        'subscription_status' => 'active',
    ]);

    $this->mock(\App\Services\Payment\StripeService::class, function ($mock) {
        $mock->shouldReceive('updateSubscription')->once()->andReturn(true);
    });

    $service = app(SubscriptionService::class);
    $service->upgrade($user, $businessPlan);

    $user->refresh();

    expect($user->plan_id)->toBe($businessPlan->id);
    // Credits should be prorated upward (not reset to 0)
    expect($user->credits)->toBeGreaterThan(3000);
});

// ─── STEP 7: Cancel ────────────────────────────────────────────────────────

it('marks subscription as pending_cancellation and preserves access', function () {
    $plan = Plan::factory()->create(['credits_monthly' => 5000]);
    $user = User::factory()->create([
        'plan_id'                => $plan->id,
        'subscription_status'    => 'active',
        'subscription_ends_at'   => now()->addDays(20),
        'stripe_subscription_id' => 'sub_test_123',
    ]);

    $this->mock(\App\Services\Payment\StripeService::class, function ($mock) {
        $mock->shouldReceive('cancelSubscription')->once()->andReturn(true);
    });

    $response = $this->actingAs($user)->deleteJson('/api/v1/subscription');

    $response->assertStatus(200);

    $user->refresh();

    expect($user->subscription_status)->toBe('pending_cancellation');
    expect($user->subscription_ends_at)->toBeInstanceOf(Carbon::class);
    expect($user->subscription_ends_at->isFuture())->toBeTrue(); // still has access
});

// ─── STEP 8: Post-Cancel Access ────────────────────────────────────────────

it('allows AI generation during pending_cancellation period', function () {
    $user = User::factory()->withCredits(1000)->create([
        'subscription_status'  => 'pending_cancellation',
        'subscription_ends_at' => now()->addDays(15), // still within period
    ]);

    $this->mock(\App\Services\AI\AiService::class, function ($mock) {
        $mock->shouldReceive('complete')->once()->andReturn(
            new \App\DTOs\AI\CompletionResult('Content', 100, 400, 'gpt-4o-mini', 'openai')
        );
    });

    $response = $this->actingAs($user)
        ->postJson('/api/v1/generate/text', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'test'],
        ]);

    $response->assertStatus(200);
});

// ─── STEP 9: Expiry ────────────────────────────────────────────────────────

it('blocks generation after subscription_ends_at passes', function () {
    $user = User::factory()->withCredits(1000)->create([
        'subscription_status'  => 'expired',
        'subscription_ends_at' => now()->subDay(), // expired yesterday
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/generate/text', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'test'],
        ]);

    // Expired users fall back to free tier (no plan credits)
    // If free tier is disabled or 0 credits → 402
    $response->assertStatus(402);
});

// ─── STEP 10: Admin Reinstate ──────────────────────────────────────────────

it('admin can reinstate an expired subscription', function () {
    $admin = \App\Models\Admin::factory()->superAdmin()->create();
    $plan  = Plan::factory()->create(['credits_monthly' => 5000]);
    $user  = User::factory()->create([
        'subscription_status'  => 'expired',
        'subscription_ends_at' => now()->subDays(2),
        'plan_id'              => $plan->id,
        'credits'              => 0,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->postJson("/admin/users/{$user->ulid}/subscription/reinstate", [
            'ends_at'     => now()->addDays(30)->toDateString(),
            'add_credits' => true,
        ]);

    $response->assertStatus(200);

    $user->refresh();

    expect($user->subscription_status)->toBe('active');
    expect($user->credits)->toBe(5000.0);
    expect($user->subscription_ends_at->isFuture())->toBeTrue();
});

// ─── STEP 11: Terminate (Webhook) ─────────────────────────────────────────

it('immediately locks account on customer.subscription.deleted webhook', function () {
    $user = User::factory()->withCredits(1000)->create([
        'subscription_status'    => 'active',
        'stripe_subscription_id' => 'sub_test_999',
    ]);

    $payload = stripeWebhookPayload('customer.subscription.deleted', [
        'id'       => 'sub_test_999',
        'customer' => 'cus_test_456',
        'status'   => 'canceled',
    ]);

    $this->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => $this->generateStripeSignature($payload),
    ])->assertStatus(200);

    $user->refresh();

    expect($user->subscription_status)->toBe('terminated');
    expect($user->subscription_ends_at->isPast())->toBeTrue(); // ended immediately

    // After termination, generation is blocked
    $response = $this->actingAs($user)
        ->postJson('/api/v1/generate/text', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'test'],
        ]);

    $response->assertStatus(402);
});

// ─── STEP 12: Refund ───────────────────────────────────────────────────────

it('admin can issue refund and payment status updates', function () {
    $admin   = \App\Models\Admin::factory()->superAdmin()->create();
    $user    = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id'        => $user->id,
        'gateway'        => 'stripe',
        'transaction_id' => 'ch_test_abc',
        'amount'         => 29.00,
        'status'         => 'paid',
    ]);

    $this->mock(\App\Services\Payment\StripeService::class, function ($mock) {
        $mock->shouldReceive('refund')
            ->once()
            ->with('ch_test_abc', 29.00)
            ->andReturn(true);
    });

    $response = $this->actingAs($admin, 'admin')
        ->postJson("/admin/payments/{$payment->id}/refund", [
            'amount' => 29.00,
            'reason' => 'Customer request',
        ]);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('refunded');
    expect($payment->refunded_at)->not()->toBeNull();
});

// ─── STEP 13: License Gate ─────────────────────────────────────────────────

it('returns 403 on subscription endpoint with regular license', function () {
    setLicense(1); // regular license
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/plans')
        ->assertStatus(403);

    $this->actingAs($user)
        ->postJson('/api/v1/subscribe', ['plan_id' => 1])
        ->assertStatus(403);
});

// ─── STEP 14: Trial Period ─────────────────────────────────────────────────

it('sets subscription to trialing status with no immediate charge', function () {
    $user = User::factory()->create(['credits' => 0]);
    $plan = Plan::factory()->create([
        'credits_monthly' => 5000,
        'trial_days'      => 7,
        'price_monthly'   => 29,
    ]);

    $this->mock(\App\Services\Payment\StripeService::class, function ($mock) {
        $mock->shouldReceive('createSubscription')
            ->once()
            ->andReturn(new \App\DTOs\Payment\SubscriptionResult(
                success: true,
                subscriptionId: 'sub_trial_001',
                customerId: 'cus_trial_001',
                status: 'trialing',
            ));
    });

    $service = app(SubscriptionService::class);
    $service->subscribe($user, $plan, 'stripe', 'monthly', ['payment_method' => 'pm_card_visa']);

    $user->refresh();

    expect($user->subscription_status)->toBe('trialing');
    expect($user->trial_ends_at)->not()->toBeNull();
    expect($user->trial_ends_at->isFuture())->toBeTrue();
    expect($user->credits)->toBe(5000.0); // trial credits allocated immediately

    // No payment record for trial start
    expect(Payment::where('user_id', $user->id)->where('status', 'paid')->exists())->toBeFalse();
});

// ─── STEP 15: Trial End → Auto Charge ─────────────────────────────────────

it('converts trial to active on invoice.payment_succeeded after trial ends', function () {
    $plan = Plan::factory()->create(['credits_monthly' => 5000]);
    $user = User::factory()->create([
        'plan_id'                => $plan->id,
        'subscription_status'    => 'trialing',
        'trial_ends_at'          => now()->subHour(), // trial just ended
        'stripe_subscription_id' => 'sub_trial_001',
        'credits'                => 4200, // some used during trial
    ]);

    $payload = stripeWebhookPayload('invoice.payment_succeeded', [
        'subscription' => 'sub_trial_001',
        'customer'     => 'cus_trial_001',
        'amount_paid'  => 2900,
        'currency'     => 'usd',
        'id'           => 'in_trial_end_001',
        'billing_reason' => 'subscription_cycle',
    ]);

    $this->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => $this->generateStripeSignature($payload),
    ])->assertStatus(200);

    $user->refresh();

    expect($user->subscription_status)->toBe('active');
    expect($user->trial_ends_at)->toBeNull(); // trial cleared
    expect($user->credits)->toBe(5000.0); // refilled
});
```

---

## FILE 2: `tests/Feature/Payment/StripeWebhookTest.php`

Focused on webhook signature verification and all event types:

```php
<?php

use App\Models\User;
use App\Models\Plan;

// All webhook types MakeAI must handle:
it('rejects webhook with invalid signature', function () { ... });
it('accepts webhook with valid signature', function () { ... });
it('handles payment_intent.payment_failed event', function () { ... });
it('handles customer.subscription.updated event (plan change from Stripe dashboard)', function () { ... });
it('handles invoice.upcoming event (sends renewal reminder notification)', function () { ... });
it('returns 200 for unhandled event types (graceful no-op)', function () { ... });
it('is idempotent — processing same event twice does not double-credit', function () { ... });
```

**Important:** The idempotency test is critical. Each Stripe event has a unique `id`. The webhook
handler MUST check `processed_webhook_ids` cache/table before processing. If the event `id` was
already processed, return 200 with no side effects.

---

## FILE 3: `tests/Feature/Payment/CreditPurchaseTest.php`

One-time credit pack purchases (not subscription):

```php
<?php

// Test cases:
it('user can purchase a credit pack via stripe', function () { ... });
it('credits are added immediately after payment_intent.succeeded webhook', function () { ... });
it('duplicate webhook does not double-add credits', function () { ... });
it('failed payment does not add credits', function () { ... });
it('admin can manually add credits to any user', function () { ... });
it('credit transaction log is created for every credit change', function () { ... });
```

---

## FILE 4: `tests/Unit/TokenGuardTest.php`

Pure unit tests for `TokenGuard` — no HTTP, no DB:

```php
<?php

use App\Services\AI\TokenGuard;
use App\Exceptions\CreditLimitException;
use App\Exceptions\InsufficientCreditsException;

it('throws InsufficientCreditsException when user credits < estimated cost', function () { ... });
it('throws CreditLimitException with type=daily when daily limit exceeded', function () { ... });
it('throws CreditLimitException with type=monthly when monthly limit exceeded', function () { ... });
it('after() returns correct credit amount based on token counts', function () { ... });
it('after() updates credits_used_today and credits_used_month', function () { ... });
it('skips daily limit check when user_daily_credit_limit = 0', function () { ... });
```

---

## FILE 5: `tests/Feature/Payment/PayPalWebhookTest.php`

```php
<?php

// PayPal webhook events:
it('handles BILLING.SUBSCRIPTION.CANCELLED from PayPal', function () { ... });
it('handles PAYMENT.SALE.COMPLETED for one-time purchases', function () { ... });
it('handles BILLING.SUBSCRIPTION.RENEWED for monthly refill', function () { ... });
it('rejects PayPal webhook with invalid IPN verification', function () { ... });
```

---

## FILE 6: `database/factories/PlanFactory.php`

Create this factory if it does not exist:

```php
<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name'             => $this->faker->words(2, true) . ' Plan',
            'slug'             => $this->faker->unique()->slug(2),
            'description'      => $this->faker->sentence(),
            'price_monthly'    => $this->faker->randomElement([9, 19, 29, 49, 79, 99]),
            'price_yearly'     => fn(array $attr) => $attr['price_monthly'] * 10,
            'price_lifetime'   => fn(array $attr) => $attr['price_monthly'] * 30,
            'credits_monthly'  => $this->faker->randomElement([1000, 3000, 5000, 15000, 50000]),
            'features'         => [],
            'is_featured'      => false,
            'is_active'        => true,
            'trial_days'       => 0,
            'sort_order'       => 0,
        ];
    }

    public function withTrial(int $days = 7): static
    {
        return $this->state(['trial_days' => $days]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

---

## FILE 7: `database/factories/PaymentFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'plan_id'          => Plan::factory(),
            'gateway'          => $this->faker->randomElement(['stripe', 'paypal', 'paddle']),
            'transaction_id'   => 'txn_test_' . $this->faker->unique()->numerify('##########'),
            'amount'           => $this->faker->randomElement([9, 19, 29, 49, 79]),
            'currency'         => 'usd',
            'status'           => 'paid',
            'type'             => 'subscription',
            'invoice_number'   => 'INV-' . $this->faker->unique()->numerify('#####'),
            'paid_at'          => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'paid_at' => null]);
    }

    public function refunded(): static
    {
        return $this->state(['status' => 'refunded', 'refunded_at' => now()]);
    }
}
```

---

## FILE 8: `tests/TestCase.php` additions

Add this helper method to the base `TestCase` class:

```php
/**
 * Generate a valid Stripe webhook signature for testing.
 * Uses STRIPE_WEBHOOK_SECRET from .env.testing
 */
protected function generateStripeSignature(array $payload): string
{
    $secret    = config('services.stripe.webhook_secret', 'whsec_test_secret');
    $timestamp = time();
    $body      = json_encode($payload);
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return "t={$timestamp},v1={$signature}";
}
```

---

## FILE 9: `.env.testing`

Ensure these keys exist (add only if missing — do not overwrite existing values):

```env
# Testing overrides
APP_ENV=testing
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
SESSION_DRIVER=array
MAIL_MAILER=array

# Fake Stripe keys (never real keys in tests)
STRIPE_KEY=sk_test_fake_key_for_testing
STRIPE_SECRET=sk_test_fake_secret_for_testing
STRIPE_WEBHOOK_SECRET=whsec_test_secret_for_testing

# License: extended for most tests
LICENSE_TYPE=2
SUBSCRIPTIONS_ENABLED=true
```

---

## IMPLEMENTATION REQUIREMENTS

### 1. Webhook Idempotency

Every webhook handler MUST be idempotent. Implement a `processed_webhooks` table or Redis set:

```php
// In ProcessStripeWebhook job handle():
$cacheKey = "stripe_webhook_processed_{$payload['id']}";
if (Cache::has($cacheKey)) {
    return; // Already processed — skip silently
}
Cache::put($cacheKey, true, now()->addDays(7));

// ... process event ...
```

### 2. Credit Refill Logic

When `invoice.payment_succeeded` fires (renewal), the credit refill must:
1. Set `credits = plan->credits_monthly` (NOT add — replace)
2. Reset `credits_used_today = 0`
3. Reset `credits_used_month = 0`
4. Update `subscription_ends_at` to next billing period
5. Create a `credit_transactions` row with `type = 'renewal'`
6. Dispatch notification to user via `default` queue

### 3. Subscription Status State Machine

Valid transitions only:
```
null → trialing → active
null → active
active → pending_cancellation → expired
active → terminated (webhook only)
trialing → active (after trial payment)
trialing → expired (trial ended, no payment)
expired → active (admin reinstate)
any → terminated (admin force-terminate or webhook)
```

The `SubscriptionService` MUST enforce these transitions. Invalid transitions throw
`InvalidSubscriptionTransitionException`.

### 4. Factory `withPlan()` state

Ensure `UserFactory` has:

```php
public function withPlan(string $planSlug): static
{
    return $this->state(function (array $attributes) use ($planSlug) {
        $plan = Plan::firstOrCreate(['slug' => $planSlug], [
            'name'            => ucfirst($planSlug),
            'price_monthly'   => 29,
            'credits_monthly' => 5000,
            'is_active'       => true,
            'trial_days'      => 0,
        ]);
        return ['plan_id' => $plan->id, 'subscription_status' => 'active'];
    });
}
```

### 5. Run command

```bash
# All payment/subscription tests
./vendor/bin/pest tests/Feature/Payment/ tests/Unit/TokenGuardTest.php --parallel

# Only lifecycle
./vendor/bin/pest tests/Feature/Payment/SubscriptionLifecycleTest.php -v

# With coverage
./vendor/bin/pest tests/Feature/Payment/ --coverage --min=80
```

---

## CHECKLIST (verify each item after implementation)

- [ ] All 15 lifecycle steps have a passing test
- [ ] Webhook idempotency tested (duplicate event → no side effect)
- [ ] Stripe signature validation tested (invalid → 400, valid → 200)
- [ ] `isProAvailable()` gating tested for all subscription endpoints
- [ ] Trial → active transition tested via webhook
- [ ] Credit refill resets monthly/daily counters
- [ ] Admin reinstate test passes
- [ ] `PaymentFactory` and `PlanFactory` exist and work
- [ ] `UserFactory::withPlan()` state works
- [ ] `UserFactory::withCredits()` state works
- [ ] `.env.testing` has all required keys
- [ ] `generateStripeSignature()` helper in TestCase
- [ ] Zero real API calls during test run (all mocked)
- [ ] `./vendor/bin/pest tests/Feature/Payment/ --parallel` passes with 0 failures
- [ ] PSR-12 compliant (`./vendor/bin/pint tests/` passes)

---

## NOTES FOR DEEPSEEK

1. **Never use `LLPhant`** — AI calls use `laravel/ai` SDK via `AiService`. If you see `LLPhant`
   anywhere in existing code, treat it as already removed.

2. **Never hardcode "MakeAI"** — use `settings('app_name')` in any user-facing string.

3. **`ai_tools` table** — NOT `ai_templates`. The model is `AiTool`, not `AiTemplate`.

4. **`addon_setting('slug', 'key')`** — for addon settings, not `settings('addon_XXX')`.

5. **Mocking priority**: Always mock at the service layer (`StripeService`, `AiService`),
   never at the HTTP client layer. This ensures the service interface contract is tested.

6. **Queue assertions**: Use `Queue::fake()` only when testing that a job was dispatched.
   For testing job behavior itself, use `QUEUE_CONNECTION=sync` so jobs run synchronously.

7. **Database**: Tests use SQLite in-memory. Never reference MySQL-specific syntax.
   Use `RefreshDatabase` trait on all Feature tests.

8. **Admin model**: `\App\Models\Admin` with guard `'admin'`. Use `actingAs($admin, 'admin')`
   for admin route tests.

