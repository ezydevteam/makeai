<?php

namespace Addons\FakerAi\Jobs;

use Addons\FakerAi\Models\FakerBatch;
use Addons\FakerAi\Services\GeneratorRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one FakerAI batch off the request thread. AI drafting is slow and large runs would time
 * out a web request, so generation happens on the 'ai' queue and the History page polls status.
 */
class GenerateFakeDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $batchId)
    {
        // NOTE: $queue is declared untyped in Queueable — assign it here, never as a typed
        // property/default, or the trait composition fatals at boot. (addon-scaffolding gotcha.)
        $this->queue = 'ai';
    }

    public function handle(GeneratorRegistry $registry): void
    {
        $batch = FakerBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        $generator = $registry->get($batch->type);
        if (! $generator || ! $generator->isAvailable()) {
            $batch->update([
                'status' => 'failed',
                'error' => "Generator '{$batch->type}' is not available on this install.",
            ]);

            return;
        }

        $batch->update(['status' => 'processing']);

        try {
            $inserted = $generator->generate($batch);

            // FakeContentGenerator writes token spend (and any AI error) straight onto the batch
            // as it runs, so re-read before stamping the final status.
            $batch->refresh();
            $batch->inserted_count = $inserted;
            $batch->status = ($inserted === 0 && $batch->error) ? 'failed' : 'completed';
            $batch->save();
        } catch (Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 480),
            ]);
        }
    }
}
