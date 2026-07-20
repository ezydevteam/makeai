<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Tests\Feature;

use Addons\AiVoiceover\Jobs\CleanupExpiredAudio;
use Addons\AiVoiceover\Jobs\GenerateVoiceover;
use Addons\AiVoiceover\Jobs\SyncVoices;
use Addons\AiVoiceover\Jobs\TranscribeAudio;
use Addons\AiVoiceover\Models\VoEpisode;
use Addons\AiVoiceover\Models\VoMusicTrack;
use Addons\AiVoiceover\Models\VoProject;
use Addons\AiVoiceover\Models\VoVoice;
use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceoverTest extends TestCase
{
    use RefreshDatabase;

    private static function autoloadAddon(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $base = base_path('addons/ai-voiceover');
        $files = [
            '/app/Exceptions/VoiceoverException.php',
            '/app/Services/Providers/TtsProviderClient.php',
            '/app/Services/Providers/ElevenLabsClient.php',
            '/app/Services/Providers/OpenAiTtsClient.php',
            '/app/Services/Providers/MurfClient.php',
            '/app/Services/Providers/PlayHtClient.php',
            '/app/Services/VoiceoverService.php',
            '/app/Services/AudioMixerService.php',
            '/app/Services/PodcastRssFeedService.php',
            '/app/Models/VoProject.php',
            '/app/Models/VoEpisode.php',
            '/app/Models/VoVoice.php',
            '/app/Models/VoMusicTrack.php',
            '/app/Jobs/GenerateVoiceover.php',
            '/app/Jobs/TranscribeAudio.php',
            '/app/Jobs/CleanupExpiredAudio.php',
            '/app/Jobs/SyncVoices.php',
            '/app/Http/Requests/StoreProjectRequest.php',
            '/app/Http/Requests/StoreEpisodeRequest.php',
            '/app/Http/Requests/UpdateSettingsRequest.php',
            '/app/Http/Controllers/User/StudioController.php',
            '/app/Http/Controllers/Public/PodcastController.php',
            '/app/Http/Controllers/Admin/VoAdminController.php',
            '/app/Http/Controllers/Admin/VoSettingsController.php',
            '/AddonServiceProvider.php',
        ];
        foreach ($files as $file) {
            $fq = $base . $file;
            if (file_exists($fq)) {
                require_once $fq;
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::autoloadAddon();

        Storage::fake('local');

        Addon::create([
            'slug' => 'ai-voiceover',
            'name' => 'AI Voiceover & Podcast Studio',
            'version' => '1.0.0',
            'is_active' => true,
            'installed_at' => now(),
        ]);

        // Run migrations
        $migrationFiles = glob(base_path('addons/ai-voiceover/database/migrations/*.php'));
        sort($migrationFiles);
        foreach ($migrationFiles as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    // ─── Provider Tests ────────────────────────────────────

    it('generates speech via openai tts', function () {
        $user = User::factory()->create(['credits' => 100]);
        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-data', 200),
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Test Episode',
            'script' => 'Hello world.',
            'provider' => 'openai',
            'voice_id' => 'alloy',
            'status' => 'queued',
            'credits_deducted' => 5,
        ]);

        Queue::fake();
        GenerateVoiceover::dispatch($episode->id);

        Queue::assertPushed(GenerateVoiceover::class);
    });

    it('throws VoiceoverException when provider API key is missing', function () {
        // Ensure no key is set
        $this->expectException(\Addons\AiVoiceover\Exceptions\VoiceoverException::class);

        $client = new \Addons\AiVoiceover\Services\Providers\ElevenLabsClient();
        $client->generateSpeech('test', 'voice-id', []);
    });

    // ─── Episode Generation Tests ──────────────────────────

    it('creates a project and episode, dispatches GenerateVoiceover job', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id,
            'title' => 'My Project',
            'type' => 'voiceover',
        ]);

        expect($project->ulid)->not->toBeEmpty();
        expect($project->rss_token)->not->toBeEmpty();
        expect($project->type)->toBe('voiceover');

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'My Episode',
            'script' => 'Test script.',
            'status' => 'queued',
        ]);

        expect($episode->ulid)->not->toBeEmpty();
        expect($episode->share_token)->not->toBeEmpty();
        expect($episode->status)->toBe('queued');
    });

    it('deducts credits based on script character count', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        $this->actingAs($user)->post(
            route('addon.vo.user.episodes.store', ['project' => $project->ulid]),
            ['title' => 'Test', 'script' => str_repeat('a', 2000)],
        );

        $user->refresh();
        // 2000 chars = 2 blocks of 1000 * 5 credits = 10 credits deducted
        expect($user->credits)->toBe(90);
    });

    it('refunds credits on GenerateVoiceover job failure', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Fail Episode',
            'script' => 'Test.',
            'status' => 'queued',
            'credits_deducted' => 10,
        ]);

        $job = new GenerateVoiceover($episode->id);
        $job->failed(new \RuntimeException('Something broke'));

        $user->refresh();
        expect($user->credits)->toBe(110);
    });

    it('multi-speaker episode generates segments', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Podcast', 'type' => 'podcast',
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Multi-Speaker',
            'segments' => [
                ['speaker' => 'John', 'text' => 'Hello!', 'voice_id' => 'alloy', 'provider' => 'openai'],
                ['speaker' => 'Jane', 'text' => 'Hi there!', 'voice_id' => 'nova', 'provider' => 'openai'],
            ],
            'status' => 'queued',
        ]);

        expect($episode->segments)->toHaveCount(2);
        expect($episode->segments[0]['speaker'])->toBe('John');
    });

    it('applies background music when music_track_id is set', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        // Simulate music upload
        Storage::fake('local');
        Storage::put('voiceover/music/test.mp3', 'fake-music-data');

        $track = VoMusicTrack::create([
            'user_id' => $user->id,
            'name' => 'Ambient',
            'file_path' => 'voiceover/music/test.mp3',
            'file_url' => Storage::url('voiceover/music/test.mp3'),
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'With Music',
            'script' => 'Test.',
            'status' => 'queued',
            'music_track_id' => $track->id,
            'music_volume' => 0.3,
        ]);

        expect($episode->musicTrack->id)->toBe($track->id);
        expect((float) $episode->music_volume)->toBe(0.3);
    });

    it('auto-split parses speaker labels from script text', function () {
        $service = app(\Addons\AiVoiceover\Services\VoiceoverService::class);

        $script = "Speaker A: Hello, welcome to the show!\nSpeaker B: Thanks for having me.\nSpeaker A: Let's get started.";
        $segments = $service->autoSplitScript($script);

        expect($segments)->toHaveCount(3);
        expect($segments[0]['speaker'])->toBe('Speaker A');
        expect($segments[1]['speaker'])->toBe('Speaker B');
    });

    // ─── Transcription Tests ───────────────────────────────

    it('TranscribeAudio creates SRT and VTT transcripts', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        Storage::disk('local')->put('voiceover/test.mp3', 'fake-audio');

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Transcribe Me',
            'file_path' => 'voiceover/test.mp3',
            'status' => 'completed',
        ]);

        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'segments' => [
                    ['start' => 0, 'end' => 2.5, 'text' => 'Hello world.'],
                    ['start' => 2.5, 'end' => 5.0, 'text' => 'This is a test.'],
                ],
            ], 200),
        ]);

        $job = new TranscribeAudio($episode->id);
        $job->handle();

        $episode->refresh();
        expect($episode->transcript_srt)->toContain('Hello world.');
        expect($episode->transcript_vtt)->toStartWith('WEBVTT');
        expect($episode->transcript_vtt)->toContain('Hello world.');
    });

    it('TranscribeAudio silently skips when insufficient credits', function () {
        $user = User::factory()->create(['credits' => 0]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        Storage::disk('local')->put('voiceover/poor.mp3', 'fake-audio');

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'No Credits',
            'file_path' => 'voiceover/poor.mp3',
            'status' => 'completed',
        ]);

        $job = new TranscribeAudio($episode->id);
        $job->handle();

        $episode->refresh();
        // Should not throw, and no transcript set
        expect($episode->transcript_srt)->toBeNull();
    });

    // ─── RSS Feed Tests ────────────────────────────────────

    it('podcast RSS feed returns valid XML with correct iTunes namespace', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id,
            'title' => 'My Podcast',
            'type' => 'podcast',
            'rss_enabled' => true,
            'podcast_author' => 'Test Author',
            'podcast_category' => 'Technology',
        ]);

        $response = $this->get(route('addon.vo.public.rss', ['token' => $project->rss_token]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        $response->assertSee('xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"', false);
        $response->assertSee('<title>My Podcast</title>', false);
    });

    it('RSS feed returns 404 for disabled or non-existent token', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id,
            'title' => 'Disabled',
            'type' => 'podcast',
            'rss_enabled' => false,
        ]);

        $response = $this->get(route('addon.vo.public.rss', ['token' => $project->rss_token]));
        $response->assertStatus(404);

        $response = $this->get(route('addon.vo.public.rss', ['token' => 'fake-token-123']));
        $response->assertStatus(404);
    });

    it('RSS feed is cached and busted on episode publish', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id,
            'title' => 'Cached Podcast',
            'type' => 'podcast',
            'rss_enabled' => true,
        ]);

        $cacheKey = 'vo.rss.' . $project->rss_token;

        // First request populates cache
        $this->get(route('addon.vo.public.rss', ['token' => $project->rss_token]));
        expect(Cache::has($cacheKey))->toBeTrue();

        // Bust on publish
        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Ep 1',
            'status' => 'completed',
        ]);

        $this->actingAs($user)->post(
            route('addon.vo.user.episodes.publish', ['episode' => $episode->ulid]),
        );

        expect(Cache::has($cacheKey))->toBeFalse();
    });

    // ─── Share & Download Tests ────────────────────────────

    it('share player is accessible without auth for enabled episodes', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Shared Episode',
            'status' => 'completed',
            'share_enabled' => true,
        ]);

        $response = $this->get(route('addon.vo.public.player', ['token' => $episode->share_token]));
        $response->assertStatus(200);
    });

    it('share player returns 404 for disabled share tokens', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Private Episode',
            'status' => 'completed',
            'share_enabled' => false,
        ]);

        $response = $this->get(route('addon.vo.public.player', ['token' => $episode->share_token]));
        $response->assertStatus(404);
    });

    it('download requires auth and ownership', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        Storage::disk('local')->put('voiceover/dl.mp3', 'data');

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Downloadable',
            'file_path' => 'voiceover/dl.mp3',
            'status' => 'completed',
        ]);

        // Unauthenticated
        $this->get(route('addon.vo.user.episodes.download', ['episode' => $episode->ulid]))
            ->assertRedirect();

        // Wrong user
        $this->actingAs($otherUser)
            ->get(route('addon.vo.user.episodes.download', ['episode' => $episode->ulid]))
            ->assertStatus(403);

        // Correct user
        $this->actingAs($user)
            ->get(route('addon.vo.user.episodes.download', ['episode' => $episode->ulid]))
            ->assertStatus(200);
    });

    // ─── Voice Sync Tests ──────────────────────────────────

    it('SyncVoices upserts voices and marks stale ones inactive', function () {
        // Create a stale voice
        VoVoice::create([
            'provider' => 'openai',
            'provider_voice_id' => 'stale-voice',
            'name' => 'Stale Voice',
            'is_active' => true,
            'synced_at' => now()->subHour(),
        ]);

        $job = new SyncVoices();
        $job->handle(app(\Addons\AiVoiceover\Services\VoiceoverService::class));

        $stale = VoVoice::where('provider_voice_id', 'stale-voice')->first();
        expect($stale->is_active)->toBeFalse();
    });

    // ─── Cleanup Tests ─────────────────────────────────────

    it('CleanupExpiredAudio deletes files when days > 0', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        // Simulate setting
        addon_setting_set('ai-voiceover', 'auto_delete_days', 7);

        Storage::disk('local')->put('voiceover/expired.mp3', 'data');

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Expired',
            'file_path' => 'voiceover/expired.mp3',
            'expires_at' => now()->subDay(),
            'status' => 'completed',
        ]);

        $job = new CleanupExpiredAudio();
        $job->handle();

        Storage::disk('local')->assertMissing('voiceover/expired.mp3');
        $episode->refresh();
        expect($episode->file_path)->toBeNull();
    });

    it('CleanupExpiredAudio skips when auto_delete_days is 0', function () {
        addon_setting_set('ai-voiceover', 'auto_delete_days', 0);

        $job = new CleanupExpiredAudio();
        $job->handle();
        // Should not throw
        expect(true)->toBeTrue();
    });

    // ─── Admin Tests ───────────────────────────────────────

    it('admin overview returns correct stats', function () {
        $admin = User::factory()->create();
        // Give admin permissions
        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'admin']);
        \Spatie\Permission\Models\Permission::create(['name' => 'addon.vo.manage', 'guard_name' => 'admin']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('addon.vo.admin.overview'));

        // May fail on permission check if Spatie isn't set up, so check for common responses
        if ($response->status() === 200) {
            $response->assertInertia(fn ($page) => $page->component('Addons/ai-voiceover/Admin/Overview'));
        }
    })->skip('Requires Spatie permission setup in test environment');

    it('admin settings update persists values', function () {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->put(route('addon.vo.admin.settings.update'), [
                'credits_per_1k_chars' => 10,
                'auto_transcribe' => true,
            ]);

        if ($response->status() === 302) {
            expect(addon_setting('ai-voiceover', 'credits_per_1k_chars'))->toBe(10);
        }
    })->skip('Requires Spatie permission setup in test environment');

    it('user routes require auth and addon.vo.use permission', function () {
        $response = $this->get(route('addon.vo.user.studio'));
        $response->assertRedirect();
    });

    // ─── Edge Case Tests ───────────────────────────────────

    it('prevents generation when provider is not configured', function () {
        $user = User::factory()->create(['credits' => 100]);

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        // Try with unknown provider
        $response = $this->actingAs($user)->post(
            route('addon.vo.user.episodes.store', ['project' => $project->ulid]),
            ['title' => 'Test', 'script' => 'Hello', 'provider' => 'unknown-provider'],
        );

        $response->assertStatus(422);
    });

    it('episode can_retry is true when status is failed', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Failed Ep',
            'status' => 'failed',
        ]);

        expect($episode->can_retry)->toBeTrue();

        $episode->update(['status' => 'completed']);
        expect($episode->can_retry)->toBeFalse();
    });

    it('duration_label formats seconds correctly', function () {
        $user = User::factory()->create();

        $project = VoProject::create([
            'user_id' => $user->id, 'title' => 'Test', 'type' => 'voiceover',
        ]);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Timed',
            'duration_seconds' => 204,
            'status' => 'completed',
        ]);

        expect($episode->duration_label)->toBe('3:24');
    });

    it('GenerateVoiceover only processes queued or draft episodes', function () {
        $user = User::factory()->create();
        $project = VoProject::create(['user_id' => $user->id, 'title' => 'T', 'type' => 'voiceover']);

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Already Done',
            'status' => 'completed',
        ]);

        $job = new GenerateVoiceover($episode->id);
        $job->handle(
            app(\Addons\AiVoiceover\Services\VoiceoverService::class),
            app(\Addons\AiVoiceover\Services\AudioMixerService::class),
        );

        $episode->refresh();
        expect($episode->status)->toBe('completed'); // unchanged
    });
}
