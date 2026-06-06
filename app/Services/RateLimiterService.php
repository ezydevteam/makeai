<?php

namespace App\Services;

use App\Models\BannedIp;
use App\Models\User;
use App\Models\UserRateLimitOverride;
use Illuminate\Support\Facades\Redis;

class RateLimiterService
{
    private const PREFIX = 'rl:';

    private array $defaults = [
        'text_gen' => [
            'guest' => ['max_attempts' => 5, 'window_seconds' => 3600],
            'free_user' => ['max_attempts' => 30, 'window_seconds' => 60],
            'pro_user' => ['max_attempts' => 120, 'window_seconds' => 60],
        ],
        'auth' => [
            'guest' => ['max_attempts' => 5, 'window_seconds' => 900],
            'free_user' => ['max_attempts' => 10, 'window_seconds' => 900],
            'pro_user' => ['max_attempts' => 20, 'window_seconds' => 900],
        ],
        'otp' => [
            'guest' => ['max_attempts' => 5, 'window_seconds' => 900],
            'free_user' => ['max_attempts' => 5, 'window_seconds' => 900],
            'pro_user' => ['max_attempts' => 5, 'window_seconds' => 900],
        ],
        'contact' => [
            'guest' => ['max_attempts' => 3, 'window_seconds' => 3600],
            'free_user' => ['max_attempts' => 5, 'window_seconds' => 3600],
            'pro_user' => ['max_attempts' => 10, 'window_seconds' => 3600],
        ],
        'comments' => [
            'guest' => ['max_attempts' => 5, 'window_seconds' => 60],
            'free_user' => ['max_attempts' => 10, 'window_seconds' => 60],
            'pro_user' => ['max_attempts' => 20, 'window_seconds' => 60],
        ],
        'newsletter' => [
            'guest' => ['max_attempts' => 3, 'window_seconds' => 3600],
            'free_user' => ['max_attempts' => 3, 'window_seconds' => 3600],
            'pro_user' => ['max_attempts' => 3, 'window_seconds' => 3600],
        ],
        'public' => [
            'guest' => ['max_attempts' => 5, 'window_seconds' => 3600],
            'free_user' => ['max_attempts' => 15, 'window_seconds' => 3600],
            'pro_user' => ['max_attempts' => 30, 'window_seconds' => 3600],
        ],
        'social_auth' => [
            'guest' => ['max_attempts' => 10, 'window_seconds' => 300],
            'free_user' => ['max_attempts' => 10, 'window_seconds' => 300],
            'pro_user' => ['max_attempts' => 10, 'window_seconds' => 300],
        ],
    ];

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function attempt(string $category, string $key, ?int $maxAttempts = null, ?int $windowSeconds = null, ?User $user = null): array
    {
        $tier = $this->resolveTier($user);
        $limits = $this->getLimitForTier($category, $tier, $user);

        $maxAttempts = $maxAttempts ?? $limits['max_attempts'];
        $windowSeconds = $windowSeconds ?? $limits['window_seconds'];

        $redisKey = $this->buildRedisKey($category, $key);

        return $this->slidingWindowCheck($redisKey, $maxAttempts, $windowSeconds, false);
    }

    public function hit(string $category, string $key, ?int $windowSeconds = null): void
    {
        $windowSeconds = $windowSeconds ?? 900;
        $redisKey = $this->buildRedisKey($category, $key);

        $now = microtime(true);

        Redis::pipeline(function ($pipe) use ($redisKey, $now, $windowSeconds) {
            $pipe->zremrangebyscore($redisKey, '-inf', $now - $windowSeconds);
            $pipe->zadd($redisKey, $now, $now.uniqid('', true));
            $pipe->expire($redisKey, $windowSeconds + 1);
        });
    }

    public function clear(string $category, string $key): void
    {
        $redisKey = $this->buildRedisKey($category, $key);
        Redis::del($redisKey);
    }

    public function status(string $category, string $key, ?int $maxAttempts = null, ?int $windowSeconds = null, ?User $user = null): array
    {
        $tier = $this->resolveTier($user);
        $limits = $this->getLimitForTier($category, $tier, $user);

        $maxAttempts = $maxAttempts ?? $limits['max_attempts'];
        $windowSeconds = $windowSeconds ?? $limits['window_seconds'];

        $redisKey = $this->buildRedisKey($category, $key);

        return $this->slidingWindowCheck($redisKey, $maxAttempts, $windowSeconds, true);
    }

