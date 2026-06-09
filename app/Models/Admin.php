<?php

namespace App\Models;

use App\Services\Security\TotpService;
use App\Traits\HasRBAC;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class Admin extends Authenticatable
{
    use HasRBAC, Notifiable, SoftDeletes;

    protected $guard = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'role_id',
        'is_active', 'must_change_password',
        'two_factor_secret', 'two_factor_enabled',
        'two_factor_confirmed_at', 'two_factor_recovery_codes',
        'otp_secret', 'otp_expires_at',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'otp_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'otp_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get admin's display name with role.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name.' ('.($this->role?->name ?? 'No Role').')';
    }

    /**
     * Verify an email OTP code.
     */
    public function verifyOtp(string $code): bool
    {
        if (! $this->otp_secret || ! $this->otp_expires_at) {
            return false;
        }

        if (now()->isAfter($this->otp_expires_at)) {
            return false;
        }

        return Hash::check($code, $this->otp_secret);
    }

    /**
     * Generate and store a new email OTP.
     */
    public function generateOtp(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'otp_secret' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /**
     * Clear OTP after use.
     */
    public function clearOtp(): void
    {
        $this->update([
            'otp_secret' => null,
            'otp_expires_at' => null,
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

    /**
     * Record a login event.
     */
    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }
}
