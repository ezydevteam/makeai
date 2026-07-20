<?php

namespace App\Jobs;

use App\Events\ChainCompleted;
use App\Events\ChainStepCompleted;
use App\Models\ToolChain;
use App\Models\ToolChainRun;
use App\Models\User;
use App\Services\ToolChainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class RunToolChainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Every step is a billed AI call, so a retry would charge the user twice for
     * the steps that already succeeded. Fail loudly instead.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        protected ToolChain $chain,
        protected User $user,
        protected string $initialInput = '',
    ) {
        $this->onQueue('ai');
    }

    public function handle(ToolChainService $chainService): void
    {
        $steps = $this->chain->steps ?? [];
        $totalSteps = count($steps);

        $run = ToolChainRun::create([
            'chain_id' => $this->chain->id,
            'user_id' => $this->user->id,
            'status' => 'running',
            'input' => $this->initialInput,
            'step_outputs' => [],
            'started_at' => now(),
        ]);

        // Keyed by step number so {{step_N_output}} can address a specific step,
        // not just the one that happened to run last.
        $stepOutputs = [];
        $stepRecords = [];
        $totalTokens = 0;
        $totalCredits = 0.0;

        try {
            foreach ($steps as $index => $step) {
                $stepNumber = (int) ($step['step'] ?? $index + 1);

                $result = $chainService->executeStep($step, $stepOutputs, $this->initialInput, $this->user);

                $stepOutputs[$stepNumber] = $result['output'];
                $stepTokens = $result['input_tokens'] + $result['output_tokens'];
                $totalTokens += $stepTokens;
                $totalCredits += $result['credits_used'];

                $stepRecords[] = [
                    'step' => $stepNumber,
                    'tool_slug' => $step['tool_slug'],
                    'output' => $result['output'],
                    'tokens' => $stepTokens,
                    'credits' => $result['credits_used'],
                ];

                // Persist as we go: a chain that dies on step 3 should still show
                // the output the user already paid for in steps 1 and 2.
                $run->update([
                    'step_outputs' => $stepRecords,
                    'total_tokens' => $totalTokens,
                    'total_credits' => $totalCredits,
                ]);

                $this->announce(new ChainStepCompleted(
                    userId: $this->user->id,
                    runUlid: $run->ulid,
                    stepNumber: $stepNumber,
                    totalSteps: $totalSteps,
                    toolSlug: $step['tool_slug'],
                    outputPreview: Str::limit($result['output'], 200),
                ));
            }

            $run->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->chain->update(['last_run_at' => now()]);
            $this->chain->increment('run_count');

            $this->announce(new ChainCompleted(
                userId: $this->user->id,
                runUlid: $run->ulid,
                status: 'completed',
                totalTokens: $totalTokens,
                totalCredits: $totalCredits,
            ));
        } catch (Throwable $e) {
            // The old catch swallowed this entirely — a failed chain recorded no
            // reason anywhere, in the row or the log.
            report($e);

            $run->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 500),
                'completed_at' => now(),
            ]);

            $this->announce(new ChainCompleted(
                userId: $this->user->id,
                runUlid: $run->ulid,
                status: 'failed',
                totalTokens: $totalTokens,
                totalCredits: $totalCredits,
            ));
        }
    }

    /**
     * Live progress is a nicety; the run is the product. broadcast() throws when the
     * websocket server is unreachable, and inside the step loop that aborted a chain
     * whose AI calls had already succeeded — and been billed. The run row and the
     * polled history are the source of truth, so a broadcast outage stays cosmetic.
     */
    private function announce(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The job died outside handle()'s own catch (timeout, worker kill). Without
     * this the run row sits at "running" forever and the UI polls it for good.
     */
    public function failed(?Throwable $e): void
    {
        ToolChainRun::where('chain_id', $this->chain->id)
            ->where('user_id', $this->user->id)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error' => Str::limit($e?->getMessage() ?? 'The chain did not finish.', 500),
                'completed_at' => now(),
            ]);
    }
}
