<?php

namespace App\Services\AI;

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
        return $this->effectiveLevel($tool) !== 'public' || $tool->isProRequired();
    }

    public function assertCanUse(AiTool $tool, ?User $user): void
    {
        $accessLevel = $this->effectiveLevel($tool);

        if ($tool->isProRequired()) {
            if (! $user) {
                abort(401, translate('Please sign in to use this tool.'));
            }

            if (! $user->isPro()) {
                abort(403, translate('Upgrade to Pro to unlock this tool.'));
            }

            return;
        }

        if ($accessLevel === 'public') {
            return;
        }

        if (in_array($accessLevel, ['login_required', 'free_plan'], true)) {
            if (! $user) {
                abort(401, translate('Please sign in to use this tool.'));
            }

            return;
        }

        if ($accessLevel === 'pro_plan') {
            if (! $user) {
                abort(401, translate('Please sign in to use this tool.'));
            }

            if (! $user->isPro()) {
                abort(403, translate('Upgrade to Pro to unlock this tool.'));
            }
        }
    }
}
