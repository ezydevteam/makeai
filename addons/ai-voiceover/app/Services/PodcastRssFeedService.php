<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Services;

use Addons\AiVoiceover\Models\VoProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PodcastRssFeedService
{
    public function generateFeed(VoProject $project): string
    {
        $cacheKey = 'vo.rss.' . $project->rss_token;

        return Cache::remember($cacheKey, 900, function () use ($project) {
            return $this->buildXml($project);
        });
    }

    public function bustCache(VoProject $project): void
    {
        Cache::forget('vo.rss.' . $project->rss_token);
    }

    private function buildXml(VoProject $project): string
    {
        $appUrl = rtrim((string) settings('app_url', ''), '/');
        $appName = e(settings('app_name', 'MakeAI'));
        $rssUrl = $project->rss_url;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . e($project->title) . '</title>' . "\n";
        $xml .= '  <link>' . e($appUrl) . '</link>' . "\n";
        $xml .= '  <description>' . e($project->description ?: $project->title) . '</description>' . "\n";
        $xml .= '  <language>' . e($project->podcast_language ?: 'en') . '</language>' . "\n";
        $xml .= '  <itunes:author>' . e($project->podcast_author ?: $appName) . '</itunes:author>' . "\n";
        $xml .= '  <itunes:explicit>' . ($project->podcast_explicit ? 'true' : 'false') . '</itunes:explicit>' . "\n";

        $category = $project->podcast_category ?: 'Technology';
        $xml .= '  <itunes:category text="' . e($category) . '" />' . "\n";

        if ($project->cover_art_url) {
            $xml .= '  <itunes:image href="' . e($project->cover_art_url) . '" />' . "\n";
        }

        $xml .= '  <atom:link href="' . e($rssUrl) . '" rel="self" type="application/rss+xml" />' . "\n";
        $xml .= '  <lastBuildDate>' . now()->toRfc822String() . '</lastBuildDate>' . "\n";

        $episodes = $project->episodes()
            ->completed()
            ->published()
            ->orderByDesc('published_at')
            ->get();

        foreach ($episodes as $episode) {
            $xml .= '  <item>' . "\n";
            $xml .= '    <title>' . e($episode->title) . '</title>' . "\n";

            if ($episode->episode_number !== null) {
                $xml .= '    <itunes:episode>' . $episode->episode_number . '</itunes:episode>' . "\n";
            }
            if ($episode->season_number !== null) {
                $xml .= '    <itunes:season>' . $episode->season_number . '</itunes:season>' . "\n";
            }

            $description = $episode->script
                ? e(Str::limit(strip_tags($episode->script), 500))
                : e($episode->title);
            $xml .= '    <description>' . $description . '</description>' . "\n";

            if ($episode->file_url) {
                $enclosureUrl = str_starts_with((string) $episode->file_url, 'http')
                    ? $episode->file_url
                    : $appUrl . '/' . ltrim((string) $episode->file_url, '/');

                $xml .= '    <enclosure url="' . e($enclosureUrl) . '" '
                    . 'length="' . ($episode->file_size_bytes ?: 0) . '" '
                    . 'type="audio/mpeg" />' . "\n";
            }

            $xml .= '    <guid isPermaLink="false">' . e($episode->ulid) . '</guid>' . "\n";

            if ($episode->published_at) {
                $xml .= '    <pubDate>' . $episode->published_at->toRfc822String() . '</pubDate>' . "\n";
            }

            if ($episode->duration_seconds) {
                $xml .= '    <itunes:duration>' . gmdate('H:i:s', $episode->duration_seconds) . '</itunes:duration>' . "\n";
            }

            $xml .= '  </item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return $xml;
    }
}
