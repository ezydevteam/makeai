<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTempFiles extends Command
{
    protected $signature = 'system:cleanup-temp-files';

    protected $description = 'Remove temporary files older than 24 hours from storage/app/temp/.';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = time() - 86400;

        $deleted = 0;

        foreach (['temp', 'tmp', 'temp/ingest'] as $dir) {
            if (! $disk->exists($dir)) {
                continue;
            }

            $files = $disk->allFiles($dir);

            foreach ($files as $file) {
                $modified = $disk->lastModified($file);
                if ($modified < $cutoff) {
                    $disk->delete($file);
                    $deleted++;
                }
            }

            // Clean empty subdirectories
            $dirs = $disk->allDirectories($dir);
            foreach ($dirs as $subdir) {
                if (empty($disk->allFiles($subdir)) && empty($disk->allDirectories($subdir))) {
                    $disk->deleteDirectory($subdir);
                }
            }
        }

        $this->info("Cleaned up {$deleted} temporary file(s).");

        return self::SUCCESS;
    }
}
