<?php

namespace App\Console\Commands;

use App\Services\AddonLicenseService;
use Illuminate\Console\Command;

class AddonCheckUpdates extends Command
{
    protected $signature = 'addons:check-updates';

    protected $description = 'Check the license server for available updates to installed, licensed addons';

    public function handle(AddonLicenseService $service): int
    {
        $results = $service->checkAllAddonUpdates();

        $available = collect($results)->filter(fn ($r) => ! empty($r['update_available']));

        $this->info(sprintf(
            'Checked %d addon(s); %d update(s) available.',
            count($results),
            $available->count(),
        ));

        foreach ($available as $slug => $r) {
            $this->line("  • {$slug} → v{$r['latest_version']}");
        }

        return self::SUCCESS;
    }
}
