<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;

/**
 * CreditPricingService — anchors AiModel::credits_per_1k to real provider cost
 * plus a configurable markup, converted to credits via credit_price_per_unit.
 *
 * Currency model (robust across stores that don't sell in USD):
 *   - Provider cost (cost_input_1k / cost_output_1k) is always in USD — that is how
 *     OpenAI/Anthropic/etc. bill, and it matches global_daily_ai_budget_usd.
 *   - credit_price_per_unit and plan/top-up prices are in the store's base currency
 *     (pricing_currency_code) — that is what the buyer sells in.
 *   - So the USD cost is converted to the base currency (via stored FX rates) BEFORE
 *     the markup/credit division. On a USD store this is a no-op; on an INR store it
 *     stops the derivation from dividing USD cost by an INR credit price.
 *
 *
 * Billing is unchanged — TokenGuard::calculateCredits still reads credits_per_1k.
 * This service only decides what value that column should hold.
 */
class CreditPricingService
{
    /**
     * Weight applied to cost_input_1k in the blended cost.
     *
     * Fixed internal constant (not a buyer-facing setting) — kept simple by
     * design. Generation is output-heavy, so output is weighted higher.
     */
    private const INPUT_WEIGHT = 0.25;

    /**
     * Weight applied to cost_output_1k in the blended cost.
     */
    private const OUTPUT_WEIGHT = 0.75;

    /**
     * Derive the credits_per_1k value a model *should* have, from its real
     * provider cost, the configured markup, and the credit-to-USD anchor.
     *
     * Formula (plan §1):
     *   blended = 0.25 * cost_input_1k + 0.75 * cost_output_1k
     *   if blended <= 0: 0                          // local/free models stay free
     *   else: max(floor, ceil((blended * markup) / credit_price_per_unit))
     */
    public static function deriveCreditsPer1k(AiModel $model): int
    {
        $pricePerCredit = (float) settings('credit_price_per_unit', 0.01);

        if ($pricePerCredit <= 0) {
            // Guard against a misconfigured anchor — leave the model unchanged.
            return (int) $model->credits_per_1k;
        }

        $markup = (float) settings('ai_credit_markup', 3);
        $floor = (int) settings('ai_credit_min_per_1k', 1);

        $blendedUsd = self::INPUT_WEIGHT * (float) $model->cost_input_1k
            + self::OUTPUT_WEIGHT * (float) $model->cost_output_1k;

        if ($blendedUsd <= 0) {
            // Local/free models stay free.
            return 0;
        }

        // Convert USD provider cost → store base currency, then apply markup.
        $amountToCharge = self::usdToBaseCurrency($blendedUsd) * $markup;
        // round() before ceil() — float error (e.g. 15.000000000000002) must not
        // push a clean division up a whole credit.
        $credits = (int) ceil(round($amountToCharge / $pricePerCredit, 6));

        return max($floor, $credits);
    }

    /**
     * Derive the credits to charge for ONE media unit (image / audio clip /
     * transcription) from its real USD provider cost, using the same markup and
     * credit anchor as chat models.
     *
     *   credits = max(floor, ceil((costPerUnitUsd * markup) / credit_price_per_unit))
     *
     * Returns 0 for a free (or unpriced) unit, or when the anchor is
     * misconfigured — the caller then falls back to a flat default.
     */
    public static function deriveCreditsPerUnit(float $costPerUnitUsd): int
    {
        $pricePerCredit = (float) settings('credit_price_per_unit', 0.01);

        if ($pricePerCredit <= 0 || $costPerUnitUsd <= 0) {
            return 0;
        }

        $markup = (float) settings('ai_credit_markup', 3);
        $floor = (int) settings('ai_credit_min_per_1k', 1);

        // Convert USD provider cost → store base currency, then apply markup.
        $amountToCharge = self::usdToBaseCurrency($costPerUnitUsd) * $markup;
        // round() before ceil() — guard against float error on clean divisions.
        $credits = (int) ceil(round($amountToCharge / $pricePerCredit, 6));

        return max($floor, $credits);
    }

    /**
     * Convert a USD amount into the store's base selling currency
     * (pricing_currency_code) using stored FX rates. No-op on a USD store; safe
     * fallback (returns the USD amount unchanged) when rates are unavailable.
     */
    private static function usdToBaseCurrency(float $usd): float
    {
        $base = base_currency();

        if ($base === 'USD' || $base === '') {
            return $usd;
        }

        return convert_currency($usd, 'USD', $base);
    }

    /**
     * Preview derived credits for every chat model, without writing anything.
     *
     * @return array<int, array{id: int, name: string, provider: string, type: string, current: int, derived: int, auto: bool}>
     */
    public static function preview(): array
    {
        $models = AiModel::ofType('chat')
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        return $models->map(fn (AiModel $model) => [
            'id' => $model->id,
            'name' => $model->name,
            'provider' => $model->provider,
            'type' => $model->type,
            'current' => (int) $model->credits_per_1k,
            'derived' => self::deriveCreditsPer1k($model),
            'auto' => (bool) $model->credits_auto,
        ])->all();
    }

    /**
     * Recalculate and persist credits_per_1k for every chat model that has
     * credits_auto = true. Manually-priced (credits_auto = false) and
     * non-chat rows are left untouched.
     *
     * @return int number of rows actually changed
     */
    public static function recalculateAll(): int
    {
        return DB::transaction(function (): int {
            $changed = 0;

            $models = AiModel::ofType('chat')
                ->where('credits_auto', true)
                ->get();

            foreach ($models as $model) {
                $derived = self::deriveCreditsPer1k($model);

                if ($derived !== (int) $model->credits_per_1k) {
                    $model->credits_per_1k = $derived;
                    $model->save();
                    $changed++;
                }
            }

            return $changed;
        });
    }
}
