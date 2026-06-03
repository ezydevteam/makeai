# MakeAI — Addon Development Complete Guide

> This document covers everything needed to build, package, and distribute addons for MakeAI.
> Stack: PHP 8.3+ / Laravel 12+ / Vue 3 / Inertia.js / Tailwind CSS v4

---

## PART 1 — ADDON SYSTEM ARCHITECTURE

### 1.1 What Is an Addon?

An addon is a **self-contained feature module** that plugs into MakeAI without modifying core files.

**Addons can:**
- Add new routes (web + API)
- Add new admin panel sections
- Add new user dashboard sections
- Add new AI tools to the `ai_tools` table
- Register new queue jobs
- Add new DB tables via migrations
- Override or extend existing services via Laravel's service container
- Add new settings to the admin settings panel
- Hook into core events (new user registered, payment received, etc.)
- Add new menu items (admin sidebar + frontend nav)

**Addons cannot:**
- Modify core files directly (they extend via service container + hooks)
- Override the base authentication system
- Change the core `ai_tools`, `users`, or `settings` table structure

---

### 1.2 Addon Directory Structure

Every addon lives in `/addons/{addon-slug}/`:

```
addons/
  whatsapp-chatbot/
    addon.json                    ← manifest (required)
    AddonServiceProvider.php      ← main entry point (required)
    app/
      Http/
        Controllers/
          Admin/
            WhatsappController.php
          Api/
            WhatsappApiController.php
      Models/
        WhatsappSession.php
        WhatsappMessage.php
      Services/
        WhatsappService.php
        TwilioService.php
      Jobs/
        SendWhatsappMessage.php
      Listeners/
        OnNewChatMessage.php
    database/
      migrations/
        2024_01_01_create_whatsapp_sessions_table.php
        2024_01_01_create_whatsapp_messages_table.php
      seeders/
        WhatsappAddonSeeder.php
    resources/
      js/
        Pages/
          Admin/
            Whatsapp/
              Index.vue
              Settings.vue
          User/
            WhatsappInbox.vue
        Components/
          WhatsappWidget.vue
      views/
        (Blade views if needed — rare)
    routes/
      web.php                     ← web routes for this addon
      api.php                     ← API routes for this addon
      admin.php                   ← admin panel routes
    config/
      whatsapp.php                ← addon-specific config
    lang/
      en/
        whatsapp.json             ← translation strings for this addon
```

---

### 1.3 `addon.json` — Manifest File

```json
{
  "name": "WhatsApp Chatbot",
  "slug": "whatsapp-chatbot",
  "version": "1.0.0",
  "description": "Connect your AI chatbots to WhatsApp via Twilio or 360dialog.",
  "author": "YourName",
  "author_url": "https://yourwebsite.com",
  "min_makeai_version": "1.0.0",
  "requires_license": 1,
  "requires_pro": false,
  "php_min": "8.3",
  "dependencies": [],
  "conflicts": [],
  "homepage_widgets": [],
  "admin_menu": [
    {
      "parent": "AI Tools",
      "label": "WhatsApp",
      "route": "addon.whatsapp.admin.index",
      "icon": "ti-brand-whatsapp",
      "permission": "addon.whatsapp.view"
    }
  ],
  "user_menu": [
    {
      "label": "WhatsApp Inbox",
      "route": "addon.whatsapp.user.inbox",
      "icon": "ti-brand-whatsapp"
    }
  ],
  "settings": [
    {
      "key": "whatsapp_provider",
      "type": "select",
      "label": "Provider",
      "options": ["twilio", "360dialog"],
      "default": "twilio",
      "group": "whatsapp"
    },
    {
      "key": "whatsapp_account_sid",
      "type": "encrypted",
      "label": "Account SID",
      "group": "whatsapp",
      "required": true
    },
    {
      "key": "whatsapp_auth_token",
      "type": "encrypted",
      "label": "Auth Token",
      "group": "whatsapp",
      "required": true
    },
    {
      "key": "whatsapp_phone_number",
      "type": "string",
      "label": "WhatsApp Phone Number",
      "placeholder": "+14155238886",
      "group": "whatsapp"
    },
    {
      "key": "whatsapp_welcome_message",
      "type": "textarea",
      "label": "Welcome Message",
      "default": "Hello! I'm an AI assistant. How can I help you today?",
      "group": "whatsapp"
    },
    {
      "key": "whatsapp_max_context_messages",
      "type": "integer",
      "label": "Max conversation context (messages)",
      "default": 10,
      "group": "whatsapp"
    }
  ],
  "permissions": [
    { "slug": "addon.whatsapp.view",   "name": "View WhatsApp",   "group": "WhatsApp Addon" },
    { "slug": "addon.whatsapp.manage", "name": "Manage WhatsApp", "group": "WhatsApp Addon" }
  ],
  "hooks": [
    "makeai.chatbot.message_received",
    "makeai.user.registered"
  ]
}
```

