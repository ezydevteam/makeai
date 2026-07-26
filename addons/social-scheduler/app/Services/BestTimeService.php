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
        $cacheKey = "social.best_time.{$user->id}.{$platform}.{$contentType}";
        $cachedSuggestion = Cache::get($cacheKey);

        if (is_array($cachedSuggestion)) {
            $normalizedSuggestion = $this->normalizeSuggestion($cachedSuggestion);

            if ($normalizedSuggestion !== null) {
                return $normalizedSuggestion;
            }
        }

        $freshSuggestion = $this->buildSuggestion($user, $platform, $contentType);

        Cache::put($cacheKey, $this->serializeSuggestion($freshSuggestion), 3600);

        return $freshSuggestion;
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

    private function buildSuggestion(User $user, string $platform, string $contentType): array
    {
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
            $modelName = settings('default_ai_model', config('ai.fallback_model'));
        }

        try {
            $adapter = ProviderRegistry::resolve($providerName);
            $response = $adapter->chatCompletion([
                ['role' => 'user', 'content' => $prompt],
            ], $modelName, ['max_tokens' => 300]);

            $content = $response['content'] ?? '';

            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $suggestion = json_decode($matches[0], true);

                if (is_array($suggestion) && isset($suggestion['suggested_time'])) {
                    $normalizedSuggestion = $this->normalizeSuggestion($suggestion);

                    if ($normalizedSuggestion !== null) {
                        return $normalizedSuggestion;
                    }
                }
            }
        } catch (\Throwable) {
            // Fall through to the default suggestion.
        }

        return $this->defaultSuggestion();
    }

    private function normalizeSuggestion(array $suggestion): ?array
    {
        try {
            $suggestedTime = $this->normalizeTimeValue($suggestion['suggested_time'] ?? null);
            if ($suggestedTime === null) {
                return null;
            }

            $alternatives = [];

            foreach ($suggestion['alternatives'] ?? [] as $alternative) {
                $normalizedAlternative = $this->normalizeTimeValue($alternative);

                if ($normalizedAlternative !== null) {
                    $alternatives[] = $normalizedAlternative;
                }
            }

            return [
                'suggested_time' => $suggestedTime,
                'reasoning' => (string) ($suggestion['reasoning'] ?? ''),
                'alternatives' => $alternatives,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTimeValue(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        if (is_object($value)) {
            $properties = get_object_vars($value);
            $dateString = $properties['date'] ?? null;

            if (is_string($dateString) && $dateString !== '') {
                return Carbon::parse($dateString);
            }
        }

        return null;
    }

    private function serializeSuggestion(array $suggestion): array
    {
        return [
            'suggested_time' => $suggestion['suggested_time']->toISOString(),
            'reasoning' => $suggestion['reasoning'],
            'alternatives' => array_map(
                fn (Carbon $alternative) => $alternative->toISOString(),
                $suggestion['alternatives'],
            ),
        ];
    }

    private function defaultSuggestion(): array
    {
        return [
            'suggested_time' => now()->addDay()->setHour(9)->setMinute(0)->setSecond(0),
            'reasoning' => translate('Based on general best practices.'),
            'alternatives' => [
                now()->addDay()->setHour(12)->setMinute(0)->setSecond(0),
                now()->addDay()->setHour(17)->setMinute(0)->setSecond(0),
            ],
        ];
    }
}
