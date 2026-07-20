<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\DTO\AccessResult;
use App\Models\AiTool;
use App\Models\User;
use App\Services\AccessLevelService;

class ToolAccessService
{
    protected AccessLevelService $accessLevelService;

    public function __construct(AccessLevelService $accessLevelService)
    {
        $this->accessLevelService = $accessLevelService;
    }

    public function effectiveLevel(AiTool $tool): string
    {
        return $tool->getEffectiveAccessLevel();
    }

    public function requiresAuth(AiTool $tool): bool
    {
        $level = $this->effectiveLevel($tool);

        return $this->accessLevelService->requiresAuth($level);
    }

    public function checkAccess(AiTool $tool, ?User $user): AccessResult
    {
        $level = $this->effectiveLevel($tool);

        // Check if premium features are available
        if ($this->accessLevelService->requiresSubscription($level) && ! isProAvailable()) {
            return AccessResult::deny('pro_unavailable');
        }

        // Use the AccessLevelService to check access
        if ($this->accessLevelService->checkAccess($level, $user)) {
            return AccessResult::allow(truncate: $level === 'guest');
        }

        // Determine the specific denial reason
        if ($this->accessLevelService->requiresAuth($level) && ! $user) {
            return AccessResult::deny('login', 401);
        }

        if ($this->accessLevelService->requiresSubscription($level)) {
            if (! $user) {
                return AccessResult::deny('login', 401);
            }

            if (! $user->isPro()) {
                return AccessResult::deny('upgrade');
            }

            // Check if specific plan is required
            if ($this->accessLevelService->isPlanLevel($level)) {
                $requiredPlanSlug = $this->accessLevelService->getPlanSlugFromLevel($level);

                if ($requiredPlanSlug && $user->plan && $user->plan->slug !== $requiredPlanSlug) {
                    return AccessResult::deny('upgrade');
                }
            }
        }

        return AccessResult::deny('unknown');
    }

    public function assertCanUse(AiTool $tool, ?User $user): void
    {
        $result = $this->checkAccess($tool, $user);

        if (! $result->allowed) {
            abort($result->httpStatus, match ($result->reason) {
                'login' => translate('Please sign in to use this tool.'),
                'credits' => translate('You do not have enough credits to use this tool.'),
                'upgrade' => translate('Upgrade to Pro to unlock this tool.'),
                'pro_unavailable' => translate('Premium features are not available on this installation.'),
                default => translate('You do not have access to this tool.'),
            });
        }
    }
}
