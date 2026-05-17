<?php

namespace App\Models;

use App\Traits\HasRBAC;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasRBAC, Notifiable;

    protected $guard = 'admin';

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'role_id',
        'is_active', 'two_factor_secret', 'two_factor_enabled',
        'otp_secret', 'otp_expires_at',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'otp_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'otp_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
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

        return $this->otp_secret === $code;
    }

    /**
     * Generate and store a new email OTP.
     */
    public function generateOtp(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'otp_secret' => $code,
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