---

### 1.4 `AddonServiceProvider.php` — Entry Point

```php
<?php

namespace Addons\WhatsappChatbot;

use Illuminate\Support\ServiceProvider;
use App\Services\AddonService;
use App\Facades\AddonHook;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind addon services into container
        $this->app->singleton(WhatsappService::class, function ($app) {
            return new WhatsappService(
                provider: addon_setting('whatsapp-chatbot', 'whatsapp_provider', 'twilio'),
                accountSid: addon_setting('whatsapp-chatbot', 'whatsapp_account_sid'),
                authToken: addon_setting('whatsapp-chatbot', 'whatsapp_auth_token'),
            );
        });

        // Merge addon config
        $this->mergeConfigFrom(__DIR__ . '/config/whatsapp.php', 'addon-whatsapp');
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/admin.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Load translations
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');

        // Register event listeners
        $this->app['events']->listen(
            \App\Events\ChatbotMessageReceived::class,
            \Addons\WhatsappChatbot\Listeners\OnNewChatMessage::class
        );

        // Register admin menu items (from addon.json — AddonService handles this)
        AddonService::registerMenuItems('whatsapp-chatbot');

        // Register hooks (addon can hook into core events)
        AddonHook::on('makeai.user.registered', function ($user) {
            // Send welcome WhatsApp if phone number on file
        });
    }
}
```

---

### 1.5 `AddonService.php` — Core System Service

```php
// app/Services/AddonService.php

class AddonService
{
    // Scans addons/ directory, loads enabled addons from DB
    public static function loadAll(): void

    // Returns addon manifest data
    public static function getManifest(string $slug): array

    // Returns all active addons
    public static function getActive(): Collection

    // Check if addon is active
    public static function isActive(string $slug): bool  // → is_addon_active() helper

    // Register addon's admin menu items into the sidebar
    public static function registerMenuItems(string $slug): void

    // Get all settings defined by all active addons (for admin settings page)
    public static function getAllSettings(): array

    // Run addon migrations (on install/update)
    public static function migrate(string $slug): void

    // Run addon seeder
    public static function seed(string $slug): void
}
```

**`addons` DB table:**
```sql
addons
  id
  slug              varchar(100) UNIQUE
  name              varchar(255)
  version           varchar(20)
  is_active         boolean DEFAULT false
  manifest          json              -- full addon.json contents
  installed_at      timestamp NULL
  activated_at      timestamp NULL
  created_at, updated_at
```

---

### 1.6 Helper Functions for Addons

```php
// Available globally (in app/Helpers/helpers.php)

// Check if an addon is installed and active
is_addon_active(string $slug): bool
// Example: is_addon_active('whatsapp-chatbot')

// Read an addon setting (from settings table, group = 'addon_{slug}')
addon_setting(string $addon, string $key, mixed $default = null): mixed
// Example: addon_setting('whatsapp-chatbot', 'whatsapp_provider', 'twilio')

// Set an addon setting
addon_setting_set(string $addon, string $key, mixed $value, string $type = 'string'): void

// Get addon manifest
addon_manifest(string $slug): array

// Get addon version
addon_version(string $slug): string
```

