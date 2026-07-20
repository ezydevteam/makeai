<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExportsCommand extends Command
{
    protected $signature = 'exports:cleanup';
    protected $description = 'Delete export files older than 24 hours';

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $files = $disk->allFiles('exports');
        $cutoff = now()->subHours(24);

        $deleted = 0;
        foreach ($files as $file) {
            $lastModified = \Carbon\Carbon::createFromTimestamp($disk->lastModified($file));
            if ($lastModified->lt($cutoff)) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Cleaned up {$deleted} old export files.");
    }
}
