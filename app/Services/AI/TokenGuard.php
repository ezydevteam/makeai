<?php

namespace App\Services\AI;

use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\GlobalBudgetExceededException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Jobs\SendCreditAlertJob;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\Cache;
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
    public static function before(?User $user, ?AiTool $template = null, ?string $model = null): void
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

        // User-specific limits and balances. The internal "system" user (admin
        // AI-assist) is not a paying account — it skips per-user credit/limit
        // enforcement but still counts against the global budget checked below.
        if ($user && ! $user->isInternalAi()) {
            // 2. Check user daily limit
            $dailyLimit = $user->daily_limit ?? (float) settings('user_daily_credit_limit', 0);
            if ($dailyLimit > 0 && ($user->credits_used_today + $estimatedCost) > $dailyLimit) {
                throw new CreditLimitException('daily', $dailyLimit - $user->credits_used_today);
            }

            // 3. Check user monthly limit
            $monthlyLimit = $user->monthly_limit ?? (float) settings('user_monthly_credit_limit', 0);
            if ($monthlyLimit > 0 && ($user->credits_used_month + $estimatedCost) > $monthlyLimit) {
                throw new CreditLimitException('monthly', $monthlyLimit - $user->credits_used_month);
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

        // Soft warnings for user (skipped for the internal system user, which has
        // no per-user limits and whose $dailyLimit/$monthlyLimit are never set).
        if ($user && ! $user->isInternalAi()) {
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
     * @param  bool  $deductCredits  false = log only, never charge (guest/personal API key)
     * @param  bool  $success  false = logged as 'cancelled' (aborted stream); tokens that were
     *                         generated are still billed when $deductCredits is true
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
        bool $deductCredits = true,
        bool $success = true,
        ?int $responseTimeMs = null
    ): float {
        // Fetch model config from DB for pricing
        $dbModel = self::resolveModelForPricing($model);

        $costUsd = self::calculateCostUsd($dbModel, $inputTokens, $outputTokens);
        $credits = self::calculateCredits($dbModel, $inputTokens, $outputTokens);

        $deducted = false;

        // The internal system user (admin AI-assist) is never charged per-user
        // credits; its usage is still logged and counted toward global spend.
        $billable = $user && $deductCredits && ! $user->isInternalAi();

        // Charge for tokens the provider actually generated — including
        // streams the client aborted part-way ($success = false).
        if ($billable) {
            $deducted = $user->deductCredits($credits, "AI generation: {$model}", [
                'provider' => $provider,
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
            ]);

            if ($deducted && function_exists('isProAvailable') && isProAvailable()) {
                $threshold = (int) settings('credit_alert_threshold', 100);
                if ($user->fresh()->credits <= $threshold) {
                    $cacheKey = "credit_alert_sent_{$user->id}";
                    if (! Cache::has($cacheKey)) {
                        SendCreditAlertJob::dispatch($user)->onQueue('mail');
                        Cache::put($cacheKey, true, now()->addDays((int) settings('credit_alert_cooldown_days', 7)));
                    }
                }
            }
        }

        // Update global spend tracker — tokens were consumed either way
        self::incrementGlobalSpend($costUsd);
        self::notifyHighAiCostIfNeeded();

        $status = $success ? 'completed' : 'cancelled';
        $billingFailed = $billable && ! $deducted;

        if ($user) {
            AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model,
                'type' => $type,
                'tool_slug' => $metadata['template_slug'] ?? $metadata['tool_slug'] ?? null,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
                'credits_used' => ($billable && $deducted) ? $credits : 0,
                'response_time_ms' => $responseTimeMs,
                'status' => $billingFailed ? 'failed' : $status,
                'metadata' => $billingFailed
                    ? array_merge($metadata, [
                        'billing_error' => 'INSUFFICIENT_CREDITS_AFTER_COMPLETION',
                        'credits_due' => $credits,
                    ])
                    : $metadata,
            ]);
        }

        if ($user) {
            Cache::forget("usage_stats_{$user->id}");
        }

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
            $dbModel = self::resolveModelForPricing($model);
            $costUsd = self::calculateCostUsd($dbModel, $inputTokens, $outputTokens);
            $credits = self::calculateCredits($dbModel, $inputTokens, $outputTokens);

            self::incrementGlobalSpend($costUsd);
            self::notifyHighAiCostIfNeeded();

            if ($credits > 0 && $user && ! $user->isInternalAi()) {
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

        if ($user) {
            AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model,
                'type' => $type,
                'tool_slug' => $metadata['template_slug'] ?? $metadata['tool_slug'] ?? null,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
                'credits_used' => $deducted ? $credits : 0,
                'status' => 'failed',
                'metadata' => $credits > 0 && ! $deducted && ! $user->isInternalAi()
                    ? array_merge($metadata, [
                        'billing_error' => 'INSUFFICIENT_CREDITS_AFTER_FAILURE',
                        'credits_due' => $credits,
                    ])
                    : $metadata,
            ]);
        }
    }

    /**
     * Reset user usage counters. Scheduled daily; safe to run manually.
     */
    public static function resetDailyCounters(): int
    {
        $affected = User::query()
            ->where('credits_used_today', '>', 0)
            ->update(['credits_used_today' => 0]);

        // Monthly reset via a persisted marker: if the scheduler misses the
        // 1st, the reset is caught up on the next run instead of skipping
        // the whole month.
        $currentMonth = now()->format('Y-m');
        $lastReset = (string) settings('credits_month_last_reset', '');

        if ($lastReset === '') {
            settings_set('credits_month_last_reset', $currentMonth, 'string', 'ai');
        } elseif ($lastReset !== $currentMonth) {
            User::query()
                ->where('credits_used_month', '>', 0)
                ->update(['credits_used_month' => 0]);
            settings_set('credits_month_last_reset', $currentMonth, 'string', 'ai');
        }

        return $affected;
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
     * Resolve the AiModel row used for pricing.
     *
     * Providers return fully-qualified model names (e.g. "gpt-4o-mini-2024-07-18")
     * that don't match the stored slug ("gpt-4o-mini"). Fall back to the
     * longest slug that prefixes the returned name so billing never silently
     * drops to the generic per-token fallback for known models.
     */
    private static function resolveModelForPricing(string $model): ?AiModel
    {
        $dbModel = AiModel::where('slug', $model)->first();
        if ($dbModel) {
            return $dbModel;
        }

        return AiModel::all()
            ->filter(fn (AiModel $candidate) => str_starts_with($model, $candidate->slug))
            ->sortByDesc(fn (AiModel $candidate) => strlen($candidate->slug))
            ->first();
    }

    /**
     * Estimate credit cost for pre-flight check.
     */
    private static function estimateCreditCost(?AiTool $template, ?string $model): float
    {
        $estimatedTokens = $template?->avg_output_tokens ?? 500;
        $modelSlug = $model ?? settings('default_ai_model', 'gpt-4o-mini');

        $dbModel = self::resolveModelForPricing($modelSlug);

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
            // Alert once per day, not on every request past the threshold
            $alertKey = 'ai_budget_alert_sent:'.now()->toDateString();
            if (! Cache::has($alertKey)) {
                Cache::put($alertKey, true, now()->addDay());
                app(NotificationEventService::class)->highAiCostAlert($percentage);
            }
        }
    }

    private static function globalSpendCacheKey(): string
    {
        return 'ai_spend_today_usd:'.now()->toDateString();
    }
}
