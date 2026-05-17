<?php

namespace App\Services;

use App\Models\SocialFollowCount;

class SocialService
{
    /**
     * Get follow counts for all active platforms.
     */
    public function getFollowCounts(): array
    {
        return SocialFollowCount::where('is_active', true)->pluck('count', 'platform')->toArray();
    }

    /**
     * Refresh counts from external APIs.
     */
    public function refreshCounts(): void
    {
        $platforms = ['facebook', 'twitter', 'youtube', 'instagram', 'linkedin'];

        foreach ($platforms as $platform) {
            $count = $this->fetchCount($platform);

            SocialFollowCount::updateOrCreate(
                ['platform' => $platform],
                ['count' => $count, 'is_active' => true]
            );
        }
    }

    /**
     * Fetch count from a specific platform.
     */
    protected function fetchCount(string $platform): int
    {
        // Mocking logic for now. Real implementation would use API keys from settings.
        // e.g. $apiKey = settings($platform . '_api_key');

        return match ($platform) {
            'facebook' => rand(5000, 10000),
            'twitter' => rand(12000, 15000),
            'youtube' => rand(20000, 30000),
            'instagram' => rand(8000, 12000),
            'linkedin' => rand(3000, 5000),
            default => 0,
        };
    }

    /**
     * Generate social share URLs.
     */
    public static function getShareUrls(string $url, string $title = ''): array
    {
        $url = urlencode($url);
        $title = urlencode($title);

        return [
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
            'twitter' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
            'whatsapp' => "https://api.whatsapp.com/send?text={$title}%20{$url}",
            'telegram' => "https://t.me/share/url?url={$url}&text={$title}",
            'reddit' => "https://www.reddit.com/submit?url={$url}&title={$title}",
        ];
    }
}
