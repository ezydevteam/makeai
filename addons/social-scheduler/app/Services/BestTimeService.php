<?php

namespace Addons\SocialScheduler\Services;

use Addons\SocialScheduler\Models\SsPostAnalytics;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BestTimeService
{
    public function __construct(private AiService $ai) {}

    public function suggestBestTime(User $user, string $platform, string $contentType): array
    {
        $cacheKey = "social.best_time.{$user->id}.{$platform}";

        return Cache::remember($cacheKey, 3600, function () use ($user, $platform, $contentType) {
            $analytics = SsPostAnalytics::join('ss_post_platforms', 'ss_post_platforms.id', '=', 'ss_post_analytics.ss_post_platform_id')
                ->join('ss_scheduled_posts', 'ss_scheduled_posts.id', '=', 'ss_post_platforms.ss_scheduled_post_id')
                ->where('ss_scheduled_posts.user_id', $user->id)
                ->where('ss_post_analytics.platform', $platform)
                ->where('ss_scheduled_posts.published_at', '>=', now()->subDays(90))
                ->select([
                    DB::raw('HOUR(ss_scheduled_posts.published_at) as hour'),
                    DB::raw('DAYOFWEEK(ss_scheduled_posts.published_at) as day_of_week'),
                    DB::raw('AVG(ss_post_analytics.engagement_rate) as avg_engagement'),
                    DB::raw('COUNT(*) as post_count'),
                ])
                ->groupBy('hour', 'day_of_week')
                ->orderByDesc('avg_engagement')
                ->limit(20)
                ->get();

            $prompt = $this->buildPrompt($analytics, $platform, $contentType);

            $providerName = addon_setting('social-scheduler', 'provider');
            if (empty($providerName)) {
                $providerName = settings('default_ai_provider', 'openai');
            }

            $modelName = addon_setting('social-scheduler', 'best_time_model');
            if (empty($modelName)) {
                $modelName = settings('default_ai_model', 'gpt-4o-mini');
            }

            try {
                $adapter = ProviderRegistry::resolve($providerName);
                $response = $adapter->chatCompletion([
                    ['role' => 'user', 'content' => $prompt],
                ], $modelName, ['max_tokens' => 300]);

                $content = $response['content'] ?? '';

                // Extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $m)) {
                    $suggestion = json_decode($m[0], true);
                    if ($suggestion && isset($suggestion['suggested_time'])) {
                        return [
                            'suggested_time' => Carbon::parse($suggestion['suggested_time']),
                            'reasoning' => $suggestion['reasoning'] ?? '',
                            'alternatives' => array_map(fn ($t) => Carbon::parse($t), $suggestion['alternatives'] ?? []),
                        ];
                    }
                }
            } catch (\Throwable) {
                // Fall through to default
            }

            // Reasonable default: Mon-Fri 9am UTC
            return [
                'suggested_time' => now()->addDay()->setHour(9)->setMinute(0)->setSecond(0),
                'reasoning' => 'Based on general best practices.',
                'alternatives' => [
                    now()->addDay()->setHour(12)->setMinute(0)->setSecond(0),
                    now()->addDay()->setHour(17)->setMinute(0)->setSecond(0),
                ],
            ];
        });
    }

    private function buildPrompt($analytics, string $platform, string $contentType): string
    {
        if ($analytics->count() < 5) {
            return "Based on general best practices for {$platform}, suggest the best time to post {$contentType} content. User timezone: UTC. Return JSON only: {\"suggested_time\":\"2026-01-15T10:00:00Z\",\"reasoning\":\"...\",\"alternatives\":[\"2026-01-15T12:00:00Z\",\"2026-01-15T17:00:00Z\"]}";
        }

        $dataDescription = $analytics->map(fn ($row) =>
            "Day: {$row->day_of_week}, Hour: {$row->hour}:00, Avg Engagement: {$row->avg_engagement}%, Posts: {$row->post_count}"
        )->implode("\n");

        return "Analyze this user's past post performance on {$platform} and suggest the optimal posting time for {$contentType} content:\n{$dataDescription}\nReturn JSON only (no markdown): {\"suggested_time\":\"ISO8601\",\"reasoning\":\"...\",\"alternatives\":[\"ISO8601\",\"ISO8601\"]}";
    }
}
