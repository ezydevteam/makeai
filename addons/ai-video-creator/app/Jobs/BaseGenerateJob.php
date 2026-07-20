<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Services\VideoProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

abstract class BaseGenerateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $renderId)
    {
        $this->queue = 'ai';
        $this->tries = 3;
        $this->backoff = [60, 300];
    }

    protected function findRender(): ?VcRender
    {
        return VcRender::find($this->renderId);
    }

    protected function startProcessing(VcRender $render): void
    {
        $render->update(['status' => 'processing']);
    }

    protected function dispatchPoll(VcRender $render): void
    {
        $interval = (int) addon_setting('ai-video-creator', 'poll_interval_seconds', 30);

        PollVideoStatus::dispatch($render->id)
            ->delay(now()->addSeconds($interval))
            ->onQueue('ai');
    }

    public function failed(\Throwable $e): void
    {
        $render = VcRender::find($this->renderId);
        if (! $render) {
            return;
        }

        $render->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);

        app(VideoProviderService::class)->refundCredits($render);
    }
}
