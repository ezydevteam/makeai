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
  "envato_item_id": null,
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

# ADDON 04: AI Image Editor — Implementation Guide

> **Slug:** `ai-image-editor`
> **Queue:** `media` (all edit jobs — synchronous-ish, fast APIs)
> **AI engine:** External provider APIs via `Http` facade — Stability AI (inpainting/outpainting/style),
>   Replicate (Real-ESRGAN upscaler), Remove.bg + Clipdrop (background removal),
>   `laravel/ai` via `AiService` NOT used (no text generation)
> **Canvas library:** **Fabric.js** — loaded client-side, handles brush masking + text overlay
> **Extends:** Core image generator output — images saved in core `generated_images` table;
>   this addon adds edit operations on top of those images
> **No polling:** All API calls complete synchronously (< 30s) — no PollStatus pattern needed

---

## WHAT THIS ADDON BUILDS

A canvas-based post-processing suite that opens any image from the core image library and applies
AI-powered edits. Eight edit operations across three categories:

**AI Operations** (provider API calls, deduct credits):
- Inpainting — brush over a region → AI regenerates it
- Outpainting — extend canvas edges in any direction
- Background removal — one-click transparent PNG
- Upscaling — 4× resolution via Real-ESRGAN
- Style transfer — apply art style to a photo
- Object removal — brush over object → fill with background

**Local Operations** (client-side or PHP GD — free, no credits):
- Color correction — brightness/contrast/saturation/hue sliders (PHP GD)
- Text overlay — font/color/size/position on canvas (Fabric.js client-side)

Each edit creates an `ie_edits` row (history) so users can undo/redo by reverting to any prior version.

---

## ARCHITECTURE OVERVIEW

```
User opens any image from image library (or uploads new)
        │
        ▼
Editor canvas (Fabric.js) loads image
  ├── User draws mask brush / adjusts sliders / types text
  ├── Clicks apply operation
        │
        ▼
ImageEditorController::apply()
  → validates, deducts credits, creates ie_edits row (status: processing)
  → dispatches ApplyImageEdit job (queue: media)
        │
        ▼
ApplyImageEdit job:
  → ImageEditorService::apply($edit)
       └── routes to correct provider client by operation type
           ├── StabilityClient   (inpainting, outpainting, style transfer)
           ├── ReplicateClient   (upscaler)
           ├── RemoveBgClient    (background removal)
           ├── ClipdropClient    (object removal fallback)
           └── GdEditService     (color correction — no API)
  → saves output file to storage
  → updates ie_edits: status=completed, output_path
  → broadcasts ImageEditCompleted (Reverb) to user
        │
        ▼
Frontend: receives broadcast / polls status
  → loads new image version into canvas
  → add to edit history timeline
```

---

## STEP-BY-STEP BUILD ORDER

```
Step 1  → addon.json + AddonServiceProvider
Step 2  → Migrations (2 tables)
Step 3  → Models + Relationships
Step 4  → Seeder
Step 5  → Provider client classes (Stability, Replicate, Remove.bg, Clipdrop)
Step 6  → GdEditService (local color correction)
Step 7  → ImageEditorService (router + credit logic)
Step 8  → ApplyImageEdit job + ImageEditCompleted event
Step 9  → Controllers + FormRequests
Step 10 → Routes
Step 11 → Vue Pages (Editor, Library widget, Admin Settings)
Step 12 → Pest Tests
```

---

## STEP 1 — addon.json + AddonServiceProvider

### DEEPSEEK PROMPT 1

```
You are building an addon for MakeAI (Laravel 13 + Vue 3 + Inertia.js).
Create two files for ADDON 04: AI Image Editor.

━━━ FILE 1: addons/ai-image-editor/addon.json ━━━

{
  "name": "AI Image Editor",
  "slug": "ai-image-editor",
  "version": "1.0.0",
  "description": "Post-processing suite for AI images: inpainting, outpainting, background removal, upscaling, style transfer, object removal, color correction, and text overlay.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 1,
  "requires_pro": false,
  "admin_menu": [
    { "parent": "Settings", "label": "Image Editor", "route": "addon.ie.admin.settings", "icon": "ti-photo-edit", "permission": "addon.ie.settings" }
  ],
  "settings": [
    { "key": "enabled",                       "type": "boolean",  "label": "Enable Image Editor",                  "default": true },
    { "key": "inpaint_provider",              "type": "select",   "label": "Inpainting Provider",                  "options": ["stability","replicate"],      "default": "stability" },
    { "key": "outpaint_provider",             "type": "select",   "label": "Outpainting Provider",                 "options": ["stability","replicate"],      "default": "stability" },
    { "key": "bg_remove_provider",            "type": "select",   "label": "Background Removal Provider",          "options": ["remove_bg","clipdrop"],       "default": "remove_bg" },
    { "key": "upscale_provider",              "type": "select",   "label": "Upscaling Provider",                   "options": ["replicate"],                  "default": "replicate" },
    { "key": "style_provider",                "type": "select",   "label": "Style Transfer Provider",              "options": ["stability","replicate"],      "default": "stability" },
    { "key": "object_remove_provider",        "type": "select",   "label": "Object Removal Provider",              "options": ["stability","clipdrop"],       "default": "stability" },
    { "key": "stability_api_key",             "type": "encrypted","label": "Stability AI API Key",                 "default": null },
    { "key": "replicate_api_key",             "type": "encrypted","label": "Replicate API Key",                    "default": null },
    { "key": "remove_bg_api_key",             "type": "encrypted","label": "Remove.bg API Key",                    "default": null },
    { "key": "clipdrop_api_key",              "type": "encrypted","label": "Clipdrop API Key",                     "default": null },
    { "key": "credits_inpaint",               "type": "integer",  "label": "Credits: inpainting",                  "default": 15 },
    { "key": "credits_outpaint",              "type": "integer",  "label": "Credits: outpainting",                 "default": 15 },
    { "key": "credits_bg_remove",             "type": "integer",  "label": "Credits: background removal",          "default": 5 },
    { "key": "credits_upscale",               "type": "integer",  "label": "Credits: upscaling (4×)",              "default": 20 },
    { "key": "credits_style_transfer",        "type": "integer",  "label": "Credits: style transfer",              "default": 20 },
    { "key": "credits_object_remove",         "type": "integer",  "label": "Credits: object removal",              "default": 15 },
    { "key": "credits_color_correction",      "type": "integer",  "label": "Credits: color correction",            "default": 0 },
    { "key": "credits_text_overlay",          "type": "integer",  "label": "Credits: text overlay",                "default": 0 },
    { "key": "max_input_size_mb",             "type": "integer",  "label": "Max input image size (MB)",            "default": 10 },
    { "key": "max_output_dimension",          "type": "integer",  "label": "Max output dimension (px)",            "default": 4096 },
    { "key": "history_limit_per_image",       "type": "integer",  "label": "Max edit history entries per image",   "default": 20 },
    { "key": "auto_save_to_library",          "type": "boolean",  "label": "Auto-save edited images to library",   "default": true }
  ],
  "permissions": [
    { "slug": "addon.ie.use",      "name": "Use Image Editor",          "group": "Image Editor" },
    { "slug": "addon.ie.settings", "name": "Manage Image Editor Settings", "group": "Image Editor" }
  ],
  "hooks": []
}

━━━ FILE 2: addons/ai-image-editor/AddonServiceProvider.php ━━━

Namespace: Addons\AiImageEditor

In register():
  Bind singletons: StabilityClient, ReplicateClient, RemoveBgClient, ClipdropClient,
                   GdEditService, ImageEditorService

In boot() — only if is_addon_active('ai-image-editor'):
  - Load routes: routes/web.php
  - Load migrations
  - Share via Inertia::share('imageEditor', fn() => [
        'enabled'    => addon_setting('ai-image-editor', 'enabled', true),
        'creditCosts' => [
            'inpaint'          => addon_setting('ai-image-editor', 'credits_inpaint', 15),
            'outpaint'         => addon_setting('ai-image-editor', 'credits_outpaint', 15),
            'bg_remove'        => addon_setting('ai-image-editor', 'credits_bg_remove', 5),
            'upscale'          => addon_setting('ai-image-editor', 'credits_upscale', 20),
            'style_transfer'   => addon_setting('ai-image-editor', 'credits_style_transfer', 20),
            'object_remove'    => addon_setting('ai-image-editor', 'credits_object_remove', 15),
            'color_correction' => addon_setting('ai-image-editor', 'credits_color_correction', 0),
            'text_overlay'     => addon_setting('ai-image-editor', 'credits_text_overlay', 0),
        ],
    ])
    NEVER share API keys.

  - Hook: inject "Edit Image" button into core image library view
    AddonHook::on('makeai.image.card.actions', fn($image) => [
        'label' => translate('Edit Image'),
        'route' => route('addon.ie.user.editor', ['image' => $image->ulid ?? $image->id]),
        'icon'  => 'ti-photo-edit',
    ])

No scheduled jobs for this addon.
```

---

## STEP 2 — Migrations (2 tables)

### DEEPSEEK PROMPT 2

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create 2 migration files in addons/ai-image-editor/database/migrations/.
Tables prefixed ie_. Standard timestamps.

━━━ MIGRATION 1: create_ie_sessions_table ━━━
(Tracks the current working canvas for a user — one per user at a time)

ie_sessions
  id
  ulid              char(26) UNIQUE NOT NULL
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  source_type       enum('generated','uploaded','url') DEFAULT 'generated'
  source_image_id   bigint UNSIGNED NULL       -- FK → generated_images.id (if from core library)
  source_path       varchar(500) NOT NULL      -- storage path of the original input image
  source_url        varchar(500) NULL          -- public URL of source
  current_path      varchar(500) NOT NULL      -- storage path of current working version
  current_url       varchar(500) NULL          -- public URL of current version
  width             int UNSIGNED NULL           -- pixels
  height            int UNSIGNED NULL
  format            varchar(10) DEFAULT 'png'  -- 'png' | 'jpg' | 'webp'
  last_operation    varchar(50) NULL
  created_at, updated_at

  UNIQUE (user_id)   -- one active session per user at a time
  INDEX (source_image_id)

━━━ MIGRATION 2: create_ie_edits_table ━━━
(History of every edit operation applied in a session)

ie_edits
  id
  ulid              char(26) UNIQUE NOT NULL
  ie_session_id     bigint UNSIGNED NOT NULL FK → ie_sessions.id ON DELETE CASCADE
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  operation         enum(
                      'inpaint','outpaint','bg_remove','upscale',
                      'style_transfer','object_remove',
                      'color_correction','text_overlay'
                    ) NOT NULL
  status            enum('queued','processing','completed','failed') DEFAULT 'queued'
  provider          varchar(30) NULL            -- 'stability','replicate','remove_bg','clipdrop','local'
  input_path        varchar(500) NOT NULL       -- image state BEFORE this edit
  output_path       varchar(500) NULL           -- image state AFTER (null until completed)
  output_url        varchar(500) NULL
  mask_path         varchar(500) NULL           -- brush mask PNG (for inpaint/object_remove)
  params            json NULL                   -- operation-specific params (prompt, strength, etc.)
  credits_deducted  decimal(10,4) DEFAULT 0
  error_message     text NULL
  version_number    smallint UNSIGNED DEFAULT 1 -- sequential within session (1, 2, 3...)
  is_current        boolean DEFAULT false       -- is this the active version?
  completed_at      timestamp NULL
  created_at, updated_at

  INDEX (ie_session_id, version_number)
  INDEX (user_id, status)
  INDEX (is_current)

Add FK: ie_edits.ie_session_id → ie_sessions.id ON DELETE CASCADE
```

---

## STEP 3 — Models + Relationships

### DEEPSEEK PROMPT 3

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create 2 Eloquent models in addons/ai-image-editor/app/Models/.
Namespace: Addons\AiImageEditor\Models

━━━ IeSession.php ━━━
- $fillable: ulid, user_id, source_type, source_image_id, source_path, source_url,
             current_path, current_url, width, height, format, last_operation
- $casts: width → integer, height → integer
- $appends: ['dimensions_label']
- Relationships:
    belongsTo(User::class)
    hasMany(IeEdit::class)
- Scopes:
    scopeForUser($q, int $userId) → where('user_id', $userId)
- Accessor: getDimensionsLabelAttribute() → "{$this->width} × {$this->height}px"
- Method: currentEdit(): ?IeEdit → $this->edits()->where('is_current', true)->latest()->first()
- Method: nextVersionNumber(): int → ($this->edits()->max('version_number') ?? 0) + 1
- Boot: on creating → generate ULID

━━━ IeEdit.php ━━━
- $fillable: ulid, ie_session_id, user_id, operation, status, provider, input_path,
             output_path, output_url, mask_path, params, credits_deducted,
             error_message, version_number, is_current, completed_at
- $casts: params → array, credits_deducted → float, is_current → boolean,
          completed_at → datetime, version_number → integer
- $appends: ['operation_label', 'can_revert_to']
- Relationships:
    belongsTo(IeSession::class)
    belongsTo(User::class)
- Scopes:
    scopeCompleted($q) → where('status', 'completed')
    scopeCurrent($q) → where('is_current', true)
- Accessor: getOperationLabelAttribute() → human-readable map of operation
- Accessor: getCanRevertToAttribute(): bool → status === 'completed' && !is_current
- Boot: on creating → generate ULID
- Static: markAsCurrent(IeEdit $edit): void
    DB::transaction(function() use ($edit) {
        IeEdit::where('ie_session_id', $edit->ie_session_id)->update(['is_current' => false])
        $edit->update(['is_current' => true])
        $edit->session->update([
            'current_path'   => $edit->output_path,
            'current_url'    => $edit->output_url,
            'last_operation' => $edit->operation,
        ])
    })
```

---

## STEP 4 — Seeder

### DEEPSEEK PROMPT 4

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create: addons/ai-image-editor/database/seeders/ImageEditorSeeder.php
Namespace: Addons\AiImageEditor\Database\Seeders

This addon has no demo data requirements — the editor opens on images
from the core image library, which are seeded by core seeders.

The seeder should only verify/document prerequisites:

public function run(): void
{
    // No demo data needed — AI Image Editor works with existing images
    // from the core generated_images library.
    //
    // Prerequisites for testing:
    // 1. Core image generator must be configured (at least one provider API key set)
    // 2. At least one image must exist in the user's image library
    // 3. For AI operations: at least one of these API keys must be set in Admin → Settings:
    //    - Stability AI key (inpainting, outpainting, style transfer, object removal)
    //    - Replicate key (upscaling, SD-based inpainting fallback)
    //    - Remove.bg key (background removal)
    //    - Clipdrop key (alternative bg removal, object removal)
    //
    // Color correction and text overlay work without any API keys.
    //
    // To open the editor: navigate to image library → click "Edit Image" on any image.
}
```

---

## STEP 5 — Provider Client Classes

### DEEPSEEK PROMPT 5

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create 4 provider client classes in:
  addons/ai-image-editor/app/Services/Providers/

Namespace: Addons\AiImageEditor\Services\Providers

All use Laravel Http facade. All throw ImageEditException on API errors.
All API keys from addon_setting('ai-image-editor', 'key_name').
Timeout: 60s (image processing takes longer than text).

━━━ BASE: ImageEditException.php ━━━
class ImageEditException extends \RuntimeException {}

━━━ StabilityClient.php ━━━
API base: https://api.stability.ai/v2beta
Auth: Authorization: Bearer {stability_api_key}

━━━ inpaint(string $imagePath, string $maskPath, string $prompt, array $options = []): string ━━━
(Returns storage path of the output PNG)

POST /stable-image/edit/inpaint (multipart/form-data)
Fields:
  image:     binary — the source image (PNG/JPG, max 10MB)
  mask:      binary — grayscale mask PNG (white=regenerate, black=keep)
  prompt:    string (required)
  negative_prompt: string (optional, from $options)
  output_format: 'png'
  seed:      integer (optional, for reproducibility)
  strength:  float 0.0–1.0 (default 0.75) — how strongly to inpaint
Response: binary PNG (Content-Type: image/png)
Save to: storage/app/image-editor/{user_id}/edits/{ulid}_inpaint.png

━━━ outpaint(string $imagePath, array $expand, string $prompt = '', array $options = []): string ━━━
(expand = ['left'=>0, 'right'=>0, 'up'=>0, 'down'=>0] in pixels)

POST /stable-image/edit/outpaint (multipart/form-data)
Fields:
  image:   binary
  left, right, up, down: integers (pixels to expand each edge, max 2000 each)
  prompt:  string (optional — if empty, Stability extrapolates from the image)
  output_format: 'png'
Response: binary PNG (expanded canvas)

━━━ styleTransfer(string $contentPath, string $stylePath, float $fidelity = 0.5): string ━━━
POST /stable-image/control/style (multipart/form-data)
Fields:
  image:   binary — content image
  style_image: binary — style reference image
  fidelity: float 0.0–1.0 (how closely to follow style)
  output_format: 'png'
Response: binary PNG

━━━ objectRemove(string $imagePath, string $maskPath): string ━━━
Reuse inpaint() with prompt = 'remove object, fill with background, seamless'
(Stability inpainting is the best approach for object removal)
Return the output path.

━━━ ReplicateClient.php ━━━
API base: https://api.replicate.com/v1
Auth: Authorization: Token {replicate_api_key}

Replicate is asynchronous (returns a prediction ID → poll for completion).
Max poll: 30 attempts × 3s = 90s. If exceeded: throw ImageEditException('Upscale timed out').

━━━ upscale(string $imagePath, int $scaleFactor = 4): string ━━━
Submit prediction:
  POST /predictions
  Body: {
    version: "42fed1c4974146d4d2414e2be2c5277c7fcf05fcc3a73abf41610695738c1d7b",
             // nightmareai/real-esrgan:latest — check Replicate for current version hash
    input: {
      image: "data:image/{ext};base64,{base64_image}",
      scale: $scaleFactor,    // 2 or 4
      face_enhance: false
    }
  }
  Returns: { id: "prediction_id", status: "starting" }

Poll: GET /predictions/{id}
  Until status = 'succeeded' | 'failed' | 'canceled'
  Sleep 3s between polls

On succeeded: output[0] = URL to upscaled image
Download to storage: Http::withOptions(['sink' => $tempPath])->get($url)
Move to storage path, return path.

━━━ sdInpaint(string $imagePath, string $maskPath, string $prompt): string ━━━
(Fallback for inpainting when Stability key not set)
  POST /predictions
  Body: {
    version: "stability-ai/stable-diffusion-inpainting:...",  // latest Replicate SD inpainting hash
    input: { image: $base64, mask: $base64Mask, prompt: $prompt, num_outputs: 1 }
  }
  Poll until done, download output, return path.

━━━ RemoveBgClient.php ━━━
API: https://api.remove.bg/v1.0/removebg
Auth: X-Api-Key: {remove_bg_api_key}

━━━ removeBackground(string $imagePath): string ━━━
POST /removebg (multipart/form-data)
Fields:
  image_file: binary — source image
  size: 'auto'
  format: 'png'   // always PNG for transparency
  bg_color: ''    // transparent
Response: binary PNG (background removed)
Save to storage, return path.

━━━ ClipdropClient.php ━━━
API base: https://clipdrop-api.co
Auth: x-api-key: {clipdrop_api_key}

━━━ removeBackground(string $imagePath): string ━━━
POST /remove-background/v1 (multipart/form-data)
Fields: image_file: binary
Response: binary PNG

━━━ removeObject(string $imagePath, string $maskPath): string ━━━
POST /cleanup/v1 (multipart/form-data)
Fields:
  image_file: binary — source image
  mask_file:  binary — grayscale mask (white=remove, black=keep)
Response: binary PNG (object removed, background filled)

HELPER (all clients share this):
private function saveToStorage(string $binaryContent, string $storagePath): string
  Storage::put($storagePath, $binaryContent)
  return $storagePath

private function imageToBase64(string $storagePath): string
  $ext = pathinfo($storagePath, PATHINFO_EXTENSION)
  $bytes = Storage::get($storagePath)
  return 'data:image/' . $ext . ';base64,' . base64_encode($bytes)

RULES:
- addon_setting('ai-image-editor', 'key') for all API keys
- Http::timeout(60) on all image requests
- Throw ImageEditException on non-2xx
- NEVER log binary image data
```

---

## STEP 6 — GdEditService (Local Color Correction)

### DEEPSEEK PROMPT 6

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create: addons/ai-image-editor/app/Services/GdEditService.php
Namespace: Addons\AiImageEditor\Services

PHP GD-based local image editing. No API calls. No credits (unless admin sets credits_color_correction > 0).
Requires PHP GD extension (standard in PHP 8.3).

━━━ colorCorrection(string $inputPath, array $params, string $outputPath): string ━━━

$params may include:
  brightness:  int -100 to 100 (PHP imagefilter uses -255 to 255, scale it: $b * 2.55)
  contrast:    int -100 to 100 (PHP imagefilter uses -100 to 100, keep as-is)
  saturation:  float -100 to 100 (custom implementation — adjust HSL saturation)
  hue:         int 0 to 360 (custom implementation — rotate hue)
  sharpness:   int 0 to 100 (imageconvolution with sharpen kernel)

Implementation:
  $abs = storage_path('app/' . $inputPath)
  $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION))

  // Load image
  $img = match($ext) {
    'jpg', 'jpeg' => imagecreatefromjpeg($abs),
    'png'         => imagecreatefrompng($abs),
    'webp'        => imagecreatefromwebp($abs),
    default       => throw new \InvalidArgumentException("Unsupported format: $ext")
  }
  if (!$img) throw new \RuntimeException("Failed to load image: $abs")

  // Apply brightness
  if (isset($params['brightness']) && $params['brightness'] !== 0) {
    imagefilter($img, IMG_FILTER_BRIGHTNESS, (int)($params['brightness'] * 2.55))
  }

  // Apply contrast (negate value: PHP's contrast is inverted from UI expectation)
  if (isset($params['contrast']) && $params['contrast'] !== 0) {
    imagefilter($img, IMG_FILTER_CONTRAST, -(int)$params['contrast'])
  }

  // Apply saturation + hue via HSL conversion (custom pixel loop)
  if ((!empty($params['saturation']) && $params['saturation'] !== 0)
      || (!empty($params['hue']) && $params['hue'] !== 0)) {
    $img = $this->applyHslAdjustments($img, $params['saturation'] ?? 0, $params['hue'] ?? 0)
  }

  // Apply sharpness via unsharp mask convolution
  if (!empty($params['sharpness']) && $params['sharpness'] > 0) {
    $strength = $params['sharpness'] / 100
    $sharpen = [
      [0,  -1 * $strength,  0],
      [-1 * $strength, 1 + 4 * $strength, -1 * $strength],
      [0,  -1 * $strength,  0],
    ]
    imageconvolution($img, $sharpen, 1, 0)
  }

  // Save output (always PNG to preserve quality)
  $absOutput = storage_path('app/' . $outputPath)
  imagepng($img, $absOutput)
  imagedestroy($img)
  return $outputPath

━━━ private applyHslAdjustments(GdImage $img, float $satDelta, float $hueDelta): GdImage ━━━

For each pixel (imagecolorat loop):
  Extract R, G, B → convert to HSL (custom rgbToHsl / hslToRgb helpers)
  Adjust: H += $hueDelta / 360; S = clamp(S + $satDelta / 100, 0, 1)
  Convert back to RGB → imagesetpixel

Note: pixel-by-pixel loop is slow for large images. Document max 2048×2048 for color correction,
or use imagick extension if available (check extension_loaded('imagick') and use faster Imagick path).

━━━ textOverlay(string $inputPath, array $params, string $outputPath): string ━━━

$params:
  text:          string (required)
  font_size:     int (default 48)
  font_color:    string hex (default '#FFFFFF')
  x:             int pixels from left
  y:             int pixels from top
  font_path:     string (optional — path to TTF font file in public/fonts/)
  shadow:        bool (default false)
  shadow_color:  string hex (default '#000000')

Load image → use imagettftext() or imagestring() (if no TTF available)
Parse hex color to RGB → imagecolorallocate
If shadow: draw text at x+2, y+2 in shadow_color first
Draw text at x, y in font_color
Save as PNG, return output_path.

Note: imagettftext requires a TTF font file. Bundle one in addons/ai-image-editor/public/fonts/
(use a free-licensed font like Inter or Roboto). Provide 2-3 font choices via admin settings.
Fall back to imagestring() (built-in bitmap font) if TTF path not found.
```

---

## STEP 7 — ImageEditorService (Router + Credit Logic)

### DEEPSEEK PROMPT 7

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create: addons/ai-image-editor/app/Services/ImageEditorService.php
Namespace: Addons\AiImageEditor\Services

This service routes edit operations to the correct client and manages credits.

━━━ Constructor ━━━
public function __construct(
    private StabilityClient $stability,
    private ReplicateClient $replicate,
    private RemoveBgClient $removeBg,
    private ClipdropClient $clipdrop,
    private GdEditService $gd,
) {}

━━━ apply(IeEdit $edit): string (returns output storage path) ━━━

Routes by $edit->operation:

'inpaint':
  $provider = addon_setting('ai-image-editor', 'inpaint_provider', 'stability')
  $prompt   = $edit->params['prompt'] ?? 'fill region naturally'
  return match($provider) {
    'stability' => $this->stability->inpaint($edit->input_path, $edit->mask_path, $prompt, $edit->params),
    'replicate' => $this->replicate->sdInpaint($edit->input_path, $edit->mask_path, $prompt),
    default     => throw new ImageEditException("Unknown inpaint provider: $provider"),
  }

'outpaint':
  $provider = addon_setting('ai-image-editor', 'outpaint_provider', 'stability')
  $expand   = array_intersect_key($edit->params, array_flip(['left','right','up','down']))
  $prompt   = $edit->params['prompt'] ?? ''
  return match($provider) {
    'stability' => $this->stability->outpaint($edit->input_path, $expand, $prompt, $edit->params),
    default     => throw new ImageEditException("Outpainting only available with Stability AI"),
  }

'bg_remove':
  $provider = addon_setting('ai-image-editor', 'bg_remove_provider', 'remove_bg')
  return match($provider) {
    'remove_bg' => $this->removeBg->removeBackground($edit->input_path),
    'clipdrop'  => $this->clipdrop->removeBackground($edit->input_path),
    default     => throw new ImageEditException("Unknown bg_remove provider: $provider"),
  }

'upscale':
  // Only Replicate supported currently
  $scale = $edit->params['scale'] ?? 4
  return $this->replicate->upscale($edit->input_path, $scale)

'style_transfer':
  $stylePath = $edit->params['style_image_path'] ?? null
  if (!$stylePath) throw new ImageEditException('Style image is required for style transfer')
  return $this->stability->styleTransfer($edit->input_path, $stylePath, $edit->params['fidelity'] ?? 0.5)

'object_remove':
  if (!$edit->mask_path) throw new ImageEditException('Mask is required for object removal')
  $provider = addon_setting('ai-image-editor', 'object_remove_provider', 'stability')
  return match($provider) {
    'stability' => $this->stability->objectRemove($edit->input_path, $edit->mask_path),
    'clipdrop'  => $this->clipdrop->removeObject($edit->input_path, $edit->mask_path),
    default     => throw new ImageEditException("Unknown object_remove provider: $provider"),
  }

'color_correction':
  $outputPath = $this->buildOutputPath($edit, 'cc')
  return $this->gd->colorCorrection($edit->input_path, $edit->params, $outputPath)

'text_overlay':
  $outputPath = $this->buildOutputPath($edit, 'txt')
  return $this->gd->textOverlay($edit->input_path, $edit->params, $outputPath)

default:
  throw new ImageEditException("Unknown operation: {$edit->operation}")

━━━ getCreditsForOperation(string $operation): int ━━━
  Maps operation → addon_setting key → credit cost
  'inpaint'          → credits_inpaint
  'outpaint'         → credits_outpaint
  'bg_remove'        → credits_bg_remove
  'upscale'          → credits_upscale
  'style_transfer'   → credits_style_transfer
  'object_remove'    → credits_object_remove
  'color_correction' → credits_color_correction
  'text_overlay'     → credits_text_overlay
  Unknown            → 0

━━━ buildOutputPath(IeEdit $edit, string $suffix = ''): string ━━━
  'image-editor/' . $edit->user_id . '/' . $edit->ulid
  . ($suffix ? "_$suffix" : '') . '.png'

━━━ isProviderConfigured(string $operation): bool ━━━
  Returns true if the configured provider for this operation has its API key set.
  E.g. for 'inpaint' with stability provider → check addon_setting('stability_api_key') !== null
  For 'color_correction' and 'text_overlay': always true (local, no API key needed).
  Used by the UI to disable buttons for unconfigured operations.
```

---

## STEP 8 — ApplyImageEdit Job + Event

### DEEPSEEK PROMPT 8

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create 2 files in addons/ai-image-editor/app/Jobs/ and app/Events/.
Namespace: Addons\AiImageEditor\Jobs / Events

━━━ ApplyImageEdit.php ━━━
Queue: 'media'. Max attempts: 2. Backoff: [30, 120].

Constructor: __construct(public readonly int $editId)

handle(ImageEditorService $service):
  $edit = IeEdit::with('session.user')->find($this->editId)
  if (!$edit || $edit->status !== 'queued') return

  $edit->update(['status' => 'processing'])

  try {
    $outputPath = $service->apply($edit)

    $outputUrl = Storage::url($outputPath)

    // Determine output dimensions (use GD imagesx/imagesy or getimagesize)
    $absPath = storage_path('app/' . $outputPath)
    [$width, $height] = getimagesize($absPath) ?: [null, null]

    // Mark edit as completed + set as current version
    $edit->update([
      'status'       => 'completed',
      'output_path'  => $outputPath,
      'output_url'   => $outputUrl,
      'completed_at' => now(),
    ])
    IeEdit::markAsCurrent($edit)

    // Update session dimensions if they changed (e.g. upscale, outpaint)
    $edit->session->update([
      'width'  => $width  ?? $edit->session->width,
      'height' => $height ?? $edit->session->height,
    ])

    // Auto-save to core image library if enabled
    if (addon_setting('ai-image-editor', 'auto_save_to_library', true)) {
      $this->saveToLibrary($edit)
    }

    // Broadcast completion
    event(new ImageEditCompleted($edit))

  } catch (ImageEditException $e) {
    $edit->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)])
    // Refund credits
    if ($edit->credits_deducted > 0) {
      User::where('id', $edit->user_id)->increment('credits', $edit->credits_deducted)
      DB::table('credit_transactions')->insert([
        'user_id'       => $edit->user_id,
        'amount'        => $edit->credits_deducted,
        'balance_after' => $edit->session->user->credits,
        'type'          => 'refund',
        'description'   => 'Image edit failed — refund: ' . $edit->ulid,
        'created_at'    => now(),
      ])
    }
    event(new ImageEditCompleted($edit))  // broadcast failure too (UI needs to know)
  }

private function saveToLibrary(IeEdit $edit): void
  // Insert into core generated_images table (polymorphic ownership)
  DB::table('generated_images')->insert([
    'user_id'    => $edit->user_id,
    'path'       => $edit->output_path,
    'url'        => $edit->output_url,
    'source'     => 'image_editor',
    'prompt'     => 'Edited: ' . $edit->operation . ($edit->params['prompt'] ?? ''),
    'width'      => $edit->session->width,
    'height'     => $edit->session->height,
    'created_at' => now(),
    'updated_at' => now(),
  ])
  // Note: column names may vary — check actual generated_images table schema
  // This is a best-effort insert; wrap in try-catch to avoid breaking the edit on schema mismatch

failed(Throwable $e):
  IeEdit::where('id', $this->editId)->update([
    'status'        => 'failed',
    'error_message' => Str::limit($e->getMessage(), 500),
  ])
  // Refund credits
  $edit = IeEdit::find($this->editId)
  if ($edit && $edit->credits_deducted > 0) {
    User::where('id', $edit->user_id)->increment('credits', $edit->credits_deducted)
  }

━━━ ImageEditCompleted.php (Event) ━━━

class ImageEditCompleted implements ShouldBroadcast
{
    use InteractsWithSockets

    public function __construct(public IeEdit $edit) {}

    public function broadcastOn(): PrivateChannel
        return new PrivateChannel('user.' . $this->edit->user_id)

    public function broadcastAs(): string
        return 'ImageEditCompleted'

    public function broadcastWith(): array
        return [
            'edit_id'     => $this->edit->id,
            'edit_ulid'   => $this->edit->ulid,
            'operation'   => $this->edit->operation,
            'status'      => $this->edit->status,
            'output_url'  => $this->edit->output_url,
            'error'       => $this->edit->error_message,
            'version'     => $this->edit->version_number,
        ]
}
```

---

## STEP 9 — Controllers + FormRequests

### DEEPSEEK PROMPT 9

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create controllers in addons/ai-image-editor/app/Http/Controllers/.
Namespace: Addons\AiImageEditor\Http\Controllers

━━━ User/ImageEditorController ━━━
Middleware: ['auth', 'permission:addon.ie.use', 'addon.ie.enabled']

━━━ show(Request $request): InertiaResponse ━━━
Open or resume the editor for the current user.

Step 1: Resolve source image
  If $request->has('image'):
    $imageId = $request->image   // ulid or ID from core image library
    $generatedImage = DB::table('generated_images')
      ->where('user_id', auth()->id())
      ->where(fn($q) => $q->where('id', $imageId)->orWhere('ulid', $imageId))
      ->first()
    abort_if(!$generatedImage, 404)
    $sourcePath = $generatedImage->path
    $sourceType = 'generated'
    $sourceImageId = $generatedImage->id
  elseif $request->hasFile('upload'):
    Validate: upload file mimes:jpg,jpeg,png,webp max:{max_input_size_mb from settings}MB
    $sourcePath = $request->file('upload')->store('image-editor/' . auth()->id() . '/sources', 'local')
    $sourceType = 'uploaded'
    $sourceImageId = null
  else:
    // Resume existing session
    $session = IeSession::where('user_id', auth()->id())->latest()->first()
    abort_if(!$session, 404, 'No active editor session. Open an image from your library.')
    return $this->renderEditor($session)

Step 2: Create or replace session (one per user)
  [$width, $height] = getimagesize(storage_path('app/' . $sourcePath)) ?: [null, null]
  $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION))

  IeSession::where('user_id', auth()->id())->delete()  // clear previous session files? No — keep files, just replace session row
  $session = IeSession::create([
    'user_id'         => auth()->id(),
    'source_type'     => $sourceType,
    'source_image_id' => $sourceImageId ?? null,
    'source_path'     => $sourcePath,
    'source_url'      => Storage::url($sourcePath),
    'current_path'    => $sourcePath,
    'current_url'     => Storage::url($sourcePath),
    'width'           => $width,
    'height'          => $height,
    'format'          => $ext,
  ])

return $this->renderEditor($session)

private function renderEditor(IeSession $session): InertiaResponse
  $history = IeEdit::where('ie_session_id', $session->id)
    ->orderBy('version_number')
    ->get(['id','ulid','operation','status','output_url','version_number','is_current','created_at','error_message'])
  $operationsAvailable = $this->getAvailableOperations()
  return Inertia::render('AiImageEditor::User/Editor', compact('session','history','operationsAvailable'))

private function getAvailableOperations(): array
  $service = app(ImageEditorService::class)
  return collect(['inpaint','outpaint','bg_remove','upscale','style_transfer',
                  'object_remove','color_correction','text_overlay'])
    ->mapWithKeys(fn($op) => [$op => [
        'available' => $service->isProviderConfigured($op),
        'credits'   => $service->getCreditsForOperation($op),
    ]])->toArray()

━━━ apply(ImageEditRequest $request): JsonResponse ━━━
POST — creates edit job

Step 1: Load session
  $session = IeSession::where('user_id', auth()->id())->firstOrFail()

Step 2: Check credits
  $credits = app(ImageEditorService::class)->getCreditsForOperation($request->operation)
  if (auth()->user()->credits < $credits) {
    return response()->json(['error' => translate('Insufficient credits')], 402)
  }

Step 3: Handle mask upload (if provided)
  $maskPath = null
  if ($request->hasFile('mask')) {
    Validate: mask file mimes:png max:5120
    $maskPath = $request->file('mask')->store('image-editor/' . auth()->id() . '/masks', 'local')
  }

Step 4: Handle style image upload (for style_transfer)
  $stylePath = null
  if ($request->hasFile('style_image')) {
    $stylePath = $request->file('style_image')->store('image-editor/' . auth()->id() . '/styles', 'local')
  }

Step 5: Deduct credits
  if ($credits > 0) {
    deduct_credits(auth()->id(), $credits, 'Image edit: ' . $request->operation)
  }

Step 6: Create IeEdit row
  $edit = IeEdit::create([
    'ie_session_id'   => $session->id,
    'user_id'         => auth()->id(),
    'operation'       => $request->operation,
    'status'          => 'queued',
    'provider'        => $this->getProviderName($request->operation),
    'input_path'      => $session->current_path,
    'mask_path'       => $maskPath,
    'params'          => array_merge(
        $request->params ?? [],
        $stylePath ? ['style_image_path' => $stylePath] : []
    ),
    'credits_deducted'=> $credits,
    'version_number'  => $session->nextVersionNumber(),
  ])

Step 7: Dispatch job
  ApplyImageEdit::dispatch($edit->id)->onQueue('media')

  return response()->json([
    'edit_id'   => $edit->id,
    'edit_ulid' => $edit->ulid,
    'status'    => 'queued',
    'version'   => $edit->version_number,
  ])

━━━ status(IeEdit $edit): JsonResponse ━━━
  abort_if($edit->user_id !== auth()->id(), 403)
  return response()->json([
    'status'     => $edit->status,
    'output_url' => $edit->output_url,
    'error'      => $edit->error_message,
  ])

━━━ revert(IeEdit $edit): JsonResponse ━━━
  abort_if($edit->user_id !== auth()->id(), 403)
  abort_if(!$edit->can_revert_to, 422, translate('Cannot revert to this version'))
  IeEdit::markAsCurrent($edit)
  return response()->json(['output_url' => $edit->output_url, 'version' => $edit->version_number])

━━━ download(IeEdit $edit): StreamedResponse ━━━
  abort_if($edit->user_id !== auth()->id(), 403)
  abort_if($edit->status !== 'completed', 422)
  $filename = 'edited-' . $edit->operation . '-' . $edit->ulid . '.png'
  return Storage::download($edit->output_path, $filename)

━━━ saveToLibrary(IeEdit $edit): JsonResponse ━━━
  abort_if($edit->user_id !== auth()->id(), 403)
  abort_if($edit->status !== 'completed', 422)
  app(ApplyImageEdit::class)->saveToLibrary($edit)  // call the private method (make it public)
  return response()->json(['saved' => true])

━━━ Admin/ImageEditorSettingsController ━━━
Middleware: ['auth:admin', 'admin.permission:addon.ie.settings']

edit():
  $operationsStatus = collect([
    'inpaint','outpaint','bg_remove','upscale','style_transfer','object_remove'
  ])->mapWithKeys(fn($op) => [$op => app(ImageEditorService::class)->isProviderConfigured($op)])
  return Inertia 'AiImageEditor::Admin/Settings'
  props: settings (all ie_ settings), operationsStatus

update(ImageEditorSettingsRequest $request):
  foreach ($request->validated() as $key => $value)
    addon_setting_set('ai-image-editor', $key, $value)
  return back()->with('flash', 'saved')

━━━ FormRequests ━━━

ImageEditRequest:
  operation: required in:inpaint,outpaint,bg_remove,upscale,style_transfer,object_remove,color_correction,text_overlay
  params: nullable array
  params.prompt: required_if:operation,inpaint | max:1000
  params.prompt: nullable max:500                (for outpaint — optional)
  params.scale:  in:2,4                          (for upscale)
  params.left, params.right, params.up, params.down: nullable integer min:0 max:2000 (for outpaint)
  params.fidelity: nullable numeric min:0 max:1  (for style_transfer)
  params.brightness, params.contrast, params.saturation, params.hue, params.sharpness:
                 nullable numeric                 (for color_correction)
  params.text: required_if:operation,text_overlay | max:500
  params.font_size: nullable integer min:8 max:200
  params.font_color: nullable regex:/#[0-9a-fA-F]{6}/
  params.x, params.y: nullable integer min:0
  mask: nullable file mimes:png max:5120         (binary PNG from canvas brush)
  style_image: required_if:operation,style_transfer | file mimes:jpg,jpeg,png,webp max:5120

ImageEditorSettingsRequest: validate all settings fields per type.
```

---

## STEP 10 — Routes

### DEEPSEEK PROMPT 10

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create: addons/ai-image-editor/routes/web.php

━━━ USER ROUTES ━━━
Route::middleware(['web', 'auth', 'permission:addon.ie.use'])
  ->prefix('image-editor')
  ->name('addon.ie.user.')
  ->group(function () {

    // Open / resume editor
    Route::get('/',                     [ImageEditorController::class, 'show'])->name('editor')
    Route::post('/upload',              [ImageEditorController::class, 'show'])->name('upload')
      ->middleware('throttle:10,1')   // limit uploads

    // Edit operations
    Route::post('/apply',               [ImageEditorController::class, 'apply'])->name('apply')
      ->middleware('throttle:30,1')   // 30 edit operations per minute

    // Edit management
    Route::get('/edits/{edit}/status',  [ImageEditorController::class, 'status'])->name('edits.status')
    Route::post('/edits/{edit}/revert', [ImageEditorController::class, 'revert'])->name('edits.revert')
    Route::get('/edits/{edit}/download',[ImageEditorController::class, 'download'])->name('edits.download')
    Route::post('/edits/{edit}/save',   [ImageEditorController::class, 'saveToLibrary'])->name('edits.save')
  })

━━━ ADMIN ROUTES ━━━
Route::middleware(['web', 'auth:admin', 'admin.permission:addon.ie.settings'])
  ->prefix('admin/image-editor')
  ->name('addon.ie.admin.')
  ->group(function () {
    Route::get('settings',  [ImageEditorSettingsController::class, 'edit'])->name('settings')
    Route::put('settings',  [ImageEditorSettingsController::class, 'update'])
  })

Note: The 'apply' endpoint accepts multipart/form-data (mask + style_image file uploads).
FormData must be serialized from the canvas in the Vue layer (mask drawn via Fabric.js → toBlob → append to FormData).
```

---

## STEP 11 — Vue Pages

### DEEPSEEK PROMPT 11

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13 + Vue 3 + Inertia.js).

Create Vue pages in addons/ai-image-editor/resources/js/Pages/.
All: <script setup lang="ts">, TypeScript, Tabler Icons, Tailwind v4.
MakeAI design tokens. $t() for all strings. Never Options API.
Fabric.js loaded as npm package: import { Canvas, Image as FabricImage, ... } from 'fabric'
(Install: npm install fabric@6)

━━━ PAGE 1: User/Editor.vue — Main Canvas Editor ━━━

LAYOUT: Full-viewport two-panel layout (no sidebar nav — editor is immersive)
  LEFT PANEL (fixed 280px): Operation list + active tool controls
  CENTER: Canvas area (fills remaining space, scrollable if image larger than viewport)
  RIGHT PANEL (fixed 280px): Edit history + save/download actions

━━━ TOP BAR (full width) ━━━
  ← Back to Library  |  [Image dimensions]  |  Credit balance badge  |  [↓ Download] [💾 Save to Library]

━━━ LEFT PANEL — Operations ━━━

Grouped operation buttons:

  [AI Operations] — header
    Each operation: icon + label + credit badge + ⚠️ if not configured
    - 🎨 Inpaint       [15 cr]
    - 🔲 Outpaint      [15 cr]
    - ✂️ Remove BG     [ 5 cr]
    - 🔍 Upscale 4×   [20 cr]
    - 🖼️ Style Transfer[20 cr]
    - 🧹 Object Remove [15 cr]

  [Local Edits] — header (always available, no API key needed)
    - 🎨 Color Correct [ 0 cr]
    - 🔤 Text Overlay  [ 0 cr]

  Click operation → shows its parameter form in the lower LEFT PANEL:

  INPAINT FORM:
    Instruction: "Brush over the area you want to regenerate"
    Brush mode toggle (activates Fabric.js brush on canvas)
    Brush size slider (10–100px)
    Prompt textarea: "What to fill in?" (required)
    Negative prompt (collapsible, optional)
    [Clear Brush] [Apply Inpaint →] (disabled until brush strokes drawn)

  OUTPAINT FORM:
    Pixel controls (number inputs):
      ← Left [___] | Right [___] →
         Up [___]
        Down [___]
    Prompt (optional: "what should appear in the extended area")
    Preview: shows how canvas will expand (outline preview)
    [Apply Outpaint →]

  BACKGROUND REMOVAL FORM:
    "One-click — removes background and creates a transparent PNG"
    [Remove Background →]

  UPSCALE FORM:
    Scale: [2× ○] [4× ●]
    Estimated output size: {w*scale}×{h*scale}px
    Warning if output > max_output_dimension setting
    [Upscale Image →]

  STYLE TRANSFER FORM:
    Style image upload zone (drag-drop, max 5MB)
    Preview of uploaded style image
    Fidelity slider: [Low ←————→ High] (0.0–1.0)
    [Apply Style →]

  OBJECT REMOVAL FORM:
    "Brush over the object you want to remove"
    (same brush controls as inpaint)
    No prompt needed — fill automatically
    [Clear Brush] [Remove Object →]

  COLOR CORRECTION FORM:
    Brightness: slider -100 to 100 (default 0)
    Contrast:   slider -100 to 100
    Saturation: slider -100 to 100
    Hue:        slider 0 to 360
    Sharpness:  slider 0 to 100
    Live preview: debounced 500ms — apply preview via CSS filter (not actual edit)
    [Apply Color Correction →]

  TEXT OVERLAY FORM:
    Text input (required)
    Font size (number, 8–200)
    Font color (color picker)
    X position (number)
    Y position (number)
    [Place Text on Canvas] — adds text object to Fabric.js canvas (user drags it)
    [Apply Text Overlay →]

━━━ CENTER — Canvas ━━━

Fabric.js canvas element (full width of center panel, height proportional to image).

Canvas modes:
  - VIEW: default — pan/zoom with mouse wheel (transform CSS scale)
  - BRUSH: active when inpaint/object_remove brush mode on
    Fabric.js PencilBrush, color=white, opacity=0.7 on overlay layer
  - TEXT: text overlay mode — Fabric.js IText draggable

Status overlay (shown when edit is processing):
  Semi-transparent black overlay + spinner + "Applying {operation}..."
  Progress: show current operation name + estimated wait time per operation type

After edit completes:
  Animate canvas update: fade old → new image
  Show "✓ Applied" toast with [Undo to v{n-1}] action link

━━━ RIGHT PANEL — History ━━━

"Edit History" heading

Timeline list (newest first):
  Each entry:
    Version badge (v1, v2, v3...)
    Operation icon + label
    Timestamp (relative: "2 min ago")
    Status: completed (green check) | processing (spinner) | failed (red ×)
    [Revert to this version] button — only shown for completed, non-current edits
    Current version marked with ● badge

  "Original" entry at top (v0) → always revert to source_path

Empty state: "Apply an operation to start your edit history"

Below timeline:
  [💾 Save to Library] — POST /image-editor/edits/{currentEdit}/save
  [⬇️ Download current] — GET /image-editor/edits/{currentEdit}/download

━━━ Fabric.js mask export to FormData ━━━
When user clicks [Apply] for brush-based operations:
  1. Get the overlay canvas (brush strokes) as binary PNG:
     const maskDataUrl = fabricCanvas.toDataURL({ format: 'png', multiplier: 1 })
  2. Convert dataURL to Blob:
     const blob = await (await fetch(maskDataUrl)).blob()
  3. Build FormData:
     const fd = new FormData()
     fd.append('operation', 'inpaint')
     fd.append('mask', blob, 'mask.png')
     fd.append('params[prompt]', prompt.value)
  4. POST to /image-editor/apply via axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  5. On success: start polling status every 3s (or listen on Reverb)

Mask must be: white where user brushed, black everywhere else.
Implementation: draw on a separate transparent Fabric.js canvas layer.
When exporting mask: flood-fill background to black.

━━━ Reverb real-time update ━━━
Listen: window.Echo.private('user.' + userId).listen('ImageEditCompleted', (e) => {
  if (e.status === 'completed') {
    // Update history, load new image into canvas
    history.value.find(h => h.ulid === e.edit_ulid).status = 'completed'
    history.value.find(h => h.ulid === e.edit_ulid).output_url = e.output_url
    currentImageUrl.value = e.output_url
    isProcessing.value = false
    showToast(t('Edit applied') + ': ' + t(e.operation))
  } else {
    // Failed
    history.value.find(h => h.ulid === e.edit_ulid).status = 'failed'
    isProcessing.value = false
    showErrorToast(e.error)
  }
})
// HTTP fallback: poll GET /image-editor/edits/{ulid}/status every 3s while isProcessing

━━━ PAGE 2: Admin/Settings.vue ━━━

Layout: AdminLayout

Sections:
  Provider Configuration (6 subsections, one per AI operation):
    Each shows: Provider select + API key field + ✓ Configured / ⚠ Not configured badge

  Credits Per Operation:
    Table: Operation | Credits | Input
    (all 8 operations including color correction and text overlay which default to 0)

  Limits:
    Max input image size (MB), Max output dimension (px), History limit per image

  Advanced:
    Auto-save to library (toggle), operation descriptions

[Save Settings]
```

---

## STEP 12 — Pest Tests

### DEEPSEEK PROMPT 12

```
You are building ADDON 04: AI Image Editor for MakeAI (Laravel 13).

Create: addons/ai-image-editor/tests/Feature/ImageEditorTest.php
Namespace: Addons\AiImageEditor\Tests\Feature
PestPHP syntax. RefreshDatabase. Http::fake() for all external API calls.
Use GD test images: imagepng(imagecreatetruecolor(100,100), $path)

━━━ SESSION TESTS ━━━
it('opens editor session from a generated image in the library')
it('opens editor session from a file upload')
it('replaces previous session when user opens a new image')
it('resumes existing session when no image param provided')
it('returns 404 when opening an image that belongs to another user')

━━━ APPLY OPERATION TESTS ━━━
it('creates an IeEdit row with status queued and dispatches ApplyImageEdit job')
it('deducts credits on apply')
it('returns 402 when user has insufficient credits')
it('increments version_number sequentially within a session')
it('requires mask for inpaint operation')
it('requires style_image file for style_transfer operation')
it('requires prompt for inpaint operation')

━━━ JOB TESTS (mock provider clients) ━━━
it('ApplyImageEdit marks edit as completed and calls IeEdit::markAsCurrent on success')
it('ApplyImageEdit updates session current_path and current_url on completion')
it('ApplyImageEdit marks edit as failed and refunds credits on ImageEditException')
it('ApplyImageEdit marks edit as failed and refunds credits on job failed()')
it('ApplyImageEdit broadcasts ImageEditCompleted event on completion')
it('ApplyImageEdit broadcasts ImageEditCompleted event on failure')
it('ApplyImageEdit saves to core generated_images table when auto_save_to_library is true')
it('ApplyImageEdit does not save to library when auto_save_to_library is false')

━━━ PROVIDER ROUTING TESTS ━━━
it('routes inpaint to StabilityClient when stability provider configured')
it('routes inpaint to ReplicateClient when replicate provider configured')
it('routes bg_remove to RemoveBgClient when remove_bg provider configured')
it('routes bg_remove to ClipdropClient when clipdrop provider configured')
it('routes upscale always to ReplicateClient')
it('routes color_correction to GdEditService without any API call')
it('routes text_overlay to GdEditService without any API call')
it('throws ImageEditException for unknown operation')

━━━ GD SERVICE TESTS ━━━
it('colorCorrection applies brightness adjustment and saves PNG')
it('colorCorrection applies contrast adjustment')
it('textOverlay renders text on image and saves PNG')
it('colorCorrection throws for unsupported image format')

━━━ VERSION/HISTORY TESTS ━━━
it('revert sets a previous edit as current and updates session current_path')
it('revert returns 422 for a failed edit')
it('revert returns 422 for the currently active edit')
it('history returns all edits for the session in version order')

━━━ DOWNLOAD TESTS ━━━
it('download streams the output file with correct filename')
it('download returns 422 for a processing edit')
it('download returns 403 for an edit belonging to another user')

━━━ PROVIDER AVAILABILITY TESTS ━━━
it('isProviderConfigured returns true for color_correction without any API keys')
it('isProviderConfigured returns false for inpaint when stability key is null')
it('getAvailableOperations returns correct configured status for all 8 operations')

For Http::fake:
  Http::fake([
    'api.stability.ai/*'     => Http::response(file_get_contents(base_path('tests/stubs/test.png')), 200,
                                  ['Content-Type' => 'image/png']),
    'api.replicate.com/*'    => Http::sequence()
      ->push(['id' => 'pred_123', 'status' => 'starting'], 201)
      ->push(['id' => 'pred_123', 'status' => 'succeeded', 'output' => ['https://cdn.replicate.com/test.png']], 200),
    'api.remove.bg/*'        => Http::response(file_get_contents(base_path('tests/stubs/test.png')), 200,
                                  ['Content-Type' => 'image/png']),
    'clipdrop-api.co/*'      => Http::response(file_get_contents(base_path('tests/stubs/test.png')), 200,
                                  ['Content-Type' => 'image/png']),
    'cdn.replicate.com/*'    => Http::response(file_get_contents(base_path('tests/stubs/test.png')), 200),
  ])

  // Create stub PNG for tests:
  // tests/stubs/test.png — a tiny 10×10 red PNG (created in TestCase::setUp)
```

---

## IMPLEMENTATION SEQUENCE NOTES

1. **Steps 1–4** — scaffold + migrations. Run migrate before any service code.
2. **Steps 5–6** — provider clients + GdEditService. Test each client independently with Http::fake before wiring.
3. **Step 7** — ImageEditorService router. Test with all 8 operations before writing the job.
4. **Step 8** — job + event. The `markAsCurrent()` transaction is the critical path — test it carefully.
5. **Steps 9–10** — controllers + routes. The `apply()` endpoint handles multipart — test file upload manually.
6. **Step 11** — Vue. The Fabric.js canvas + mask export to FormData is the hardest piece. Build the UI shell first, then add Fabric.js integration, then the mask export logic.
7. **Step 12** — run tests throughout.

---

## PRE-BUILD CHECKLIST

```bash
# Verify PHP GD is enabled (required for color correction + text overlay)
php -r "echo extension_loaded('gd') ? 'GD OK' : 'GD MISSING';"

# Install Fabric.js
npm install fabric@6

# Verify the generated_images table exists in core
php artisan tinker --execute="Schema::hasTable('generated_images') ? 'exists' : 'missing';"
# If it doesn't exist yet, the saveToLibrary feature will be skipped gracefully (wrapped in try-catch)

# API keys needed for full testing (at minimum one):
# - Stability AI key → test inpaint, outpaint, style transfer, object removal
# - Replicate key    → test upscaling
# - Remove.bg key    → test background removal
# Color correction + text overlay work with ZERO API keys
```

---

## CRITICAL INVARIANTS (repeat for every DeepSeek session)

```
AI ENGINE:    No laravel/ai — all provider calls via Http facade in provider client classes
QUEUE:        media → all ApplyImageEdit jobs
CREDITS:      Deducted upfront in controller; refunded in job failed() and catch block
ADDON CONFIG: addon_setting('ai-image-editor', 'key') — NEVER settings('key') with prefix
              addon_setting_set('ai-image-editor', 'key', $value) to write
APP NAME:     settings('app_name') — NEVER hardcode "MakeAI"
ADMIN ROUTES: auth:admin + admin.permission:addon.ie.settings
USER ROUTES:  auth + permission:addon.ie.use
MASK:         Always PNG, exported from Fabric.js canvas as binary blob via FormData
              White = affected area, black = keep
SESSION:      One active IeSession per user (UNIQUE user_id constraint)
BROADCAST:    ImageEditCompleted via Reverb — fired for BOTH success AND failure
TRANSLATE:    translate() PHP / $t() Vue — ALL user-facing strings
N+1:          Always with(['session','user']) on IeEdit queries
GD:           Check extension_loaded('gd') before using; wrap GD failures in ImageEditException
```

---

# ADDON 05: AI Video Creator — Implementation Guide

> **Slug:** `ai-video-creator`
> **Queue:** `ai` (generation jobs), `media` (file processing), `low` (cleanup)
> **Requires Pro:** Yes (`isProAvailable()` gate — Extended license + subscriptions_enabled)
> **AI engine:** External provider HTTP APIs (Kling, Runway, HeyGen, D-ID, Pika, ElevenLabs,
>   Whisper) via Laravel `Http` facade — **no** `laravel/ai` SDK for video (unsupported);
>   `AiService` used only for script generation (text completion)
> **Progress tracking:** Reverb WebSocket broadcast → frontend polling fallback
> **Storage:** S3 or local disk via `Storage` facade — all media through `storage/app/video-creator/`

---

## WHAT THIS ADDON BUILDS

A full video creation suite with four distinct workflows:

1. **Text-to-Video** — type a prompt → Kling/Runway/Pika/Minimax generates a short video clip
2. **Image-to-Video** — upload an image → animate it into a video
3. **AI Avatar Video** — type a script → HeyGen or D-ID renders a talking avatar speaking it
4. **Video Slideshow** — upload images + pick music → AI generates voiceover → ffmpeg stitches into MP4

Plus: Whisper subtitle generation, basic trim (start/end), video library with folders, download + share links.

---

## ARCHITECTURE OVERVIEW

```
User submits generation request
        │
        ▼
VideoController::create() → validates input, deducts credits, creates vc_renders row (status: queued)
        │
        ▼
Dispatch job to queue 'ai':
  GenerateTextToVideo | GenerateImageToVideo | GenerateAvatarVideo | GenerateSlideshow
        │
        ▼
Job: calls provider API (async — most providers return a job_id, not instant result)
  ├── Provider accepts → polls status every N seconds (loop with sleep)
  │     OR dispatches PollVideoStatus job on queue 'ai' with delay
  │
  ▼ when provider signals complete:
Downloads video file → stores in storage/app/video-creator/{user_id}/{ulid}.mp4
Updates vc_renders: status=completed, file_path, duration, thumbnail_path
Broadcasts on Reverb: private-user.{user_id} → event: VideoRenderComplete
        │
        ▼
Frontend: Echo listener → updates UI; fallback: poll GET /video-creator/renders/{ulid}/status
```

---

## KEY DESIGN DECISIONS

**Why not `laravel/ai` SDK for video?**
`laravel/ai` wraps LLM completion and embedding APIs. Video generation providers (Kling, HeyGen,
Runway) have entirely proprietary async job APIs — no SDK abstraction exists. All video provider
calls use `Http` facade directly inside provider client classes.

**Credit model for video:**
Video costs are fixed per render type (not token-based). Credits deducted upfront on job creation.
If the job fails, credits are refunded to the user.

| Render type | Default credits | Setting key |
|---|---|---|
| Text-to-video (5s) | 50 | `credits_text_video` |
| Text-to-video (10s) | 100 | `credits_text_video_long` |
| Image-to-video (5s) | 40 | `credits_image_video` |
| Avatar video (per 30s) | 80 | `credits_avatar_video` |
| Slideshow (per minute) | 30 | `credits_slideshow` |
| Subtitle generation | 10 | `credits_subtitles` |

**Polling strategy:**
Most video providers (Kling, Runway) take 30s–5min. Jobs use a retry loop:
- `PollVideoStatus` job dispatched with 30s delay, retries up to 20 times (10 minutes total)
- On completion: Reverb broadcast + `vc_renders.status = completed`
- On timeout (20 retries): `status = failed`, credits refunded

---

## STEP-BY-STEP BUILD ORDER

```
Step 1  → addon.json + AddonServiceProvider
Step 2  → Migrations (4 tables)
Step 3  → Models + Relationships
Step 4  → Seeder
Step 5  → Provider client classes (Kling, Runway, Pika, HeyGen, D-ID, ElevenLabs, Whisper)
Step 6  → VideoProviderService (router + credit logic)
Step 7  → Generation jobs (4 types + PollVideoStatus)
Step 8  → SlideshowBuilderService (ffmpeg)
Step 9  → SubtitleService (Whisper)
Step 10 → TrimmerService (ffmpeg)
Step 11 → Controllers + FormRequests (user + admin)
Step 12 → Routes
Step 13 → Vue Pages (Library, Creator, Viewer, Admin Settings)
Step 14 → Pest Tests
```

---

## STEP 1 — addon.json + AddonServiceProvider

### DEEPSEEK PROMPT 1

```
You are building an addon for MakeAI (Laravel 13 + Vue 3 + Inertia.js).
Create two files for ADDON 05: AI Video Creator.

━━━ FILE 1: addons/ai-video-creator/addon.json ━━━

{
  "name": "AI Video Creator",
  "slug": "ai-video-creator",
  "version": "1.0.0",
  "description": "Full video creation suite: text-to-video, image-to-video, AI avatar, slideshow, subtitles, and video library.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 2,
  "requires_pro": true,
  "admin_menu": [
    { "parent": "Content",  "label": "Video Creator",  "route": "addon.video.admin.overview",  "icon": "ti-video",    "permission": "addon.video.manage" },
    { "parent": "Settings", "label": "Video Creator",  "route": "addon.video.admin.settings",  "icon": "ti-settings", "permission": "addon.video.settings" }
  ],
  "settings": [
    { "key": "enabled",                  "type": "boolean",  "label": "Enable Video Creator",             "default": true },
    { "key": "text_video_provider",      "type": "select",   "label": "Text-to-Video Provider",           "options": ["kling","runway","pika","minimax"], "default": "kling" },
    { "key": "image_video_provider",     "type": "select",   "label": "Image-to-Video Provider",          "options": ["kling","runway","pika"],           "default": "kling" },
    { "key": "avatar_provider",          "type": "select",   "label": "Avatar Video Provider",            "options": ["heygen","did"],                    "default": "heygen" },
    { "key": "tts_provider",             "type": "select",   "label": "TTS Provider (slideshow voiceover)","options": ["elevenlabs","openai","murf"],      "default": "elevenlabs" },
    { "key": "subtitle_provider",        "type": "select",   "label": "Subtitle Provider",                "options": ["whisper","assemblyai"],            "default": "whisper" },
    { "key": "kling_api_key",            "type": "encrypted","label": "Kling AI API Key",                 "default": null },
    { "key": "kling_api_secret",         "type": "encrypted","label": "Kling AI API Secret",              "default": null },
    { "key": "runway_api_key",           "type": "encrypted","label": "Runway ML API Key",                "default": null },
    { "key": "pika_api_key",             "type": "encrypted","label": "Pika Labs API Key",                "default": null },
    { "key": "minimax_api_key",          "type": "encrypted","label": "Minimax Video API Key",            "default": null },
    { "key": "heygen_api_key",           "type": "encrypted","label": "HeyGen API Key",                   "default": null },
    { "key": "did_api_key",              "type": "encrypted","label": "D-ID API Key",                     "default": null },
    { "key": "elevenlabs_api_key",       "type": "encrypted","label": "ElevenLabs API Key",               "default": null },
    { "key": "assemblyai_api_key",       "type": "encrypted","label": "AssemblyAI API Key",               "default": null },
    { "key": "max_video_duration",       "type": "integer",  "label": "Max video duration (seconds)",     "default": 30 },
    { "key": "max_storage_mb_per_user",  "type": "integer",  "label": "Max storage per user (MB)",        "default": 500 },
    { "key": "credits_text_video",       "type": "integer",  "label": "Credits: text-to-video (5s)",      "default": 50 },
    { "key": "credits_text_video_long",  "type": "integer",  "label": "Credits: text-to-video (10s)",     "default": 100 },
    { "key": "credits_image_video",      "type": "integer",  "label": "Credits: image-to-video (5s)",     "default": 40 },
    { "key": "credits_avatar_video",     "type": "integer",  "label": "Credits: avatar video (per 30s)",  "default": 80 },
    { "key": "credits_slideshow",        "type": "integer",  "label": "Credits: slideshow (per minute)",  "default": 30 },
    { "key": "credits_subtitles",        "type": "integer",  "label": "Credits: subtitle generation",     "default": 10 },
    { "key": "ffmpeg_path",              "type": "string",   "label": "ffmpeg binary path",               "default": "/usr/bin/ffmpeg" },
    { "key": "poll_interval_seconds",    "type": "integer",  "label": "Provider poll interval (seconds)", "default": 30 },
    { "key": "max_poll_attempts",        "type": "integer",  "label": "Max poll attempts before timeout", "default": 20 },
    { "key": "auto_delete_days",         "type": "integer",  "label": "Auto-delete renders after N days (0=never)", "default": 30 }
  ],
  "permissions": [
    { "slug": "addon.video.manage",   "name": "Use Video Creator",          "group": "Video Creator" },
    { "slug": "addon.video.settings", "name": "Manage Video Creator Settings", "group": "Video Creator" }
  ],
  "hooks": []
}

━━━ FILE 2: addons/ai-video-creator/AddonServiceProvider.php ━━━

Namespace: Addons\AiVideoCreator

In register():
  - Bind VideoProviderService, SlideshowBuilderService, SubtitleService, TrimmerService as singletons

In boot() — only if is_addon_active('ai-video-creator'):
  - Load routes: routes/web.php, routes/api.php
  - Load migrations
  - Share via Inertia::share('videoCreator', fn() => [...]) — only safe fields:
      enabled, max_video_duration, max_storage_mb_per_user
      (credits per type also shared for display in UI)
      NEVER share API keys
  - Register scheduled jobs:
      Schedule::job(new CleanupExpiredVideos)->daily()->when(
          fn() => addon_setting('ai-video-creator', 'auto_delete_days', 30) > 0
      )

CRITICAL: isProAvailable() MUST be checked in every user controller before allowing access.
If isProAvailable() returns false → abort(403, 'Pro license required').
This addon requires_pro: true — the gate is isProAvailable() only.
```

---

## STEP 2 — Migrations (4 tables)

### DEEPSEEK PROMPT 2

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create 4 migration files in addons/ai-video-creator/database/migrations/.
All tables prefixed vc_. All PKs: bigint unsigned auto-increment. Standard timestamps.

━━━ MIGRATION 1: create_vc_projects_table ━━━

vc_projects
  id
  ulid              char(26) UNIQUE NOT NULL
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  name              varchar(255) NOT NULL DEFAULT 'Untitled Project'
  description       text NULL
  folder_id         bigint UNSIGNED NULL FK → vc_folders.id ON DELETE SET NULL
  color             varchar(7) DEFAULT '#6366f1'    -- folder/project accent color
  thumbnail_path    varchar(500) NULL               -- first render's thumbnail
  render_count      int UNSIGNED DEFAULT 0          -- denormalized
  total_duration    int UNSIGNED DEFAULT 0          -- total seconds across renders
  created_at, updated_at

  INDEX (user_id, folder_id)

━━━ MIGRATION 2: create_vc_folders_table ━━━

vc_folders
  id
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  name              varchar(100) NOT NULL
  color             varchar(7) DEFAULT '#6366f1'
  sort_order        smallint DEFAULT 0
  created_at, updated_at

  INDEX (user_id, sort_order)

━━━ MIGRATION 3: create_vc_renders_table ━━━
(Core table — one row per generation job)

vc_renders
  id
  ulid              char(26) UNIQUE NOT NULL        -- public-facing ID + share token
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  vc_project_id     bigint UNSIGNED NULL FK → vc_projects.id ON DELETE SET NULL
  type              enum('text_to_video','image_to_video','avatar_video','slideshow') NOT NULL
  status            enum('queued','processing','completed','failed','cancelled') DEFAULT 'queued'
  provider          varchar(30) NOT NULL            -- 'kling','runway','heygen','did','local'
  provider_job_id   varchar(255) NULL               -- external job ID for polling
  poll_attempts     tinyint UNSIGNED DEFAULT 0
  title             varchar(255) NULL               -- user-given label
  prompt            text NULL                       -- user's text prompt
  script            text NULL                       -- for avatar: the spoken script
  duration_seconds  smallint UNSIGNED NULL          -- requested duration (5 or 10)
  aspect_ratio      varchar(10) DEFAULT '16:9'      -- '16:9' | '9:16' | '1:1'
  resolution        varchar(20) DEFAULT '1280x720'
  provider_settings json NULL                       -- extra provider-specific params
  input_media_path  varchar(500) NULL               -- for image-to-video: source image path
  file_path         varchar(500) NULL               -- stored video path (after completion)
  file_url          varchar(500) NULL               -- public URL
  file_size_bytes   int UNSIGNED DEFAULT 0
  thumbnail_path    varchar(500) NULL
  thumbnail_url     varchar(500) NULL
  duration_actual   smallint UNSIGNED NULL          -- actual video duration from metadata
  share_enabled     boolean DEFAULT false
  share_token       varchar(64) NULL UNIQUE
  credits_deducted  decimal(10,4) DEFAULT 0
  error_message     text NULL
  metadata          json NULL                       -- provider response metadata
  completed_at      timestamp NULL
  expires_at        timestamp NULL                  -- when auto-delete will remove the file
  created_at, updated_at

  INDEX (user_id, status)
  INDEX (status, provider_job_id)       -- for PollVideoStatus job lookups
  INDEX (expires_at)                    -- for cleanup job
  INDEX (share_token)

━━━ MIGRATION 4: create_vc_subtitles_table ━━━

vc_subtitles
  id
  vc_render_id      bigint UNSIGNED NOT NULL FK → vc_renders.id ON DELETE CASCADE
  provider          varchar(30) DEFAULT 'whisper'
  status            enum('queued','processing','completed','failed') DEFAULT 'queued'
  language          varchar(10) DEFAULT 'en'
  format            enum('srt','vtt','json') DEFAULT 'srt'
  content           longtext NULL                   -- raw subtitle file content
  segments          json NULL                       -- [{start, end, text}, ...] for in-player display
  credits_deducted  decimal(10,4) DEFAULT 0
  error_message     text NULL
  created_at, updated_at

  UNIQUE (vc_render_id, format)

Use standard Laravel migration syntax. Add FK constraints with constrained().
```

---

## STEP 3 — Models + Relationships

### DEEPSEEK PROMPT 3

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create 4 Eloquent models in addons/ai-video-creator/app/Models/.
Namespace: Addons\AiVideoCreator\Models

━━━ VcFolder.php ━━━
- $fillable: user_id, name, color, sort_order
- $casts: sort_order → integer
- Relationships: belongsTo(User::class), hasMany(VcProject::class)
- Scope: scopeForUser($q, int $userId)

━━━ VcProject.php ━━━
- $fillable: ulid, user_id, name, description, folder_id, color, thumbnail_path, render_count, total_duration
- $casts: render_count → integer, total_duration → integer
- $appends: ['thumbnail_url']
- Relationships:
    belongsTo(User::class)
    belongsTo(VcFolder::class)
    hasMany(VcRender::class)
- Accessor: getThumbnailUrlAttribute() → thumbnail_path ? Storage::url($this->thumbnail_path) : null
- Boot: on creating → generate ULID

━━━ VcRender.php ━━━
- $fillable: ulid, user_id, vc_project_id, type, status, provider, provider_job_id,
             poll_attempts, title, prompt, script, duration_seconds, aspect_ratio,
             resolution, provider_settings, input_media_path, file_path, file_url,
             file_size_bytes, thumbnail_path, thumbnail_url, duration_actual,
             share_enabled, share_token, credits_deducted, error_message,
             metadata, completed_at, expires_at
- $casts: provider_settings → array, metadata → array, share_enabled → boolean,
          completed_at → datetime, expires_at → datetime, credits_deducted → float,
          duration_seconds → integer, duration_actual → integer, poll_attempts → integer
- $appends: ['status_label', 'type_label', 'can_retry', 'is_expired']
- Relationships:
    belongsTo(User::class)
    belongsTo(VcProject::class)
    hasMany(VcSubtitle::class)
- Scopes:
    scopeForUser($q, int $userId) → where('user_id', $userId)
    scopeProcessing($q) → whereIn('status', ['queued','processing'])
    scopeCompleted($q) → where('status', 'completed')->whereNotNull('file_path')
    scopePendingPoll($q) → where('status','processing')
                            ->whereNotNull('provider_job_id')
                            ->where('poll_attempts','<', addon_setting('ai-video-creator','max_poll_attempts',20))
- Accessor: getStatusLabelAttribute() → human-readable map of status
- Accessor: getTypeLabelAttribute() → map type enum to label
- Accessor: getCanRetryAttribute(): bool → status === 'failed'
- Accessor: getIsExpiredAttribute(): bool → expires_at && expires_at->isPast()
- Boot: on creating → generate ULID; generate share_token (Str::random(64))

━━━ VcSubtitle.php ━━━
- $fillable: vc_render_id, provider, status, language, format, content, segments, credits_deducted, error_message
- $casts: segments → array, credits_deducted → float
- Relationship: belongsTo(VcRender::class)
```

---

## STEP 4 — Seeder

### DEEPSEEK PROMPT 4

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/database/seeders/VideoCreatorSeeder.php
Namespace: Addons\AiVideoCreator\Database\Seeders

Idempotent (check if vc_projects already has rows).

For the first Pro user (User::where('credits', '>', 0)->first()):
  Create 2 demo folders: 'Marketing Videos' (color #6366f1) and 'Personal' (color #10b981)
  Create 1 demo project per folder
  Create 1 demo vc_render per project with status='completed', type='text_to_video',
    provider='kling', prompt='A serene mountain landscape at sunset...', duration_seconds=5
    file_path and file_url = null (no actual file — demo only)
    title = 'Demo: Mountain Sunset'

  Comment: "Demo renders have no actual video files.
  Buyers must configure provider API keys in Admin → Settings → Video Creator
  and create a real render to test end-to-end."
```

---

## STEP 5 — Provider Client Classes

### DEEPSEEK PROMPT 5

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create 7 provider client classes in:
  addons/ai-video-creator/app/Services/Providers/

Namespace: Addons\AiVideoCreator\Services\Providers

Each class is a thin HTTP wrapper. All use Laravel Http facade.
They do NOT handle retries or polling — that is done by the jobs.
All throw VideoProviderException (create this exception class) on API errors.

━━━ BASE: VideoProviderClient.php (abstract) ━━━

abstract class VideoProviderClient
{
    abstract public function submitJob(array $params): string   // returns provider_job_id
    abstract public function checkStatus(string $jobId): ProviderJobStatus   // see below
    abstract public function downloadResult(string $jobId): string  // returns temp file path
    abstract public function supportedTypes(): array  // ['text_to_video','image_to_video',...]
}

// Value object:
readonly class ProviderJobStatus {
    public function __construct(
        public string $status,      // 'processing' | 'completed' | 'failed'
        public ?string $videoUrl,   // direct download URL when completed
        public ?string $error,      // error message when failed
        public array $metadata = [] // any extra provider-specific data
    ) {}
}

━━━ KlingClient.php ━━━
API base: https://api.klingai.com/v1
Auth: JWT from API Key + Secret (HMAC-SHA256, generated per request)

submitJob(array $params):
  For text-to-video:
    POST /videos/text2video
    Body: { model_name: 'kling-v1-5', prompt: $params['prompt'],
            duration: $params['duration'] (5 or 10),
            aspect_ratio: $params['aspect_ratio'] (16:9|9:16|1:1),
            cfg_scale: 0.5, mode: 'std' }
    Auth header: Authorization: Bearer {jwt}
    Returns: data.task_id

  For image-to-video:
    POST /videos/image2video
    Body: { model_name: 'kling-v1-5', image: $base64ImageOrUrl,
            prompt: $params['prompt'] ?? '',
            duration: $params['duration'],
            cfg_scale: 0.5 }
    Returns: data.task_id

  JWT generation (HMAC-SHA256):
    $header  = base64url_encode(json_encode(['alg'=>'HS256','typ':'JWT']))
    $payload = base64url_encode(json_encode(['iss'=>$apiKey,'exp'=>time()+1800,'nbf'=>time()-5]))
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", $apiSecret, true))
    return "$header.$payload.$sig"

checkStatus(string $jobId):
  GET /videos/{task_id}
  Returns: data.task_status ('submitted'|'processing'|'succeed'|'failed')
  Map: succeed→completed, failed→failed, others→processing
  videoUrl: data.task_result.videos[0].url (when succeed)

downloadResult(string $jobId):
  Get video URL from checkStatus, stream to temp file
  Http::withOptions(['sink' => $tempPath])->get($videoUrl)
  Return temp file path

supportedTypes(): ['text_to_video', 'image_to_video']

━━━ RunwayClient.php ━━━
API base: https://api.dev.runwayml.com/v1
Auth: Authorization: Bearer {api_key}, X-Runway-Version: 2024-11-06

submitJob(array $params):
  POST /image_to_video (Runway Gen-3 only supports image-to-video as of 2024)
  Body: { promptImage: $base64ImageUrl, promptText: $params['prompt'],
          model: 'gen3a_turbo', duration: $params['duration'] (5|10),
          ratio: $params['aspect_ratio'] ('16:9'|'9:16'|'1:1') }
  Returns: id (task ID)

  Note: Runway does not support text-to-video without an image.
  If type=text_to_video, throw VideoProviderException('Runway requires an image for text-to-video. Use Kling or Pika instead.')

checkStatus(string $jobId):
  GET /tasks/{id}
  Returns: status ('PENDING'|'RUNNING'|'SUCCEEDED'|'FAILED')
  Map: SUCCEEDED→completed, FAILED→failed, others→processing
  videoUrl: output[0] (when SUCCEEDED)

supportedTypes(): ['image_to_video']

━━━ PikaClient.php ━━━
API base: https://api.pika.art/v1
Auth: Authorization: Bearer {api_key}

submitJob(array $params):
  POST /generate
  Body: { promptText: $params['prompt'],
          image: $params['image_url'] ?? null,   // optional
          duration: $params['duration'],
          aspectRatio: $params['aspect_ratio'] }
  Returns: id

checkStatus(string $jobId):
  GET /jobs/{id}
  Returns: status ('queued'|'running'|'succeeded'|'failed')
  videoUrl: resultUrl (when succeeded)

supportedTypes(): ['text_to_video', 'image_to_video']

━━━ MinimaxClient.php ━━━
API base: https://api.minimaxi.chat/v1
Auth: Authorization: Bearer {api_key}

submitJob(array $params):
  POST /video_generation
  Body: { model: 'video-01', prompt: $params['prompt'] }
  Returns: task_id

checkStatus(string $jobId):
  GET /query/video_generation?task_id={task_id}
  Returns: status ('Queueing'|'Processing'|'Success'|'Fail')
  videoUrl: file_id → requires second call to /files/retrieve?file_id=...
  Actually: when status=Success, fetch the video URL:
    GET /files/retrieve?GroupId={groupId}&file_id={fileId}
    Returns: file.download_url

supportedTypes(): ['text_to_video']

━━━ HeyGenClient.php ━━━
API base: https://api.heygen.com/v2
Auth: X-Api-Key: {api_key}

submitJob(array $params):
  POST /video/generate
  Body: {
    video_inputs: [{
      character: {
        type: "avatar",
        avatar_id: $params['avatar_id'] ?? "Vanessa-invest-20240827",  // default avatar
        avatar_style: "normal"
      },
      voice: {
        type: "text",
        input_text: $params['script'],
        voice_id: $params['voice_id'] ?? "2d5b0e6cf36f460aa7fc47e3eee4ba54"  // default voice
      },
      background: {
        type: "color",
        value: "#FFFFFF"
      }
    }],
    dimension: { width: 1280, height: 720 }
  }
  Returns: data.video_id

checkStatus(string $jobId):
  GET /video_status.get?video_id={video_id}
  Returns: data.status ('processing'|'completed'|'failed')
  videoUrl: data.video_url (when completed)

listAvatars():
  GET /avatars (returns list of available avatars for the UI picker)

listVoices():
  GET /voices (returns list of available voices)

supportedTypes(): ['avatar_video']

━━━ DidClient.php ━━━
API base: https://api.d-id.com
Auth: Authorization: Basic base64({api_key}:)

submitJob(array $params):
  POST /talks
  Body: {
    script: {
      type: "text",
      subtitles: false,
      provider: { type: "microsoft", voice_id: "en-US-JennyNeural" },
      input: $params['script']
    },
    presenter_id: $params['presenter_id'] ?? "rian-pbMoTzs7an",  // default presenter
    driver_id: "uM00QurTBs"
  }
  Returns: id

checkStatus(string $jobId):
  GET /talks/{id}
  Returns: status ('created'|'started'|'done'|'error')
  videoUrl: result_url (when done)

supportedTypes(): ['avatar_video']

━━━ WhisperClient.php + ElevenLabsClient.php ━━━
(used by SubtitleService and SlideshowBuilderService respectively — not video generation)

WhisperClient:
  transcribe(string $audioOrVideoPath): array
    POST https://api.openai.com/v1/audio/transcriptions
    Body: multipart file + model='whisper-1' + response_format='verbose_json' + timestamp_granularities=['segment']
    Auth: Authorization: Bearer {openai_api_key from core settings('openai_api_key')}
    Returns: { text: string, segments: [{start, end, text}, ...] }

ElevenLabsClient:
  textToSpeech(string $text, string $voiceId, string $outputPath): void
    POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}
    Body: { text, model_id: 'eleven_monolingual_v1', voice_settings: {stability:0.5,similarity_boost:0.75} }
    Auth: xi-api-key: {api_key}
    Streams response to $outputPath (audio/mpeg)

RULES:
- All HTTP calls use Laravel Http facade — Http::withToken(), Http::withHeaders(), etc.
- All API keys read from addon_setting('ai-video-creator', 'key_name')
  EXCEPT Whisper which uses core settings('openai_api_key')
- Throw VideoProviderException for any non-2xx response
- Never log decrypted API keys — log only the exception message
- Timeout: 30s for job submission, 60s for file download
```

---

## STEP 6 — VideoProviderService (Router + Credit Logic)

### DEEPSEEK PROMPT 6

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/app/Services/VideoProviderService.php
Namespace: Addons\AiVideoCreator\Services

This service routes generation requests to the correct provider client,
handles credit deduction/refund, and creates the vc_renders row.

━━━ Constructor ━━━
public function __construct(
    private KlingClient $kling,
    private RunwayClient $runway,
    private PikaClient $pika,
    private MinimaxClient $minimax,
    private HeyGenClient $heygen,
    private DidClient $did,
) {}

━━━ getClient(string $renderType): VideoProviderClient ━━━
  Read the configured provider from addon_setting():
    text_to_video / image_to_video → addon_setting('ai-video-creator', 'text_video_provider') or 'image_video_provider'
    avatar_video → addon_setting('ai-video-creator', 'avatar_provider')
    slideshow → null (handled locally by SlideshowBuilderService)
  Return the matching client instance.
  Throw VideoProviderException if provider not configured or API key is null.

━━━ calculateCredits(string $type, int $durationSeconds): int ━━━
  Returns credits to deduct based on type + duration:
    text_to_video: $durationSeconds <= 5
                     ? addon_setting('ai-video-creator', 'credits_text_video', 50)
                     : addon_setting('ai-video-creator', 'credits_text_video_long', 100)
    image_to_video: addon_setting('ai-video-creator', 'credits_image_video', 40)
    avatar_video:   ceil($durationSeconds / 30) * addon_setting('ai-video-creator', 'credits_avatar_video', 80)
    slideshow:      ceil($durationSeconds / 60) * addon_setting('ai-video-creator', 'credits_slideshow', 30)

━━━ createRender(User $user, array $params): VcRender ━━━
  Step 1: Check isProAvailable() → throw UnauthorizedException if false
  Step 2: Check user credits >= calculateCredits($type, $duration) → throw CreditLimitException if not enough
  Step 3: Check storage limit:
    $usedMb = VcRender::where('user_id', $user->id)
      ->whereNotNull('file_path')
      ->sum('file_size_bytes') / 1024 / 1024
    $maxMb = addon_setting('ai-video-creator', 'max_storage_mb_per_user', 500)
    if ($usedMb >= $maxMb) throw StorageLimitException("Storage limit reached: {$maxMb}MB")
  Step 4: Deduct credits upfront:
    $credits = calculateCredits($params['type'], $params['duration'] ?? 5)
    deduct_credits($user->id, $credits, 'Video Creator: ' . $params['type'])
  Step 5: Create VcRender:
    VcRender::create([
      'user_id'           => $user->id,
      'vc_project_id'     => $params['project_id'] ?? null,
      'type'              => $params['type'],
      'status'            => 'queued',
      'provider'          => $this->getProviderName($params['type']),
      'title'             => $params['title'] ?? null,
      'prompt'            => $params['prompt'] ?? null,
      'script'            => $params['script'] ?? null,
      'duration_seconds'  => $params['duration'] ?? 5,
      'aspect_ratio'      => $params['aspect_ratio'] ?? '16:9',
      'provider_settings' => $params['provider_settings'] ?? [],
      'input_media_path'  => $params['input_media_path'] ?? null,
      'credits_deducted'  => $credits,
      'expires_at'        => $autoDeleteDays > 0 ? now()->addDays($autoDeleteDays) : null,
    ])
  Step 6: Return the render

━━━ refundCredits(VcRender $render): void ━━━
  Called when a job fails. Adds credits back:
    DB::table('credit_transactions')->insert([
      'user_id'       => $render->user_id,
      'amount'        => $render->credits_deducted,
      'balance_after' => $render->user->credits + $render->credits_deducted,
      'type'          => 'refund',
      'description'   => 'Video generation failed — refund: ' . $render->ulid,
      'created_at'    => now(),
    ])
    User::where('id', $render->user_id)->increment('credits', $render->credits_deducted)
```

---

## STEP 7 — Generation Jobs

### DEEPSEEK PROMPT 7

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create 5 jobs in addons/ai-video-creator/app/Jobs/.
Namespace: Addons\AiVideoCreator\Jobs
Queue: 'ai'. Max attempts: 3. Backoff: [60, 300].

━━━ SHARED PATTERN: all generation jobs ━━━

Constructor: __construct(public readonly int $renderId)

handle():
  1. $render = VcRender::find($this->renderId) — if null: return
  2. $render->update(['status' => 'processing'])
  3. Get provider client via VideoProviderService::getClient()
  4. Submit job → get provider_job_id
  5. $render->update(['provider_job_id' => $jobId])
  6. Dispatch PollVideoStatus::dispatch($render->id)->delay(now()->addSeconds(
       addon_setting('ai-video-creator', 'poll_interval_seconds', 30)
     ))->onQueue('ai')

failed(Throwable $e):
  VcRender::where('id', $this->renderId)->update([
    'status' => 'failed',
    'error_message' => Str::limit($e->getMessage(), 500),
  ])
  app(VideoProviderService::class)->refundCredits(VcRender::find($this->renderId))
  // Notify user via Reverb
  SendInAppNotification::dispatch(
    User::find(VcRender::where('id',$this->renderId)->value('user_id')),
    'video_failed',
    ['render_id' => $this->renderId]
  )->onQueue('default')

━━━ GenerateTextToVideo.php ━━━
handle():
  Follows shared pattern.
  params for submitJob:
    type: 'text_to_video'
    prompt: $render->prompt
    duration: $render->duration_seconds
    aspect_ratio: $render->aspect_ratio
    ...$render->provider_settings

━━━ GenerateImageToVideo.php ━━━
handle():
  Follows shared pattern.
  Before submitting: encode input image to base64 URL:
    $imagePath = storage_path('app/' . $render->input_media_path)
    $base64 = 'data:image/' . pathinfo($imagePath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($imagePath))
  params for submitJob:
    type: 'image_to_video'
    image_url: $base64
    prompt: $render->prompt ?? ''
    duration: $render->duration_seconds
    aspect_ratio: $render->aspect_ratio

━━━ GenerateAvatarVideo.php ━━━
handle():
  Follows shared pattern.
  Validates script is not empty. If empty: mark failed, refund.
  params for submitJob:
    type: 'avatar_video'
    script: $render->script
    avatar_id: $render->provider_settings['avatar_id'] ?? null
    voice_id: $render->provider_settings['voice_id'] ?? null
    presenter_id: $render->provider_settings['presenter_id'] ?? null  // D-ID

━━━ GenerateSlideshow.php ━━━
handle():
  Uses SlideshowBuilderService (not a provider API — runs locally with ffmpeg).
  Does NOT need PollVideoStatus. Completes synchronously (or in-job).

  $render->update(['status' => 'processing'])
  $service = app(SlideshowBuilderService::class)
  $outputPath = $service->build($render)   // see Step 8
  // After build completes:
  $render->update([
    'status'          => 'completed',
    'file_path'       => $outputPath,
    'file_url'        => Storage::url($outputPath),
    'file_size_bytes' => Storage::size($outputPath),
    'completed_at'    => now(),
  ])
  broadcast_render_complete($render)   // helper defined below

━━━ PollVideoStatus.php ━━━
Namespace: Addons\AiVideoCreator\Jobs
Queue: 'ai'. Max attempts: 1 (self-requeueing pattern). No backoff.

handle():
  $render = VcRender::with('user')->find($this->renderId)
  if (!$render || $render->status !== 'processing') return

  // Timeout check
  $maxAttempts = addon_setting('ai-video-creator', 'max_poll_attempts', 20)
  if ($render->poll_attempts >= $maxAttempts) {
    $render->update(['status' => 'failed', 'error_message' => 'Generation timed out after ' . $maxAttempts . ' attempts'])
    app(VideoProviderService::class)->refundCredits($render)
    SendInAppNotification::dispatch($render->user, 'video_failed', ['render_id' => $render->id])
    return
  }

  $render->increment('poll_attempts')

  $client = app(VideoProviderService::class)->getClient($render->type)
  $status = $client->checkStatus($render->provider_job_id)

  if ($status->status === 'completed') {
    // Download video to storage
    $tempPath = $client->downloadResult($render->provider_job_id)
    $storagePath = 'video-creator/' . $render->user_id . '/' . $render->ulid . '.mp4'
    Storage::put($storagePath, file_get_contents($tempPath))
    @unlink($tempPath)

    // Generate thumbnail via ffmpeg
    $thumbPath = app(TrimmerService::class)->extractThumbnail($storagePath)

    $render->update([
      'status'          => 'completed',
      'file_path'       => $storagePath,
      'file_url'        => Storage::url($storagePath),
      'file_size_bytes' => Storage::size($storagePath),
      'thumbnail_path'  => $thumbPath,
      'thumbnail_url'   => $thumbPath ? Storage::url($thumbPath) : null,
      'metadata'        => $status->metadata,
      'completed_at'    => now(),
    ])

    // Broadcast via Reverb
    event(new VideoRenderCompleted($render))
    SendInAppNotification::dispatch($render->user, 'video_completed', [
      'render_id' => $render->id,
      'title' => $render->title ?? $render->type,
    ])->onQueue('default')

  } elseif ($status->status === 'failed') {
    $render->update(['status' => 'failed', 'error_message' => $status->error ?? 'Provider returned failure'])
    app(VideoProviderService::class)->refundCredits($render)
    SendInAppNotification::dispatch($render->user, 'video_failed', ['render_id' => $render->id])

  } else {
    // Still processing — requeue
    PollVideoStatus::dispatch($render->id)
      ->delay(now()->addSeconds(addon_setting('ai-video-creator', 'poll_interval_seconds', 30)))
      ->onQueue('ai')
  }

━━━ Also create: VideoRenderCompleted event (app/Events/ inside addon) ━━━

class VideoRenderCompleted implements ShouldBroadcast
  use InteractsWithSockets
  public $channel = null

  __construct(public VcRender $render) {}

  broadcastOn(): PrivateChannel
    return new PrivateChannel('user.' . $this->render->user_id)

  broadcastAs(): 'VideoRenderCompleted'

  broadcastWith(): ['render_id' => $this->render->id, 'ulid' => $this->render->ulid,
                    'status' => 'completed', 'title' => $this->render->title]
```

---

## STEP 8 — SlideshowBuilderService (ffmpeg)

### DEEPSEEK PROMPT 8

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/app/Services/SlideshowBuilderService.php
Namespace: Addons\AiVideoCreator\Services

This service builds a video slideshow from images + TTS voiceover using ffmpeg.
It runs synchronously inside GenerateSlideshow job.

━━━ Constructor ━━━
public function __construct(
    private ElevenLabsClient $elevenlabs,
    private WhisperClient $whisper,
) {}

━━━ build(VcRender $render): string (returns storage path of output MP4) ━━━

$params = $render->provider_settings
// Expected params structure:
// {
//   images: [storage paths of uploaded images],
//   script: "voiceover script text...",
//   voice_id: "optional ElevenLabs voice ID",
//   music_volume: 0.3,   // 0.0–1.0
//   slide_duration: 3,   // seconds per slide
//   transition: "fade",  // 'fade' | 'none'
//   background_music_path: "optional storage path to music file"
// }

Step 1: Validate ffmpeg exists
  $ffmpeg = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg')
  if (!file_exists($ffmpeg)) throw new \RuntimeException('ffmpeg not found at: ' . $ffmpeg)

Step 2: Resolve image paths
  $imagePaths = array_map(fn($p) => storage_path('app/' . $p), $params['images'])
  foreach ($imagePaths as $path) {
    if (!file_exists($path)) throw new \RuntimeException("Image not found: $path")
  }

Step 3: Generate voiceover (if script provided)
  $voicePath = null
  if (!empty($params['script'])) {
    $provider = addon_setting('ai-video-creator', 'tts_provider', 'elevenlabs')
    $voicePath = sys_get_temp_dir() . '/' . uniqid('voice_') . '.mp3'
    if ($provider === 'elevenlabs') {
      $this->elevenlabs->textToSpeech($params['script'], $params['voice_id'] ?? '21m00Tcm4TlvDq8ikWAM', $voicePath)
    } elseif ($provider === 'openai') {
      // Use core OpenAI TTS endpoint
      Http::withToken(settings('openai_api_key'))
        ->withOptions(['sink' => $voicePath])
        ->post('https://api.openai.com/v1/audio/speech', [
          'model' => 'tts-1', 'input' => $params['script'],
          'voice' => $params['voice_id'] ?? 'alloy'
        ])
    }
  }

Step 4: Build ffmpeg command
  $slideDuration = $params['slide_duration'] ?? 3
  $totalImages   = count($imagePaths)
  $outputPath    = 'video-creator/' . $render->user_id . '/' . $render->ulid . '.mp4'
  $absOutputPath = storage_path('app/' . $outputPath)

  // Create file list for ffmpeg concat
  $concatList = tempnam(sys_get_temp_dir(), 'ffmpeg_concat_') . '.txt'
  $lines = array_map(
    fn($p) => "file '$p'\nduration $slideDuration",
    $imagePaths
  )
  $lines[] = "file '" . end($imagePaths) . "'";  // repeat last image (ffmpeg concat needs it)
  file_put_contents($concatList, implode("\n", $lines))

  // Base command: images → silent video
  $cmd = escapeshellarg($ffmpeg)
       . ' -f concat -safe 0 -i ' . escapeshellarg($concatList)
       . ' -vf "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,format=yuv420p"'
       . ' -r 25';

  // Add voiceover
  if ($voicePath && file_exists($voicePath)) {
    $cmd .= ' -i ' . escapeshellarg($voicePath) . ' -shortest';
    // Add background music if provided
    if (!empty($params['background_music_path'])) {
      $musicPath = storage_path('app/' . $params['background_music_path'])
      $vol = $params['music_volume'] ?? 0.3
      $cmd .= ' -i ' . escapeshellarg($musicPath)
           . ' -filter_complex "[1:a]volume=1[voice];[2:a]volume=' . $vol . '[music];[voice][music]amix=inputs=2:duration=first[aout]"'
           . ' -map 0:v -map "[aout]"'
    } else {
      $cmd .= ' -map 0:v -map 1:a'
    }
  }

  // Fade transition (simple approach: xfade filter between clips)
  // For simplicity in v1.0: just use concat without xfade
  // TODO v2.0: implement proper xfade transitions

  $cmd .= ' -c:v libx264 -c:a aac -b:a 192k'
       . ' -movflags +faststart'
       . ' -y ' . escapeshellarg($absOutputPath)

Step 5: Execute
  $output = []
  $exitCode = 0
  exec($cmd . ' 2>&1', $output, $exitCode)
  @unlink($concatList)
  if ($voicePath) @unlink($voicePath)

  if ($exitCode !== 0) {
    throw new \RuntimeException('ffmpeg failed: ' . implode("\n", array_slice($output, -5)))
  }

  return $outputPath

━━━ Notes on ffmpeg dependency ━━━
- ffmpeg must be installed on the server: `apt install ffmpeg` (Ubuntu)
- Path configurable via addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg')
- Admin Settings UI shows whether ffmpeg is detected (check file_exists on save)
- Shared hosting without shell_exec: slideshow feature not available — show admin warning

━━━ Also create: CleanupExpiredVideos.php job ━━━
Namespace: Addons\AiVideoCreator\Jobs
Queue: 'low'. Runs daily.

handle():
  VcRender::where('expires_at', '<=', now())
    ->whereNotNull('file_path')
    ->chunk(50, function($renders) {
      foreach ($renders as $render) {
        if ($render->file_path)      Storage::delete($render->file_path)
        if ($render->thumbnail_path) Storage::delete($render->thumbnail_path)
        $render->update(['file_path' => null, 'file_url' => null, 'thumbnail_path' => null, 'thumbnail_url' => null])
      }
    })
```

---

## STEP 9 — SubtitleService (Whisper)

### DEEPSEEK PROMPT 9

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/app/Services/SubtitleService.php
Namespace: Addons\AiVideoCreator\Services

━━━ generate(VcRender $render, string $format = 'srt', string $language = 'en'): VcSubtitle ━━━

Step 1: Check credits
  $credits = addon_setting('ai-video-creator', 'credits_subtitles', 10)
  if ($render->user->credits < $credits) throw CreditLimitException

Step 2: Create or update VcSubtitle row (status: queued)
  $subtitle = VcSubtitle::updateOrCreate(
    ['vc_render_id' => $render->id, 'format' => $format],
    ['status' => 'queued', 'language' => $language, 'credits_deducted' => $credits]
  )

Step 3: Deduct credits
  deduct_credits($render->user_id, $credits, 'Subtitle generation: ' . $render->ulid)

Step 4: Get video file path
  $videoPath = storage_path('app/' . $render->file_path)
  if (!file_exists($videoPath)) throw new \RuntimeException('Video file not found')

Step 5: Transcribe via configured provider
  $provider = addon_setting('ai-video-creator', 'subtitle_provider', 'whisper')
  $subtitle->update(['status' => 'processing', 'provider' => $provider])

  $result = match($provider) {
    'whisper'     => app(WhisperClient::class)->transcribe($videoPath),
    'assemblyai'  => $this->transcribeAssemblyAI($videoPath),
    default       => throw new \InvalidArgumentException("Unknown subtitle provider: $provider"),
  }
  // $result = ['text' => '...', 'segments' => [{start, end, text}, ...]]

Step 6: Convert segments to requested format
  $content = match($format) {
    'srt'  => $this->segmentsToSrt($result['segments']),
    'vtt'  => $this->segmentsToVtt($result['segments']),
    'json' => json_encode($result['segments']),
  }

Step 7: Save
  $subtitle->update([
    'status'   => 'completed',
    'content'  => $content,
    'segments' => $result['segments'],
  ])
  return $subtitle

━━━ Private helpers ━━━

segmentsToSrt(array $segments): string
  Each segment → "N\nHH:MM:SS,ms --> HH:MM:SS,ms\ntext\n\n"

segmentsToVtt(array $segments): string
  "WEBVTT\n\n" + segments formatted with HH:MM:SS.ms timestamps

transcribeAssemblyAI(string $videoPath): array
  Step 1: Upload file to AssemblyAI
    POST https://api.assemblyai.com/v2/upload (binary stream)
    Returns: upload_url
  Step 2: Submit transcript job
    POST https://api.assemblyai.com/v2/transcript
    Body: { audio_url: $uploadUrl }
    Returns: id
  Step 3: Poll until status=completed (synchronous in this method, max 3 min)
    while (true) { GET /v2/transcript/{id}; if status=completed → return; sleep(5) }
  Returns: same structure as Whisper { text, segments }
```

---

## STEP 10 — TrimmerService (ffmpeg)

### DEEPSEEK PROMPT 10

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/app/Services/TrimmerService.php
Namespace: Addons\AiVideoCreator\Services

Basic start/end trim using ffmpeg. No re-encoding (stream copy for speed).

━━━ trim(VcRender $render, float $startSeconds, float $endSeconds): string ━━━

Validates:
  $startSeconds >= 0 or throw
  $endSeconds > $startSeconds or throw
  $endSeconds - $startSeconds >= 1 or throw (min 1 second)

$ffmpeg = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg')
$inputPath = storage_path('app/' . $render->file_path)
$outputPath = 'video-creator/' . $render->user_id . '/' . $render->ulid . '_trimmed.mp4'
$absOutput  = storage_path('app/' . $outputPath)

$duration = $endSeconds - $startSeconds

$cmd = escapeshellarg($ffmpeg)
     . ' -i '       . escapeshellarg($inputPath)
     . ' -ss '      . $startSeconds        // seek input
     . ' -t '       . $duration            // duration
     . ' -c copy'                          // stream copy (no re-encode — fast)
     . ' -avoid_negative_ts make_zero'
     . ' -y '       . escapeshellarg($absOutput)

exec($cmd . ' 2>&1', $output, $exitCode)
if ($exitCode !== 0) throw new \RuntimeException('Trim failed: ' . implode(' ', array_slice($output,-3)))

// Replace original file with trimmed version
Storage::delete($render->file_path)
Storage::move($outputPath, $render->file_path)  // keep same path

// Update render metadata
$render->update([
  'duration_actual' => (int)($endSeconds - $startSeconds),
  'file_size_bytes' => Storage::size($render->file_path),
])

return $render->file_path

━━━ extractThumbnail(string $storagePath): ?string ━━━
Extract a thumbnail at 1-second mark for the video library.

$ffmpeg = addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg')
$inputPath  = storage_path('app/' . $storagePath)
$thumbPath  = str_replace('.mp4', '_thumb.jpg', $storagePath)
$absThumb   = storage_path('app/' . $thumbPath)

$cmd = escapeshellarg($ffmpeg)
     . ' -i ' . escapeshellarg($inputPath)
     . ' -ss 00:00:01.000 -vframes 1'
     . ' -vf "scale=480:-1"'
     . ' -y ' . escapeshellarg($absThumb)

exec($cmd . ' 2>&1', $output, $exitCode)
return $exitCode === 0 ? $thumbPath : null

━━━ getVideoDuration(string $storagePath): ?float ━━━
Use ffprobe (comes with ffmpeg) to get duration in seconds:

$ffprobe = str_replace('ffmpeg', 'ffprobe', addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg'))
$inputPath = storage_path('app/' . $storagePath)
$cmd = escapeshellarg($ffprobe)
     . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
     . escapeshellarg($inputPath)

$output = shell_exec($cmd)
return $output ? (float)trim($output) : null
```

---

## STEP 11 — Controllers + FormRequests

### DEEPSEEK PROMPT 11

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create controllers in addons/ai-video-creator/app/Http/Controllers/.
Namespace: Addons\AiVideoCreator\Http\Controllers

All user controllers: extend Controller, middleware ['auth', 'addon.video.enabled']
Pro gate at TOP of every user controller method:
  if (!isProAvailable()) abort(403, translate('Pro subscription required'))

━━━ User/VideoLibraryController ━━━

index():
  $folders  = VcFolder::forUser(auth()->id())->withCount('projects')->orderBy('sort_order')->get()
  $projects = VcProject::where('user_id', auth()->id())
    ->with(['folder', 'renders' => fn($q) => $q->latest()->limit(4)])
    ->withCount('renders')
    ->when(request('folder_id'), fn($q, $id) => $q->where('folder_id', $id))
    ->latest()->paginate(12)
  $recentRenders = VcRender::forUser(auth()->id())->completed()->latest()->limit(8)->get()
  return Inertia 'AiVideoCreator::User/Library' props: folders, projects, recentRenders

storeFolder(Request $request):
  Validate: name required max:100, color nullable regex:/#[0-9a-f]{6}/i
  VcFolder::create(['user_id' => auth()->id(), ...$request->validated()])
  return back()->with('flash','created')

storeProject(Request $request):
  Validate: name required max:255, folder_id nullable exists:vc_folders,id, color nullable
  Guard: if folder_id provided → folder must belong to auth user
  VcProject::create(['user_id' => auth()->id(), ...$request->validated()])
  return back()->with('flash','created')

destroyRender(VcRender $render):
  abort_if($render->user_id !== auth()->id(), 403)
  abort_if($render->status === 'processing', 422, 'Cannot delete a render in progress')
  if ($render->file_path)      Storage::delete($render->file_path)
  if ($render->thumbnail_path) Storage::delete($render->thumbnail_path)
  $render->delete()
  return back()->with('flash','deleted')

━━━ User/VideoCreatorController ━━━

create():
  $projects = VcProject::where('user_id', auth()->id())->get(['id','name'])
  $avatars = [];
  $voices  = [];
  $avatarProvider = addon_setting('ai-video-creator', 'avatar_provider', 'heygen')
  try {
    if ($avatarProvider === 'heygen') {
      $avatars = app(HeyGenClient::class)->listAvatars()
      $voices  = app(HeyGenClient::class)->listVoices()
    }
  } catch (\Throwable) {}   // don't fail if provider unavailable — show empty lists
  return Inertia 'AiVideoCreator::User/Creator'
  props: projects, avatars, voices, creditCosts (all credit settings for UI display)

store(VideoCreatorRequest $request):
  $user = auth()->user()

  // Handle media upload for image-to-video / slideshow
  $inputMediaPath = null
  if ($request->hasFile('image') && $request->type === 'image_to_video') {
    $file = $request->file('image')
    abort_if($file->getSize() > 10 * 1024 * 1024, 422, 'Image must be under 10MB')
    $inputMediaPath = $file->store('video-creator/' . $user->id . '/inputs', 'local')
  }

  // For slideshow: handle multiple images
  $slideshowParams = []
  if ($request->type === 'slideshow' && $request->hasFile('slides')) {
    $imagePaths = []
    foreach ($request->file('slides') as $slide) {
      $imagePaths[] = $slide->store('video-creator/' . $user->id . '/inputs', 'local')
    }
    $slideshowParams = [
      'images'            => $imagePaths,
      'script'            => $request->script,
      'voice_id'          => $request->voice_id,
      'slide_duration'    => $request->slide_duration ?? 3,
      'music_volume'      => $request->music_volume ?? 0.3,
    ]
  }

  $render = app(VideoProviderService::class)->createRender($user, [
    'type'              => $request->type,
    'project_id'        => $request->project_id,
    'title'             => $request->title,
    'prompt'            => $request->prompt,
    'script'            => $request->script,
    'duration'          => $request->duration ?? 5,
    'aspect_ratio'      => $request->aspect_ratio ?? '16:9',
    'input_media_path'  => $inputMediaPath,
    'provider_settings' => array_merge($request->provider_settings ?? [], $slideshowParams),
  ])

  // Dispatch the correct job
  $job = match($render->type) {
    'text_to_video'   => new GenerateTextToVideo($render->id),
    'image_to_video'  => new GenerateImageToVideo($render->id),
    'avatar_video'    => new GenerateAvatarVideo($render->id),
    'slideshow'       => new GenerateSlideshow($render->id),
  }
  dispatch($job->onQueue('ai'))

  return response()->json(['render_id' => $render->id, 'ulid' => $render->ulid, 'status' => 'queued'])

━━━ User/VideoViewerController ━━━

show(VcRender $render):
  abort_if($render->user_id !== auth()->id(), 403)
  $render->load('subtitles', 'project')
  return Inertia 'AiVideoCreator::User/Viewer' props: render

status(VcRender $render): JsonResponse
  abort_if($render->user_id !== auth()->id() && !$render->share_enabled, 403)
  return response()->json([
    'status'        => $render->status,
    'file_url'      => $render->file_url,
    'thumbnail_url' => $render->thumbnail_url,
    'error_message' => $render->error_message,
    'completed_at'  => $render->completed_at,
  ])

generateSubtitles(Request $request, VcRender $render): JsonResponse
  abort_if($render->user_id !== auth()->id(), 403)
  abort_if($render->status !== 'completed', 422, 'Video must be completed before generating subtitles')
  Validate: format in:srt,vtt,json, language nullable max:5
  $subtitle = app(SubtitleService::class)->generate($render, $request->format, $request->language ?? 'en')
  return response()->json($subtitle)

trim(Request $request, VcRender $render): JsonResponse
  abort_if($render->user_id !== auth()->id(), 403)
  abort_if($render->status !== 'completed', 422)
  Validate: start_seconds numeric min:0, end_seconds numeric
  app(TrimmerService::class)->trim($render, $request->start_seconds, $request->end_seconds)
  $render->refresh()
  return response()->json(['file_url' => $render->file_url, 'duration_actual' => $render->duration_actual])

toggleShare(VcRender $render): JsonResponse
  abort_if($render->user_id !== auth()->id(), 403)
  $render->update(['share_enabled' => !$render->share_enabled])
  return response()->json(['share_enabled' => $render->share_enabled, 'share_token' => $render->share_token])

━━━ Public/ShareController ━━━
(No auth required — public share page)
show(string $token):
  $render = VcRender::where('share_token', $token)->where('share_enabled', true)
    ->where('status', 'completed')->firstOrFail()
  return Inertia 'AiVideoCreator::Public/Share' props: render (only: title, file_url, thumbnail_url, duration_actual)
  // Do NOT expose user_id, prompt, script, credits, metadata

━━━ Admin/VideoAdminController ━━━
Middleware: ['auth:admin', 'admin.permission:addon.video.manage']

overview():
  return Inertia 'AiVideoCreator::Admin/Overview'
  props:
    total_renders: VcRender::count()
    processing:    VcRender::processing()->count()
    completed_today: VcRender::whereDate('completed_at', today())->where('status','completed')->count()
    failed_today:    VcRender::whereDate('created_at', today())->where('status','failed')->count()
    total_storage_gb: VcRender::sum('file_size_bytes') / 1024 / 1024 / 1024
    by_type: VcRender::selectRaw('type, COUNT(*) as count')->groupBy('type')->get()
    by_provider: VcRender::selectRaw('provider, COUNT(*) as count, AVG(poll_attempts) as avg_polls')->groupBy('provider')->get()
    top_users: VcRender::selectRaw('user_id, COUNT(*) as renders, SUM(credits_deducted) as credits')
                 ->groupBy('user_id')->orderByDesc('renders')->limit(10)
                 ->with('user:id,name,email')->get()

━━━ Admin/VideoSettingsController ━━━
Middleware: ['auth:admin', 'admin.permission:addon.video.settings']

edit():
  $ffmpegFound = file_exists(addon_setting('ai-video-creator', 'ffmpeg_path', '/usr/bin/ffmpeg'))
  return Inertia 'AiVideoCreator::Admin/Settings'
  props: settings (all video_ settings), ffmpegFound

update(VideoSettingsRequest $request):
  foreach ($request->validated() as $key => $value) {
    addon_setting_set('ai-video-creator', $key, $value)
  }
  return back()->with('flash', 'saved')

━━━ FormRequests ━━━

VideoCreatorRequest:
  type: required in:text_to_video,image_to_video,avatar_video,slideshow
  prompt: required_if:type,text_to_video,image_to_video | max:2000
  script: required_if:type,avatar_video,slideshow | max:5000
  duration: integer in:5,10
  aspect_ratio: in:16:9,9:16,1:1
  project_id: nullable exists:vc_projects,id  (scoped: must belong to auth user)
  title: nullable max:255
  image: required_if:type,image_to_video | file mimes:jpg,jpeg,png,webp max:10240
  slides: required_if:type,slideshow | array min:2 max:{carousel_max_slides from settings}
  slides.*: file mimes:jpg,jpeg,png,webp max:5120
  slide_duration: nullable integer min:1 max:10
  provider_settings: nullable array
  voice_id: nullable string
  music_volume: nullable numeric min:0 max:1

VideoSettingsRequest: validate all settings fields.
```

---

## STEP 12 — Routes

### DEEPSEEK PROMPT 12

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/routes/web.php

━━━ USER ROUTES ━━━
Route::middleware(['web', 'auth', 'permission:addon.video.manage'])
  ->prefix('video-creator')
  ->name('addon.video.user.')
  ->group(function () {

    // Library
    Route::get('/',                       [VideoLibraryController::class, 'index'])->name('library')
    Route::post('/folders',               [VideoLibraryController::class, 'storeFolder'])->name('folders.store')
    Route::post('/projects',              [VideoLibraryController::class, 'storeProject'])->name('projects.store')
    Route::delete('/renders/{render}',    [VideoLibraryController::class, 'destroyRender'])->name('renders.destroy')

    // Creator
    Route::get('/create',                 [VideoCreatorController::class, 'create'])->name('create')
    Route::post('/create',                [VideoCreatorController::class, 'store'])->name('store')
      ->middleware('throttle:10,1')      // max 10 generation requests per minute

    // Viewer + actions
    Route::get('/renders/{render}',       [VideoViewerController::class, 'show'])->name('renders.show')
    Route::get('/renders/{render}/status',[VideoViewerController::class, 'status'])->name('renders.status')
    Route::post('/renders/{render}/subtitles', [VideoViewerController::class, 'generateSubtitles'])->name('renders.subtitles')
      ->middleware('throttle:5,1')
    Route::post('/renders/{render}/trim', [VideoViewerController::class, 'trim'])->name('renders.trim')
    Route::post('/renders/{render}/share',[VideoViewerController::class, 'toggleShare'])->name('renders.share')
  })

// Public share (no auth)
Route::middleware(['web'])
  ->prefix('video')
  ->name('addon.video.public.')
  ->group(function () {
    Route::get('/share/{token}', [ShareController::class, 'show'])->name('share')
  })

━━━ ADMIN ROUTES ━━━
Route::middleware(['web', 'auth:admin'])
  ->prefix('admin/video-creator')
  ->name('addon.video.admin.')
  ->group(function () {
    Route::get('/',         [VideoAdminController::class, 'overview'])->name('overview')
      ->middleware('admin.permission:addon.video.manage')
    Route::get('/settings', [VideoSettingsController::class, 'edit'])->name('settings')
      ->middleware('admin.permission:addon.video.settings')
    Route::put('/settings', [VideoSettingsController::class, 'update'])
      ->middleware('admin.permission:addon.video.settings')
  })
```

---

## STEP 13 — Vue Pages

### DEEPSEEK PROMPT 13

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13 + Vue 3 + Inertia.js).

Create Vue pages in addons/ai-video-creator/resources/js/Pages/.
All: <script setup lang="ts">, TypeScript, Tabler Icons, Tailwind v4.
MakeAI design tokens: emerald primary, ocean blue accent.
$t() for all strings. Never Options API. sessionStorage for polling state.

━━━ PAGE 1: User/Library.vue — Video Library ━━━

Three-section layout:

TOP: Stat bar (total renders | processing badge | storage used / limit)

LEFT SIDEBAR (collapsible on mobile):
  - [+ New Folder] button
  - Folder list: color dot + folder name + project count
    Click folder → filters projects grid to that folder
  - [All Videos] option at top (no folder filter)

MAIN GRID:
  Project cards (3 cols desktop, 2 tablet, 1 mobile):
    - Thumbnail (first render thumbnail or placeholder gradient)
    - Project name
    - Folder badge (color-coded)
    - Render count + total duration
    - [Open] button → expands to show render list inline
    - [+ New Video] button → navigate to Creator pre-selecting this project

  Within each expanded project: Render mini-cards (horizontal scroll):
    - Thumbnail (or status skeleton if processing)
    - Status badge (queued=gray, processing=spinning, completed=green, failed=red)
    - Type icon (ti-video for T2V, ti-photo for I2V, ti-user for avatar, ti-slideshow for slideshow)
    - Duration badge
    - [▶ Open] → navigate to Viewer
    - [...] menu: Download | Copy Share Link (if share_enabled) | Delete

  Recent renders section (bottom): horizontal scroll of last 8 renders across all projects

Processing renders: poll GET /video-creator/renders/{ulid}/status every 10 seconds
  (use setInterval, store in composable useRenderPolling)
  When status changes to 'completed': update card, show toast "Your video is ready!"
  Also listen on Reverb: Echo.private('user.' + userId).listen('VideoRenderCompleted', handler)

━━━ PAGE 2: User/Creator.vue — Video Creator ━━━

Three-tab wizard interface:
  Tab 1: Choose Type | Tab 2: Configure | Tab 3: Review & Generate

TAB 1 — Choose Type:
  Four large cards with icon, label, description, credit cost:
    🎬 Text to Video — "Generate a video from a text prompt"   [50–100 credits]
    🖼️→🎬 Image to Video — "Animate any image into a video"    [40 credits]
    👤 AI Avatar — "Create a talking head video from script"    [80 credits per 30s]
    📷 Slideshow — "Turn images into a narrated slideshow"      [30 credits per min]
  Click a card → advance to Tab 2

TAB 2 — Configure (content varies by type):

  TEXT TO VIDEO:
    Prompt textarea (large, 2000 char limit + counter)
    Duration toggle: [5s] [10s]  (shows credit cost per selection)
    Aspect ratio: [16:9 🖥️] [9:16 📱] [1:1 ⬛]
    Title input (optional, max 100 chars)
    Project selector (dropdown)
    Provider badge: "Powered by {provider name}"

  IMAGE TO VIDEO:
    Image uploader (drag-drop zone, max 10MB, jpg/png/webp)
    Prompt textarea (optional motion hint, max 500 chars)
    Duration toggle: [5s] [10s]
    Aspect ratio select
    Preview of uploaded image

  AI AVATAR:
    Script textarea (required, 5000 char limit, word count shown)
    Avatar picker: grid of avatar thumbnails (from HeyGen/D-ID API)
      Show "No avatars configured" if provider keys not set
    Voice picker: dropdown of available voices with play sample button (if provider supports preview)
    Duration estimate: auto-calculated from script length (avg 130 words/min)
    Credit estimate: calculated and shown in real-time

  SLIDESHOW:
    Multi-image uploader (min 2, max N slides, drag to reorder)
    Script/voiceover textarea (optional — leave empty for music-only slideshow)
    Voice selector (if script provided)
    Slide duration: [2s] [3s] [5s] per slide
    Background music toggle + upload (optional, mp3 only, max 10MB)
    Music volume slider (if music enabled)
    Estimated video duration: auto-calculated (slides × slide_duration)

TAB 3 — Review & Generate:
  Summary card: Type | Provider | Duration | Credits to deduct | Current balance
  Insufficient credits warning (if balance < cost): link to buy credits
  [← Back] [🎬 Generate Video] buttons
  On Generate: POST /video-creator/create → redirects to Library with toast + auto-polling

━━━ PAGE 3: User/Viewer.vue — Video Viewer ━━━

Layout: large video player (left 2/3) + info panel (right 1/3)

VIDEO PLAYER (left):
  HTML5 <video> element with controls
  Subtitle track (if VTT available): <track kind="subtitles" src=...>
  Status overlay if not completed:
    queued: spinner + "Queued..."
    processing: progress ring + "Generating your video..." + live status updates
    failed: error icon + error message + [Retry] button
    Poll every 5s while processing; listen on Reverb for VideoRenderCompleted

  Below player:
    Trim bar: start/end sliders with seconds display (only shown for completed videos)
    [✂️ Trim] button → POST trim endpoint, then reload video src

INFO PANEL (right):
  Title (editable inline — PATCH on blur)
  Type badge | Provider badge | Duration | Resolution | File size
  Created date | Credits used

  ACTIONS group:
    [⬇ Download MP4] → direct link to file_url
    [📋 Subtitles] → dropdown: [Generate SRT] [Generate VTT] | shows existing subtitles download
    [🔗 Share] → toggle share on/off, copy share URL to clipboard

  SUBTITLES section (shown when subtitles exist):
    Language | Format | Status (processing spinner / completed with download link)
    [Download SRT] [Download VTT]
    In-player subtitle preview (if VTT available)

  PROJECT section:
    Current project name (editable)
    [Move to project] dropdown

━━━ PAGE 4: Public/Share.vue — Public Share Page ━━━
No auth, no layout sidebar.
  App logo + "Shared video from {settings('app_name')}"
  Video player (HTML5, controls, no download button)
  Title (if set), Duration
  Footer: "Create AI videos at {app_url}"
  NO: prompts, credits, user info, metadata

━━━ PAGE 5: Admin/Overview.vue ━━━
Stat cards: Total Renders | Processing | Completed Today | Failed Today | Storage Used
By type: horizontal bar chart (recharts)
By provider: table with avg_polls (higher avg = slower provider)
Top users table: User | Renders | Credits Spent

━━━ PAGE 6: Admin/Settings.vue ━━━
Sections:
  Providers (Text/Image Video): Provider select + API key fields per provider
  Providers (Avatar): Provider select + API key fields
  Audio/Subtitles: TTS provider + keys, subtitle provider + keys
  Credits: credit cost fields per render type (all number inputs)
  Storage & Limits: max_video_duration, max_storage_mb_per_user, auto_delete_days
  Technical: ffmpeg_path + "ffmpeg detected: ✓" / "⚠ ffmpeg not found" badge
             poll_interval_seconds, max_poll_attempts
[Save Settings]

━━━ COMPOSABLE: useRenderPolling.ts ━━━
Manages polling for a single render. Used in Library and Viewer.

export function useRenderPolling(renderUlid: string, initialStatus: string) {
  const status = ref(initialStatus)
  const render = ref(null)
  let intervalId: number | null = null
  let echoChannel: any = null

  function startPolling() {
    if (['completed','failed','cancelled'].includes(status.value)) return
    // Reverb WebSocket first
    echoChannel = window.Echo?.private('user.' + currentUserId)
      .listen('VideoRenderCompleted', (e: any) => {
        if (e.ulid === renderUlid) { status.value = 'completed'; stopPolling() }
      })
    // HTTP fallback every 10s
    intervalId = setInterval(async () => {
      const res = await axios.get('/video-creator/renders/' + renderUlid + '/status')
      status.value = res.data.status
      if (['completed','failed'].includes(res.data.status)) stopPolling()
    }, 10000)
  }

  function stopPolling() {
    if (intervalId) clearInterval(intervalId)
    echoChannel?.stopListening('VideoRenderCompleted')
  }

  onMounted(() => startPolling())
  onUnmounted(() => stopPolling())

  return { status, startPolling, stopPolling }
}
```

---

## STEP 14 — Pest Tests

### DEEPSEEK PROMPT 14

```
You are building ADDON 05: AI Video Creator for MakeAI (Laravel 13).

Create: addons/ai-video-creator/tests/Feature/VideoCreatorTest.php
Namespace: Addons\AiVideoCreator\Tests\Feature
PestPHP syntax. RefreshDatabase. Http::fake() for all provider calls.

━━━ ACCESS CONTROL ━━━
it('blocks access when isProAvailable returns false')
it('blocks access when addon is disabled via settings')
it('public share page is accessible without auth for enabled share tokens')
it('public share page returns 404 for disabled share tokens')

━━━ RENDER CREATION ━━━
it('creates a text-to-video render and dispatches GenerateTextToVideo job')
it('creates an image-to-video render with uploaded image stored in correct path')
it('creates an avatar video render with script')
it('creates a slideshow render with multiple uploaded images')
it('deducts credits upfront on render creation')
it('rejects render when user has insufficient credits')
it('rejects render when user has reached storage limit')
it('rejects image-to-video when no image is uploaded')
it('rejects slideshow when fewer than 2 slides uploaded')

━━━ POLLING JOB ━━━
it('PollVideoStatus marks render as completed and downloads video file when provider returns done')
it('PollVideoStatus requeues itself when provider returns processing')
it('PollVideoStatus marks render as failed and refunds credits after max_poll_attempts exceeded')
it('PollVideoStatus marks render as failed and refunds credits when provider returns failure')
it('PollVideoStatus broadcasts VideoRenderCompleted event on success')
it('PollVideoStatus does nothing for a render that is already completed or cancelled')

━━━ INDIVIDUAL PROVIDER JOBS ━━━
it('GenerateTextToVideo submits to Kling and stores provider_job_id')
it('GenerateImageToVideo encodes image to base64 before submission')
it('GenerateAvatarVideo uses HeyGen by default')
it('GenerateSlideshow does not poll — completes synchronously via SlideshowBuilderService')
it('job failed() method marks render as failed and refunds credits')

━━━ SUBTITLE SERVICE ━━━
it('generates SRT subtitles using Whisper and stores them')
it('generates VTT subtitles on request')
it('subtitle generation deducts correct credits')
it('subtitle generation rejects if video is not completed')

━━━ TRIMMER SERVICE ━━━
it('trim rejects end_seconds <= start_seconds')
it('trim rejects duration shorter than 1 second')
it('trim stores updated file and updates duration_actual on render')

━━━ LIBRARY & VIEWER ━━━
it('library returns only renders belonging to current user')
it('viewer returns 403 for renders not belonging to current user')
it('share toggle enables share link and returns share_token')
it('share toggle disables share link')
it('destroy deletes video file from storage')
it('destroy rejects delete while render is processing')

━━━ CREDITS REFUND ━━━
it('refundCredits adds credits back to user balance and creates credit_transaction row')
it('refundCredits does not double-refund on second call')

━━━ CLEANUP ━━━
it('CleanupExpiredVideos deletes files for expired renders and nullifies paths')
it('CleanupExpiredVideos skips renders with null expires_at')

For Http::fake:
  Http::fake([
    'api.klingai.com/*'   => Http::response(['code'=>0,'data'=>['task_id'=>'kling_123']], 200),
    'api.dev.runwayml.com/*' => Http::response(['id'=>'runway_456'], 200),
    'api.heygen.com/*'    => Http::response(['data'=>['video_id'=>'heygen_789']], 200),
    'api.d-id.com/*'      => Http::response(['id'=>'did_012'], 201),
    'api.pika.art/*'      => Http::response(['id'=>'pika_345'], 200),
  ])

For ffmpeg tests: mock TrimmerService and SlideshowBuilderService via
  app()->instance(TrimmerService::class, Mockery::mock(TrimmerService::class))
  (never call real ffmpeg in tests)
```

---

## IMPLEMENTATION SEQUENCE NOTES

1. **Steps 1–4** first — scaffold, migrations, models. Run `php artisan migrate` before any service code.
2. **Step 5** — build provider clients in this order: Kling (most documented), Pika, Runway, HeyGen, D-ID, Minimax. Build WhisperClient and ElevenLabsClient last.
3. **Step 6** — VideoProviderService ties everything together. Build after all clients are stubbed.
4. **Steps 7–10** — jobs + services. Test GenerateTextToVideo + PollVideoStatus end-to-end with Http::fake before building the others.
5. **Step 11** — controllers. The `store()` method is the critical path — test it manually before the UI.
6. **Step 13** — Vue. Build Library first (foundation), then Creator, then Viewer. Share page last.
7. **Step 14** — run tests throughout, not just at the end.

---

## PRE-BUILD CHECKLIST

```bash
# Verify ffmpeg is available on the target server
ffmpeg -version
ffprobe -version

# Required PHP extensions
php -m | grep -E "fileinfo|gd|zip"

# Check storage disk config (config/filesystems.php)
# The addon uses Storage::put/delete/url — ensure 'local' disk is writable
# and 'public' disk symlink exists: php artisan storage:link
```

---

## CRITICAL INVARIANTS (repeat for every DeepSeek session)

```
AI ENGINE:     App\Services\AI\AiService for text/script generation ONLY
               Video provider APIs: direct Http facade calls in provider client classes
               NEVER route video API calls through AiService — it doesn't support async job APIs
STREAMING:     N/A for video generation — async poll pattern used instead
PROGRESS:      Reverb (VideoRenderCompleted event) primary; HTTP polling fallback every 10s
SESSION:       sessionStorage for polling abort state if needed
APP NAME:      settings('app_name') — NEVER hardcode "MakeAI"
ADDON CONFIG:  addon_setting('ai-video-creator', 'key') — NEVER settings('key') with prefix
               addon_setting_set('ai-video-creator', 'key', $value) to write
PRO GATE:      isProAvailable() checked at TOP of every user controller method — no exceptions
QUEUES:        ai → generation + polling jobs | media → (reserved) | low → cleanup
CREDITS:       Deducted UPFRONT; refunded in job failed() via VideoProviderService::refundCredits()
API KEYS:      All from addon_setting() encrypted fields — NEVER hardcoded, NEVER logged
TOKENS:        Public share via share_token (random 64-char string); never expose ulid alone
FFMPEG:        Path from addon_setting(); check file_exists before any exec() call
TRANSLATE:     translate() PHP / $t() Vue — ALL user-facing strings
N+1:           Always eager load: with(['user','renders','subtitles','project'])
```

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

# ADDON 08: Social Media Scheduler Pro — Implementation Guide

> **Slug:** `social-scheduler`
> **Queue:** `social` (publishing jobs), `ai` (caption generation), `low` (analytics ingestion)
> **AI engine:** `laravel/ai` via `AiService` only
> **OAuth:** Laravel Socialite (already in core) — extended for Instagram Graph API,
>   LinkedIn v2, X API v2, TikTok Login Kit, Pinterest API v5, YouTube Data API v3
> **Depends on core:** `PublishToLinkedIn`, `PublishToTwitter`, `PublishToInstagram`,
>   `PublishToFacebook` jobs already exist — this addon EXTENDS them, does not replace them

---

## WHAT THIS ADDON BUILDS

A full social media scheduling suite layered on top of core's basic social features.
Users connect their social accounts via OAuth, write posts with AI-generated captions,
schedule them on a drag-drop calendar, build carousels, set up RSS auto-posting, run
approval workflows for team drafts, and review per-post analytics.

---

## ARCHITECTURE OVERVIEW

```
User connects account (OAuth) → social_accounts stored (encrypted tokens)
        │
        ▼
User writes post in composer
  ├── AI generates platform-specific caption (AiService::stream)
  ├── Attaches media (images/video → S3/local)
  ├── Selects platforms (multi-select)
  └── Picks schedule time (calendar picker) OR "Best time" AI suggestion

Post saved → social_scheduled_posts (status: draft / pending_approval / scheduled)
        │
        ▼
Approval flow (if enabled): team draft → admin approves → status → scheduled
        │
        ▼
PublishSocialPost job (queue: social, every 5 min cron)
  ├── PublishToInstagram (Graph API container → publish)
  ├── PublishToFacebook (Graph API page post)
  ├── PublishToLinkedIn (v2 ugcPosts / assets API)
  ├── PublishToTwitter (X API v2 tweet/thread)
  ├── PublishToTikTok (TikTok Login Kit video upload)
  ├── PublishToPinterest (Pins API)
  └── PublishToYouTube (YouTube Data API v3 upload)

Result → social_post_platforms.status (published / failed) + external_post_id stored

Analytics pull (cron daily) → FetchPostAnalytics job → social_post_analytics upserted
```

---

## STEP-BY-STEP BUILD ORDER

```
Step 1  → addon.json + AddonServiceProvider
Step 2  → Migrations (7 tables)
Step 3  → Models + Relationships
Step 4  → Seeder (demo data + default settings)
Step 5  → SocialAccountService (OAuth connect/disconnect/token refresh)
Step 6  → Platform Publisher jobs (7 platforms)
Step 7  → PublishSocialPost orchestrator job
Step 8  → AiCaptionService (platform-aware caption streaming)
Step 9  → RssFeedService + PollRssFeeds job
Step 10 → BestTimeService (AI-suggested posting time)
Step 11 → FetchPostAnalytics job
Step 12 → Admin Controllers + FormRequests
Step 13 → User Controllers + FormRequests (composer, calendar, accounts, analytics)
Step 14 → Routes
Step 15 → Vue Pages (Calendar, Composer, Accounts, Analytics, Admin settings)
Step 16 → Pest Tests
```

---

## STEP 1 — addon.json + AddonServiceProvider

### DEEPSEEK PROMPT 1

```
You are building an addon for MakeAI (Laravel 13 + Vue 3 + Inertia.js).
Create two files for ADDON 08: Social Media Scheduler Pro.

━━━ FILE 1: addons/social-scheduler/addon.json ━━━

{
  "name": "Social Media Scheduler Pro",
  "slug": "social-scheduler",
  "version": "1.0.0",
  "description": "Full social media scheduling suite with AI captions, drag-drop calendar, carousels, RSS auto-post, and approval workflows.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 1,
  "requires_pro": false,
  "admin_menu": [
    { "parent": "Content",  "label": "Social Scheduler",  "route": "addon.social.admin.overview",  "icon": "ti-calendar-share",  "permission": "addon.social.manage" },
    { "parent": "Settings", "label": "Social Scheduler",  "route": "addon.social.admin.settings",  "icon": "ti-settings",        "permission": "addon.social.settings" }
  ],
  "settings": [
    { "key": "enabled",                    "type": "boolean",  "label": "Enable Social Scheduler",            "default": true },
    { "key": "approval_required",          "type": "boolean",  "label": "Require approval before publishing", "default": false },
    { "key": "max_accounts_per_user",      "type": "integer",  "label": "Max connected accounts per user",    "default": 10 },
    { "key": "max_media_mb",               "type": "integer",  "label": "Max media upload size (MB)",         "default": 50 },
    { "key": "ai_model",                   "type": "string",   "label": "AI model for captions",             "default": "gpt-4o-mini" },
    { "key": "best_time_model",            "type": "string",   "label": "AI model for best-time analysis",   "default": "gpt-4o-mini" },
    { "key": "rss_poll_interval_minutes",  "type": "integer",  "label": "RSS poll interval (minutes)",        "default": 60 },
    { "key": "analytics_pull_enabled",     "type": "boolean",  "label": "Pull post analytics daily",          "default": true },
    { "key": "carousel_max_slides",        "type": "integer",  "label": "Max carousel slides",                "default": 10 },
    { "key": "first_comment_enabled",      "type": "boolean",  "label": "Enable first-comment scheduler (Instagram)", "default": true }
  ],
  "permissions": [
    { "slug": "addon.social.manage",   "name": "Use Social Scheduler",      "group": "Social Scheduler" },
    { "slug": "addon.social.approve",  "name": "Approve Scheduled Posts",   "group": "Social Scheduler" },
    { "slug": "addon.social.settings", "name": "Manage Social Scheduler Settings", "group": "Social Scheduler" }
  ],
  "hooks": []
}

━━━ FILE 2: addons/social-scheduler/AddonServiceProvider.php ━━━

Namespace: Addons\SocialScheduler

In register():
  - Bind SocialAccountService, AiCaptionService, BestTimeService, RssFeedService as singletons

In boot() — only if is_addon_active('social-scheduler'):
  - Load routes: routes/web.php, routes/api.php
  - Load migrations: database/migrations/
  - Load views (Blade) from resources/views/
  - Share via Inertia::share('socialScheduler', fn() => [...]):
      enabled, approval_required, max_accounts_per_user, max_media_mb,
      carousel_max_slides, first_comment_enabled
      Never share: ai_model, best_time_model, API tokens
  - Register scheduled jobs (INSIDE boot, not in console.php — addon's own schedule):
      Schedule::job(new PublishSocialPost)->everyFiveMinutes()
        (note: core's PublishScheduledPost handles core posts; this handles addon posts)
      Schedule::job(new PollRssFeeds)->everyNMinutes(addon_setting('social-scheduler', 'rss_poll_interval_minutes', 60))
      Schedule::job(new FetchPostAnalytics)->dailyAt('05:00')->when(addon_setting('social-scheduler', 'analytics_pull_enabled', true))

Use settings() with prefix 'social_' for all addon settings.
```

---

## STEP 2 — Migrations (7 tables)

### DEEPSEEK PROMPT 2

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create 7 migration files in addons/social-scheduler/database/migrations/.
All tables prefixed ss_ (social scheduler). All PKs: bigint unsigned auto-increment.

━━━ MIGRATION 1: create_ss_social_accounts_table ━━━

ss_social_accounts
  id
  user_id             bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  platform            enum('instagram','facebook','twitter','linkedin',
                           'tiktok','pinterest','youtube') NOT NULL
  platform_user_id    varchar(100) NOT NULL         -- platform's UID for this account
  platform_username   varchar(100) NULL             -- @handle or page name
  platform_name       varchar(150) NULL             -- display name
  avatar_url          varchar(500) NULL
  access_token        text NOT NULL                 -- encrypted with APP_KEY
  refresh_token       text NULL                     -- encrypted, nullable (not all platforms)
  token_expires_at    timestamp NULL
  scopes              json NULL                     -- granted OAuth scopes
  page_id             varchar(100) NULL             -- Facebook/Instagram page ID
  page_name           varchar(150) NULL
  account_type        enum('personal','page','business') DEFAULT 'personal'
  is_active           boolean DEFAULT true
  follower_count      int UNSIGNED DEFAULT 0
  followers_updated_at timestamp NULL
  created_at, updated_at
  UNIQUE (user_id, platform, platform_user_id)
  INDEX (user_id, platform, is_active)

━━━ MIGRATION 2: create_ss_scheduled_posts_table ━━━

ss_scheduled_posts
  id
  ulid                char(26) UNIQUE NOT NULL      -- public-facing ID
  user_id             bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  title               varchar(255) NULL             -- internal label only
  caption             text NOT NULL                 -- primary caption
  hashtags            text NULL                     -- stored as plain text
  platforms           json NOT NULL                 -- ['instagram','facebook','twitter',...]
  status              enum('draft','pending_approval','scheduled',
                           'publishing','published','partial','failed','cancelled')
                      DEFAULT 'draft'
  post_type           enum('single','carousel','thread','story','reel') DEFAULT 'single'
  scheduled_at        timestamp NULL                -- NULL = draft
  published_at        timestamp NULL
  is_rss_auto         boolean DEFAULT false         -- true if created by RSS auto-post
  rss_feed_id         bigint UNSIGNED NULL FK → ss_rss_feeds.id ON DELETE SET NULL
  approved_by         bigint UNSIGNED NULL FK → users.id ON DELETE SET NULL
  approved_at         timestamp NULL
  rejection_reason    text NULL
  first_comment       text NULL                     -- Instagram first comment text
  platform_overrides  json NULL                     -- {"twitter":{"caption":"shorter version"}}
  created_at, updated_at
  INDEX (user_id, status, scheduled_at)
  INDEX (scheduled_at, status)

━━━ MIGRATION 3: create_ss_post_media_table ━━━

ss_post_media
  id
  ss_scheduled_post_id bigint UNSIGNED NOT NULL FK → ss_scheduled_posts.id ON DELETE CASCADE
  carousel_slide_id   bigint UNSIGNED NULL FK → ss_carousel_slides.id ON DELETE SET NULL
  type                enum('image','video','gif') DEFAULT 'image'
  path                varchar(500) NOT NULL         -- storage path
  url                 varchar(500) NOT NULL          -- public URL
  mime_type           varchar(100) NULL
  file_size_bytes     int UNSIGNED DEFAULT 0
  width               smallint UNSIGNED NULL
  height              smallint UNSIGNED NULL
  duration_seconds    smallint UNSIGNED NULL        -- video/gif only
  alt_text            varchar(500) NULL             -- accessibility
  sort_order          smallint DEFAULT 0
  created_at, updated_at
  INDEX (ss_scheduled_post_id, sort_order)

━━━ MIGRATION 4: create_ss_carousel_slides_table ━━━

ss_carousel_slides
  id
  ss_scheduled_post_id bigint UNSIGNED NOT NULL FK → ss_scheduled_posts.id ON DELETE CASCADE
  slide_index         smallint UNSIGNED NOT NULL
  caption             text NULL                     -- per-slide caption (LinkedIn/Instagram)
  created_at, updated_at
  UNIQUE (ss_scheduled_post_id, slide_index)

(media for each slide is in ss_post_media linked by carousel_slide_id)

━━━ MIGRATION 5: create_ss_post_platforms_table ━━━

ss_post_platforms
  id
  ss_scheduled_post_id bigint UNSIGNED NOT NULL FK → ss_scheduled_posts.id ON DELETE CASCADE
  ss_social_account_id bigint UNSIGNED NOT NULL FK → ss_social_accounts.id ON DELETE CASCADE
  platform            varchar(30) NOT NULL
  status              enum('pending','publishing','published','failed','skipped') DEFAULT 'pending'
  external_post_id    varchar(255) NULL             -- ID returned by the platform API
  external_post_url   varchar(500) NULL
  error_message       text NULL
  published_at        timestamp NULL
  attempt_count       tinyint UNSIGNED DEFAULT 0
  created_at, updated_at
  UNIQUE (ss_scheduled_post_id, ss_social_account_id)
  INDEX (status, platform)

━━━ MIGRATION 6: create_ss_rss_feeds_table ━━━

ss_rss_feeds
  id
  user_id             bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  url                 varchar(500) NOT NULL
  title               varchar(255) NULL
  platforms           json NOT NULL                 -- platforms to auto-post to
  caption_prompt      text NULL                     -- AI prompt for generating caption from RSS item
  status              enum('active','paused','error') DEFAULT 'active'
  last_polled_at      timestamp NULL
  last_error          text NULL
  last_item_guid      varchar(500) NULL             -- track last processed item
  created_at, updated_at
  INDEX (user_id, status)

━━━ MIGRATION 7: create_ss_post_analytics_table ━━━

ss_post_analytics
  id
  ss_post_platform_id bigint UNSIGNED NOT NULL FK → ss_post_platforms.id ON DELETE CASCADE
  platform            varchar(30) NOT NULL
  impressions         int UNSIGNED DEFAULT 0
  reach               int UNSIGNED DEFAULT 0
  likes               int UNSIGNED DEFAULT 0
  comments            int UNSIGNED DEFAULT 0
  shares              int UNSIGNED DEFAULT 0
  saves               int UNSIGNED DEFAULT 0        -- Instagram saves, Pinterest saves
  clicks              int UNSIGNED DEFAULT 0        -- link clicks
  video_views         int UNSIGNED DEFAULT 0        -- reels/video only
  engagement_rate     decimal(5,2) DEFAULT 0.00    -- (likes+comments+shares)/impressions*100
  fetched_at          timestamp NOT NULL
  created_at, updated_at
  UNIQUE (ss_post_platform_id)                     -- upserted on each analytics pull
  INDEX (platform, fetched_at)

Use standard Laravel migration syntax. Add foreign key constraints with constrained().
```

---

## STEP 3 — Models + Relationships

### DEEPSEEK PROMPT 3

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create 7 Eloquent models in addons/social-scheduler/app/Models/.
Namespace: Addons\SocialScheduler\Models

━━━ SsSocialAccount.php ━━━
- $fillable: all non-id fields
- $casts: token_expires_at → datetime, followers_updated_at → datetime,
          scopes → array, is_active → boolean
- $hidden: ['access_token', 'refresh_token']   -- never serialized in responses
- Relationship: belongsTo(User::class), hasMany(SsPostPlatform::class)
- Accessor: getPlatformLabelAttribute() → ['instagram'=>'Instagram', 'twitter'=>'X / Twitter', ...][$this->platform]
- Accessor: getIsTokenExpiredAttribute(): bool → token_expires_at && token_expires_at->isPast()
- Method: getDecryptedAccessToken(): string → Crypt::decryptString($this->access_token)
- Method: getDecryptedRefreshToken(): ?string → refresh_token ? Crypt::decryptString($this->refresh_token) : null
- Scope: scopeActive($q) → where('is_active', true)
- Scope: scopeForPlatform($q, string $platform) → where('platform', $platform)
- Boot: on creating → encrypt access_token and refresh_token if not already encrypted
  (use a flag: check if value starts with 'ey' suggesting JWT vs. encrypted string)
  Better: ALWAYS encrypt in setter. Override setAttribute for access_token + refresh_token.

━━━ SsScheduledPost.php ━━━
- $fillable: all non-id fields
- $casts: platforms → array, platform_overrides → array, scheduled_at → datetime,
          published_at → datetime, approved_at → datetime, is_rss_auto → boolean
- $appends: ['status_label', 'is_overdue']
- Relationships:
    belongsTo(User::class)
    belongsTo(User::class, 'approved_by')->as('approver')
    belongsTo(SsRssFeed::class, 'rss_feed_id')
    hasMany(SsPostMedia::class, 'ss_scheduled_post_id')
    hasMany(SsCarouselSlide::class, 'ss_scheduled_post_id')
    hasMany(SsPostPlatform::class, 'ss_scheduled_post_id')
- Scopes:
    scopeDue($q) → status='scheduled' AND scheduled_at <= now()
    scopeScheduled($q) → where('status', 'scheduled')
    scopePendingApproval($q) → where('status', 'pending_approval')
    scopeForCalendar($q, Carbon $start, Carbon $end) → scheduled_at BETWEEN $start AND $end
- Accessor: getStatusLabelAttribute(): map status enum to human label
- Accessor: getIsOverdueAttribute(): bool → scheduled_at && scheduled_at->isPast() && status === 'scheduled'
- Boot: on creating → generate ULID; on saving → if status changes to 'scheduled' AND
  addon_setting('social-scheduler', 'approval_required') AND !approved_at → set status = 'pending_approval' automatically

━━━ SsPostMedia.php ━━━
- $fillable: all fields
- $casts: sort_order → integer, file_size_bytes → integer, width → integer, height → integer
- Relationship: belongsTo(SsScheduledPost::class), belongsTo(SsCarouselSlide::class)

━━━ SsCarouselSlide.php ━━━
- $fillable: ss_scheduled_post_id, slide_index, caption
- $casts: slide_index → integer
- Relationships: belongsTo(SsScheduledPost::class), hasMany(SsPostMedia::class, 'carousel_slide_id')

━━━ SsPostPlatform.php ━━━
- $fillable: all fields
- $casts: published_at → datetime, attempt_count → integer
- Relationships: belongsTo(SsScheduledPost::class), belongsTo(SsSocialAccount::class), hasOne(SsPostAnalytics::class)
- Accessor: getExternalUrlAttribute(): return external_post_url
- Scope: scopePending($q) → where('status', 'pending')
- Scope: scopeFailed($q) → where('status', 'failed')->where('attempt_count', '<', 3)

━━━ SsRssFeed.php ━━━
- $fillable: user_id, url, title, platforms, caption_prompt, status, last_item_guid
- $casts: platforms → array, last_polled_at → datetime
- Relationship: belongsTo(User::class), hasMany(SsScheduledPost::class, 'rss_feed_id')
- Scope: scopeActive($q) → where('status', 'active')

━━━ SsPostAnalytics.php ━━━
- $fillable: all fields
- $casts: fetched_at → datetime, engagement_rate → decimal (use float)
- Relationship: belongsTo(SsPostPlatform::class)

IMPORTANT: access_token and refresh_token in SsSocialAccount use attribute overrides
to auto-encrypt on set and auto-decrypt on get — but $hidden ensures they never
appear in toArray()/toJson(). The getDecrypted*() methods are for internal service use only.
```

---

## STEP 4 — Seeder

### DEEPSEEK PROMPT 4

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/database/seeders/SocialSchedulerSeeder.php
Namespace: Addons\SocialScheduler\Database\Seeders

The seeder is idempotent (check ss_scheduled_posts count before seeding demo data).

Seed 3 demo scheduled posts for the first admin user (visible in calendar):

Post 1:
  title: 'Product Launch Announcement'
  caption: 'Exciting news! We just launched our newest feature...'
  platforms: ['instagram', 'twitter', 'linkedin']
  status: 'scheduled'
  scheduled_at: now()->addDays(2)->setHour(10)->setMinute(0)
  post_type: 'single'

Post 2:
  title: 'Weekly Tips Thread'
  caption: '5 tips to improve your content strategy this week 🧵'
  platforms: ['twitter']
  status: 'scheduled'
  scheduled_at: now()->addDays(3)->setHour(14)->setMinute(0)
  post_type: 'thread'

Post 3:
  title: 'Behind the Scenes'
  caption: 'A look behind the scenes at our team...'
  platforms: ['instagram']
  status: 'draft'
  scheduled_at: null
  post_type: 'carousel'

For each post also create SsPostPlatform rows (one per platform in the post's platforms array),
all with status: 'pending'.

No real media attached (demo only). Note in code comments that buyers should connect
real social accounts via OAuth before the scheduler can publish.
```

---

## STEP 5 — SocialAccountService (OAuth Connect / Token Refresh)

### DEEPSEEK PROMPT 5

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/app/Services/SocialAccountService.php
Namespace: Addons\SocialScheduler\Services

This service manages OAuth connect/disconnect and token refresh for 7 platforms.
OAuth is handled via Laravel Socialite (already in core) — this service extends it.

━━━ SUPPORTED PLATFORMS AND OAUTH DETAILS ━━━

instagram:
  Provider: facebook (Graph API) — Instagram uses Facebook OAuth + Graph API
  Scopes: instagram_basic, instagram_content_publish, pages_show_list, pages_read_engagement
  Token: long-lived page access token (exchange via Graph API after initial auth)
  Refresh: manual re-auth (tokens last 60 days, warn user at 10 days)

facebook:
  Provider: facebook (same Socialite driver)
  Scopes: pages_manage_posts, pages_read_engagement, pages_show_list
  Token: long-lived page access token
  Refresh: manual re-auth

twitter:
  Provider: twitter-oauth-2 (OAuth 2.0 PKCE)
  Scopes: tweet.read, tweet.write, users.read, offline.access
  Token: access_token + refresh_token (auto-refreshable)
  Refresh: POST https://api.twitter.com/2/oauth2/token (grant_type: refresh_token)

linkedin:
  Provider: linkedin-openid
  Scopes: openid, profile, w_member_social, r_basicprofile
  Token: access_token (60-day TTL)
  Refresh: manual re-auth (LinkedIn deprecated refresh tokens for most apps)

tiktok:
  Provider: custom (TikTok Login Kit — not in Socialite by default)
  Scopes: user.info.basic, video.publish, video.upload
  Token: access_token + refresh_token (auto-refreshable, 24h access / 365d refresh)
  Refresh: POST https://open.tiktokapis.com/v2/oauth/token/ (grant_type: refresh_token)
  Note: use a package or custom Socialite driver. Add instruction comment:
        'Install: composer require amirezaeb/socialite-tiktok or implement custom driver'

pinterest:
  Provider: pinterest (custom Socialite driver)
  Scopes: boards:read, pins:read, pins:write, user_accounts:read
  Token: access_token + refresh_token (auto-refreshable)
  Refresh: POST https://api.pinterest.com/v5/oauth/token (grant_type: refresh_token)
  Note: 'Install: composer require socialiteproviders/pinterest'

youtube:
  Provider: google (already in Socialite via core)
  Scopes: https://www.googleapis.com/auth/youtube.upload, https://www.googleapis.com/auth/youtube.readonly
  Token: access_token + refresh_token (auto-refreshable via Google OAuth)
  Refresh: POST https://oauth2.googleapis.com/token (grant_type: refresh_token)

━━━ METHOD SIGNATURES ━━━

/**
 * Returns the Socialite redirect URL for the platform.
 * Called by the OAuth initiation controller.
 */
public function getRedirectUrl(string $platform): string

/**
 * Handles the OAuth callback: fetches user data, upserts SsSocialAccount.
 * For Facebook/Instagram: exchanges short-lived token for long-lived page token via Graph API.
 * For Twitter: exchanges code for token using PKCE verifier (stored in session).
 * Encrypts tokens before saving.
 */
public function handleCallback(string $platform, User $user): SsSocialAccount

/**
 * Refreshes an expired access token using the stored refresh token.
 * Called automatically by platform publisher jobs before API calls.
 * Updates ss_social_accounts with new encrypted tokens.
 * Returns the refreshed account model.
 * Throws SocialTokenRefreshException if refresh fails (caller should mark account inactive).
 */
public function refreshToken(SsSocialAccount $account): SsSocialAccount

/**
 * Returns the platform API client with fresh tokens.
 * Called by publisher jobs to get ready-to-use HTTP client.
 * Auto-calls refreshToken() if token_expires_at is within 5 minutes.
 */
public function getApiClient(SsSocialAccount $account): PlatformApiClient

/**
 * Soft-disconnects an account (is_active = false, clears tokens).
 * Does NOT delete scheduled posts — they remain with the account reference.
 */
public function disconnect(SsSocialAccount $account): void

/**
 * Checks how many accounts a user has connected. Throws
 * AccountLimitException if >= addon_setting('social-scheduler', 'max_accounts_per_user', 10).
 */
public function checkAccountLimit(User $user): void

━━━ PlatformApiClient value object ━━━
  (simple wrapper returned by getApiClient)
  readonly class PlatformApiClient {
    public string $platform,
    public string $accessToken,
    public ?string $pageId,
    public Illuminate\Http\Client\PendingRequest $http   // Http::withToken($accessToken)->timeout(30)
  }

RULES:
- Tokens always encrypted (Crypt::encryptString) before DB storage
- Never log or expose decrypted tokens
- Token refresh failures must be caught and re-thrown as SocialTokenRefreshException
- settings() for all config (platform OAuth keys come from core settings table:
  'facebook_client_id', 'facebook_client_secret', etc. — already set in Admin → Integrations)
```

---

## STEP 6 — Platform Publisher Jobs (7 platforms)

### DEEPSEEK PROMPT 6

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create 7 platform publisher jobs in:
  addons/social-scheduler/app/Jobs/Publishers/

Namespace: Addons\SocialScheduler\Jobs\Publishers
Queue: 'social'. Max attempts: 3. Backoff: [60, 300, 900].

All jobs share this interface:
  __construct(
    public readonly int $postId,           // SsScheduledPost.id
    public readonly int $postPlatformId,   // SsPostPlatform.id
    public readonly int $accountId         // SsSocialAccount.id
  )

All jobs:
1. Load models; if any not found → return silently
2. Mark SsPostPlatform: status='publishing', attempt_count++
3. Get fresh API client: SocialAccountService::getApiClient($account)
4. Publish content (platform-specific logic below)
5. On success: SsPostPlatform::update(status='published', external_post_id, external_post_url, published_at=now())
6. On failure in failed(): SsPostPlatform::update(status='failed', error_message=exception message)

━━━ PublishToInstagramJob ━━━
Instagram Graph API (requires Facebook Page + linked Instagram Business Account):

Step 1: Upload media container
  POST /{ig-user-id}/media  { image_url: $mediaUrl, caption: $caption }
  → returns creation_id

  For carousel: create container for each slide, then create carousel container:
  POST /{ig-user-id}/media {
    media_type: 'CAROUSEL',
    children: [$creationId1, $creationId2, ...],
    caption: $caption
  }

Step 2: Publish container
  POST /{ig-user-id}/media_publish { creation_id: $creationId }
  → returns post id

Step 3: If first_comment is set (addon_setting('social-scheduler', 'first_comment_enabled')):
  POST /{media-id}/comments { message: $post->first_comment }
  Delay 5 seconds before commenting (Instagram API requirement)

For video/Reels: use Resumable Upload API (chunked upload to media endpoint)

━━━ PublishToFacebookJob ━━━
Facebook Graph API (Page post):

Single image: POST /{page-id}/photos { url: $mediaUrl, message: $caption, access_token: $pageToken }
Video: POST /{page-id}/videos { file_url: $videoUrl, description: $caption, access_token: $pageToken }
No media: POST /{page-id}/feed { message: $caption, access_token: $pageToken }
→ returns id (post id)
External URL: https://www.facebook.com/{page-id}/posts/{post-id}

━━━ PublishToTwitterJob ━━━
X API v2:

Upload media (if any) first:
  POST https://upload.twitter.com/1.1/media/upload.json (multipart, v1.1 endpoint still required for media)
  → returns media_id_string
  If video: use chunked INIT/APPEND/FINALIZE pattern

Create tweet:
  POST https://api.twitter.com/2/tweets
  {
    text: $caption + ($hashtags ? '\n' . $hashtags : ''),
    media: { media_ids: [$mediaId] }   // if media
  }
  Authorization: Bearer $accessToken (OAuth 2.0)
  → returns data.id

For 'thread' post_type:
  Split caption into 280-char segments. Post first tweet, then reply to it for each subsequent segment.
  Store first tweet ID as external_post_id.

━━━ PublishToLinkedInJob ━━━
LinkedIn v2 UGC Posts API:

Register image upload first (if image):
  POST https://api.linkedin.com/v2/assets?action=registerUpload
  → returns uploadMechanism.uploadUrl and asset ID
  PUT $uploadUrl (binary image body)

For carousel: LinkedIn document upload (PDF of slides) — convert slides to PDF using mpdf/mpdf:
  POST /v2/assets?action=registerUpload (documentType: PDF)
  → upload PDF
  Build share with DOCUMENT media type

Create post:
  POST https://api.linkedin.com/v2/ugcPosts
  {
    author: "urn:li:person:{platformUserId}",
    lifecycleState: "PUBLISHED",
    specificContent: { shareContent: { shareCommentary: { text: caption }, shareMediaCategory: "IMAGE"/"NONE", media: [...] } },
    visibility: { memberNetworkVisibility: "PUBLIC" }
  }
  → returns id (urn:li:ugcPost:{id})

━━━ PublishToTikTokJob ━━━
TikTok Login Kit Content Posting API:

Step 1: Initialize upload
  POST https://open.tiktokapis.com/v2/post/publish/inbox/video/init/
  {
    post_info: { title: $caption, privacy_level: 'PUBLIC_TO_EVERYONE', disable_comment: false },
    source_info: { source: 'FILE_UPLOAD', video_size: $fileSize, chunk_size: $chunkSize, total_chunk_count: $chunks }
  }
  → returns publish_id + upload_url

Step 2: Upload video chunks to upload_url (PUT requests)

Step 3: Poll publish status
  GET https://open.tiktokapis.com/v2/post/publish/status/fetch/
  { publish_id: $publishId }
  Poll every 10s up to 5 minutes → status: 'PROCESSING_UPLOAD' | 'SEND_TO_USER_INBOX' | 'FAILED'

Note: TikTok requires VIDEO content only (images as slideshows are a newer feature — skip for v1.0).
If post has no video: mark SsPostPlatform status='skipped', error_message='TikTok requires video content'.

━━━ PublishToPinterestJob ━━━
Pinterest API v5:

Create Pin:
  POST https://api.pinterest.com/v5/pins
  {
    board_id: $boardId,   // user must specify board during scheduling — store in platform_overrides
    media_source: { source_type: 'image_url', url: $mediaUrl },
    title: $title,
    description: $caption,
    link: $linkUrl        // optional
  }
  Authorization: Bearer $accessToken
  → returns id

If no media: Pinterest requires at least an image. If no media attached:
  Mark SsPostPlatform status='skipped', error_message='Pinterest requires an image'.

Board ID is stored in ss_social_accounts.page_id (repurposed field) or in
platform_overrides JSON: {"pinterest":{"board_id":"..."}}

━━━ PublishToYouTubeJob ━━━
YouTube Data API v3 (requires video file — YouTube is video only):

Upload video using resumable upload:
  POST https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable
  Headers: Authorization, Content-Type: application/json, X-Upload-Content-Type: video/*
  Body: { snippet: { title: $title, description: $caption }, status: { privacyStatus: 'public' } }
  → returns upload URI (Location header)
  PUT $uploadUri (video file content in chunks — 256KB minimum chunk size per Google spec)

If post has no video: mark status='skipped', error_message='YouTube requires video content'.

RULES FOR ALL JOBS:
- Use SocialAccountService::getApiClient() — never directly decrypt tokens in jobs
- All HTTP calls via Laravel Http facade: Http::withToken($client->accessToken)
- Log failures to SsPostPlatform.error_message (Str::limit($exception->getMessage(), 500))
- failed() method always marks SsPostPlatform as 'failed'
- Never throw outside the jobs — all exceptions caught and stored
- Use settings('app_name') in any user-facing attribution — never hardcode
```

---

## STEP 7 — PublishSocialPost Orchestrator Job

### DEEPSEEK PROMPT 7

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/app/Jobs/PublishSocialPost.php
Namespace: Addons\SocialScheduler\Jobs
Queue: 'social'. This job runs every 5 minutes via the addon's schedule.

This orchestrator finds due posts and dispatches the correct platform publisher job.

━━━ handle() ━━━

Step 1: Find due posts
  SsScheduledPost::due()
    ->with(['postPlatforms.socialAccount'])
    ->limit(50)   // process max 50 per run to avoid memory issues
    ->get()

Step 2: For each post, mark status='publishing'

Step 3: For each SsPostPlatform where status='pending':
  Load the associated SsSocialAccount
  If account not found or not active: mark platform status='skipped', continue
  If account token expired AND no refresh_token: mark platform status='skipped',
    error_message='Account token expired — reconnect account', continue
  Dispatch the correct job based on platform:
    'instagram' → PublishToInstagramJob
    'facebook'  → PublishToFacebookJob
    'twitter'   → PublishToTwitterJob
    'linkedin'  → PublishToLinkedInJob
    'tiktok'    → PublishToTikTokJob
    'pinterest' → PublishToPinterestJob
    'youtube'   → PublishToYouTubeJob
  All dispatched to queue: 'social'

Step 4: After all platform jobs dispatched for a post,
  register a callback to update the post's overall status once all platforms resolve.
  Use a chained listener or a separate CheckPostPublishStatus job dispatched with 5-minute delay:

  CheckPostPublishStatus::dispatch($post->id)->delay(now()->addMinutes(10))->onQueue('social')

━━━ Also create: CheckPostPublishStatus job ━━━

handle():
  $post = SsScheduledPost::with('postPlatforms')->find($this->postId)
  if (!$post) return

  $platforms = $post->postPlatforms
  $allDone  = $platforms->every(fn($p) => in_array($p->status, ['published','failed','skipped']))
  if (!$allDone) {
    // Still in progress — requeue check after another 5 min (max 3 rechecks)
    if ($this->attempts() < 3) {
      $this->release(300)
    }
    return
  }

  $publishedCount = $platforms->where('status', 'published')->count()
  $failedCount    = $platforms->where('status', 'failed')->count()

  $post->update([
    'status'       => match(true) {
      $publishedCount === $platforms->count()  => 'published',
      $publishedCount > 0                      => 'partial',
      default                                  => 'failed',
    },
    'published_at' => $publishedCount > 0 ? now() : null,
  ])

  // Notify user
  if ($publishedCount > 0) {
    SendInAppNotification::dispatch($post->user, 'post_published', [
      'post_title' => $post->title ?? Str::limit($post->caption, 50),
      'published_count' => $publishedCount,
      'failed_count' => $failedCount,
    ])->onQueue('default')
  }
```

---

## STEP 8 — AiCaptionService (Platform-Aware Caption Streaming)

### DEEPSEEK PROMPT 8

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/app/Services/AiCaptionService.php
Namespace: Addons\SocialScheduler\Services

Streams an AI-generated caption adapted for a specific social platform.

━━━ Platform tone guidelines ━━━

const PLATFORM_GUIDES = [
  'instagram' => 'Engaging, visual storytelling, 2200 char max, heavy emoji, 20–30 hashtags at end, newlines for readability. Focus on emotions and aesthetics.',
  'twitter'   => 'Punchy, conversational, 280 chars MAX per tweet. If thread, clearly mark "1/" "2/" etc. No hashtag spam — max 2 relevant hashtags.',
  'linkedin'  => 'Professional, insightful, thought leadership tone. 700 char sweet spot. 3–5 relevant hashtags. Use line breaks. No excessive emoji.',
  'facebook'  => 'Friendly, community-oriented, storytelling. 500–1000 chars optimal. 2–3 hashtags. Ask a question to drive comments.',
  'tiktok'    => 'Trend-aware, casual, Gen-Z friendly. Under 2200 chars. 3–5 trending hashtags. Direct hook in first line. CTA at end.',
  'pinterest' => 'Keyword-rich for search, inspirational, descriptive. 500 char max. Focus on value and discovery.',
  'youtube'   => 'SEO-optimized description. First 2–3 lines most important (shown before "show more"). Include timestamps if long-form. 5000 char limit.',
];

━━━ Method signatures ━━━

public function __construct(private AiService $ai) {}

/**
 * Streams a caption for a given topic and platform.
 * Used by the Vue composer's AI generate button.
 * Returns a Generator (consumed by the streaming controller).
 */
public function streamCaption(
  string $topic,
  string $platform,
  string $tone,        // 'professional' | 'casual' | 'funny' | 'inspirational' | 'educational'
  ?string $additionalContext,
  ?User $user
): Generator

━━━ streamCaption() implementation ━━━

$guide = self::PLATFORM_GUIDES[$platform] ?? 'Write an engaging social media caption.';
$model = addon_setting('social-scheduler', 'ai_model', 'gpt-4o-mini');

$systemPrompt = "You are a social media expert writing a caption for {$platform}.
Platform guidelines: {$guide}
Tone: {$tone}.
Write ONLY the caption text — no preamble, no explanation, no quotes around it.
App context: " . settings('app_name');

$userPrompt = "Topic: {$topic}" . ($additionalContext ? "\nAdditional context: {$additionalContext}" : '');

$request = new CompletionRequest(
  model: $model,
  messages: [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user',   'content' => $userPrompt],
  ],
  maxTokens: 1024,
);

foreach ($this->ai->stream($request) as $token) {
  yield $token;
}

/**
 * Non-streaming: generate a short platform-adapted version of an existing caption.
 * Used for platform_overrides: user has a main caption, wants a shorter Twitter version.
 */
public function adaptCaption(string $originalCaption, string $targetPlatform): string

━━━ Controller for streaming endpoint ━━━

Also create: addons/social-scheduler/app/Http/Controllers/User/CaptionController.php

generate(Request $request): StreamedResponse
  Validate: topic (required, max:500), platform (in:instagram,facebook,twitter,linkedin,tiktok,pinterest,youtube),
            tone (in:professional,casual,funny,inspirational,educational), context (nullable, max:300)
  Throttle: 20,1

  return response()->stream(function() use ($request) {
    try {
      foreach (app(AiCaptionService::class)->streamCaption(
        $request->topic, $request->platform, $request->tone,
        $request->context, auth()->user()
      ) as $token) {
        echo $token;
        ob_flush(); flush();
      }
    } finally {}
  }, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'X-Accel-Buffering' => 'no'])

RULES:
- AiService — never raw HTTP
- POST + ReadableStream — never EventSource
- X-Accel-Buffering: no
- finally block always present
- translate() for error messages
```

---

## STEP 9 — RssFeedService + PollRssFeeds Job

### DEEPSEEK PROMPT 9

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create two files:
  addons/social-scheduler/app/Services/RssFeedService.php
  addons/social-scheduler/app/Jobs/PollRssFeeds.php

━━━ RssFeedService.php ━━━
Namespace: Addons\SocialScheduler\Services

/**
 * Fetches and parses an RSS feed. Returns array of items newer than last_item_guid.
 * Uses Laravel Http facade — no external RSS parser package.
 */
public function fetchNewItems(SsRssFeed $feed): array
  Http::timeout(20)->get($feed->url) → parse XML with PHP's SimpleXML
  Items are SimpleXMLElement objects with: title, link, description, pubDate, guid
  Filter: only items where guid !== $feed->last_item_guid (and pubDate newer if available)
  Return: array of ['title' => ..., 'link' => ..., 'description' => ..., 'guid' => ...]
  On HTTP error: throw RssFetchException($message)

/**
 * Creates a draft SsScheduledPost from an RSS item.
 * Calls AiService to generate caption from item content + feed's caption_prompt.
 */
public function createPostFromItem(SsRssFeed $feed, array $item): SsScheduledPost
  $captionPrompt = $feed->caption_prompt
    ?: 'Write a social media post about this article. Include the key insight.'
  $context = "Article title: {$item['title']}\nURL: {$item['link']}\nSummary: " . Str::limit(strip_tags($item['description']), 500)
  $caption = app(AiCaptionService::class)->adaptCaption($context, 'general')
  // 'general' platform = use a neutral, broad social media tone

  SsScheduledPost::create([
    'user_id'       => $feed->user_id,
    'title'         => Str::limit($item['title'], 200),
    'caption'       => $caption . "\n\n" . $item['link'],
    'platforms'     => $feed->platforms,
    'status'        => 'draft',             // admin/user reviews before scheduling
    'is_rss_auto'   => true,
    'rss_feed_id'   => $feed->id,
    'scheduled_at'  => null,
  ])

━━━ PollRssFeeds.php ━━━
Namespace: Addons\SocialScheduler\Jobs
Queue: 'social'. Runs on cron schedule (every N minutes per settings).

handle(RssFeedService $service):
  SsRssFeed::active()->chunk(20, function($feeds) use ($service) {
    foreach ($feeds as $feed) {
      try {
        $items = $service->fetchNewItems($feed)
        foreach ($items as $item) {
          $service->createPostFromItem($feed, $item)
        }
        // Update tracking
        $lastGuid = $items[0]['guid'] ?? $feed->last_item_guid
        $feed->update(['last_polled_at' => now(), 'last_item_guid' => $lastGuid, 'status' => 'active', 'last_error' => null])
      } catch (RssFetchException $e) {
        $feed->update(['status' => 'error', 'last_error' => $e->getMessage(), 'last_polled_at' => now()])
      }
    }
  })
```

---

## STEP 10 — BestTimeService (AI-Suggested Posting Time)

### DEEPSEEK PROMPT 10

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/app/Services/BestTimeService.php
Namespace: Addons\SocialScheduler\Services

This service analyzes a user's past post analytics and asks the AI to suggest
the best time to post for a given platform and audience.

━━━ suggestBestTime() ━━━

public function suggestBestTime(User $user, string $platform, string $contentType): array
// Returns: ['suggested_time' => Carbon, 'reasoning' => string, 'alternatives' => [Carbon, Carbon]]

Step 1: Gather analytics data for this user + platform
  $analytics = SsPostAnalytics::join('ss_post_platforms', ...)
    ->join('ss_scheduled_posts', ...)
    ->where('ss_scheduled_posts.user_id', $user->id)
    ->where('ss_post_analytics.platform', $platform)
    ->where('ss_scheduled_posts.published_at', '>=', now()->subDays(90))
    ->select([
      DB::raw('HOUR(ss_scheduled_posts.published_at) as hour'),
      DB::raw('DAYOFWEEK(ss_scheduled_posts.published_at) as day_of_week'),
      DB::raw('AVG(ss_post_analytics.engagement_rate) as avg_engagement'),
      DB::raw('COUNT(*) as post_count')
    ])
    ->groupBy('hour', 'day_of_week')
    ->orderByDesc('avg_engagement')
    ->limit(20)
    ->get()

Step 2: If insufficient data (< 5 posts), use general industry knowledge:
  $prompt = "Based on general best practices for {$platform}, suggest the best time to post
  {$contentType} content. User timezone: UTC. Return JSON only:
  {\"suggested_time\":\"2024-01-15T10:00:00Z\",\"reasoning\":\"...\",\"alternatives\":[\"...\",\"...\"]}";

Step 3: If sufficient data:
  $dataDescription = $analytics->map(fn($row) =>
    "Day: {$row->day_of_week}, Hour: {$row->hour}:00, Avg Engagement: {$row->avg_engagement}%, Posts: {$row->post_count}"
  )->implode("\n");

  $prompt = "Analyze this user's past post performance on {$platform} and suggest the
  optimal posting time for {$contentType} content:
  {$dataDescription}
  Return JSON only (no markdown): {\"suggested_time\":\"ISO8601\",\"reasoning\":\"...\",\"alternatives\":[\"ISO8601\",\"ISO8601\"]}";

Step 4: Call AiService (non-streaming, small response)
  $response = $this->ai->complete(new CompletionRequest(
    model: addon_setting('social-scheduler', 'best_time_model', 'gpt-4o-mini'),
    messages: [['role' => 'user', 'content' => $prompt]],
    maxTokens: 300,
  ))

  Parse JSON from response. Return array with Carbon objects.
  Cache result: Cache::remember("social.best_time.{$user->id}.{$platform}", 3600, fn() => ...)
  On parse failure: return reasonable defaults per platform (Mon-Fri 9am UTC fallback)

RULES: AiService only, settings() for model config, cache per user per platform.
```

---

## STEP 11 — FetchPostAnalytics Job

### DEEPSEEK PROMPT 11

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/app/Jobs/FetchPostAnalytics.php
Namespace: Addons\SocialScheduler\Jobs
Queue: 'low'. Runs daily at 05:00.

handle(SocialAccountService $accountService):
  // Only run if analytics pull is enabled
  if (!addon_setting('social-scheduler', 'analytics_pull_enabled', true)) return

  // Get all published platform posts from last 90 days, in chunks
  SsPostPlatform::where('status', 'published')
    ->whereNotNull('external_post_id')
    ->where('published_at', '>=', now()->subDays(90))
    ->with('socialAccount')
    ->chunk(50, function($platformPosts) use ($accountService) {
      foreach ($platformPosts as $pp) {
        try {
          $client = $accountService->getApiClient($pp->socialAccount)
          $metrics = $this->fetchMetrics($pp, $client)
          SsPostAnalytics::updateOrCreate(
            ['ss_post_platform_id' => $pp->id],
            [...$metrics, 'platform' => $pp->platform, 'fetched_at' => now()]
          )
        } catch (Throwable $e) {
          // Log silently, don't fail the whole job
          Log::warning("Analytics fetch failed for platform post {$pp->id}: " . $e->getMessage())
        }
      }
    })

private function fetchMetrics(SsPostPlatform $pp, PlatformApiClient $client): array
  // Returns array matching ss_post_analytics columns
  // Platform-specific API calls:

  'instagram': GET /{media_id}/insights?metric=impressions,reach,likes,comments,saves,shares&access_token=...
               (Instagram Graph API media insights — requires Business account)

  'facebook':  GET /{post_id}/insights?metric=post_impressions,post_engaged_users,post_reactions_by_type_total&access_token=...

  'twitter':   GET https://api.twitter.com/2/tweets/{id}?tweet.fields=public_metrics
               Returns: impression_count, like_count, reply_count, retweet_count, url_link_clicks

  'linkedin':  GET https://api.linkedin.com/v2/organizationalEntityShareStatistics?q=organizationalEntity&shares=urn:li:ugcPost:{id}
               Returns: impressionCount, clickCount, likeCount, commentCount, shareCount

  'tiktok':    GET https://open.tiktokapis.com/v2/video/query/?fields=id,like_count,comment_count,share_count,view_count,reach
               Body: { filters: { video_ids: [$pp->external_post_id] } }

  'pinterest': GET https://api.pinterest.com/v5/pins/{id}/analytics?start_date=...&end_date=...&metric_types=IMPRESSION,OUTBOUND_CLICK,PIN_CLICK,SAVE
               Returns per-metric daily data — sum it

  'youtube':   GET https://www.googleapis.com/youtube/v3/videos?part=statistics&id={videoId}&key={apiKey}
               Returns: viewCount, likeCount, commentCount, favoriteCount

  Calculate engagement_rate: (likes + comments + shares) / impressions * 100 where impressions > 0

  Return mapped array matching table columns. Unknown metrics default to 0.
```

---

## STEP 12 — Admin Controllers + FormRequests

### DEEPSEEK PROMPT 12

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create admin controllers in:
  addons/social-scheduler/app/Http/Controllers/Admin/

Namespace: Addons\SocialScheduler\Http\Controllers\Admin
Middleware: ['auth:admin', 'admin.permission:addon.social.manage']

━━━ SsAdminOverviewController ━━━

index():
  return Inertia 'SocialScheduler::Admin/Overview'
  props:
    - total_posts: SsScheduledPost::count()
    - scheduled_posts: SsScheduledPost::scheduled()->count()
    - pending_approval: SsScheduledPost::pendingApproval()->count()
    - published_today: SsScheduledPost::where('status','published')->whereDate('published_at', today())->count()
    - failed_posts: SsScheduledPost::where('status','failed')->count()
    - connected_accounts: SsSocialAccount::active()->count()
    - platform_breakdown: SsSocialAccount::active()->selectRaw('platform, COUNT(*) as count')->groupBy('platform')->get()
    - recent_posts: SsScheduledPost::latest()->limit(10)->with(['user','postPlatforms'])->get()

━━━ SsApprovalController ━━━ (uses permission: addon.social.approve)

index():
  $posts = SsScheduledPost::pendingApproval()
    ->with(['user','postPlatforms','media'])
    ->latest()->paginate(20)
  return Inertia 'SocialScheduler::Admin/Approval' props: posts

approve(SsScheduledPost $post):
  Guard: post must be pending_approval
  $post->update(['status' => 'scheduled', 'approved_by' => auth('admin')->id(), 'approved_at' => now()])
  SendInAppNotification::dispatch($post->user, 'post_approved', ['title' => Str::limit($post->caption, 50)])
  return back()->with('flash', 'approved')

reject(Request $request, SsScheduledPost $post):
  Validate: reason required max:500
  $post->update(['status' => 'draft', 'rejection_reason' => $request->reason])
  SendInAppNotification::dispatch($post->user, 'post_rejected', [...])
  return back()->with('flash', 'rejected')

━━━ SsAdminSettingsController ━━━

edit():
  return Inertia 'SocialScheduler::Admin/Settings'
  props: settings (all social_ settings), active_feeds_count, active_accounts_count

update(SsAdminSettingsRequest $request):
  foreach validated() as $key => $value: addon_setting_set('social-scheduler', $key, $value)
  redirect back with flash 'saved'

━━━ FormRequests ━━━

SsAdminSettingsRequest: validate all settings fields.
SsApprovalRequest: reason required max:500.
```

---

## STEP 13 — User Controllers + FormRequests

### DEEPSEEK PROMPT 13

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create user-facing controllers in:
  addons/social-scheduler/app/Http/Controllers/User/

Namespace: Addons\SocialScheduler\Http\Controllers\User
Middleware: ['auth', 'permission:addon.social.manage', 'addon.social.enabled']

━━━ SsAccountController ━━━ (OAuth connect/disconnect)

index():
  $accounts = SsSocialAccount::where('user_id', auth()->id())->get()
  return Inertia 'SocialScheduler::User/Accounts' props: accounts, platforms_list

redirect(string $platform):
  app(SocialAccountService::class)->checkAccountLimit(auth()->user())
  $url = app(SocialAccountService::class)->getRedirectUrl($platform)
  return redirect($url)

callback(string $platform):
  try {
    $account = app(SocialAccountService::class)->handleCallback($platform, auth()->user())
    return redirect()->route('addon.social.user.accounts')->with('flash', 'connected')
  } catch (Throwable $e) {
    return redirect()->route('addon.social.user.accounts')->withErrors(['error' => $e->getMessage()])
  }

disconnect(SsSocialAccount $account):
  Guard: $account->user_id === auth()->id() — else abort 403
  app(SocialAccountService::class)->disconnect($account)
  return back()->with('flash', 'disconnected')

━━━ SsPostController ━━━ (CRUD for scheduled posts)

index():
  $posts = SsScheduledPost::where('user_id', auth()->id())
    ->with(['postPlatforms','media'])
    ->latest()->paginate(20)
  return Inertia 'SocialScheduler::User/Posts/Index' props: posts, filters

create():
  $accounts = SsSocialAccount::where('user_id', auth()->id())->active()->get()
  return Inertia 'SocialScheduler::User/Posts/Composer'
  props: post (null), accounts, platforms_list, approval_required (from settings)

store(SsPostRequest $request):
  Validate: caption required max:5000, platforms array min:1, post_type in:single/carousel/thread/story/reel,
    scheduled_at nullable after:now, hashtags nullable max:2000, first_comment nullable max:2200,
    platform_overrides nullable array

  DB::transaction(function() use ($request) {
    $post = SsScheduledPost::create([...$request->validated(), 'user_id' => auth()->id()])

    // Attach platform rows
    foreach ($request->platforms as $platform) {
      $account = SsSocialAccount::where('user_id', auth()->id())
                   ->where('platform', $platform)->active()->firstOrFail()
      $post->postPlatforms()->create([
        'ss_social_account_id' => $account->id,
        'platform' => $platform,
        'status' => 'pending',
      ])
    }

    // Handle media uploads
    if ($request->hasFile('media')) {
      foreach ($request->file('media') as $index => $file) {
        $path = $file->store('social-media/' . auth()->id(), 'public')
        $post->media()->create([
          'type'           => Str::startsWith($file->getMimeType(), 'video') ? 'video' : 'image',
          'path'           => $path,
          'url'            => Storage::url($path),
          'mime_type'      => $file->getMimeType(),
          'file_size_bytes'=> $file->getSize(),
          'sort_order'     => $index,
        ])
      }
    }
  })
  redirect to index with flash 'created'

edit(SsScheduledPost $post):
  abort_if($post->user_id !== auth()->id(), 403)
  abort_if(in_array($post->status, ['publishing','published']), 403, 'Cannot edit a published post')
  $accounts = SsSocialAccount::where('user_id', auth()->id())->active()->get()
  return Inertia 'SocialScheduler::User/Posts/Composer' props: post (with relations), accounts

update(SsPostRequest $request, SsScheduledPost $post):
  abort_if($post->user_id !== auth()->id(), 403)
  abort_if(in_array($post->status, ['publishing','published']), 403)
  DB::transaction(fn() => $post->update($request->validated()))
  redirect to index with flash 'updated'

destroy(SsScheduledPost $post):
  abort_if($post->user_id !== auth()->id(), 403)
  abort_if($post->status === 'publishing', 403, 'Cannot delete a post currently being published')
  // Delete media files from storage
  foreach ($post->media as $media) { Storage::delete($media->path) }
  $post->delete()
  return back()->with('flash', 'deleted')

━━━ SsCalendarController ━━━

index():
  return Inertia 'SocialScheduler::User/Calendar'
  props: initial_month (current), accounts (active accounts for filter)

events(Request $request):
  Validate: start (date), end (date), platforms (array nullable)
  $posts = SsScheduledPost::where('user_id', auth()->id())
    ->forCalendar(Carbon::parse($request->start), Carbon::parse($request->end))
    ->with(['postPlatforms'])
    ->get(['id','ulid','title','caption','platforms','status','scheduled_at','post_type'])
  return response()->json($posts)

reschedule(Request $request, SsScheduledPost $post):
  abort_if($post->user_id !== auth()->id(), 403)
  Validate: scheduled_at required date after:now
  $post->update(['scheduled_at' => $request->scheduled_at])
  return response()->json(['success' => true, 'scheduled_at' => $post->scheduled_at])

━━━ SsAnalyticsController ━━━

index():
  $platforms = SsPostAnalytics::join('ss_post_platforms','...')
    ->join('ss_scheduled_posts','...')
    ->where('ss_scheduled_posts.user_id', auth()->id())
    ->select(
      'ss_post_analytics.platform',
      DB::raw('SUM(impressions) as total_impressions'),
      DB::raw('SUM(likes) as total_likes'),
      DB::raw('SUM(comments) as total_comments'),
      DB::raw('SUM(shares) as total_shares'),
      DB::raw('AVG(engagement_rate) as avg_engagement'),
      DB::raw('COUNT(*) as post_count')
    )
    ->groupBy('platform')
    ->get()

  $topPosts = SsPostAnalytics::join(...)
    ->where('user_id', auth()->id())
    ->orderByDesc('engagement_rate')
    ->limit(10)
    ->get([...'title','caption','platform','external_post_url','impressions','engagement_rate'])

  return Inertia 'SocialScheduler::User/Analytics' props: platforms, topPosts

━━━ SsRssFeedController ━━━ (CRUD)
index, store, update, destroy — standard Inertia resource, user_id scoped.
FormRequest: url required url max:500, platforms array min:1, caption_prompt nullable max:1000.

━━━ FormRequests ━━━
SsPostRequest: all post fields with appropriate validation rules per field spec above.
SsRssFeedRequest: url, platforms, caption_prompt.
```

---

## STEP 14 — Routes

### DEEPSEEK PROMPT 14

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/routes/web.php

━━━ ADMIN ROUTES ━━━
Route::middleware(['web', 'auth:admin'])
  ->prefix('admin/social-scheduler')
  ->name('addon.social.admin.')
  ->group(function () {
    Route::get('/',          [SsAdminOverviewController::class, 'index'])->name('overview')
      ->middleware('admin.permission:addon.social.manage')

    Route::middleware('admin.permission:addon.social.approve')->group(function () {
      Route::get('approval',              [SsApprovalController::class, 'index'])->name('approval.index')
      Route::post('approval/{post}/approve', [SsApprovalController::class, 'approve'])->name('approval.approve')
      Route::post('approval/{post}/reject',  [SsApprovalController::class, 'reject'])->name('approval.reject')
    })

    Route::middleware('admin.permission:addon.social.settings')->group(function () {
      Route::get('settings',  [SsAdminSettingsController::class, 'edit'])->name('settings')
      Route::put('settings',  [SsAdminSettingsController::class, 'update'])
    })
  })

━━━ USER ROUTES ━━━
Route::middleware(['web', 'auth', 'permission:addon.social.manage'])
  ->prefix('social')
  ->name('addon.social.user.')
  ->group(function () {

    // Calendar
    Route::get('calendar',             [SsCalendarController::class, 'index'])->name('calendar')
    Route::get('calendar/events',      [SsCalendarController::class, 'events'])->name('calendar.events')
    Route::patch('posts/{post}/reschedule', [SsCalendarController::class, 'reschedule'])->name('posts.reschedule')

    // Posts (composer + list)
    Route::resource('posts', SsPostController::class)->except(['show'])

    // Connected Accounts + OAuth
    Route::get('accounts',             [SsAccountController::class, 'index'])->name('accounts')
    Route::get('accounts/{platform}/connect',  [SsAccountController::class, 'redirect'])->name('accounts.redirect')
    Route::get('accounts/{platform}/callback', [SsAccountController::class, 'callback'])->name('accounts.callback')
    Route::delete('accounts/{account}',        [SsAccountController::class, 'disconnect'])->name('accounts.disconnect')

    // Analytics
    Route::get('analytics',  [SsAnalyticsController::class, 'index'])->name('analytics')

    // RSS Feeds
    Route::resource('rss-feeds', SsRssFeedController::class)->except(['show', 'create', 'edit'])

    // AI Caption (streaming)
    Route::post('caption/generate', [CaptionController::class, 'generate'])
      ->middleware('throttle:20,1')->name('caption.generate')

    // Best Time suggestion
    Route::post('best-time', [BestTimeController::class, 'suggest'])
      ->middleware('throttle:10,1')->name('best-time')
  })

Note: OAuth callback routes (/social/accounts/{platform}/callback) must be excluded from
CSRF verification in App\Http\Middleware\VerifyCsrfToken.php $except array.
Add a comment in AddonServiceProvider instructing buyers to add these routes to $except.
Also create: addons/social-scheduler/routes/api.php (empty for now — all routes are web).
```

---

## STEP 15 — Vue Pages

### DEEPSEEK PROMPT 15

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13 + Vue 3 + Inertia.js).

Create Vue pages in:
  addons/social-scheduler/resources/js/Pages/

All files: <script setup lang="ts">, TypeScript, Tabler Icons, Tailwind v4.
Use MakeAI design tokens: emerald primary, ocean blue accent. Dark sidebar in admin.
$t() for all strings. Intl API for dates. Never Options API. Never localStorage.

━━━ PAGE 1: User/Calendar.vue — Drag-Drop Visual Calendar ━━━

Use vue-cal (vue-cal package — already compatible with Vue 3) for the calendar grid.
Install instruction comment: 'npm install vue-cal'

Features:
- Month / Week / Day view switcher (TabGroup component)
- Each event card shows:
    Platform icons (Tabler icons: ti-brand-instagram, ti-brand-twitter, ti-brand-linkedin, etc.)
    Post status badge (color-coded: draft=gray, scheduled=blue, published=green, failed=red, partial=yellow)
    Truncated caption (max 60 chars)
- Drag an event to a new date → PATCH /social/posts/{ulid}/reschedule (optimistic update with revert on error)
- Click event → opens PostPreviewSlideOver (see below)
- [+ New Post] button in header → navigate to Composer
- Platform filter: show/hide platforms (multi-select toggle buttons with platform icons)
- Fetch events via GET /social/calendar/events?start=&end= when month changes (axios, not Inertia)

PostPreviewSlideOver (sub-component):
  Shows: caption, platforms (with status per platform), scheduled_at, media thumbnails
  Actions: [Edit] → Composer | [Cancel Post] → DELETE
  For pending_approval posts: shows "Awaiting approval" badge

━━━ PAGE 2: User/Posts/Composer.vue — Post Composer ━━━

Two-column layout: LEFT (composer form) / RIGHT (live preview per platform)

LEFT COLUMN:

1. Platform selector (multi-select pill buttons with platform logos):
   Instagram | Facebook | X/Twitter | LinkedIn | TikTok | Pinterest | YouTube
   Only shows platforms the user has connected accounts for.

2. Post type selector (tabs): Single | Carousel | Thread | Story | Reel
   Tabs shown/hidden based on selected platforms' capabilities.

3. Caption area:
   - Main textarea (large, auto-resize)
   - Character counter per selected platform (shows limit, color-coded when near/over)
   - [🤖 AI Generate] button → opens AiCaptionPanel (below)
   - Hashtag input (separate field, appended to caption on publish)
   - Platform overrides: expandable section per platform with its own caption field
     ("Customize caption for Twitter" — shorter version)

4. AiCaptionPanel (inline, not a modal):
   - Topic input (what to post about)
   - Tone select: Professional / Casual / Funny / Inspirational / Educational
   - Extra context textarea (optional)
   - [Generate] → POST /social/caption/generate (ReadableStream)
   - Streams caption into the panel, user clicks [Use This] to copy to main textarea
   - Abort button while streaming

5. Media uploader:
   - For 'single': drag-drop zone, max 10 images or 1 video
   - For 'carousel': slide builder — add/remove/reorder slides, each with image + caption
   - Shows file size warning if over settings limit

6. Instagram first comment field (visible only if Instagram selected + settings enabled):
   "First comment (hashtags)" textarea

7. Schedule picker:
   - Date + time input
   - [💡 Suggest Best Time] → POST /social/best-time → shows suggested time + reasoning in tooltip

8. [Save Draft] / [Schedule Post] buttons

RIGHT COLUMN — Live Preview:
  Tab per selected platform (Instagram | Twitter | etc.)
  Shows realistic platform mockup:
    Instagram: phone frame, profile avatar, image, caption with "more" truncation, hashtags
    Twitter: tweet card with handle, text (truncated at 280), media
    LinkedIn: post card with name, headline, text, image
    Pinterest: pin card with image (tall aspect ratio), title, description
  Switches between platforms as user selects tabs.
  Updates live as user types.

━━━ PAGE 3: User/Accounts.vue — Connected Accounts ━━━

Grid of platform cards (7 total: Instagram, Facebook, X, LinkedIn, TikTok, Pinterest, YouTube)

Each platform card:
  - Platform icon + name
  - Status: NOT CONNECTED (gray) | CONNECTED (green) | TOKEN EXPIRED (orange warning)
  - If connected: shows @username, avatar, follower count, connected date
  - [Connect] button → GET /social/accounts/{platform}/connect (OAuth redirect)
  - [Disconnect] button (with confirmation) → DELETE /social/accounts/{account}
  - Token expired warning: "Reconnect to continue publishing to this platform"

Below platform grid:
  Info callout: "Your credentials are stored encrypted and are never shared with third parties."
  Link to privacy policy.

━━━ PAGE 4: User/Analytics.vue — Analytics Dashboard ━━━

Platform stat cards row:
  One card per connected platform with: total impressions, avg engagement rate, total posts

Best performing posts table:
  Columns: Platform | Caption (truncated) | Impressions | Likes | Engagement % | [View Post ↗]
  Sort by engagement rate descending

Engagement over time chart (line chart using recharts or Chart.js):
  X-axis: last 30 days
  Y-axis: avg engagement rate per day
  One line per platform (color-coded)
  Fetch data via Inertia prop or separate endpoint

Empty state (no analytics yet):
  Illustration + "Publish your first post to start seeing analytics. Analytics are updated daily."

━━━ PAGE 5: Admin/Approval.vue — Approval Queue ━━━

Table of posts pending approval:
  Columns: User | Caption (truncated) | Platforms | Scheduled For | Media Count | Actions
  [Approve] → POST (green button) | [Reject] → opens rejection modal with reason textarea

Empty state: "No posts awaiting approval. All clear! 🎉"

━━━ PAGE 6: Admin/Overview.vue — Admin Overview ━━━

Stat cards: Total Posts | Scheduled | Pending Approval (badge with count) | Published Today | Failed | Connected Accounts
Platform breakdown: horizontal bar showing account count per platform
Recent posts table: User | Caption | Platforms | Status | Scheduled At

━━━ PAGE 7: Admin/Settings.vue — Admin Settings ━━━

Sections:
  General: Enable toggle, Approval Required toggle, Max Accounts Per User, Max Media MB
  AI: AI Model (caption), Best Time Model
  RSS: Poll Interval Minutes
  Analytics: Pull Analytics Daily toggle, Carousel Max Slides, First Comment toggle
[Save] button.

STREAMING RULES (non-negotiable for AiCaptionPanel):
- POST + fetch ReadableStream for caption generation — NEVER EventSource
- AbortController to cancel previous request when [Generate] clicked again
- sessionStorage for session IDs if needed
- X-Accel-Buffering: no on server side (already in CaptionController)
```

---

## STEP 16 — Pest Tests

### DEEPSEEK PROMPT 16

```
You are building ADDON 08: Social Media Scheduler Pro for MakeAI (Laravel 13).

Create: addons/social-scheduler/tests/Feature/SocialSchedulerTest.php
Namespace: Addons\SocialScheduler\Tests\Feature
PestPHP syntax. RefreshDatabase. Mock Http facade for all external API calls.

━━━ ACCOUNT TESTS ━━━
it('user can connect a social account via oauth callback')
it('access_token is always stored encrypted, never plaintext in DB')
it('disconnect marks account inactive and clears tokens')
it('throws AccountLimitException when user exceeds max connected accounts')
it('expired token is detected by isTokenExpired accessor')

━━━ POST CREATION TESTS ━━━
it('user can create a draft post without a schedule')
it('scheduled post with approval required becomes pending_approval automatically')
it('scheduled post without approval required stays scheduled')
it('media files are stored in storage and linked to post')
it('platform rows created for each selected platform on post save')
it('user cannot edit a post that is in publishing or published status')
it('deleting a post cascades to media files being removed from storage')

━━━ PUBLISHING TESTS (mock Http, no real API calls) ━━━
it('PublishSocialPost job dispatches platform-specific jobs for due posts')
it('PublishToInstagramJob marks platform as published on success')
it('PublishToInstagramJob marks platform as failed and stores error on exception')
it('PublishToInstagramJob posts first comment after publish when configured')
it('PublishToTwitterJob splits long captions into thread tweets')
it('PublishToTikTokJob skips post with no video and sets skipped status')
it('PublishToPinterestJob skips post with no image and sets skipped status')
it('CheckPostPublishStatus marks post as published when all platforms succeed')
it('CheckPostPublishStatus marks post as partial when some platforms fail')
it('CheckPostPublishStatus marks post as failed when all platforms fail')
it('failed job increments attempt_count on SsPostPlatform')

━━━ CALENDAR TESTS ━━━
it('calendar events endpoint returns posts in date range for current user only')
it('reschedule updates scheduled_at and returns success')
it('cannot reschedule a post to a time in the past')

━━━ RSS TESTS ━━━
it('PollRssFeeds job fetches new items and creates draft posts')
it('PollRssFeeds marks feed as error on HTTP failure')
it('already processed items are not re-created (last_item_guid prevents duplicates)')

━━━ ANALYTICS TESTS ━━━
it('FetchPostAnalytics skips when analytics pull is disabled in settings')
it('analytics are upserted not duplicated on re-fetch')
it('analytics endpoint returns data scoped to current user only')

━━━ APPROVAL TESTS ━━━
it('admin can approve a pending post and it becomes scheduled')
it('admin can reject a post with a reason and it returns to draft')
it('approval routes require addon.social.approve permission')
it('regular user cannot access approval routes')

━━━ AI CAPTION TESTS ━━━
it('caption generate endpoint streams a response for valid input')
it('caption generate is rate limited to 20 per minute')
it('caption generate returns 422 for invalid platform')

For API call mocks:
  Http::fake([
    'graph.facebook.com/*' => Http::response(['id' => '123456'], 200),
    'api.twitter.com/*'    => Http::response(['data' => ['id' => '789']], 200),
    'api.linkedin.com/*'   => Http::response(['id' => 'urn:li:ugcPost:123'], 201),
    // etc.
  ])
  AiService: mock via app()->instance(AiService::class, Mockery::mock(AiService::class))
```

---

## IMPLEMENTATION SEQUENCE NOTES

1. **Steps 1–4** — scaffold + migrations first. Verify clean migration before any service code.
2. **Step 5** (SocialAccountService) — implement without OAuth drivers first (stubs), add real drivers per platform iteratively. TikTok and Pinterest need third-party Socialite packages — install these before running the prompt.
3. **Steps 6–7** (publisher jobs) — implement with Http::fake() tests first, then real API calls. Start with Facebook (simplest Graph API), then Instagram, Twitter, LinkedIn. TikTok/Pinterest/YouTube last (most complex).
4. **Steps 8–10** (AI services) — independent, can be built in parallel with publishers.
5. **Step 11** (analytics) — implement last in backend; it's optional for v1.0 launch.
6. **Steps 12–14** (controllers + routes) — together.
7. **Step 15** (Vue) — Calendar is the most complex page. Build Composer and Accounts first, then Calendar, then Analytics.
8. **Step 16** (tests) — run throughout, not just at the end.

---

## PLATFORM OAUTH PACKAGES TO INSTALL BEFORE STEP 5

```bash
# Pinterest OAuth via SocialiteProviders
composer require socialiteproviders/pinterest
# Add in EventServiceProvider: \SocialiteProviders\Manager\SocialiteWasCalled::class => [\SocialiteProviders\Pinterest\PinterestExtendSocialite::class]

# TikTok OAuth (choose one):
composer require amirezaeb/socialite-tiktok
# OR implement a custom Socialite driver per TikTok Login Kit docs

# YouTube uses core Google Socialite driver — no extra package needed
# Twitter uses core twitter-oauth-2 — verify it's already registered in core
```

---

## CRITICAL INVARIANTS (repeat for every DeepSeek session)

```
AI ENGINE:     App\Services\AI\AiService — NEVER LLPhant, NEVER raw Http::post to openai.com
STREAMING:     POST + fetch ReadableStream — NEVER EventSource
SSE HEADER:    X-Accel-Buffering: no — ALL streaming responses
FINALLY:       Always in response()->stream() callback
TOKENS:        access_token + refresh_token ALWAYS encrypted (Crypt::encryptString)
               NEVER in $fillable without encrypt-on-set override
               NEVER in toArray()/toJson() output ($hidden)
APP NAME:      settings('app_name') — NEVER hardcode
USER ID:       $user->ulid public-facing; $user->id DB only
QUEUES:        social → publish jobs | ai → caption generation | low → analytics ingestion
APPROVAL:      Check addon_setting('social-scheduler', 'approval_required') in SsScheduledPost boot, not in controller
CSRF:          OAuth callback routes must be in VerifyCsrfToken $except
N+1:           Always eager load: with(['postPlatforms','media','socialAccount'])
TRANSLATE:     translate() PHP / $t() Vue — ALL strings
```

---

# ADDON 09: AI Knowledge Base (Public) — Implementation Guide

> **Slug:** `public-knowledge-base`
> **Queue:** `embeddings` (ingestion), `ai` (RAG query)
> **AI engine:** `laravel/ai` via `AiService` only — no LLPhant, no raw SDKs
> **Vector store:** MySQL `kb_embeddings` table using `JSON` column + PHP cosine similarity
>   (shared-hosting safe — no pgvector, no Pinecone, no Qdrant required)

---

## WHAT THIS ADDON BUILDS

A fully public help center at `/help` where visitors type a question, the system does
semantic vector search across admin-written KB articles, and an AI generates a cited answer.
Admin creates and organizes articles via Tiptap rich text editor. Includes voting, SEO,
analytics, and a `<script>` embeddable widget for external sites.

---

## ARCHITECTURE OVERVIEW

```
Admin writes article (Tiptap)
        │
        ▼
KbArticle saved → IngestKbArticle job dispatched (queue: embeddings)
        │
        ▼
Job: chunk article body → AiService::embedText() per chunk → stored in kb_embeddings

Public visitor types question (/help)
        │
        ▼
KbSearchController → AiService::embedText(question) → cosine similarity against kb_embeddings
        │ top-K chunks retrieved
        ▼
AiService::complete() → cited answer generated with article sources
        │
        ▼
KbSearch log saved → streamed back to Vue via POST + ReadableStream
```

---

## STEP-BY-STEP BUILD ORDER

```
Step 1  → addon.json + AddonServiceProvider
Step 2  → Migrations (5 tables)
Step 3  → Models + Relationships
Step 4  → Seeders (default settings + demo articles)
Step 5  → IngestKbArticle job (embeddings pipeline)
Step 6  → KbSearchService (cosine similarity + RAG answer)
Step 7  → Admin Controllers + FormRequests (categories, articles, settings, analytics)
Step 8  → Public Controllers (search, article view, vote, widget)
Step 9  → Routes (admin + public + widget API)
Step 10 → Admin Vue pages (Category manager, Article editor, Settings, Analytics)
Step 11 → Public Vue pages (Help center home, Article view, Search results)
Step 12 → Embeddable Widget (vanilla JS bundle)
Step 13 → Pest tests
```

---

## STEP 1 — addon.json + AddonServiceProvider

### DEEPSEEK PROMPT 1

```
You are building an addon for MakeAI — a Laravel 13 + Vue 3 + Inertia.js SaaS platform.

Create two files for ADDON 09: AI Knowledge Base (Public).

━━━ FILE 1: addons/public-knowledge-base/addon.json ━━━

Required fields:
{
  "name": "AI Knowledge Base",
  "slug": "public-knowledge-base",
  "version": "1.0.0",
  "description": "Public help center powered by AI semantic search and RAG-generated cited answers.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 1,
  "requires_pro": false,
  "admin_menu": [
    { "parent": "Content",  "label": "Knowledge Base", "route": "addon.kb.admin.articles.index",  "icon": "ti-help-square", "permission": "addon.kb.articles.manage" },
    { "parent": "Settings", "label": "KB Settings",    "route": "addon.kb.admin.settings",        "icon": "ti-settings",    "permission": "addon.kb.settings.manage" }
  ],
  "settings": [
    { "key": "enabled",             "type": "boolean", "label": "Enable Public KB",           "default": true },
    { "key": "public_slug",         "type": "string",  "label": "Public URL slug",            "default": "help" },
    { "key": "page_title",          "type": "string",  "label": "Page Title",                 "default": "Help Center" },
    { "key": "page_description",    "type": "string",  "label": "Page Meta Description",      "default": "" },
    { "key": "ai_model",            "type": "string",  "label": "AI Model for answers",       "default": "gpt-4o-mini" },
    { "key": "embedding_model",     "type": "string",  "label": "Embedding Model",            "default": "text-embedding-3-small" },
    { "key": "top_k",               "type": "integer", "label": "Top-K chunks for RAG",       "default": 5 },
    { "key": "max_answer_tokens",   "type": "integer", "label": "Max answer tokens",          "default": 512 },
    { "key": "show_vote_buttons",   "type": "boolean", "label": "Show vote buttons",          "default": true },
    { "key": "allow_guest_search",  "type": "boolean", "label": "Allow guest search",         "default": true },
    { "key": "widget_enabled",      "type": "boolean", "label": "Enable embeddable widget",   "default": false },
    { "key": "widget_accent_color", "type": "color",   "label": "Widget accent color",        "default": "#10b981" }
  ],
  "permissions": [
    { "slug": "addon.kb.articles.manage", "name": "Manage KB Articles & Categories", "group": "Knowledge Base" },
    { "slug": "addon.kb.settings.manage", "name": "Manage KB Settings",              "group": "Knowledge Base" }
  ],
  "hooks": []
}

━━━ FILE 2: addons/public-knowledge-base/AddonServiceProvider.php ━━━

Namespace: Addons\PublicKnowledgeBase

Register:
- Blade views from: __DIR__ . '/resources/views'
- Routes from: __DIR__ . '/routes/web.php' and routes/api.php
- Migrations from: __DIR__ . '/database/migrations'
- In boot(): load routes only if addon is active (is_addon_active('public-knowledge-base'))
- In boot(): share 'kbSettings' via Inertia::share() — only safe fields
  (enabled, public_slug, page_title, page_description, show_vote_buttons,
   allow_guest_search, widget_enabled, widget_accent_color)
  Never share: ai_model, embedding_model, top_k, max_answer_tokens
- Register console command: IngestAllKbArticles (for re-ingestion on model change)

DO NOT reference LLPhant. AI calls go through App\Services\AI\AiService only.
DO use settings() helper for all addon settings via prefix 'kb_' (e.g. settings('kb_enabled')).
```

---

## STEP 2 — Migrations (5 tables)

### DEEPSEEK PROMPT 2

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create 5 migration files in addons/public-knowledge-base/database/migrations/.
All tables prefixed kb_. All primary keys bigint unsigned auto-increment.
All timestamps: created_at, updated_at.

━━━ MIGRATION 1: create_kb_categories_table ━━━

kb_categories
  id
  slug          varchar(100) UNIQUE NOT NULL
  name          varchar(150) NOT NULL
  description   text NULL
  icon          varchar(50) NULL                -- Tabler icon name e.g. 'ti-lock'
  sort_order    smallint DEFAULT 0
  is_active     boolean DEFAULT true
  articles_count int UNSIGNED DEFAULT 0        -- denormalized counter
  meta_title    varchar(160) NULL
  meta_desc     varchar(320) NULL
  created_at, updated_at
  INDEX (is_active, sort_order)

━━━ MIGRATION 2: create_kb_articles_table ━━━

kb_articles
  id
  ulid          char(26) UNIQUE NOT NULL        -- public-facing ID
  kb_category_id bigint UNSIGNED FK → kb_categories.id ON DELETE SET NULL NULL
  title         varchar(255) NOT NULL
  slug          varchar(255) UNIQUE NOT NULL
  excerpt       varchar(500) NULL
  body          longtext NOT NULL               -- Tiptap HTML
  body_plain    longtext NULL                   -- stripped plain text for embedding
  status        enum('draft','published') DEFAULT 'draft'
  sort_order    smallint DEFAULT 0
  views         int UNSIGNED DEFAULT 0
  helpful_count int UNSIGNED DEFAULT 0
  not_helpful_count int UNSIGNED DEFAULT 0
  embed_status  enum('pending','processing','done','failed') DEFAULT 'pending'
  embed_error   text NULL
  embedded_at   timestamp NULL
  meta_title    varchar(160) NULL
  meta_desc     varchar(320) NULL
  created_by    bigint UNSIGNED FK → users.id ON DELETE SET NULL NULL
  published_at  timestamp NULL
  created_at, updated_at
  INDEX (status, published_at)
  INDEX (kb_category_id, status)
  INDEX (embed_status)

━━━ MIGRATION 3: create_kb_embeddings_table ━━━

kb_embeddings
  id
  kb_article_id bigint UNSIGNED NOT NULL FK → kb_articles.id ON DELETE CASCADE
  chunk_index   smallint UNSIGNED NOT NULL      -- 0-based chunk position within article
  chunk_text    text NOT NULL                   -- the source text for this chunk
  embedding     json NOT NULL                   -- float[] vector from embedding model
  token_count   smallint UNSIGNED DEFAULT 0
  created_at, updated_at
  INDEX (kb_article_id)
  UNIQUE (kb_article_id, chunk_index)

Note on vector search: no pgvector needed. PHP cosine similarity computed in
KbSearchService over the top-200 candidates (ORDER BY kb_article_id, filtered by
status=published). For typical help centers (<500 articles, <5000 chunks) this is
fast enough on shared hosting. Document this clearly in code comments.

━━━ MIGRATION 4: create_kb_searches_table ━━━

kb_searches
  id
  session_id    varchar(64) INDEX NULL          -- anonymous session
  user_id       bigint UNSIGNED FK → users.id ON DELETE SET NULL NULL
  query         varchar(500) NOT NULL
  results_count smallint DEFAULT 0             -- how many articles matched
  was_answered  boolean DEFAULT false           -- AI produced an answer (not just no-results)
  article_ids   json NULL                       -- [id, id, ...] of cited articles
  created_at                                    -- no updated_at (append-only log)
  INDEX (created_at)
  INDEX (was_answered)

━━━ MIGRATION 5: create_kb_article_votes_table ━━━

kb_article_votes
  id
  kb_article_id bigint UNSIGNED NOT NULL FK → kb_articles.id ON DELETE CASCADE
  session_id    varchar(64) NOT NULL
  user_id       bigint UNSIGNED FK → users.id ON DELETE SET NULL NULL
  vote          tinyint NOT NULL               -- 1 = helpful, -1 = not helpful
  created_at, updated_at
  UNIQUE (kb_article_id, session_id)           -- one vote per session per article
  INDEX (kb_article_id, vote)

Use standard Laravel migration syntax. Add foreign key constraints with constrained().
```

---

## STEP 3 — Models + Relationships

### DEEPSEEK PROMPT 3

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create 4 Eloquent models in addons/public-knowledge-base/app/Models/.
All models use namespace Addons\PublicKnowledgeBase\Models.

━━━ KbCategory.php ━━━
- $fillable: slug, name, description, icon, sort_order, is_active, meta_title, meta_desc
- $casts: is_active → boolean, sort_order → integer
- Relationship: hasMany(KbArticle::class)
- Scope: scopeActive($q) → $q->where('is_active', true)->orderBy('sort_order')
- Static boot: when deleting, set articles' kb_category_id = null (already covered by ON DELETE SET NULL)
- Accessor: getPublishedArticlesCountAttribute() → $this->articles()->published()->count()
- Auto-generate slug from name on saving if slug is empty (Str::slug)

━━━ KbArticle.php ━━━
- $fillable: ulid, kb_category_id, title, slug, excerpt, body, body_plain, status,
             sort_order, embed_status, embed_error, embedded_at, meta_title, meta_desc,
             created_by, published_at
- $casts: published_at → datetime, embedded_at → datetime, helpful_count → integer,
          not_helpful_count → integer
- $appends: ['helpful_percent']
- Relationships:
    belongsTo(KbCategory::class)
    hasMany(KbEmbedding::class)
    hasMany(KbArticleVote::class)
    belongsTo(User::class, 'created_by', 'id')
- Scopes:
    scopePublished($q) → status=published AND published_at <= now()
    scopeInCategory($q, $categoryId)
    scopePendingEmbed($q) → embed_status IN ('pending','failed')
- Accessor: getHelpfulPercentAttribute():
    total = helpful_count + not_helpful_count
    return total > 0 ? round(helpful_count / total * 100) : null
- Boot: on creating → generate ULID if not set; generate slug from title if not set; strip HTML from body → set body_plain; on saving → if body changed, set embed_status = 'pending', embed_error = null, embedded_at = null
- Route model binding key: ulid

━━━ KbEmbedding.php ━━━
- $fillable: kb_article_id, chunk_index, chunk_text, embedding, token_count
- $casts: embedding → array (JSON cast), chunk_index → integer, token_count → integer
- Relationship: belongsTo(KbArticle::class)

━━━ KbArticleVote.php ━━━
- $fillable: kb_article_id, session_id, user_id, vote
- $casts: vote → integer
- Relationships: belongsTo(KbArticle::class), belongsTo(User::class)
```

---

## STEP 4 — Seeder

### DEEPSEEK PROMPT 4

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create: addons/public-knowledge-base/database/seeders/KbSeeder.php
Namespace: Addons\PublicKnowledgeBase\Database\Seeders

The seeder creates demo data only if no kb_categories rows exist (idempotent).

Create 3 demo categories:
  1. slug='getting-started', name='Getting Started', icon='ti-rocket', sort_order=1
  2. slug='account-billing',  name='Account & Billing', icon='ti-credit-card', sort_order=2
  3. slug='troubleshooting',  name='Troubleshooting', icon='ti-tool', sort_order=3

Create 2 demo articles per category (6 total). Example for category 1:
  Article 1:
    title: 'How to create your first AI document'
    status: published
    published_at: now()
    excerpt: 'Learn how to generate your first piece of AI content in under 2 minutes.'
    body: '<h2>Getting Started</h2><p>Welcome to ' . settings('app_name') . '...</p>
           <h3>Step 1: Choose a tool</h3><p>Navigate to the AI Tools section...</p>
           <h3>Step 2: Fill in the form</h3><p>Enter your topic and preferences...</p>
           <h3>Step 3: Generate</h3><p>Click Generate and your content will appear...</p>'
    embed_status: pending (the IngestKbArticle job will pick it up)
  Article 2:
    title: 'Understanding credits and usage limits'
    status: published
    ...etc

For categories 2 and 3, make up realistic help center content.

After creating articles, dispatch IngestKbArticle job for each article on queue 'embeddings'
so embeddings are created on first install.

All slugs auto-generated from titles via Str::slug() in the model boot.
Set created_by to the first admin user: User::where('is_admin', true)->first()?->id
```

---

## STEP 5 — IngestKbArticle Job (Embeddings Pipeline)

### DEEPSEEK PROMPT 5

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create: addons/public-knowledge-base/app/Jobs/IngestKbArticle.php
Namespace: Addons\PublicKnowledgeBase\Jobs

This job chunks a KB article's plain text and generates embeddings using AiService.
It runs on queue: 'embeddings'. Max attempts: 3. Backoff: [60, 300, 900] seconds.

━━━ Constructor ━━━
  __construct(public readonly int $articleId)

━━━ handle(AiService $ai) ━━━

Step 1: Load article
  $article = KbArticle::find($this->articleId)
  If null or status !== 'published': return (silently, article may have been deleted or unpublished)
  Update: embed_status = 'processing'

Step 2: Get text to embed
  Use $article->body_plain (already stripped HTML in model boot).
  If empty: mark embed_status = 'failed', embed_error = 'No plain text content', return.

Step 3: Chunk text
  Split into chunks of MAX 400 tokens (approx 1600 chars). Use this simple strategy:
    - Split body_plain by double-newline paragraphs
    - Accumulate paragraphs until 1600 chars, then start a new chunk
    - Minimum chunk size: 100 chars (discard shorter trailing chunks)
    - Always prepend article title to chunk 0: "Title: {title}\n\n{chunk_text}"
    - For chunks 1+: prepend "From article: {title}\n\n{chunk_text}" (context anchor)
  Result: array of strings

Step 4: Generate embeddings
  $model = settings('kb_embedding_model', 'text-embedding-3-small')
  For each chunk:
    $vector = $ai->embedText($chunk, 'openai')  // returns float[]
    Estimate token_count = (int)(strlen($chunk) / 4)  // rough estimate

Step 5: Save embeddings (upsert)
  Delete existing: KbEmbedding::where('kb_article_id', $article->id)->delete()
  Insert new: KbEmbedding::insert([...]) in one query (not loop of ::create)

Step 6: Mark done
  $article->update([
    'embed_status' => 'done',
    'embedded_at'  => now(),
    'embed_error'  => null,
  ])

━━━ failed(Throwable $exception) ━━━
  KbArticle::where('id', $this->articleId)->update([
    'embed_status' => 'failed',
    'embed_error'  => Str::limit($exception->getMessage(), 500),
  ])

━━━ Also create: app/Console/Commands/IngestAllKbArticles.php ━━━
Signature: kb:ingest-all
Description: Re-ingests all published KB articles (use after changing embedding model)
Loops KbArticle::published()->chunk(50) → dispatch IngestKbArticle for each
Shows progress bar.

CRITICAL RULES:
- Use App\Services\AI\AiService — NEVER raw HTTP calls to OpenAI
- Never reference LLPhant
- embedText() call: $ai->embedText(string $text, string $provider = 'openai'): array
```

---

## STEP 6 — KbSearchService (Cosine Similarity + RAG Answer)

### DEEPSEEK PROMPT 6

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create: addons/public-knowledge-base/app/Services/KbSearchService.php
Namespace: Addons\PublicKnowledgeBase\Services

This service handles the full RAG pipeline: embed query → cosine search → AI answer.

━━━ Method signatures ━━━

public function __construct(private AiService $ai) {}

/**
 * Main entry point. Returns a Generator for streaming the AI answer.
 * Caller streams this via response()->stream() with X-Accel-Buffering: no.
 *
 * Protocol (SSE-like but over POST + ReadableStream):
 *   First chunk type: "sources" — JSON array of matched articles
 *   Subsequent chunks: "delta" — streamed answer text tokens
 *   Final chunk: "done"
 *
 * Format per chunk: JSON line followed by \n
 * {"type":"sources","articles":[{"id":1,"ulid":"...","title":"...","slug":"...","excerpt":"..."}]}
 * {"type":"delta","text":"The answer is..."}
 * {"type":"done","query_id":123}
 */
public function searchAndAnswer(
    string $query,
    ?int $userId,
    string $sessionId
): Generator

━━━ searchAndAnswer() implementation ━━━

Step 1: Embed the query
  $queryVector = $this->ai->embedText($query, 'openai')

Step 2: Fetch candidate chunks
  Load ALL kb_embeddings joined with kb_articles where:
    - kb_articles.status = 'published'
    - kb_articles.published_at <= now()
    - kb_articles.embed_status = 'done'
  Select: kb_embeddings.id, kb_article_id, chunk_index, chunk_text, embedding,
          kb_articles.title, kb_articles.slug, kb_articles.ulid, kb_articles.excerpt
  Limit to 500 rows (for perf) ordered by kb_article_id

Step 3: Cosine similarity in PHP
  private function cosineSimilarity(array $a, array $b): float
    $dot = 0.0; $normA = 0.0; $normB = 0.0;
    foreach ($a as $i => $val) {
        $dot   += $val * $b[$i];
        $normA += $val * $val;
        $normB += $b[$i] * $b[$i];
    }
    return ($normA > 0 && $normB > 0)
        ? $dot / (sqrt($normA) * sqrt($normB))
        : 0.0;

  Compute similarity for each chunk vs $queryVector.
  Sort descending. Take top settings('kb_top_k', 5) chunks.

Step 4: De-duplicate by article
  Collect unique articles from top-K chunks (preserve order of first appearance).
  Max 3 unique articles as sources (merge their chunks' text for context).

Step 5: If no chunks found (similarity of top result < 0.3):
  yield json_encode(['type'=>'sources','articles'=>[]]) . "\n"
  yield json_encode(['type'=>'delta','text'=>'I couldn\'t find a relevant answer. Please try rephrasing your question or browse the categories below.']) . "\n"
  Log KbSearch (was_answered: false)
  yield json_encode(['type'=>'done','query_id'=>$log->id]) . "\n"
  return

Step 6: Yield sources first (before AI call, so UI can show article cards immediately)
  yield json_encode(['type'=>'sources','articles'=>[... article data ...]]) . "\n"

Step 7: Build RAG prompt
  $context = collect($topChunks)->map(fn($c) =>
    "Article: {$c->title}\n{$c->chunk_text}"
  )->implode("\n\n---\n\n");

  $systemPrompt = "You are a helpful support assistant for " . settings('app_name') . ".
  Answer the user's question using ONLY the provided knowledge base context.
  At the end of your answer, list the source articles you referenced as:
  Sources: [Article Title 1], [Article Title 2]
  If the context does not contain the answer, say so honestly.
  Be concise. Max " . settings('kb_max_answer_tokens', 512) . " tokens.";

  $userPrompt = "Context:\n{$context}\n\nQuestion: {$query}";

Step 8: Stream AI answer
  $request = new CompletionRequest(
    model: settings('kb_ai_model', 'gpt-4o-mini'),
    messages: [
      ['role'=>'system','content'=>$systemPrompt],
      ['role'=>'user',  'content'=>$userPrompt],
    ],
    maxTokens: (int) settings('kb_max_answer_tokens', 512),
  );

  foreach ($this->ai->stream($request) as $token) {
    yield json_encode(['type'=>'delta','text'=>$token]) . "\n";
  }

Step 9: Log and close
  $log = KbSearch::create([
    'session_id'    => $sessionId,
    'user_id'       => $userId,
    'query'         => $query,
    'results_count' => count($uniqueArticles),
    'was_answered'  => true,
    'article_ids'   => collect($uniqueArticles)->pluck('id')->toArray(),
  ]);
  yield json_encode(['type'=>'done','query_id'=>$log->id]) . "\n";

━━━ Additional public method ━━━
public function getRelatedArticles(KbArticle $article, int $limit = 4): Collection
  Embed article title (cache 1 hour: "kb.related.{$article->id}")
  Cosine search → return top $limit articles, excluding $article itself
  Returns Collection<KbArticle> with: id, ulid, title, slug, excerpt, category name

CRITICAL:
- Use App\Services\AI\AiService — NEVER raw OpenAI HTTP calls
- CompletionRequest DTO from laravel/ai SDK
- Streaming: POST + ReadableStream (never EventSource/SSE with data: framing)
- X-Accel-Buffering: no — set in the controller (not here)
- finally block enforced in the controller's stream response
```

---

## STEP 7 — Admin Controllers + FormRequests

### DEEPSEEK PROMPT 7

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create admin controllers in:
  addons/public-knowledge-base/app/Http/Controllers/Admin/

Namespace: Addons\PublicKnowledgeBase\Http\Controllers\Admin
All controllers extend App\Http\Controllers\Controller
All admin routes use middleware: ['auth:admin', 'permission:addon.kb.articles.manage']
Settings routes use: ['auth:admin', 'permission:addon.kb.settings.manage']

━━━ KbCategoryController ━━━ (resource: index, store, update, destroy)

index(): return Inertia 'PublicKnowledgeBase::Admin/Categories/Index'
  props: categories (paginated 20, with articles_count), can (manage)

store(KbCategoryRequest $request):
  KbCategory::create($request->validated())
  redirect back with flash 'created'

update(KbCategoryRequest $request, KbCategory $category):
  $category->update($request->validated())
  redirect back with flash 'updated'

destroy(KbCategory $category):
  Guard: if $category->articles()->exists() → back()->withErrors(['error' => 'Category has articles. Reassign or delete them first.'])
  $category->delete()
  redirect back with flash 'deleted'

━━━ KbArticleController ━━━ (index, create, store, edit, update, destroy, reEmbed)

index():
  Articles paginated 20, with ['category','creator']
  Filterable by: status (draft/published), category_id, embed_status, search (title)
  return Inertia 'PublicKnowledgeBase::Admin/Articles/Index'
  props: articles, categories (for filter dropdown), filters (current)

create():
  return Inertia 'PublicKnowledgeBase::Admin/Articles/Form'
  props: article (null), categories, tiptap config

store(KbArticleRequest $request):
  $article = KbArticle::create([
    ...$request->validated(),
    'created_by' => auth('admin')->id(),
    'published_at' => $request->status === 'published' ? now() : null,
  ])
  if ($article->status === 'published') {
    IngestKbArticle::dispatch($article->id)->onQueue('embeddings')
  }
  redirect to edit with flash 'created'

edit(KbArticle $article):
  return Inertia 'PublicKnowledgeBase::Admin/Articles/Form'
  props: article (with category), categories, embed_status_label

update(KbArticleRequest $request, KbArticle $article):
  $wasPublished = $article->status === 'published';
  $article->update($request->validated())
  if ($article->status === 'published' && $article->embed_status === 'pending') {
    IngestKbArticle::dispatch($article->id)->onQueue('embeddings')
  }
  redirect back with flash 'updated'

destroy(KbArticle $article):
  $article->delete()  // cascade deletes embeddings + votes
  redirect to index with flash 'deleted'

reEmbed(KbArticle $article): (POST route)
  $article->update(['embed_status' => 'pending', 'embed_error' => null])
  IngestKbArticle::dispatch($article->id)->onQueue('embeddings')
  return response()->json(['message' => 'Re-embedding queued'])

━━━ KbSettingsController ━━━

edit():
  return Inertia 'PublicKnowledgeBase::Admin/Settings'
  props: settings (all addon settings), models_list (from AiService or hardcoded list)

update(KbSettingsRequest $request):
  foreach ($request->validated() as $key => $value) {
    settings_set('kb_' . $key, $value)
  }
  redirect back with flash 'saved'

━━━ KbAnalyticsController ━━━

index():
  return Inertia 'PublicKnowledgeBase::Admin/Analytics'
  props:
    - searches_today: KbSearch::whereDate('created_at', today())->count()
    - searches_7d:    KbSearch::where('created_at','>=',now()->subDays(7))->count()
    - answer_rate:    percentage of was_answered=true in last 7d
    - unanswered:     KbSearch::where('was_answered',false)
                               ->latest()
                               ->limit(20)
                               ->pluck('query')
    - top_queries:    KbSearch group by query, count, order by count desc, limit 20
    - top_articles:   KbArticle published, order by views desc, limit 10
                      (select id, title, views, helpful_percent)
    - embed_status_summary: count per embed_status value

━━━ FormRequests ━━━

KbCategoryRequest: slug nullable (auto-generated if null), name required max:150,
  description nullable text, icon nullable max:50, sort_order integer min:0, is_active boolean,
  meta_title nullable max:160, meta_desc nullable max:320.
  Slug unique in kb_categories ignoring current record on update.

KbArticleRequest: kb_category_id nullable exists:kb_categories,id, title required max:255,
  body required, excerpt nullable max:500, status in:draft,published,
  sort_order integer min:0, meta_title nullable max:160, meta_desc nullable max:320.

KbSettingsRequest: validate all settings fields with type-appropriate rules.

RULES:
- Never N+1: always eager load relationships
- Return Inertia responses, never blade views
- Use settings() and settings_set() helpers — never direct DB::table('settings')
- Use translate() for all user-facing strings
```

---

## STEP 8 — Public Controllers

### DEEPSEEK PROMPT 8

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create public controllers in:
  addons/public-knowledge-base/app/Http/Controllers/Public/

Namespace: Addons\PublicKnowledgeBase\Http\Controllers\Public
Middleware on all public routes: addon.kb.enabled (custom middleware, checks settings('kb_enabled'))

━━━ Middleware: KbEnabledMiddleware ━━━
  if (!settings('kb_enabled', true)) abort(404)
  if user auth required: if (!settings('kb_allow_guest_search') && !auth()->check()) redirect('/login')

━━━ KbHomeController ━━━

index():
  $categories = KbCategory::active()
    ->withCount(['articles' => fn($q) => $q->published()])
    ->get()
  $featuredArticles = KbArticle::published()
    ->orderByDesc('views')
    ->limit(6)
    ->with('category')
    ->get(['id','ulid','title','slug','excerpt','views','helpful_count','published_at'])
  return Inertia 'PublicKnowledgeBase::Public/Home'
  props: categories, featuredArticles, meta (title/desc from settings)
  Add Head: <title>, <meta description>, <meta og:*>, schema.org WebPage JSON-LD

━━━ KbArticleController (public) ━━━

show(string $slug):
  $article = KbArticle::published()->where('slug', $slug)->with('category')->firstOrFail()
  $article->increment('views')  // fire and forget — use DB::table() to avoid model events
  $related = app(KbSearchService::class)->getRelatedArticles($article)
  $userVote = null
  if (auth()->check() || request()->hasSession()) {
    $userVote = KbArticleVote::where('kb_article_id', $article->id)
      ->where('session_id', session()->getId())
      ->value('vote')
  }
  return Inertia 'PublicKnowledgeBase::Public/Article'
  props: article (with category, helpful_count, not_helpful_count, helpful_percent),
         related, userVote, meta

  SEO: <title>{article.meta_title ?: article.title}, meta description, schema.org Article JSON-LD:
    { "@type": "Article", "headline": title, "datePublished": published_at,
      "dateModified": updated_at, "author": { "@type": "Organization", "name": settings('app_name') } }

━━━ KbSearchController (public) ━━━

search(KbSearchRequest $request): StreamedResponse
  Validate: query required string min:2 max:500
  Rate limit: throttle:20,1 (20 searches per minute per IP)

  $sessionId = session()->getId()
  $userId    = auth()->id()

  return response()->stream(function() use ($request, $sessionId, $userId) {
    try {
      $service = app(KbSearchService::class)
      foreach ($service->searchAndAnswer($request->query, $userId, $sessionId) as $chunk) {
        echo $chunk
        ob_flush()
        flush()
      }
    } finally {
      // Always runs — stream ends cleanly even on exception
    }
  }, 200, [
    'Content-Type'      => 'text/plain; charset=utf-8',
    'X-Accel-Buffering' => 'no',
    'Cache-Control'     => 'no-cache',
  ])

━━━ KbVoteController (public) ━━━

store(Request $request, KbArticle $article):
  Validate: vote in:1,-1
  $sessionId = session()->getId()

  KbArticleVote::updateOrCreate(
    ['kb_article_id' => $article->id, 'session_id' => $sessionId],
    ['user_id' => auth()->id(), 'vote' => $request->vote]
  )

  // Recalculate counts from DB (accurate)
  $helpful    = KbArticleVote::where('kb_article_id', $article->id)->where('vote', 1)->count()
  $notHelpful = KbArticleVote::where('kb_article_id', $article->id)->where('vote', -1)->count()
  $article->update(['helpful_count' => $helpful, 'not_helpful_count' => $notHelpful])

  return response()->json([
    'helpful_count'     => $helpful,
    'not_helpful_count' => $notHelpful,
    'your_vote'         => $request->vote,
  ])

━━━ KbWidgetController ━━━ (for embeddable widget — separate from main public routes)

search(Request $request): StreamedResponse
  Check: settings('kb_widget_enabled') || abort(403)
  Validate Origin header against settings('kb_widget_allowed_origins') if set
  Same streaming logic as KbSearchController::search() but:
  - No session (stateless, cookie-free)
  - session_id: sha256(request()->ip() . $request->input('widget_session', uniqid()))
  - Add CORS headers: Access-Control-Allow-Origin: * (widget is cross-origin by design)

STREAMING RULES (non-negotiable):
- POST + ReadableStream ONLY (never EventSource)
- X-Accel-Buffering: no on every streaming response
- finally block enforced on every stream callback
```

---

## STEP 9 — Routes

### DEEPSEEK PROMPT 9

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create two route files for the addon.

━━━ FILE: addons/public-knowledge-base/routes/web.php ━━━

use Addons\PublicKnowledgeBase\Http\Controllers\Admin\ ...
use Addons\PublicKnowledgeBase\Http\Controllers\Public\ ...

$publicSlug = settings('kb_public_slug', 'help')

// ── PUBLIC ROUTES ──────────────────────────────────────────────────
Route::middleware(['web', 'addon.kb.enabled'])
    ->prefix($publicSlug)
    ->name('addon.kb.public.')
    ->group(function () {
        Route::get('/',              [KbHomeController::class,    'index'])->name('home')
        Route::get('/article/{slug}', [KbArticleController::class, 'show'])->name('article')
        Route::post('/search',       [KbSearchController::class,  'search'])
            ->middleware('throttle:20,1')->name('search')
        Route::post('/vote/{article}', [KbVoteController::class,  'store'])->name('vote')
    })

// ── ADMIN ROUTES ───────────────────────────────────────────────────
Route::middleware(['web', 'auth:admin'])
    ->prefix('admin/kb')
    ->name('addon.kb.admin.')
    ->group(function () {

        Route::middleware('permission:addon.kb.articles.manage')->group(function () {
            Route::resource('categories', KbCategoryController::class)
                ->except(['show','create','edit'])
            Route::resource('articles', KbArticleController::class)
            Route::post('articles/{article}/re-embed', [KbArticleController::class, 'reEmbed'])
                ->name('articles.re-embed')
            Route::get('analytics', [KbAnalyticsController::class, 'index'])->name('analytics')
        })

        Route::middleware('permission:addon.kb.settings.manage')->group(function () {
            Route::get('settings',  [KbSettingsController::class, 'edit'])->name('settings')
            Route::put('settings',  [KbSettingsController::class, 'update'])
        })
    })

━━━ FILE: addons/public-knowledge-base/routes/api.php ━━━

// Widget search endpoint (cross-origin, stateless)
Route::middleware(['api'])
    ->prefix('api/kb-widget')
    ->name('addon.kb.widget.')
    ->group(function () {
        Route::post('/search', [KbWidgetController::class, 'search'])
            ->middleware('throttle:10,1')
            ->name('search')
        Route::options('/search', fn() => response('', 204, [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]))
    })

Note: The public slug is loaded from settings() at route registration time
(AddonServiceProvider::boot()). If settings() is unavailable (e.g. during
artisan route:cache before DB is seeded), default to 'help'.
Route cache is cleared on settings('kb_public_slug') change via settings_set() observer.
```

---

## STEP 10 — Admin Vue Pages

### DEEPSEEK PROMPT 10

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13 + Vue 3 + Inertia.js).

Create admin Vue pages in:
  addons/public-knowledge-base/resources/js/Pages/Admin/

All files: <script setup lang="ts">, TypeScript, Tabler Icons, Tailwind v4.
Use MakeAI design system: emerald green (primary), dark green-black sidebar.
Use $t() / useTranslate() for ALL user-facing strings. Never hardcode strings.
Use Intl API for dates. Never Options API.

━━━ PAGE 1: Categories/Index.vue ━━━

Layout: admin panel layout (AdminLayout)

Features:
- Header: "Knowledge Base Categories" + [+ New Category] button (opens inline create form or slide-over)
- Table: Name | Icon (Tabler icon preview) | Articles | Status | Sort Order | Actions (Edit, Delete)
- Inline edit (no page redirect for category CRUD — use slide-over panel)
- Delete guard: if article_count > 0 → show error toast "Reassign articles first"
- Drag-to-reorder: use vue-draggable-plus to reorder categories, send PATCH to update sort_order

Slide-over for Create/Edit:
  Fields: Name (text), Slug (auto-generated, editable), Icon (text with preview),
          Description (textarea), Is Active (toggle), Meta Title, Meta Description
  [Save] → Inertia.post/put, close slide-over on success

━━━ PAGE 2: Articles/Index.vue ━━━

Layout: AdminLayout
Header: "KB Articles" + [+ New Article] button → link to Articles/Form

Filter bar:
  - Search input (debounced 400ms, Inertia visit with preserveState)
  - Status filter: All / Draft / Published (select)
  - Category filter (select, populated from prop)
  - Embed status filter: All / Pending / Done / Failed

Table columns:
  Title | Category | Status (badge) | Embed Status (badge with icon:
    pending=ti-clock, processing=ti-loader-2 (spin), done=ti-check, failed=ti-alert-triangle)
  | Views | Votes (helpful%) | Updated | Actions

Actions per row:
  [Edit] → link to Form | [Re-Embed] → POST re-embed (only show if embed_status = failed/done)
  [Delete] → confirm modal

Embed status 'failed': show red badge + tooltip showing embed_error text.
Embed status 'processing': animate spinner.

━━━ PAGE 3: Articles/Form.vue ━━━

Layout: AdminLayout. Works for both create and edit (article prop is null for create).

Two-column layout (70/30):
  LEFT column:
    - Title input (large, auto-generates slug preview)
    - Slug field (editable, shows /help/article/{slug})
    - Tiptap v2 rich text editor for body (full toolbar: headings, bold, italic,
      lists, code, links, images — use MakeAI's existing TiptapEditor component)
    - Excerpt textarea (optional, 500 chars)

  RIGHT column (sticky):
    - Status toggle (Draft / Published) — on switch to Published show info:
      "Article will be indexed for AI search automatically"
    - Category select
    - Sort order number
    - Meta SEO section (collapsible):
        Meta Title (with char counter 160), Meta Description (320)
    - Embed status badge + "Re-embed now" button (visible in edit mode)
    - [Save Draft] and [Save & Publish] buttons

On save: Inertia.post/put with form data. Show loading state on buttons.

━━━ PAGE 4: Settings.vue ━━━

Layout: AdminLayout. Three-section settings page.

Section 1 — General:
  Enable Public KB (toggle)
  Public URL Slug (text, shows preview: /help)
  Page Title (text)
  Page Meta Description (textarea)
  Show Vote Buttons (toggle)
  Allow Guest Search (toggle)

Section 2 — AI Configuration:
  AI Model (ModelSelector component or select from models_list prop)
  Embedding Model (select: text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002)
  Top-K Chunks (number 1–20)
  Max Answer Tokens (number 128–2048)
  Info box: "Changing the embedding model requires re-indexing all articles. Run: php artisan kb:ingest-all"

Section 3 — Embeddable Widget:
  Enable Widget (toggle)
  Widget Accent Color (color picker)
  Widget Install Code (code block, copyable):
    <script src="https://{app_url}/addons/kb-widget.js"
            data-kb-url="https://{app_url}/api/kb-widget"
            data-accent="{accent_color}"></script>

[Save Settings] button at bottom.

━━━ PAGE 5: Analytics.vue ━━━

Layout: AdminLayout.

Stat cards row:
  - Searches Today | Searches (7d) | Answer Rate % | Published Articles

Two-column grid:
  LEFT:
    - "Unanswered Queries" list (queries where was_answered=false, last 20)
      Each row: the query text + [Create Article] link (prefills title in Form)
    - "Top Search Queries" table: Query | Count

  RIGHT:
    - "Top Articles by Views" table: Title | Views | Helpful %
    - "Embed Status Summary" small donut or bar showing pending/processing/done/failed counts

Empty states for each section when no data yet.
```

---

## STEP 11 — Public Vue Pages

### DEEPSEEK PROMPT 11

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13 + Vue 3 + Inertia.js).

Create public Vue pages in:
  addons/public-knowledge-base/resources/js/Pages/Public/

All files: <script setup lang="ts">, TypeScript, Tailwind v4.
These are FULL public pages — no admin layout. Use a clean, minimal KbLayout.vue.
Use $t() for strings. Use Intl API for dates.

━━━ KbLayout.vue (shared layout) ━━━
- Navbar: app logo (settings('app_name')), nav links: [Home] [Categories] [Back to App]
- Below navbar: full-width search bar (visible on all KB pages)
  - Large input: "Search the help center..."
  - As user types (debounced 600ms) → triggers search via POST fetch (ReadableStream)
  - Shows inline results below the search bar while typing
- Footer: minimal — app name + link back to main app

━━━ PAGE 1: Home.vue ━━━

Hero section:
  - Heading: {kbSettings.page_title} (from Inertia shared prop)
  - Sub-heading: {kbSettings.page_description}
  - Large search input (same as layout, focused by default)

Categories grid (3 columns on desktop, 1 on mobile):
  Each card: Tabler icon + Category name + Article count + Description excerpt
  Click → filter articles by that category (or navigate to /help?category=slug)

Featured Articles section:
  6 cards in a 3-col grid: Title + Excerpt + Views + Helpful% badge + Category badge

━━━ PAGE 2: Article.vue ━━━

Breadcrumb: Help Center → {category.name} → {article.title}

Article header:
  <h1>{article.title}</h1>
  Published date + category badge + views count

Article body:
  Render article.body as HTML (v-html, sanitized)
  Apply Tailwind prose class: class="prose prose-emerald max-w-none"

Vote widget (below body, if show_vote_buttons is true):
  "Was this article helpful?"
  [👍 Yes ({helpful_count})]  [👎 No ({not_helpful_count})]
  On click: POST /help/vote/{ulid} — optimistic update, then set userVote state
  After voting: disable buttons + show "Thanks for your feedback!"
  Show helpful_percent progress bar if total votes > 0

Related articles (sidebar or below on mobile):
  Up to 4 article cards with title + excerpt

━━━ PAGE 3: SearchResults.vue (inline component, used in KbLayout) ━━━

This is NOT a full page — it's the live search dropdown rendered below the search bar.

States:
  idle:       empty (nothing shown)
  loading:    skeleton cards (2-3 placeholder cards)
  sources:    article cards rendered as soon as {"type":"sources"} chunk arrives
  streaming:  AI answer text streams in below the article cards with a blinking cursor
  done:       final answer shown, "See full results" link if many articles
  no-results: "No results found. Try rephrasing your question." message
  error:      "Search unavailable. Please try again." (network/500 error)

Source article mini-cards (shown during streaming, before full answer):
  Article title + excerpt + category badge
  Clicking a card → navigate to article (search panel closes)

AI Answer box:
  Heading: "AI Answer" with a small sparkle icon (ti-sparkles)
  Streamed text renders with lightweight markdown: bold (**text**), line breaks, inline code
  Sources cited at bottom: "Sources: [Article 1], [Article 2]"

Implementation:
  Uses fetch() with response.body.getReader() to consume the POST streaming response.
  Parse each line as JSON: {"type":"sources"|"delta"|"done", ...}
  On "sources" → render article cards
  On "delta"   → append text to answerText ref
  On "done"    → set done state, stop cursor animation

  const reader = response.body!.getReader()
  const decoder = new TextDecoder()
  let buffer = ''
  while (true) {
    const { done, value } = await reader.read()
    if (done) break
    buffer += decoder.decode(value, { stream: true })
    const lines = buffer.split('\n')
    buffer = lines.pop()!
    for (const line of lines) {
      if (!line.trim()) continue
      const event = JSON.parse(line)
      // handle event.type
    }
  }

STREAMING RULES:
- POST + ReadableStream (fetch API) — NEVER EventSource
- session_id from sessionStorage: sessionStorage.getItem('kb_session') ??
  crypto.randomUUID() → store it back
- Abort previous request (AbortController) when new search starts
```

---

## STEP 12 — Embeddable Widget (Vanilla JS)

### DEEPSEEK PROMPT 12

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create the embeddable widget bundle. This is a standalone vanilla JS file
(NO Vue, NO framework dependencies) that external websites can include via a <script> tag.

File: addons/public-knowledge-base/resources/js/widget/kb-widget.js
Build output: public/addons/kb-widget.js (compiled via Vite, self-contained IIFE)

━━━ BEHAVIOR ━━━

1. Read config from script tag attributes:
   const script = document.currentScript
   const kbUrl       = script.getAttribute('data-kb-url')       // API base URL
   const accentColor = script.getAttribute('data-accent') || '#10b981'

2. Inject styles (inline CSS in IIFE — no external stylesheet):
   - FAB button: fixed bottom-right, 56px circle, accent color bg, white icon (❓)
   - Chat panel: 380×520px iframe OR inline div, white background, shadow, border-radius 12px
   - Positioned above FAB, hidden by default

3. Create DOM structure (appended to document.body):
   <div id="kb-widget-root">
     <div id="kb-chat-panel" hidden>
       <div class="kb-header"> <!-- accent color bg -->
         <span>Help Center</span>
         <button class="kb-close">✕</button>
       </div>
       <div class="kb-search-area">
         <input type="text" placeholder="Ask a question..." />
       </div>
       <div class="kb-results-area"> <!-- answer renders here --> </div>
     </div>
     <button id="kb-fab">❓</button>
   </div>

4. FAB click: toggle panel show/hide, focus input

5. Search: on input (debounced 800ms) OR on Enter:
   - POST {kbUrl}/search  { query, widget_session }
   - widget_session: sessionStorage.getItem('kb_widget_session') ?? crypto.randomUUID()
   - ReadableStream parsing (same as frontend, vanilla fetch + getReader())
   - Render sources as clickable links (open in new tab using the full article URL from source data)
   - Stream AI answer text into results area (plain text, no markdown parsing in widget)

6. Apply accent color as CSS variable on #kb-widget-root:
   root.style.setProperty('--kb-accent', accentColor)

7. Self-contained — no dependencies. Works in any website with a modern browser.
   Total bundle target: < 15KB gzipped.

━━━ Vite build config addition (vite.config.ts in addon) ━━━
  build: {
    lib: {
      entry:   'resources/js/widget/kb-widget.js',
      formats: ['iife'],
      name:    'KbWidget',
      fileName: () => 'kb-widget',
    },
    outDir:    '../../../../public/addons',
    emptyOutDir: false,
  }

The widget JS is served from the main MakeAI app's public/addons/ directory
so buyers can give clients a single <script> tag to embed.
```

---

## STEP 13 — Pest Tests

### DEEPSEEK PROMPT 13

```
You are building ADDON 09: AI Knowledge Base for MakeAI (Laravel 13).

Create: addons/public-knowledge-base/tests/Feature/KbTest.php
Namespace: Addons\PublicKnowledgeBase\Tests\Feature

Use PestPHP syntax. Mock AiService where AI calls are made.
Use RefreshDatabase trait.

━━━ CATEGORY TESTS ━━━
it('admin can create a kb category')
it('admin cannot delete a category that has articles')
it('category slug is auto-generated from name if not provided')
it('category list is ordered by sort_order')

━━━ ARTICLE TESTS ━━━
it('admin can create a kb article and it defaults to draft')
it('publishing an article dispatches IngestKbArticle job on embeddings queue')
it('article body change sets embed_status back to pending')
it('article plain text is auto-generated from html body on save')
it('admin can re-embed a failed article')
it('deleting an article cascades to embeddings and votes')

━━━ INGESTION JOB TESTS ━━━
it('IngestKbArticle chunks article body and stores embeddings')
it('IngestKbArticle marks article as done after successful embedding')
it('IngestKbArticle marks article as failed if AiService throws')
it('IngestKbArticle skips draft articles')
it('IngestKbArticle skips deleted articles gracefully')

━━━ SEARCH / RAG TESTS ━━━
it('search endpoint returns 422 for query shorter than 2 chars')
it('search endpoint is rate limited to 20 per minute')
it('search endpoint streams sources then delta then done chunks')
it('search with no matching embeddings returns sources=[] and fallback message')
it('KbSearch log is created after every search')
it('guest search is blocked when allow_guest_search is false')

━━━ VOTE TESTS ━━━
it('user can vote helpful on an article')
it('vote is idempotent — voting twice updates rather than creates')
it('helpful_count and not_helpful_count are recalculated from DB on vote')

━━━ PUBLIC PAGE TESTS ━━━
it('help center home is accessible at the configured public slug')
it('article page increments view count on each visit')
it('article page returns 404 for unpublished articles')
it('kb returns 404 when addon disabled via settings')
it('article page includes schema.org Article JSON-LD in head')

━━━ WIDGET TESTS ━━━
it('widget search endpoint returns 403 when widget is disabled')
it('widget search endpoint adds CORS headers to the response')

For AI-dependent tests:
  AiService::embedText() → mock return: array_fill(0, 1536, 0.01)
  AiService::stream()    → mock return: (fn() => yield 'test answer')()
```

---

## IMPLEMENTATION SEQUENCE NOTES

1. **Run steps 1–4 first**, then verify migrations run clean before any job/service code
2. **Step 5 (IngestKbArticle)** depends on `AiService::embedText()` — confirm that signature is available in your core before running this prompt
3. **Step 6 (KbSearchService)** is the most complex prompt — run it alone and verify the cosine similarity math and streaming generator before moving to controllers
4. **Steps 7–9** (controllers + routes) can be run together
5. **Steps 10–11** (Vue pages) — run separately: admin pages first, then public
6. **Step 12** (widget) is independent — can be built last
7. **Step 13** (tests) — run last, after all feature code is in place

---

## CRITICAL INVARIANTS (repeat for every DeepSeek session)

```
AI ENGINE:    App\Services\AI\AiService — NEVER LLPhant, NEVER raw Http::post('api.openai.com')
STREAMING:    POST + fetch ReadableStream — NEVER EventSource
SSE HEADER:   X-Accel-Buffering: no — on ALL streaming responses
FINALLY:      Always in streaming response()->stream() callback
SESSION:      sessionStorage (not localStorage) for widget_session / kb_session
APP NAME:     settings('app_name') — NEVER hardcode "MakeAI"
USER ID:      $user->ulid — public facing; $user->id — DB only
QUEUES:       embeddings queue for IngestKbArticle; ai queue for ProcessRagQuery
TRANSLATE:    translate() PHP / $t() Vue — ALL user-facing strings
```

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

#### ADDON 19: AI Assistant (Floating Widget)
**Slug:** `ai-assistant`
**Price:** $29–$49 | **License required:** Regular

**What it does:**
Embeds a floating AI assistant widget site-wide — both in the admin panel and the user-facing frontend. Fully configurable persona, model, access rules, and system prompts from Admin → Settings → AI Assistant. Streams responses via POST + `ReadableStream` using the Laravel AI SDK.

**Features:**
- Floating FAB button (bottom-right or bottom-left, configurable) with unread message badge
- 380×560px chat window — avatar, name, designation, greeting message
- Independent enable/disable toggles for frontend and admin panel
- Streaming AI responses (POST + `ReadableStream`, never `EventSource`); `X-Accel-Buffering: no` mandatory
- Context-aware suggestions on empty state (page-specific prompt chips)
- Admin slash commands: `/review-site`, `/marketing-tips`, `/revenue-analysis`, `/top-tools`, `/seo-check`
- Admin mode injects live site metrics (users, subscriptions, revenue, AI usage, system health) as context — cached 2 minutes
- Per-user / per-session daily message limit (Redis counter, 0 = unlimited)
- Access control: show to `all` / `logged_in` / `pro_only` (requires `isProAvailable()`)
- Pro upsell banner for non-pro users when `show_to = pro_only`
- Thumbs up/down feedback per message (SHA-256 message hash, `updateOrCreate`)
- Copy and Regenerate actions on assistant messages
- Lightweight inline markdown renderer (bold, inline code, line breaks — no heavy deps)
- Admin dashboard feedback card: messages today, thumbs-up %, top pages, recent negative comments
- Session ID from `sessionStorage` (resets per tab, prevents stale history)
- Customisable accent color (hex color picker) and avatar URL

**New tables:**

```sql
-- ai_assistant_settings (singleton, id=1)
ai_assistant_settings
  id
  enabled               boolean DEFAULT false          -- frontend on/off
  admin_enabled         boolean DEFAULT true           -- admin panel on/off
  model                 varchar(100) DEFAULT 'gpt-4o-mini'
  max_tokens            smallint DEFAULT 1024
  temperature           decimal(3,2) DEFAULT 0.70
  api_key_source        varchar(20) DEFAULT 'global'   -- global | custom
  custom_api_key        text NULL                      -- encrypted
  assistant_name        varchar(60) DEFAULT 'AI Assistant'
  avatar_url            varchar(500) NULL
  designation           varchar(80) DEFAULT 'Your AI Helper'
  greeting_message      text NULL
  system_prompt_frontend longtext NULL
  system_prompt_admin    longtext NULL
  show_to               enum('all','logged_in','pro_only') DEFAULT 'all'
  daily_message_limit   int UNSIGNED DEFAULT 20        -- 0 = unlimited
  show_on_guest_pages   boolean DEFAULT true
  position              enum('bottom-right','bottom-left') DEFAULT 'bottom-right'
  accent_color          varchar(7) DEFAULT '#1F75FE'   -- uses MakeAI primary by default
  created_at, updated_at

-- ai_assistant_feedback
ai_assistant_feedback
  id
  user_id               bigint FK → users.id NULL ON DELETE SET NULL
  session_id            varchar(64) INDEX
  context_page          varchar(255) NULL
  message_hash          varchar(64)                    -- sha256 of assistant message
  rating                tinyint                        -- 1 = up, -1 = down
  comment               text NULL
  created_at, updated_at
  UNIQUE (session_id, message_hash)
```

**File structure:**

```
addons/ai-assistant/
├── addon.json
├── AddonServiceProvider.php
├── app/
│   ├── Models/
│   │   ├── AiAssistantSetting.php        ← singleton model, ::current() → firstOrCreate(['id' => 1])
│   │   └── AiAssistantFeedback.php
│   ├── Services/
│   │   └── AiAssistantService.php        ← buildFrontendSystemPrompt(), buildAdminSystemPrompt(),
│   │                                          buildSiteContext(), isVisibleForUser(), checkDailyLimit(),
│   │                                          incrementDailyCount()
│   └── Http/
│       └── Controllers/
│           ├── Api/AiAssistantController.php     ← chat(), adminChat(), feedback()
│           └── Admin/AiAssistantSettingsController.php
├── database/
│   └── migrations/
│       ├── create_ai_assistant_settings_table.php
│       └── create_ai_assistant_feedback_table.php
├── resources/
│   └── js/
│       └── Components/
│           └── Assistant/
│               ├── FloatingAssistant.vue       ← injected via AddonServiceProvider view composer
│               ├── AssistantTrigger.vue        ← FAB + badge
│               ├── AssistantWindow.vue         ← chat logic, streaming, slash commands
│               ├── AssistantHeader.vue         ← avatar, name, designation, close button
│               ├── AssistantMessages.vue       ← messages list
│               ├── AssistantMessage.vue        ← bubble + copy + rating + regenerate
│               ├── AssistantInput.vue          ← textarea, slash menu, send
│               └── AssistantSuggestions.vue    ← context-aware chip suggestions
├── routes/
│   └── api.php                                 ← POST /api/assistant/chat (throttle:30,1)
│                                                  POST /api/assistant/feedback (throttle:30,1)
│                                                  POST /api/admin/assistant/chat (auth:admin)
└── README.md
```

**`addon.json` settings block:**

```json
{
  "name": "AI Assistant",
  "slug": "ai-assistant",
  "version": "1.0.0",
  "description": "Floating AI assistant widget for frontend and admin panel with streaming, feedback, and admin slash commands.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 1,
  "requires_pro": false,
  "admin_menu": [
    {
      "parent": "Settings",
      "label": "AI Assistant",
      "route": "addon.ai-assistant.admin.settings",
      "icon": "ti-message-chatbot",
      "permission": "addon.ai-assistant.settings"
    }
  ],
  "settings": [
    { "key": "enabled",              "type": "boolean",  "label": "Enable on Frontend",   "default": false },
    { "key": "admin_enabled",        "type": "boolean",  "label": "Enable in Admin Panel", "default": true  },
    { "key": "model",                "type": "string",   "label": "AI Model",              "default": "gpt-4o-mini" },
    { "key": "max_tokens",           "type": "integer",  "label": "Max Tokens",            "default": 1024 },
    { "key": "temperature",          "type": "string",   "label": "Temperature",           "default": "0.7" },
    { "key": "assistant_name",       "type": "string",   "label": "Assistant Name",        "default": "AI Assistant" },
    { "key": "avatar_url",           "type": "url",      "label": "Avatar URL",            "default": null },
    { "key": "designation",          "type": "string",   "label": "Designation",           "default": "Your AI Helper" },
    { "key": "greeting_message",     "type": "textarea", "label": "Greeting Message",      "default": null },
    { "key": "system_prompt_frontend","type": "textarea","label": "Frontend System Prompt","default": null },
    { "key": "system_prompt_admin",  "type": "textarea", "label": "Admin System Prompt",   "default": null },
    { "key": "show_to",              "type": "select",   "label": "Show To",               "options": ["all","logged_in","pro_only"], "default": "all" },
    { "key": "daily_message_limit",  "type": "integer",  "label": "Daily Limit (0=∞)",    "default": 20 },
    { "key": "position",             "type": "select",   "label": "Widget Position",       "options": ["bottom-right","bottom-left"], "default": "bottom-right" },
    { "key": "accent_color",         "type": "color",    "label": "Accent Color",          "default": "#1F75FE" },
    { "key": "custom_api_key",       "type": "encrypted","label": "Custom API Key (optional)", "default": null }
  ],
  "permissions": [
    { "slug": "addon.ai-assistant.settings", "name": "Manage AI Assistant Settings", "group": "AI Assistant" }
  ],
  "hooks": []
}
```

**Key backend method signatures:**

```php
// AiAssistantService.php
namespace Addons\AiAssistant\Services;

class AiAssistantService
{
    public function __construct(private AiService $ai) {}

    // Builds frontend system prompt; injects app name, current page, user info (plan + credits)
    public function buildFrontendSystemPrompt(AiAssistantSetting $s, ?User $user, string $page): string;

    // Builds admin system prompt; injects live site metrics via buildSiteContext()
    public function buildAdminSystemPrompt(AiAssistantSetting $s): string;

    // Queries DB for users/subscriptions/revenue/AI usage/system stats — Cache::remember 120s
    // Cache key: 'addon_ai_assistant.admin.site_context'
    public function buildSiteContext(): array;

    // Returns false if disabled or show_to condition not met; isProAvailable() checked for pro_only
    public function isVisibleForUser(AiAssistantSetting $s, ?User $user): bool;

    // Redis counter: addon_ai_assistant.limit.user.{id}.{date} or .session.{id}.{date}
    public function checkDailyLimit(AiAssistantSetting $s, ?User $user, string $sessionId): bool;

    public function incrementDailyCount(?User $user, string $sessionId): void;
}

// AiAssistantController.php
namespace Addons\AiAssistant\Http\Controllers\Api;

class AiAssistantController extends Controller
{
    // POST /api/assistant/chat — validates message (max:2000), history (max:20 msgs), session_id
    // Checks isVisibleForUser() → 403; checkDailyLimit() → 429 JSON with error:'daily_limit_reached'
    // Streams via response()->stream() + X-Accel-Buffering:no + finally block enforced
    public function chat(Request $request): StreamedResponse;

    // POST /api/admin/assistant/chat — auth:admin only; validates message (max:4000), history (max:30)
    // No credit deduction (admin only); streams with live site context in system prompt
    public function adminChat(Request $request): StreamedResponse;

    // POST /api/assistant/feedback — AiAssistantFeedback::updateOrCreate(['session_id','message_hash'])
    // rating: 1 or -1; optional comment (max:500)
    public function feedback(Request $request): JsonResponse;
}
```

**Layout injection (via AddonServiceProvider view composer):**

The addon uses a view composer to inject `assistantSettings` into all Inertia shared data and appends `<FloatingAssistant>` to `AppLayout.vue` and `AdminLayout.vue` via Blade stack or Inertia plugin slot. Only safe fields are exposed — `system_prompt_*` and `custom_api_key` are **never** passed to frontend.

```php
// AddonServiceProvider::boot()
Inertia::share('assistantSettings', fn() =>
    is_addon_active('ai-assistant')
        ? AiAssistantSetting::current()->only([
            'enabled','admin_enabled','assistant_name','avatar_url','designation',
            'greeting_message','show_to','daily_message_limit','position','accent_color',
          ])
        : null
);
```

**Streaming rules (non-negotiable for this addon):**

1. All AI calls use **Laravel AI SDK (`laravel/ai`) via `AiService`** — never raw OpenAI SDK.
2. Streaming endpoint uses **POST + `ReadableStream`** — never `EventSource` / SSE `data:` framing.
3. `X-Accel-Buffering: no` header on every streaming response (mandatory for Nginx).
4. `finally` block enforced on all streaming controller patterns.
5. `session_id` stored in `sessionStorage` (not `localStorage`) — resets per tab.
6. `system_prompt_*` and `custom_api_key` are **never** exposed to the Inertia frontend.
7. Admin context cache key: `addon_ai_assistant.admin.site_context`, TTL 120s.
8. `AiAssistantFeedback::updateOrCreate` on feedback — no duplicate ratings per message.
9. `isProAvailable()` checked before gating on `pro_only` show_to setting.
10. `settings('app_name')` used everywhere — never hardcode app name.

**Admin settings UI — `resources/js/Pages/Admin/Settings/AiAssistantSettings.vue`:**

Five-tab settings page under Admin → Settings → AI Assistant:

| Tab | Fields |
|-----|--------|
| General | Enable on Frontend (toggle), Enable in Admin Panel (toggle), Widget Position (select), Accent Color (color picker) |
| AI Model | Model (ModelSelector), Max Tokens (range 128–8192), Temperature (range 0–2, step 0.1) |
| Persona | Assistant Name, Avatar (upload), Designation, Greeting Message (textarea) |
| Access Control | Show To (select: all/logged_in/pro_only), Daily Message Limit (number, 0=unlimited) |
| System Prompts | Frontend System Prompt (textarea, 8 rows), Admin System Prompt (textarea, 8 rows) + info box listing available variables: `{app_name}`, `{assistant_name}` |

**Admin dashboard feedback card (`AiAssistantFeedbackCard.vue`):**

Small card injected into Admin → Dashboard via hook or Inertia shared data when addon is active. Shows: total messages today, thumbs-up %, thumbs-down %, top pages with feedback, recent negative comments (last 5). Query uses `AiAssistantFeedback::selectRaw(...)whereDate('created_at', today())`.

**Implementation checklist:**

- [ ] Migrations create `ai_assistant_settings` and `ai_assistant_feedback` with correct indexes
- [ ] `AiAssistantSetting::current()` seeds row with id=1 on first call via `firstOrCreate`
- [ ] `AiAssistantService::buildSiteContext()` queries only with indexed columns; cached 120s
- [ ] Streaming controller uses `response()->stream()` with `X-Accel-Buffering: no` + `finally` block
- [ ] Daily limit Redis keys expire at end of day (TTL 86400)
- [ ] `isVisibleForUser()` correctly enforces `pro_only` via `isProAvailable()`
- [ ] `FloatingAssistant.vue` reads `$page.props.assistantSettings` — never raw API call on mount
- [ ] Session ID read from `sessionStorage`, UUID generated on first access per tab
- [ ] Feedback `updateOrCreate` prevents duplicate ratings on same message hash
- [ ] `system_prompt_frontend`, `system_prompt_admin`, `custom_api_key` excluded from Inertia share
- [ ] Admin slash command menu only shown when `isAdmin = true`
- [ ] Pro upsell banner only renders when `show_to === 'pro_only'` and user is not pro
- [ ] Accent color applied as inline CSS variable on widget (not as Tailwind class)
- [ ] Admin menu item appears under Settings after activation, disappears after deactivation
- [ ] All routes prefixed `addon.ai-assistant.*`, all DB tables prefixed `ai_assistant_*`
- [ ] Deactivating addon hides widget globally without dropping tables
- [ ] `AiAssistantFeedbackCard.vue` visible in Admin Dashboard only when addon is active

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
$this->app->bind(LLPhantService::class, MyCustomLLPhantService::class);

// ✅ CORRECT — extend, don't replace
$this->app->extend(LLPhantService::class, function($service, $app) {
    return new ExtendedLLPhantService($service);
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
9. Admin clicks "Activate" → **Envato license verification modal (first-time activation only — see 4.1)** → on success: `is_active = true` → ServiceProvider registered → cache cleared

---

### 4.1 First-Time Activation — Envato License Verification

Every addon sold on CodeCanyon has its **own Envato item ID and its own purchase code** — separate from the core MakeAI purchase code. The first time an admin clicks "Activate" on an addon, a license verification modal blocks activation until a valid purchase code for **that specific addon item** is verified. Subsequent activations (after a deactivate/reactivate cycle) skip the modal if a valid license is already stored.

#### Architecture: Author License Server (NOT direct Envato API calls)

> ⚠️ **Why verification must go through the author's license server:**
> The Envato endpoint that looks up a sale by purchase code (`/v3/market/author/sale?code=`) only works with the **author's personal token**. The author token can NEVER be shipped inside the distributed product — anyone could extract it and query the author's full sales history. And a buyer's own token cannot call `author/sale` for the author's items. Therefore all purchase-code verification is proxied through a small API the author hosts. This is the same model used by MagicAI and other major CodeCanyon SaaS scripts.

```
Buyer's MakeAI install                    Author's License Server              Envato API
┌──────────────────────┐                 ┌─────────────────────────┐         ┌──────────────┐
│ AddonLicenseService  │  POST /verify   │ license.yourdomain.com  │  GET    │ /v3/market/  │
│ ::verify()           │ ──────────────► │ • holds Envato token    │ ──────► │ author/sale  │
│                      │                 │ • holds slug→item_id map│         │ ?code=...    │
│ verifies Ed25519     │ ◄────────────── │ • signs every response  │ ◄────── │              │
│ signature, stores    │  signed JSON    │   with private key      │         │              │
└──────────────────────┘                 └─────────────────────────┘         └──────────────┘
```

**Benefits of this architecture:**
1. **Solves the chicken-and-egg problem** — the distributed zip never contains `envato_item_id`. Envato assigns the item ID at first upload (visible in the author dashboard item URL even while the item is pending review). After uploading, the author simply adds one row to the license server's mapping table: `('ai-assistant', 51234567)`. No zip re-upload needed for the ID.
2. **Author token never leaves the author's server.**
3. **Anti-spoofing** — every response is signed with the author's Ed25519 private key; MakeAI core ships only the **public key** and rejects any response with an invalid signature. A nuller redirecting `license.yourdomain.com` via hosts file gets responses that fail signature verification.
4. **Central kill switch** — refunded/abused codes can be revoked from the author's dashboard without an Envato round-trip.

#### `addon.json` — `envato_item_id` is informational only

```json
{
  "envato_item_id": null
}
```

`envato_item_id` MAY be included for documentation once known, but it is **never used for enforcement** — the authoritative slug→item_id mapping lives on the license server. This means the same addon zip works before and after the item ID exists. (Set to `null` or omit before first Envato upload.)

#### Author's pre-submission workflow (per addon)

```
1. Build + zip the addon (envato_item_id: null is fine)
2. Upload to CodeCanyon → item enters review queue
3. Copy the item ID from the author dashboard item URL
   (assigned immediately at upload, before approval)
4. Add mapping row on license server:  slug='ai-assistant', item_id=51234567
5. Done — no zip changes required. Verification works the moment
   the first buyer purchases.
```

#### License Server API (author-hosted — a single Laravel route is enough)

```
POST https://license.yourdomain.com/api/v1/verify
Content-Type: application/json

Request:
{
  "product":        "addon",              // "core" | "addon"
  "slug":           "ai-assistant",
  "purchase_code":  "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "domain":         "buyersite.com",
  "version":        "1.0.0"
}

Response (signed):
{
  "payload": {
    "valid":           true,
    "slug":            "ai-assistant",
    "item_id":         51234567,
    "license_type":    1,                  // 1 = regular, 2 = extended
    "buyer":           "envato_username",
    "purchased_at":    "2026-05-01T10:00:00Z",
    "supported_until": "2026-11-01T10:00:00Z",
    "verified_at":     "2026-06-12T08:00:00Z",
    "error":           null                // or machine code: invalid_format | not_found |
  },                                       //    wrong_item | refunded | revoked
  "signature": "base64-ed25519-signature-of-payload"
}
```

**License server responsibilities:**
- Validates purchase code format before calling Envato (`/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i`)
- Calls `GET https://api.envato.com/v3/market/author/sale?code={code}` with the author token (timeout 15s, 2 retries)
- Checks the returned `item.id` against its own slug→item_id mapping table → `wrong_item` on mismatch
- Checks `refunded` flag → `refunded`
- Checks its own revocation list → `revoked`
- Logs every verification: code (hashed), domain, slug, result, timestamp — for abuse detection (same code on 20+ domains → flag)
- Signs the payload with Ed25519 private key (`sodium_crypto_sign_detached`)
- Rate limits per IP: 10/min

#### Migration: `create_addon_licenses_table` (core table, ships with MakeAI core)

```sql
addon_licenses
  id              bigint PK
  addon_slug      varchar(100) UNIQUE NOT NULL   -- FK-like → addons.slug
  purchase_code   text NOT NULL                  -- encrypted with APP_KEY
  envato_item_id  bigint NOT NULL                -- from license server response
  license_type    tinyint NOT NULL               -- 1 = regular, 2 = extended
  buyer           varchar(100) NULL              -- Envato buyer username
  purchased_at    timestamp NULL
  supported_until timestamp NULL                 -- Envato support expiry
  domain          varchar(255) NOT NULL          -- domain at verification time
  verified_at     timestamp NOT NULL             -- last successful verification
  status          enum('valid','grace','invalid') DEFAULT 'valid'
  created_at, updated_at
  INDEX (addon_slug, status)
```

#### Service: `app/Services/AddonLicenseService.php` (core service)

```php
<?php

namespace App\Services;

use App\Models\AddonLicense;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class AddonLicenseService
{
    // Public key shipped in core — pairs with the private key on the license server.
    // Stored as a class constant, NOT in settings/DB (settings can be edited by a nuller).
    private const LICENSE_SERVER_PUBLIC_KEY = 'base64-ed25519-public-key';
    private const LICENSE_SERVER_URL        = 'https://license.yourdomain.com/api/v1/verify';

    /**
     * Verify a purchase code via the author's license server.
     * Called ONLY during first-time activation (or manual re-entry after invalidation).
     */
    public function verify(string $addonSlug, string $purchaseCode): AddonLicenseResult
    // 1. Validate purchase code format locally → fail fast, no network call
    // 2. POST to LICENSE_SERVER_URL { product:'addon', slug, purchase_code, domain, version }
    //    — timeout 15s, 2 retries with backoff
    // 3. Verify Ed25519 signature: sodium_crypto_sign_verify_detached(
    //        base64_decode($signature), json_encode($payload), base64_decode(PUBLIC_KEY))
    //    → signature invalid = hard fail ('Could not verify license server response')
    // 4. Reject if payload.valid === false (map payload.error to user-facing message)
    // 5. Encrypt purchase_code with Crypt::encryptString(), upsert into addon_licenses
    //    using payload fields (item_id, license_type, buyer, purchased_at, supported_until)
    // 6. Set: verified_at = now(), domain = request()->getHost(), status = 'valid'
    // 7. Return AddonLicenseResult { valid, type, buyer, supportedUntil, error }

    /**
     * Quick cached check — used by is_addon_active() and AddonLicenseMiddleware.
     * Cache key: "addon_license.{slug}" TTL 3600 (cleared on verify/revoke).
     */
    public function isLicensed(string $addonSlug): bool

    /**
     * Scheduled re-verification — daily job re-verifies addons whose
     * verified_at > 7 days ago (configurable via settings('addon_license_recheck_days', 7)).
     * Calls the same license server /verify endpoint with the stored (decrypted) code.
     *
     * On network failure / license server down: keep status, retry next day —
     *   NEVER punish buyers for the author's server being temporarily unreachable.
     * On signed invalid response (refunded / revoked): status = 'grace', grace_started_at = now().
     * After 72h grace: status = 'invalid' → addon auto-deactivated + admin notified (mail + in-app).
     */
    public function reverify(string $addonSlug): void

    /** Remove stored license — only on addon delete. */
    public function revoke(string $addonSlug): void
}
```

#### Activation flow (replaces plain step 9)

```
Admin clicks [Activate] on addon row
        │
        ▼
Has valid row in addon_licenses for this slug?
        │
   ┌────┴─────┐
  YES         NO  (first-time activation)
   │           │
   │           ▼
   │   ┌─────────────────────────────────────────────┐
   │   │  🔑 Activate "{Addon Name}"                  │
   │   │                                              │
   │   │  Enter your Envato purchase code for this    │
   │   │  addon. Find it in Envato → Downloads →      │
   │   │  License certificate & purchase code.        │
   │   │                                              │
   │   │  Purchase Code *                             │
   │   │  ┌────────────────────────────────────────┐  │
   │   │  │ xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx   │  │
   │   │  └────────────────────────────────────────┘  │
   │   │                                              │
   │   │  ⓘ This code is different from your MakeAI  │
   │   │     core purchase code.                      │
   │   │                                              │
   │   │  [Where do I find this?]   [Cancel] [Verify]│
   │   └─────────────────────────────────────────────┘
   │           │
   │           ▼
   │   AddonLicenseService::verify(slug, code)
   │   → license server → Envato → signed response
   │           │
   │      ┌────┴─────┐
   │    VALID      INVALID → inline error in modal, button re-enabled:
   │      │                  • "Invalid purchase code format"        (invalid_format)
   │      │                  • "Purchase code not found"             (not_found)
   │      │                  • "This code belongs to a different item" (wrong_item)
   │      │                  • "This purchase was refunded"          (refunded)
   │      │                  • "This license has been revoked"       (revoked)
   │      │                  • "Could not reach license server — try again" (network)
   │      ▼
   └──→ is_active = true → ServiceProvider registered → caches cleared
        → success toast: "✓ {Addon Name} activated — licensed to {buyer}"
```

**Frontend (Vue):** `AddonLicenseModal.vue` — purchase code input with UUID-format mask, paste support, auto-trim whitespace, [Verify] button shows spinner during API call, all six error states rendered inline (never a toast-only error). Help link opens Envato's "Where Is My Purchase Code?" article in a new tab.

---

# ADDON 21: AI Voiceover & Podcast Studio — Implementation Guide

> **Slug:** `ai-voiceover`
> **Queue:** `ai` (TTS generation), `media` (audio processing/mixing), `low` (RSS + cleanup)
> **AI engine:** ElevenLabs, OpenAI TTS, Murf, PlayHT via `Http` facade; Whisper for STT
>   (all keys from core `settings()` — already in Admin → Integrations)
> **No `laravel/ai`** for TTS/STT — direct provider HTTP calls
> **Extends core:** Reuses `GenerateAudio` job pattern; adds podcast-specific layer on top

---

## WHAT THIS BUILDS

A full audio studio: generate voiceovers from scripts, produce multi-speaker podcast
episodes, mix in background music, auto-transcribe back to SRT/VTT, manage an audio
library with folders, and publish a podcast RSS feed that works in Apple Podcasts /
Spotify RSS import.

---

## STEP-BY-STEP BUILD ORDER

```
Step 1  → addon.json + AddonServiceProvider
Step 2  → Migrations (3 tables)
Step 3  → Models
Step 4  → Provider clients (ElevenLabs, OpenAI TTS, Murf, PlayHT)
Step 5  → VoiceoverService + AudioMixerService (ffmpeg)
Step 6  → Generation jobs
Step 7  → PodcastRssFeedService
Step 8  → Controllers + FormRequests
Step 9  → Routes
Step 10 → Vue Pages
Step 11 → Pest Tests
```

---

## STEP 1 — addon.json + AddonServiceProvider

### DEEPSEEK PROMPT 1

```
Create two files for ADDON 21: AI Voiceover & Podcast Studio for MakeAI (Laravel 13).

━━━ FILE 1: addons/ai-voiceover/addon.json ━━━
{
  "name": "AI Voiceover & Podcast Studio",
  "slug": "ai-voiceover",
  "version": "1.0.0",
  "description": "Generate AI voiceovers, produce multi-speaker podcast episodes with background music, auto-transcribe audio, and publish a podcast RSS feed.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 1,
  "requires_pro": false,
  "admin_menu": [
    { "parent": "Content",  "label": "Voiceover Studio", "route": "addon.vo.admin.overview",  "icon": "ti-microphone",  "permission": "addon.vo.manage"   },
    { "parent": "Settings", "label": "Voiceover Studio", "route": "addon.vo.admin.settings",  "icon": "ti-settings",    "permission": "addon.vo.settings" }
  ],
  "settings": [
    { "key": "enabled",               "type": "boolean", "label": "Enable Voiceover Studio",          "default": true },
    { "key": "default_provider",      "type": "select",  "label": "Default TTS provider",             "options": ["elevenlabs","openai","murf","playht"], "default": "openai" },
    { "key": "podcast_enabled",       "type": "boolean", "label": "Enable Podcast RSS feeds",          "default": true },
    { "key": "podcast_base_url",      "type": "string",  "label": "Podcast public base URL",          "default": "" },
    { "key": "credits_per_1k_chars",  "type": "integer", "label": "Credits per 1,000 characters TTS", "default": 5 },
    { "key": "credits_stt",           "type": "integer", "label": "Credits per STT transcription",    "default": 10 },
    { "key": "max_script_chars",      "type": "integer", "label": "Max script characters per generation", "default": 5000 },
    { "key": "max_file_size_mb",      "type": "integer", "label": "Max audio file size for STT (MB)", "default": 25 },
    { "key": "auto_transcribe",       "type": "boolean", "label": "Auto-transcribe generated audio",  "default": false },
    { "key": "ffmpeg_path",           "type": "string",  "label": "ffmpeg binary path",               "default": "/usr/bin/ffmpeg" },
    { "key": "auto_delete_days",      "type": "integer", "label": "Auto-delete audio after N days (0=never)", "default": 0 }
  ],
  "permissions": [
    { "slug": "addon.vo.use",      "name": "Use Voiceover Studio",          "group": "Voiceover" },
    { "slug": "addon.vo.manage",   "name": "View Voiceover Admin Overview",  "group": "Voiceover" },
    { "slug": "addon.vo.settings", "name": "Manage Voiceover Settings",      "group": "Voiceover" }
  ],
  "hooks": []
}

━━━ FILE 2: addons/ai-voiceover/AddonServiceProvider.php ━━━
Namespace: Addons\AiVoiceover

register(): bind singletons: VoiceoverService, AudioMixerService, PodcastRssFeedService

boot() (only if is_addon_active('ai-voiceover')):
  Load routes: routes/web.php, routes/api.php
  Load migrations
  Inertia::share('voiceover', fn() => [
    'enabled'           => addon_setting('ai-voiceover', 'enabled', true),
    'podcastEnabled'    => addon_setting('ai-voiceover', 'podcast_enabled', true),
    'defaultProvider'   => addon_setting('ai-voiceover', 'default_provider', 'openai'),
    'maxScriptChars'    => addon_setting('ai-voiceover', 'max_script_chars', 5000),
    'creditsPerKChars'  => addon_setting('ai-voiceover', 'credits_per_1k_chars', 5),
  ])
  // NEVER share API keys
  Schedule::job(new CleanupExpiredAudio)->daily()
    ->when(fn() => addon_setting('ai-voiceover', 'auto_delete_days', 0) > 0)
```

---

## STEP 2 — Migrations

### DEEPSEEK PROMPT 2

```
Create 3 migration files in addons/ai-voiceover/database/migrations/.
Tables prefixed vo_. Standard timestamps.

━━━ MIGRATION 1: create_vo_projects_table ━━━
vo_projects
  id
  ulid              char(26) UNIQUE NOT NULL
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  title             varchar(255) NOT NULL
  type              enum('voiceover','podcast') DEFAULT 'voiceover'
  description       text NULL
  cover_art_path    varchar(500) NULL         -- podcast cover (3000×3000px JPG)
  cover_art_url     varchar(500) NULL
  podcast_author    varchar(150) NULL
  podcast_category  varchar(100) NULL         -- iTunes category e.g. 'Technology'
  podcast_language  varchar(10) DEFAULT 'en'
  podcast_explicit  boolean DEFAULT false
  rss_token         varchar(64) NULL UNIQUE   -- for public RSS URL
  rss_enabled       boolean DEFAULT false
  total_duration    int UNSIGNED DEFAULT 0    -- seconds, denormalized
  episode_count     int UNSIGNED DEFAULT 0
  created_at, updated_at
  INDEX (user_id, type)
  INDEX (rss_token)

━━━ MIGRATION 2: create_vo_episodes_table ━━━
vo_episodes
  id
  ulid              char(26) UNIQUE NOT NULL
  vo_project_id     bigint UNSIGNED NOT NULL FK → vo_projects.id ON DELETE CASCADE
  user_id           bigint UNSIGNED NOT NULL FK → users.id ON DELETE CASCADE
  title             varchar(255) NOT NULL
  episode_number    smallint UNSIGNED NULL
  season_number     smallint UNSIGNED NULL
  script            longtext NULL             -- plain text script
  segments          json NULL                 -- [{speaker, text, voice_id, provider}, ...]
  status            enum('draft','queued','processing','completed','failed') DEFAULT 'draft'
  provider          varchar(30) NULL
  file_path         varchar(500) NULL
  file_url          varchar(500) NULL
  file_size_bytes   int UNSIGNED DEFAULT 0
  duration_seconds  int UNSIGNED NULL
  format            varchar(10) DEFAULT 'mp3'
  transcript_srt    text NULL
  transcript_vtt    text NULL
  credits_deducted  decimal(10,4) DEFAULT 0
  error_message     text NULL
  share_token       varchar(64) NULL UNIQUE
  share_enabled     boolean DEFAULT false
  published_at      timestamp NULL
  expires_at        timestamp NULL
  created_at, updated_at
  INDEX (vo_project_id, status)
  INDEX (user_id, status)
  INDEX (share_token)

━━━ MIGRATION 3: create_vo_voices_table ━━━
(Cache of available voices per provider — refreshed on-demand)
vo_voices
  id
  provider          varchar(30) NOT NULL
  provider_voice_id varchar(100) NOT NULL
  name              varchar(150) NOT NULL
  gender            varchar(20) NULL          -- 'male' | 'female' | 'neutral'
  language          varchar(10) DEFAULT 'en'
  accent            varchar(50) NULL
  preview_url       varchar(500) NULL
  labels            json NULL                 -- e.g. {"age":"young","use_case":"narration"}
  is_cloned         boolean DEFAULT false
  is_active         boolean DEFAULT true
  synced_at         timestamp NULL
  created_at, updated_at
  UNIQUE (provider, provider_voice_id)
  INDEX (provider, language, gender)
```

---

## STEP 3 — Models

### DEEPSEEK PROMPT 3

```
Create 3 Eloquent models in addons/ai-voiceover/app/Models/.
Namespace: Addons\AiVoiceover\Models

━━━ VoProject.php ━━━
fillable: all non-id fields
casts: rss_enabled → boolean, podcast_explicit → boolean,
       total_duration → integer, episode_count → integer
appends: ['cover_art_url_resolved', 'rss_url']
Relationships: belongsTo(User), hasMany(VoEpisode)
Accessor: getRssUrlAttribute() → route('addon.vo.public.rss', ['token' => $this->rss_token])
Boot: on creating → generate ULID; generate rss_token (Str::random(64))
scope: scopeForUser($q, int $userId)

━━━ VoEpisode.php ━━━
fillable: all non-id fields
casts: segments → array, duration_seconds → integer, file_size_bytes → integer,
       credits_deducted → float, share_enabled → boolean, published_at → datetime, expires_at → datetime
appends: ['duration_label', 'can_retry', 'is_published']
Relationships: belongsTo(VoProject), belongsTo(User)
Scopes: scopeCompleted, scopePublished (published_at <= now() && status=completed)
Accessor: getDurationLabelAttribute(): '3:24' format from duration_seconds
Accessor: getCanRetryAttribute(): status === 'failed'
Boot: on creating → generate ULID; generate share_token (Str::random(64))

━━━ VoVoice.php ━━━
fillable: all non-id fields
casts: labels → array, is_cloned → boolean, is_active → boolean, synced_at → datetime
Scope: scopeForProvider($q, string $provider)
Scope: scopeActive($q) → where('is_active', true)
```

---

## STEP 4 — Provider Clients

### DEEPSEEK PROMPT 4

```
Create 4 TTS provider clients in addons/ai-voiceover/app/Services/Providers/.
Namespace: Addons\AiVoiceover\Services\Providers
All use Http facade. Throw VoiceoverException on errors. Timeout: 60s.
All API keys from CORE settings() — they're already in Admin → Integrations:
  ElevenLabs: settings('elevenlabs_api_key')
  OpenAI TTS: settings('openai_api_key')
  Murf:       settings('murf_api_key')
  PlayHT:     settings('playht_api_key') + settings('playht_user_id')

━━━ abstract TtsProviderClient.php ━━━
abstract generateSpeech(string $text, string $voiceId, array $options): string  // → storage path
abstract listVoices(): array  // → [{id, name, gender, language, preview_url, labels}, ...]

━━━ ElevenLabsClient.php ━━━
generateSpeech($text, $voiceId, $options):
  POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}/stream
  Headers: xi-api-key: {key}
  Body: { text, model_id: 'eleven_multilingual_v2',
          voice_settings: { stability: 0.5, similarity_boost: 0.75,
            style: $options['style'] ?? 0.0, use_speaker_boost: true } }
  Stream response to temp mp3 → move to storage, return path.

listVoices():
  GET https://api.elevenlabs.io/v1/voices
  Returns voices array (includes shared + own clones).
  Map to standard format above.

━━━ OpenAiTtsClient.php ━━━
generateSpeech($text, $voiceId, $options):
  POST https://api.openai.com/v1/audio/speech
  Headers: Authorization: Bearer {openai_api_key}
  Body: { model: 'tts-1-hd', input: $text, voice: $voiceId,
          response_format: 'mp3', speed: $options['speed'] ?? 1.0 }
  Stream to mp3 → storage.

listVoices():
  Return hardcoded list — OpenAI voices are fixed:
  [alloy, ash, coral, echo, fable, nova, onyx, shimmer, verse]
  (as of 2025 — wrap in a config array, not hardcoded strings in logic)

━━━ MurfClient.php ━━━
generateSpeech($text, $voiceId, $options):
  POST https://api.murf.ai/v1/speech/generate-with-key
  Headers: api-key: {murf_api_key}
  Body: { voiceId: $voiceId, text: $text,
          format: 'MP3', channelType: 'STEREO',
          sampleRate: 48000, audioDuration: 0 }
  Response: { audioFile: "base64 mp3 data" }
  Decode base64 → save to storage.

listVoices():
  GET https://api.murf.ai/v1/speech/voices
  Headers: api-key: {murf_api_key}

━━━ PlayHtClient.php ━━━
generateSpeech($text, $voiceId, $options):
  POST https://api.play.ht/api/v2/tts/stream
  Headers: Authorization: Bearer {playht_api_key}, X-User-ID: {playht_user_id}
  Body: { voice: $voiceId, text: $text, output_format: 'mp3',
          voice_engine: 'PlayHT2.0-turbo', quality: 'high' }
  Stream response to mp3 → storage.

listVoices():
  GET https://api.play.ht/api/v2/voices
  Headers: Authorization: Bearer {playht_api_key}, X-User-ID: {playht_user_id}

RULES:
  - API keys from core settings() (not addon_setting) — they're shared provider keys
  - Http::withOptions(['sink' => $tempPath])->post(...) for streaming binary
  - Throw VoiceoverException with provider name + message on non-2xx
  - Output always in storage/app/voiceover/{user_id}/{ulid}.mp3
```

---

## STEP 5 — VoiceoverService + AudioMixerService

### DEEPSEEK PROMPT 5

```
Create two services in addons/ai-voiceover/app/Services/.
Namespace: Addons\AiVoiceover\Services

━━━ VoiceoverService.php ━━━

getClient(string $provider): TtsProviderClient
  Maps 'elevenlabs' → ElevenLabsClient, 'openai' → OpenAiTtsClient, etc.
  Throw if provider key not configured in core settings.

calculateCredits(string $text): int
  ceil(mb_strlen($text) / 1000) * addon_setting('ai-voiceover', 'credits_per_1k_chars', 5)

generateSingle(VoEpisode $episode): string
  (Single-voice generation — entire script in one API call)
  provider = $episode->provider ?? addon_setting('ai-voiceover', 'default_provider', 'openai')
  client = getClient(provider)
  outputPath = 'voiceover/' . $episode->user_id . '/' . $episode->ulid . '.mp3'
  client->generateSpeech($episode->script, first segment voice_id, options)
  return outputPath

generateMultiSpeaker(VoEpisode $episode): string
  (Multi-speaker: generate each segment separately → mix/concat with ffmpeg)
  $segments = $episode->segments  // [{speaker, text, voice_id, provider}, ...]
  $segmentPaths = []
  foreach $segments as $i => $seg:
    client = getClient($seg['provider'] ?? default_provider)
    segPath = 'voiceover/' . $episode->user_id . '/' . $episode->ulid . '_seg' . $i . '.mp3'
    client->generateSpeech($seg['text'], $seg['voice_id'], [])
    $segmentPaths[] = $segPath
  return app(AudioMixerService::class)->concatenate($segmentPaths, 'voiceover/' . $episode->user_id . '/' . $episode->ulid . '.mp3')

syncVoices(string $provider): void
  client = getClient($provider)
  voices = client->listVoices()
  foreach voices as $v:
    VoVoice::updateOrCreate(
      ['provider' => $provider, 'provider_voice_id' => $v['id']],
      [...$v, 'synced_at' => now()]
    )
  VoVoice::where('provider', $provider)
    ->where('synced_at', '<', now()->subMinutes(5))
    ->update(['is_active' => false])  // mark old voices inactive

getVoicesForProvider(string $provider): Collection
  Return VoVoice::forProvider($provider)->active()->orderBy('name')->get()
  If empty → syncVoices($provider) first (lazy sync)

━━━ AudioMixerService.php ━━━
(ffmpeg-based audio operations — same pattern as SlideshowBuilderService in ADDON 05)

concatenate(array $inputPaths, string $outputPath): string
  Build ffmpeg concat list → run concat → return output path
  All paths are storage-relative; resolve to abs with storage_path('app/')

mixWithMusic(string $voicePath, string $musicPath, float $musicVolume, string $outputPath): string
  ffmpeg -i voice.mp3 -i music.mp3
    -filter_complex "[1:a]volume={vol}[music];[0:a][music]amix=inputs=2:duration=first:dropout_transition=3[out]"
    -map "[out]" -c:a libmp3lame -q:a 2 output.mp3

getDuration(string $storagePath): ?int
  ffprobe -v error -show_entries format=duration → return (int) seconds

generateWaveform(string $storagePath): array
  ffmpeg -i input.mp3 -filter_complex "aformat=channel_layouts=mono,compand,showwavespic=s=800x80:colors=#10b981"
    -frames:v 1 output_waveform.png
  Return ['waveform_path' => $path, 'waveform_url' => Storage::url($path)]
  (waveform PNG used in player UI)

All ffmpeg paths from addon_setting('ai-voiceover', 'ffmpeg_path', '/usr/bin/ffmpeg')
Wrap all exec() calls — throw VoiceoverException on non-zero exit
```

---

## STEP 6 — Generation Jobs

### DEEPSEEK PROMPT 6

```
Create 3 jobs in addons/ai-voiceover/app/Jobs/.
Namespace: Addons\AiVoiceover\Jobs
Queue: 'ai'. Max attempts: 3. Backoff: [60, 300].

━━━ GenerateVoiceover.php ━━━
Constructor: __construct(public readonly int $episodeId)

handle(VoiceoverService $service, AudioMixerService $mixer):
  $episode = VoEpisode::with('project.user')->find($this->episodeId)
  if (!$episode || !in_array($episode->status, ['queued','draft'])) return

  $episode->update(['status' => 'processing'])

  try {
    $isMultiSpeaker = count($episode->segments ?? []) > 1
      && collect($episode->segments)->pluck('voice_id')->unique()->count() > 1

    $outputPath = $isMultiSpeaker
      ? $service->generateMultiSpeaker($episode)
      : $service->generateSingle($episode)

    $duration = $mixer->getDuration($outputPath)
    $waveform = $mixer->generateWaveform($outputPath)

    $episode->update([
      'status'         => 'completed',
      'file_path'      => $outputPath,
      'file_url'       => Storage::url($outputPath),
      'file_size_bytes'=> Storage::size($outputPath),
      'duration_seconds' => $duration,
    ])

    // Update project totals
    VoProject::where('id', $episode->vo_project_id)->increment('episode_count')
    VoProject::where('id', $episode->vo_project_id)
      ->increment('total_duration', $duration ?? 0)

    // Auto-transcribe if enabled
    if (addon_setting('ai-voiceover', 'auto_transcribe', false)) {
      TranscribeAudio::dispatch($episode->id)->onQueue('media')
    }

    // Notify
    SendInAppNotification::dispatch($episode->user, 'voiceover_completed', [
      'title' => $episode->title,
    ])->onQueue('default')

  } catch (VoiceoverException $e) {
    $episode->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)])
    // Refund credits
    if ($episode->credits_deducted > 0) {
      User::where('id', $episode->user_id)->increment('credits', $episode->credits_deducted)
    }
  }

failed(Throwable $e):
  VoEpisode::where('id', $this->episodeId)->update([
    'status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)
  ])
  $ep = VoEpisode::find($this->episodeId)
  if ($ep?->credits_deducted > 0)
    User::where('id', $ep->user_id)->increment('credits', $ep->credits_deducted)

━━━ TranscribeAudio.php ━━━
Queue: 'media'. Constructor: __construct(public readonly int $episodeId)

handle():
  $episode = VoEpisode::find($this->episodeId)
  if (!$episode || !$episode->file_path) return

  $credits = addon_setting('ai-voiceover', 'credits_stt', 10)
  if (User::find($episode->user_id)?->credits < $credits) return  // silent skip — no crash

  deduct_credits($episode->user_id, $credits, 'Audio transcription: ' . $episode->ulid)

  $abs = storage_path('app/' . $episode->file_path)
  $result = Http::withToken(settings('openai_api_key'))
    ->timeout(120)
    ->attach('file', file_get_contents($abs), basename($abs))
    ->post('https://api.openai.com/v1/audio/transcriptions', [
      'model'                    => 'whisper-1',
      'response_format'          => 'verbose_json',
      'timestamp_granularities[]' => 'segment',
    ])

  if ($result->failed()) throw new VoiceoverException('Whisper transcription failed')

  $json = $result->json()
  $segments = $json['segments'] ?? []

  $episode->update([
    'transcript_srt' => $this->toSrt($segments),
    'transcript_vtt' => $this->toVtt($segments),
  ])

private function toSrt(array $segments): string  // standard SRT format
private function toVtt(array $segments): string  // WEBVTT format

━━━ CleanupExpiredAudio.php ━━━
Queue: 'low'. Daily.

handle():
  $days = addon_setting('ai-voiceover', 'auto_delete_days', 0)
  if ($days <= 0) return
  VoEpisode::where('expires_at', '<=', now())
    ->whereNotNull('file_path')
    ->chunk(50, function($episodes) {
      foreach ($episodes as $ep) {
        Storage::delete($ep->file_path)
        $ep->update(['file_path' => null, 'file_url' => null, 'status' => 'expired'])
      }
    })

━━━ SyncVoices.php ━━━ (artisan command + manual trigger)
Queue: 'low'. Handle: foreach ['elevenlabs','openai','murf','playht'] as $p → try { VoiceoverService::syncVoices($p) } catch {}
```

---

## STEP 7 — PodcastRssFeedService

### DEEPSEEK PROMPT 7

```
Create: addons/ai-voiceover/app/Services/PodcastRssFeedService.php
Namespace: Addons\AiVoiceover\Services

generateFeed(VoProject $project): string  (returns XML string)

Build a valid Apple Podcasts / Spotify RSS 2.0 feed:

<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
  xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>{$project->title}</title>
  <link>{$appUrl}</link>
  <description>{$project->description}</description>
  <language>{$project->podcast_language}</language>
  <itunes:author>{$project->podcast_author ?? settings('app_name')}</itunes:author>
  <itunes:explicit>{$project->podcast_explicit ? 'true' : 'false'}</itunes:explicit>
  <itunes:category text="{$project->podcast_category ?? 'Technology'}" />
  <itunes:image href="{$project->cover_art_url}" />
  <atom:link href="{rss_url}" rel="self" type="application/rss+xml" />
  <lastBuildDate>{now()->toRfc822String()}</lastBuildDate>

  foreach $project->episodes()->completed()->published()->orderByDesc('published_at')->get() as $ep:
  <item>
    <title>{$ep->title}</title>
    <itunes:episode>{$ep->episode_number}</itunes:episode>
    <itunes:season>{$ep->season_number}</itunes:season>
    <description>{excerpt from $ep->script, 500 chars}</description>
    <enclosure url="{$ep->file_url}" length="{$ep->file_size_bytes}" type="audio/mpeg" />
    <guid isPermaLink="false">{$ep->ulid}</guid>
    <pubDate>{$ep->published_at->toRfc822String()}</pubDate>
    <itunes:duration>{gmdate('H:i:s', $ep->duration_seconds)}</itunes:duration>
  </item>

</channel></rss>

Cache the feed for 15 minutes: Cache::remember("vo.rss.{$project->rss_token}", 900, fn() => generateFeed($project))
Bust cache when episode status changes: Cache::forget("vo.rss.{$project->rss_token}")
```

---

## STEP 8 — Controllers + FormRequests

### DEEPSEEK PROMPT 8

```
Create controllers in addons/ai-voiceover/app/Http/Controllers/.
Namespace: Addons\AiVoiceover\Http\Controllers
User controllers middleware: ['auth', 'permission:addon.vo.use']

━━━ User/StudioController ━━━

index(): projects paginated 12, with episode_count + last episode
  return Inertia 'AiVoiceover::User/Studio'

storeProject(Request $request): validate title, type (voiceover|podcast), description,
  podcast fields when type=podcast. Create VoProject. Redirect to show.

showProject(VoProject $project): abort_if user mismatch
  $episodes = $project->episodes()->latest()->paginate(20)
  $voices = app(VoiceoverService::class)->getVoicesForProvider(addon_setting('ai-voiceover','default_provider','openai'))
  return Inertia 'AiVoiceover::User/Project' props: project, episodes, voices

storeEpisode(Request $request, VoProject $project):
  Validate: title, script (nullable, max:5000 chars), segments (nullable array), provider,
    voice_id (required_without: segments), music_path (nullable string)
  Check credits: calculateCredits(script or concat all segment texts)
  deduct_credits()
  VoEpisode::create([...request, 'vo_project_id' => $project->id,
    'user_id' => auth()->id(), 'status' => 'queued', 'credits_deducted' => $credits])
  GenerateVoiceover::dispatch($episode->id)->onQueue('ai')
  return response()->json(['episode_id' => $episode->id, 'ulid' => $episode->ulid])

transcribeEpisode(VoEpisode $episode):
  abort_if user mismatch or status !== 'completed'
  TranscribeAudio::dispatch($episode->id)->onQueue('media')
  return back()->with('flash', 'Transcription queued')

toggleShare(VoEpisode $episode): JsonResponse
  abort_if user mismatch
  $episode->update(['share_enabled' => !$episode->share_enabled])
  return response()->json(['share_enabled' => $episode->share_enabled, 'share_token' => $episode->share_token])

publishEpisode(Request $request, VoEpisode $episode):
  abort_if user mismatch or status !== 'completed'
  $episode->update(['published_at' => $request->published_at ?? now()])
  Cache::forget('vo.rss.' . $episode->project->rss_token)
  return back()

download(VoEpisode $episode): StreamedResponse
  abort_if user mismatch or !$episode->file_path
  return Storage::download($episode->file_path, Str::slug($episode->title) . '.mp3')

━━━ Public/PodcastController ━━━
rss(string $token): Response
  $project = VoProject::where('rss_token', $token)->where('rss_enabled', true)->firstOrFail()
  $xml = app(PodcastRssFeedService::class)->generateFeed($project)
  return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8'])

sharePlayer(string $token): InertiaResponse
  $episode = VoEpisode::where('share_token', $token)->where('share_enabled', true)
    ->where('status', 'completed')->firstOrFail()
  return Inertia 'AiVoiceover::Public/Player' props: episode (only: title, file_url, duration_seconds)

━━━ Admin/VoAdminController + VoSettingsController ━━━
Admin overview: total episodes, processing, total storage, credits used today, by provider
Settings: edit/update — all addon_setting keys
  Also: [Sync Voices] button → dispatches SyncVoices job
  Admin can upload background music tracks to shared library (stored in storage/app/voiceover/music/)
```

---

## STEP 9 — Routes

### DEEPSEEK PROMPT 9

```
Create addons/ai-voiceover/routes/web.php

User routes (middleware: web, auth, permission:addon.vo.use, prefix: voiceover-studio):
  GET /                         → StudioController@index           (addon.vo.user.studio)
  POST /projects                → StudioController@storeProject
  GET /projects/{project}       → StudioController@showProject
  POST /projects/{project}/episodes → StudioController@storeEpisode  throttle:20,1
  POST /episodes/{episode}/transcribe → StudioController@transcribeEpisode
  POST /episodes/{episode}/share      → StudioController@toggleShare
  POST /episodes/{episode}/publish    → StudioController@publishEpisode
  GET /episodes/{episode}/download    → StudioController@download

Public routes (no auth, prefix: podcast):
  GET /rss/{token}              → PodcastController@rss            (addon.vo.public.rss)
  GET /player/{token}           → PodcastController@sharePlayer    (addon.vo.public.player)

Admin routes (auth:admin, prefix: admin/voiceover):
  GET /                         → VoAdminController@overview       admin.permission:addon.vo.manage
  GET /settings                 → VoSettingsController@edit        admin.permission:addon.vo.settings
  PUT /settings                 → VoSettingsController@update
  POST /sync-voices             → VoSettingsController@syncVoices  throttle:2,1
```

---

## STEP 10 — Vue Pages

### DEEPSEEK PROMPT 10

```
Create Vue pages in addons/ai-voiceover/resources/js/Pages/.
<script setup lang="ts">, TypeScript, Tailwind v4, Tabler Icons. $t() everywhere.

━━━ User/Studio.vue — Project Library ━━━
Grid of project cards: cover art (placeholder gradient if none), title, type badge
(🎙 Voiceover | 🎧 Podcast), episode count, total duration, last activity.
[+ New Project] → slide-over with type picker (single voiceover vs podcast series).
Empty state: "Create your first voiceover project"

━━━ User/Project.vue — Episode List + Creator ━━━

TOP: Project header — cover art (upload for podcasts), title, description, RSS badge
     (if type=podcast: [📡 RSS URL] copy button, [Enable RSS] toggle, podcast metadata edit)

CREATE EPISODE panel (collapsible):
  Title input
  Speaker mode: [Single voice ●] [Multi-speaker ○]

  SINGLE VOICE:
    Script textarea (large, char counter vs max)
    Provider select (elevenlabs/openai/murf/playht)
    Voice picker: searchable grid with gender/language filter
      Each voice card: name, gender badge, language, [▶ Preview] button
      Preview plays a sample via provider's preview URL (ElevenLabs has it; others show "No preview")
    Speed slider (0.5–2.0, only for OpenAI)
    Background music: dropdown from shared library OR upload own (mp3, max 10MB)
    Music volume slider (if music selected)

  MULTI-SPEAKER:
    Segment builder:
      [+ Add Speaker] → each speaker: name label, voice provider, voice picker
      Script split: textarea per segment OR auto-split mode
        Auto-split: paste full script → AI detects speaker labels (Speaker A: ... Speaker B: ...)
        → auto-creates segments
    Preview: shows alternating speaker bubbles

  Credit cost display (updates live as script length changes)
  [Generate Audio] button → POST → redirect/update with polling

EPISODE LIST:
  Each row: episode title | speaker count | duration | status badge (processing spinner) |
    [▶ Play] | [⬇ Download] | [📋 Transcript] | [🔗 Share] | [📢 Publish to RSS] | [...] menu

  Inline audio player (expands on play):
    Waveform visualization (img from waveform_path)
    HTML5 <audio> with custom controls
    Show transcript (SRT as timed text overlay if VTT available)
    [📋 Generate Transcript] if none yet

━━━ Public/Player.vue — Shared Episode Player ━━━
Minimal page. App logo. Episode title. HTML5 audio player with waveform.
No user info, no script, no sensitive data. Footer: "Made with {settings('app_name')}"

━━━ Admin/Settings.vue ━━━
Sections: General (enabled, podcast_enabled, auto_transcribe), AI Settings
(default_provider, credits_per_1k_chars, credits_stt, max_script_chars),
Storage (max_file_size_mb, auto_delete_days, ffmpeg_path + detection badge),
Voice Library ([Sync Voices] button per provider + last synced timestamp)
```

---

## STEP 11 — Pest Tests

### DEEPSEEK PROMPT 11

```
Create addons/ai-voiceover/tests/Feature/VoiceoverTest.php
PestPHP, RefreshDatabase, Http::fake() for all provider calls.

it('creates a project and episode, dispatches GenerateVoiceover job')
it('deducts credits based on script character count')
it('refunds credits on GenerateVoiceover job failure')
it('multi-speaker episode generates segments and concatenates audio')
it('single-speaker episode calls TTS provider once')
it('TranscribeAudio creates SRT and VTT transcripts')
it('podcast RSS feed returns valid XML with correct itunes namespace')
it('RSS feed returns 404 for disabled or non-existent token')
it('RSS feed is cached and busted on episode publish')
it('share player is accessible without auth for enabled episodes')
it('share player returns 404 for disabled share tokens')
it('download requires auth and ownership')
it('publish sets published_at and busts RSS cache')
it('SyncVoices upserts voices and marks stale ones inactive')
it('CleanupExpiredAudio deletes files and marks episodes expired when days > 0')
it('CleanupExpiredAudio skips when auto_delete_days is 0')
```

---

## CRITICAL INVARIANTS

```
API KEYS:      TTS provider keys from core settings() — NOT addon_setting()
               ElevenLabs: settings('elevenlabs_api_key')
               OpenAI TTS: settings('openai_api_key')
               Murf:       settings('murf_api_key')
               PlayHT:     settings('playht_api_key'), settings('playht_user_id')
ADDON CONFIG:  addon_setting('ai-voiceover', 'key') for all addon settings
QUEUE:         ai → generation | media → transcription | low → cleanup, sync
CREDITS:       Deducted upfront; refunded in failed()
FFMPEG:        addon_setting('ai-voiceover', 'ffmpeg_path') — check exists before exec
RSS:           Cache key: "vo.rss.{rss_token}" TTL 900s — bust on episode publish/unpublish
APP NAME:      settings('app_name') — never hardcode
ADMIN ROUTES:  auth:admin + admin.permission:addon.vo.settings / addon.vo.manage
USER ROUTES:   auth + permission:addon.vo.use
```

---

# ADDON 31: AI YouTube & Video Content Repurposer — Implementation Guide

> **Slug:** `ai-repurposer`
> **Queue:** `ai` (all generation), `media` (file transcription)
> **AI engine:** `laravel/ai` via `AiService` for all content generation;
>   Whisper (via `settings('openai_api_key')`) for video/audio file transcription;
>   `youtube-transcript/youtube-transcript` PHP package for YouTube transcript fetch
> **Depends on core:** `YoutubeService` already in core — reuse it

---

## WHAT THIS BUILDS

Paste a YouTube URL or upload a video/audio file → get all of these in one workflow:
blog post, Twitter/X thread, LinkedIn article, email newsletter, TikTok/Reels scripts,
podcast show notes, key quote graphics captions, and chapter markers. Bulk mode processes
multiple URLs in a queue job. Everything saved to a repurpose history library.

---

## STEP-BY-STEP BUILD ORDER

```
Step 1  → addon.json + AddonServiceProvider
Step 2  → Migrations (2 tables)
Step 3  → Models
Step 4  → TranscriptService (YouTube + file upload)
Step 5  → RepurposeService (generation for each format)
Step 6  → Jobs (single + bulk)
Step 7  → Controllers + FormRequests
Step 8  → Routes
Step 9  → Vue Pages
Step 10 → Pest Tests
```

---

## STEP 1 — addon.json + AddonServiceProvider

### DEEPSEEK PROMPT 1

```
Create two files for ADDON 31: AI YouTube & Video Content Repurposer for MakeAI (Laravel 13).

━━━ FILE 1: addons/ai-repurposer/addon.json ━━━
{
  "name": "AI Content Repurposer",
  "slug": "ai-repurposer",
  "version": "1.0.0",
  "description": "Turn any YouTube video or uploaded audio/video into blog posts, threads, newsletters, scripts, show notes, and more — all at once.",
  "author": "MakeAI",
  "min_makeai_version": "1.0.0",
  "envato_item_id": null,
  "requires_license": 1,
  "requires_pro": false,
  "admin_menu": [
    { "parent": "Content",  "label": "Content Repurposer", "route": "addon.repurpose.user.index",    "icon": "ti-refresh",  "permission": "addon.repurpose.use"      },
    { "parent": "Settings", "label": "Content Repurposer", "route": "addon.repurpose.admin.settings","icon": "ti-settings", "permission": "addon.repurpose.settings"  }
  ],
  "settings": [
    { "key": "enabled",                   "type": "boolean",  "label": "Enable Content Repurposer",       "default": true },
    { "key": "ai_model",                  "type": "string",   "label": "AI model",                        "default": "gpt-4o-mini" },
    { "key": "transcription_provider",    "type": "select",   "label": "Transcription provider",          "options": ["whisper","assemblyai"], "default": "whisper" },
    { "key": "credits_per_repurpose",     "type": "integer",  "label": "Credits per single repurpose",    "default": 15 },
    { "key": "credits_per_bulk_item",     "type": "integer",  "label": "Credits per bulk item",           "default": 12 },
    { "key": "max_file_size_mb",          "type": "integer",  "label": "Max upload file size (MB)",       "default": 100 },
    { "key": "max_bulk_items",            "type": "integer",  "label": "Max URLs in bulk batch",          "default": 10 },
    { "key": "default_formats",           "type": "string",   "label": "Default formats (comma-separated)","default": "blog_post,twitter_thread,linkedin_article,email_newsletter" },
    { "key": "twitter_thread_length",     "type": "integer",  "label": "Max tweets in a thread",          "default": 10 },
    { "key": "blog_post_min_words",       "type": "integer",  "label": "Blog post min word count",        "default": 800 },
    { "key": "auto_save_blog",            "type": "boolean",  "label": "Auto-save blog post to core blog","default": false }
  ],
  "permissions": [
    { "slug": "addon.repurpose.use",      "name": "Use Content Repurposer",          "group": "Repurposer" },
    { "slug": "addon.repurpose.settings", "name": "Manage Content Repurposer Settings", "group": "Repurposer" }
  ],
  "hooks": []
}

━━━ FILE 2: addons/ai-repurposer/AddonServiceProvider.php ━━━
Namespace: Addons\AiRepurposer

register(): bind singletons: TranscriptService, RepurposeService

boot():
  Load routes, migrations
  Inertia::share('repurposer', fn() => [
    'enabled'        => addon_setting('ai-repurposer', 'enabled', true),
    'availableFormats' => RepurposeService::FORMATS,  // static list
    'defaultFormats'  => explode(',', addon_setting('ai-repurposer', 'default_formats', 'blog_post,twitter_thread,linkedin_article,email_newsletter')),
    'maxBulkItems'   => addon_setting('ai-repurposer', 'max_bulk_items', 10),
    'creditCost'     => addon_setting('ai-repurposer', 'credits_per_repurpose', 15),
    'maxFileMb'      => addon_setting('ai-repurposer', 'max_file_size_mb', 100),
  ])
```

---

## STEP 2 — Migrations

### DEEPSEEK PROMPT 2

```
Create 2 migration files in addons/ai-repurposer/database/migrations/.
Tables prefixed rp_. Standard timestamps.

━━━ MIGRATION 1: create_rp_jobs_table ━━━
rp_jobs
  id, ulid UNIQUE
  user_id           bigint FK → users.id ON DELETE CASCADE
  source_type       enum('youtube_url','file_upload','text_paste') NOT NULL
  source_url        varchar(500) NULL      -- YouTube URL
  source_path       varchar(500) NULL      -- uploaded file path
  source_title      varchar(500) NULL      -- extracted/provided title
  transcript        longtext NULL          -- raw transcript text
  transcript_source enum('youtube','whisper','assemblyai','pasted') NULL
  word_count        int UNSIGNED NULL      -- transcript word count
  duration_seconds  int UNSIGNED NULL      -- video/audio duration
  chapters          json NULL              -- [{title, start_seconds}, ...] from YouTube
  status            enum('queued','transcribing','generating','completed','failed','partial') DEFAULT 'queued'
  formats_requested json NOT NULL          -- ['blog_post','twitter_thread',...]
  formats_completed json DEFAULT '[]'     -- formats successfully generated
  credits_deducted  decimal(10,4) DEFAULT 0
  error_message     text NULL
  is_bulk           boolean DEFAULT false
  bulk_batch_id     varchar(64) NULL       -- groups bulk jobs together
  created_at, updated_at
  INDEX (user_id, status)
  INDEX (bulk_batch_id)

━━━ MIGRATION 2: create_rp_outputs_table ━━━
rp_outputs
  id, ulid UNIQUE
  rp_job_id         bigint FK → rp_jobs.id ON DELETE CASCADE
  user_id           bigint FK → users.id ON DELETE CASCADE
  format            varchar(50) NOT NULL
  content           longtext NOT NULL
  word_count        int UNSIGNED NULL
  metadata          json NULL      -- {tweet_count, reading_time, etc.}
  is_saved          boolean DEFAULT false   -- saved to blog/elsewhere
  saved_post_id     bigint NULL            -- if saved to core blog
  created_at, updated_at
  UNIQUE (rp_job_id, format)
  INDEX (user_id, format)
```

---

## STEP 3 — Models

### DEEPSEEK PROMPT 3

```
Create 2 models in addons/ai-repurposer/app/Models/.
Namespace: Addons\AiRepurposer\Models

━━━ RpJob.php ━━━
fillable: all, casts: chapters → array, formats_requested → array, formats_completed → array,
  is_bulk → boolean
appends: ['progress_percent', 'source_label', 'is_youtube']
Relationships: belongsTo(User), hasMany(RpOutput)
Scopes: scopeForUser, scopeCompleted, scopeBulkBatch($q, string $batchId)
Accessors:
  getProgressPercentAttribute(): int
    count(formats_completed) / count(formats_requested) * 100
  getSourceLabelAttribute(): truncated title or URL domain
  getIsYoutubeAttribute(): source_type === 'youtube_url'
Boot: ULID on creating

━━━ RpOutput.php ━━━
fillable: all, casts: metadata → array, is_saved → boolean
appends: ['format_label', 'format_icon']
Relationships: belongsTo(RpJob), belongsTo(User)
Static: FORMAT_LABELS = ['blog_post' => 'Blog Post', 'twitter_thread' => 'X Thread', ...]
Accessors: getFormatLabelAttribute(), getFormatIconAttribute() → Tabler icon name
Boot: ULID on creating
```

---

## STEP 4 — TranscriptService

### DEEPSEEK PROMPT 4

```
Create: addons/ai-repurposer/app/Services/TranscriptService.php
Namespace: Addons\AiRepurposer\Services

━━━ getYoutubeTranscript(string $url): array ━━━
Returns: ['transcript' => string, 'title' => string, 'duration' => int, 'chapters' => array]

Step 1: Extract video ID from URL
  Support: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID
  $videoId = extractVideoId($url) → throw RepurposeException('Invalid YouTube URL') if null

Step 2: Fetch transcript via youtube-transcript package
  Requires: composer require youtube-transcript/youtube-transcript (if not already in core)
  use YoutubeTranscript\YoutubeTranscript;
  $transcript = YoutubeTranscript::getTranscript($videoId);
  // Returns array of [{text, start, duration}]
  $fullText = collect($transcript)->pluck('text')->implode(' ')
  $fullText = preg_replace('/\s+/', ' ', strip_tags($fullText))

Step 3: Fetch video metadata (title, duration, chapters) via YouTube Data API or oEmbed
  Try: GET https://www.youtube.com/oembed?url={$url}&format=json (no key needed)
  → extracts title
  For duration and chapters: GET https://www.googleapis.com/youtube/v3/videos?id={$videoId}&part=contentDetails,snippet&key={settings('youtube_api_key')}
  If no API key: skip duration and chapters (graceful degradation)

Step 4: Extract chapters from description if available
  Parse description for timestamps: /^(\d{1,2}:\d{2}(?::\d{2})?)\s+(.+)$/m

Return structured array above.

━━━ transcribeFile(string $storagePath, string $provider = null): array ━━━
Returns: ['transcript' => string, 'duration' => int]

$provider ??= addon_setting('ai-repurposer', 'transcription_provider', 'whisper')

Whisper:
  $abs = storage_path('app/' . $storagePath)
  Check file size <= max_file_size_mb
  POST https://api.openai.com/v1/audio/transcriptions
    file: binary, model: 'whisper-1', response_format: 'json'
  Auth: settings('openai_api_key')
  Returns: { text: '...' }

AssemblyAI: (same pattern as in ADDON 05 SubtitleService)
  Upload file → submit → poll → return text

Return: ['transcript' => $text, 'duration' => (from file metadata or 0)]

━━━ Private helpers ━━━
extractVideoId(string $url): ?string
  Handles all YouTube URL formats
  Returns null for non-YouTube URLs

estimateReadingTime(string $text): int
  ceil(str_word_count($text) / 200) // minutes at 200 wpm
```

---

## STEP 5 — RepurposeService

### DEEPSEEK PROMPT 5

```
Create: addons/ai-repurposer/app/Services/RepurposeService.php
Namespace: Addons\AiRepurposer\Services

const FORMATS = [
  'blog_post'           => ['label' => 'Blog Post',           'icon' => 'ti-file-text',        'min_words' => 800],
  'twitter_thread'      => ['label' => 'X / Twitter Thread',  'icon' => 'ti-brand-twitter',    'min_words' => 0],
  'linkedin_article'    => ['label' => 'LinkedIn Article',    'icon' => 'ti-brand-linkedin',   'min_words' => 400],
  'email_newsletter'    => ['label' => 'Email Newsletter',    'icon' => 'ti-mail',             'min_words' => 200],
  'tiktok_script'       => ['label' => 'TikTok / Reels Script','icon' => 'ti-brand-tiktok',   'min_words' => 0],
  'podcast_show_notes'  => ['label' => 'Podcast Show Notes',  'icon' => 'ti-microphone',       'min_words' => 150],
  'key_quotes'          => ['label' => 'Key Quotes',          'icon' => 'ti-quote',            'min_words' => 0],
  'chapter_markers'     => ['label' => 'Chapter Markers',     'icon' => 'ti-list-numbers',     'min_words' => 0],
];

public function __construct(private AiService $ai) {}

generateFormat(string $transcript, string $title, string $format, array $options = []): string
  Returns the generated content for that format.

  Base prompt context injected into all formats:
  "Title: {$title}\n\nTranscript:\n{truncate($transcript, 8000)}"
  (Always truncate transcript — token limit protection)

  Format-specific prompts:
  'blog_post':
    "Write a comprehensive, engaging blog post based on this video transcript.
    Include an intro, clear H2/H3 headings, key insights, and a conclusion.
    Minimum " . addon_setting('ai-repurposer', 'blog_post_min_words', 800) . " words.
    Format as Markdown."

  'twitter_thread':
    "Write a Twitter/X thread from this video. Make it engaging and punchy.
    Format: each tweet on a new line starting with 1/ 2/ 3/ etc.
    Max " . addon_setting('ai-repurposer', 'twitter_thread_length', 10) . " tweets.
    Each tweet max 280 characters. End with a CTA."

  'linkedin_article':
    "Write a professional LinkedIn article from this video.
    Use a hook opening, clear sections, professional tone.
    Include 3-5 relevant hashtags at the end.
    Format as Markdown."

  'email_newsletter':
    "Write an email newsletter based on this video.
    Subject line options: provide 3 subject line variants at the top.
    Body: conversational tone, key takeaways, call-to-action.
    Format: Subject options first, then email body."

  'tiktok_script':
    "Write a 60-second TikTok/Reels video script based on this content.
    Format: HOOK (first 3 seconds), BODY (key points as quick bullets), CTA.
    Make it energetic and trend-aware."

  'podcast_show_notes':
    "Write podcast show notes for this episode.
    Include: episode summary (2 paragraphs), key topics covered (bullet list),
    key takeaways (3-5 bullets), timestamps (estimate from content flow).
    Format as Markdown."

  'key_quotes':
    "Extract 8-10 powerful, quotable statements from this transcript.
    Format: one quote per line, in quotation marks, attributed to the speaker."

  'chapter_markers':
    "Create YouTube chapter markers for this video.
    Format: timestamp HH:MM:SS - Chapter Title (one per line).
    Start with 00:00:00 - Introduction."
    // If actual chapters exist in $options['chapters'], use those as base and fill in gaps.

  model = addon_setting('ai-repurposer', 'ai_model', 'gpt-4o-mini')
  maxTokens per format: blog_post=2000, twitter_thread=800, linkedin=1200,
    email=800, tiktok=500, show_notes=800, quotes=500, chapters=300

generateAll(RpJob $job, array $formats): void
  foreach $formats as $format:
    try:
      $content = $this->generateFormat($job->transcript, $job->source_title ?? 'Untitled', $format)
      $wordCount = str_word_count(strip_tags($content))
      RpOutput::updateOrCreate(
        ['rp_job_id' => $job->id, 'format' => $format],
        ['content' => $content, 'word_count' => $wordCount,
         'user_id' => $job->user_id,
         'metadata' => $this->buildMetadata($format, $content)]
      )
      // Mark format as completed
      $completed = array_merge($job->formats_completed, [$format])
      $job->update(['formats_completed' => $completed])
    catch (Throwable $e):
      // Continue with other formats — partial completion is OK
      Log::warning("Repurpose format {$format} failed for job {$job->id}: " . $e->getMessage())

private function buildMetadata(string $format, string $content): array
  return match($format) {
    'twitter_thread' => ['tweet_count' => substr_count($content, "\n1/") + 1],  // approximate
    'blog_post'      => ['reading_time' => ceil(str_word_count($content) / 200)],
    'tiktok_script'  => ['estimated_seconds' => 60],
    default          => []
  }
```

---

## STEP 6 — Jobs

### DEEPSEEK PROMPT 6

```
Create 3 jobs in addons/ai-repurposer/app/Jobs/.
Namespace: Addons\AiRepurposer\Jobs

━━━ ProcessRepurposeJob.php ━━━
Queue: 'ai'. Max attempts: 2. Backoff: [60, 300].
Constructor: __construct(public readonly int $jobId)

handle(TranscriptService $transcript, RepurposeService $repurpose):
  $job = RpJob::find($this->jobId)
  if (!$job || $job->status === 'completed') return

  // Step 1: Transcribe
  $job->update(['status' => 'transcribing'])
  try {
    if ($job->source_type === 'youtube_url') {
      $data = $transcript->getYoutubeTranscript($job->source_url)
    } elseif ($job->source_type === 'file_upload') {
      $data = $transcript->transcribeFile($job->source_path)
    } else {
      $data = ['transcript' => $job->transcript, 'title' => $job->source_title, 'duration' => 0, 'chapters' => []]
    }
    $job->update([
      'transcript'        => $data['transcript'],
      'source_title'      => $data['title'] ?? $job->source_title,
      'duration_seconds'  => $data['duration'] ?? null,
      'chapters'          => $data['chapters'] ?? [],
      'word_count'        => str_word_count($data['transcript']),
      'transcript_source' => $job->source_type === 'youtube_url' ? 'youtube' : 'whisper',
    ])
  } catch (RepurposeException $e) {
    $job->update(['status' => 'failed', 'error_message' => $e->getMessage()])
    if ($job->credits_deducted > 0)
      User::where('id', $job->user_id)->increment('credits', $job->credits_deducted)
    return
  }

  // Step 2: Generate all formats
  $job->update(['status' => 'generating'])
  $repurpose->generateAll($job, $job->formats_requested)

  // Step 3: Finalise
  $completedCount = count($job->fresh()->formats_completed)
  $requestedCount = count($job->formats_requested)
  $finalStatus = $completedCount === $requestedCount ? 'completed' : 'partial'
  $job->update(['status' => $finalStatus])

  // Auto-save blog post if enabled
  if (addon_setting('ai-repurposer', 'auto_save_blog', false)) {
    $blogOutput = RpOutput::where('rp_job_id', $job->id)->where('format', 'blog_post')->first()
    if ($blogOutput) {
      $this->saveToBlog($blogOutput, $job)
    }
  }

  SendInAppNotification::dispatch(
    User::find($job->user_id), 'repurpose_completed',
    ['title' => $job->source_title, 'formats' => $completedCount]
  )->onQueue('default')

failed(Throwable $e):
  $job = RpJob::find($this->jobId)
  if ($job) {
    $job->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 500)])
    if ($job->credits_deducted > 0)
      User::where('id', $job->user_id)->increment('credits', $job->credits_deducted)
  }

━━━ ProcessBulkRepurposeJob.php ━━━
Queue: 'ai'. Loops through all RpJob with bulk_batch_id = $this->batchId,
dispatches ProcessRepurposeJob for each, staggered with 5s delays.
Constructor: __construct(public readonly string $batchId)

━━━ CleanupRepurposerFiles.php ━━━
Queue: 'low'. Daily. Deletes uploaded audio/video files older than 7 days.
Storage::delete() for rp_jobs.source_path where source_type = 'file_upload'
and created_at < now()->subDays(7).
```

---

## STEP 7 — Controllers + FormRequests

### DEEPSEEK PROMPT 7

```
Create controllers in addons/ai-repurposer/app/Http/Controllers/.
Namespace: Addons\AiRepurposer\Http\Controllers
User middleware: ['auth', 'permission:addon.repurpose.use']

━━━ RepurposerController ━━━

index():
  $jobs = RpJob::forUser(auth()->id())->with('outputs')->latest()->paginate(15)
  return Inertia 'AiRepurposer::User/Index' props: jobs

show(RpJob $job):
  abort_if($job->user_id !== auth()->id(), 403)
  $job->load('outputs')
  return Inertia 'AiRepurposer::User/Result' props: job

store(RepurposeRequest $request):
  Validate:
    source_type: required in:youtube_url,file_upload,text_paste
    source_url:  required_if:source_type,youtube_url | url
    file:        required_if:source_type,file_upload | file mimes:mp3,mp4,m4a,wav,webm,ogg max:{max_file_mb}M
    text:        required_if:source_type,text_paste | min:100 max:20000
    title:       nullable max:255
    formats:     required array min:1 | each in:blog_post,twitter_thread,linkedin_article,...
    is_bulk:     boolean

  $credits = addon_setting('ai-repurposer', 'credits_per_repurpose', 15)
  if (auth()->user()->credits < $credits) return response()->json(['error' => translate('Insufficient credits')], 402)
  deduct_credits(auth()->id(), $credits, 'Content repurpose')

  $sourcePath = null
  if ($request->hasFile('file')) {
    $sourcePath = $request->file('file')->store('repurposer/' . auth()->id(), 'local')
  }

  $job = RpJob::create([
    'user_id'           => auth()->id(),
    'source_type'       => $request->source_type,
    'source_url'        => $request->source_url,
    'source_path'       => $sourcePath,
    'source_title'      => $request->title,
    'transcript'        => $request->source_type === 'text_paste' ? $request->text : null,
    'transcript_source' => $request->source_type === 'text_paste' ? 'pasted' : null,
    'status'            => 'queued',
    'formats_requested' => $request->formats,
    'credits_deducted'  => $credits,
  ])

  ProcessRepurposeJob::dispatch($job->id)->onQueue('ai')
  return response()->json(['job_id' => $job->id, 'ulid' => $job->ulid, 'status' => 'queued'])

storeBulk(BulkRepurposeRequest $request):
  Validate: urls array min:2 max:{max_bulk_items}, formats array, title_prefix nullable
  $totalCredits = count($request->urls) * addon_setting('ai-repurposer', 'credits_per_bulk_item', 12)
  // Check + deduct
  $batchId = Str::uuid()->toString()
  foreach ($request->urls as $i => $url):
    RpJob::create([..., 'source_url' => $url, 'bulk_batch_id' => $batchId, 'is_bulk' => true, ...])
  ProcessBulkRepurposeJob::dispatch($batchId)->onQueue('ai')
  return response()->json(['batch_id' => $batchId])

status(RpJob $job): JsonResponse
  abort_if($job->user_id !== auth()->id(), 403)
  return response()->json([
    'status'             => $job->status,
    'formats_completed'  => $job->formats_completed,
    'formats_requested'  => $job->formats_requested,
    'progress_percent'   => $job->progress_percent,
  ])

saveToBlog(Request $request, RpOutput $output): JsonResponse
  abort_if($output->user_id !== auth()->id(), 403)
  abort_if($output->format !== 'blog_post', 422)
  // Insert into core blog_posts table as draft
  $postId = DB::table('blog_posts')->insertGetId([
    'user_id' => auth()->id(),
    'title'   => $output->job->source_title ?? 'Repurposed Post',
    'content' => $output->content,
    'status'  => 'draft',
    'slug'    => Str::slug($output->job->source_title ?? 'repurposed-post') . '-' . Str::random(6),
    'created_at' => now(), 'updated_at' => now(),
  ])
  $output->update(['is_saved' => true, 'saved_post_id' => $postId])
  return response()->json(['post_id' => $postId, 'saved' => true])
```

---

## STEP 8 — Routes

### DEEPSEEK PROMPT 8

```
Create addons/ai-repurposer/routes/web.php

User (middleware: web, auth, permission:addon.repurpose.use, prefix: content-repurposer):
  GET /                          → RepurposerController@index     (addon.repurpose.user.index)
  POST /                         → RepurposerController@store     throttle:10,1
  POST /bulk                     → RepurposerController@storeBulk throttle:3,1
  GET /{job}                     → RepurposerController@show      (addon.repurpose.user.show)
  GET /{job}/status              → RepurposerController@status    (JSON)
  POST /outputs/{output}/save-blog → RepurposerController@saveToBlog

Admin (auth:admin, prefix: admin/content-repurposer):
  GET /settings                  → RepurposeSettingsController@edit  admin.permission:addon.repurpose.settings
  PUT /settings                  → RepurposeSettingsController@update
```

---

## STEP 9 — Vue Pages

### DEEPSEEK PROMPT 9

```
Create Vue pages in addons/ai-repurposer/resources/js/Pages/.
<script setup lang="ts">, TypeScript, Tailwind v4, Tabler Icons.

━━━ User/Index.vue — Repurposer Home ━━━

TOP SECTION: Repurpose Form

  Source type tabs: [🎬 YouTube URL] [📁 Upload File] [📝 Paste Text]

  YOUTUBE URL tab:
    URL input (paste + validate YouTube URL format)
    Show YouTube thumbnail preview after URL validation (fetch from img.youtube.com/vi/{id}/0.jpg)
    Custom title override (optional)

  UPLOAD FILE tab:
    Drag-drop zone: mp3, mp4, m4a, wav, webm, ogg accepted
    Max file size badge
    File name + size display after selection

  PASTE TEXT tab:
    "Already have a transcript or article to repurpose"
    Textarea (min 100 chars, counter)
    Title field (required for this mode)

  Format selector (shown for all modes):
    8 format cards in a 4×2 grid, each: icon + label + brief description
    Multi-select — click to toggle. Pre-selected = default_formats from settings.
    Select All / Deselect All quick toggles.

  Credit cost display: "This will cost {N} credits"
  [🔄 Repurpose →] button

  BULK MODE toggle (collapses single URL, shows URL list):
    Textarea: one YouTube URL per line (max {max_bulk_items})
    Shows total credit cost = count × credits_per_bulk_item
    [Process All] button

BOTTOM SECTION: Job History
  Table: Source title (truncated) | Type icon | Status badge | Formats | Date | [View Results]
  Processing jobs show a progress bar (poll GET /{job}/status every 5s)

━━━ User/Result.vue — Repurpose Results ━━━

TOP: Source info card — thumbnail (YouTube) or file icon, title, duration, word count

FORMAT TABS (one per completed format):
  Each tab: format icon + label + status badge

  Tab content per format:
    Content display area (read-only Tiptap for blog_post/linkedin; plain text for others)
    Format-specific UI:
      blog_post: [Copy Markdown] [Copy HTML] [📝 Save to Blog as Draft]
      twitter_thread: tweet cards numbered 1/ 2/ ... with char counter per tweet
      linkedin_article: LinkedIn-style card mockup + [Copy]
      email_newsletter: Subject lines section + body section + [Copy All]
      tiktok_script: hook/body/cta sections in colored blocks
      podcast_show_notes: formatted show notes + [Copy Markdown]
      key_quotes: quote card list, each with [Copy Quote] [Copy for Instagram Caption]
      chapter_markers: list of timestamps + chapter names + [Copy All for YouTube Description]

    [📋 Copy All] button always visible
    Word count badge

  Right sidebar:
    Source transcript (collapsible, full text)
    Regenerate format: [↻ Regenerate This Format] → POST to re-run single format
    Chapter markers (if extracted from YouTube)

  If job status is 'partial': warning banner "Some formats failed to generate — you can retry them"
  [↻ Retry Failed Formats] button

━━━ Admin/Settings.vue ━━━
  General: enabled, auto_save_blog, default_formats (multi-select)
  AI: ai_model, transcription_provider
  Credits: credits_per_repurpose, credits_per_bulk_item
  Limits: max_file_size_mb, max_bulk_items, twitter_thread_length, blog_post_min_words
```

---

## STEP 10 — Pest Tests

### DEEPSEEK PROMPT 10

```
Create addons/ai-repurposer/tests/Feature/RepurposerTest.php
PestPHP, RefreshDatabase, Http::fake() for YouTube oEmbed/transcript, mock AiService.

it('creates a repurpose job from YouTube URL and dispatches ProcessRepurposeJob')
it('creates a repurpose job from file upload and stores file in storage')
it('creates a repurpose job from pasted text without transcription')
it('deducts credits on job creation')
it('refunds credits when ProcessRepurposeJob fails at transcription step')
it('refunds credits when ProcessRepurposeJob fails at generation step')
it('ProcessRepurposeJob sets status to completed when all formats succeed')
it('ProcessRepurposeJob sets status to partial when some formats fail')
it('all requested formats create RpOutput rows')
it('chapter_markers format uses YouTube chapters when available')
it('bulk job creates multiple RpJob rows with same bulk_batch_id')
it('bulk deducts credits per item not flat rate')
it('save to blog inserts into core blog_posts table as draft')
it('save to blog marks output as saved with saved_post_id')
it('status endpoint returns correct progress_percent')
it('user cannot view another user\'s repurpose job')
it('CleanupRepurposerFiles deletes uploaded files older than 7 days')
it('auto_save_blog saves blog post when enabled')
it('auto_save_blog skips when disabled')
```

---

## CRITICAL INVARIANTS

```
ADDON CONFIG:  addon_setting('ai-repurposer', 'key') for all addon settings
AI ENGINE:     AiService (laravel/ai) for ALL content generation
TRANSCRIPTION: Whisper via settings('openai_api_key') — core key, not addon key
               youtube-transcript package for YouTube (no API key needed)
QUEUE:         ai → all generation jobs | media → (file transcription if heavy)
CREDITS:       Deducted upfront on job creation; refunded in failed() and catch blocks
PARTIAL:       Status 'partial' is valid — don't treat it as failure, don't refund
STREAMING:     No streaming — all formats generated sequentially in one job
FORMATS:       const FORMATS in RepurposeService — single source of truth
ADMIN ROUTES:  auth:admin + admin.permission:addon.repurpose.settings
USER ROUTES:   auth + permission:addon.repurpose.use
FILE CLEANUP:  Uploaded audio/video files auto-deleted after 7 days (CleanupRepurposerFiles)
APP NAME:      settings('app_name') — never hardcode
```

#### Routes (core)

```php
// Admin → Addons
Route::middleware(['auth:admin', 'permission:addons.manage'])->group(function () {
    Route::post('/admin/addons/{slug}/verify-license', [AddonController::class, 'verifyLicense'])
        ->middleware('throttle:5,1');   // max 5 attempts/min — prevents purchase code brute force
    Route::post('/admin/addons/{slug}/activate',       [AddonController::class, 'activate']);
});
```

`activate()` MUST re-check `AddonLicenseService::isLicensed($slug)` server-side — never trust that the modal flow was completed client-side.

#### Anti-nulling rules (mirror core P03)

1. `purchase_code` stored **encrypted** (`Crypt::encryptString`) — never plaintext, never exposed in any API response (masked as `xxxxxxxx-…-xxxx1234` in admin UI).
2. **Ed25519 signature verification on every license server response** — public key is a hardcoded class constant in core (never in settings/DB, which a nuller could edit). Hosts-file redirection to a fake server fails signature check.
3. Re-verified via the license server every 7 days via scheduled job (`addon:reverify-licenses`, daily at 03:00, queue: `low`).
4. Explicit signed invalidation → 72h grace period with persistent warning banner on the addon row → then auto-deactivate + email admin. Network failures NEVER trigger grace — only signed `valid:false` responses do.
5. **No offline bypass** — license server call mandatory on first activation. No Envato token is required from the buyer (the author's server holds it).
6. Domain recorded at verification and sent on every re-verify; the license server flags one code used across many domains (abuse detection on the author side). Local domain change triggers a warning banner (not auto-deactivation) prompting re-verification.
7. Item mismatch is a hard fail enforced **server-side** — a core MakeAI purchase code can never activate an addon, and one addon's code can never activate another addon. The mapping lives on the license server, not in editable shipped files.
8. Demo mode (`DemoMode` middleware): verification modal renders but [Verify] is blocked with tooltip "Disabled in demo".

#### Pest test cases (core: `tests/Feature/AddonLicenseTest.php`)

```php
it('blocks first-time activation without a verified license')
it('rejects a purchase code with invalid uuid format without calling the license server')
it('rejects a response with an invalid or missing ed25519 signature')
it('rejects a signed wrong_item response')
it('rejects a signed refunded response')
it('rejects a signed revoked response')
it('stores the purchase code encrypted and never returns it in any response')
it('activates the addon after successful signed verification')
it('skips the license modal on reactivation when a valid license exists')
it('rate limits verification attempts to 5 per minute')
it('marks license as grace only on signed invalidation and deactivates after 72h')
it('does not punish network failures or license server downtime during reverification')
it('blocks verification in demo mode')
```

---

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

### License Verification (First-Time Activation)
- [ ] Addon slug → item_id mapping added on the author's license server after CodeCanyon upload
- [ ] First-time activation blocked until purchase code verified via author license server
- [ ] Purchase code format validated locally before any network call
- [ ] Ed25519 signature verified on every license server response; invalid signature = hard fail
- [ ] Public key is a hardcoded class constant in core — never in settings/DB
- [ ] Item mismatch rejected server-side (core code or other addon's code cannot activate this addon)
- [ ] Refunded and revoked purchase codes rejected
- [ ] Purchase code stored encrypted; masked in admin UI; never in API responses
- [ ] Reactivation skips modal when valid stored license exists
- [ ] Verification endpoint rate limited (5/min)
- [ ] Scheduled 7-day re-verification works; network failures / license server downtime never invalidate
- [ ] 72h grace period (signed invalidation only) → auto-deactivate + admin notification
- [ ] Verification blocked in demo mode with tooltip

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
