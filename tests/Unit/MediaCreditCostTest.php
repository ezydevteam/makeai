<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Services\AI\TokenGuard;
use Tests\TestCase;

/**
 * Findings #6/#7 — media is priced per unit (per image/clip), never via the
 * meaningless per-token fallback that billed ~1 credit on unconfigured installs.
 *
 * Media credits are now ANCHORED to real USD cost × the global AI markup
 * (see CreditPricingService::deriveCreditsPerUnit), mirroring chat models.
 * Precedence: model meta.credits_per_unit (manual) → USD cost anchor
 * (model meta.cost_per_unit, else config ai.media_costs) → flat ai.media_credits.
 */
class MediaCreditCostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Anchor: 1 credit = $0.01, charge 3× provider cost, floor 1.
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_markup', 3, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');
    }

    public function test_derives_credits_from_config_media_cost(): void
    {
        config(['ai.media_costs' => ['image' => 0.04, 'audio' => 0.05, 'transcription' => 0.02]]);

        // image: ceil(0.04 * 3 / 0.01) = 12 credits per unit.
        $this->assertSame(12.0, TokenGuard::mediaCreditCost('image', null, 1));
        $this->assertSame(36.0, TokenGuard::mediaCreditCost('image', null, 3));
        // audio: ceil(0.05 * 3 / 0.01) = 15.
        $this->assertSame(15.0, TokenGuard::mediaCreditCost('audio', null, 1));
        // transcription: ceil(0.02 * 3 / 0.01) = 6.
        $this->assertSame(6.0, TokenGuard::mediaCreditCost('transcription', null, 1));
    }

    public function test_model_meta_credits_per_unit_is_a_hard_manual_override(): void
    {
        config(['ai.media_costs.image' => 0.04]);

        AiModel::create([
            'slug' => 'dall-e-3',
            'name' => 'DALL-E 3',
            'provider' => 'openai',
            'is_active' => true,
            'cost_input_1k' => 0,
            'cost_output_1k' => 0,
            'credits_per_1k' => 1,
            'type' => 'image',
            'meta' => ['credits_per_unit' => 7],
        ]);

        // Manual per-unit override wins over the cost anchor.
        $this->assertSame(14.0, TokenGuard::mediaCreditCost('image', 'dall-e-3', 2));
    }

    public function test_model_meta_cost_per_unit_anchors_credits(): void
    {
        config(['ai.media_costs.image' => 0.04]);

        AiModel::create([
            'slug' => 'flux-pro',
            'name' => 'Flux Pro',
            'provider' => 'replicate',
            'is_active' => true,
            'cost_input_1k' => 0,
            'cost_output_1k' => 0,
            'credits_per_1k' => 1,
            'type' => 'image',
            'meta' => ['cost_per_unit' => 0.10], // pricier model overrides the config default
        ]);

        // ceil(0.10 * 3 / 0.01) = 30 credits per image.
        $this->assertSame(30.0, TokenGuard::mediaCreditCost('image', 'flux-pro', 1));
    }

    public function test_flat_fallback_when_no_usd_cost_configured(): void
    {
        // No USD cost for this type => fall back to the legacy flat credit value.
        config(['ai.media_costs.image' => 0]);
        config(['ai.media_credits.image' => 4]);

        $this->assertSame(4.0, TokenGuard::mediaCreditCost('image', null, 1));
    }

    public function test_unknown_media_model_uses_config_cost_not_one_credit(): void
    {
        config(['ai.media_costs.image' => 0.04]);

        // No AiModel row for this returned slug — the old token path billed ~1 credit.
        $this->assertSame(12.0, TokenGuard::mediaCreditCost('image', 'dall-e-3-2099', 1));
    }
}
