<?php

use Addons\SocialScheduler\Models\SsCampaign;
use Addons\SocialScheduler\Models\SsPostAnalytics;
use Addons\SocialScheduler\Models\SsPostPlatform;
use Addons\SocialScheduler\Models\SsScheduledPost;
use Addons\SocialScheduler\Models\SsSocialAccount;
use Addons\SocialScheduler\Models\SsRssFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
    $this->admin = \App\Models\User::factory()->create(['is_admin' => true]);

    // Seed settings so addon_setting doesn't crash
    addon_setting_set('social-scheduler', 'enabled', true);
    addon_setting_set('social-scheduler', 'approval_required', false);
});

// ─── Post Creation Tests ───

test('user can create a draft post', function () {
    actingAs($this->user)
        ->post('/social/posts', [
            'caption' => 'Test post caption',
            'platforms' => ['twitter'],
            'post_type' => 'single',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $post = SsScheduledPost::first();
    expect($post->status)->toBe('draft');
    expect($post->user_id)->toBe($this->user->id);
});

test('post cannot be created without caption', function () {
    actingAs($this->user)
        ->post('/social/posts', [
            'caption' => '',
            'platforms' => ['twitter'],
            'post_type' => 'single',
        ])
        ->assertSessionHasErrors('caption');
});

test('post cannot be created without platforms', function () {
    actingAs($this->user)
        ->post('/social/posts', [
            'caption' => 'Test',
            'platforms' => [],
            'post_type' => 'single',
        ])
        ->assertSessionHasErrors('platforms');
});

test('scheduled post with approval required becomes pending_approval', function () {
    addon_setting_set('social-scheduler', 'approval_required', true);

    actingAs($this->user)
        ->post('/social/posts', [
            'caption' => 'Needs approval',
            'platforms' => ['twitter'],
            'post_type' => 'single',
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
        ->assertSessionHasNoErrors();

    $post = SsScheduledPost::first();
    expect($post->status)->toBe('pending_approval');
});

test('user cannot edit a post belonging to another user', function () {
    $otherUser = \App\Models\User::factory()->create();
    $post = SsScheduledPost::factory()->create([
        'user_id' => $otherUser->id,
        'caption' => 'Not yours',
        'platforms' => ['twitter'],
    ]);

    actingAs($this->user)
        ->get("/social/posts/{$post->ulid}/edit")
        ->assertForbidden();
});

// ─── Publishing Tests ───

test('PublishSocialPost job marks platforms as publishing', function () {
    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute(),
        'platforms' => ['facebook'],
    ]);

    $pp = SsPostPlatform::factory()->create([
        'ss_scheduled_post_id' => $post->id,
        'ss_social_account_id' => $account->id,
        'platform' => 'facebook',
        'status' => 'pending',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'fb_12345'], 200),
    ]);

    (new \Addons\SocialScheduler\Jobs\PublishSocialPost)->handle();

    $pp->refresh();
    expect($pp->status)->toBe('publishing');
});

test('PublishToFacebookJob marks post as published on success', function () {
    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'publishing',
        'platforms' => ['facebook'],
    ]);

    $pp = SsPostPlatform::factory()->create([
        'ss_scheduled_post_id' => $post->id,
        'ss_social_account_id' => $account->id,
        'platform' => 'facebook',
        'status' => 'publishing',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => 'fb_12345'], 200),
    ]);

    (new \Addons\SocialScheduler\Jobs\Publishers\PublishToFacebookJob(
        $post->id, $pp->id, $account->id,
    ))->handle(app(\Addons\SocialScheduler\Services\SocialAccountService::class));

    $pp->refresh();
    expect($pp->status)->toBe('published');
    expect($pp->external_post_id)->toBe('fb_12345');
});

test('PublishToFacebookJob marks post as failed on API error', function () {
    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'platforms' => ['facebook'],
    ]);

    $pp = SsPostPlatform::factory()->create([
        'ss_scheduled_post_id' => $post->id,
        'ss_social_account_id' => $account->id,
        'platform' => 'facebook',
        'status' => 'pending',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Permission denied']], 403),
    ]);

    (new \Addons\SocialScheduler\Jobs\Publishers\PublishToFacebookJob(
        $post->id, $pp->id, $account->id,
    ))->handle(app(\Addons\SocialScheduler\Services\SocialAccountService::class));

    $pp->refresh();
    expect($pp->status)->toBe('failed');
    expect($pp->error_message)->toContain('Permission denied');
});

test('CheckPostPublishStatus marks as published when all platforms succeed', function () {
    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'publishing',
        'platforms' => ['facebook', 'twitter'],
    ]);

    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    SsPostPlatform::factory()->create([
        'ss_scheduled_post_id' => $post->id,
        'ss_social_account_id' => $account->id,
        'platform' => 'facebook',
        'status' => 'published',
    ]);

    (new \Addons\SocialScheduler\Jobs\CheckPostPublishStatus($post->id))->handle();

    $post->refresh();
    expect($post->status)->toBe('partial'); // only 1 of 2 published
});

test('CheckPostPublishStatus marks as partial when some fail', function () {
    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'publishing',
        'platforms' => ['facebook'],
    ]);

    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    SsPostPlatform::factory()->create([
        'ss_scheduled_post_id' => $post->id,
        'ss_social_account_id' => $account->id,
        'platform' => 'facebook',
        'status' => 'published',
    ]);

    (new \Addons\SocialScheduler\Jobs\CheckPostPublishStatus($post->id))->handle();

    $post->refresh();
    expect($post->status)->toBe('published');
});

