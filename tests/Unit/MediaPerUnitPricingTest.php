<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Services\AI\TokenGuard;
use Tests\TestCase;

/**
 * Per-unit media pricing: audio/image/transcription models charge from
 * meta.credits_per_unit (manual override) → meta.cost_per_unit (USD, derived) →
 * config default. Admin sets these on the provider page.
 */
class MediaPerUnitPricingTest extends TestCase
{
    private function audioModel(array $meta = []): AiModel
    {
        return AiModel::create([
            'slug' => 'eleven-'.uniqid(),
            'name' => 'ElevenLabs Test',
            'provider' => 'eleven',
            'type' => 'audio',
            'is_active' => true,
            'cost_input_1k' => 0, 'cost_output_1k' => 0,
            'credits_per_1k' => 0, 'credits_auto' => false, 'max_tokens' => 0,
            'meta' => $meta,
        ]);
    }

    public function test_manual_credits_per_unit_wins(): void
    {
        $model = $this->audioModel(['credits_per_unit' => 5]);

        $this->assertSame(15.0, TokenGuard::mediaCreditCost('audio', $model->slug, 3));
    }

    public function test_cost_per_unit_derives_credits_via_markup(): void
    {
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_markup', 3, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');

        // $0.05 × 3 / 0.01 = 15 credits per unit.
        $model = $this->audioModel(['cost_per_unit' => 0.05]);

        $this->assertSame(15.0, TokenGuard::mediaCreditCost('audio', $model->slug, 1));
    }

    public function test_falls_back_to_config_default_when_meta_empty(): void
    {
        config(['ai.media_costs.audio' => 0, 'ai.media_credits.audio' => 2]);
        $model = $this->audioModel([]); // no per-unit meta

        $this->assertSame(2.0, TokenGuard::mediaCreditCost('audio', $model->slug, 1));
    }

    public function test_two_models_can_charge_differently(): void
    {
        $premium = $this->audioModel(['credits_per_unit' => 2]);
        $fast = $this->audioModel(['credits_per_unit' => 1]);

        $this->assertSame(2.0, TokenGuard::mediaCreditCost('audio', $premium->slug, 1));
        $this->assertSame(1.0, TokenGuard::mediaCreditCost('audio', $fast->slug, 1));
    }
}
