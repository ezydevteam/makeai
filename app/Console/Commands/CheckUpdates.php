<?php

namespace App\Console\Commands;

use App\Services\UpdateService;
use Illuminate\Console\Command;

class CheckUpdates extends Command
{
    protected $signature = 'updates:check';
    protected $description = 'Check the license server for a new core version and set the update_available flag';

    public function handle(UpdateService $updates): int
    {
        $this->info('Checking for updates...');

        try {
            $manifest = $updates->checkForUpdate();

            if (! empty($manifest['update_available'])) {
                $this->info('Update available: v' . ($manifest['latest_version'] ?? '?'));
            } else {
                $this->info('You are up to date.');
            }
        } catch (\Throwable $e) {
            // Transient (no license yet, network, unconfigured key) — never fatal;
            // the flag simply stays as-is until the next run.
            $this->warn('Update check skipped: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
