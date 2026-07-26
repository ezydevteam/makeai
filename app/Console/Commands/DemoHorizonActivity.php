<?php

namespace App\Console\Commands;

use App\Jobs\DemoQueueActivity;
use Illuminate\Console\Command;

/**
 * Keeps the Horizon dashboard looking active on the demo by dispatching a batch of harmless
 * DemoQueueActivity jobs across the queues Horizon's supervisors watch.
 *
 * Horizon monitors REDIS queues only and trims its recent/completed data within the hour,
 * so this is scheduled to re-run periodically (see routes/console.php) — a one-off would go
 * quiet again quickly. Requires the demo box to run `php artisan horizon`; the command only
 * dispatches, Horizon does the processing that produces the metrics.
 */
class DemoHorizonActivity extends Command
{
    protected $signature = 'demo:horizon-activity {--count=60 : How many demo jobs to dispatch}';

    protected $description = 'Dispatch harmless demo jobs so the Horizon dashboard shows activity (demo mode only)';

    /**
     * Representative queues per Horizon supervisor (see config/horizon.php), weighted so the
     * spread reads like a real workload rather than a flat line. Repeats bias the mix.
     *
     * @var array<int, string>
     */
    private array $queueWeights = [
        // supervisor-long
        'ai', 'ai', 'ai', 'media', 'embeddings',
        // supervisor-delivery
        'emails', 'emails', 'mail', 'otp', 'webhooks', 'social',
        // supervisor-general
        'default', 'default', 'default', 'low',
    ];

    public function handle(): int
    {
        // Demo-only: dispatching real jobs onto queues on a live install is not something
        // this command should ever do.
        if (! config('demo.enabled')) {
            $this->error('demo:horizon-activity is refused: demo mode is not enabled (DEMO_ENABLED).');

            return self::FAILURE;
        }

        // Horizon watches Redis queues; on the DB/sync driver these jobs would never appear
        // on the dashboard, so bail with an actionable message rather than silently doing
        // nothing useful.
        if (config('queue.default') !== 'redis') {
            $this->warn('Queue connection is "'.config('queue.default').'", not "redis".');
            $this->line('Horizon only monitors Redis queues. Set QUEUE_CONNECTION=redis and run `php artisan horizon` for these to appear.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $labels = ['ProcessReport', 'GenerateThumbnail', 'SyncSubscriber', 'SendDigest', 'RebuildIndex', 'WarmCache'];

        for ($i = 0; $i < $count; $i++) {
            $queue = $this->queueWeights[array_rand($this->queueWeights)];
            $label = $labels[array_rand($labels)];

            // Roughly 1 in 12 fails, so the Failed tab and failure-rate metric are not empty.
            $shouldFail = random_int(1, 12) === 1;

            // A little delay spread so jobs do not all land in the same instant — makes the
            // throughput graph a curve rather than a single spike.
            DemoQueueActivity::dispatch($label, $shouldFail)
                ->onQueue($queue)
                ->delay(now()->addSeconds(random_int(0, 45)));
        }

        $this->info("Dispatched {$count} demo jobs across ".count(array_unique($this->queueWeights)).' queues for Horizon.');

        return self::SUCCESS;
    }
}
