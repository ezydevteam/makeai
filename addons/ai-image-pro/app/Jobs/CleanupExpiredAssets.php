<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Jobs;

use Addons\AiImagePro\Models\AipAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The retention sweep. Deletes assets whose admin-set retention window has passed,
 * files first and then the row, in chunks so a large library doesn't load into
 * memory at once. Scheduled daily by the service provider, and only when a
 * retention window is actually configured.
 */
class CleanupExpiredAssets implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct()
    {
        // Set here, not as a property default: Queueable already declares $queue
        // with no default, and redeclaring it with one is a fatal trait-composition
        // error.
        $this->queue = 'media';
    }

    public function handle(): void
    {
        $deleted = 0;

        AipAsset::query()
            ->expired()
            ->chunkById(200, function ($assets) use (&$deleted) {
                foreach ($assets as $asset) {
                    /** @var AipAsset $asset */
                    $asset->deleteFiles();
                    $asset->forceDelete();
                    $deleted++;
                }
            });

        if ($deleted > 0) {
            Log::info('[ai-image-pro] Retention sweep removed expired assets', ['count' => $deleted]);
        }
    }
}
