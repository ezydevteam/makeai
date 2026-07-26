<?php

namespace Tests\Feature;

use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DemoBillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_showcase_billing_page_has_a_payment_history(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        // demo:reset re-runs the seeder; the history must not stack up.
        $this->seed(DemoSeeder::class);

        settings_set('license_type', 2, 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'premium');

        $user = User::where('email', config('demo.user_email'))->firstOrFail();
        $payments = Payment::where('user_id', $user->id)->get();

        $this->assertCount(7, $payments, 're-seeding must not duplicate the history');

        // Every state the Billing page styles differently is represented.
        foreach (['completed', 'failed', 'refunded', 'pending'] as $status) {
            $this->assertTrue(
                $payments->contains(fn (Payment $payment) => $payment->status === $status),
                "no {$status} payment — that badge never renders on the demo"
            );
        }

        foreach (['subscription', 'credit_topup', 'one_time'] as $type) {
            $this->assertTrue($payments->contains(fn (Payment $payment) => $payment->type === $type));
        }

        // Spread over a year, not stacked on today (created_at is not fillable).
        $this->assertGreaterThanOrEqual(6, $payments->pluck('created_at')->map->format('Y-m')->unique()->count());
        $this->assertTrue($payments->min('created_at')->lt(now()->subMonths(10)));

        // The newest subscription charge must reconcile with the active subscription row,
        // or the page shows a plan whose renewal was never paid for.
        $subscription = GatewaySubscription::where('user_id', $user->id)->firstOrFail();
        $latestSubscriptionPayment = $payments
            ->where('type', 'subscription')
            ->where('status', 'completed')
            ->sortByDesc('created_at')
            ->first();

        $this->assertEqualsWithDelta(
            (float) $subscription->amount,
            (float) $latestSubscriptionPayment->amount,
            0.01,
            'the latest subscription charge does not match the active subscription amount'
        );
        $this->assertSame(
            $subscription->current_period_start->format('Y-m'),
            $latestSubscriptionPayment->created_at->format('Y-m'),
            'the renewal charge is in a different month than the subscription period it started'
        );

        // And the page renders them.
        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/billing')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(7, $props['payments']);
        $this->assertNotNull($props['payments'][0]['created_at']);
    }
}