---

### 1.7 Addon Routes — Naming Convention

All addon routes MUST be prefixed and named with `addon.{slug}.`:

```php
// addons/whatsapp-chatbot/routes/admin.php
Route::prefix('admin/addons/whatsapp')
    ->middleware(['auth:admin', 'admin.permission:addon.whatsapp.view'])
    ->name('addon.whatsapp.admin.')
    ->group(function () {
        Route::get('/', [WhatsappController::class, 'index'])->name('index');
        Route::get('/settings', [WhatsappController::class, 'settings'])->name('settings');
        Route::post('/settings', [WhatsappController::class, 'saveSettings'])->name('settings.save');
        Route::get('/sessions', [WhatsappController::class, 'sessions'])->name('sessions');
    });

// addons/whatsapp-chatbot/routes/api.php
Route::prefix('api/v1/addons/whatsapp')
    ->middleware(['auth:sanctum'])
    ->name('addon.whatsapp.api.')
    ->group(function () {
        Route::get('/sessions', [WhatsappApiController::class, 'sessions'])->name('sessions');
    });

// addons/whatsapp-chatbot/routes/web.php (webhook from Twilio)
Route::post('/webhooks/whatsapp/twilio', [WhatsappController::class, 'twilioWebhook'])
    ->name('addon.whatsapp.webhook.twilio')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

---

### 1.8 Addon Vue Pages

Addon Vue pages live inside `addons/{slug}/resources/js/` but are **compiled into the main build** via Vite config:

```typescript
// vite.config.ts — auto-detects addon Vue files
import { glob } from 'glob'

const addonPages = glob.sync('addons/*/resources/js/Pages/**/*.vue')

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.ts', ...addonPages],
    }),
  ],
})
```

Addon Vue pages use the same layouts and composables as core:
```vue
<!-- addons/whatsapp-chatbot/resources/js/Pages/Admin/Whatsapp/Index.vue -->
<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/composables/useTranslate'
// All core composables available
</script>

<template>
  <AdminLayout title="WhatsApp Chatbot">
    <!-- uses same MakeAI design system -->
  </AdminLayout>
</template>
```

---

### 1.9 Addon Settings in Admin Panel

Addon settings appear in **Admin → Settings → Addons → {Addon Name}** — auto-generated from `addon.json`.

The same field types as AI tool fields are supported:
`string`, `encrypted`, `textarea`, `boolean`, `integer`, `select`, `color`, `url`, `email`

```php
// AddonSettingsController.php (core) auto-renders addon settings
// Admin saves → stored in settings table with group = 'addon_{slug}'
// addon_setting() helper reads from this group (Redis cached)
```

---

### 1.10 Hook System

Core events that addons can listen to:

```php
// app/Facades/AddonHook.php

// Register a hook listener (called in AddonServiceProvider::boot)
AddonHook::on(string $event, callable $callback): void

// Core fires hooks at key points:
AddonHook::fire('makeai.user.registered',        $user);
AddonHook::fire('makeai.user.subscription.started', $user, $plan);
AddonHook::fire('makeai.user.subscription.cancelled', $user);
AddonHook::fire('makeai.payment.completed',       $user, $payment);
AddonHook::fire('makeai.ai.generation.completed', $user, $tool, $output);
AddonHook::fire('makeai.chatbot.message_received',$chatbot, $message, $user);
AddonHook::fire('makeai.blog.post.published',     $post);
AddonHook::fire('makeai.support.ticket.created',  $ticket);
AddonHook::fire('makeai.admin.login',             $admin);
```

---

### 1.11 Packaging an Addon for Sale

Final addon zip structure for distribution:
```
whatsapp-chatbot-v1.0.0.zip
  whatsapp-chatbot/
    addon.json
    AddonServiceProvider.php
    app/...
    database/...
    resources/...
    routes/...
    README.md
    CHANGELOG.md
