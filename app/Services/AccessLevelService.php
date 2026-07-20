<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AccessLevelService
{
    /**
     * Get all available access levels (static + dynamic plan levels).
     */
    /**
     * Get all available access levels (static + dynamic plan levels).
     * Filters out premium/plan levels if isProAvailable() is false.
     */
    public function getAllLevels(): Collection
    {
        $staticLevels = collect(config('access-levels.levels', []));

        // Filter out premium level if pro is not available
        if (! isProAvailable()) {
            $staticLevels = $staticLevels->filter(fn (array $level, string $key) => $key !== 'premium');
        }

        if (! config('access-levels.dynamic_plan_levels', true)) {
            return $staticLevels->sortBy('sort_order');
        }

        // Only include plan levels if pro is available
        if (! isProAvailable()) {
            return $staticLevels->sortBy('sort_order');
        }

        $planLevels = $this->getPlanLevels();

        return $staticLevels->merge($planLevels)->sortBy('sort_order');
    }

    /**
     * Get access levels for dropdown/select options.
     */
    public function getOptions(): array
    {
        return $this->getAllLevels()
            ->map(fn (array $level, string $key) => [
                'value' => $key,
                'label' => $level['label'],
                'description' => $level['description'] ?? null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get a specific access level configuration.
     */
    public function getLevel(string $key): ?array
    {
        $levels = $this->getAllLevels();

        return $levels->get($key);
    }

    /**
     * Check if an access level exists.
     */
    public function levelExists(string $key): bool
    {
        return $this->getLevel($key) !== null;
    }

    /**
     * Get the default access level.
     */
    public function getDefault(): string
    {
        return config('access-levels.default', 'login');
    }

    /**
     * Check if a level requires authentication.
     */
    public function requiresAuth(string $level): bool
    {
        $config = $this->getLevel($level);

        return $config['requires_auth'] ?? false;
    }

    /**
     * Check if a level requires a subscription.
     */
    public function requiresSubscription(string $level): bool
    {
        $config = $this->getLevel($level);

        return $config['requires_subscription'] ?? false;
    }

    /**
     * Check if a level is for a specific plan.
     */
    public function isPlanLevel(string $level): bool
    {
        $prefix = config('access-levels.plan_prefix', 'plan:');

        return str_starts_with($level, $prefix);
    }

    /**
     * Get the plan slug from a plan-level access level.
     */
    public function getPlanSlugFromLevel(string $level): ?string
    {
        if (! $this->isPlanLevel($level)) {
            return null;
        }

        $prefix = config('access-levels.plan_prefix', 'plan:');

        return substr($level, strlen($prefix));
    }

    /**
     * Check if a user has access to a specific level.
     */
    public function checkAccess(string $level, ?\App\Models\User $user): bool
    {
        $config = $this->getLevel($level);

        if (! $config) {
            return false;
        }

        // Check authentication requirement
        if ($config['requires_auth'] && ! $user) {
            return false;
        }

        // Check subscription requirement
        if ($config['requires_subscription']) {
            if (! $user || ! $user->isPro()) {
                return false;
            }

            // Check if specific plan is required
            if ($this->isPlanLevel($level)) {
                $requiredPlanSlug = $this->getPlanSlugFromLevel($level);

                if (! $requiredPlanSlug || ! $user->plan) {
                    return false;
                }

                if ($user->plan->slug === $requiredPlanSlug) {
                    return true;
                }

                // Plan hierarchy: a strictly higher-tier plan (by sort_order)
                // also unlocks tools gated to lower-tier plans.
                $requiredPlan = Plan::where('slug', $requiredPlanSlug)
                    ->where('is_active', true)
                    ->first();

                return $requiredPlan !== null
                    && (int) $user->plan->sort_order > (int) $requiredPlan->sort_order;
            }
        }

        return true;
    }

    /**
     * Get validation rules for access level.
     */
    public function getValidationRules(): array
    {
        $validLevels = $this->getAllLevels()->keys()->toArray();

        return ['in:' . implode(',', $validLevels)];
    }

    /**
     * Get dynamically generated plan levels.
     */
    protected function getPlanLevels(): Collection
    {
        $cacheKey = 'access-levels.plan-levels';
        $cached = Cache::get($cacheKey);

        // Handle valid cached data
        if (is_array($cached)) {
            return collect($cached);
        }

        // Clear invalid cache (including __PHP_Incomplete_Class or corrupted data)
        if ($cached !== null && !($cached instanceof Collection)) {
            Cache::forget($cacheKey);
        }

        $prefix = config('access-levels.plan_prefix', 'plan:');

        $items = Plan::where('is_active', true)
            ->where('is_free', false)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function (Plan $plan) use ($prefix) {
                $key = $prefix . $plan->slug;

                return [
                    $key => [
                        'label' => "Plan: {$plan->name}",
                        'description' => "Requires {$plan->name} subscription",
                        'requires_auth' => true,
                        'requires_subscription' => true,
                        'plan_slugs' => [$plan->slug],
                        'sort_order' => 40 + $plan->sort_order,
                    ],
                ];
            })
            ->all();

        // Cache as array to avoid serialization issues
        Cache::put($cacheKey, $items, 3600);

        return collect($items);
    }

    /**
     * Clear the plan levels cache.
     */
    public function clearCache(): void
    {
        Cache::forget('access-levels.plan-levels');
    }
}
