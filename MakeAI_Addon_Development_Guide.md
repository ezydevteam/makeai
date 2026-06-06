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

---

## PART 6 — NEW ADDON SPECS (ADDONS 19–29)

> **AI Engine:** All addons use `laravel/ai` (Laravel AI SDK) exclusively via `AiService`.
> Controllers **never** call the SDK directly — always go through `AiService`.
> All streaming uses POST + `ReadableStream` + `X-Accel-Buffering: no` (never EventSource).
> All requests go through `TokenGuard::before()` / `TokenGuard::after()` for credit management.

### Laravel AI SDK — Addon Usage Pattern

Every AI call in an addon follows this contract:

```php
use App\Services\AI\AiService;
use App\Services\AI\DTO\CompletionRequest;

// Inject via constructor
public function __construct(private AiService $ai) {}

// Non-streaming (synchronous) — returns CompletionResponse
$response = $this->ai->complete(new CompletionRequest(
    messages: [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    model:       settings('ai_default_model'),
    provider:    settings('ai_default_provider'),
    max_tokens:  1000,
    temperature: 0.7,
));
$text = $response->text;
$tokens = $response->usage; // { input_tokens, output_tokens }

// Streaming — returns Generator, pipe to StreamedResponse
$stream = $this->ai->stream(new CompletionRequest(
    messages: [...],
    model:    settings('ai_default_model'),
));
return response()->stream(function () use ($stream) {
    foreach ($stream as $chunk) {
        echo "data: " . json_encode(['text' => $chunk->text]) . "\n\n";
        ob_flush(); flush();
    }
}, 200, [
    'Content-Type'      => 'text/event-stream',
    'Cache-Control'     => 'no-cache',
    'X-Accel-Buffering' => 'no',
]);

// Structured JSON output — prompt model to return JSON, then parse
$response = $this->ai->complete(new CompletionRequest(
    messages: [
        ['role' => 'system', 'content' => 'Return ONLY valid JSON. No markdown. No explanation.'],
        ['role' => 'user',   'content' => $userPrompt],
    ],
));
$data = json_decode($response->text, true);

// Embeddings (for RAG addons)
$vector = $this->ai->embedText($text, settings('ai_embedding_provider'));

// Image generation
$imageUrl = $this->ai->generateImage($prompt, settings('ai_image_provider'));

// Audio / TTS (for Voice Studio addon — use provider APIs directly if not in SDK)
$audioPath = $this->ai->generateAudio($text, $voice, $provider);
```

**Provider selection in addons:**
- Default: use `settings('ai_default_provider')` and `settings('ai_default_model')`
- If addon has its own provider setting (e.g. Voice Studio uses ElevenLabs): call provider APIs directly via addon's own Service class — only text/chat generation goes through `AiService`
- Never hardcode provider names or model names in addon code

**TokenGuard in addons:**
- All `AiService::complete()` and `AiService::stream()` calls automatically run TokenGuard
- Addons do NOT need to manually call `TokenGuard::before()` / `TokenGuard::after()` — `AiService` handles it
- If an addon calls an external AI API directly (e.g. ElevenLabs TTS), it must manually deduct credits using `deduct_credits($user, $amount)` helper and log to `ai_usage_logs`

---

### Priority 1 — High Demand, High Value

---

#### ADDON 19: AI Brand Voice Trainer
**Slug:** `brand-voice`
**Price:** $29–$49 | **License required:** Regular

**What it does:**
Users upload sample content (blog posts, emails, documents). The AI analyzes writing style and builds a "Brand Voice Profile" — tone, vocabulary, sentence structure, formality level. This profile is automatically injected as a system-level instruction into every AI tool prompt for that user, making all generated content sound on-brand.

**Features:**
- Upload up to 10 sample documents (TXT, DOCX, PDF) or paste text
- AI analyzes samples → generates Brand Voice Profile card (tone adjectives, vocabulary level, avg sentence length, style notes)
- Profile stored per user (one active profile at a time; multiple saved)
- Profile toggle in user dashboard sidebar — "Brand Voice: ON / OFF"
- When ON: injected as system instruction prefix in all `AiService` calls for that user
- Admin can create org-wide brand profiles and assign to user groups
- Profile preview: paste any text → AI rewrites it in your brand voice
- Version history: keep old profiles, compare side-by-side
- Workspace support: if `multi-tenant` addon active, profiles are per-workspace

**Step-by-step build prompt for DeepSeek:**

```
ADDON: brand-voice | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATIONS
Create migration: `brand_voice_profiles`
Columns: id (ulid), user_id (FK users), name (string), tone_adjectives (json), vocabulary_level
(enum: casual/neutral/formal/technical), avg_sentence_length (tinyint), style_notes (text),
raw_analysis (longtext), is_active (boolean default false), created_at, updated_at
Create migration: `brand_voice_samples`
Columns: id (ulid), profile_id (FK brand_voice_profiles), filename (string nullable),
content (longtext), word_count (int), created_at

STEP 2 — MODELS
BrandVoiceProfile: belongs to User; has many BrandVoiceSample; casts tone_adjectives as array
BrandVoiceService:
  - analyzeSamples(array $texts, User $user): BrandVoiceProfile
    → concatenate all sample texts
    → $this->ai->complete(CompletionRequest) — build messages with system prompt instructing analysis output as JSON:
      { tone_adjectives: string[], vocabulary_level: string, avg_sentence_length: int, style_notes: string }
    → create BrandVoiceProfile record
    → return profile
  - getActiveProfile(User $user): ?BrandVoiceProfile
    → Redis cache key: "bv_active_{user_id}"
    → query brand_voice_profiles where user_id and is_active = true
  - buildSystemInjection(BrandVoiceProfile $profile): string
    → returns: "Write in the following brand voice: tone is {adjectives}, vocabulary is {level},
      keep sentences {avg_sentence_length} words on average. {style_notes}"
  - previewRewrite(string $text, BrandVoiceProfile $profile): string
    → $this->ai->complete() — system: = buildSystemInjection(), user = "Rewrite this text in my brand voice: {text}"

STEP 3 — CORE HOOK (inject into all AI generations)
In AddonServiceProvider::boot():
AddonHook::on('makeai.ai.generation.before', function($user, &$systemPrompt) {
    $profile = app(BrandVoiceService::class)->getActiveProfile($user);
    if ($profile) {
        $systemPrompt = app(BrandVoiceService::class)->buildSystemInjection($profile) . "\n\n" . $systemPrompt;
    }
});
NOTE: Core must fire 'makeai.ai.generation.before' hook passing user + system prompt by
reference before every AiService call.

STEP 4 — CONTROLLERS
BrandVoiceController (user-facing):
  index(): return Inertia 'BrandVoice/Index' with user's profiles
  store(UploadRequest $request): parse uploaded files/text, dispatch AnalyzeBrandVoiceJob
  activate(BrandVoiceProfile $profile): set profile is_active=true, all others false, clear Redis cache
  deactivate(): set all is_active=false for user, clear cache
  preview(Request $request): call BrandVoiceService::previewRewrite(), return streamed response
  destroy(BrandVoiceProfile $profile): delete profile and its samples
AdminBrandVoiceController:
  index(): list all users with active profiles, global profile list
  createOrgProfile(): create a profile assignable to all users
  assignToGroup(Request $request): assign org profile to a plan or user group

STEP 5 — JOBS
AnalyzeBrandVoiceJob (queue: 'ai'):
  - accepts: array of file paths + user_id + profile_name
  - extracts text from each file (PDF via Smalot, DOCX via PhpWord, TXT directly)
  - calls BrandVoiceService::analyzeSamples()
  - broadcasts 'BrandVoiceReady' event via Reverb to user's private channel
  - notifies user: "Your brand voice profile '{name}' is ready"

STEP 6 — VUE PAGES
resources/js/Pages/BrandVoice/Index.vue:
  - Header: "Brand Voice" + "Create Profile" button
  - Active profile card (highlighted, tone adjectives as pills, vocabulary badge, style notes)
  - Toggle switch "Apply to all generations"
  - Profile list: name, created date, "Activate" / "Preview" / "Delete" actions
  - Empty state: upload prompt with drag-drop zone
resources/js/Pages/BrandVoice/Create.vue:
  - Step 1: Name your profile
  - Step 2: Upload samples (drag-drop, accepts .txt .docx .pdf, max 5 files) OR paste text
  - Step 3: Review & Analyze button → loading state → redirect to Index when ready
resources/js/Pages/BrandVoice/Preview.vue:
  - Left: textarea "Paste any text here"
  - Right: AI-rewritten output (streaming)
  - Profile selector dropdown
Admin page: resources/js/Pages/Admin/BrandVoice/Index.vue
  - Table: user, active profile name, last updated, sample count
  - "Create Org Profile" button

STEP 7 — ROUTES (routes/web.php)
Route::middleware(['auth', 'addon.active:brand-voice'])->prefix('brand-voice')->group(function () {
    Route::get('/', [BrandVoiceController::class, 'index'])->name('addon.brand-voice.index');
    Route::post('/', [BrandVoiceController::class, 'store'])->name('addon.brand-voice.store');
    Route::post('/{profile}/activate', ...)->name('addon.brand-voice.activate');
    Route::post('/deactivate', ...)->name('addon.brand-voice.deactivate');
    Route::post('/preview', ...)->name('addon.brand-voice.preview');
    Route::delete('/{profile}', ...)->name('addon.brand-voice.destroy');
});
Route::middleware(['auth:admin'])->prefix('admin/brand-voice')->group(function () { ... });

STEP 8 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "max_samples_per_profile", "type": "integer", "default": 10, "label": "Max sample files per profile" },
    { "key": "max_sample_words", "type": "integer", "default": 50000, "label": "Max total words per analysis" },
    { "key": "allow_org_profiles", "type": "boolean", "default": true, "label": "Allow admin org-wide profiles" }
  ]
}

STEP 9 — SIDEBAR MENU ITEM
AddonHook::on('makeai.sidebar.user.items', function(&$items) {
    $items[] = ['label' => 'Brand Voice', 'route' => 'addon.brand-voice.index', 'icon' => 'ti-microphone'];
});

STEP 10 — CHECKLIST
- [ ] Profile activation clears Redis cache key "bv_active_{user_id}"
- [ ] File parsing handles corrupt files gracefully (catch exceptions, mark sample as failed)
- [ ] Preview endpoint streams response with X-Accel-Buffering: no header
- [ ] Deactivating addon does NOT delete profiles
```

