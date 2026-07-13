<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use App\Models\User;
use App\Services\AccessLevelService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Response;

/**
 * Every gate that stands between a user and an operation: access level, daily
 * usage cap, storage quota, retention. All of it reads from admin settings —
 * nothing here decides policy, it only enforces what the operator configured.
 */
class ImageAccessService
{
    public function __construct(
        private OperationRegistry $registry,
        private AccessLevelService $accessLevels,
    ) {
    }

    public function addonEnabled(): bool
    {
        return (bool) addon_setting(OperationRegistry::SLUG, 'enabled', true);
    }

    public function canAccessStudio(?User $user): bool
    {
        return $this->addonEnabled()
            && $this->accessLevels->checkAccess($this->studioAccessLevel(), $user);
    }

    public function canAccessLibrary(?User $user): bool
    {
        return $this->addonEnabled()
            && $this->accessLevels->checkAccess($this->libraryAccessLevel(), $user);
    }

    public function studioAccessLevel(): string
    {
        // Fallback must match addon.json's shipped default. They diverge only on a fresh
        // install where the settings rows have not been seeded yet — and a public landing
        // page that 302s to /login on day one would be worse than useless.
        return $this->level((string) addon_setting(OperationRegistry::SLUG, 'studio_access', 'guest'), 'guest');
    }

    public function libraryAccessLevel(): string
    {
        return $this->level((string) addon_setting(OperationRegistry::SLUG, 'library_access', 'login'), 'login');
    }

    public function canRun(string $operation, ?User $user): bool
    {
        return $this->registry->isUsable($operation)
            && $this->accessLevels->checkAccess($this->registry->accessLevel($operation), $user);
    }

    /**
     * The full pre-flight for an operation request. Order matters: cheapest and
     * most explanatory failure first, so a blocked user learns *why*.
     *
     * @throws HttpResponseException
     */
    public function assertCanRun(string $operation, ?User $user, ?string $ip = null): void
    {
        if (! $this->addonEnabled()) {
            $this->deny(translate('This feature is currently unavailable.'), 403);
        }

        $definition = $this->registry->get($operation);

        if (! $definition || ! $definition['enabled']) {
            $this->deny(translate('This operation is disabled.'), 403);
        }

        if (! $definition['available']) {
            $this->deny(translate('This operation is not configured. Please contact the administrator.'), 503);
        }

        if (! $this->accessLevels->checkAccess($definition['access'], $user)) {
            $level = $definition['access'];

            if ($this->accessLevels->requiresSubscription($level)) {
                $this->deny(translate('This operation requires an active subscription.'), 403);
            }

            $this->deny(translate('Please sign in to use this operation.'), 401);
        }

        $this->assertWithinDailyLimit($user, $ip);
    }

