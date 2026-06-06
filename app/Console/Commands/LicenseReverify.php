<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use App\Services\NotificationEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class LicenseReverify extends Command
{
    protected $signature = 'license:reverify';
    protected $description = 'Re-verify the active Envato license and manage grace period';

    public function handle(LicenseService $licenseService, NotificationEventService $notifications): int
    {
        if (! license_verified()) {
            $this->warn('No active license to re-verify.');

            return self::SUCCESS;
        }

        $this->info('Re-verifying license against Envato API...');

        $result = $licenseService->reverify();

        if ($result) {
            $this->info('License re-verified successfully.');

            Log::info('LicenseReverify: License re-verified successfully.');

            return self::SUCCESS;
        }

        // Re-verify failed — handle grace period tracking
        $graceStart = settings('license_grace_start');
        $graceHours = config('license.grace_period', 72);

        if (blank($graceStart)) {
            // Grace period just started
            $this->warn("Re-verification failed. Grace period started ({$graceHours}h).");
            $notifications->licenseGracePeriod((int) ceil($graceHours / 24));

            Log::warning('LicenseReverify: Re-verification failed. Grace period started.', [
                'grace_hours' => $graceHours,
            ]);

            return self::SUCCESS;
        }

        $startedAt = Carbon::parse($graceStart);
        $expiresAt = $startedAt->copy()->addHours($graceHours);
        $remainingHours = max(0, (int) now()->diffInHours($expiresAt, false));

        if ($remainingHours > 0) {
            $this->warn("Re-verification still failing. {$remainingHours}h remaining in grace period.");
            $notifications->licenseGracePeriod((int) ceil($remainingHours / 24));

            Log::warning('LicenseReverify: Re-verification still failing. Grace period active.', [
                'remaining_hours' => $remainingHours,
            ]);

            return self::SUCCESS;
        }

        // Grace period expired — BLOCK THE ENTIRE FRONTEND
        $this->error('Grace period expired. License invalid — frontend blocked.');

        Log::critical('LicenseReverify: Grace period expired. Deactivating license and disabling all features.');

        $notifications->licenseGracePeriod(0);

        return self::FAILURE;
    }
}
