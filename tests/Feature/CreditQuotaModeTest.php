<?php

namespace Tests\Feature;

use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\User;
use App\Services\AI\TokenGuard;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Plan: plan-regular-license-credits.md §1, §3, §4, §5.
 *
 * Regular license (no billing) puts TokenGuard into "quota mode"
 * (`credit_quota_mode() === true`): credits stop being a spend-down wallet and
 * become a resetting daily/monthly allowance, so a drained wallet never dead-ends
 * a user. Extended license + billing on ("metered mode") keeps today's wallet
 * behavior unchanged. Guests get a per-IP daily allowance in both modes.
 */
class CreditQuotaModeTest extends TestCase
{
    private function seedChatModel(): AiModel
    {
        return AiModel::create([
            'slug' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.00015,
            'cost_output_1k' => 0.0006,
            'credits_per_1k' => 1,
            'credits_auto' => true,
            'max_tokens' => 16384,
        ]);
    }

    private function useQuotaMode(): void
    {
        // Regular license (license_type=1) is the default when unset; set it
        // explicitly so the test doesn't depend on migration defaults.
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');
    }

    private function useMeteredMode(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
    }

    public function test_quota_mode_does_not_drain_wallet_but_tracks_usage(): void
    {
        $this->useQuotaMode();
        $this->seedChatModel();

        $user = User::factory()->create(['credits' => 5, 'is_active' => true]);

        // 1000 input + 1000 output tokens @ credits_per_1k=1 => round(2000*1/1000,2)=2.0
        $charged = TokenGuard::after($user, 1000, 1000, 'gpt-4o-mini', 'openai', 'chat', [], true, true);

        $this->assertSame(2.0, $charged);

        $fresh = $user->fresh();
        $this->assertEquals(5.0, (float) $fresh->credits, 'Wallet balance must stay untouched in quota mode.');
        $this->assertEquals(2.0, (float) $fresh->credits_used_today);
        $this->assertEquals(2.0, (float) $fresh->credits_used_month);

        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $user->id,
            'model' => 'gpt-4o-mini',
            'credits_used' => 2,
        ]);
    }

    public function test_quota_mode_lets_zero_balance_user_generate(): void
    {
        $this->useQuotaMode();
        $this->seedChatModel();

        // No daily allowance configured — the balance wall is the only thing
        // that could block this user, and quota mode must skip it.
        settings_set('user_daily_credit_limit', '0', 'string', 'ai');

        $user = User::factory()->create(['credits' => 0, 'is_active' => true]);

        try {
            TokenGuard::before($user, null, 'gpt-4o-mini');
        } catch (\Throwable $e) {
            $this->fail('before() must not throw for a zero-balance user in quota mode: '.$e::class.' — '.$e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_quota_mode_enforces_daily_allowance(): void
    {
        $this->useQuotaMode();
        $this->seedChatModel();

        settings_set('user_daily_credit_limit', '5', 'string', 'ai');

        $user = User::factory()->create([
            'credits' => 0,
            'credits_used_today' => 5,
            'is_active' => true,
        ]);

        $this->expectException(CreditLimitException::class);

        TokenGuard::before($user, null, 'gpt-4o-mini');
    }

    public function test_metered_mode_still_enforces_balance_wall(): void
    {
        $this->useMeteredMode();
        $this->seedChatModel();

        $blocked = User::factory()->create(['credits' => 0, 'is_active' => true]);

        try {
            TokenGuard::before($blocked, null, 'gpt-4o-mini');
            $this->fail('before() must throw InsufficientCreditsException in metered mode with 0 balance.');
        } catch (InsufficientCreditsException $e) {
            $this->assertTrue(true);
        }

        // after() still drains the wallet in metered mode.
        $payer = User::factory()->create(['credits' => 5, 'is_active' => true]);
        $charged = TokenGuard::after($payer, 1000, 1000, 'gpt-4o-mini', 'openai', 'chat', [], true, true);

        $this->assertSame(2.0, $charged);
        $this->assertEquals(3.0, (float) $payer->fresh()->credits);
    }

    public function test_charge_credits_meters_in_quota_and_drains_in_metered(): void
    {
        // Quota mode: never fails on balance; wallet untouched; allowance advances.
        $this->useQuotaMode();
        $quotaUser = User::factory()->create(['credits' => 3, 'is_active' => true]);

        $this->assertTrue($quotaUser->chargeCredits(5, 'RAG ingestion: test', []));
        $freshQuota = $quotaUser->fresh();
        $this->assertEquals(3.0, (float) $freshQuota->credits, 'Wallet untouched in quota mode.');
        $this->assertEquals(5.0, (float) $freshQuota->credits_used_today);

        // Metered mode: drains the wallet, and fails when the balance is short.
        $this->useMeteredMode();
        $meteredUser = User::factory()->create(['credits' => 3, 'is_active' => true]);

        $this->assertFalse($meteredUser->chargeCredits(5, 'RAG ingestion: test', []), 'Insufficient balance must fail in metered mode.');
        $this->assertEquals(3.0, (float) $meteredUser->fresh()->credits);

        $this->assertTrue($meteredUser->chargeCredits(2, 'RAG ingestion: test', []));
        $this->assertEquals(1.0, (float) $meteredUser->fresh()->credits, 'Wallet drained in metered mode.');
    }

    public function test_refund_winds_back_allowance_in_quota_and_wallet_in_metered(): void
    {
        // Quota mode: refund reverses the consumed allowance, wallet untouched.
        $this->useQuotaMode();
        $quotaUser = User::factory()->create(['credits' => 3, 'credits_used_today' => 5, 'credits_used_month' => 5, 'is_active' => true]);

        $quotaUser->refundCredits(2, 'Failed run refund');
        $fresh = $quotaUser->fresh();
        $this->assertEquals(3.0, (float) $fresh->credits, 'Wallet untouched by a quota refund.');
        $this->assertEquals(3.0, (float) $fresh->credits_used_today, 'Allowance wound back.');
        $this->assertEquals(3.0, (float) $fresh->credits_used_month);

        // Metered mode: refund returns credits to the wallet.
        $this->useMeteredMode();
        $meteredUser = User::factory()->create(['credits' => 3, 'is_active' => true]);

        $meteredUser->refundCredits(2, 'Failed run refund');
        $this->assertEquals(5.0, (float) $meteredUser->fresh()->credits, 'Wallet credited by a metered refund.');
    }

    public function test_guest_daily_limit_blocks_and_tracks_by_ip(): void
    {
        $this->useQuotaMode();
        $this->seedChatModel();

        settings_set('guest_daily_credit_limit', '3', 'integer', 'ai');

        // Pin a deterministic IP so request()->ip() resolves consistently for
        // both after() (increments guest usage) and before() (checks the gate).
        $this->app['request']->server->set('REMOTE_ADDR', '1.2.3.4');

        $this->assertSame(0.0, TokenGuard::guestUsedToday('1.2.3.4'));

        // Each after() call charges 2 credits (1000+1000 tokens @ credits_per_1k=1).
        TokenGuard::after(null, 1000, 1000, 'gpt-4o-mini', 'openai', 'chat', [], true, true);
        $this->assertSame(2.0, TokenGuard::guestUsedToday('1.2.3.4'));

        TokenGuard::after(null, 1000, 1000, 'gpt-4o-mini', 'openai', 'chat', [], true, true);
        $this->assertSame(4.0, TokenGuard::guestUsedToday('1.2.3.4'));

        // Used (4) + estimated cost (0.7 for a template-less chat estimate) > limit (3).
        $this->expectException(CreditLimitException::class);

        TokenGuard::before(null, null, 'gpt-4o-mini');
    }
}
