# ADDON 08: Social Media Scheduler Pro — Implementation Plan

## Scope: v1.0
- 4 platforms: Facebook, Instagram, Twitter (X), LinkedIn
- TikTok, Pinterest, YouTube deferred to v1.1
- Add `ss_campaigns` table for post grouping (not in original guide)
- In-house date-grid calendar (not vue-cal)
- Settings: `addon_setting('social-scheduler', 'key')` convention
- Auth middleware: `admin.auth` + `auth` (not `auth:admin`)
- AI: `ProviderRegistry::resolve()` + direct adapter (no `CompletionRequest` DTO)

## Migrations (8 tables — guide's 7 + campaigns)
1. `ss_social_accounts` — connected OAuth accounts
2. `ss_campaigns` — post campaign grouping (NEW — not in guide)
3. `ss_scheduled_posts` — add `ss_campaign_id` FK
4. `ss_post_media` — attached images/video
5. `ss_carousel_slides` — carousel slide data
6. `ss_post_platforms` — per-platform publish status
7. `ss_rss_feeds` — RSS auto-post feeds
8. `ss_post_analytics` — per-post analytics

## Build Order (16 steps → 14 steps after deferring 3 platforms)

### Step 1: addon.json + AddonServiceProvider
- Admin menu: 1 collapsible parent "Social Scheduler" → Overview, Approval, Settings
- Inertia share: safe settings only (never AI model, API tokens)
- Register scheduled jobs, singleton services

### Step 2: 8 migrations

### Step 3: 8 models (7 from guide + SsCampaign)

### Step 4: Seeder — demo posts for admin user

### Step 5: SocialAccountService — OAuth for 4 platforms
- Facebook/Instagram: Socialite facebook driver → Graph API page token exchange
- Twitter: Socialite twitter-oauth-2 → PKCE + refresh token
- LinkedIn: Socialite linkedin-openid → v2 API token
- Token encryption: Crypt::encryptString on set, never in toArray()

### Step 6: Platform Publisher Jobs (4 platforms)
- PublishToFacebookJob, PublishToInstagramJob, PublishToTwitterJob, PublishToLinkedInJob
- All: queue 'social', tries 3, backoff [60,300,900]
- Instagram: Graph API container → publish → optional first comment
- Facebook: Graph API page post (photos/feed)
- Twitter: X API v2 tweet, thread support
- LinkedIn: v2 UGC Posts API

### Step 7: PublishSocialPost orchestrator + CheckPostPublishStatus
- Due posts → dispatch platform jobs → callback to update overall status

### Step 8: AiCaptionService — platform-aware caption streaming
- Stream via POST + ReadableStream
- Platform tone guides per platform

### Step 9: RssFeedService + PollRssFeeds job

### Step 10: BestTimeService — AI-suggested posting time

### Step 11: FetchPostAnalytics job (daily)

### Step 12: Admin Controllers — Overview, Approval, Settings

### Step 13: User Controllers — Accounts (OAuth), Posts (CRUD), Calendar, Analytics, RSS Feeds, Caption streaming

### Step 14: Routes — admin (/admin/social-scheduler), user (/social)

### Step 15: Vue Pages
- User/Calendar.vue — in-house date-grid with drag-drop, platform filter
- User/Posts/Composer.vue — two-column: form + live preview, RichEditor for caption
- User/Accounts.vue — grid of platform cards with connect/disconnect
- User/Analytics.vue — dashboard
- Admin/Overview.vue — stat cards + recent posts
- Admin/Approval.vue — approval queue
- Admin/Settings.vue — settings form

### Step 16: Pest Tests

## Key Corrections to Guide
1. `CompletionRequest` DTO doesn't exist → use adapter directly
2. `auth:admin` → `admin.auth`
3. `settings('social_XXX')` → `addon_setting('social-scheduler', 'XXX')`
4. No `config/services.php` OAuth configs → store in addon settings
5. Admin menu: merge into collapsible parent, add Approval link
6. OAuth callback routes must exclude CSRF

## Recommendations (beyond guide)
1. Campaign grouping (ss_campaigns) — in scope
2. Reconnect flow for expired tokens — in scope
3. Platform OAuth credentials as addon settings — in scope (no services.php mods)
4. TikTok, Pinterest, YouTube deferred to v1.1
5. vue-cal replaced with in-house date-grid