```

Upload zip in Admin → Appearance → Addons → "Upload Addon"
→ `AddonService::install()` extracts to `/addons/`, runs migrations, seeds, registers.

---

## PART 2 — SUGGESTED ESSENTIAL ADDONS

### Priority 1 — High Demand, High Value

---

#### ADDON 01: WhatsApp Chatbot
**Slug:** `whatsapp-chatbot`
**Price:** $29–$49 | **License required:** Regular

**What it does:**
Connects MakeAI's chatbot builder to WhatsApp. Users configure their AI chatbot in MakeAI and it responds to WhatsApp messages automatically.

**Features:**
- Provider choice: Twilio (reliable, paid) or 360dialog (WhatsApp Business API)
- Webhook receiver for incoming messages
- Conversation memory (stores context in `whatsapp_sessions` table)
- Assigns AI chatbot persona per WhatsApp number
- Admin CRM inbox: view all WhatsApp conversations
- Auto-reply, delay, and typing indicator simulation
- Media support: receive images → analyze with vision model
- Opt-out/opt-in handling (`STOP` / `START` keywords)
- Rate limiting per WhatsApp number

**New tables:** `whatsapp_sessions`, `whatsapp_messages`, `whatsapp_contacts`

**Hook used:** `makeai.chatbot.message_received`

---

#### ADDON 02: Telegram Bot
**Slug:** `telegram-bot`
**Price:** $19–$29 | **License required:** Regular

**What it does:**
Connects AI chatbots to Telegram via Bot API.

**Features:**
- Multiple bots (one per chatbot persona)
- Inline keyboard buttons for quick replies
- Command handlers (`/start`, `/help`, `/reset`)
- Group chat support (responds when @mentioned)
- Broadcast to subscribers
- File/photo/voice message handling
- Webhook or polling mode

**New tables:** `telegram_bots`, `telegram_sessions`, `telegram_subscribers`

---

#### ADDON 03: WordPress Publisher
**Slug:** `wordpress-publisher`
**Price:** $19–$29 | **License required:** Regular

**What it does:**
Publishes AI-generated content directly from MakeAI to one or multiple WordPress sites.

**Features:**
- Connect multiple WP sites (REST API + Application Password)
- Publish from AI Writer, Blog Article tool, or Document Library
- Set: post title, content, category, tags, featured image, status (draft/publish/schedule)
- SEO meta push (Yoast SEO + RankMath compatible via REST)
- Auto-format markdown → WordPress blocks (Gutenberg)
- Bulk publish from document library
- Publish history log per user
- Admin: manage WP site connections globally

**New tables:** `wordpress_sites`, `wordpress_publish_logs`

---

#### ADDON 04: AI Image Editor
**Slug:** `ai-image-editor`
**Price:** $39–$59 | **License required:** Regular

**What it does:**
Post-processing tools for AI-generated images. Extends the Image Generator tool.

**Features:**
- **Inpainting**: erase region → regenerate (Stability AI / Replicate)
- **Outpainting**: extend canvas in any direction
- **Background removal** (Remove.bg / Clipdrop)
- **Upscaler** (4x resolution via Real-ESRGAN on Replicate)
- **Style transfer**: apply art style to photo
- **Object removal**: select object → fill with background
- **Color correction**: brightness/contrast/saturation sliders
- **Text overlay**: add text with font/color/position controls
- Canvas-based editor UI (using Fabric.js or Konva.js)
- Save edited image back to image library

**New tables:** `image_edits` (tracks edit history per image)

---

#### ADDON 05: AI Video Creator
**Slug:** `ai-video-creator`
**Price:** $49–$79 | **License required:** Extended (Pro)

**What it does:**
Full video creation workflow inside MakeAI.

**Features:**
- Text-to-video (Kling AI, Pika, Runway ML)
- Image-to-video animation
- AI avatar video (HeyGen, D-ID) — type script → avatar speaks it
- Video slideshow: images + AI voiceover + background music
- Subtitle generation (Whisper → auto-transcribe)
- Video trimmer (basic: trim start/end)
- Download MP4 / share link
- Video library with folders
- Progress tracking for long generations (polling)

**New tables:** `video_projects`, `video_renders`

---

#### ADDON 06: AI Email Inbox (Smart Reply)
**Slug:** `ai-email-inbox`
**Price:** $29–$49 | **License required:** Regular

**What it does:**
Connects Gmail or Outlook. AI reads incoming emails and drafts smart replies.

**Features:**
- OAuth2 connection (Gmail API / Microsoft Graph)
- Inbox view inside MakeAI dashboard
- AI-suggested reply (one-click generate based on email content)
- Tone selector for reply (professional, friendly, etc.)
- Send directly from MakeAI or copy to clipboard
- Email categories (auto-label: inquiry, complaint, feedback, spam)
- Auto-reply rules: if email matches trigger → AI auto-responds
- Unread count badge in user nav

**New tables:** `email_accounts`, `email_messages`, `email_drafts`

---

#### ADDON 07: Bulk Content Generator
**Slug:** `bulk-generator`
**Price:** $29–$39 | **License required:** Regular

**What it does:**
Generate content in bulk — upload CSV with inputs, download CSV with outputs.

**Features:**
- Upload CSV with column headers matching tool field names
- Select which AI tool to run on each row
- Background processing (queue job per row)
- Progress bar: "Generated 47/200 items"
- Download results as CSV, XLSX, or ZIP of text files
- Error rows marked + retry individual rows
- Cost estimate before starting (credits required)
- Credit deduction per row (with pre-check — block if insufficient)
- Admin: set max rows per bulk job (settings)

**New tables:** `bulk_jobs`, `bulk_job_rows`

---

#### ADDON 08: Social Media Scheduler Pro
**Slug:** `social-scheduler`
**Price:** $39–$59 | **License required:** Regular

**What it does:**
Full social media scheduling suite. Extends the basic social features in core.

**Features:**
- Connect: Instagram, Facebook, X/Twitter, LinkedIn, TikTok, Pinterest, YouTube
- Visual calendar (drag-drop to reschedule)
- Multi-platform post: write once → publish to all selected platforms
- AI caption generator per platform (tone adapted per platform)
- Image/video attachment per post
- First-comment scheduler (Instagram)
- Carousel post builder (LinkedIn/Instagram)
- Analytics dashboard: impressions, clicks, reach per post
- Best-time-to-post AI suggestion (based on account analytics)
- RSS feed auto-post: new RSS item → auto-draft post
- Approval workflow: team member drafts → admin approves before publish

**New tables:** `social_accounts_ext`, `social_scheduled_posts`, `social_post_analytics`

---

#### ADDON 09: AI Knowledge Base (Public)
**Slug:** `public-knowledge-base`
**Price:** $29–$39 | **License required:** Regular

**What it does:**
Creates a public-facing help center powered by AI. Users ask questions → AI answers from your KB.

**Features:**
- Admin creates KB articles (rich text editor)
- Articles organized by category
- Public URL: `/help` or `/kb`
- AI search: user types question → semantic search finds relevant articles → AI generates answer with citations
- Article voting (helpful / not helpful)
- Related articles widget
- SEO per article (meta, schema.org `Article`)
- Embeddable widget: `<script>` snippet for external websites
- Analytics: most searched terms, unanswered questions

**New tables:** `kb_articles`, `kb_categories`, `kb_searches`, `kb_article_votes`

---

#### ADDON 10: Affiliate Pro (Advanced)
**Slug:** `affiliate-pro`
**Price:** $29–$39 | **License required:** Extended (Pro)

**What it does:**
Extends the built-in referral system with advanced affiliate management.

**Features:**
- Multi-tier commissions (2-level: referrer + their referrer)
- Custom commission rates per affiliate (override global %)
- Coupon-linked commissions (affiliate shares coupon → earns on uses)
- PayPal Payouts API (auto-pay approved commissions)
- Affiliate landing page builder per affiliate (custom URL)
- Click tracking pixel
- Sub-affiliate management
- Tax form collection (W9/W8 for US compliance)
- Affiliate leaderboard (public or admin-only)
- Fraud detection: flag suspicious click patterns

**New tables:** `affiliate_tiers`, `affiliate_coupon_links`, `affiliate_payout_batches`

---

### Priority 2 — Useful, Good Market

---

#### ADDON 11: Slack Bot
**Slug:** `slack-bot`
**Price:** $19–$29 | **License required:** Regular

Users type `/makeai generate blog-article topic="Laravel tips"` in any Slack channel.
Bot responds with generated content. Also: `/makeai chat`, `/makeai image`.
Admin configures which tools are available via Slack.

---

#### ADDON 12: Discord Bot
**Slug:** `discord-bot`
**Price:** $19–$29 | **License required:** Regular

Same concept as Slack but for Discord. Slash commands in Discord servers.
`/generate`, `/chat`, `/image`. Supports role-based access per command.

---

#### ADDON 13: Notion Integration
**Slug:** `notion-integration`
**Price:** $19–$29 | **License required:** Regular

- Export any MakeAI document → Notion page (one-click)
- Import Notion pages → MakeAI document library
- Use Notion database as knowledge base for RAG chatbots
- Sync: MakeAI content calendar ↔ Notion database

---

#### ADDON 14: Zapier / Make.com Integration
**Slug:** `automation-webhooks`
**Price:** $19–$29 | **License required:** Regular

- Outbound webhooks on core events (generation complete, subscription started, etc.)
- Inbound trigger: receive webhook → trigger AI generation automatically
- Pre-built Zapier templates listed in docs
- Event log (last 100 webhook deliveries with status)

---

#### ADDON 15: Multi-Tenant / Agency
**Slug:** `multi-tenant`
**Price:** $49–$99 | **License required:** Extended (Pro)

- Admin creates sub-workspaces for clients
- Each workspace: custom domain, custom logo, custom colors
- Workspace admin role (client manages their own users)
- Separate credit pools per workspace
- Admin sees all workspaces with usage dashboard
- Billing per workspace (optional)

**New tables:** `workspaces`, `workspace_users`, `workspace_settings`

---

#### ADDON 16: API Key Marketplace
**Slug:** `api-marketplace`
**Price:** $19–$29 | **License required:** Regular

Users who want to use their own API keys (OpenAI, Anthropic, etc.) can add them in one place.
- Validated on save (test API call)
- Used for their generations (bypasses admin keys, no credit deduction)
- Admin can enable/disable this feature globally
- Supports all providers in the integrations list

---

#### ADDON 17: Advanced Analytics
**Slug:** `advanced-analytics`
**Price:** $29–$49 | **License required:** Regular

Extends the core dashboard with deeper insights:
- User cohort analysis (retention by signup week)
- AI tool funnel (which tools users try → which they keep using)
- Revenue forecasting (MRR projection based on churn/growth)
- Geographic usage map (Mapbox heatmap)
- Export to Google Sheets / CSV / PDF report
- Scheduled email reports (weekly digest to admin)

---

#### ADDON 18: Custom Domain per User
**Slug:** `custom-domains`
**Price:** $29–$39 | **License required:** Extended (Pro)

Pro users can map their own domain to their MakeAI-generated content:
- Chatbot embeds served from `chat.theirdomain.com`
- Public KB served from `help.theirdomain.com`
- Custom SSL provisioning (Let's Encrypt via Caddy/nginx API)
- DNS verification flow (CNAME check)

---

## PART 3 — ADDON DEVELOPMENT RULES

### 3.1 DO — Always Follow These

**DO namespace all addon PHP classes under `Addons\{PascalSlug}\`:**
```php
namespace Addons\WhatsappChatbot;
namespace Addons\WhatsappChatbot\Services;
namespace Addons\WhatsappChatbot\Models;
```

**DO prefix all addon DB table names with the addon slug:**
```php
// ✅ CORRECT
Schema::create('whatsapp_sessions', ...);
Schema::create('whatsapp_messages', ...);

