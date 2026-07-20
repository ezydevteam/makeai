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

    public function handle(LicenseService $licenseService): int
    {
        $this->info('Running scheduled license re-verification...');

        $licenseService->reverify();

        return self::SUCCESS;
    }
}
