<?php

namespace App\Services;

use App\Models\SocialFollowCount;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialService
{
    public const SHARE_NETWORKS = [
        'facebook' => 'Facebook',
        'x' => 'X',
        'linkedin' => 'LinkedIn',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
        'pinterest' => 'Pinterest',
        'reddit' => 'Reddit',
        'email' => 'Email',
        'copy' => 'Copy link',
    ];

    public const SHARE_STYLES = [
        'icon',
        'icon-label',
        'icon-count',
    ];

    public const FOLLOW_PLATFORMS = [
        'facebook' => ['label' => 'Facebook', 'unit' => 'Fans'],
        'x' => ['label' => 'X', 'unit' => 'Followers'],
        'instagram' => ['label' => 'Instagram', 'unit' => 'Followers'],
        'linkedin' => ['label' => 'LinkedIn', 'unit' => 'Connections'],
        'youtube' => ['label' => 'YouTube', 'unit' => 'Subscribers'],
        'tiktok' => ['label' => 'TikTok', 'unit' => 'Followers'],
        'github' => ['label' => 'GitHub', 'unit' => 'Followers'],
        'discord' => ['label' => 'Discord', 'unit' => 'Members'],
    ];

    public const FOLLOW_DISPLAY_MODES = [
        'icons',
        'counts',
        'cards',
    ];

    /**
     * Get follow counts for all active platforms.
     */
    public function getFollowCounts(): array
    {
        return $this->activeFollowProfiles()
            ->mapWithKeys(fn (SocialFollowCount $profile) => [
                $profile->platform => $this->displayCount($profile),
            ])
            ->all();
    }

    /**
     * Refresh counts from external APIs.
     */
    public function refreshCounts(): void
    {
        SocialFollowCount::query()
            ->where('fetch_enabled', true)
            ->where('is_active', true)
            ->each(function (SocialFollowCount $profile) {
                if ($profile->count_source !== 'api') {
                    return;
                }

                if (blank(settings($this->apiKeySettingKey($profile->platform)))) {
                    $profile->forceFill([
                        'last_fetched_at' => now(),
                        'last_error' => translate('API key is not configured for this platform.'),
                    ])->save();

                    return;
                }

                $count = $this->fetchCount($profile->platform, $profile->profile_url);

                if ($count === null) {
                    $profile->forceFill([
                        'last_fetched_at' => now(),
                        'last_error' => translate('API fetching is not yet supported for :platform. Contact the developer for custom integration.', ['platform' => $profile->platform]),
                    ])->save();

                    return;
                }

                $profile->forceFill([
                    'count' => $count,
                    'count_source' => 'api',
                    'last_fetched_at' => now(),
                    'last_error' => null,
                ])->save();
            });
    }

    /**
     * Fetch count from a specific platform.
     */
    protected function fetchCount(string $platform, ?string $profileUrl = null): ?int
    {
        return match ($platform) {
            'facebook' => $this->fetchFacebookCount($profileUrl),
            'youtube' => $this->fetchYoutubeCount($profileUrl),
            'github' => $this->fetchGithubCount($profileUrl),
            default => null,
        };
    }

    private function fetchFacebookCount(?string $profileUrl): ?int
    {
        if (! $profileUrl) {
            return null;
        }

        $pageId = $this->extractFacebookPageId($profileUrl);
        if (! $pageId) {
            return null;
        }

        $apiToken = settings($this->apiKeySettingKey('facebook'), '');

        try {
            $response = Http::timeout(10)
                ->get("https://graph.facebook.com/v21.0/{$pageId}", [
                    'fields' => 'fan_count',
                    'access_token' => $apiToken,
                ]);

            if ($response->successful() && isset($response['fan_count'])) {
                return (int) $response['fan_count'];
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook Graph API fetch failed for page.', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function extractFacebookPageId(?string $profileUrl): ?string
    {
        if (! $profileUrl) {
            return null;
        }

        if (preg_match('/facebook\.com\/([^\/\?\s]+)/', $profileUrl, $m)) {
            $slug = $m[1];
            if (is_numeric($slug)) {
                return $slug;
            }
        }

        $externalId = settings($this->externalIdSettingKey('facebook'));
        if ($externalId) {
            return (string) $externalId;
        }

        return null;
    }

    private function fetchYoutubeCount(?string $profileUrl): ?int
    {
        if (! $profileUrl) {
            return null;
        }

        $channelId = $this->extractYoutubeChannelId($profileUrl);
        if (! $channelId) {
            return null;
        }

        $apiKey = settings($this->apiKeySettingKey('youtube'), '');

        try {
            $response = Http::timeout(10)
                ->get('https://www.googleapis.com/youtube/v3/channels', [
                    'id' => $channelId,
                    'part' => 'statistics',
                    'key' => $apiKey,
                ]);

            if ($response->successful()) {
                $count = data_get($response->json(), 'items.0.statistics.subscriberCount');
                if ($count !== null) {
                    return (int) $count;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('YouTube API fetch failed for channel.', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function extractYoutubeChannelId(?string $profileUrl): ?string
    {
        if (! $profileUrl) {
            return null;
        }

        if (preg_match('/youtube\.com\/channel\/([^\/\?\s]+)/', $profileUrl, $m)) {
            return $m[1];
        }

        if (preg_match('/youtube\.com\/@([^\/\?\s]+)/', $profileUrl, $m)) {
            $handle = $m[1];
            $apiKey = settings($this->apiKeySettingKey('youtube'), '');

            try {
                $response = Http::timeout(10)
                    ->get('https://www.googleapis.com/youtube/v3/channels', [
                        'forHandle' => $handle,
                        'part' => 'id',
                        'key' => $apiKey,
                    ]);

                if ($response->successful()) {
                    $id = data_get($response->json(), 'items.0.id');
                    if ($id) {
                        return $id;
                    }
                }
            } catch (\Throwable) {
                // Continue to external ID fallback
            }
        }

        $externalId = settings($this->externalIdSettingKey('youtube'));
        if ($externalId) {
            return (string) $externalId;
        }

        return null;
    }

    private function fetchGithubCount(?string $profileUrl): ?int
    {
        if (! $profileUrl) {
            return null;
        }

        if (! preg_match('/github\.com\/([^\/\?\s]+)/', $profileUrl, $m)) {
            return null;
        }

        $username = $m[1];
        $apiToken = settings($this->apiKeySettingKey('github'), '');

        try {
            $req = Http::timeout(10);
            if ($apiToken) {
                $req = $req->withToken($apiToken);
            }

            $response = $req->get("https://api.github.com/users/{$username}");

            if ($response->successful() && isset($response['followers'])) {
                return (int) $response['followers'];
            }

            if ($response->status() === 403) {
                Log::warning('GitHub API rate limit hit. Add a token to increase limits.', [
                    'username' => $username,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('GitHub API fetch failed for user.', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Generate social share URLs.
     */
    public static function getShareUrls(string $url, string $title = '', ?string $image = null): array
    {
        $encodedUrl = rawurlencode($url);
        $encodedTitle = rawurlencode($title);
        $encodedImage = rawurlencode((string) $image);

        return [
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'x' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedTitle}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}",
            'whatsapp' => "https://api.whatsapp.com/send?text={$encodedTitle}%20{$encodedUrl}",
            'telegram' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedTitle}",
            'pinterest' => "https://www.pinterest.com/pin/create/button/?url={$encodedUrl}&description={$encodedTitle}&media={$encodedImage}",
            'reddit' => "https://www.reddit.com/submit?url={$encodedUrl}&title={$encodedTitle}",
            'email' => "mailto:?subject={$encodedTitle}&body={$encodedTitle}%0A{$encodedUrl}",
        ];
    }

    public function sharePayload(string $url, string $title = '', ?string $image = null): array
    {
        $networks = $this->enabledShareNetworks();
        $urls = static::getShareUrls($url, $title, $image);

        return [
            'url' => $url,
            'title' => $title,
            'style' => $this->shareStyle(),
            'networks' => $networks,
            'urls' => Arr::only($urls, array_diff($networks, ['copy'])),
            'counts' => $this->shareCounts($url, $networks),
            'show_counts' => (bool) settings('social_share_show_counts', false),
        ];
    }

    public function enabledShareNetworks(): array
    {
        $networks = settings('social_share_networks', array_keys(self::SHARE_NETWORKS));

        if (! is_array($networks)) {
            \Illuminate\Support\Facades\Log::warning('social_share_networks setting is corrupted, falling back to defaults.', [
                'value' => $networks,
            ]);

            settings_set('social_share_networks', array_keys(self::SHARE_NETWORKS), 'json', 'social');

            $networks = array_keys(self::SHARE_NETWORKS);
        }

        return collect($networks)
            ->map(fn ($network) => (string) $network)
            ->filter(fn ($network) => array_key_exists($network, self::SHARE_NETWORKS))
            ->unique()
            ->values()
            ->all();
    }

    public function shareStyle(): string
    {
        $style = (string) settings('social_share_blog_style', 'icon-label');

        return in_array($style, self::SHARE_STYLES, true) ? $style : 'icon-label';
    }

    public function shareCounts(string $url, array $networks): array
    {
        if (! (bool) settings('social_share_show_counts', false)) {
            return [];
        }

        $counts = [];

        if (in_array('facebook', $networks, true)) {
            $counts['facebook'] = $this->fetchFacebookShareCount($url);
        }

        if (in_array('pinterest', $networks, true)) {
            $counts['pinterest'] = $this->fetchPinterestShareCount($url);
        }

        return $counts;
    }

    private function fetchFacebookShareCount(string $url): ?int
    {
        $apiToken = settings($this->apiKeySettingKey('facebook'), '');

        if (! $apiToken) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get('https://graph.facebook.com/v21.0/', [
                    'id' => $url,
                    'fields' => 'engagement',
                    'access_token' => $apiToken,
                ]);

            if ($response->successful() && isset($response['engagement']['share_count'])) {
                return (int) $response['engagement']['share_count'];
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook share count fetch failed.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function fetchPinterestShareCount(string $url): ?int
    {
        try {
            $response = Http::timeout(10)
                ->get('https://api.pinterest.com/v1/urls/count.json', [
                    'url' => $url,
                ]);

            if ($response->successful()) {
                $body = $response->body();
                if (preg_match('/"count":(\d+)/', $body, $m)) {
                    return (int) $m[1];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Pinterest share count fetch failed.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function followPayload(?string $displayMode = null): array
    {
        return [
            'display_mode' => $this->followDisplayMode($displayMode),
            'profiles' => $this->activeFollowProfiles()
                ->map(fn (SocialFollowCount $profile) => $this->followProfilePayload($profile))
                ->values()
                ->all(),
        ];
    }

    public function followPlatforms(): array
    {
        return collect(self::FOLLOW_PLATFORMS)
            ->map(fn (array $platform, string $key) => [
                'platform' => $key,
                'label' => translate($platform['label']),
                'unit' => translate($platform['unit']),
            ])
            ->values()
            ->all();
    }

    public function followDisplayMode(?string $displayMode = null): string
    {
        $mode = $displayMode ?: (string) settings('social_follow_display_mode', 'counts');

        return in_array($mode, self::FOLLOW_DISPLAY_MODES, true) ? $mode : 'counts';
    }

    public function configuredFollowProfiles(): array
    {
        $profiles = SocialFollowCount::query()
            ->get()
            ->keyBy('platform');

        return collect(self::FOLLOW_PLATFORMS)
            ->map(function (array $platform, string $key) use ($profiles) {
                $profile = $profiles->get($key);

                return [
                    'platform' => $key,
                    'label' => translate($platform['label']),
                    'unit' => translate($platform['unit']),
                    'profile_url' => $profile?->profile_url,
                    'count' => $profile?->count ?? 0,
                    'manual_count' => $profile?->manual_count,
                    'count_source' => $profile?->count_source ?? 'manual',
                    'fetch_enabled' => (bool) ($profile?->fetch_enabled ?? false),
                    'api_key_configured' => filled(settings($this->apiKeySettingKey($key))),
                    'external_id' => settings($this->externalIdSettingKey($key)),
                    'sort_order' => $profile?->sort_order ?? $this->defaultSortOrder($key),
                    'is_active' => (bool) ($profile?->is_active ?? false),
                    'last_fetched_at' => $profile?->last_fetched_at?->toISOString(),
                    'last_error' => $profile?->last_error,
                ];
            })
            ->values()
            ->all();
    }

    private function activeFollowProfiles(): Collection
    {
        return SocialFollowCount::query()
            ->where('is_active', true)
            ->whereNotNull('profile_url')
            ->orderBy('sort_order')
            ->orderBy('platform')
            ->get()
            ->filter(fn (SocialFollowCount $profile) => array_key_exists($profile->platform, self::FOLLOW_PLATFORMS));
    }

    private function followProfilePayload(SocialFollowCount $profile): array
    {
        $platform = self::FOLLOW_PLATFORMS[$profile->platform];

        return [
            'platform' => $profile->platform,
            'label' => translate($platform['label']),
            'unit' => translate($platform['unit']),
            'url' => $profile->profile_url,
            'count' => $this->displayCount($profile),
        ];
    }

    private function displayCount(SocialFollowCount $profile): int
    {
        return (int) ($profile->manual_count ?? $profile->count ?? 0);
    }

    private function defaultSortOrder(string $platform): int
    {
        return (int) array_search($platform, array_keys(self::FOLLOW_PLATFORMS), true);
    }

    public function apiKeySettingKey(string $platform): string
    {
        // Must match the key SocialCountersController::updateFollow() writes, i.e.
        // social_follow_api_key_{platform} — not social_follow_{platform}_api_key.
        return "social_follow_api_key_{$platform}";
    }

    public function externalIdSettingKey(string $platform): string
    {
        return "social_follow_external_id_{$platform}";
    }
}
