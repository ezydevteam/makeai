<?php

namespace App\Services;

use App\Models\SocialFollowCount;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
                        'last_error' => translate('API fetching is not configured for this platform.'),
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

        return [];
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
        return "social_follow_{$platform}_api_key";
    }

    public function externalIdSettingKey(string $platform): string
    {
        return "social_follow_{$platform}_external_id";
    }
}