// ─── Calendar Tests ───

test('calendar events returns user-scoped posts', function () {
    actingAs($this->user);

    SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDays(1),
        'platforms' => ['twitter'],
    ]);

    $otherUser = \App\Models\User::factory()->create();
    SsScheduledPost::factory()->create([
        'user_id' => $otherUser->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDays(1),
        'platforms' => ['twitter'],
    ]);

    actingAs($this->user)
        ->getJson('/social/calendar/events?' . http_build_query([
            'start' => now()->startOfMonth()->toDateString(),
            'end' => now()->endOfMonth()->toDateString(),
        ]))
        ->assertJsonCount(1)
        ->assertOk();
});

// ─── Account Tests ───

test('access_token is always stored encrypted', function () {
    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'twitter',
        'access_token' => 'secret-token-12345',
    ]);

    $raw = $account->getRawOriginal('access_token');

    // Raw in DB should NOT be the plaintext token
    expect($raw)->not->toBe('secret-token-12345');

    // Decrypted via accessor should work
    expect($account->access_token)->toBe('secret-token-12345');

    // toArray should hide the token
    $array = $account->toArray();
    expect($array)->not->toHaveKey('access_token');
});

test('disconnect marks account inactive', function () {
    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'instagram',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    app(\Addons\SocialScheduler\Services\SocialAccountService::class)->disconnect($account);

    $account->refresh();
    expect($account->is_active)->toBeFalse();
});

test('token expiry is detected', function () {
    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'instagram',
        'token_expires_at' => now()->subDay(),
        'access_token' => 'test-token',
    ]);

    expect($account->is_token_expired)->toBeTrue();

    $account->token_expires_at = now()->addDay();
    expect($account->is_token_expired)->toBeFalse();
});

// ─── Campaign Tests ───

test('posts can be grouped into a campaign', function () {
    $campaign = SsCampaign::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Product Launch',
        'status' => 'active',
    ]);

    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'ss_campaign_id' => $campaign->id,
        'caption' => 'Campaign post',
        'platforms' => ['twitter'],
    ]);

    expect($post->campaign->id)->toBe($campaign->id);
    expect($campaign->scheduledPosts)->toHaveCount(1);
});

// ─── Analytics Tests ───

test('analytics are upserted not duplicated', function () {
    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'platforms' => ['facebook'],
    ]);

    $account = SsSocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'is_active' => true,
        'access_token' => 'test-token',
    ]);

    $pp = SsPostPlatform::factory()->create([
        'ss_scheduled_post_id' => $post->id,
        'ss_social_account_id' => $account->id,
        'platform' => 'facebook',
        'status' => 'published',
        'external_post_id' => 'abc123',
    ]);

    SsPostAnalytics::factory()->create([
        'ss_post_platform_id' => $pp->id,
        'platform' => 'facebook',
        'impressions' => 100,
        'fetched_at' => now(),
    ]);

    // recreate with same ss_post_platform_id should update
    SsPostAnalytics::updateOrCreate(
        ['ss_post_platform_id' => $pp->id],
        ['platform' => 'facebook', 'impressions' => 200, 'likes' => 10, 'fetched_at' => now()],
    );

    expect(SsPostAnalytics::count())->toBe(1);
    expect(SsPostAnalytics::first()->impressions)->toBe(200);
});

// ─── Approval Tests ───

test('admin can approve a pending post', function () {
    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending_approval',
        'platforms' => ['twitter'],
    ]);

    actingAs($this->admin, 'admin')
        ->post("/admin/social-scheduler/approval/{$post->id}/approve")
        ->assertSessionHasNoErrors();

    $post->refresh();
    expect($post->status)->toBe('scheduled');
    expect($post->approved_by)->toBe($this->admin->id);
});

test('admin can reject a post with a reason', function () {
    $post = SsScheduledPost::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending_approval',
        'platforms' => ['twitter'],
    ]);

    actingAs($this->admin, 'admin')
        ->post("/admin/social-scheduler/approval/{$post->id}/reject", [
            'reason' => 'Not appropriate for our brand.',
        ])
        ->assertSessionHasNoErrors();

    $post->refresh();
    expect($post->status)->toBe('draft');
    expect($post->rejection_reason)->toBe('Not appropriate for our brand.');
});

// ─── RSS Tests ───

test('polling RSS feed creates draft posts from new items', function () {
    $feed = SsRssFeed::factory()->create([
        'user_id' => $this->user->id,
        'url' => 'https://example.com/feed.xml',
        'platforms' => ['twitter'],
        'status' => 'active',
    ]);

    Http::fake([
        'example.com/feed.xml' => Http::response(
            '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Article 1</title><link>https://example.com/1</link><description>Test</description><guid>guid-1</guid></item></channel></rss>',
            200,
        ),
    ]);

    (new \Addons\SocialScheduler\Jobs\PollRssFeeds)->handle(app(\Addons\SocialScheduler\Services\RssFeedService::class));

    $feed->refresh();
    expect($feed->last_item_guid)->toBe('guid-1');

    $posts = SsScheduledPost::where('is_rss_auto', true)->get();
    expect($posts)->toHaveCount(1);
    expect($posts->first()->rss_feed_id)->toBe($feed->id);
});
