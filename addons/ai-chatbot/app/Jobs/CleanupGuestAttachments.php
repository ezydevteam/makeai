<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Guest chat attachments are stored under chat-attachments/guest_<sessionId>/ and are
 * never tied to an authenticated account, so nothing else ever reclaims them. Without
 * this, guest uploads accumulate on disk forever (a slow disk-exhaustion vector that
 * upload throttling alone does not address). Deletes guest attachment files older than
 * the retention window and prunes emptied directories. Registered-user attachments are
 * left untouched.
 */
class CleanupGuestAttachments implements ShouldQueue
{
    use Queueable;

    private const RETENTION_DAYS = 7;

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $base = 'chat-attachments';

        if (! $disk->exists($base)) {
            return;
        }

        $cutoff = now()->subDays(self::RETENTION_DAYS)->getTimestamp();

        foreach ($disk->directories($base) as $dir) {
            // Only guest directories — registered users' files are retained.
            if (! str_starts_with(basename($dir), 'guest_')) {
                continue;
            }

            $remaining = 0;

            foreach ($disk->files($dir) as $file) {
                if ($disk->lastModified($file) < $cutoff) {
                    $disk->delete($file);
                } else {
                    $remaining++;
                }
            }

            // Prune the guest directory once it holds nothing recent.
            if ($remaining === 0) {
                $disk->deleteDirectory($dir);
            }
        }
    }
}
