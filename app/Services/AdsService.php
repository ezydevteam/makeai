<?php

namespace App\Services;

use App\Jobs\IncrementAdImpressions;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdsService
{
    public function shouldShowAds(?User $user = null): bool
    {
        if (! (bool) settings('ads_enabled', true)) {
            return false;
        }

        $user ??= Auth::user();

        if (isProAvailable() && $user && (bool) settings('ads_disable_for_subscribed_users', false)) {
            if (in_array($user->subscription_status, ['active', 'trialing'], true)) {
                return false;
            }
        }

        $disabledPlanIds = settings('ads_disabled_plan_ids', []);
        if ($user && $user->plan_id && is_array($disabledPlanIds) && in_array((int) $user->plan_id, array_map('intval', $disabledPlanIds), true)) {
            return false;
        }

        return true;
    }

    public function activeForZone(string $zone, ?User $user = null): ?Ad
    {
        $user ??= Auth::user();

        if (! $this->shouldShowAds($user)) {
            return null;
        }

        return Ad::active()
            ->where('zone', $zone)
            ->orderBy('sort_order')
            ->inRandomOrder()
            ->get()
            ->first(fn (Ad $ad) => $this->matchesAudience($ad, $user));
    }

    public function render(string $zone): string
    {
        $ad = $this->activeForZone($zone);

        if (! $ad) {
            return '';
        }

        IncrementAdImpressions::dispatch($ad->id)->onQueue('low');

        return match ($ad->type) {
            'adsense' => $this->renderAdsense($ad),
            'custom_html' => (string) $ad->custom_html,
            default => $this->renderImageLink($ad),
        };
    }

    public function toFrontend(Ad $ad): array
    {
        return [
            'id' => $ad->id,
            'title' => $ad->title,
            'type' => $ad->type,
            'zone' => $ad->zone,
            'custom_html' => $ad->custom_html,
            'adsense_client' => $ad->adsense_client ?: settings('adsense_publisher_id'),
            'adsense_slot' => $ad->adsense_slot,
            'adsense_format' => $ad->adsense_format ?: 'auto',
            'image_url' => $ad->image_url,
            'link_url' => $ad->link_url,
            'link_target' => $ad->link_target ?: '_blank',
            'click_url' => $ad->link_url ? route('ads.click', $ad) : null,
        ];
    }

    private function matchesAudience(Ad $ad, ?User $user): bool
    {
        return match ($ad->show_to) {
            'guests' => $user === null,
            'logged_in' => $user !== null,
            'free_users' => $user !== null && ! in_array($user->subscription_status, ['active', 'trialing'], true),
            'paid_users' => $user !== null && in_array($user->subscription_status, ['active', 'trialing'], true),
            default => true,
        };
    }

    private function renderAdsense(Ad $ad): string
    {
        $client = e($ad->adsense_client ?: settings('adsense_publisher_id'));
        $slot = e($ad->adsense_slot);
        $format = e($ad->adsense_format ?: 'auto');

        if (! $client || ! $slot) {
            return '';
        }

        return <<<HTML
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="{$client}"
     data-ad-slot="{$slot}"
     data-ad-format="{$format}"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
HTML;
    }

    private function renderImageLink(Ad $ad): string
    {
        $image = e($ad->image_url);
        $title = e($ad->title);
        $target = e($ad->link_target ?: '_blank');
        $url = e(route('ads.click', $ad));

        if (! $image || ! $ad->link_url) {
            return '';
        }

        return <<<HTML
<a href="{$url}" target="{$target}" rel="noopener noreferrer">
    <img src="{$image}" alt="{$title}" loading="lazy">
</a>
HTML;
    }
}
