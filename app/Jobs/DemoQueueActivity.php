<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * A do-nothing job whose ONLY purpose is to give the Horizon dashboard something to show
 * on the demo. It performs no real work — it sleeps briefly so Horizon records a realistic
 * runtime, and a small share throw so the Failed tab and failure-rate metrics are populated
 * too.
 *
 * Dispatched in batches by `demo:horizon-activity` across the queues Horizon's supervisors
 * watch, so every supervisor shows throughput. Gated on demo mode at BOTH ends: the command
 * refuses to dispatch outside a demo, and handle() no-ops defensively if one ever runs
 * elsewhere.
 */
class DemoQueueActivity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Fail immediately — no retry noise; the point is a single visible pass. */
    public int $tries = 1;

    public function __construct(
        private string $label,
        private bool $shouldFail = false,
    ) {}

    public function handle(): void
    {
        // Never run real work outside a demo, even if a stray job is somehow queued.
        if (! config('demo.enabled')) {
            return;
        }

        // A short, varied runtime so Horizon's runtime / throughput graphs look alive
        // without workers backing up. 40–360ms.
        usleep(random_int(40_000, 360_000));

        if ($this->shouldFail) {
            throw new RuntimeException("Demo job '{$this->label}' failed on purpose to populate Horizon's Failed tab.");
        }
    }

    /**
     * Grouped under a "demo" tag so an operator can spot (and clear) these in Horizon.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['demo', 'demo:'.$this->label];
    }
}