**New tables:** `brand_voice_profiles`, `brand_voice_samples`
**Hook used:** `makeai.ai.generation.before` (system prompt injection)

---

#### ADDON 20: AI Presentation Builder
**Slug:** `ai-presentation`
**Price:** $39–$59 | **License required:** Regular

**What it does:**
Users input a topic or paste an outline. AI generates a complete slide deck (title, bullets, speaker notes per slide). Downloadable as `.pptx` via PhpPresentation or viewable in an interactive in-browser slide viewer. Optionally fetches Unsplash images per slide.

**Features:**
- Input: topic + audience + tone + number of slides (5–30)
- OR: paste outline → AI structures it into slides
- AI generates: slide title, 3–5 bullet points, speaker notes, image query per slide
- Unsplash image fetch per slide (admin adds Unsplash API key)
- In-browser slide viewer (keyboard nav, fullscreen, slide counter)
- Download as `.pptx` via PhpPresentation library
- Presentation library with folders, rename, duplicate, delete
- 5 built-in color themes (light, dark, corporate, creative, minimal)
- Inline slide editing: click title/bullets → edit in place (Vue contenteditable)
- Re-generate individual slide without rebuilding the whole deck
- Share link: public read-only presentation URL

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-presentation | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATIONS
Create migration: `presentation_decks`
Columns: id (ulid), user_id (FK), title (string), topic (string), theme (string default 'light'),
slide_count (tinyint), status (enum: pending/generating/ready/failed),
share_token (string nullable unique), created_at, updated_at
Create migration: `presentation_slides`
Columns: id (ulid), deck_id (FK presentation_decks), slide_order (tinyint),
slide_title (string), bullets (json), speaker_notes (text nullable),
image_url (string nullable), image_query (string nullable), created_at, updated_at

STEP 2 — SERVICE: PresentationService
generateDeck(array $input, User $user): PresentationDeck
  → Create deck record with status=pending → Dispatch GeneratePresentationJob → Return deck

generateDeckContent(PresentationDeck $deck, array $input): void
  → $this->ai->stream(new CompletionRequest()) — prompt:
    System: "You are a presentation expert. Return ONLY valid JSON."
    User: "Create a {slide_count}-slide presentation on '{topic}' for {audience}. Tone: {tone}.
    Return JSON: { slides: [ { title, bullets: string[], speaker_notes, image_query } ] }"
  → Parse JSON → Create PresentationSlide records
  → If Unsplash key set: fetch image per slide via Unsplash API
  → Set deck status=ready, broadcast PresentationReady event

exportToPptx(PresentationDeck $deck): string
  → Use PhpPresentation (composer require phpoffice/phppresentation)
  → For each slide: title shape + bullets text shape + notes + optional image
  → Apply theme colors → save to storage/app/temp/presentation_{id}.pptx

STEP 3 — CONTROLLERS
PresentationController:
  index(): Inertia 'Presentation/Index' with user decks paginated
  store(Request): validate → PresentationService::generateDeck() → redirect to show
  show(Deck): Inertia 'Presentation/Show' with deck + slides
  export(Deck): stream pptx download
  regenerateSlide(Slide): re-run AI for single slide, return JSON
  updateSlide(Slide, Request): update title/bullets/notes
  share(Deck): generate share_token, return URL
  destroy(Deck): delete deck + slides + temp file
PublicPresentationController:
  show(string $token): Inertia 'Presentation/Public' (no auth required)

STEP 4 — JOBS
GeneratePresentationJob (queue: 'ai'):
  → calls PresentationService::generateDeckContent()
  → on fail: status=failed, notify user
  → on success: broadcast 'PresentationReady' on user private Reverb channel

STEP 5 — VUE PAGES
resources/js/Pages/Presentation/Create.vue:
  - Topic input, Audience dropdown, Tone dropdown, Slide count slider (5–30), Theme picker (5 swatches)
  - OR toggle: "Paste Outline" mode textarea
  - Generate button → animated loading state while polling status via Reverb

resources/js/Pages/Presentation/Show.vue:
  - Left: slide thumbnails (reorderable via vue-draggable-plus)
  - Main: current slide preview (title + bullets + image)
  - Right: speaker notes editor (Tiptap)
  - Toolbar: Download PPTX, Share, Theme switcher, "Regenerate Slide"
  - Click any bullet → contenteditable inline edit

resources/js/Pages/Presentation/Index.vue:
  - Grid of deck cards: first slide title, theme color, slide count, date
  - Actions: Open, Download, Share, Delete

resources/js/Pages/Presentation/Public.vue:
  - Full-screen viewer, arrow key nav, slide counter, fullscreen button, no edit controls

STEP 6 — ROUTES
Route::middleware(['auth', 'addon.active:ai-presentation'])->prefix('presentations')->group(...);
Route::get('/p/{token}', ...)->name('addon.presentation.public');

STEP 7 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "unsplash_access_key", "type": "encrypted", "label": "Unsplash API Access Key" },
    { "key": "max_slides", "type": "integer", "default": 30, "label": "Max slides per deck" },
    { "key": "credits_per_deck", "type": "integer", "default": 5, "label": "Credits per deck generation" }
  ]
}

