<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupExpiredOtps extends Command
{
    protected $signature = 'accounts:cleanup-expired-otps';

    protected $description = 'Clear expired OTP codes from user accounts.';

    public function handle(): int
    {
        $expired = User::whereNotNull('otp_code')
            ->where('otp_expires_at', '<', now())
            ->count();

        if ($expired === 0) {
            $this->info('No expired OTP codes found.');

            return self::SUCCESS;
        }

        User::whereNotNull('otp_code')
            ->where('otp_expires_at', '<', now())
            ->update([
                'otp_code' => null,
                'otp_expires_at' => null,
                'otp_attempts' => 0,
                'otp_locked_until' => null,
            ]);

        $this->info("Cleared {$expired} expired OTP code(s).");

        return self::SUCCESS;
    }
}
