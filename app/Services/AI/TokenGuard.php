<?php

namespace App\Services\AI;

use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\GlobalBudgetExceededException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Models\AiModel;
use App\Models\AiTemplate;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TokenGuard — enforces credit/token limits before AI requests
 * and records usage after completion.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.13
 *
 * CAVEMAN say: "No credits? No fire! 🔥"
 * Every AI request MUST pass through TokenGuard before hitting the provider.
 */
class TokenGuard
{
    /**
     * PRE-FLIGHT CHECK — run BEFORE sending request to AI provider.
     *
     * @throws CreditLimitException
     * @throws InsufficientCreditsException
     * @throws GlobalBudgetExceededException
     */
    public static function before(?User $user, ?AiTemplate $template = null, ?string $model = null): void
    {
        // 0. Check active status
        if ($user) {
            if (! $user->is_active) {
                throw new RuntimeException(translate('Your account has been deactivated.'));
            }

            if ($user->is_banned) {
                throw new RuntimeException(translate('Your account has been suspended.'));
            }
        }

        // 1. Estimate cost
        $estimatedCost = self::estimateCreditCost($template, $model);

        // User-specific limits and balances
        if ($user) {
            // 2. Check user daily limit
            $dailyLimit = $user->daily_limit ?? (float) settings('user_daily_credit_limit', 0);
            if ($dailyLimit > 0 && ($user->credits_used_today + $estimatedCost) > $dailyLimit) {
                throw new CreditLimitException('daily');
            }

            // 3. Check user monthly limit
            $monthlyLimit = $user->monthly_limit ?? (float) settings('user_monthly_credit_limit', 0);
            if ($monthlyLimit > 0 && ($user->credits_used_month + $estimatedCost) > $monthlyLimit) {
                throw new CreditLimitException('monthly');
            }

            // 4. Check user credit balance
            if ($user->credits < $estimatedCost) {
                throw new InsufficientCreditsException((float) $user->credits, $estimatedCost);
            }
        }

        // 5. Check global daily budget
        $globalBudget = (float) settings('global_daily_ai_budget_usd', 0);
        if ($globalBudget > 0) {
            $spentToday = self::getGlobalSpendTodayUsd();
            if ($spentToday >= $globalBudget) {
                throw new GlobalBudgetExceededException;
            }
        }

        // Soft warnings for user
        if ($user) {
            // 6. Soft warning at 80% of daily limit
            if ($dailyLimit > 0 && ($user->credits_used_today / $dailyLimit) >= 0.8) {
                request()?->attributes?->set('credit_warning', 'daily_80');
            }

            // 7. Soft warning at 80% of monthly limit
            if ($monthlyLimit > 0 && ($user->credits_used_month / $monthlyLimit) >= 0.8) {
                request()?->attributes?->set('credit_warning', 'monthly_80');
            }
        }
    }

    /**
     * POST-COMPLETION — run AFTER receiving AI response.
     * Deducts credits, updates counters, logs usage.
     *
     * @return float Credits actually used
     */
    public static function after(
        ?User $user,
        int $inputTokens,
        int $outputTokens,
        string $model,
        string $provider = 'openai',
        string $type = 'chat',
        array $metadata = [],
        bool $deductCredits = true
    ): float {
        // Fetch model config from DB for pricing
        $dbModel = AiModel::where('slug', $model)->first();

        $costUsd = self::calculateCostUsd($dbModel, $inputTokens, $outputTokens);
        $credits = self::calculateCredits($dbModel, $inputTokens, $outputTokens);

        $deducted = false;

        // Deduct credits via the User model method (creates transaction record)
        if ($user && $deductCredits) {
            $deducted = $user->deductCredits($credits, "AI generation: {$model}", [
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
            ]);
        }

        // Update global spend tracker in cache using dated micro-USD precision.
        self::incrementGlobalSpend($costUsd);
        self::notifyHighAiCostIfNeeded();

        $billingFailed = $deductCredits && ! $deducted;

        AiUsageLog::create([
            'user_id' => $user?->id,
            'provider' => $provider,
            'model' => $model,
            'type' => $type,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
            'credits_used' => $billingFailed ? 0 : ($deductCredits ? $credits : 0),
            'status' => $billingFailed ? 'failed' : 'completed',
            'metadata' => $billingFailed
                ? array_merge($metadata, [
                    'billing_error' => 'INSUFFICIENT_CREDITS_AFTER_COMPLETION',
                    'credits_due' => $credits,
                ])
                : $metadata,
        ]);

        return ($deductCredits && $deducted) ? $credits : 0.0;
    }

