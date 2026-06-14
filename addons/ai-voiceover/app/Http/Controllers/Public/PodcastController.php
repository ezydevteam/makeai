<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Controllers\Public;

use Addons\AiVoiceover\Models\VoEpisode;
use Addons\AiVoiceover\Models\VoProject;
use Addons\AiVoiceover\Services\PodcastRssFeedService;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PodcastController extends \App\Http\Controllers\Controller
{
    public function rss(string $token): Response
    {
        $project = VoProject::where('rss_token', $token)
            ->where('rss_enabled', true)
            ->first();

        if (! $project) {
            abort(404);
        }

        $xml = app(PodcastRssFeedService::class)->generateFeed($project);

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
        ]);
    }

    public function sharePlayer(string $token): InertiaResponse
    {
        $episode = VoEpisode::where('share_token', $token)
            ->where('share_enabled', true)
            ->where('status', 'completed')
            ->first();

        if (! $episode) {
            abort(404);
        }

        return Inertia::render('Addons/ai-voiceover/Public/Player', [
            'episode' => $episode->only([
                'title', 'file_url', 'duration_seconds', 'duration_label', 'waveform_url',
            ]),
        ]);
    }
}
