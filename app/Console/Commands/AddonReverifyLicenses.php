<?php

namespace App\Console\Commands;

use App\Models\AddonLicense;
use App\Services\AddonLicenseService;
use Illuminate\Console\Command;

class AddonReverifyLicenses extends Command
{
    protected $signature = 'addon:reverify-licenses';

    protected $description = 'Re-verify all addon licenses against the Envato API (daily at 03:00, queue: low).';

    public function handle(AddonLicenseService $service): int
    {
        $licenses = AddonLicense::where('status', '!=', 'invalid')
            ->where('verified_at', '<', now()->subDays(
                (int) settings('addon_license_recheck_days', 7)
            ))
            ->get();

        if ($licenses->isEmpty()) {
            $this->info('No addon licenses need re-verification.');

            return self::SUCCESS;
        }

        $this->info("Re-verifying {$licenses->count()} addon licenses...");

        $invalidCount = 0;
        $graceCount = 0;

        foreach ($licenses as $license) {
            try {
                $service->reverify($license->addon_slug);

                // Re-check after reverify
                $license->refresh();
                if ($license->status === 'invalid') {
                    $invalidCount++;
                } elseif ($license->status === 'grace') {
                    $graceCount++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to re-verify addon '{$license->addon_slug}': {$e->getMessage()}");
            }
        }

        $this->info(
            "Re-verification complete. " .
            "Grace: {$graceCount}. Invalidated: {$invalidCount}."
        );

        return self::SUCCESS;
    }
}
