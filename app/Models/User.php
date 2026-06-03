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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * MakeAI User model — aligned with AI_SaaS_Master_Prompt Part 4.1.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'ulid', 'avatar',
        'credits', 'credits_used_today', 'credits_used_month',
        'daily_limit', 'monthly_limit',
        'plan_id', 'subscription_status', 'subscription_ends_at', 'trial_ends_at',
        'referral_code', 'affiliate_custom_slug', 'referred_by', 'referral_earnings', 'referral_count',
        'theme_preference', 'locale', 'timezone',
        'personal_api_keys', 'brand_voice',
        'is_active', 'is_banned', 'ban_reason',
        'otp_code', 'otp_expires_at', 'otp_attempts', 'otp_locked_until',
        'two_factor_secret', 'two_factor_enabled', 'two_factor_confirmed_at', 'two_factor_recovery_codes',
        'login_attempts', 'locked_until',
        'oauth_provider', 'oauth_id',
        'last_login_at', 'last_login_ip', 'email_marketing',
    ];

    protected $hidden = [
        'password', 'remember_token', 'otp_code', 'two_factor_secret', 'two_factor_recovery_codes', 'personal_api_keys',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'credits' => 'decimal:4',
            'credits_used_today' => 'decimal:4',
            'credits_used_month' => 'decimal:4',
            'daily_limit' => 'decimal:4',
            'monthly_limit' => 'decimal:4',
            'referral_earnings' => 'decimal:4',
            'is_active' => 'boolean',
            'is_banned' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'array',
            'email_marketing' => 'boolean',
            'otp_expires_at' => 'datetime',
            'otp_locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'personal_api_keys' => 'encrypted:array',
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
                $user->referral_code = strtoupper(Str::random(8));
            }
            if (is_null($user->credits)) {
                $user->credits = (float) settings('default_credits_new_user', 100);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    // ─── Relationships ──────────────────────────

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
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
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
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
            app(NotificationEventService::class)->creditBalanceChanged($this, (float) $this->credits);
        }

        return $deducted;
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

        app(NotificationEventService::class)->creditsAdded($this->fresh(), $amount, $reason);
    }

    // ─── Login ──────────────────────────────────

    public function recordLogin(string $ip, ?string $userAgent = null): void
    {
        $this->update(['last_login_at' => now(), 'last_login_ip' => $ip, 'login_attempts' => 0]);

        $this->loginHistory()->create([
            'ip' => $ip,
            'user_agent' => $userAgent,
            'success' => true,
        ]);
    }

    // ─── Subscription ───────────────────────────

    public function isSubscribed(): bool
    {
        return in_array($this->subscription_status, ['active', 'trialing']);
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isPro(): bool
    {
        if ($this->isSubscribed()) {
            return true;
        }

        if ($this->plan && ! $this->plan->is_free) {
            return true;
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

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}
