<?php

namespace App\Http\Middleware;

use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\GlobalBudgetExceededException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Models\AiTool;
use App\Models\User;
use App\Services\AI\TokenGuard;
use App\Services\AI\ToolAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * CheckCredits — pre-flight credit check middleware.
 *
 * Applied to all AI generation routes.
 * Runs TokenGuard::before() to validate user has enough credits.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.1
 */
class CheckCredits
{
    public function handle(Request $request, Closure $next)
    {
        $user = $this->currentUser();
        $access = app(ToolAccessService::class);

        $template = null;
        $slug = $request->input('slug');
        if ($slug) {
            $template = AiTool::where('slug', $slug)->where('is_active', true)->first();
        }

        $accessLevel = $template ? $access->effectiveLevel($template) : 'login_required';

        if ($template && $access->requiresAuth($template) && ! $user) {
            return response()->json([
                'success' => false,
                'message' => translate('Authentication required to use this tool.'),
            ], 401);
        }

        if ($template && $access->requiresAuth($template) && $user && method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => translate('Please verify your email address before using this tool.'),
            ], 403);
        }

        if ($template && $template->isProRequired() && $user && ! $user->isPro()) {
            return response()->json([
                'success' => false,
                'message' => translate('Upgrade to Pro to unlock this tool.'),
            ], 403);
        }

        if ($accessLevel === 'public' && ! $user && ! $template?->isProRequired()) {
            $limit = (int) settings('public_tool_rate_limit_per_hour', 5);
            $key = 'public_tool_rate:'.$request->ip().':'.($template?->slug ?? 'unknown');
            $attempts = (int) Cache::get($key, 0);

            if ($limit > 0 && $attempts >= $limit) {
                return response()->json([
                    'success' => false,
                    'message' => translate('Public preview limit reached. Please sign in to continue.'),
                    'type' => 'PublicRateLimit',
                ], 429);
            }

            Cache::put($key, $attempts + 1, now()->addHour());
        }

        $model = $request->input('model')
            ?? data_get($request->input('fields', []), 'model')
            ?? $template?->model_override
            ?? settings('default_ai_model', 'gpt-4o-mini');

        try {

            TokenGuard::before($user, $template, $model);
        } catch (\Throwable $e) {
            if ($template || $user) {
                TokenGuard::recordFailure(
                    $user,
                    settings('default_ai_provider', 'openai'),
                    $model,
                    'template',
                    0,
                    0,
                    [
                        'template_slug' => $template?->slug,
                        'preflight_error' => class_basename($e),
                    ]
                );
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'type' => class_basename($e),
            ], $this->statusForException($e));
        }

        $response = $next($request);

        // Attach credit warning header if set by TokenGuard
        $warning = $request->attributes->get('credit_warning');
        if ($warning && method_exists($response, 'header')) {
            $response->header('X-Credit-Warning', $warning);
        }

        return $response;
    }

    private function currentUser(): ?User
    {
        if (array_key_exists('sanctum', config('auth.guards', []))) {
            return Auth::guard('sanctum')->user() ?? Auth::user();
        }

        return Auth::user();
    }

    private function statusForException(\Throwable $exception): int
    {
        return match (true) {
            $exception instanceof CreditLimitException,
            $exception instanceof InsufficientCreditsException => 402,
            $exception instanceof GlobalBudgetExceededException => 503,
            default => 422,
        };
    }
}
}
}
