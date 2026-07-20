<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Jobs;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Services\AssetService;
use Addons\AiImagePro\Services\CreditService;
use Addons\AiImagePro\Services\GenerationService;
use Addons\AiImagePro\Services\OperationRegistry;
use Addons\AiImagePro\Services\ProviderOperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one queued image job — a generation (media-billed) or a provider edit
 * (flat-billed) — to completion.
 *
 * The two billing tiers fail differently and this job honours both. A `provider`
 * edit is charged up front, so a failure refunds through `CreditService`. A
 * `generate` job is billed only at the very end of a successful run (inside the
 * generation engine, via `TokenGuard::afterMedia`), so a failure has nothing to
 * refund — the charge simply never happened. Either way the credit rules are the
 * mode-aware helpers, never a raw wallet mutation.
 */
class RunImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 600];

    public function __construct(public readonly int $jobId)
    {
        // Set here, not as a property default: Queueable already declares $queue
        // with no default, and redeclaring it with one is a fatal trait-composition
        // error. This is the same pattern the video-creator jobs use.
        $this->queue = 'media';
    }

    public function handle(
        GenerationService $generation,
        ProviderOperationService $providerOps,
        AssetService $assets,
        CreditService $credits,
    ): void {
        $job = AipJob::with('user', 'inputAsset')->find($this->jobId);

        if (! $job || $job->isFinished()) {
            return;
        }

        $job->markProcessing();

        try {
            $created = match ($job->tier) {
                OperationRegistry::TIER_GENERATE => $generation->run($job),
                OperationRegistry::TIER_PROVIDER => $providerOps->run($job),
                default => throw new ImageOperationException(translate('This job cannot be processed.')),
            };

            $this->finalize($assets, $created);

            $job->markCompleted();
        } catch (ImageOperationException $e) {
            $job->markFailed($e->getMessage());
            $credits->refund($job);
        }
    }

    /**
     * Honour the two storage settings once the outputs exist. `mirror_to_documents`
     * cross-lists each result in the core library (the service re-checks the flag).
     * When `auto_save_to_library` is off the results are not kept long-term: they
     * stay downloadable but are expired promptly for the retention sweep to reclaim.
     *
     * @param  array<int, AipAsset>  $created
     */
    private function finalize(AssetService $assets, array $created): void
    {
        $autoSave = (bool) addon_setting(OperationRegistry::SLUG, 'auto_save_to_library', true);
        $mirror = (bool) addon_setting(OperationRegistry::SLUG, 'mirror_to_documents', false);

        foreach ($created as $asset) {
            if ($mirror) {
                $assets->mirrorToDocuments($asset);
            }

            if (! $autoSave && $asset->expires_at === null) {
                $asset->update(['expires_at' => now()->addDay()]);
            }
        }
    }

    /**
     * Last-resort failure hook (a non-ImageOperationException, or the final retry
     * giving up). Marks the job failed and refunds the flat charge; a `generate`
     * job has nothing to refund because it is billed only on success.
     */
    public function failed(\Throwable $e): void
    {
        $job = AipJob::find($this->jobId);

        if (! $job) {
            return;
        }

        if (! $job->isFinished()) {
            $job->markFailed($e->getMessage());
        }

        app(CreditService::class)->refund($job);
    }
}
