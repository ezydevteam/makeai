<?php

namespace Addons\AiRepurposer\Jobs;

use Addons\AiRepurposer\Models\RpJob;
use Addons\AiRepurposer\Models\RpOutput;
use Addons\AiRepurposer\Services\RepurposeService;
use Addons\AiRepurposer\Services\TranscriptService;
use App\Jobs\SendInAppNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRepurposeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $jobId) {}

    public function handle(TranscriptService $transcript, RepurposeService $repurpose): void
    {
        $job = RpJob::find($this->jobId);

        if (! $job || $job->status === 'completed') {
            return;
        }

        // Step 1: Transcribe
        $job->update(['status' => 'transcribing']);

        try {
            if ($job->source_type === 'youtube_url') {
                $data = $transcript->getYoutubeTranscript($job->source_url);
            } elseif ($job->source_type === 'file_upload') {
                $data = $transcript->transcribeFile($job->source_path);
            } else {
                $data = [
                    'transcript' => $job->transcript,
                    'title'      => $job->source_title,
                    'duration'   => 0,
                    'chapters'   => [],
                ];
            }

            $job->update([
                'transcript'        => $data['transcript'],
                'source_title'      => $data['title'] ?? $job->source_title,
                'duration_seconds'  => $data['duration'] ?? null,
                'chapters'          => $data['chapters'] ?? [],
                'word_count'        => count(preg_split('/\s+/u', trim($data['transcript']), -1, PREG_SPLIT_NO_EMPTY)),
                'transcript_source' => $job->source_type === 'youtube_url' ? 'youtube' : ($data['provider'] ?? 'whisper'),
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)]);

            $this->refundCredits($job);

            Log::error("Repurpose job {$job->id} failed at transcription: " . $e->getMessage());

            return;
        }

        // Step 2: Generate all formats
        $job->update(['status' => 'generating']);

        $repurpose->generateAll($job, $job->formats_requested);

        // Step 3: Finalise
        $freshJob = $job->fresh();
        $completedCount = count($freshJob->formats_completed ?? []);
        $requestedCount = count($freshJob->formats_requested ?? []);
        $finalStatus = $completedCount === $requestedCount ? 'completed' : 'partial';

        $freshJob->update(['status' => $finalStatus]);

        // Auto-save blog post if enabled
        if (addon_setting('ai-repurposer', 'auto_save_blog', false)) {
            $blogOutput = RpOutput::where('rp_job_id', $freshJob->id)
                ->where('format', 'blog_post')
                ->first();

            if ($blogOutput) {
                $this->saveToBlog($blogOutput, $freshJob);
            }
        }

        // Notify user
        SendInAppNotification::dispatch(
            User::find($job->user_id),
            'repurpose_completed',
            ['title' => $freshJob->source_title, 'formats' => $completedCount]
        )->onQueue('default');
    }

    public function failed(\Throwable $e): void
    {
        $job = RpJob::find($this->jobId);

        if ($job) {
            $job->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)]);

            $this->refundCredits($job);
        }

        Log::error("Repurpose job {$this->jobId} failed permanently: " . $e->getMessage());
    }

    protected function refundCredits(RpJob $job): void
    {
        if ($job->credits_deducted > 0 && $job->status !== 'completed') {
            $user = User::find($job->user_id);

            if (! $user) {
                Log::warning("Failed to refund credits for repurpose job {$job->id} — user {$job->user_id} may not exist.");

                return;
            }

            // Mode-correct: metered mode returns wallet credits; quota mode (Regular
            // license) winds back the consumed daily/monthly allowance instead —
            // mirrors the mode-aware charge in deduct_credits().
            $user->refundCredits(
                (float) $job->credits_deducted,
                "Content repurpose failed — refund: {$job->id}",
                ['rp_job_id' => $job->id],
            );
        }
    }

    protected function saveToBlog(RpOutput $output, RpJob $job): void
    {
        $postId = DB::table('blog_posts')->insertGetId([
            'user_id'    => $job->user_id,
            'title'      => $job->source_title ?? 'Repurposed Post',
            'content'    => $output->content,
            'status'     => 'draft',
            'slug'       => Str::slug($job->source_title ?? 'repurposed-post') . '-' . Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $output->update(['is_saved' => true, 'saved_post_id' => $postId]);
    }
}