STEP 8 — CHECKLIST
- [ ] JSON parse errors from AiService: retry once with stricter prompt, then fail gracefully
- [ ] pptx temp files deleted after download (scheduled cleanup)
- [ ] Credits deducted before generation, refunded on job failure
- [ ] Slide reorder persists via PATCH updating slide_order values
```

**New tables:** `presentation_decks`, `presentation_slides`

---

#### ADDON 21: AI Form Builder & Lead Capture
**Slug:** `ai-forms`
**Price:** $29–$39 | **License required:** Regular

**What it does:**
Drag-and-drop form builder where each text field can optionally have an AI "Smart Suggest" button. Admin views and exports all submissions. Forms embed on any external website via `<script>` snippet.

**Features:**
- Drag-drop fields via `vue-draggable-plus`: Text, Textarea, Email, Phone, Select, Checkbox, Radio, File, Date, Divider, Heading
- Per-field AI Smart Suggest: "Improve with AI" button rewrites user's rough input professionally
- Form settings: title, description, submit label, success message, redirect URL
- Honeypot spam protection + optional reCAPTCHA v3
- Email notifications: admin + optional submitter confirmation
- Submissions inbox: table, search, read/unread, bulk delete, CSV export
- Embed via `<script src>` snippet — inline or floating popup mode
- Form analytics: views, submission count, conversion rate
- Conditional logic: show/hide field based on another field's value

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-forms | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATIONS
Create migration: `form_builder_forms`
Columns: id (ulid), user_id (FK), title (string), slug (string unique), description (text nullable),
submit_label (string default 'Submit'), success_message (text), redirect_url (string nullable),
notify_admin (boolean default true), notify_submitter_field (string nullable),
recaptcha_enabled (boolean default false), is_active (boolean default true),
view_count (int default 0), created_at, updated_at

Create migration: `form_builder_fields`
Columns: id (ulid), form_id (FK), field_key (string), field_type (enum: text/textarea/email/phone/
select/checkbox/radio/file/date/divider/heading), label (string), placeholder (string nullable),
required (boolean), ai_suggest (boolean default false), ai_suggest_tone (string default 'professional'),
options (json nullable), conditional_logic (json nullable), field_order (tinyint), created_at, updated_at

Create migration: `form_builder_submissions`
Columns: id (ulid), form_id (FK), data (json), ip_address (string), user_agent (string),
is_read (boolean default false), created_at, updated_at

STEP 2 — SERVICE: FormBuilderService
aiSmartSuggest(string $rawText, string $fieldLabel, string $tone): string
  → $this->ai->complete(new CompletionRequest(messages: [...], ...)):
    prompt: "Rewrite for a form field '{fieldLabel}' in {tone} tone. Return ONLY rewritten text."
generateEmbedScript(Form $form): string → JS snippet for external embed
processSubmission(Form $form, array $data, Request $request): FormSubmission
  → Validate honeypot, required fields → create record → dispatch notification job

STEP 3 — CONTROLLERS
FormController (user): index, create, edit, store, update, destroy
FormSubmissionController: index, markRead, export CSV, destroy
PublicFormController (no auth): show (render form), submit (POST), track (increment views)
FormAiController: suggest (stream AI rewrite)
EmbedController: script (return JS file dynamically)

STEP 4 — VUE PAGES
resources/js/Pages/Forms/Builder.vue:
  - Left: field palette → drag to center canvas
  - Center: form canvas (vue-draggable-plus reorder), each field shows label + edit/delete
  - Right: selected field settings panel (label, placeholder, required, ai_suggest toggle, options)
  - Top: title input, Settings tab, Embed tab, Preview button, Save button
  - Conditional logic: "Show when [field] [is] [value]" per field

resources/js/Pages/Forms/Public.vue:
  - Renders fields from props; text fields with ai_suggest show "✨ Improve with AI" button
  - Honeypot: hidden div (CSS display:none), submit POST → success message or redirect

resources/js/Pages/Forms/Submissions.vue:
  - Table: date, email (if captured), read/unread badge, View action
  - Bulk select + delete, Export CSV, Stats bar (total/today/conversion rate)

STEP 5 — JOBS
FormSubmissionNotificationJob (queue: 'mail'):
  → notify_admin: send mail to admin with submission data table
  → notify_submitter_field set + email in submission: send confirmation email to submitter

STEP 6 — ROUTES
Route::middleware(['auth', 'addon.active:ai-forms'])->group(function () {
    Route::resource('forms', FormController::class)->names('addon.forms.*');
    Route::get('forms/{form}/submissions', ...)->name('addon.forms.submissions');
    Route::post('forms/ai-suggest', ...)->name('addon.forms.ai-suggest');
    Route::post('forms/{form}/submissions/{sub}/read', ...);
    Route::get('forms/{form}/submissions/export', ...);
});
Route::get('/f/{slug}', [PublicFormController::class, 'show'])->name('addon.forms.public');
Route::post('/f/{slug}', [PublicFormController::class, 'submit']);
Route::post('/f/{slug}/track', [PublicFormController::class, 'track']);
Route::get('/addon/forms/embed/{slug}.js', [EmbedController::class, 'script']);

STEP 7 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "recaptcha_site_key", "type": "string", "label": "reCAPTCHA v3 Site Key" },
    { "key": "recaptcha_secret_key", "type": "encrypted", "label": "reCAPTCHA v3 Secret Key" },
    { "key": "max_forms_per_user", "type": "integer", "default": 20 },
    { "key": "ai_suggest_credits", "type": "integer", "default": 1, "label": "Credits per AI suggestion" }
  ]
}

STEP 8 — CHECKLIST
- [ ] Honeypot never visible (CSS display:none, NOT hidden input type)
- [ ] Conditional logic evaluated client-side in Public.vue — no server round-trip
- [ ] Embed iframe: sandbox="allow-scripts allow-forms allow-same-origin"
- [ ] File upload fields: validate mime + max size, store in storage/app/form-uploads/{form_id}/
```

**New tables:** `form_builder_forms`, `form_builder_fields`, `form_builder_submissions`

---

#### ADDON 22: AI Resume & CV Builder
**Slug:** `ai-resume`
**Price:** $19–$29 | **License required:** Regular

**What it does:**
Guided step-by-step resume builder. AI writes each section from rough notes. ATS-friendly templates. Export PDF (mPDF) or DOCX (PhpWord). LinkedIn paste import — paste profile text, AI extracts structured data.

**Features:**
- Guided builder: Personal Info → Experience → Education → Skills → Summary → Certifications → Custom Sections
- Per-section "Write with AI": user provides notes → AI writes polished text (streaming)
- Target job field: AI tailors language to role/industry
- 5 ATS-friendly templates (clean, modern, sidebar, minimal, executive)
- Real-time WYSIWYG preview pane (A4 proportions)
- Export PDF (mPDF) or DOCX (PhpWord)
- LinkedIn paste import: paste profile text → AI extracts structured JSON → fills all fields
- Cover letter generator: one-click from resume data → tailored cover letter
- Multiple resumes per user (each targeting different roles)
- ATS score checker: paste job description → AI scores match % + missing keywords

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-resume | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATION
Create migration: `resume_documents`
Columns: id (ulid), user_id (FK), title (string default 'My Resume'), target_job (string nullable),
template (string default 'clean'), data (json — all resume sections), ats_score (tinyint nullable),
cover_letter (longtext nullable), created_at, updated_at

DATA JSON SCHEMA:
{
  personal: { full_name, email, phone, location, website, linkedin, summary },
  experience: [ { company, title, start_date, end_date, current, bullets: string[] } ],
  education: [ { institution, degree, field, start_date, end_date } ],
  skills: [ { category, items: string[] } ],
  certifications: [ { name, issuer, date, url } ],
  custom_sections: [ { heading, content } ]
}

STEP 2 — SERVICE: ResumeService
writeSummary(array $resumeData, string $targetJob): Generator (streaming)
  → "Write a 3-sentence resume summary for a {targetJob}. Based on: {json}. Return ONLY the summary."
writeExperienceBullets(array $experience, string $targetJob): Generator
  → "Write 4 achievement-oriented bullets for: {json}. Target job: {targetJob}. Return JSON array of strings."
importFromLinkedIn(string $pastedText): array
  → "Extract resume data from this LinkedIn profile text. Return ONLY JSON matching schema: {schema}"
atsScore(array $resumeData, string $jobDescription): array
  → "Score resume vs job description. Return JSON: { score: int, matched_keywords, missing_keywords, suggestions }"
exportToPdf(Resume $resume): string → mPDF renders template Blade view → returns file path
exportToDocx(Resume $resume): string → PhpWord → returns file path

STEP 3 — CONTROLLERS
ResumeController: index, create, edit, store, update, export(format), destroy
ResumeAiController (all stream except importLinkedIn + atsScore):
  writeSummary, writeBullets, importLinkedIn (returns JSON), atsScore (returns JSON), coverLetter (stream)

STEP 4 — VUE PAGES
resources/js/Pages/Resume/Builder.vue:
  - Left: section tabs (Personal, Experience, Education, Skills, Certifications, Custom)
  - Right: live A4 preview pane (updates reactively via computed)
  - Top toolbar: 5 template thumbnails, Export PDF/DOCX buttons, ATS Score, Cover Letter
  - Each section: form fields + "Write with AI" button per text area
  - Experience: multiple entries, add/remove, drag reorder (vue-draggable-plus)
  - ATS Score modal: paste job description → score gauge + keyword chips

resources/js/Pages/Resume/Index.vue:
  - Cards: title, target job, template, last updated
  - Actions: Edit, Download PDF, Download DOCX, Duplicate, Delete

STEP 5 — PDF TEMPLATES (Blade views)
resources/views/addons/ai-resume/templates/{clean|modern|sidebar|minimal|executive}.blade.php
Each receives $resume model, outputs A4 HTML for mPDF rendering.

STEP 6 — ROUTES
Route::middleware(['auth', 'addon.active:ai-resume'])->prefix('resume')->group(function () {
    Route::get('/', ...)->name('addon.resume.index');
    Route::get('/create', ...)->name('addon.resume.create');
    Route::post('/', ...)->name('addon.resume.store');
    Route::get('/{resume}/edit', ...)->name('addon.resume.edit');
    Route::put('/{resume}', ...)->name('addon.resume.update');
    Route::get('/{resume}/export/{format}', ...)->name('addon.resume.export');
    Route::delete('/{resume}', ...)->name('addon.resume.destroy');
    Route::prefix('ai')->group(function () {
        Route::post('/summary', ...); Route::post('/bullets', ...);
        Route::post('/linkedin-import', ...); Route::post('/ats-score', ...);
        Route::post('/{resume}/cover-letter', ...);
    });
});

STEP 7 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "max_resumes_per_user", "type": "integer", "default": 10 },
    { "key": "credits_per_section_write", "type": "integer", "default": 1 },
    { "key": "credits_per_ats_score", "type": "integer", "default": 2 }
  ]
}

