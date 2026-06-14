<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Jobs;

use Addons\AiVoiceover\Exceptions\VoiceoverException;
use Addons\AiVoiceover\Models\VoEpisode;
use Addons\AiVoiceover\Models\VoMusicTrack;
use Addons\AiVoiceover\Models\VoProject;
use Addons\AiVoiceover\Services\AudioMixerService;
use Addons\AiVoiceover\Services\VoiceoverService;
use App\Jobs\SendInAppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateVoiceover implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 600;

    public function __construct(public readonly int $episodeId)
    {
        $this->onQueue('ai');
    }

    public function handle(VoiceoverService $service, AudioMixerService $mixer): void
    {
        $episode = VoEpisode::with('project.user')->find($this->episodeId);

        if (! $episode || ! in_array($episode->status, ['draft', 'queued'], true)) {
            return;
        }

        $episode->update(['status' => 'processing']);

        try {
            $isMultiSpeaker = $this->isMultiSpeaker($episode);

            $outputPath = $isMultiSpeaker
                ? $service->generateMultiSpeaker($episode)
                : $service->generateSingle($episode);

            // Apply background music if set
            $musicTrack = $episode->musicTrack;
            if ($musicTrack && Storage::exists($musicTrack->file_path)) {
                $mixedOutputPath = 'voiceover/' . $episode->user_id . '/' . $episode->ulid . '_mixed.mp3';
                $musicVolume = (float) ($episode->music_volume ?? 0.30);
                $outputPath = $mixer->mixWithMusic($outputPath, $musicTrack->file_path, $musicVolume, $mixedOutputPath);
            }

            $duration = $mixer->getDuration($outputPath);
            $waveform = $mixer->generateWaveform($outputPath);

            $episode->update([
                'status' => 'completed',
                'file_path' => $outputPath,
                'file_url' => Storage::url($outputPath),
                'file_size_bytes' => Storage::size($outputPath) ?: 0,
                'duration_seconds' => $duration,
                'waveform_path' => $waveform['waveform_path'] ?? null,
                'waveform_url' => $waveform['waveform_url'] ?? null,
                'error_message' => null,
            ]);

            // Update project counters
            VoProject::where('id', $episode->vo_project_id)->increment('episode_count');
            $result = VoProject::where('id', $episode->vo_project_id)->increment('total_duration', $duration ?? 0);
            if ($result === 0) {
                Log::warning('Failed to increment total_duration for project', [
                    'project_id' => $episode->vo_project_id,
                ]);
            }

            // Auto-transcribe if enabled
            if (addon_setting('ai-voiceover', 'auto_transcribe', false)) {
                TranscribeAudio::dispatch($episode->id)->onQueue('media');
            }

            // Notify user
            SendInAppNotification::dispatch($episode->user, [
                'title' => 'Voiceover Ready',
                'message' => "Your episode \"{$episode->title}\" is ready to play.",
                'category' => 'voiceover',
                'action_url' => route('addon.vo.user.projects.show', ['project' => $episode->project->ulid]),
            ]);

        } catch (VoiceoverException $e) {
            $episode->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            // Refund credits
            if ($episode->credits_deducted > 0) {
                $refunded = \App\Models\User::where('id', $episode->user_id)->increment('credits', $episode->credits_deducted);
                if ($refunded === 0) {
                    Log::warning('Failed to refund credits for voiceover failure', [
                        'episode_id' => $episode->id,
                        'user_id' => $episode->user_id,
                        'amount' => $episode->credits_deducted,
                    ]);
                }
            }

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $episode = VoEpisode::find($this->episodeId);
        if (! $episode) {
            return;
        }

        $episode->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);

        if ($episode->credits_deducted > 0) {
            $refunded = \App\Models\User::where('id', $episode->user_id)->increment('credits', $episode->credits_deducted);
            if ($refunded === 0) {
                Log::warning('Failed to refund credits on job failure', [
                    'episode_id' => $episode->id,
                    'user_id' => $episode->user_id,
                    'amount' => $episode->credits_deducted,
                ]);
            }
        }
    }

    private function isMultiSpeaker(VoEpisode $episode): bool
    {
        $segments = $episode->segments ?? [];
        if (count($segments) <= 1) {
            return false;
        }

        $uniqueVoices = collect($segments)->pluck('voice_id')->unique()->filter()->count();

        return $uniqueVoices > 1;
    }
}
