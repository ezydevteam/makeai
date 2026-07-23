<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExportsCommand extends Command
{
    protected $signature = 'exports:cleanup {--days= : Override the retention period in days}';
    protected $description = 'Delete export files older than the configured retention period';

    public function handle(): void
    {
        // Retention in days: CLI override wins, else the admin-configurable setting,
        // else 30. A value < 1 disables cleanup (keep exports indefinitely) so an
        // accidental 0 can never wipe the whole Recent Exports history.
        $days = (int) ($this->option('days') ?: settings('export_retention_days', 30));

        if ($days < 1) {
            $this->info('Export cleanup disabled (retention set to keep indefinitely).');

            return;
        }

        $disk = Storage::disk('local');
        $files = $disk->allFiles('exports');
        $cutoff = now()->subDays($days);

        $deleted = 0;
        foreach ($files as $file) {
            $lastModified = \Carbon\Carbon::createFromTimestamp($disk->lastModified($file));
            if ($lastModified->lt($cutoff)) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Cleaned up {$deleted} export files older than {$days} days.");
    }
}
