<?php

namespace Addons\AiVideoCreator\Jobs;

use Addons\AiVideoCreator\Events\VideoRenderCompleted;
use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Services\TrimmerService;
use Addons\AiVideoCreator\Services\VideoProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PollVideoStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $renderId)
    {
        $this->tries = 1;
        $this->queue = 'ai';
    }

    public function handle(VideoProviderService $providerService, TrimmerService $trimmer): void
    {
        $render = VcRender::with('user')->find($this->renderId);
        if (! $render || $render->status !== 'processing') {
            return;
        }

        $maxAttempts = (int) addon_setting('ai-video-creator', 'max_poll_attempts', 20);

        if ($render->poll_attempts >= $maxAttempts) {
            $render->update([
                'status' => 'failed',
                'error_message' => 'Generation timed out after ' . $maxAttempts . ' attempts.',
            ]);
            $providerService->refundCredits($render);
            return;
        }

        $render->increment('poll_attempts');

        $client = $providerService->getClient($render->type);
        $status = $client->checkStatus($render->provider_job_id);

        if ($status->status === 'completed') {
            $tempPath = $client->downloadResult($render->provider_job_id);
            $storagePath = 'video-creator/' . $render->user_id . '/' . $render->ulid . '.mp4';
            Storage::put($storagePath, file_get_contents($tempPath));
            @unlink($tempPath);

            $thumbPath = $trimmer->extractThumbnail($storagePath);

            $render->update([
                'status' => 'completed',
                'file_path' => $storagePath,
                'file_url' => Storage::url($storagePath),
                'file_size_bytes' => Storage::size($storagePath),
                'thumbnail_path' => $thumbPath,
                'thumbnail_url' => $thumbPath ? Storage::url($thumbPath) : null,
                'metadata' => $status->metadata,
                'completed_at' => now(),
            ]);

            broadcast(new VideoRenderCompleted($render->fresh()));
        } elseif ($status->status === 'failed') {
            $render->update([
                'status' => 'failed',
                'error_message' => $status->error ?? 'Provider returned failure.',
            ]);
            $providerService->refundCredits($render);
        } else {
            $interval = (int) addon_setting('ai-video-creator', 'poll_interval_seconds', 30);
            self::dispatch($render->id)->delay(now()->addSeconds($interval))->onQueue('ai');
        }
    }
}
