<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AiModel;
use App\Models\Currency;
use App\Services\AI\CreditPricingService;
use Tests\TestCase;

/**
 * Ref: plan-credit-usd-anchor.md §1 (formula), §4 Phase 3 item 9.
 *
 * Follows tests/Unit/MediaCreditCostTest.php harness conventions — no extra
 * traits needed, Tests\TestCase already applies RefreshDatabase globally.
 */
class CreditPricingServiceTest extends TestCase
{
    public function test_derive_matches_formula_for_a_chat_model(): void
    {
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_markup', 3, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');

        $model = AiModel::create([
            'slug' => 'gpt-4o',
            'name' => 'GPT-4o',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.0025,
            'cost_output_1k' => 0.010,
            'credits_per_1k' => 15,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        // blended = 0.25*0.0025 + 0.75*0.010 = 0.008125
        // usdToCharge = 0.008125 * 3 = 0.024375
        // credits = ceil(0.024375 / 0.01) = ceil(2.4375) = 3
        $this->assertSame(3, CreditPricingService::deriveCreditsPer1k($model));
    }

    public function test_derivation_converts_usd_cost_to_non_usd_base_currency(): void
    {
        // Store sells in INR (₹80 = $1); credits are priced in INR.
        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1]);
        Currency::create(['code' => 'INR', 'symbol' => '₹', 'name' => 'Indian Rupee', 'exchange_rate' => 80]);
        settings_set('default_currency', 'INR', 'string', 'general'); // unified base currency
        settings_set('credit_price_per_unit', 0.80, 'string', 'billing'); // ₹0.80 per credit
        settings_set('ai_credit_markup', 3, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');

        $model = AiModel::create([
            'slug' => 'gpt-4o-inr',
            'name' => 'GPT-4o',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.0025,
            'cost_output_1k' => 0.010,
            'credits_per_1k' => 1,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        // blended USD = 0.008125; × markup 3 = $0.024375; × 80 = ₹1.95; ÷ ₹0.80 = 2.44 → 3.
        // (Without USD→INR conversion this would divide $0.024375 by ₹0.80 = 0.03 → floor 1.)
        $this->assertSame(3, CreditPricingService::deriveCreditsPer1k($model));
    }

    public function test_free_model_derives_zero(): void
    {
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_markup', 3, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');

        $model = AiModel::create([
            'slug' => 'local-llama',
            'name' => 'Local Llama',
            'provider' => 'ollama',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0,
            'cost_output_1k' => 0,
            'credits_per_1k' => 0,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        // blended <= 0 => free models stay free; the floor does NOT apply here.
        $this->assertSame(0, CreditPricingService::deriveCreditsPer1k($model));
    }

    public function test_floor_applies_to_cheap_paid_model(): void
    {
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_markup', 1, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 2, 'integer', 'ai');

        $model = AiModel::create([
            'slug' => 'super-cheap-model',
            'name' => 'Super Cheap Model',
            'provider' => 'test-provider',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0,
            'cost_output_1k' => 0.0001,
            'credits_per_1k' => 1,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        // blended = 0.75*0.0001 = 0.000075; usdToCharge = 0.000075 * 1 = 0.000075
        // raw credits = ceil(0.000075 / 0.01) = ceil(0.0075) = 1, but paid so floor(2) wins.
        $this->assertSame(2, CreditPricingService::deriveCreditsPer1k($model));
    }

    public function test_markup_scales_linearly(): void
    {
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');

        $model = AiModel::create([
            'slug' => 'gpt-4o-markup-test',
            'name' => 'GPT-4o Markup Test',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.0025,
            'cost_output_1k' => 0.010,
            'credits_per_1k' => 1,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        settings_set('ai_credit_markup', 2, 'string', 'ai');
        $atMarkup2 = CreditPricingService::deriveCreditsPer1k($model);

        settings_set('ai_credit_markup', 4, 'string', 'ai');
        $atMarkup4 = CreditPricingService::deriveCreditsPer1k($model);

        // Robust to ceil()/floor() rounding — a higher markup must never derive
        // a lower (or equal) credit cost for the same underlying provider cost.
        $this->assertGreaterThan($atMarkup2, $atMarkup4);
    }

    public function test_recalculate_all_skips_manual_and_non_chat(): void
    {
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        settings_set('ai_credit_markup', 3, 'string', 'ai');
        settings_set('ai_credit_min_per_1k', 1, 'integer', 'ai');

        // (a) chat + auto => should be recalculated (derives to 3, per test #1's math).
        $autoModel = AiModel::create([
            'slug' => 'auto-chat-model',
            'name' => 'Auto Chat Model',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.0025,
            'cost_output_1k' => 0.010,
            'credits_per_1k' => 999,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        // (b) chat + manual => must be left untouched.
        $manualModel = AiModel::create([
            'slug' => 'manual-chat-model',
            'name' => 'Manual Chat Model',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.0025,
            'cost_output_1k' => 0.010,
            'credits_per_1k' => 999,
            'credits_auto' => false,
            'max_tokens' => 4096,
        ]);

        // (c) non-chat + auto => must be left untouched (recalculateAll only touches type=chat).
        $embeddingModel = AiModel::create([
            'slug' => 'embedding-model',
            'name' => 'Embedding Model',
            'provider' => 'openai',
            'type' => 'embedding',
            'is_active' => true,
            'cost_input_1k' => 0.0025,
            'cost_output_1k' => 0.010,
            'credits_per_1k' => 999,
            'credits_auto' => true,
            'max_tokens' => 4096,
        ]);

        $changed = CreditPricingService::recalculateAll();

        $this->assertSame(1, $changed);
        $this->assertSame(3, $autoModel->fresh()->credits_per_1k);
        $this->assertSame(999, $manualModel->fresh()->credits_per_1k);
        $this->assertSame(999, $embeddingModel->fresh()->credits_per_1k);
    }

    /**
     * Regression for the max_tokens=0 bugfix (plan §4 Phase 2 item 5): the
     * validation rule on AiManagementController::updateModel() was changed
     * from `min:1` to `min:0` because audio/image rows legitimately seed
     * max_tokens = 0. Follows tests/Feature/LicenseServiceTest.php's pattern
     * of building a super-admin (bypasses `admin.permission:ai.tools`) and
     * posting to a named admin route.
     */
    public function test_update_model_accepts_zero_max_tokens(): void
    {
        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');

        $role = AdminRole::firstOrCreate(
            ['slug' => $superSlug],
            ['name' => 'Super Admin', 'is_system' => true]
        );

        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'credit-pricing-test@makeai.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $model = AiModel::create([
            'slug' => 'zero-max-tokens-model',
            'name' => 'Zero Max Tokens Model',
            'provider' => 'elevenlabs',
            'type' => 'audio',
            'is_active' => true,
            'cost_input_1k' => 0,
            'cost_output_1k' => 0,
            'credits_per_1k' => 1,
            'credits_auto' => false,
            'max_tokens' => 0,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.ai.model.update', $model), [
                'is_active' => true,
                'cost_input_1k' => 0,
                'cost_output_1k' => 0,
                'credits_per_1k' => 1,
                'max_tokens' => 0,
                'credits_auto' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, $model->fresh()->max_tokens);
    }
}
