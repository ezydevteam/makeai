<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Controllers\User;

use Addons\AiVoiceover\Http\Requests\StoreEpisodeRequest;
use Addons\AiVoiceover\Http\Requests\StoreProjectRequest;
use Addons\AiVoiceover\Jobs\GenerateVoiceover;
use Addons\AiVoiceover\Jobs\TranscribeAudio;
use Addons\AiVoiceover\Models\VoEpisode;
use Addons\AiVoiceover\Models\VoMusicTrack;
use Addons\AiVoiceover\Models\VoProject;
use Addons\AiVoiceover\Services\PodcastRssFeedService;
use Addons\AiVoiceover\Services\VoiceoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends \App\Http\Controllers\Controller
{
    public function index(): Response
    {
        $projects = VoProject::forUser(auth()->id())
            ->withCount('episodes')
            ->latest()
            ->paginate(12);

        return Inertia::render('Addons/ai-voiceover/User/Studio', [
            'projects' => $projects,
        ]);
    }

    public function storeProject(StoreProjectRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('cover_art')) {
            $path = $request->file('cover_art')->store('voiceover/covers', 'public');
            $data['cover_art_path'] = $path;
            $data['cover_art_url'] = Storage::disk('public')->url($path);
        }

        $project = VoProject::create(array_merge($data, [
            'user_id' => auth()->id(),
        ]));

        return redirect()->route('addon.vo.user.projects.show', ['project' => $project->ulid]);
    }

    public function showProject(VoProject $project): Response
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $project->loadCount('episodes');

        $episodes = $project->episodes()->latest()->paginate(20);

        $defaultProvider = addon_setting('ai-voiceover', 'default_provider', 'openai');
        $voices = app(VoiceoverService::class)->getVoicesForProvider($defaultProvider);

        $musicLibrary = VoMusicTrack::where(function ($q) {
            $q->forUser(auth()->id())->orWhere('is_shared', true);
        })->latest()->get();

        $configuredProviders = app(VoiceoverService::class)->getConfiguredProviders();

        return Inertia::render('Addons/ai-voiceover/User/Project', [
            'project' => $project,
            'episodes' => $episodes,
            'voices' => $voices,
            'musicLibrary' => $musicLibrary,
            'configuredProviders' => $configuredProviders,
            'defaultProvider' => $defaultProvider,
        ]);
    }

    public function updateProject(Request $request, VoProject $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'podcast_author' => ['nullable', 'string', 'max:150'],
            'podcast_category' => ['nullable', 'string', 'max:100'],
            'podcast_language' => ['nullable', 'string', 'max:10'],
            'podcast_explicit' => ['nullable', 'boolean'],
            'rss_enabled' => ['nullable', 'boolean'],
            'cover_art' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        if ($request->hasFile('cover_art')) {
            if ($project->cover_art_path) {
                Storage::disk('public')->delete($project->cover_art_path);
            }
            $path = $request->file('cover_art')->store('voiceover/covers', 'public');
            $validated['cover_art_path'] = $path;
            $validated['cover_art_url'] = Storage::disk('public')->url($path);
        }

        $project->update($validated);

        return back()->with('flash', 'Project updated.');
    }

    public function destroyProject(VoProject $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $project->delete();

        return redirect()->route('addon.vo.user.studio');
    }

    public function storeEpisode(StoreEpisodeRequest $request, VoProject $project): JsonResponse
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $service = app(VoiceoverService::class);

        $script = $request->script ?? '';
        if ($request->segments) {
            $script = collect($request->segments)->pluck('text')->implode(' ');
        }

        $credits = $service->calculateCredits($script);

        $user = auth()->user();
        if ($user->credits < $credits) {
            return response()->json([
                'success' => false,
                'message' => translate('Insufficient credits.'),
            ], 402);
        }

        if ($credits > 0) {
            deduct_credits(auth()->id(), $credits, 'Voiceover episode: ' . ($request->title ?? 'Untitled'));
        }

        $episode = VoEpisode::create([
            'vo_project_id' => $project->id,
            'user_id' => auth()->id(),
            'title' => $request->title,
            'script' => $request->script,
            'segments' => $request->segments,
            'provider' => $request->provider,
            'voice_id' => $request->voice_id,
            'music_track_id' => $request->music_track_id,
            'music_volume' => $request->music_volume,
            'episode_number' => $request->episode_number,
            'season_number' => $request->season_number,
            'status' => 'queued',
            'credits_deducted' => $credits,
        ]);

        GenerateVoiceover::dispatch($episode->id);

        return response()->json([
            'success' => true,
            'episode_id' => $episode->id,
            'ulid' => $episode->ulid,
            'status' => 'queued',
        ]);
    }

    public function episodeStatus(VoEpisode $episode): JsonResponse
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        return response()->json([
            'status' => $episode->status,
            'file_url' => $episode->file_url,
            'waveform_url' => $episode->waveform_url,
            'duration_seconds' => $episode->duration_seconds,
            'duration_label' => $episode->duration_label,
            'error_message' => $episode->error_message,
            'transcript_vtt' => $episode->transcript_vtt,
            'can_retry' => $episode->can_retry,
        ]);
    }

    public function transcribeEpisode(VoEpisode $episode)
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        if ($episode->status !== 'completed') {
            return back()->with('flash', 'Episode must be completed before transcribing.');
        }

        if (! $episode->file_path) {
            return back()->with('flash', 'No audio file to transcribe.');
        }

        TranscribeAudio::dispatch($episode->id);

        return back()->with('flash', 'Transcription queued.');
    }

    public function toggleShare(VoEpisode $episode): JsonResponse
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        $episode->update(['share_enabled' => ! $episode->share_enabled]);

        return response()->json([
            'share_enabled' => $episode->share_enabled,
            'share_token' => $episode->share_token,
        ]);
    }

    public function publishEpisode(Request $request, VoEpisode $episode)
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        if ($episode->status !== 'completed') {
            return back()->with('flash', 'Only completed episodes can be published.');
        }

        $episode->update(['published_at' => $request->published_at ?? now()]);

        if ($episode->project && $episode->project->rss_token) {
            app(PodcastRssFeedService::class)->bustCache($episode->project);
        }

        return back()->with('flash', 'Episode published.');
    }

    public function unpublishEpisode(VoEpisode $episode)
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        $episode->update(['published_at' => null]);

        if ($episode->project && $episode->project->rss_token) {
            app(PodcastRssFeedService::class)->bustCache($episode->project);
        }

        return back()->with('flash', 'Episode unpublished.');
    }

    public function destroyEpisode(VoEpisode $episode)
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        if ($episode->file_path) {
            Storage::delete($episode->file_path);
        }
        if ($episode->waveform_path) {
            Storage::delete($episode->waveform_path);
        }

        $episode->delete();

        return back()->with('flash', 'Episode deleted.');
    }

    public function download(VoEpisode $episode)
    {
        abort_if($episode->user_id !== auth()->id(), 403);

        if (! $episode->file_path || ! Storage::exists($episode->file_path)) {
            abort(404);
        }

        $filename = \Illuminate\Support\Str::slug($episode->title) . '.' . ($episode->format ?: 'mp3');

        return Storage::download($episode->file_path, $filename);
    }

    public function uploadMusic(Request $request): JsonResponse
    {
        $maxSizeMb = (int) addon_setting('ai-voiceover', 'background_music_max_size_mb', 10);

        $request->validate([
            'music' => ['required', 'file', 'mimes:mp3,wav,ogg', 'max:' . ($maxSizeMb * 1024)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('music');
        $path = $file->store('voiceover/music/' . auth()->id(), 'local');

        $track = VoMusicTrack::create([
            'user_id' => auth()->id(),
            'name' => $request->name ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'file_url' => Storage::url($path),
            'file_size_bytes' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'track' => [
                'id' => $track->id,
                'name' => $track->name,
            ],
        ]);
    }

    public function autoSplitScript(Request $request): JsonResponse
    {
        $request->validate([
            'script' => ['required', 'string'],
        ]);

        $segments = app(VoiceoverService::class)->autoSplitScript($request->script);

        return response()->json([
            'segments' => $segments,
        ]);
    }
}