STEP 8 — CHECKLIST
- [ ] All AI stream via POST + ReadableStream + X-Accel-Buffering: no
- [ ] PDF/DOCX temp files cleaned by scheduled job (daily: delete files older than 1 hour)
- [ ] Duplicate resume creates full deep copy of data JSON
- [ ] ATS score shown with disclaimer: "AI estimate, not guaranteed"
- [ ] LinkedIn import handles incomplete text gracefully (partial fill is fine)
```

**New tables:** `resume_documents`

---

#### ADDON 23: AI Voice Studio
**Slug:** `ai-voice-studio`
**Price:** $39–$59 | **License required:** Regular

**What it does:**
Text-to-speech generation with ElevenLabs, PlayHT, and OpenAI TTS. Voice cloning via ElevenLabs. Audio library with folder organization. WaveSurfer.js waveform player. MP3 download.

**Features:**
- Provider selection: ElevenLabs, PlayHT, OpenAI TTS (admin enables each)
- Voice browser: grid with preview play per voice, filter by provider/category
- Voice cloning: upload 30s–3min audio sample → ElevenLabs creates personal voice
- Text input: paste text OR pull from Document Library
- Audio settings: stability, similarity boost, speed, pitch (provider-dependent)
- Generation queued via job; Reverb notification when ready
- Audio library: list/grid view, folders, rename, delete
- WaveSurfer.js player: waveform visualizer, play/pause, time display, download MP3
- Batch TTS: up to 20 paragraphs → one file per paragraph

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-voice-studio | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATIONS
Create migration: `voice_studio_voices`
Columns: id (ulid), provider (enum: elevenlabs/playht/openai), provider_voice_id (string),
name (string), category (string nullable), preview_url (string nullable),
is_cloned (boolean default false), user_id (FK nullable), created_at, updated_at

Create migration: `voice_studio_generations`
Columns: id (ulid), user_id (FK), voice_id (FK nullable), text_input (longtext),
character_count (int), provider (string), settings (json), status (enum: pending/processing/ready/failed),
audio_path (string nullable), duration_seconds (float nullable), folder_id (FK nullable),
title (string), created_at, updated_at

Create migration: `voice_studio_folders`
Columns: id (ulid), user_id (FK), name (string), created_at, updated_at

STEP 2 — SERVICE: VoiceStudioService
getVoices(string $provider): array
  → ElevenLabs: GET api.elevenlabs.io/v1/voices (cache 1hr Redis)
  → PlayHT: GET api.play.ht/api/v2/voices (cache 1hr)
  → OpenAI: hardcoded [alloy, echo, fable, onyx, nova, shimmer]
  → Upsert into voice_studio_voices

generateSpeech(Generation $gen): string
  → ElevenLabs: POST /v1/text-to-speech/{voice_id} → binary stream → save MP3
  → PlayHT: POST /api/v2/tts → poll audio URL → download → save MP3
  → OpenAI: POST /v1/audio/speech → binary stream → save MP3
  → Save to storage/app/voice-studio/{user_id}/{gen_id}.mp3
  → Update status=ready, duration_seconds

cloneVoice(UploadedFile $sample, string $name, User $user): VoiceStudioVoice
  → POST ElevenLabs /v1/voices/add (multipart) → create voice record with is_cloned=true

STEP 3 — CONTROLLERS
VoiceStudioController: index, create, store (dispatch job), stream (auth audio playback),
  download, update (title/folder), destroy (delete record + file)
VoiceController: index (all voices), clone, preview (proxy provider preview), destroyCloned
FolderController: store, update, destroy

STEP 4 — JOBS
GenerateSpeechJob (queue: 'ai'):
  → set processing → call generateSpeech() → set ready
  → broadcast 'SpeechGenerationReady' on user Reverb channel
  → deduct credits: ceil(char_count / 1000) * credits_per_1k_chars

STEP 5 — VUE PAGES
resources/js/Pages/VoiceStudio/Create.vue:
  - Textarea (char counter) OR "Import from Document" button
  - Voice browser: grid cards (name, category, play preview, select radio)
  - Filter: provider, category, search
  - Settings: stability/similarity/speed sliders, SSML toggle
  - Generate button → credit estimate display
  - "Clone My Voice" button → modal with file upload + name

resources/js/Pages/VoiceStudio/Index.vue:
  - Left: folder tree; Main: grid/list of generations
  - Each item: WaveSurfer waveform, title, duration, provider badge
  - Inline player (load from /voice-studio/{id}/stream on play)
  - Drag-drop between folders

WaveSurfer integration:
  Load from unpkg.com/wavesurfer.js@7/dist/wavesurfer.min.js
  WaveSurfer.create({ container, waveColor, progressColor, url: streamUrl })
  Play/pause/stop + time elapsed display

STEP 6 — ROUTES
Route::middleware(['auth', 'addon.active:ai-voice-studio'])->prefix('voice-studio')->group(function () {
    Route::get('/', ...); Route::get('/create', ...); Route::post('/', ...);
    Route::get('/{gen}/stream', ...); Route::get('/{gen}/download', ...);
    Route::put('/{gen}', ...); Route::delete('/{gen}', ...);
    Route::get('/voices', ...); Route::post('/voices/clone', ...);
    Route::get('/voices/{voice}/preview', ...); Route::delete('/voices/{voice}', ...);
    Route::apiResource('/folders', FolderController::class);
});

STEP 7 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "elevenlabs_api_key", "type": "encrypted", "label": "ElevenLabs API Key" },
    { "key": "playht_api_key", "type": "encrypted", "label": "PlayHT API Key" },
    { "key": "playht_user_id", "type": "encrypted", "label": "PlayHT User ID" },
    { "key": "active_providers", "type": "multiselect", "options": ["elevenlabs","playht","openai"] },
    { "key": "credits_per_1k_chars", "type": "integer", "default": 2 },
    { "key": "max_chars_per_generation", "type": "integer", "default": 5000 },
    { "key": "allow_voice_cloning", "type": "boolean", "default": true }
  ]
}

STEP 8 — CHECKLIST
- [ ] Audio files in private storage — always streamed through authenticated controller
- [ ] PlayHT uses async polling — job must poll status endpoint with timeout (max 5 min)
- [ ] Characters counted before job dispatch — block if insufficient credits
- [ ] Generation::deleting observer also deletes storage file
- [ ] Voice cloning: max 1 cloned voice per user by default
```

**New tables:** `voice_studio_voices`, `voice_studio_generations`, `voice_studio_folders`

---

#### ADDON 24: Client Portal
**Slug:** `client-portal`
**Price:** $49–$79 | **License required:** Extended (Pro)

**What it does:**
Agency-focused addon. Pro users create client accounts with dedicated portals for reviewing and approving deliverables. Clients get a white-labeled, OTP-authenticated portal showing only content assigned to them. Full comment + approval workflow.

**Features:**
- Create client records: name, email, company, brand color, logo, optional expiry date
- Client receives OTP invite email; authenticates via separate `portal` guard
- Client portal URL: `/portal/{client-slug}` — shows client's logo/color
- Assign any document/image/file as a deliverable to a client
- Deliverable statuses: Draft → In Review → Approved / Revision Requested
- Inline comment threads; version history per deliverable
- Owner notified via Reverb + email on every client action
- Activity log: all views, approvals, revisions with timestamps
- Admin sees all portals: client name, pending approvals, last activity

**Step-by-step build prompt for DeepSeek:**

