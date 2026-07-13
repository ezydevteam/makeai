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
        $user = $this->currentUser($request);
        $access = app(ToolAccessService::class);

        $template = null;
        $slug = $request->input('slug');
        if ($slug) {
            $template = AiTool::where('slug', $slug)->where('is_active', true)->first();
        }

        if ($user && $user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => translate('Your account has been suspended. Please contact support.'),
            ], 403);
        }

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

        if ($template && $template->isProRequired() && ! isProAvailable()) {
            return response()->json([
                'success' => false,
                'message' => translate('Premium features are not available on this installation.'),
            ], 404);
        }

        if ($template && $template->isProRequired() && $user && ! $user->isPro()) {
            return response()->json([
                'success' => false,
                'message' => translate('Upgrade to Pro to unlock this tool.'),
            ], 403);
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
                    'tool',
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

        $warning = $request->attributes->get('credit_warning');
        if ($warning && method_exists($response, 'header')) {
            $response->header('X-Credit-Warning', $warning);
        }

        return $response;
    }

    private function currentUser(Request $request): ?User
    {
        // With 'web' middleware applied, Laravel's session is already started
        // and Auth::guard('web') is populated from the session.
        $user = Auth::guard('web')->user();

        if ($user instanceof User) {
            return $user;
        }

        return null;
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