// ❌ WRONG — conflicts with core or other addons
Schema::create('sessions', ...);
Schema::create('messages', ...);
```

**DO prefix all addon route names with `addon.{slug}.`:**
```php
// ✅ CORRECT
Route::get('/...')->name('addon.whatsapp.admin.index');

// ❌ WRONG — conflicts with core routes
Route::get('/...')->name('whatsapp.index');
```

**DO use `addon_setting()` helper for all addon config — never `config()` or `env()`:**
```php
// ✅ CORRECT
$token = addon_setting('whatsapp-chatbot', 'whatsapp_auth_token');

// ❌ WRONG
$token = config('addon-whatsapp.auth_token');
$token = env('WHATSAPP_AUTH_TOKEN');
```

**DO check `is_addon_active('your-slug')` before using any addon functionality in core:**
```php
// In core code that references addon features:
if (is_addon_active('whatsapp-chatbot')) {
    // use WhatsappService
}
```

**DO use the hook system instead of modifying core files:**
```php
// ✅ CORRECT — hooks into core event
AddonHook::on('makeai.user.registered', function($user) {
    // send WhatsApp welcome
});

// ❌ WRONG — modifies core RegisterController
// Never edit files in app/, resources/, routes/ outside addons/
```

**DO run migrations only for your addon's tables:**
```php
// loadMigrationsFrom only your addon's migrations directory
$this->loadMigrationsFrom(__DIR__ . '/database/migrations');
// Never call Artisan::call('migrate') for all migrations from an addon
```

**DO version your addon in `addon.json` using semver (1.0.0, 1.1.0, 2.0.0).**

**DO include `README.md` with:**
- Installation requirements
- Configuration steps
- Usage guide
- Troubleshooting

---

### 3.2 DON'T — Never Do These

❌ **NEVER modify any file outside your addon's directory:**
```
// These are ALL off-limits:
app/             resources/js/    routes/         config/
database/        bootstrap/       public/          .env
```

❌ **NEVER use `DB::statement()` to alter core tables:**
```php
// ❌ WRONG — breaks core functionality
DB::statement('ALTER TABLE users ADD COLUMN whatsapp_number VARCHAR(20)');