```
ADDON: client-portal | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4
Gate: isProAvailable() must be true — check in AddonServiceProvider::register()

STEP 1 — MIGRATIONS
Create migration: `portal_clients`
Columns: id (ulid), owner_user_id (FK users), name, email, company (nullable), slug (unique),
brand_color (string default '#6366f1'), logo_path (nullable), portal_expires_at (timestamp nullable),
is_active (boolean default true), created_at, updated_at

Create migration: `portal_deliverables`
Columns: id (ulid), client_id (FK), owner_user_id (FK), title, type (enum: document/image/text/file),
content (longtext nullable), file_path (nullable), status (enum: draft/in_review/approved/revision_requested),
current_version (tinyint default 1), created_at, updated_at

Create migration: `portal_deliverable_versions`
Columns: id (ulid), deliverable_id (FK), version_number (tinyint), content (longtext nullable),
file_path (nullable), created_by_type (enum: owner/client), notes (text nullable), created_at

Create migration: `portal_comments`
Columns: id (ulid), deliverable_id (FK), user_id (FK nullable), client_id (FK nullable),
content (text), created_at, updated_at

Create migration: `portal_activity_log`
Columns: id (ulid), client_id (FK), deliverable_id (FK nullable), action (string),
metadata (json nullable), ip_address (string), created_at

STEP 2 — AUTH: Portal Guard
Add guard 'portal' in config/auth.php: driver=session, provider=portal_clients
PortalUserProvider implements UserProvider for portal_clients table
Middleware 'auth:portal' on all client-facing portal routes
Client OTP: same 6-digit OTP system as core, adapted for portal_clients table
OTP stored in Redis: "portal_otp_{client_id}" → TTL = portal_otp_expiry_hours setting

STEP 3 — SERVICE: ClientPortalService
inviteClient(PortalClient $client): void
  → Generate OTP → cache → send invite email with /portal/{slug}/login?token={otp}
assignDeliverable(array $data, User $owner): Deliverable → create record, notify client
submitForReview(Deliverable $d): void → status=in_review, email client
approveDeliverable(Deliverable $d, PortalClient $client): void
  → status=approved → log activity → broadcast + email to owner
requestRevision(Deliverable $d, PortalClient $client, string $comment): void
  → status=revision_requested → create comment → notify owner

STEP 4 — CONTROLLERS
Owner-side (auth middleware):
  ClientController: CRUD for portal_clients (owner's own clients only)
  DeliverableController: create from doc/image/file, submit for review, update (new version)
  CommentController: add comment

Client-side (auth:portal middleware, under /portal/{slug}/):
  PortalController: loginForm, login (OTP verify), logout, dashboard, show deliverable
  PortalActionController: approve, requestRevision
  PortalCommentController: add comment

STEP 5 — VUE PAGES
Owner-side:
resources/js/Pages/Portal/Clients/Index.vue — table: name, status, pending count, last activity
resources/js/Pages/Portal/Clients/Show.vue — deliverables table + activity log + "Add Deliverable"
resources/js/Pages/Portal/Deliverables/Create.vue — title, type, content/file, select client

Client-side (uses PortalLayout — applies brand_color as CSS var, shows client logo):
resources/js/Pages/Portal/Public/Login.vue — email input → OTP 6-digit boxes
resources/js/Pages/Portal/Public/Dashboard.vue — deliverables list, status badges, filter
resources/js/Pages/Portal/Public/Deliverable.vue:
  - Content viewer (HTML doc / image lightbox / file download)
  - Version tabs: compare v1 vs v2 side-by-side
  - Comment thread
  - Action bar: "Approve" (green, only in_review) | "Request Revision" (requires comment)

STEP 6 — PORTAL LAYOUT
resources/js/Layouts/PortalLayout.vue:
  - Minimal header: client logo + portal name + logout
  - CSS: :root { --portal-brand: {brand_color} } — applied to buttons/accents
  - No MakeAI branding visible (optional "Powered by" toggle via addon setting)

STEP 7 — ROUTES
// Owner routes
Route::middleware(['auth', 'addon.active:client-portal', 'isProAvailable'])->prefix('portal')->group(function () {
    Route::resource('clients', ClientController::class)->names('addon.portal.clients.*');
    Route::resource('clients.deliverables', DeliverableController::class)->names('addon.portal.deliverables.*');
    Route::post('deliverables/{d}/submit', ...)->name('addon.portal.deliverables.submit');
});
// Client portal routes
Route::prefix('portal/{clientSlug}')->name('portal.')->group(function () {
    Route::get('/login', ...)->name('login');
    Route::post('/login', ...)->name('login.submit');
    Route::post('/logout', ...)->name('logout');
    Route::middleware(['auth:portal'])->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/d/{deliverable}', ...)->name('deliverable');
        Route::post('/d/{deliverable}/approve', ...)->name('deliverable.approve');
        Route::post('/d/{deliverable}/revision', ...)->name('deliverable.revision');
        Route::post('/d/{deliverable}/comments', ...)->name('deliverable.comment');
    });
});

STEP 8 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "show_powered_by", "type": "boolean", "default": true },
    { "key": "max_clients_per_user", "type": "integer", "default": 50 },
    { "key": "portal_otp_expiry_hours", "type": "integer", "default": 24 },
    { "key": "notify_on_approve", "type": "boolean", "default": true }
  ]
}

STEP 9 — CHECKLIST
- [ ] isProAvailable() checked in register() — block install if false
- [ ] Portal guard sessions completely isolated from main app (different session key prefix)
- [ ] Client cannot access /portal/{other-slug}/ — slug check in middleware
- [ ] Expired portal: redirect to login with "This portal has expired" message
- [ ] File deliverables streamed via portal controller — never direct storage URL
```

**New tables:** `portal_clients`, `portal_deliverables`, `portal_deliverable_versions`, `portal_comments`, `portal_activity_log`
**Requires:** `isProAvailable() === true`

---

#### ADDON 25: AI SEO Suite
**Slug:** `ai-seo-suite`
**Price:** $39–$59 | **License required:** Regular

**What it does:**
Full SEO toolkit. Keyword research (AI generates clusters from a seed), competitor content gap analysis (fetch URL → identify topic gaps), on-page SEO scoring (12 factors), internal link suggestions from document library, schema markup generator (JSON-LD), and SERP snippet previewer.

**Features:**
- Keyword Researcher: seed + audience → 40+ keywords in 4 intent clusters
- Competitor Gap Analyzer: paste URL → Jina Reader fetches content → AI finds gaps vs user's library
- On-Page SEO Scorer: paste URL or content → 12-factor score + fix list
- Internal Link Suggester: analyze content → suggest pages from document library to link
- Schema Markup Generator: Article/FAQ/Product/LocalBusiness/HowTo/Review → JSON-LD output
- SERP Snippet Previewer: meta title + description editor with live Google-style preview
- Keyword Tracker: save keywords, manual rank entry, basic Chart.js trend line
- Project organization: all tools grouped under named projects (one per website)

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-seo-suite | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATIONS
Create migration: `seo_projects`
Columns: id (ulid), user_id (FK), name (string), domain (string nullable), created_at, updated_at

Create migration: `seo_keyword_reports`
Columns: id (ulid), project_id (FK), seed_keyword (string), clusters (json), created_at

Create migration: `seo_page_scores`
Columns: id (ulid), project_id (FK), url (nullable), content_hash (string), score (tinyint),
factors (json), suggestions (json), created_at

Create migration: `seo_tracked_keywords`
Columns: id (ulid), project_id (FK), keyword, target_url (nullable), created_at, updated_at

Create migration: `seo_keyword_ranks`
Columns: id (ulid), tracked_keyword_id (FK), rank (smallint nullable), recorded_at (date), notes (nullable)

Create migration: `seo_schema_markup`
Columns: id (ulid), project_id (FK), schema_type (string), form_data (json),
generated_json_ld (longtext), created_at

STEP 2 — SERVICE: SeoService
keywordResearch(string $seed, string $audience): array
  → AiService (provider set to Perplexity if enabled):
    "Generate 40 SEO keywords for '{seed}' targeting '{audience}'.
    Return JSON: { informational: string[], commercial: string[], navigational: string[], long_tail: string[] }"
  → Save report → return clusters

competitorGapAnalysis(string $url, User $user): array
  → Fetch URL via Jina Reader: https://r.jina.ai/{encoded_url} (returns clean markdown)
  → Fetch user's doc library titles
  → $this->ai->complete(new CompletionRequest(messages: [...], ...)):
    prompt: "Competitor covers: {topics}. User has: {user_titles}. Find top 10 content gaps.
    Return JSON: { gaps: [ { topic, competitor_coverage, suggested_title } ] }"

scorePageSeo(string $content, string $keyword): array
  → $this->ai->complete(new CompletionRequest(messages: [...], ...)):
    prompt: "Score this content for SEO targeting '{keyword}'. Score each 0-10.
    Factors: title_tag, meta_description, h1_usage, keyword_density, content_length, readability,
    internal_links, image_alt_text, url_structure, mobile_indicators, schema_presence, page_speed_indicators.
    Return JSON: { overall_score: int, factors: { name: { score: int, notes: string } }, top_fixes: string[] }"

internalLinkSuggestions(string $content, User $user): array
  → $this->ai->complete(new CompletionRequest(messages: [...], ...)):
    prompt: "Given content and these pages: {pages_json}.
    Suggest 5 internal linking opportunities.
    Return JSON: { suggestions: [ { anchor_text, target_page_title, target_url, context } ] }"

generateSchemaMarkup(string $type, array $data): string
  → $this->ai->complete(new CompletionRequest(messages: [...], ...)):
    prompt: "Generate valid JSON-LD schema of type '{type}' for: {json}. Return ONLY raw JSON-LD."
  → json_decode() validate before saving

STEP 3 — CONTROLLERS
SeoProjectController: CRUD for seo_projects
KeywordController: store (research), index (list reports), show
CompetitorController: store (dispatches SeoCompetitorAnalysisJob for large pages, inline for small)
PageScoreController: store, index, show
InternalLinkController: analyze (POST, no persistence)
SchemaController: store, index, show, destroy
KeywordTrackerController: CRUD + rank entry endpoint

STEP 4 — VUE PAGES
resources/js/Pages/Seo/Keywords.vue:
  - Seed + audience inputs + "Research" button
  - Output: 4 tab columns (Informational/Commercial/Navigational/Long-tail)
  - Per keyword: copy, "Add to tracker", "Create content for this" (→ AI Writer with pre-filled topic)

resources/js/Pages/Seo/CompetitorGap.vue:
  - URL input → analyze → card per gap: topic, competitor coverage, "Create this content" CTA

resources/js/Pages/Seo/PageScore.vue:
  - Content textarea OR URL input + target keyword
  - Score display: large gauge (overall), factor grid (color-coded: red<5, amber<8, green≥8)
  - Fix list: ranked action items

resources/js/Pages/Seo/InternalLinks.vue:
  - Content paste → analyze → suggestion list with anchor text + target + context quote
  - "Copy all as HTML" button

resources/js/Pages/Seo/Schema.vue:
  - Schema type cards (icons per type), dynamic form per type
  - Generated JSON-LD: <pre> syntax-highlighted, copy button, "Test in Google" external link
  - Saved schemas list

resources/js/Pages/Seo/SerpPreview.vue:
  - Meta title (60 char counter), meta description (160 char counter)
  - Live Google SERP preview (desktop + mobile toggle)

STEP 5 — ROUTES
Route::middleware(['auth', 'addon.active:ai-seo-suite'])->prefix('seo')->group(function () {
    Route::resource('projects', SeoProjectController::class)->names('addon.seo.projects.*');
    Route::post('projects/{p}/keywords', ...); Route::get('projects/{p}/keywords', ...);
    Route::post('projects/{p}/competitor-gap', ...);
    Route::post('projects/{p}/page-score', ...);
    Route::post('internal-links', ...);
    Route::resource('projects.schema', SchemaController::class)->names('addon.seo.schema.*');
    Route::resource('projects.tracked-keywords', KeywordTrackerController::class);
    Route::post('projects/{p}/tracked-keywords/{k}/rank', ...);
});

