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

class RunToolChainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ToolChain $chain,
        protected User $user,
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
            'step_outputs' => [],
            'started_at' => now(),
        ]);

        $previousOutput = '';
        $stepOutputs = [];
        $totalTokens = 0;

        try {
            foreach ($steps as $index => $step) {
                $result = $chainService->executeStep($step, $previousOutput, [], $this->user);

                $previousOutput = $result['output'];
                $totalTokens += ($result['input_tokens'] ?? 0) + ($result['output_tokens'] ?? 0);

                $stepOutputs[] = [
                    'step' => $step['step'] ?? $index + 1,
                    'tool_slug' => $step['tool_slug'],
                    'output' => $result['output'],
                    'tokens' => ($result['input_tokens'] ?? 0) + ($result['output_tokens'] ?? 0),
                ];

                broadcast(new ChainStepCompleted(
                    userId: $this->user->id,
                    runUlid: $run->ulid,
                    stepNumber: $index + 1,
                    totalSteps: $totalSteps,
                    toolSlug: $step['tool_slug'],
                    outputPreview: Str::limit($result['output'], 200),
                ));
            }

            $run->update([
                'status' => 'completed',
                'step_outputs' => $stepOutputs,
                'total_tokens' => $totalTokens,
                'completed_at' => now(),
            ]);

            $this->chain->update(['last_run_at' => now()]);
            $this->chain->increment('run_count');

            broadcast(new ChainCompleted(
                userId: $this->user->id,
                runUlid: $run->ulid,
                status: 'completed',
                totalTokens: $totalTokens,
                totalCredits: 0,
            ));
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'step_outputs' => $stepOutputs,
                'total_tokens' => $totalTokens,
                'completed_at' => now(),
            ]);

            broadcast(new ChainCompleted(
                userId: $this->user->id,
                runUlid: $run->ulid,
                status: 'failed',
                totalTokens: $totalTokens,
                totalCredits: 0,
            ));
        }
    }
}
