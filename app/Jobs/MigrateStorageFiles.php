<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Setting;
use App\Services\CloudStorageService;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copy every media file from one storage location to another so switching drivers
 * doesn't orphan already-uploaded content.
 *
 * Direction is expressed as source → target driver. `local` reads/writes the
 * on-server public disk; any other value is an S3-compatible bucket built from its
 * stored credentials. Files are COPIED (never deleted from the source) so the
 * operation is safe to re-run and never destroys the origin — which also means a
 * rollback to a previously-used driver needs no migration (its files are still there).
 *
 * The copy is idempotent: a file already present at the target with the same size is
 * skipped, so a re-run — or a queue re-pop (see {@see retryUntil()}) — resumes instead
 * of recopying.
 *
 * When $activateOnComplete is set, the target driver is switched on ONLY after a
 * fully-successful copy. That makes the switch atomic: the site keeps serving from the
 * old location until every file exists in the new one (no 404 window), and a partial
 * or failed copy never activates.
 *
 * Progress is published to the cache under {@see STATE_KEY} for the settings page to poll.
 */
class MigrateStorageFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const STATE_KEY = 'storage_migration_state';

    public $timeout = 3600;

    public function __construct(
        private string $sourceDriver,
        private string $targetDriver,
        private bool $activateOnComplete = false,
    ) {
        $this->onQueue('default');
    }

    /**
     * Retry on a TIME budget rather than an attempt count.
     *
     * The default queue connection's retry_after (90s) is far shorter than a large
     * copy, so a long-running job gets re-popped by the worker. With the old $tries=1
     * that re-pop was marked FAILED even though the files were copying fine. Because the
     * copy is idempotent, a re-pop can simply resume; a time-based limit lets it keep
     * resuming until the tree is done (or six hours pass) instead of false-failing.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        try {
            $source = $this->resolveDisk($this->sourceDriver);
            $target = $this->resolveDisk($this->targetDriver);

            $result = $this->copyBetween($source, $target);

            // Activate the target driver only after a fully-successful copy — the switch
            // is atomic and a partial copy never goes live.
            if ($this->activateOnComplete && $result['failed'] === 0) {
                settings_set('storage_driver', $this->targetDriver, 'string', 'storage');
                Setting::flushCache();
            }

            $message = $result['failed'] === 0
                ? translate(':count file(s) copied successfully.', ['count' => $result['processed'] - $result['failed']])
                : translate(':ok file(s) copied, :failed failed — see logs.', [
                    'ok' => $result['processed'] - $result['failed'],
                    'failed' => $result['failed'],
                ]);

            $this->publish(
                $result['failed'] === 0 ? 'completed' : 'completed_with_errors',
                $result['total'],
                $result['processed'],
                $result['failed'],
                $message,
            );
        } catch (Throwable $e) {
            // A catastrophic failure (e.g. the target disk can't be built) is recorded
            // but not rethrown, so it isn't retried for hours. A worker TIMEOUT re-pop is
            // handled separately by retryUntil() and resumes idempotently.
            Log::error('Storage migration failed.', ['error' => $e->getMessage()]);
            $this->publishFailure($e->getMessage());
        }
    }

    /**
     * Copy every file from $source to $target, skipping any that already exist at the
     * same size. Returns ['total' => int, 'processed' => int, 'failed' => int].
     *
     * Extracted (and public) so it can be exercised directly against two fake disks
     * without a queue or a live bucket.
     *
     * @return array{total: int, processed: int, failed: int, skipped: int}
     */
    public function copyBetween(Filesystem $source, Filesystem $target): array
    {
        $files = $source->allFiles();
        $total = count($files);
        $processed = 0;
        $failed = 0;
        $skipped = 0;

        $this->publish('running', $total, 0, 0, translate('Copying files…'));

        foreach ($files as $path) {
            // Skip health-check probes written by testConnection().
            if (str_starts_with($path, 'healthcheck/')) {
                $processed++;
                continue;
            }

            $stream = null;

            try {
                // Idempotent: a file already at the target with the same size is done.
                if ($target->exists($path) && $target->size($path) === $source->size($path)) {
                    $skipped++;
                    $processed++;
                    continue;
                }

                $stream = $source->readStream($path);

                if (! is_resource($stream)) {
                    $failed++;
                } elseif ($target->writeStream($path, $stream) === false) {
                    // writeStream() returns false (not throws) on the throw=>false disks,
                    // so a failed copy must be counted, not silently succeed.
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Storage migration: failed to copy a file.', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $processed++;

            // Throttle cache writes to roughly every 10 files.
            if ($processed % 10 === 0 || $processed === $total) {
                $this->publish('running', $total, $processed, $failed, translate('Copying files…'));
            }
        }

        return ['total' => $total, 'processed' => $processed, 'failed' => $failed, 'skipped' => $skipped];
    }

    public function failed(Throwable $e): void
    {
        $this->publishFailure($e->getMessage());
    }

    protected function resolveDisk(string $driver): Filesystem
    {
        if ($driver === 'local') {
            return Storage::disk('local_public_media');
        }

        return Storage::build(CloudStorageService::forDriver($driver)->diskConfig());
    }

    private function publish(string $status, int $total, int $processed, int $failed, string $message): void
    {
        Cache::put(self::STATE_KEY, [
            'status' => $status,
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'message' => $message,
            'source' => $this->sourceDriver,
            'target' => $this->targetDriver,
            'activate_on_complete' => $this->activateOnComplete,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }

    /**
     * Publish a failure WITHOUT zeroing the progress counters — the settings page keeps
     * showing how far the copy got instead of resetting to 0 of 0.
     */
    private function publishFailure(string $message): void
    {
        $state = Cache::get(self::STATE_KEY);
        $state = is_array($state) ? $state : ['total' => 0, 'processed' => 0, 'failed' => 0];

        $state['status'] = 'failed';
        $state['message'] = $message;
        $state['source'] = $this->sourceDriver;
        $state['target'] = $this->targetDriver;
        $state['activate_on_complete'] = $this->activateOnComplete;
        $state['updated_at'] = now()->toIso8601String();

        Cache::put(self::STATE_KEY, $state, now()->addHours(6));
    }
}
