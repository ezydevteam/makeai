<?php

namespace Addons\SocialScheduler\Database\Seeders;

use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Models\SsPostPlatform;
use Addons\SocialScheduler\Models\SsSocialAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class SocialSchedulerSeeder extends Seeder
{
    public function run(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ss_scheduled_posts')) {
            return;
        }

        if (SsScheduledPost::count() > 0) {
            return;
        }

        $user = User::first();
        if (! $user) {
            return;
        }

        // Demo accounts (inactive — no real OAuth tokens)
        $facebookAccount = SsSocialAccount::create([
            'user_id' => $user->id,
            'platform' => 'facebook',
            'platform_user_id' => 'demo_fb_' . $user->id,
            'platform_username' => 'demopage',
            'platform_name' => 'Demo Business Page',
            'account_type' => 'page',
            'is_active' => true,
            'access_token' => 'demo-token-placeholder',
            'scopes' => ['pages_manage_posts', 'pages_read_engagement'],
        ]);

        $instagramAccount = SsSocialAccount::create([
            'user_id' => $user->id,
            'platform' => 'instagram',
            'platform_user_id' => 'demo_ig_' . $user->id,
            'platform_username' => 'demobusiness',
            'platform_name' => 'Demo Business',
            'account_type' => 'business',
            'is_active' => true,
            'access_token' => 'demo-token-placeholder',
            'scopes' => ['instagram_basic', 'instagram_content_publish'],
        ]);

        $twitterAccount = SsSocialAccount::create([
            'user_id' => $user->id,
            'platform' => 'twitter',
            'platform_user_id' => 'demo_tw_' . $user->id,
            'platform_username' => 'demohandle',
            'platform_name' => 'Demo Handle',
            'account_type' => 'personal',
            'is_active' => true,
            'access_token' => 'demo-token-placeholder',
            'scopes' => ['tweet.read', 'tweet.write'],
        ]);

        $linkedinAccount = SsSocialAccount::create([
            'user_id' => $user->id,
            'platform' => 'linkedin',
            'platform_user_id' => 'demo_li_' . $user->id,
            'platform_username' => 'demouser',
            'platform_name' => 'Demo User',
            'account_type' => 'personal',
            'is_active' => true,
            'access_token' => 'demo-token-placeholder',
            'scopes' => ['openid', 'profile', 'w_member_social'],
        ]);

        // Demo Post 1: Scheduled
        $post1 = SsScheduledPost::create([
            'user_id' => $user->id,
            'title' => 'Product Launch Announcement',
            'caption' => 'Exciting news! We just launched our newest feature that will revolutionize how you create content. Check it out now! #MakeAI #AIContent',
            'platforms' => ['instagram', 'twitter', 'linkedin'],
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(2)->setHour(10)->setMinute(0),
            'post_type' => 'single',
            'approved_at' => now(),
        ]);

        SsPostPlatform::insert([
            ['ss_scheduled_post_id' => $post1->id, 'ss_social_account_id' => $instagramAccount->id, 'platform' => 'instagram', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['ss_scheduled_post_id' => $post1->id, 'ss_social_account_id' => $twitterAccount->id, 'platform' => 'twitter', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['ss_scheduled_post_id' => $post1->id, 'ss_social_account_id' => $linkedinAccount->id, 'platform' => 'linkedin', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Demo Post 2: Scheduled thread
        $post2 = SsScheduledPost::create([
            'user_id' => $user->id,
            'title' => 'Weekly Tips Thread',
            'caption' => '5 tips to improve your content strategy this week 🧵\n\n1/5 Know your audience — research before posting\n\n2/5 Consistency beats perfection\n\n3/5 Use AI to brainstorm, not replace creativity\n\n4/5 Schedule ahead with our calendar tool\n\n5/5 Measure and iterate\n\nWhat would you add?',
            'platforms' => ['twitter'],
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(3)->setHour(14)->setMinute(0),
            'post_type' => 'thread',
            'approved_at' => now(),
        ]);

        SsPostPlatform::create([
            'ss_scheduled_post_id' => $post2->id,
            'ss_social_account_id' => $twitterAccount->id,
            'platform' => 'twitter',
            'status' => 'pending',
        ]);

        // Demo Post 3: Draft carousel
        $post3 = SsScheduledPost::create([
            'user_id' => $user->id,
            'title' => 'Behind the Scenes',
            'caption' => 'A look behind the scenes at our team building the next big thing!',
            'platforms' => ['instagram', 'facebook'],
            'status' => 'draft',
            'scheduled_at' => null,
            'post_type' => 'carousel',
        ]);

        SsPostPlatform::insert([
            ['ss_scheduled_post_id' => $post3->id, 'ss_social_account_id' => $instagramAccount->id, 'platform' => 'instagram', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['ss_scheduled_post_id' => $post3->id, 'ss_social_account_id' => $facebookAccount->id, 'platform' => 'facebook', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
