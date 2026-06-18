<?php

namespace App\Http\Middleware;

use App\Models\AiModel;
use App\Services\RateLimiterService;
use Closure;
use Illuminate\Http\Request;

class ThrottleAiRequests
{
    public function __construct(private RateLimiterService $rateLimiter) {}

    public function handle(Request $request, Closure $next, ...$args): mixed
    {
        $category = $args[0] ?? 'public';

        // Skip Laravel's default 'api' throttle — we apply specific categories per route
        if ($category === 'api') {
            return $next($request);
        }

        $maxAttempts = isset($args[1]) && is_numeric($args[1]) ? (int) $args[1] : null;
        $windowSeconds = isset($args[2]) && is_numeric($args[2]) ? (int) $args[2] : null;

        // Check per-model rate limit if a model slug is in the request
        if ($category === 'text_gen' && ! $maxAttempts) {
            $modelSlug = $request->input('model') ?? $request->input('fields.model');
            if ($modelSlug) {
                $model = AiModel::where('slug', $modelSlug)->active()->first();
                if ($model && $model->rate_limit_per_min) {
                    $maxAttempts = $model->rate_limit_per_min;
                    $windowSeconds = 60;
                }
            }
        }

        // Public tool rate limit for guest IPs
        if ($category === 'public_tool' && ! $request->user() && ! $maxAttempts) {
            $maxAttempts = (int) settings('public_tool_rate_limit_per_hour', 5);
            $windowSeconds = 3600;
        }

        $user = $request->user();
        if ($user && ! $user instanceof \App\Models\User) {
            $user = null;
        }
        $ip = $request->ip();

        if ($this->rateLimiter->isIpBanned($ip)) {
            return response()->json([
                'success' => false,
                'message' => translate('Your IP has been temporarily blocked due to abuse.'),
            ], 429)->withHeaders([
                'Retry-After' => '3600',
            ]);
        }

        $key = $this->buildKey($request, $category);

        $result = $this->rateLimiter->attempt($category, $key, $maxAttempts, $windowSeconds, $user);

        if (! $result['allowed']) {
            return response()->json([
                'success' => false,
                'code' => 'RATE_LIMITED',
                'message' => translate('Too many requests. Please try again in :seconds seconds.', [
                    'seconds' => $result['retry_after_seconds'],
                ]),
                'retry_after' => $result['retry_after_seconds'],
            ], 429)->withHeaders($this->rateLimitHeaders($result));
        }

        if ($category === 'text_gen') {
            $this->rateLimiter->checkAiAbuse($ip, $category);
        }

        $response = $next($request);

        return $this->attachHeaders($response, $result);
    }

    private function buildKey(Request $request, string $category): string
    {
        $ip = $request->ip();

        return match ($category) {
            'text_gen' => ($request->user()?->ulid ?? 'ip:'.$ip).'|tool:'.($request->input('slug') ?? '_'),
            'auth' => strtolower((string) ($request->input('email') ?? '')).'|'.$ip,
            'otp' => ($request->session()->get('admin_2fa_id')
                ?? $request->session()->get('user_2fa_id')
                ?? $request->input('email')
                ?? 'na').'|'.$ip,
            'contact' => $ip,
            'comments' => $request->user()?->ulid ?? 'ip:'.$ip,
            'newsletter' => strtolower((string) $request->input('email')).'|'.$ip,
            'social_auth' => ($request->route('provider') ?? '').'|'.$ip,
            'public' => $ip,
            default => $ip,
        };
    }

    private function rateLimitHeaders(array $result): array
    {
        $headers = [
            'Retry-After' => (string) $result['retry_after_seconds'],
            'X-RateLimit-Limit' => (string) $result['limit'],
            'X-RateLimit-Remaining' => (string) $result['remaining'],
            'X-RateLimit-Reset' => (string) $result['reset_at'],
        ];

        return $headers;
    }

    private function attachHeaders(mixed $response, array $result): mixed
    {
        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', (string) $result['limit']);
            $response->header('X-RateLimit-Remaining', (string) $result['remaining']);
            $response->header('X-RateLimit-Reset', (string) $result['reset_at']);
        }

        return $response;
    }
}