STEP 6 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "jina_api_key", "type": "encrypted", "label": "Jina Reader API Key" },
    { "key": "use_perplexity", "type": "boolean", "default": false, "label": "Use Perplexity for keyword research" },
    { "key": "credits_per_keyword_research", "type": "integer", "default": 3 },
    { "key": "credits_per_page_score", "type": "integer", "default": 2 },
    { "key": "credits_per_gap_analysis", "type": "integer", "default": 5 }
  ]
}

STEP 7 — CHECKLIST
- [ ] Competitor URL fetch: 10-second timeout, handle 403/404/timeout gracefully
- [ ] Jina URL format: https://r.jina.ai/{rawurlencode($url)}
- [ ] Schema JSON-LD: json_decode() validate before saving — reject if invalid
- [ ] Internal link suggestions: only suggest docs owned by the current user (scoped query)
- [ ] SERP preview: max title 60 chars displayed, excess shown in red
```

**New tables:** `seo_projects`, `seo_keyword_reports`, `seo_page_scores`, `seo_tracked_keywords`, `seo_keyword_ranks`, `seo_schema_markup`

---

#### ADDON 26: AI Code Assistant
**Slug:** `ai-code-assistant`
**Price:** $29–$49 | **License required:** Regular

**What it does:**
Monaco Editor-based code playground. Generate code from natural language, explain code, add documentation comments, convert between languages, write unit tests, and debug errors. 30+ languages. Snippet library with tagging. Ctrl+K command bar.

**Features:**
- Monaco Editor (VS Code in browser, loaded from CDN)
- Toolbar actions: Generate, Explain, Add Comments, Convert Language, Write Tests, Debug Error
- Each action: side panel with streaming AI output + "Apply" button (diff view before applying)
- Snippet Library: save named snippets by language + tag
- Import from Document Library / Export to Document Library
- Ctrl+K command bar: type intent → AI generates directly in editor
- Multi-tab: multiple open snippets in one session
- Language auto-detection from pasted code
- Quick-start templates: common patterns per language/framework

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-code-assistant | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATION
Create migration: `code_snippets`
Columns: id (ulid), user_id (FK), title (string), language (string), code (longtext),
tags (json nullable), is_template (boolean default false), created_at, updated_at

STEP 2 — SERVICE: CodeAssistantService
All methods return Generator (streaming via AiService).
System prompt convention: "You are an expert {language} developer. Return ONLY code."

generateCode(string $prompt, string $language): Generator
  → User: "Write {language} code for: {prompt}"

explainCode(string $code, string $language): Generator
  → System: "You are a code reviewer. Explain clearly."
  → User: "Explain this {language} code step-by-step: {code}"

addComments(string $code, string $language): Generator
  → System: "Add {docblock_format} documentation to this {language} code. Return ONLY commented code."
  (docblock_format: JSDoc for JS/TS, PHPDoc for PHP, docstring for Python, XML for C#, etc.)

convertLanguage(string $code, string $from, string $to): Generator
  → "Convert this {from} code to {to}. Return ONLY the converted code."

writeTests(string $code, string $language): Generator
  → "Write comprehensive unit tests for this {language} code. Return ONLY test code."

debugError(string $code, string $language, string $error): Generator
  → "Fix this {language} code that produces error: {error}. Return ONLY fixed code."

STEP 3 — CONTROLLERS
CodeAssistantController:
  index(): Inertia 'CodeAssistant/Index' with recent snippets
  create(): Inertia 'CodeAssistant/Editor' (blank)
  edit(Snippet): Inertia 'CodeAssistant/Editor' with snippet data

CodeAiController (all POST, all stream):
  generate, explain, comment, convert, tests, debug
  Each: validate input → call CodeAssistantService → stream response
  → X-Accel-Buffering: no header on all streaming responses

SnippetController: store, update, destroy, index (JSON for side panel)

STEP 4 — VUE PAGES
resources/js/Pages/CodeAssistant/Editor.vue:
  - Top bar: language dropdown, snippet title (auto-save on blur), save, open snippet dropdown, export to docs
  - Main: Monaco Editor (full height)
  - Right sidebar (collapsible, 380px):
    Tabs: Generate | Explain | Comments | Convert | Tests | Debug
    - Generate: prompt textarea + button → streaming into sidebar → "Apply to Editor"
    - Explain/Comments/Tests: use editor content, action button → streaming
    - Convert: target language selector + button → streaming → "Apply" or "New Tab"
    - Debug: error message textarea + button → streaming → "Apply Fix"
  - Diff view modal: side-by-side (current | suggested) before applying
  - Ctrl+K: floating input bar over editor → sends to generate

Monaco Editor setup (load from CDN):
  In mounted(): use window.require with config:
    { paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' } }
  Sync with dark mode: watch(isDark, v => monaco.editor.setTheme(v ? 'vs-dark' : 'vs'))
  Apply: editor.executeEdits('ai-assistant', [{ range: fullRange, text: newCode }])
    (executeEdits preserves undo history, unlike setValue)

resources/js/Pages/CodeAssistant/Index.vue:
  - Recent snippets grid: title, language badge, first 3 lines preview, date
  - Filter by language, search
  - Templates section: quick-start cards (CRUD controller, Vue composable, etc.)
  - "New Editor" button

STEP 5 — ROUTES
Route::middleware(['auth', 'addon.active:ai-code-assistant'])->prefix('code')->group(function () {
    Route::get('/', [CodeAssistantController::class, 'index'])->name('addon.code.index');
    Route::get('/editor', ...)->name('addon.code.create');
    Route::get('/editor/{snippet}', ...)->name('addon.code.edit');
    Route::prefix('ai')->group(function () {
        Route::post('/generate', [CodeAiController::class, 'generate']);
        Route::post('/explain', ...); Route::post('/comment', ...);
        Route::post('/convert', ...); Route::post('/tests', ...); Route::post('/debug', ...);
    });
    Route::apiResource('snippets', SnippetController::class)->names('addon.code.snippets.*');
});

STEP 6 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "credits_per_action", "type": "integer", "default": 1, "label": "Credits per AI action" },
    { "key": "max_code_chars", "type": "integer", "default": 20000, "label": "Max chars per analysis" },
    { "key": "default_language", "type": "string", "default": "javascript" }
  ]
}

STEP 7 — CHECKLIST
- [ ] Monaco loaded from cdn.jsdelivr.net — already in CSP allowlist
- [ ] Code NOT saved unless user explicitly clicks save — no auto-save to DB
- [ ] Apply uses executeEdits() not setValue() to preserve undo history
- [ ] All AI actions stream via POST + ReadableStream + X-Accel-Buffering: no
- [ ] Snippet queries always scoped to auth()->id()
```

**New tables:** `code_snippets`

---

#### ADDON 27: White-Label Mobile App Shell
**Slug:** `mobile-app-shell`
**Price:** $99–$149 | **License required:** Extended (Pro)

**What it does:**
Pre-built Flutter app wrapping the MakeAI REST API. Admin wizard configures branding (name, color, logo, features). Addon generates `config.dart` + assets ZIP, ready for the buyer to compile and publish under their own App Store/Play Store account. Includes push notifications via FCM.

**Features:**
- Admin wizard: app name, primary color, logo, API URL, feature toggles
- Generates `config.dart` + assets ZIP (downloadable)
- Flutter app features: OTP login (Sanctum token), AI tools list, streaming tool execution, chat, document library, subscription management (if Pro), profile/settings
- Push notifications via FCM v1 (Service Account JSON)
- Admin sees all registered devices: platform, version, last seen
- Broadcast push from admin panel: all users or specific user
- Step-by-step build guide PDF included in ZIP

**Step-by-step build prompt for DeepSeek:**