// ✅ CORRECT — use a polymorphic approach or addon-specific table
// whatsapp_user_profiles: id, user_id FK, whatsapp_number
```

❌ **NEVER override core service bindings without a fallback:**
```php
// ❌ WRONG — breaks core if addon is deactivated
$this->app->bind(AiService::class, MyCustomAiService::class);

// ✅ CORRECT — extend, don't replace
$this->app->extend(AiService::class, function($service, $app) {
    return new ExtendedAiService($service);
});
```

❌ **NEVER hardcode the MakeAI version or domain:**
```php
// ❌ WRONG
$url = 'https://makeai.com/api/v1/...';

// ✅ CORRECT
$url = route('addon.whatsapp.webhook.twilio');
$url = url('/webhooks/whatsapp/twilio');
```

❌ **NEVER register global middleware from an addon without scoping it:**
```php
// ❌ WRONG — applies to ALL routes including core
$this->app['router']->pushMiddlewareToGroup('web', MyMiddleware::class);

// ✅ CORRECT — only your addon routes
Route::middleware([MyMiddleware::class])->group(...);
```

❌ **NEVER store secrets in `addon.json` or committed files.**

❌ **NEVER make direct HTTP calls to external APIs in controllers — always use a Service class.**

---

### 3.3 Vue Component Rules for Addons

**DO import from core composables and components:**
```typescript
// ✅ All core composables available via @/ alias
import { useTranslate } from '@/composables/useTranslate'
import { useToastr }    from '@/composables/useToastr'
import { useStream }    from '@/composables/useStream'
import BaseCard         from '@/Components/UI/BaseCard.vue'
import BaseButton       from '@/Components/UI/BaseButton.vue'
```

**DO use the same design system variables (never addon-specific colors):**
```css
/* ✅ CORRECT — uses MakeAI design tokens */
background: var(--color-primary-500);
border-radius: var(--radius-lg);