    /**
     * Daily operation cap. Guests and logged-in users have separate admin-set
     * limits; 0 means unlimited for users, and disabled for guests.
     *
     * @throws HttpResponseException
     */
    public function assertWithinDailyLimit(?User $user, ?string $ip = null): void
    {
        if ($user) {
            $limit = $this->dailyLimitFor($user);

            if ($limit <= 0) {
                return;
            }

            $used = AipJob::where('user_id', $user->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            if ($used >= $limit) {
                $this->deny(translate('You have reached today\'s image operation limit.'), 429);
            }

            return;
        }

        $limit = (int) addon_setting(OperationRegistry::SLUG, 'guest_daily_limit', 5);

        if ($limit <= 0) {
            $this->deny(translate('Please sign in to use this operation.'), 401);
        }

        $used = AipJob::whereNull('user_id')
            ->where('guest_ip', $ip)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($used >= $limit) {
            $this->deny(translate('Daily free limit reached. Sign up to keep going.'), 429);
        }
    }

    /**
     * Library storage quota. Counted from stored bytes, not file count, because
     * that's what actually costs the operator money.
     *
     * @throws HttpResponseException
     */
    public function assertWithinStorageQuota(?User $user): void
    {
        $capMb = (int) addon_setting(OperationRegistry::SLUG, 'max_storage_mb_per_user', 0);

        if ($capMb <= 0 || ! $user) {
            return;
        }

        $usedBytes = (int) AipAsset::where('user_id', $user->id)->sum('bytes');

        if ($usedBytes >= $capMb * 1024 * 1024) {
            $this->deny(
                translate('Your image storage is full. Delete some images to free up space.'),
                413,
            );
        }
    }

    public function storageUsedBytes(User $user): int
    {
        return (int) AipAsset::where('user_id', $user->id)->sum('bytes');
    }

    /**
     * When this user's new assets should expire. Stamped at creation, so a later
     * settings change only affects images made from then on. 0 days = keep forever.
     */
    public function expiresAtFor(?User $user): ?Carbon
    {
        $days = $this->retentionDaysFor($user);

        return $days > 0 ? now()->addDays($days) : null;
    }

    /**
     * The retention window for whoever made the image. Three buckets, each its own
     * admin setting:
     *
     *   guest    — no account at all (tracked by IP); ships at 1 day, because a guest
     *              cannot come back for these once their IP rotates and they would
     *              otherwise accumulate on disk forever.
     *   free     — signed in, no paid plan.
     *   premium  — signed in on an active paid plan, and only where subscriptions
     *              exist (see isPaid()). In quota mode nobody is premium, so a
     *              signed-in user falls to the free window.
     */
    public function retentionDaysFor(?User $user): int
    {
        $key = match (true) {
            $user === null => 'retention_days_guest',
            $this->isPaid($user) => 'retention_days_paid',
            default => 'retention_days_free',
        };

        // Guests default to 1 day; the account tiers default to keep-forever.
        $default = $key === 'retention_days_guest' ? 1 : 0;

        return (int) addon_setting(OperationRegistry::SLUG, $key, $default);
    }

    /**
     * "Paid" only means something when subscriptions actually exist.
     *
     * `User::isPro()` answers from the user's own plan/subscription and does NOT
     * consider the license — so on a Regular license (or Extended with subscriptions
     * off) a leftover `plan_id` would still report pro. The core convention is to pair
     * it with the license gate (see PricingController), and this addon must too, or a
     * quota-mode install would silently hand someone paid retention and skip their
     * watermark in a mode where "paid" cannot exist.
     */
    private function isPaid(?User $user): bool
    {
        return $user !== null
            && isProAvailable()
            && method_exists($user, 'isPro')
            && $user->isPro();
    }

    /**
     * The daily operation limit that applies to this signed-in user: a plan-specific
     * override when one is configured for their plan, otherwise the general logged-in
     * limit. 0 (from either) means unlimited.
     *
     * Plans are only meaningful when pro is available — in quota mode every signed-in
     * user falls to the single logged-in limit, matching AccessLevelService, which
     * likewise stops publishing plan levels when subscriptions are off.
     */
    public function dailyLimitFor(User $user): int
    {
        $planSlug = isProAvailable() ? $user->plan?->slug : null;

        if (is_string($planSlug) && $planSlug !== '') {
            $map = addon_setting(OperationRegistry::SLUG, 'plan_daily_limits');

            if (is_string($map)) {
                $map = json_decode($map, true);
            }

            // A configured entry wins — including an explicit 0 (unlimited) that the
            // operator set deliberately for that plan.
            if (is_array($map) && array_key_exists($planSlug, $map) && $map[$planSlug] !== null && $map[$planSlug] !== '') {
                return max(0, (int) $map[$planSlug]);
            }
        }

        return (int) addon_setting(OperationRegistry::SLUG, 'user_daily_limit', 0);
    }

    /**
     * Whether this user's downloads get watermarked. Paid users never do — but only
     * where "paid" exists at all (see isPaid()); in quota mode everyone is free, so
     * the watermark applies to everyone.
     * Applies when a logo is uploaded OR a text watermark is set.
     */
    public function shouldWatermark(?User $user): bool
    {
        if (! (bool) addon_setting(OperationRegistry::SLUG, 'watermark_enabled', false)) {
            return false;
        }

        if ($this->isPaid($user)) {
            return false;
        }

        $hasLogo = trim((string) addon_setting(OperationRegistry::SLUG, 'watermark_logo_path', '')) !== '';
        $hasText = trim((string) addon_setting(OperationRegistry::SLUG, 'watermark_text', '')) !== '';

        return $hasLogo || $hasText;
    }

    private function level(string $candidate, string $fallback): string
    {
        return $this->accessLevels->levelExists($candidate) ? $candidate : $fallback;
    }

    /**
     * @throws HttpResponseException
     */
    private function deny(string $message, int $status): never
    {
        throw new HttpResponseException(
            Response::json(['success' => false, 'message' => $message], $status)
        );
    }
}
