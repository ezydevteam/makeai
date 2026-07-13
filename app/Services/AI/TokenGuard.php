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
        self::assertAccountUsable($user);

        // Estimate cost, then enforce per-user limits/balance and the global budget.
        $estimatedCost = self::estimateCreditCost($template, $model);
        self::assertUserCanSpend($user, $estimatedCost);
        if (! $user) {
            self::assertGuestCanSpend(request()?->ip(), $estimatedCost);
        }
        self::assertGlobalBudget();

        self::emitSoftWarnings($user);
    }

    /**
     * PRE-FLIGHT CHECK for media generation (image/audio/transcription).
     *
     * Media is priced per unit, not per token, so it can't reuse the token-based
     * estimate in before() — that resolves to text-model pricing and under-gates an
     * expensive image job at ~0.5 credits. This estimates the real per-unit cost so
     * a low-balance user can't slip an expensive media job past the balance check.
     *
     * @param  string  $mediaType  one of: image, audio, transcription
     * @param  int  $units  number of billable units (e.g. images requested)
     */
    public static function beforeMedia(?User $user, string $mediaType, ?string $model = null, int $units = 1): void
    {
        self::assertAccountUsable($user);

        $estimatedCost = self::mediaCreditCost($mediaType, $model, $units);
        self::assertUserCanSpend($user, $estimatedCost);
        if (! $user) {
            self::assertGuestCanSpend(request()?->ip(), $estimatedCost);
        }
        self::assertGlobalBudget();

        self::emitSoftWarnings($user);
    }

    /**
     * Deactivated/banned accounts can't spend, regardless of balance.
     */
    private static function assertAccountUsable(?User $user): void
    {
        if (! $user) {
            return;
        }

        if (! $user->is_active) {
            throw new RuntimeException(translate('Your account has been deactivated.'));
        }

        if ($user->is_banned) {
            throw new RuntimeException(translate('Your account has been suspended.'));
        }
    }

    /**
     * Enforce the user's daily/monthly credit limits and balance against an
     * estimated cost. The internal "system" user (admin AI-assist) is not a paying
     * account and skips per-user enforcement (it still counts toward global spend).
     */
    private static function assertUserCanSpend(?User $user, float $estimatedCost): void
    {
        if (! $user || $user->isInternalAi()) {
            return;
        }

        $dailyLimit = $user->daily_limit ?? (float) settings('user_daily_credit_limit', 0);
        if ($dailyLimit > 0 && ($user->credits_used_today + $estimatedCost) > $dailyLimit) {
            throw new CreditLimitException('daily', $dailyLimit - $user->credits_used_today);
        }

        $monthlyLimit = $user->monthly_limit ?? (float) settings('user_monthly_credit_limit', 0);
        if ($monthlyLimit > 0 && ($user->credits_used_month + $estimatedCost) > $monthlyLimit) {
            throw new CreditLimitException('monthly', $monthlyLimit - $user->credits_used_month);
        }

        // The persistent wallet balance is only a wall in METERED mode (Extended +
        // billing). In quota mode (Regular license) there is no top-up, so the daily/
        // monthly allowance above is the only limiter — a drained wallet must not block.
        if (! credit_quota_mode() && $user->credits < $estimatedCost) {
            throw new InsufficientCreditsException((float) $user->credits, $estimatedCost);
        }
    }

    private static function assertGlobalBudget(): void
    {
        if (self::globalBudgetExceeded()) {
            throw new GlobalBudgetExceededException;
        }
    }

    /**
     * Non-throwing global-budget check. Lets callers that don't charge a user
     * (e.g. a public help-center answer, whose cost the operator absorbs) gate on
     * the daily AI cost kill-switch gracefully instead of catching an exception.
     * Returns true when a budget is configured and today's spend has reached it.
     */
    public static function globalBudgetExceeded(): bool
    {
        $globalBudget = (float) settings('global_daily_ai_budget_usd', 0);

        return $globalBudget > 0 && self::getGlobalSpendTodayUsd() >= $globalBudget;
    }

    /**
     * Soft 80%-of-limit warnings surfaced to the request (skipped for the internal
     * system user, which has no per-user limits).
     */
    private static function emitSoftWarnings(?User $user): void
    {
        if (! $user || $user->isInternalAi()) {
            return;
        }

        $dailyLimit = $user->daily_limit ?? (float) settings('user_daily_credit_limit', 0);
        if ($dailyLimit > 0 && ($user->credits_used_today / $dailyLimit) >= 0.8) {
            request()?->attributes?->set('credit_warning', 'daily_80');
        }

        $monthlyLimit = $user->monthly_limit ?? (float) settings('user_monthly_credit_limit', 0);
        if ($monthlyLimit > 0 && ($user->credits_used_month / $monthlyLimit) >= 0.8) {
            request()?->attributes?->set('credit_warning', 'monthly_80');
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
        $meta = [
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
        ];

        if ($billable) {
            if (credit_quota_mode()) {
                // Quota mode: meter usage against the daily/monthly allowance without
                // draining the (un-refillable) wallet. Never fails on balance.
                $user->trackQuotaUsage($credits, "AI generation: {$model}", $meta);
                $deducted = true;
            } else {
                $deducted = $user->deductCredits($credits, "AI generation: {$model}", $meta);

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
        } elseif (! $user && $deductCredits) {
            // Anonymous visitor on a public tool — meter the per-IP daily guest quota.
            self::incrementGuestUsage(request()?->ip(), $credits);
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
     * POST-COMPLETION for media generation — charge the resolved per-unit credit
     * cost (NOT a token-derived guess) and log the usage. Global USD spend is
     * tracked from the model's meta.cost_per_unit when configured.
     *
     * @param  string  $mediaType  one of: image, audio, transcription
     * @param  int  $units  number of billable units produced (e.g. image count)
     * @return float Credits actually charged
     */
    public static function afterMedia(
        ?User $user,
        string $mediaType,
        ?string $model,
        string $provider,
        int $units = 1,
        array $metadata = [],
        bool $deductCredits = true,
        ?int $responseTimeMs = null
    ): float {
        $units = max(1, $units);
        $dbModel = $model ? self::resolveModelForPricing($model) : null;
        $credits = self::mediaCreditCost($mediaType, $model, $units);

        // Track real USD spend: model's own cost_per_unit first, then the config
        // default for this media type (so global budget tracking works even for a
        // media call with no dedicated AiModel row).
        $costPerUnit = (float) data_get($dbModel?->meta, 'cost_per_unit', 0);
        if ($costPerUnit <= 0) {
            $costPerUnit = (float) config("ai.media_costs.{$mediaType}", 0);
        }
        $costUsd = round($costPerUnit * $units, 8);

        $billable = $user && $deductCredits && ! $user->isInternalAi();
        $deducted = false;

        $mediaMeta = [
            'provider' => $provider,
            'model' => $model,
            'media_type' => $mediaType,
            'units' => $units,
            'cost_usd' => $costUsd,
        ];

        if ($billable && credit_quota_mode()) {
            // Quota mode: meter against the allowance, don't drain the wallet.
            $user->trackQuotaUsage($credits, "AI {$mediaType}: ".($model ?? $provider), $mediaMeta);
            $deducted = true;
        } elseif ($billable) {
            $deducted = $user->deductCredits($credits, "AI {$mediaType}: ".($model ?? $provider), $mediaMeta);
        } elseif (! $user && $deductCredits) {
            // Anonymous visitor — meter the per-IP daily guest quota.
            self::incrementGuestUsage(request()?->ip(), $credits);
        }

        self::incrementGlobalSpend($costUsd);
        self::notifyHighAiCostIfNeeded();

        $billingFailed = $billable && ! $deducted;

        if ($user) {
            AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model ?? $mediaType,
                'type' => $metadata['type'] ?? $mediaType,
                'tool_slug' => $metadata['template_slug'] ?? $metadata['tool_slug'] ?? null,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => $costUsd,
                'credits_used' => ($billable && $deducted) ? $credits : 0,
                'response_time_ms' => $responseTimeMs,
                'status' => $billingFailed ? 'failed' : 'completed',
                'metadata' => $billingFailed
                    ? array_merge($metadata, [
                        'billing_error' => 'INSUFFICIENT_CREDITS_AFTER_COMPLETION',
                        'credits_due' => $credits,
                    ])
                    : $metadata,
            ]);

            Cache::forget("usage_stats_{$user->id}");
        }

        return ($billable && $deducted) ? $credits : 0.0;
    }

    /**
     * Charge the fixed per-call credit cost for an external tool integration
     * (config/external-tools.php → `external_{slug}_fixed_credit_cost`). This is the
     * consumer that finally makes that setting live. Returns credits charged (0 when
     * unpriced, internal user, or on empty balance — the row-locked deduct prevents
     * a negative balance).
     */
    public static function chargeExternalTool(?User $user, string $slug, array $metadata = [], bool $deductCredits = true): float
    {
        $cost = round((float) settings("external_{$slug}_fixed_credit_cost", 0), 2);

        $billable = $user && $deductCredits && $cost > 0 && ! $user->isInternalAi();
        $reason = "AI tool: {$slug}";
        $chargeMeta = array_merge(['integration' => $slug], $metadata);

        if ($billable && credit_quota_mode()) {
            // Quota mode: meter against the allowance, don't drain the wallet.
            $user->trackQuotaUsage($cost, $reason, $chargeMeta);
            $deducted = true;
        } elseif ($billable) {
            $deducted = $user->deductCredits($cost, $reason, $chargeMeta);
        } else {
            $deducted = false;
            if (! $user && $deductCredits && $cost > 0) {
                // Anonymous visitor on a public integration tool — meter the guest quota.
                self::incrementGuestUsage(request()?->ip(), $cost);
            }
        }

        if ($user) {
            AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => 'integration',
                'model' => $slug,
                'type' => 'tool',
                'tool_slug' => $metadata['tool_slug'] ?? $slug,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => 0,
                'credits_used' => $deducted ? $cost : 0,
                'status' => ($billable && ! $deducted) ? 'failed' : 'completed',
                'metadata' => ($billable && ! $deducted)
                    ? array_merge($metadata, ['billing_error' => 'INSUFFICIENT_CREDITS', 'credits_due' => $cost])
                    : $metadata,
            ]);

            Cache::forget("usage_stats_{$user->id}");
        }

        return $deducted ? $cost : 0.0;
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
                // Mode-correct: meter the allowance in quota mode, drain the wallet
                // in metered mode. Partial tokens are still charged either way.
                $deducted = $user->chargeCredits($credits, "AI generation (partial/failed): {$model}", [
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
            self::refreshFreePlanCredits();
            settings_set('credits_month_last_reset', $currentMonth, 'string', 'ai');
        }

        return $affected;
    }

    /**
     * Reset-style monthly refresh of free-plan credit allowances.
     *
     * At month rollover, every user on an active FREE plan (credits > 0) is topped
     * back up to that plan's monthly credits. Implemented as "bump users below the
     * allowance up to it" (WHERE credits < plan.credits) so it:
     *   - resets a spent-down free allowance (unused free credits don't roll over), and
     *   - never REDUCES a balance boosted by top-ups or admin grants.
     * Scoped to free plans so paid subscribers' purchased credits are never wiped.
     *
     * @return int users refreshed
     */
    private static function refreshFreePlanCredits(): int
    {
        $refreshed = 0;

        foreach (\App\Models\Plan::query()->where('is_active', true)->where('is_free', true)->where('credits', '>', 0)->get(['id', 'credits']) as $plan) {
            $refreshed += User::query()
                ->where('plan_id', $plan->id)
                ->where('credits', '<', (float) $plan->credits)
                ->update(['credits' => (float) $plan->credits]);
        }

        return $refreshed;
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
        return AiModel::resolveForPricing($model);
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
     * Per-unit credit cost for a media generation (image/audio/transcription).
     *
     * Resolution order: the media model's own meta.credits_per_unit, else the
     * config default for the media type (config/ai.php → media_credits). This is
     * the single source of truth for both the pre-flight estimate and the charge,
     * so media is never billed via the meaningless token fallback.
     */
    public static function mediaCreditCost(string $mediaType, ?string $model, int $units = 1): float
    {
        $units = max(1, $units);
        $dbModel = $model ? self::resolveModelForPricing($model) : null;

        // 1. Explicit manual per-unit credit override on the model always wins.
        $manual = $dbModel ? data_get($dbModel->meta, 'credits_per_unit') : null;
        if ($manual !== null) {
            return round((float) $manual * $units, 2);
        }

        // 2. Anchor to real USD cost × markup — model meta first, then config default.
        $costPerUnit = $dbModel ? (float) data_get($dbModel->meta, 'cost_per_unit', 0) : 0.0;
        if ($costPerUnit <= 0) {
            $costPerUnit = (float) config("ai.media_costs.{$mediaType}", 0);
        }

        if ($costPerUnit > 0) {
            return round(CreditPricingService::deriveCreditsPerUnit($costPerUnit) * $units, 2);
        }

        // 3. Legacy flat fallback (install with no USD cost configured for this type).
        $perUnit = config("ai.media_credits.{$mediaType}", config('ai.media_credits.image', 4));

        return round((float) $perUnit * $units, 2);
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

    // ─── Guest per-IP daily quota (public tools) ─────────────────

    /**
     * Credits an anonymous visitor (per IP) has spent today. Stored as an integer
     * count of milli-credits in the cache to avoid float drift, keyed by IP + date.
     */
    public static function guestUsedToday(?string $ip): float
    {
        if (! $ip) {
            return 0.0;
        }

        return ((int) Cache::get(self::guestCacheKey($ip), 0)) / 1000;
    }

    /**
     * Enforce the guest per-IP daily credit allowance before a public-tool run.
     * No-op when the limit is disabled (<= 0) or the IP is unknown.
     *
     * @throws CreditLimitException
     */
    private static function assertGuestCanSpend(?string $ip, float $estimatedCost): void
    {
        $limit = (float) settings('guest_daily_credit_limit', 0);
        if ($limit <= 0 || ! $ip) {
            return;
        }

        $used = self::guestUsedToday($ip);
        if (($used + $estimatedCost) > $limit) {
            throw new CreditLimitException('guest', max(0, $limit - $used));
        }
    }

    /**
     * Record credits an anonymous visitor spent, against the per-IP daily quota.
     * TTL resets at end of day. No-op when the limit is disabled or IP is unknown.
     */
    private static function incrementGuestUsage(?string $ip, float $credits): void
    {
        $limit = (float) settings('guest_daily_credit_limit', 0);
        if ($limit <= 0 || ! $ip || $credits <= 0) {
            return;
        }

        $key = self::guestCacheKey($ip);
        $milli = max(1, (int) round($credits * 1000));

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->endOfDay());
        }

        Cache::increment($key, $milli);
    }

    private static function guestCacheKey(string $ip): string
    {
        return 'guest_credits:'.$ip.':'.now()->toDateString();
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
