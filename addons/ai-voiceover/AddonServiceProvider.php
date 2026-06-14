<?php

declare(strict_types=1);

namespace Addons\AiVoiceover;

use Addons\AiVoiceover\Jobs\CleanupExpiredAudio;
use Addons\AiVoiceover\Jobs\SyncVoices;
use Addons\AiVoiceover\Services\AudioMixerService;
use Addons\AiVoiceover\Services\PodcastRssFeedService;
use Addons\AiVoiceover\Services\VoiceoverService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VoiceoverService::class);
        $this->app->singleton(AudioMixerService::class);
        $this->app->singleton(PodcastRssFeedService::class);
    }

    public function boot(): void
    {
        if (! is_addon_active('ai-voiceover')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Inertia::share('voiceover', function () {
            return [
                'enabled'          => (bool) addon_setting('ai-voiceover', 'enabled', true),
                'podcastEnabled'   => (bool) addon_setting('ai-voiceover', 'podcast_enabled', true),
                'defaultProvider'  => addon_setting('ai-voiceover', 'default_provider', 'openai'),
                'maxScriptChars'   => (int) addon_setting('ai-voiceover', 'max_script_chars', 5000),
                'creditsPerKChars' => (int) addon_setting('ai-voiceover', 'credits_per_1k_chars', 5),
                'creditsStt'       => (int) addon_setting('ai-voiceover', 'credits_stt', 10),
                'autoTranscribe'   => (bool) addon_setting('ai-voiceover', 'auto_transcribe', false),
                'musicMaxSizeMb'   => (int) addon_setting('ai-voiceover', 'background_music_max_size_mb', 10),
            ];
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new \Addons\AiVoiceover\Jobs\CleanupExpiredAudio)
                ->daily()
                ->when(fn () => addon_setting('ai-voiceover', 'auto_delete_days', 0) > 0);

            $schedule->job(new \Addons\AiVoiceover\Jobs\SyncVoices)
                ->weekly();
        });
    }
}
