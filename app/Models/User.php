<?php

namespace App\Models;

use App\Services\NotificationEventService;
use App\Services\Security\TotpService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Throwable;
use Addons\AiChatbot\Models\Conversation;
use Addons\AiChatbot\Models\ChatProject;
use Addons\AiChatbot\Models\ConversationTag;

/**
 * MakeAI User model — aligned with AI_SaaS_Master_Prompt Part 4.1.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, Billable;

    protected $fillable = [
        'name', 'email', 'password', 'password_changed_at', 'ulid', 'avatar',
        'country', 'profession', 'phone', 'phone_country', 'sms_marketing_opt_in',
        'credits', 'credits_used_today', 'credits_used_month',
        'daily_limit', 'monthly_limit',
        'plan_id', 'subscription_status', 'subscription_ends_at',
        'email_verified_at', 'trial_ends_at',
        'referral_code', 'affiliate_custom_slug', 'referred_by', 'affiliate_banned',
        'theme_preference', 'locale', 'timezone',
        'preferences', 'brand_voice', 'chat_custom_instructions',
        'stripe_id', 'pm_type', 'pm_last_four',
        'is_active', 'has_trialed', 'is_banned', 'ban_reason', 'banned_at',
        'otp_code', 'otp_expires_at', 'otp_attempts', 'otp_locked_until',
        'two_factor_secret', 'two_factor_enabled', 'two_factor_confirmed_at', 'two_factor_recovery_codes',
        'login_attempts', 'locked_until',
        'oauth_provider', 'oauth_id',
        'last_login_at', 'last_login_ip', 'email_marketing',
        'cookie_consent', 'allow_data_improve', 'scheduled_deletion_at',
        'onboarding_completed_at', 'use_case', 'dismissed_tooltips',
    ];

    protected $hidden = [
        'password', 'remember_token', 'otp_code', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'credits' => 'decimal:4',
            'credits_used_today' => 'decimal:4',
            'credits_used_month' => 'decimal:4',
            'daily_limit' => 'decimal:4',
            'monthly_limit' => 'decimal:4',
            'referral_earnings' => 'decimal:4',
            'is_active' => 'boolean',
            // Intentionally NOT in $fillable. This flag exempts an account from credit
            // limits and makes it undeletable, so it must never be settable by mass
            // assignment from request data. User::internalAi() sets it explicitly.
            'is_internal' => 'boolean',
            'has_trialed' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'affiliate_banned' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'array',
            'preferences' => 'array',
            'email_marketing' => 'boolean',
            'cookie_consent' => 'array',
            'allow_data_improve' => 'boolean',
            'scheduled_deletion_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_locked_until' => 'datetime',
            'phone_verified_at' => 'datetime',
            'sms_marketing_opt_in' => 'boolean',
            'last_login_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'dismissed_tooltips' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Auto-generate ULID + referral code on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->ulid)) {
                $user->ulid = (string) Str::ulid();
            }
            if (empty($user->referral_code)) {
                do {
                    $user->referral_code = strtoupper(Str::random(8));
                } while (User::withTrashed()->where('referral_code', $user->referral_code)->exists());
            }
            if (is_null($user->credits)) {
                $user->credits = (float) settings('default_credits_new_user', 100);
            }
        });

        static::saving(function (User $user) {
            // Stamp when a ban is applied and clear it when lifted, so admin
            // analytics can measure banning activity over time. Covers every
            // model save path (mass-assignment, fill+save, ticket ban toggle).
            if ($user->isDirty('is_banned')) {
                $user->banned_at = $user->is_banned ? now() : null;
            }
        });

        // The internal AI system account cannot be deleted, by anyone, ever.
        //
        // It is not a person — it is the account every admin AI feature bills against
        // (assistant, blog/page/FAQ assist, mail templates, ticket replies, translations),
        // and it owns their whole usage history. Deleting it would orphan every one of those
        // ai_usage_logs rows and silently break admin AI on the next request.
        //
        // Returning false cancels the delete. This covers $user->delete() and
        // $user->forceDelete() — but NOT a mass-delete query like
        // User::whereIn(...)->delete(), which bypasses model events entirely. Those paths
        // must use the excludingInternal() scope; see UserManagementController.
        static::deleting(function (User $user) {
            if ($user->isInternalAi()) {
                return false;
            }
        });
    }

    /**
     * Hide the internal AI system account from anything a human is meant to look at or act on:
     * the users table, the trash, user stats, exports, bulk actions.
     *
     * Deliberately a local scope rather than a global one. A global scope would also hide the
     * account from internalAi() itself — whose firstOrCreate would then stop finding the
     * existing row and mint a duplicate on every admin AI call — and from TokenGuard's own
     * lookups. It must stay invisible to the admin UI and fully visible to the system.
     */
    public function scopeExcludingInternal(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_internal', false);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Apply the admin-configured "new user gets" choice (Pricing Settings →
     * registration_default_plan) right after registration.
     *
     *  - 'none' / 'custom'  → keep the default signup wallet (default_credits_new_user);
     *    'custom' means usage is governed by the per-user limits in AI Settings.
     *  - '<plan id>'        → auto-assign that active plan and grant its credits as the
     *    starting wallet (free plans then get a reset-style monthly refresh).
     *
     * Invalid / inactive plan ids fall back to the default safely.
     */
    public function applyRegistrationDefault(): void
    {
        $choice = (string) settings('registration_default_plan', 'none');

        if ($choice === '' || $choice === 'none' || $choice === 'custom' || ! ctype_digit($choice)) {
            return; // default wallet (already set by the creating hook) stands
        }

        $plan = Plan::query()->whereKey((int) $choice)->where('is_active', true)->first();
        if (! $plan) {
            return;
        }

        $this->forceFill([
            'plan_id' => $plan->id,
            'credits' => (float) $plan->credits,
            'credits_used_today' => 0,
            'credits_used_month' => 0,
        ])->save();
    }

    /**
     * Get the internal AI user email (dynamic based on site domain).
     */
    public static function internalAiEmail(): string
    {
        $domain = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?? 'localhost';
        return "internalai@{$domain}";
    }

    /**
     * Get the internal AI user name.
     */
    public static function internalAiName(): string
    {
        return 'Internal AI';
    }

    /**
     * The internal "system" user that backs admin AI-assist (page/blog/FAQ/mail
     * generation). It is not a paying account, so it bypasses per-user credit
     * balances and daily/monthly limits — its usage is still tracked against the
     * global AI budget.
     *
     * Identity is the is_internal COLUMN, not the email. The email is derived from
     * config('app.url'), so the old email-comparison silently stopped recognising the
     * account the moment a buyer changed APP_URL — at which point the system account
     * became an ordinary deletable "customer" in the admin users table and a duplicate
     * was created beside it. A column cannot drift with config.
     */
    public function isInternalAi(): bool
    {
        return (bool) $this->is_internal;
    }

    /**
     * Resolve (creating if needed) the internal "system" user that backs every admin AI task
     * — the assistant's admin chat, blog/page/FAQ/testimonial assist, mail templates, ticket
     * replies and translations.
     *
     * This is the ONLY place the account may be created. Six call sites used to hand-roll the
     * same firstOrCreate, in two different shapes: some set plan_id/subscription_status, the
     * others set is_banned. They all resolved the same row, so whichever admin feature the
     * buyer happened to use first decided what the account looked like — and the fields the
     * other variant set were simply never applied. The payload below is the union of both, so
     * the account is identical on every install regardless of what created it.
     *
     * subscription_status is 'active' deliberately: model/tool access can be gated on a user's
     * standing, and a system account must never be refused a model because it looks lapsed.
     * It is exempt from per-user credits either way (see isInternalAi()).
     */
    public static function internalAi(): self
    {
        // Resolve by FLAG first, so the account survives an APP_URL change: its email would
        // no longer match internalAiEmail(), but it is still the account that owns every
        // admin AI usage log, and we must keep using it rather than mint a second one.
        if ($existing = self::where('is_internal', true)->first()) {
            return self::realignInternalEmail($existing);
        }

        // No flagged account. Before creating one, adopt an orphan left behind by an APP_URL
        // change that happened BEFORE the flag existed — it still owns the whole admin AI
        // usage history, and creating a fresh account beside it would strand that history and
        // leave the old row sitting in the admin users table as a deletable "customer".
        //
        // It is ADOPTED, never deleted: ai_usage_logs.user_id is cascadeOnDelete, so removing
        // the account would silently destroy every admin AI usage record with it.
        $user = self::orphanedInternalAccount() ?? self::firstOrCreate(
            ['email' => self::internalAiEmail()],
            [
                'name' => self::internalAiName(),
                'password' => bcrypt(Str::random(32)),
                'is_active' => true,
                'is_banned' => false,
                'plan_id' => null,
                'subscription_status' => 'active',
            ]
        );

        // is_internal is deliberately not fillable (it must never be mass-assignable from
        // request data), so it is stamped here rather than passed in the payload above.
        if (! $user->is_internal) {
            $user->forceFill(['is_internal' => true])->save();
        }

        return self::realignInternalEmail($user);
    }

    /**
     * A system account stranded by a domain change made before is_internal existed.
     *
     * The match is deliberately narrow. This flag exempts an account from credit limits and
     * makes it undeletable, so a false positive would hand a real person unlimited free AI on
     * an account an admin could never remove. All three conditions must hold — the reserved
     * local part, the exact system name, and no login has ever occurred — which no real user
     * account can satisfy, since nobody signs in as this one.
     */
    private static function orphanedInternalAccount(): ?self
    {
        return self::withTrashed()
            ->where('is_internal', false)
            ->where('email', 'like', 'internalai@%')
            ->where('name', self::internalAiName())
            ->whereNull('last_login_at')
            ->first();
    }

    /**
     * Keep the system account's address on the domain the site actually runs on.
     *
     * Cosmetic, but it stops the admin users table (and any log export) showing an account at
     * a domain the install left behind years ago. Skipped when another row already holds the
     * address, since email is unique — identity is the flag, not the address, so a collision
     * is harmless and simply leaves the old address in place.
     */
    private static function realignInternalEmail(self $user): self
    {
        $expected = self::internalAiEmail();

        if ($user->email === $expected) {
            return $user;
        }

        if (self::withTrashed()->where('email', $expected)->whereKeyNot($user->getKey())->exists()) {
            return $user;
        }

        $user->forceFill(['email' => $expected])->save();

        return $user;
    }

    // ─── OTP ────────────────────────────────────

    public function generateOtp(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes((int) settings('otp_expiry_minutes', 10)),
            'otp_attempts' => 0,
            'otp_locked_until' => null,
        ]);

        return $code;
    }

    public function isOtpLocked(): bool
    {
        return $this->otp_locked_until && now()->isBefore($this->otp_locked_until);
    }

    public function verifyOtp(string $code): bool
    {
        if ($this->isOtpLocked()) {
            return false;
        }

        if (! $this->otp_code || ! $this->otp_expires_at) {
            return false;
        }

        if (now()->isAfter($this->otp_expires_at)) {
            return false;
        }

        if (! Hash::check($code, $this->otp_code)) {
            $maxAttempts = (int) settings('otp_max_attempts', 5);
            $lockoutMinutes = (int) settings('otp_lockout_minutes', 10);
            $attempts = $this->otp_attempts + 1;

            if ($attempts >= $maxAttempts) {
                $this->forceFill([
                    'otp_attempts' => $attempts,
                    'otp_locked_until' => now()->addMinutes($lockoutMinutes),
                ])->save();
            } else {
                $this->increment('otp_attempts');
            }

            return false;
        }

        return true;
    }

    public function clearOtp(): void
    {
        $this->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
            'otp_locked_until' => null,
        ]);
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null && filled($this->phone);
    }

    public function markPhoneAsVerified(): void
    {
        $this->forceFill(['phone_verified_at' => now()])->save();
    }

    public function hasTotpEnabled(): bool
    {
        return $this->two_factor_enabled && filled($this->getTwoFactorSecret());
    }

    public function getTwoFactorSecret(): ?string
    {
        if (! $this->two_factor_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (Throwable) {
            return $this->two_factor_secret;
        }
    }

    public function verifyTotp(string $code, TotpService $totp): bool
    {
        $secret = $this->getTwoFactorSecret();

        if (! $this->hasTotpEnabled() || ! $secret) {
            return false;
        }

        return $totp->verify($secret, $code);
    }

    /**
     * @return array<int, string>
     */
    public function enableTotp(string $secret): array
    {
        $this->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => true,
            'two_factor_channel' => 'totp',
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $this->generateRecoveryCodes();
    }

    /**
     * The user's chosen second-factor channel: 'totp' or 'sms'.
     */
    public function twoFactorChannel(): string
    {
        return $this->two_factor_channel ?: 'totp';
    }

    public function usesSmsTwoFactor(): bool
    {
        return $this->two_factor_enabled && $this->twoFactorChannel() === 'sms';
    }

    /**
     * Switch the account to SMS-delivered 2FA. There is no authenticator secret
     * for this channel — codes are texted to the verified phone at challenge time.
     *
     * @return array<int, string> freshly generated recovery codes
     */
    public function enableSmsTwoFactor(): array
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => true,
            'two_factor_channel' => 'sms',
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $this->generateRecoveryCodes();
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->values()
            ->all();

        $this->forceFill([
            'two_factor_recovery_codes' => collect($codes)
                ->map(fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)))
                ->all(),
        ])->save();

        return $codes;
    }

    public function useRecoveryCode(string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);
        $recoveryCodes = $this->two_factor_recovery_codes ?? [];

        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($normalized, $hashedCode)) {
                unset($recoveryCodes[$index]);

                $this->forceFill([
                    'two_factor_recovery_codes' => array_values($recoveryCodes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function recoveryCodesCount(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    public function disableTotp(): void
    {
        // Fully disables 2FA regardless of channel and resets the channel default.
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_channel' => 'totp',
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return Str::lower(str_replace([' ', '-'], '', $code));
    }

    // ─── Credits ────────────────────────────────

    public function hasCredits(float $amount = 1): bool
    {
        return $this->credits >= $amount;
    }

    public function deductCredits(float $amount, string $reason, array $meta = []): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $deducted = DB::transaction(function () use ($amount, $reason, $meta): bool {
            $user = self::query()->whereKey($this->id)->lockForUpdate()->first();

            if (! $user || (float) $user->credits < $amount) {
                return false;
            }

            $user->decrement('credits', $amount);
            $user->increment('credits_used_today', $amount);
            $user->increment('credits_used_month', $amount);

            $fresh = $user->fresh();

            $user->creditTransactions()->create([
                'amount' => -$amount,
                'balance_after' => $fresh->credits,
                'type' => 'usage',
                'description' => $reason,
                'meta' => $meta,
            ]);

            $this->forceFill([
                'credits' => $fresh->credits,
                'credits_used_today' => $fresh->credits_used_today,
                'credits_used_month' => $fresh->credits_used_month,
            ]);

            return true;
        });

        if ($deducted) {
            // The balance already changed in the DB; a realtime/broadcast outage must
            // never fail a paid deduction.
            try {
                app(NotificationEventService::class)->creditBalanceChanged($this, (float) $this->credits);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $deducted;
    }

    /**
     * Charge credits the mode-correct way. In METERED mode (Extended + billing) this
     * drains the wallet and can fail on an empty balance. In QUOTA mode (Regular
     * license) it meters the resetting allowance instead and NEVER fails on balance.
     *
     * This is the safe entry point for any per-use charge outside TokenGuard's own
     * paths (e.g. RAG ingestion/retrieval) so credits behave consistently with the
     * license mode. Returns whether the charge was applied.
     */
    public function chargeCredits(float $amount, string $reason, array $meta = []): bool
    {
        if ($amount <= 0) {
            return true;
        }

        if (credit_quota_mode()) {
            $this->trackQuotaUsage($amount, $reason, $meta);

            return true;
        }

        return $this->deductCredits($amount, $reason, $meta);
    }

    /**
     * Meter usage against the resetting daily/monthly allowance WITHOUT draining the
     * persistent wallet. Used in quota mode (Regular license) where credits are a
     * refilling quota, not a purchasable balance — so a run never fails on balance and
     * `credits` is left untouched. The daily/monthly counters (which the allowance is
     * measured against) still advance and reset on schedule.
     */
    public function trackQuotaUsage(float $amount, string $reason, array $meta = []): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($amount, $reason, $meta): void {
            $user = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            $user->increment('credits_used_today', $amount);
            $user->increment('credits_used_month', $amount);

            $fresh = $user->fresh();

            $user->creditTransactions()->create([
                'amount' => -$amount,
                'balance_after' => $fresh->credits, // unchanged — quota usage, not a wallet spend
                'type' => 'usage',
                'description' => $reason,
                'meta' => $meta,
            ]);

            $this->forceFill([
                'credits_used_today' => $fresh->credits_used_today,
                'credits_used_month' => $fresh->credits_used_month,
            ]);
        });
    }

    /**
     * Refund a prior charge the mode-correct way. In METERED mode the wallet was
     * drained, so credits are returned to it. In QUOTA mode the daily/monthly
     * allowance was consumed (not the wallet), so the usage counters are wound back
     * instead — otherwise a failed run would keep eating the user's allowance.
     */
    public function refundCredits(float $amount, string $reason, array $meta = []): void
    {
        if ($amount <= 0) {
            return;
        }

        if (! credit_quota_mode()) {
            $this->addCredits($amount, 'refund', $reason, $meta);

            return;
        }

        DB::transaction(function () use ($amount, $reason, $meta): void {
            $user = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if (! $user) {
                return;
            }

            // Never drive the counters below zero (partial-day / cross-reset refunds).
            $user->decrement('credits_used_today', min($amount, (float) $user->credits_used_today));
            $user->decrement('credits_used_month', min($amount, (float) $user->credits_used_month));

            $fresh = $user->fresh();

            $user->creditTransactions()->create([
                'amount' => $amount,
                'balance_after' => $fresh->credits, // unchanged — quota refund, not a wallet credit
                'type' => 'refund',
                'description' => $reason,
                'meta' => $meta,
            ]);

            $this->forceFill([
                'credits_used_today' => $fresh->credits_used_today,
                'credits_used_month' => $fresh->credits_used_month,
            ]);
        });
    }

    /**
     * Grant a plan's credit allowance on activation / renewal (paid or trial).
     *
     * Reset-style but safe on a single wallet: tops the balance UP to the plan's
     * credits when it's below (a spent-down allowance is refreshed), and never
     * REDUCES a balance boosted by top-ups or admin grants. No-op for unlimited /
     * zero-credit plans. Logged as a 'purchase' credit transaction.
     */
    public function grantPlanAllowance(float $planCredits, string $reason): void
    {
        if ($planCredits <= 0) {
            return;
        }

        $current = (float) $this->fresh()->credits;
        $delta = round($planCredits - $current, 4);

        if ($delta <= 0) {
            return; // already at or above the allowance (top-ups / admin grants)
        }

        $this->addCredits($delta, 'purchase', $reason, ['plan_credits' => $planCredits]);
    }

    public function addCredits(float $amount, string $type, string $reason, array $meta = []): void
    {
        $this->increment('credits', $amount);

        $this->creditTransactions()->create([
            'amount' => $amount,
            'balance_after' => $this->fresh()->credits,
            'type' => $type,
            'description' => $reason,
            'meta' => $meta,
        ]);

        try {
            app(NotificationEventService::class)->creditsAdded($this->fresh(), $amount, $reason);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ─── Login ──────────────────────────────────

    public function recordLogin(string $ip, ?string $userAgent = null): void
    {
        $this->update(['last_login_at' => now(), 'last_login_ip' => $ip, 'login_attempts' => 0]);

        // Best-effort geo (populates country/city only when an IPInfo token is
        // configured; instant no-op otherwise, so it never adds login latency).
        $geo = \App\Services\IpGeolocationService::fromSettings()->lookupLocation($ip);

        $this->loginHistory()->create([
            'ip' => $ip,
            'user_agent' => $userAgent,
            'country' => $geo['country'],
            'city' => $geo['city'],
            'success' => true,
        ]);
    }

    // ─── Subscription ───────────────────────────

    public function isSubscribed(): bool
    {
        return $this->subscribed();
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    // ─── Pro Status ───────────────────────────

    public function isPro(): bool
    {
        if ($this->subscribed()) {
            return true;
        }

        if ($this->plan && ! $this->plan->is_free) {
            // NULL subscription_ends_at means unlimited access (lifetime plans and
            // admin-granted plans). A past date means the subscription lapsed and
            // access is revoked even before the expiry cron has run.
            return $this->subscription_ends_at === null || $this->subscription_ends_at->isFuture();
        }

        return false;
    }

    // ─── Account Lock ───────────────────────────

    public function isLocked(): bool
    {
        return $this->locked_until && now()->isBefore($this->locked_until);
    }

    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');
        if ($this->login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(15)]);
        }
    }

    /**
     * Default notification preferences structure.
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'in_app' => [
                'billing' => true,
                'content' => true,
                'security' => true,
                'affiliate' => true,
                'updates' => true,
                'admin' => true,
            ],
            'email' => [
                'billing' => true,
                'content' => true,
                'security' => true,
                'affiliate' => true,
                'updates' => true,
                'admin' => true,
            ],
        ];
    }

    /**
     * Get notification preferences with defaults merged.
     */
    public function getNotificationPreferences(): array
    {
        $defaults = self::defaultNotificationPreferences();
        $current = $this->notification_preferences ?? [];

        return [
            'in_app' => array_merge($defaults['in_app'], $current['in_app'] ?? []),
            'email' => array_merge($defaults['email'], $current['email'] ?? []),
        ];
    }

    /**
     * Check if user wants in-app notification for a group.
     */
    public function wantsInAppNotification(string $group): bool
    {
        $prefs = $this->getNotificationPreferences();
        return $prefs['in_app'][$group] ?? true;
    }

    /**
     * Check if user wants email notification for a group.
     */
    public function wantsEmailNotification(string $group): bool
    {
        $prefs = $this->getNotificationPreferences();
        return $prefs['email'][$group] ?? true;
    }

    // ─── Relationships ───────────────────────────

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function loginHistory()
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function affiliateReferrals()
    {
        return $this->hasMany(AffiliateReferral::class, 'referrer_id');
    }

    public function affiliateCommissions()
    {
        return $this->hasMany(AffiliateCommission::class, 'referrer_id');
    }

    public function affiliatePayouts()
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function byok()
    {
        return $this->hasMany(UserByok::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function chatProjects()
    {
        return $this->hasMany(ChatProject::class);
    }

    public function conversationTags()
    {
        return $this->hasMany(ConversationTag::class);
    }

    public function ragSessions()
    {
        return $this->hasMany(RagSession::class);
    }

    public function passwordHistory()
    {
        return $this->morphMany(PasswordHistory::class, 'user');
    }
}
