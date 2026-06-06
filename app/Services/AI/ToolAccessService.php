<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\DTO\AccessResult;
use App\Models\AiTool;
use App\Models\User;

class ToolAccessService
{
    public function effectiveLevel(AiTool $tool): string
    {
        return $tool->getEffectiveAccessLevel();
    }

    public function requiresAuth(AiTool $tool): bool
    {
        return $this->effectiveLevel($tool) !== 'public'
            || $tool->isProRequired()
            || ($tool->category?->requires_login ?? false);
    }

    public function checkAccess(AiTool $tool, ?User $user): AccessResult
    {
        if (($tool->category?->requires_login ?? false) && ! $user) {
            return AccessResult::deny('login', 401);
        }

        if ($tool->isProRequired()) {
            if (! isProAvailable()) {
                return AccessResult::deny('pro_unavailable');
            }

            if (! $user) {
                return AccessResult::deny('login', 401);
            }

            if (! $user->isPro()) {
                return AccessResult::deny('upgrade');
            }
        }

        $level = $this->effectiveLevel($tool);

        return match ($level) {
            'public' => AccessResult::allow(truncate: true),
            'login_required' => $user
                ? AccessResult::allow()
                : AccessResult::deny('login', 401),
            'free_plan' => $user && $user->credits > 0
                ? AccessResult::allow()
                : AccessResult::deny($user ? 'credits' : 'login', $user ? 402 : 401),
            'pro_plan' => $this->resolveProPlanAccess($user),
            default => AccessResult::deny('unknown'),
        };
    }

    private function resolveProPlanAccess(?User $user): AccessResult
    {
        if (! isProAvailable()) {
            return AccessResult::deny('pro_unavailable');
        }

        if (! $user) {
            return AccessResult::deny('login', 401);
        }

        if (! $user->isPro()) {
            return AccessResult::deny('upgrade');
        }

        return AccessResult::allow();
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
