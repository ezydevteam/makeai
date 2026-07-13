<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\AI\TokenGuard;
use Tests\TestCase;

/**
 * Admin-chosen "New user gets" (registration_default_plan) + reset-style monthly
 * refresh of free-plan allowances.
 */
class RegistrationDefaultPlanTest extends TestCase
{
    private function plan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Free', 'slug' => 'free-'.uniqid(),
            'price_monthly' => 0, 'price_yearly' => 0,
            'vat_percentage' => 0, 'credits' => 2000,
            'is_active' => true, 'is_free' => true, 'sort_order' => 1,
        ], $overrides));
    }

    public function test_none_keeps_default_signup_wallet(): void
    {
        settings_set('registration_default_plan', 'none', 'string', 'pricing');
        settings_set('default_credits_new_user', '100', 'integer', 'ai');

        $user = User::create(['name' => 'A', 'email' => 'a'.uniqid().'@x.com', 'password' => 'secret123']);
        $user->applyRegistrationDefault();

        $fresh = $user->fresh();
        $this->assertNull($fresh->plan_id);
        $this->assertEquals(100.0, (float) $fresh->credits);
    }

    public function test_custom_keeps_default_and_assigns_no_plan(): void
    {
        settings_set('registration_default_plan', 'custom', 'string', 'pricing');
        settings_set('default_credits_new_user', '100', 'integer', 'ai');

        $user = User::create(['name' => 'B', 'email' => 'b'.uniqid().'@x.com', 'password' => 'secret123']);
        $user->applyRegistrationDefault();

        $this->assertNull($user->fresh()->plan_id);
    }

    public function test_plan_choice_assigns_plan_and_grants_credits(): void
    {
        $plan = $this->plan(['credits' => 2000]);
        settings_set('registration_default_plan', (string) $plan->id, 'string', 'pricing');

        $user = User::create(['name' => 'C', 'email' => 'c'.uniqid().'@x.com', 'password' => 'secret123']);
        $user->applyRegistrationDefault();

        $fresh = $user->fresh();
        $this->assertSame($plan->id, $fresh->plan_id);
        $this->assertEquals(2000.0, (float) $fresh->credits);
    }

    public function test_invalid_plan_choice_falls_back_safely(): void
    {
        settings_set('registration_default_plan', '999999', 'string', 'pricing');
        settings_set('default_credits_new_user', '100', 'integer', 'ai');

        $user = User::create(['name' => 'D', 'email' => 'd'.uniqid().'@x.com', 'password' => 'secret123']);
        $user->applyRegistrationDefault();

        $fresh = $user->fresh();
        $this->assertNull($fresh->plan_id);
        $this->assertEquals(100.0, (float) $fresh->credits);
    }

    public function test_monthly_refresh_resets_spent_free_allowance_but_preserves_topups(): void
    {
        $plan = $this->plan(['credits' => 2000]);

        // Spent-down free user → should refresh up to 2000.
        $spent = User::create(['name' => 'E', 'email' => 'e'.uniqid().'@x.com', 'password' => 'secret123', 'plan_id' => $plan->id, 'credits' => 150]);
        // User with top-ups above the allowance → must NOT be reduced.
        $topup = User::create(['name' => 'F', 'email' => 'f'.uniqid().'@x.com', 'password' => 'secret123', 'plan_id' => $plan->id, 'credits' => 5000]);

        // Force a month rollover: seed a stale last-reset marker.
        settings_set('credits_month_last_reset', '2000-01', 'string', 'ai');
        TokenGuard::resetDailyCounters();

        $this->assertEquals(2000.0, (float) $spent->fresh()->credits, 'Spent free allowance refreshed to plan credits.');
        $this->assertEquals(5000.0, (float) $topup->fresh()->credits, 'Balance above the allowance (top-ups) preserved.');
    }
}