/* ❌ WRONG — custom color breaks theme consistency */
background: #25d366; /* WhatsApp green */
```

**DO use the existing admin layout:**
```vue
<template>
  <AdminLayout :title="$t('WhatsApp Chatbot')">
    <!-- addon content here -->
  </AdminLayout>
</template>
```

---

## PART 4 — ADDON INSTALLER (ADMIN)

**Admin → Appearance → Addons:**

```
┌─────────────────────────────────────────────────────────────┐
│ Installed Addons                          [Upload Addon .zip]│
├───────────────┬────────────┬──────────────┬─────────────────┤
│ WhatsApp Bot  │ v1.0.0     │ ● Active     │ [Settings] [×]  │
│ Telegram Bot  │ v1.2.0     │ ● Active     │ [Settings] [×]  │
│ Bulk Generator│ v1.0.0     │ ○ Inactive   │ [Activate] [×]  │
└───────────────┴────────────┴──────────────┴─────────────────┘
```

**Upload flow:**
1. Admin uploads `.zip`
2. System validates: checks `addon.json` exists + required fields
3. Checks `min_makeai_version` compatibility
4. Checks `requires_license` against current license
5. Extracts to `/addons/{slug}/`
6. Runs `php artisan migrate --path=addons/{slug}/database/migrations`
7. Runs seeder if exists
8. Inserts row into `addons` table with `is_active = false`
9. Admin clicks "Activate" → `is_active = true` → ServiceProvider registered → cache cleared

**Deactivate:**
- Sets `is_active = false`
- Routes no longer registered
- Admin menu items removed
- Does NOT drop tables (data preserved)

**Delete:**
- Only if addon is inactive
- Drops addon tables (with confirmation modal: "This will delete all WhatsApp data")
- Removes from `addons` table
- Deletes `/addons/{slug}/` directory

---

## PART 5 — ADDON CHECKLIST

### General
- [ ] `addon.json` has all required fields: name, slug, version, min_makeai_version
- [ ] PHP namespace: `Addons\{PascalSlug}\`
- [ ] All DB tables prefixed with addon slug
- [ ] All routes named `addon.{slug}.*`
- [ ] All settings use `addon_setting()` — no `config()` or `env()` calls
- [ ] No files modified outside `/addons/{slug}/`
- [ ] No core DB tables altered via migrations
- [ ] `README.md` included with setup and usage instructions
- [ ] `CHANGELOG.md` with version history

### Installation
- [ ] Install via zip upload works cleanly
- [ ] Migrations run without errors on fresh install
- [ ] Seeder populates default data correctly
- [ ] Activate/deactivate toggles cleanly without errors
- [ ] Deactivate does NOT drop tables
- [ ] Delete (inactive only) drops tables with confirmation
- [ ] `min_makeai_version` check prevents install on incompatible versions

### Integration
- [ ] Admin menu items appear after activation
- [ ] Admin menu items disappear after deactivation
- [ ] Addon settings form renders from `addon.json` definition
- [ ] Encrypted settings stored encrypted, never exposed in API responses
- [ ] `is_addon_active('slug')` returns correct value cached in Redis
- [ ] Hooks registered in `boot()` fire at correct core events

### Vue / Frontend
- [ ] Addon pages use `AdminLayout` or `AppLayout`
- [ ] All text uses `$t()` — no hardcoded English
- [ ] Design tokens used — no custom colors that clash with design system
- [ ] Dark mode tested — all addon components render correctly

### Security
- [ ] All admin routes protected by `auth:admin` + permission middleware
- [ ] All user routes protected by `auth` + appropriate middleware
- [ ] Webhook routes exclude CSRF but validate signature (e.g. Twilio `X-Twilio-Signature`)
- [ ] All external API calls in Service classes — never in controllers
- [ ] No secrets in committed files or `addon.json`

---

*MakeAI Addon Development Guide v1.0*
*Reference: AI_SaaS_Master_Prompt.md Part 04 (Foundation Layer — Addon System) for core integration details*