```
ADDON: mobile-app-shell | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4
Gate: isProAvailable() must be true

STEP 1 — MIGRATIONS
Create migration: `mobile_app_devices`
Columns: id (ulid), user_id (FK), device_id (string unique), platform (enum: ios/android),
app_version (string), fcm_token (string nullable), last_seen_at (timestamp), created_at, updated_at

Create migration: `mobile_push_notifications`
Columns: id (ulid), user_id (FK nullable — null = broadcast), title, body (text),
data (json nullable), sent_at (timestamp nullable), created_at

STEP 2 — SANCTUM MOBILE API ENDPOINTS
All under /api/v1/, authenticated via Sanctum token (ability: 'mobile').
POST   /api/v1/auth/request-otp    → send OTP
POST   /api/v1/auth/verify-otp     → verify OTP, return token with 'mobile' ability
GET    /api/v1/user                → current user profile
GET    /api/v1/ai-tools            → list active tools
POST   /api/v1/ai-tools/{tool}/run → execute (text/event-stream streaming)
GET    /api/v1/documents           → user's docs (paginated)
DELETE /api/v1/documents/{id}
GET    /api/v1/chatbots
POST   /api/v1/chatbots/{id}/chat  → streaming
GET    /api/v1/user/subscription   → status + plan (isProAvailable gated)
GET    /api/v1/plans               → list plans (isProAvailable gated)
POST   /api/v1/devices/register    → store FCM token
POST   /api/v1/devices/unregister  → remove FCM token on logout
All responses: { success: bool, data: mixed, message: string }

STEP 3 — SERVICE: MobileAppService
generateConfigDart(array $settings): string
  → Returns config.dart content:
    const String appName = '{name}';
    const String apiBaseUrl = '{url}';
    const int primaryColorHex = 0xFF{hex_no_hash};
    const bool featureChat = {bool};
    const bool featureSubscriptions = {bool};
    const bool featureDocuments = {bool};

buildConfigZip(): string (path)
  → generateConfigDart() → write to temp file
  → Copy logo to temp assets/logo.png
  → Add README_BUILD.md (Flutter setup steps: flutter pub get, flutter build apk/ios)
  → Create ZIP → return path

sendPushNotification(string $title, string $body, ?User $user): void
  → Get FCM access token via Google OAuth2 using service account JSON
  → If $user: send to user's device FCM tokens
  → If null: batch all tokens (500 per request)
  → POST https://fcm.googleapis.com/v1/projects/{project_id}/messages:send
  → Create mobile_push_notifications record, set sent_at

STEP 4 — CONTROLLERS
MobileAppAdminController:
  index(): Inertia 'MobileApp/Admin/Index' with config + device stats
  configure(Request): save settings via addon_setting()
  generateConfig(): stream ZIP download → delete temp file after
  devices(): Inertia 'MobileApp/Admin/Devices' paginated
  push(): Inertia 'MobileApp/Admin/Push'
  sendPush(Request): dispatch SendPushNotificationJob

MobileAppApiController: register, unregister (for Sanctum API routes)

STEP 5 — FLUTTER APP TEMPLATE STRUCTURE (separate repo — document in README)
lib/config.dart              ← GENERATED
lib/main.dart
lib/core/api/api_client.dart ← Dio; attaches Sanctum token from secure storage
lib/core/auth/otp_screen.dart, otp_verify_screen.dart
lib/features/home/home_screen.dart
lib/features/tools/tools_list.dart, tool_run_screen.dart (SSE streaming via http package)
lib/features/chat/chat_screen.dart
lib/features/documents/documents_screen.dart
lib/features/profile/profile_screen.dart, subscription_screen.dart
lib/shared/widgets/streaming_text.dart ← renders SSE chunks as they arrive
lib/shared/theme/app_theme.dart ← reads primaryColorHex from config.dart

STEP 6 — VUE PAGES
resources/js/Pages/MobileApp/Admin/Index.vue:
  Step wizard (4 steps, persistent state):
    1. Identity: app name, primary color picker, logo upload
    2. API Config: base URL (auto-filled current domain), "Test Connection" button
    3. Features: checkboxes Chat/Subscriptions/Documents/Notifications
    4. Push: FCM service account JSON textarea (encrypted setting), "Send Test" button
  Stats: device count, iOS/Android split, active last 7 days
  Download Config ZIP button (prominent)

resources/js/Pages/MobileApp/Admin/Devices.vue:
  Table: user email, platform badge, app version, last seen, FCM status

resources/js/Pages/MobileApp/Admin/Push.vue:
  Title + message inputs, Target: "All Users" or user search autocomplete, Send button

STEP 7 — JOBS
SendPushNotificationJob (queue: 'mail'):
  → chunk tokens by 500, send batches
  → update sent_at on record

STEP 8 — ROUTES
Route::middleware(['auth', 'isProAvailable', 'addon.active:mobile-app-shell'])->prefix('admin/mobile-app')->group(function () {
    Route::get('/', ...)->name('addon.mobile-app.admin.index');
    Route::post('/configure', ...); Route::get('/config-download', ...);
    Route::get('/devices', ...); Route::get('/push', ...); Route::post('/push', ...);
});
Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {
    Route::post('devices/register', ...); Route::post('devices/unregister', ...);
});

STEP 9 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "app_display_name", "type": "string", "label": "App Display Name" },
    { "key": "primary_color_hex", "type": "color", "default": "#10b981" },
    { "key": "fcm_service_account_json", "type": "encrypted", "label": "FCM Service Account JSON" },
    { "key": "feature_chat", "type": "boolean", "default": true },
    { "key": "feature_subscriptions", "type": "boolean", "default": true },
    { "key": "feature_documents", "type": "boolean", "default": true }
  ]
}

STEP 10 — CHECKLIST
- [ ] isProAvailable() gated in register()
- [ ] Sanctum tokens for mobile use 'mobile' ability only — cannot access admin routes
- [ ] FCM: generate OAuth2 access token per request (short-lived; cache for 50 min)
- [ ] Config ZIP temp file deleted after streaming response completes
- [ ] README_BUILD.md steps: flutter pub get → flutter run (dev) → flutter build apk/ios (prod)
```

**New tables:** `mobile_app_devices`, `mobile_push_notifications`
**Requires:** `isProAvailable() === true`

---

#### ADDON 28: AI Podcast Producer
**Slug:** `ai-podcast`
**Price:** $39–$49 | **License required:** Regular

**What it does:**
End-to-end podcast content workflow. Generate episode scripts, show notes, titles, guest bios. Build and host a valid RSS 2.0 podcast feed with iTunes tags directly from MakeAI. Embeddable player widget for external websites.

**Features:**
- Episode Script Generator: topic + duration + format → full script (intro, segments, ad reads, outro)
- Show Notes Generator: transcript/script → formatted notes with timestamps + takeaways
- Episode Title + Description Generator: 5 title variations + SEO description
- Guest Bio Writer: name + talking points → professional bio
- Podcast RSS Feed: valid RSS 2.0 + iTunes namespace, auto-generated per show
- Audio upload per episode (MP3, stored private, streamed via controller)
- In-browser player using WaveSurfer.js; public episode pages
- Embeddable player via `<iframe>` snippet
- Episode draft/scheduled/published workflow + publish date scheduling
- Play count tracking (Redis incr → hourly DB sync)

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-podcast | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4

STEP 1 — MIGRATIONS
Create migration: `podcast_shows`
Columns: id (ulid), user_id (FK), title, slug (unique), description (text), artwork_path (nullable),
category (string), author (string), language (string default 'en'), website_url (nullable),
is_public (boolean default true), created_at, updated_at

Create migration: `podcast_episodes`
Columns: id (ulid), show_id (FK), title, description (longtext), show_notes (longtext nullable),
audio_path (nullable), audio_url_external (nullable), duration_seconds (int nullable),
episode_number (smallint nullable), season_number (tinyint nullable),
status (enum: draft/scheduled/published default draft),
published_at (timestamp nullable), play_count (int default 0), created_at, updated_at

Create migration: `podcast_scripts`
Columns: id (ulid), user_id (FK), episode_id (FK nullable), title, topic, duration_minutes (tinyint),
format (enum: interview/solo/panel/storytelling), script_content (longtext), created_at, updated_at

STEP 2 — SERVICE: PodcastService
generateScript(string $topic, int $minutes, string $format, string $host): Generator (streaming)
  → System: "You are a professional podcast script writer."
  → User: "Write a {minutes}-min {format} podcast on '{topic}'. Host: {host}.
    Include [INTRO], [MAIN] with timing cues like [00:00], [AD READ], [OUTRO]."

generateShowNotes(string $transcriptOrScript): Generator
  → "Convert to show notes: summary (2-3 sentences), 5 key takeaways, topic timestamps, links."

generateTitles(string $topic): array
  → $this->ai->complete() (synchronous): "5 episode title variations for '{topic}' + 150-word SEO description.
    Return JSON: { titles: string[], description: string }"
  → json_decode() → return array

generateGuestBio(string $name, string $talkingPoints): Generator

buildRssFeed(PodcastShow $show): string (XML)
  → Build RSS 2.0 with xmlns:itunes and xmlns:content
  → <channel>: title, description, itunes:image, itunes:category, itunes:author, language, link
  → Per published episode <item>: title, description, itunes:duration,
    <enclosure url="{stream_url}" type="audio/mpeg" length="{bytes}">, pubDate
  → Cache XML in Redis: "podcast_rss_{show_id}" TTL 15 min
  → Clear cache on episode publish/update

STEP 3 — CONTROLLERS
PodcastShowController: index, create, store (with artwork upload), edit, update, destroy
PodcastEpisodeController:
  index(Show), create(Show), store (audio upload → save to private storage),
  edit, update, destroy (delete record + audio file)
  publish(Episode): set status=published, published_at=now, clear RSS cache
  stream(Episode): stream audio file (auth required if show not public, else open)
  download(Episode): stream with Content-Disposition: attachment (if allow_episode_download)

PodcastAiController (all POST):
  script (stream), showNotes (stream), titles (JSON), guestBio (stream)

PublicPodcastController (no auth):
  show(string $slug): Inertia 'Podcast/Public/Show'
  episode(string $slug, Episode): Inertia 'Podcast/Public/Episode'
  rss(string $slug): return XML (Content-Type: application/rss+xml, Cache-Control: public max-age=900)
  trackPlay(Episode): POST → Redis INCR "play_{episode_id}" (hourly cron syncs to DB)