    public function banIp(string $ip, string $reason, string $category, ?int $adminId = null, ?int $expiresInSeconds = null): void
    {
        $expiresAt = $expiresInSeconds ? now()->addSeconds($expiresInSeconds) : null;

        BannedIp::updateOrCreate(
            ['ip_address' => $ip],
            [
                'reason' => $reason,
                'category' => $category,
                'banned_at' => now(),
                'expires_at' => $expiresAt,
                'banned_by' => $adminId,
            ]
        );
    }

    public function unbanIp(string $ip): void
    {
        BannedIp::where('ip_address', $ip)->delete();
    }

    public function isIpBanned(string $ip): bool
    {
        return BannedIp::where('ip_address', $ip)->active()->exists();
    }

    public function checkAiAbuse(string $ip, string $category): bool
    {
        $threshold = (int) settings('rl_ai_abuse_threshold', 100);
        $windowMinutes = (int) settings('rl_ai_abuse_window', 60);
        $banDuration = (int) settings('rl_ai_abuse_ban_duration', 86400);

        $abuseKey = self::PREFIX."abuse:{$category}:ip:{$ip}";
        $windowSeconds = $windowMinutes * 60;
        $now = microtime(true);

        Redis::zremrangebyscore($abuseKey, '-inf', $now - $windowSeconds);
        Redis::pipeline(function ($pipe) use ($abuseKey, $now, $windowSeconds) {
            $pipe->zadd($abuseKey, $now, $now.uniqid('', true));
            $pipe->expire($abuseKey, $windowSeconds + 1);
        });

        $count = Redis::zcard($abuseKey);

        if ($count >= $threshold) {
            $this->banIp($ip, "AI abuse: {$count} rate limit hits in {$windowMinutes} min", $category, null, $banDuration);
            Redis::del($abuseKey);

            return true;
        }

        return false;
    }

    private function resolveTier(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        if (isProAvailable() && $user->isPro()) {
            return 'pro_user';
        }

        return 'free_user';
    }

    private function getLimitForTier(string $category, string $tier, ?User $user): array
    {
        $override = $this->getUserOverride($user, $category);

        if ($override) {
            return $override;
        }

        $maxKey = "rl_{$category}_{$tier}_max";
        $windowKey = "rl_{$category}_{$tier}_window";

        $maxAttempts = (int) settings($maxKey);
        $windowSeconds = (int) settings($windowKey);

        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return $this->defaults[$category][$tier] ?? ['max_attempts' => 10, 'window_seconds' => 60];
        }

        return [
            'max_attempts' => $maxAttempts,
            'window_seconds' => $windowSeconds,
        ];
    }

    private function getUserOverride(?User $user, string $category): ?array
    {
        if (! $user) {
            return null;
        }

        $override = UserRateLimitOverride::where('user_id', $user->id)
            ->where('category', $category)
            ->active()
            ->first();

        if (! $override) {
            return null;
        }

        return [
            'max_attempts' => $override->max_attempts,
            'window_seconds' => $override->window_seconds,
        ];
    }

    private function slidingWindowCheck(string $redisKey, int $maxAttempts, int $windowSeconds, bool $readOnly): array
    {
        $now = microtime(true);

        Redis::zremrangebyscore($redisKey, '-inf', $now - $windowSeconds);
        $count = Redis::zcard($redisKey);

        if (! $readOnly && $count < $maxAttempts) {
            Redis::pipeline(function ($pipe) use ($redisKey, $now, $windowSeconds) {
                $pipe->zadd($redisKey, $now, $now.uniqid('', true));
                $pipe->expire($redisKey, $windowSeconds + 1);
            });
        }

        $remaining = max(0, $maxAttempts - $count - ($readOnly ? 0 : ($count < $maxAttempts ? 1 : 0)));
        $allowed = $count < $maxAttempts;

        $retryAfter = 0;
        $resetAt = (int) ($now + $windowSeconds);

        if (! $allowed) {
            $oldest = Redis::zrange($redisKey, 0, 0, ['WITHSCORES' => true]);
            if (! empty($oldest)) {
                $oldestTime = (float) array_values($oldest)[0];
                $retryAfter = max(1, (int) ceil($oldestTime + $windowSeconds - $now));
            }
        }

        return [
            'allowed' => $allowed,
            'limit' => $maxAttempts,
            'remaining' => $remaining,
            'reset_at' => $resetAt,
            'retry_after_seconds' => $retryAfter,
        ];
    }

    private function buildRedisKey(string $category, string $key): string
    {
        return self::PREFIX."{$category}:{$key}";
    }
}
