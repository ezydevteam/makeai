<?php

namespace Addons\SocialScheduler\Http\Controllers\User;

use Addons\SocialScheduler\Models\SsPostAnalytics;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SsAnalyticsController extends Controller
{
    public function index(): \Inertia\Response
    {
        $userId = auth()->id();

        $platforms = SsPostAnalytics::join('ss_post_platforms', 'ss_post_platforms.id', '=', 'ss_post_analytics.ss_post_platform_id')
            ->join('ss_scheduled_posts', 'ss_scheduled_posts.id', '=', 'ss_post_platforms.ss_scheduled_post_id')
            ->where('ss_scheduled_posts.user_id', $userId)
            ->select(
                'ss_post_analytics.platform',
                DB::raw('SUM(ss_post_analytics.impressions) as total_impressions'),
                DB::raw('SUM(ss_post_analytics.likes) as total_likes'),
                DB::raw('SUM(ss_post_analytics.comments) as total_comments'),
                DB::raw('SUM(ss_post_analytics.shares) as total_shares'),
                DB::raw('AVG(ss_post_analytics.engagement_rate) as avg_engagement'),
                DB::raw('COUNT(*) as post_count'),
            )
            ->groupBy('platform')
            ->get();

        $topPosts = SsPostAnalytics::join('ss_post_platforms', 'ss_post_platforms.id', '=', 'ss_post_analytics.ss_post_platform_id')
            ->join('ss_scheduled_posts', 'ss_scheduled_posts.id', '=', 'ss_post_platforms.ss_scheduled_post_id')
            ->where('ss_scheduled_posts.user_id', $userId)
            ->orderByDesc('ss_post_analytics.engagement_rate')
            ->limit(10)
            ->get([
                'ss_scheduled_posts.title',
                'ss_scheduled_posts.caption',
                'ss_post_platforms.platform',
                'ss_post_platforms.external_post_url',
                'ss_post_analytics.impressions',
                'ss_post_analytics.engagement_rate',
                'ss_post_analytics.likes',
                'ss_post_analytics.comments',
            ]);

        return inertia('Addons/social-scheduler/User/Analytics', [
            'platforms' => $platforms,
            'top_posts' => $topPosts->map(fn ($p) => [
                'title' => $p->title,
                'caption' => \Illuminate\Support\Str::limit($p->caption, 80),
                'platform' => $p->platform,
                'external_post_url' => $p->external_post_url,
                'impressions' => $p->impressions,
                'engagement_rate' => $p->engagement_rate,
                'likes' => $p->likes,
                'comments' => $p->comments,
            ]),
        ]);
    }
}
