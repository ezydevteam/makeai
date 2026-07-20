<?php

namespace App\Console\Commands;

use App\Services\ThemeLicenseService;
use Illuminate\Console\Command;

class ThemeCheckUpdates extends Command
{
    protected $signature = 'themes:check-updates';

    protected $description = 'Check the License Server for updates to installed, licensed themes';

    public function handle(ThemeLicenseService $service): int
    {
        $results = $service->checkAllThemeUpdates();
        $available = collect($results)->filter(fn ($r) => ! empty($r['update_available']));

        $this->info(sprintf('Checked %d theme(s); %d update(s) available.', count($results), $available->count()));

        foreach ($available as $slug => $r) {
            $this->line("  • {$slug} → v{$r['latest_version']}");
        }

        return self::SUCCESS;
    }
}