EmbedController: player(string $slug) → minimal Inertia page for iframe

STEP 4 — SCHEDULED JOB
SyncPlayCountsJob (hourly):
  → Scan Redis keys matching "play_*" → update podcast_episodes.play_count += Redis value → delete key

PublishScheduledEpisodesJob (every minute):
  → Query episodes where status=scheduled AND published_at <= now → set published=true → clear RSS cache

STEP 5 — VUE PAGES
resources/js/Pages/Podcast/Shows.vue:
  - Show cards: artwork, title, episode count, RSS link, "Manage Episodes" button

resources/js/Pages/Podcast/Episodes.vue:
  - Table: episode number, title, duration, status badge, play count, scheduled date
  - "New Episode" + "AI Tools" dropdown

resources/js/Pages/Podcast/EpisodeCreate.vue:
  Tabs:
    Content: title, description, show notes (Tiptap) — each with "Write with AI" button
    Audio: drag-drop MP3 upload OR external URL input, WaveSurfer preview after upload
    Settings: episode/season number, scheduled publish date

resources/js/Pages/Podcast/ScriptEditor.vue:
  - Topic, duration slider, format selector, host name → "Generate Script" → streaming into Tiptap
  - "Save to Episode" button, "Export as DOCX" button

resources/js/Pages/Podcast/Public/Show.vue (no auth):
  - Show artwork + title + description
  - Episode list: number, title, duration, date, play button
  - Subscribe RSS button

resources/js/Pages/Podcast/Public/Episode.vue (no auth):
  - WaveSurfer player (load from /podcast/{slug}/{episode}/stream)
  - Episode metadata + show notes (rendered markdown)
  - Download button (if allowed)

resources/js/Pages/Podcast/Embed/Player.vue (iframe, no MakeAI branding):
  - Compact: show artwork + episode list + HTML5 audio player
  - postMessage to parent for height resize

STEP 6 — ROUTES
Route::middleware(['auth', 'addon.active:ai-podcast'])->prefix('podcast')->group(function () {
    Route::resource('shows', PodcastShowController::class)->names('addon.podcast.shows.*');
    Route::resource('shows.episodes', PodcastEpisodeController::class)->names('addon.podcast.episodes.*');
    Route::post('shows/{show}/episodes/{episode}/publish', ...)->name('addon.podcast.episodes.publish');
    Route::get('shows/{show}/episodes/{episode}/stream', ...)->name('addon.podcast.stream');
    Route::prefix('ai')->group(function () {
        Route::post('/script', ...); Route::post('/show-notes', ...);
        Route::post('/titles', ...); Route::post('/guest-bio', ...);
    });
});
// Public routes (no auth):
Route::prefix('podcast')->group(function () {
    Route::get('/{slug}', ...)->name('addon.podcast.public.show');
    Route::get('/{slug}/feed.xml', ...)->name('addon.podcast.rss');
    Route::get('/{slug}/{episode:id}', ...)->name('addon.podcast.public.episode');
    Route::post('/{slug}/{episode:id}/play', ...)->name('addon.podcast.track-play');
    Route::get('/{slug}/embed/player', ...)->name('addon.podcast.embed');
});

STEP 7 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "max_shows_per_user", "type": "integer", "default": 5 },
    { "key": "max_audio_size_mb", "type": "integer", "default": 200 },
    { "key": "allow_episode_download", "type": "boolean", "default": true },
    { "key": "credits_per_script", "type": "integer", "default": 5 },
    { "key": "credits_per_show_notes", "type": "integer", "default": 2 }
  ]
}

STEP 8 — CHECKLIST
- [ ] Audio always streamed via controller — never expose direct storage path
- [ ] RSS: validate output with W3C Feed Validator before shipping
- [ ] Episode slug from Str::slug(title), unique within show
- [ ] Audio upload: validate mime (audio/mpeg, audio/mp4, audio/ogg only)
- [ ] play_count: use Redis INCR (non-blocking) — cron syncs hourly to DB
- [ ] WaveSurfer from unpkg.com (CSP allowlist) — same approach as Voice Studio addon
```

**New tables:** `podcast_shows`, `podcast_episodes`, `podcast_scripts`

---

### Priority 3 — Core Enhancement Addons

---

#### ADDON 29: AI Playground
**Slug:** `ai-playground`
**Price:** Free / bundled | **License required:** Regular

**What it does:**
Raw model testing sandbox for power users. Compare two AI models side-by-side with full parameter control. No tool templates — direct prompt access. "Save as AI Tool" packages current prompts into a new AI tool record.

**Features:**
- Two independent panels: each with provider + model selector, system prompt, parameters
- Parameters: temperature, max tokens, top_p, presence/frequency penalty
- Single shared user message input → "Run Both" fires both simultaneously
- Side-by-side streaming output with token count + estimated cost per panel
- Session history: last 20 prompts in Pinia + localStorage (no DB)
- "Save as AI Tool" button: pre-fills AI Tool create form with current system prompt
- Share: stores prompt + params + response in Redis (7-day TTL), returns public snapshot URL
- Sync panels toggle: shares system prompt across both panels

**Step-by-step build prompt for DeepSeek:**

```
ADDON: ai-playground | MakeAI Laravel 12 + Vue 3 + Inertia + Tailwind v4
NOTE: No DB migrations needed.

STEP 1 — CONTROLLER: PlaygroundController
index(): Inertia 'Playground/Index' with available providers + models list

run(Request $request): StreamedResponse
  → Validate: provider, model, messages (array), temperature (0-2), max_tokens, top_p
  → Call TokenGuard to check user credit limit
  → Deduct estimated credits before streaming (ceil(estimated_input_tokens / 1000) * rate)
  → $this->ai->stream(new CompletionRequest(provider: $provider, model: $model, ... + model + parameters
  → Stream response → X-Accel-Buffering: no
  → After stream: record actual token usage; adjust credit balance (refund difference)

share(Request $request): JsonResponse
  → Validate: prompt, params, response (truncated to 10k chars)
  → Store in Redis: "playground_share_{uuid}" → JSON payload → TTL 604800 (7 days)
  → Return { url: "/playground/s/{uuid}" }

showShare(string $uuid): Response
  → Redis get "playground_share_{uuid}" → if null: 404
  → Inertia 'Playground/Share' with snapshot data

STEP 2 — VUE PAGE: resources/js/Pages/Playground/Index.vue
Layout: two-column split (each ~50% wide, with a divider)

Each panel (left + right):
  - Header: Provider dropdown → Model dropdown (dynamically filtered), Sync toggle (icon button)
  - Collapsible Parameters bar: Temperature slider (0–2), Max Tokens input, Top P slider
  - System Prompt textarea (collapsible, shared if sync enabled)
  - Output area: streaming text display
  - Output footer: token count (input/output), estimated cost, Copy button

Shared area (between panels or above both):
  - User Message textarea (shared input)
  - "Run Both" button (fires two simultaneous fetch() streams, not sequential)
  - History dropdown (last 20 from Pinia — restore to panels on select)
  - Clear All button

Bottom actions (left panel, or shared):
  - "Save as AI Tool" → navigate to /admin/ai-tools/create with system prompt pre-filled in query string
  - "Share Snapshot" → POST /playground/share → copy URL to clipboard

Pinia store (usePlaygroundStore):
  State: leftPanel { provider, model, systemPrompt, params, output, tokens, cost, streaming }
         rightPanel { same }
         sharedMessage, syncPanels, history[]
  History: push on every "Run Both" → persist to localStorage via plugin
  Actions: runPanel(side), clearPanel(side), loadHistory(index)

STEP 3 — ROUTES
Route::middleware(['auth', 'addon.active:ai-playground'])->prefix('playground')->group(function () {
    Route::get('/', [PlaygroundController::class, 'index'])->name('addon.playground.index');
    Route::post('/run', [PlaygroundController::class, 'run'])->name('addon.playground.run');
    Route::post('/share', [PlaygroundController::class, 'share'])->name('addon.playground.share');
});
Route::get('/playground/s/{uuid}', [PlaygroundController::class, 'showShare'])
    ->name('addon.playground.share.show');

STEP 4 — ADDON.JSON SETTINGS
{
  "settings": [
    { "key": "credits_per_1k_tokens", "type": "integer", "default": 1, "label": "Credits per 1K tokens" },
    { "key": "max_tokens_cap", "type": "integer", "default": 4000, "label": "Max tokens per run" }
  ]
}

STEP 5 — CHECKLIST
- [ ] Both panels run with two truly independent fetch() calls — NOT Promise.all of one request
- [ ] Credit deduction: estimate before stream, adjust actual after (never charge more than actual)
- [ ] Share snapshots in Redis only (no DB) — document 7-day expiry to users
- [ ] History persisted in localStorage via Pinia persist plugin — survives page reload
- [ ] "Save as AI Tool" uses query params to pre-fill form, does NOT auto-create the tool
- [ ] Sync toggle: when enabled, writing to either system prompt textarea updates both panels
```

**New tables:** None

---

*MakeAI Addon Development Guide v2.0*
*Addons covered: 01–29 (18 original + 11 new)*
*Reference: AI_SaaS_Master_Prompt.md Part 04 (Foundation Layer — Addon System) for core integration details*
