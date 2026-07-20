<?php

namespace Tests\Feature;

use App\Exceptions\AI\InsufficientCreditsException;
use App\Models\User;
use App\Services\AI\TokenGuard;
use Tests\TestCase;

/**
 * Findings #6/#7 — media generation charges the real per-unit credit cost and
 * gates the pre-flight balance check on that cost (not a ~0.5-credit text guess).
 */
class MediaCreditBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These assert METERED (wallet) behavior — the balance wall + drain. Enable
        // metered mode (Extended license + billing), since a licence-less test env
        // defaults to quota mode where the wallet is never a limiter.
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
    }

    public function test_after_media_charges_per_unit_credits(): void
    {
        // Pin the flat fallback (no USD cost) so this test targets the per-unit
        // charge mechanics, not the cost-anchor math (covered by MediaCreditCostTest).
        config(['ai.media_costs.image' => 0, 'ai.media_credits.image' => 4]);
        $user = User::factory()->create(['credits' => 100, 'is_active' => true]);

        $charged = TokenGuard::afterMedia($user, 'image', null, 'openai', 2, ['tool_slug' => 'image_generator']);

        $this->assertSame(8.0, $charged, 'Two images at 4 credits each = 8.');
        $this->assertEquals(92.0, (float) $user->fresh()->credits);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $user->id,
            'type' => 'image',
            'credits_used' => 8,
        ]);
    }

    public function test_before_media_blocks_when_balance_below_media_cost(): void
    {
        config(['ai.media_costs.image' => 0, 'ai.media_credits.image' => 4]);
        $user = User::factory()->create(['credits' => 2, 'is_active' => true]);

        $this->expectException(InsufficientCreditsException::class);

        TokenGuard::beforeMedia($user, 'image', null, 1);
    }
}