    /**
     * Record a FAILED usage attempt (for logging even when stream errors mid-way).
     */
    public static function recordFailure(
        ?User $user,
        string $provider,
        string $model,
        string $type,
        int $inputTokens = 0,
        int $outputTokens = 0,
        array $metadata = []
    ): void {
        // Still calculate partial cost if any tokens were consumed
        if ($inputTokens > 0 || $outputTokens > 0) {
            $dbModel = AiModel::where('slug', $model)->first();
            $costUsd = self::calculateCostUsd($dbModel, $inputTokens, $outputTokens);
            $credits = self::calculateCredits($dbModel, $inputTokens, $outputTokens);

            self::incrementGlobalSpend($costUsd);
            self::notifyHighAiCostIfNeeded();

            if ($credits > 0 && $user) {
                $deducted = $user->deductCredits($credits, "AI generation (partial/failed): {$model}", [
                    'provider' => $provider,
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cost_usd' => $costUsd,
                    'failed' => true,
                ]);
            } else {
                $deducted = false;
            }
        } else {
            $costUsd = 0;
            $credits = 0;
            $deducted = false;
        }

        AiUsageLog::create([
            'user_id' => $user?->id,
            'provider' => $provider,
            'model' => $model,
            'type' => $type,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
            'credits_used' => $deducted ? $credits : 0,
            'status' => 'failed',
            'metadata' => $credits > 0 && ! $deducted
                ? array_merge($metadata, [
                    'billing_error' => 'INSUFFICIENT_CREDITS_AFTER_FAILURE',
                    'credits_due' => $credits,
                ])
                : $metadata,
        ]);
    }

    /**
     * Reset user usage counters. Scheduled daily; safe to run manually.
     */
    public static function resetDailyCounters(): int
    {
        return User::query()
            ->where(function ($query): void {
                $query->where('credits_used_today', '>', 0)
                    ->orWhere('credits_used_month', '>', 0);
            })
            ->update([
                'credits_used_today' => 0,
                'credits_used_month' => DB::raw(
                    now()->day === 1 ? '0' : 'credits_used_month'
                ),
            ]);
    }

    // ─── Legacy method for backward compatibility ────────────────

    /**
     * @deprecated Use before() instead
     */
    public static function authorize(User $user, string $type = 'chat'): void
    {
        self::before($user);
    }

    /**
     * @deprecated Use after() instead
     */
    public static function recordUsage(
        User $user,
        string $provider,
        string $modelSlug,
        string $type,
        int $inputTokens,
        int $outputTokens,
        array $metadata = []
    ): AiUsageLog {
        self::after($user, $inputTokens, $outputTokens, $modelSlug, $provider, $type, $metadata);

        return AiUsageLog::where('user_id', $user->id)->latest()->first();
    }

    // ─── Private Calculation Methods ─────────────────────────────

    /**
     * Estimate credit cost for pre-flight check.
     */
    private static function estimateCreditCost(?AiTemplate $template, ?string $model): float
    {
        $estimatedTokens = $template?->avg_output_tokens ?? 500;
        $modelSlug = $model ?? settings('default_ai_model', 'gpt-4o-mini');

        $dbModel = AiModel::where('slug', $modelSlug)->first();

        if (! $dbModel) {
            return round($estimatedTokens / 1000, 2);
        }

        $totalTokens = 200 + $estimatedTokens; // rough input estimate

        return round($totalTokens * ($dbModel->credits_per_1k / 1000), 2);
    }

    /**
     * Calculate USD cost based on dynamic model pricing.
     */
    private static function calculateCostUsd(?AiModel $model, int $input, int $output): float
    {
        if (! $model) {
            return 0.0;
        }

        $inputCost = ($input / 1000) * (float) $model->cost_input_1k;
        $outputCost = ($output / 1000) * (float) $model->cost_output_1k;

        return round($inputCost + $outputCost, 8);
    }

    /**
     * Calculate credits used based on dynamic model ratio.
     */
    private static function calculateCredits(?AiModel $model, int $input, int $output): float
    {
        if (! $model) {
            return round(($input + $output) / 1000, 2);
        }

        $totalTokens = $input + $output;
        $ratio = $model->credits_per_1k / 1000;

        return round($totalTokens * $ratio, 2);
    }

    private static function getGlobalSpendTodayUsd(): float
    {
        return ((int) Cache::get(self::globalSpendCacheKey(), 0)) / 1_000_000;
    }

    private static function incrementGlobalSpend(float $costUsd): void
    {
        if ($costUsd <= 0) {
            return;
        }

        $key = self::globalSpendCacheKey();
        $microUsd = max(1, (int) round($costUsd * 1_000_000));

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addDays(2));
        }

        Cache::increment($key, $microUsd);
    }

    private static function notifyHighAiCostIfNeeded(): void
    {
        $globalBudget = (float) settings('global_daily_ai_budget_usd', 0);
        if ($globalBudget <= 0) {
            return;
        }

        $spentToday = self::getGlobalSpendTodayUsd();
        $percentage = ($spentToday / $globalBudget) * 100;

        if ($percentage >= (float) settings('ai_budget_alert_threshold_percent', 80)) {
            app(NotificationEventService::class)->highAiCostAlert($percentage);
        }
    }

    private static function globalSpendCacheKey(): string
    {
        return 'ai_spend_today_usd:'.now()->toDateString();
    }
}
