# MakeAI — Complete Development Master Prompt

> **Version 4.1** — Reorganized & Deduplicated
> Stack: PHP 8.3+ · Laravel 12+ · Laravel AI SDK Engine · Inertia SSR · Vue 3 · Tailwind v4 · MySQL · Redis · Horizon · **Laravel Reverb**

---

## 📋 TABLE OF CONTENTS

**🔷 LAYER 0 — PROJECT IDENTITY**
  `P01` PROJECT OVERVIEW & TECH STACK
  `P02` BRANDING & WHITE-LABEL SYSTEM

**🔷 LAYER 1 — FOUNDATION**
  `P03` ENVATO LICENSE SYSTEM
  `P04` FOUNDATION LAYER
  `P05` RATE LIMITING STRATEGY (DETAILED)
  `P06` DATABASE MIGRATIONS ORDER
  `P07` ADDITIONAL MIGRATIONS (Tool Page Features)

**🔷 LAYER 2 — AUTH & USERS**
  `P08` AUTH SYSTEM: Password + OTP 2FA
  `P09` USER MODEL
  `P10` ADMIN MODEL & RBAC

**🔷 LAYER 3 — AI CORE**
  `P11` AI ENGINE (Laravel AI SDK (laravel/ai) core)
  `P12` AI INTEGRATIONS & CREDENTIALS
  `P13` AI TOOL ACCESS CONTROL
  `P14` AI TOOLS & TEMPLATES (255 Templates)
  `P14B` SITE TEMPLATES (Full-Page Experiences)
  `P14B.10` CHATBOT SITE TEMPLATE (claude.ai / ChatGPT-style)
  `P15` AI TOOLS DEVELOPMENT GUIDELINES

**🔷 LAYER 4 — CONTENT & CMS**
  `P16` BLOG SYSTEM
  `P17` CUSTOM PAGES & CMS
  `P18` CATEGORIES & ORGANIZATION
  `P19` TESTIMONIALS & FAQS
  `P20` RICH TEXT EDITOR (Full Tiptap)

**🔷 LAYER 5 — COMMUNICATION**
  `P21` MAIL SYSTEM
  `P22` NEWSLETTER SYSTEM
  `P23` IN-APP NOTIFICATIONS (Reverb)
  `P24` SUPPORT TICKET SYSTEM
  `P25` ANNOUNCEMENT SYSTEM

**🔷 LAYER 6 — MONETIZATION**
  `P26` SUBSCRIPTION SYSTEM (Pro)
  `P27` AFFILIATE & REFERRAL SYSTEM
  `P28` ADS SYSTEM

**🔷 LAYER 7 — FRONTEND & APPEARANCE**
  `P29` FRONTEND ARCHITECTURE
  `P30` LOCALIZATION OF VUE COMPONENTS
  `P31` APPEARANCE & DESIGN SYSTEM
  `P32` HOMEPAGE BUILDER
  `P33` MENU, HEADER, FOOTER & SIDEBAR BUILDERS
  `P34` SOCIAL FEATURES

**🔷 LAYER 8 — ADMIN PANEL**
  `P35` ADMIN DASHBOARD & SYSTEM TOOLS
  `P36` ADMIN MENU STRUCTURE (Collapsible)

**🔷 LAYER 9 — COMMUNITY**
  `P37` COMMUNITY FEATURES (Livewire)

**🔷 LAYER 10 — API & INFRASTRUCTURE**
  `P38` MOBILE APP API ROUTES
  `P39` QUEUE & JOB ARCHITECTURE
  `P66` BROADCASTING & INFRASTRUCTURE FALLBACKS (Redis-Optional)

**🔷 LAYER 11 — DEPLOYMENT**
  `P40` SEEDER DATA (Complete)
  `P41` FILE STRUCTURE
  `P42` DEMO MODE

**🔷 LAYER 12 — CHECKLISTS**
  `P43` MASTER DEVELOPER CHECKLIST
  `P44` ENVATO SUBMISSION CHECKLIST

**🔷 LAYER 13 — QUALITY & PERFORMANCE**
  `P45` ERROR HANDLING & CUSTOM ERROR PAGES
  `P46` GDPR & DATA PRIVACY
  `P47` USER ONBOARDING FLOW
  `P48` KEYBOARD SHORTCUTS & COMMAND PALETTE
  `P49` AUDIT LOG (ADMIN ACTIONS)
  `P50` TESTING STRATEGY (PestPHP)
  `P51` DATABASE OPTIMIZATION

**🔷 LAYER 14 — CORE FEATURE ENHANCEMENTS**
  `P53` AI PLAYGROUND (Model Sandbox)
  `P54` PROMPT VERSIONING & HISTORY
  `P55` QUICK TOOL CHAINING
  `P56` USER USAGE DASHBOARD
  `P57` AI TOOL FAVORITES & COLLECTIONS
  `P58` AI OUTPUT RATING SYSTEM
  `P59` VARIANT GENERATION
  `P60` AI TOOL EMBED WIDGET
  `P61` SMART CREDIT TOP-UP ALERTS
  `P62` COMMAND PALETTE ENHANCEMENTS
  `P63` EXPORT CENTER (Excel & PDF)

**🔷 FINAL STATS**
  `P52` FINAL COMPLETE STATS


---


---


---

## 🔷 LAYER 0 — PROJECT IDENTITY

## PART 01 — PROJECT OVERVIEW & TECH STACK ✅

**Product Name:** MakeAI
**Tagline:** "One platform. Every AI tool."
**Description:** Production-ready AI SaaS platform script for sale on Envato CodeCanyon. Installable by non-technical buyers, extensible via theme/addon system, monetizable via subscription plans. Every feature controllable from admin panel.

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.3+ |
| Framework | Laravel 13+ |
| AI Framework | Laravel AI SDK (laravel/ai)  text, embeddings, images, audio, RAG, Agents |
| Frontend | Vue 3 Composition API + TypeScript |
| SSR | Inertia.js with SSR (Node.js server) |
| Styling | Tailwind CSS v4 |
| Database | MySQL 8+ |
| Cache / Queue | Redis + Laravel Horizon |
| WebSocket | Laravel Reverb (first-party, no Pusher needed) |
| Realtime UI | Laravel Echo |
| Interactive UI | Livewire v3 (newsletter, comments, favorites, search) |
| Rich Text | Tiptap v2 (full-featured) |
| Charts | Chart.js |
| State | Pinia |
| Icons | Tabler Icons |
| Payments | Stripe (laravel cashier), PayPal, SSLCommerz, Razorpay, Paddle (laravel cashier), CoinGate |

### Key Architectural Principles
1. **Zero hardcoded strings** — app name, logo, tagline all from `settings` table
2. **License-gated features** — `isProAvailable()` gates subscription UI at every layer
3. **Separate admin auth** — Admin model/guard completely isolated from user auth
4. **Password + optional OTP 2FA** — email/password login, optional 6-digit OTP via authenticator app after password if user enables 2FA
5. **SDK-native AI** — real RAG + agents, not raw API calls
6. **Queue everything** — no AI/email/media job blocks HTTP response
7. **Laravel Reverb** — first-party WebSocket, no paid external service needed
8. **ULID public IDs** — never expose auto-increment integers in URLs/API
9. **For Stripe & paddle payment gateway** - use laravel cashier package and for others use custom

## PART 02 — BRANDING & WHITE-LABEL SYSTEM  ✅

### 26.1 Site Identity (Never Hardcoded)


Every brand element stored in `settings` table under group `branding`. Zero hardcoded text anywhere in codebase — all references use `settings()` helper or Vue shared props.

**Branding settings:**
```
site_name              varchar     -- "MakeAI" (default, fully replaceable)
site_tagline           varchar     -- "One platform. Every AI tool."
site_description       text        -- used in meta tags and footer
site_logo_light        file        -- logo for light mode (SVG/PNG, recommended 200×50px)
site_logo_dark         file        -- logo for dark mode
site_favicon_ico       file        -- auto-generated from icon or manual upload
site_favicon_png       file        -- 192×192 and 512×512 for PWA manifest
site_og_image          file        -- default Open Graph image (1200×630px)
site_copyright_text    varchar     -- "{site_name} © {year}. All rights reserved."
site_support_email     varchar
site_support_url       varchar
site_terms_url         varchar     -- defaults to /terms-of-service
site_privacy_url       varchar     -- defaults to /privacy-policy
```

**Admin → Settings → general:**
- Upload fields for each logo/favicon with live preview
- Auto-generates all favicon sizes from uploaded icon (using GD/Imagick): 16×16, 32×32, 180×180 (apple-touch), 192×192, 512×512
- App name field → updates `<title>` tag, email headers, all UI references
- Tagline field → updates homepage hero, meta description
- Copyright text → supports `{site_name}` and `{year}` variables
- PWA manifest auto-generated at `/manifest.json` from branding settings

**In Vue/Inertia:**
```typescript
// Available globally via Inertia shared props
const { appName, appLogo, appTagline } = usePage().props.branding
```

**In Blade (emails, maintenance page):**
```php
{{ settings('site_name') }}
{{ asset(settings('site_logo_light')) }}
```

---

## 🔷 LAYER 1 — FOUNDATION

## PART 03 — ENVATO LICENSE SYSTEM ✅

### 1.1 License Types
Envato issues two license types:
- **Regular License** — single end product, end users not charged → enables all core features
- **Extended License** — end users can be charged → enables subscription/billing system (handle using isProAvaiable())

### 1.2 License Verification Architecture

**`app/Services/LicenseService.php`**

```php
class LicenseService
{
    public function verify(string $purchaseCode): LicenseResult
    // Calls Envato Market API: https://api.envato.com/v3/market/author/sale
    // Stores: license_key, license_type (1=regular, 2=extended), buyer, purchase_date, item_id
    // Encrypts and stores in settings table + .env
    // Returns LicenseResult { valid, type, buyer, expires_at }

    public function getLicenseType(): int  // 1 or 2

    public function isExtended(): bool     // type === 2

    public function isValid(): bool        // checks stored license + optional re-verify interval

    public function reactivate(string $code): bool
}
```

**Helper functions (`app/Helpers/license.php`):**

```php
function get_license_type(): int            // returns 1 or 2
function is_extended_license(): bool        // get_license_type() === 2
function is_regular_license(): bool         // get_license_type() === 1

function isProAvailable(): bool
// Logic: is_extended_license() AND settings('subscriptions_enabled') === true
// Used everywhere to gate subscription features

function license_verified(): bool           // quick check from cache
function get_license_buyer(): string        // buyer username from Envato
```

**Anti-nulling protection:**
- License hash stored in `settings` table (encrypted with APP_KEY)
- Re-verified against Envato API every 7 days (configurable)
- If verification fails: grace period 72h, then show a banner in frontend to buy license / verify and stop frontend all functions/features
- No offline bypass possible — API call is mandatory on first install
- License tied to domain — domain mismatch triggers warning in admin
- `LicenseMiddleware` applied on every admin and API route

### 1.3 Installation Wizard

***Make a beautiful installation wizard with step indicators, main color scheme is royal blue***

Route: `/install` — only accessible when `INSTALLED=false` in `.env`

**Steps:**
1. **System requirements check** — PHP version, extensions (curl, zip, gd, mbstring, redis, and other necessary...), writable dirs
2. **Database configuration** — host, port, db name, user, password → test connection
3. **Site setup** — site name, URL, timezone, mail driver
4. **License activation** — purchase code input → Envato API verify → store result
5. **Admin account creation** — name, email, password, confirm password
6. **One click/manual demo install** - first try one click, if not success then manual upload demo.sql file. for one click demo thumnail with preview & install button shows via seed.
6. **Final setup** — run migrations (fix conflicts with demo.sql file if needed), generate APP_KEY, set `INSTALLED=true`
7. **Done** — redirect to admin dashboard

After installation, `/install` must return 404.

---

## PART 04 — FOUNDATION LAYER

### 2.1 Settings Model ✅

**Table: `settings`**

```sql
id          bigint PK
key         varchar(255) UNIQUE NOT NULL
value       longtext NULL
type        enum('string','boolean','integer','json','encrypted') DEFAULT 'string'
group       varchar(100) NULL   -- e.g. 'general', 'ai', 'mail', 'payment', 'license'
created_at  timestamp
updated_at  timestamp
```

**`app/Models/Setting.php`** — uses cache (Redis), auto-invalidates on update.

**Helper:**
```php
function settings(string $key, mixed $default = null): mixed
// settings('site_name')           → 'My AI Platform'
// settings('openai_key')          → decrypted value
// settings('max_tokens', 2000)    → fallback default

function settings_set(string $key, mixed $value, string $type = 'string'): void
// Updates DB + flushes cache key
```

**Admin panel** must have a dedicated Settings UI organized by groups:
- General (site name, logo, favicon, meta, timezone, maintenance mode)
- AI (API keys per provider, default models, token limits, cost tracking)
- Mail (SMTP/Mailgun/SES settings + test send)
- Payment (gateways, currencies, tax)
- Social Auth (Google, GitHub, Facebook OAuth)
- Security (2FA enforcement, login throttle, captcha)
- License (current license info, re-verify button)
- Subscriptions (only visible if `is_extended_license()`)
- Advanced (cache, queue, debug, cron status)

### 2.2 Theme & Addon System ✅

**Directory structure:**
```
resources/
  themes/
    default/
      settings.json
      views/          (Blade/Inertia page overrides)
      assets/         (CSS, JS, images)
      ThemeServiceProvider.php
    sleek/
      settings.json
      ...

addons/
  social-media/
    settings.json
    routes/
    app/
    resources/
    AddonServiceProvider.php
  ai-workflow-builder/
    settings.json
    ...
```

**`settings.json` for themes:**
```json
{
  "name": "Sleek Theme",
  "slug": "sleek",
  "version": "1.0.0",
  "author": "YourName",
  "description": "Modern dark dashboard theme",
  "requires_license": 1,
  "settings": [
    { "key": "primary_color", "type": "color", "default": "#1F75FE", "label": "Primary color" },
    { "key": "sidebar_collapsed", "type": "boolean", "default": false, "label": "Collapse sidebar by default" }
  ]
}
```

**`settings.json` for addons:**
```json
{
  "name": "Social Media Suite",
  "slug": "social-media",
  "version": "1.0.0",
  "requires_license": 2,
  "settings": [
    { "key": "linkedin_enabled", "type": "boolean", "default": true, "label": "Enable LinkedIn posting" },
    { "key": "max_scheduled_posts", "type": "integer", "default": 50, "label": "Max scheduled posts per user" }
  ]
}
```

**`app/Services/ThemeService.php`** — loads active theme, merges settings, provides `theme_setting(key)` helper.

**`app/Services/AddonService.php`** — scans `addons/` directory, registers enabled addons, auto-loads their ServiceProviders.

Admin panel: Themes page (activate/deactivate, configure per-theme settings), Addons page (same), with license gating where `requires_license: 2` themes/ adons must verify license during installation, envato purchase code verification must pass to successfully install or in first time activation.

### 2.3 Helper Functions ✅

**`app/Helpers/helpers.php`** — auto-loaded via `composer.json`:

```php
// Translation
function translate(string $text, array $replace = []): string
// Uses Laravel's trans() under the hood with dynamic key generation
// Falls back to the $text itself if no translation found
// Example: translate('Welcome back, :name', ['name' => $user->name])

// Settings (see above)
function settings(string $key, mixed $default = null): mixed
function settings_set(string $key, mixed $value, string $type = 'string'): void

// License
function get_license_type(): int
function is_extended_license(): bool
function isProAvailable(): bool
function license_verified(): bool

// Currency
function format_currency(float $amount, string $currency = null): string
// Uses active currency from settings, formats with symbol and decimal places
// format_currency(29.99) → '$29.99' or '€29.99' depending on settings

function convert_currency(float $amount, string $from, string $to): float
// Uses stored exchange rates (updated via cron)

// AI / Credits
function deduct_credits(int $userId, float $amount, string $reason): bool
function add_credits(int $userId, float $amount, string $reason, ?int $fromUserId = null): bool
function get_user_credits(int $userId): float
function estimate_token_cost(int $tokens, string $model): float

// Misc
function admin_setting(string $key, mixed $default = null): mixed  // alias for settings()
function active_theme(): string         // returns slug of active theme
function is_addon_active(string $slug): bool
function app_version(): string
function is_maintenance(): bool
```

### 2.4 Toast / Toastr Notifications

Use **`toastr.js`** (loaded globally in the Inertia layout).

**Vue composable `composables/useToastr.ts`:**
```typescript
export function useToastr() {
  const success = (message: string, title?: string) => toastr.success(message, title)
  const error = (message: string, title?: string) => toastr.error(message, title)
  const warning = (message: string, title?: string) => toastr.warning(message, title)
  const info = (message: string, title?: string) => toastr.info(message, title)
  return { success, error, warning, info }
}
```

**Laravel flash → Inertia shared data:**
```php
// In HandleInertiaRequests middleware:
'flash' => [
    'success' => session('success'),
    'error'   => session('error'),
    'warning' => session('warning'),
    'info'    => session('info'),
],
```

**Root layout (`app.vue`)** watches `$page.props.flash` and fires toastr on change.

### 2.5 Multi-Language (Translation System) ✅

**Table: `languages`**
```sql
id, code (e.g. 'bn', 'ar'), name, flag, is_rtl boolean, is_default boolean, is_active boolean
```

**Table: `translations`**
```sql
id, language_id FK, key (text hash or slug), value (translated text), created_at
```

The `translate()` helper:
1. Checks `translations` table (cached per language)
2. Falls back to English original string
3. Admin panel has Translation Manager: list all keys, edit translations, import/export JSON, auto-translate via AI with one click
4. RTL support: if active language `is_rtl = true`, inject `dir="rtl"` on `<html>` via Inertia shared props


---

## PART 05 — RATE LIMITING STRATEGY (DETAILED) ✅

### 5.1 Architecture: Sliding Window Rate Limiting

MakeAI uses **sliding window** rate limiting (not fixed window) via Redis. Sliding window prevents the "boundary burst" problem where a user can double their limit by sending requests at the end of one window and start of the next.

**Implementation:** Laravel's built-in `RateLimiter` facade uses a fixed window. For sliding window, we use a custom Redis implementation:

```php
// app/Services/RateLimiterService.php
class RateLimiterService
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $now = microtime(true);
        $window = $now - $decaySeconds;

        Redis::pipeline(function ($pipe) use ($key, $now, $window) {
            $pipe->zremrangebyscore($key, '-inf', $window);       // remove old entries
            $pipe->zadd($key, $now, $now . rand());               // add current
            $pipe->zcard($key);                                   // count in window
            $pipe->expire($key, $decaySeconds + 1);              // cleanup
        });

        $count = Redis::zcard($key);
        return $count <= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $now = microtime(true);
        $count = Redis::zcard($key);
        return max(0, $maxAttempts - $count);
    }

    public function retryAfter(string $key, int $decaySeconds): int
    {
        $oldest = Redis::zrange($key, 0, 0, ['WITHSCORES' => true]);
        if (empty($oldest)) return 0;
        $oldestTime = array_values($oldest)[0];
        return (int) ceil($oldestTime + $decaySeconds - microtime(true));
    }
}
```

---

### 5.2 Rate Limit Tiers Per User Plan

All limits stored in `settings` table (group: `rate_limits`), editable from admin:

```
┌─────────────────────────────┬────────────┬───────────┬────────────┐
│ Endpoint Category           │ Guest/IP   │ Free User │ Pro User   │
├─────────────────────────────┼────────────┼───────────┼────────────┤
│ Auth (login, register, OTP) │ 10/min     │ 10/min    │ 10/min     │
│ OTP send                    │ 3/15min    │ 3/15min   │ 3/15min    │
│ Text generation             │ 5/hr (IP)  │ 30/min    │ 120/min    │
│ Image generation            │ —          │ 10/hr     │ 60/hr      │
│ Video generation            │ —          │ 5/hr      │ 20/hr      │
│ TTS generation              │ —          │ 20/hr     │ 100/hr     │
│ Chat messages               │ —          │ 60/min    │ 300/min    │
│ Document API (read)         │ —          │ 120/min   │ 600/min    │
│ Document API (write)        │ —          │ 30/min    │ 120/min    │
│ File upload                 │ —          │ 10/min    │ 30/min     │
│ Public pages (GET)          │ 300/min    │ 300/min   │ 300/min    │
│ Contact form                │ 3/hr (IP)  │ 3/hr      │ 10/hr      │
│ Newsletter subscribe        │ 3/hr (IP)  │ 3/hr      │ 10/hr      │
│ Support ticket create       │ —          │ 5/hr      │ 20/hr      │
│ Review submit               │ —          │ 5/day     │ 20/day     │
└─────────────────────────────┴────────────┴───────────┴────────────┘
```

---

### 5.3 Rate Limit Keys

Rate limit keys are constructed to be as specific as needed:

```php
// app/Http/Middleware/ThrottleAiRequests.php

private function buildKey(Request $request, string $category): string
{
    $user = $request->user();

    if ($user) {
        // Per-user + per-category
        return "rl:{$category}:user:{$user->id}";
    }

    // Per-IP + per-category (guests)
    $ip = $request->ip();
    return "rl:{$category}:ip:{$ip}";
}

// Examples:
// rl:text_gen:user:1234
// rl:text_gen:ip:192.168.1.1
// rl:auth:ip:192.168.1.1
// rl:image_gen:user:1234
```

---

### 5.4 Middleware Implementation

```php
// app/Http/Middleware/ThrottleAiRequests.php

class ThrottleAiRequests
{
    public function handle(Request $request, Closure $next, string $category): Response
    {
        $user = $request->user();
        $limits = $this->getLimits($category, $user);
        $key = $this->buildKey($request, $category);

        $allowed = app(RateLimiterService::class)->attempt(
            $key,
            $limits['max'],
            $limits['decay']
        );

        if (!$allowed) {
            $retryAfter = app(RateLimiterService::class)->retryAfter($key, $limits['decay']);

            return response()->json([
                'success' => false,
                'code'    => 'RATE_LIMITED',
                'message' => translate('Too many requests. Please try again in :seconds seconds.', [
                    'seconds' => $retryAfter
                ]),
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After'           => $retryAfter,
                'X-RateLimit-Limit'     => $limits['max'],
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset'     => now()->addSeconds($retryAfter)->timestamp,
            ]);
        }

        $remaining = app(RateLimiterService::class)->remaining($key, $limits['max']);

        return $next($request)->withHeaders([
            'X-RateLimit-Limit'     => $limits['max'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset'     => now()->addSeconds($limits['decay'])->timestamp,
        ]);
    }

    private function getLimits(string $category, ?User $user): array
    {
        $isPro = $user && isProAvailable() && $user->subscription_status === 'active';
        $tier  = $user ? ($isPro ? 'pro' : 'free') : 'guest';

        return [
            'max'   => (int) settings("rl_{$category}_{$tier}_max"),
            'decay' => (int) settings("rl_{$category}_{$tier}_decay"),
        ];
    }
}
```

**Route registration:**
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle.ai:text_gen'])
    ->post('/generate/text', [GenerateController::class, 'text']);

Route::middleware(['auth:sanctum', 'throttle.ai:image_gen'])
    ->post('/generate/image', [GenerateController::class, 'image']);
```

---

### 5.5 IP-Based Protections (Additional Layers)

Beyond per-endpoint rate limits, these global protections run on every request:

**1. Login attempt throttle** (separate from API rate limit):
```php
// 5 failed logins per email per 15 minutes → temporary lockout
// 20 failed logins per IP per hour → IP block (stored in banned_ips table)
// Unlocks automatically after lockout period
```

**2. OTP brute force** (Part 08 — 5 attempts → 10 min lock per user):
Additional: 50 OTP attempts per IP per hour → IP flagged for review.

**3. AI abuse detection:**
- If a user hits text generation rate limit 10+ times in one day → flag for review
- Admin → Users → flagged users list with reason
- Auto-suspend after 3 flags (configurable)

**4. Banned IPs table:**
```sql
banned_ips
  id, ip_address varchar(45), reason varchar(255),
  banned_at timestamp, expires_at timestamp NULL,  -- NULL = permanent
  banned_by bigint NULL FK → admins.id
```

Checked in global middleware before any processing.

---

### 5.6 Frontend Rate Limit Handling

**Vue composable `useRateLimit.ts`:**
```typescript
export function useRateLimit() {
    const retryAfter = ref(0)
    const isRateLimited = ref(false)
    let countdownTimer: ReturnType<typeof setInterval> | null = null

    const handleRateLimit = (retryAfterSeconds: number) => {
        isRateLimited.value = true
        retryAfter.value = retryAfterSeconds

        countdownTimer = setInterval(() => {
            retryAfter.value--
            if (retryAfter.value <= 0) {
                isRateLimited.value = false
                clearInterval(countdownTimer!)
            }
        }, 1000)
    }

    const handleApiError = (error: AxiosError) => {
        if (error.response?.status === 429) {
            const seconds = error.response.data.retry_after ?? 60
            handleRateLimit(seconds)
        }
    }

    return { retryAfter, isRateLimited, handleRateLimit, handleApiError }
}
```

**In `ToolPage.vue`:**
```vue
<!-- Generate button shows countdown when rate limited -->
<button
  :disabled="isRateLimited || isStreaming"
  @click="generate"
>
  <template v-if="isRateLimited">
    Try again in {{ retryAfter }}s
  </template>
  <template v-else>
    Generate ✨
  </template>
</button>

<!-- Rate limit warning toast (auto-shown) -->
<!-- "Too many requests. Try again in 45 seconds." -->
```

---

### 5.7 Admin Rate Limit Configuration

Admin → Settings → Security → Rate Limits:

- Table of all rate limit categories with current values per tier (guest/free/pro)
- Inline edit each value
- "Reset to defaults" button
- Per-user override: Admin → Users → [User] → "Custom Rate Limits" — override specific limits for a specific user (e.g. raise limits for trusted power users)

**Table: `user_rate_limit_overrides`**
```sql
id, user_id FK, category varchar(100), max_attempts int,
decay_seconds int, created_by FK → admins.id, created_at, updated_at
```

When `ThrottleAiRequests` resolves limits: checks this table first, falls back to global settings.

---

### 5.8 Rate Limit Headers Reference

Every API response includes these headers (even non-limited responses):

```
X-RateLimit-Limit:     30        -- max requests in window
X-RateLimit-Remaining: 27        -- requests left in current window
X-RateLimit-Reset:     1736912345-- unix timestamp when window resets
Retry-After:           45        -- only on 429 responses: seconds to wait
```

Mobile app and third-party integrations should read `X-RateLimit-Remaining` to proactively slow down before hitting the limit.

---

### 5.9 Checklist: Rate Limiting

- [ ] Sliding window implementation tested: no boundary burst possible
- [ ] All rate limit values stored in `settings` table — editable from admin without code deploy
- [ ] Guest/free/pro tiers correctly resolved per user auth state
- [ ] `Retry-After`, `X-RateLimit-*` headers present on ALL API responses (not just 429)
- [ ] 429 error page shows countdown timer reading `Retry-After` header
- [ ] Frontend `useRateLimit` composable shows live countdown on Generate button
- [ ] OTP: 5 wrong attempts → 10 min user lockout (existing, Part 08)
- [ ] OTP: 50 attempts per IP/hr → IP flagged (new, this part)
- [ ] Login: 5 failures per email/15min → lockout; 20 per IP/hr → ban
- [ ] Banned IPs checked in global middleware (before route resolution)
- [ ] Per-user rate limit overrides work (override takes priority over global settings)
- [ ] AI abuse: 10+ rate limit hits/day → user flagged in admin
- [ ] Rate limit keys use sliding window (Redis sorted sets), not simple counters
- [ ] Redis key TTL set correctly — keys expire cleanly after decay window
- [ ] Admin can view flagged users list with reason + action buttons
- [ ] Mobile API docs describe rate limit headers and retry strategy


---


## PART 06 — DATABASE MIGRATIONS ORDER

Add to the end of the migrations list from Part 11:

24. `newsletter_subscribers` + `newsletter_campaigns`
25. `comments` + `comment_likes`
26. `favorites`
27. `ads`
28. `appearance_settings`
29. `menus` + `menu_items`
30. `pages`
31. `contact_messages`
32. `categories` + `ai_tool_category`
33. `social_follow_counts`

---


---


## PART 07 — ADDITIONAL MIGRATIONS (Tool Page Features)

Add to migrations list after Part 06:

```
tool_reviews            -- id, template_slug, user_id, rating, comment, is_approved, is_featured, helpful_count
tool_review_votes       -- id, review_id, user_id, is_helpful boolean (unique: review_id+user_id)
```

Add columns to `ai_tools`:
```
about_content, how_it_works (json), usage_examples (json), faq_items (json),
show_about, show_how_it_works, show_usage_examples, show_faqs, show_reviews,
avg_rating (decimal 3,2), review_count (int),
meta_title, meta_description, og_image,
supports_brand_voice (boolean default true),
avg_output_tokens (int default 400),
avg_latency_ms (int null)   -- updated after each generation for slow-tool detection
```

---

*End of MakeAI Complete Development Master Prompt*
*Version 3.1 · Parts: 42 · AI Templates: 255 · Mail Templates: 23 · API Endpoints: 80+ · Queue Jobs: 45+ · Integrations: 60+*

---

---

## 🔷 LAYER 2 — AUTH & USERS ✅

## PART 08 — AUTH SYSTEM: Password + OTP 2FA

Password-based login with optional OTP 2FA via authenticator app (TOTP) or email OTP. Email verification and password reset use numeric OTP codes.

### 31.1 Email Verification Flow

On registration:
1. User submits registration form
2. Account created with `email_verified_at = null`
3. 6-digit OTP generated → stored hashed in `users.otp_code` + `otp_expires_at = now() + 15 minutes`
4. OTP email sent immediately (high-priority queue)
5. User redirected to `/verify-email` page — Inertia page with OTP input (6 boxes, auto-focus, auto-advance)
6. User enters OTP → validated server-side → `email_verified_at = now()`, `otp_code = null`
7. On success → redirect to dashboard with welcome toast

**OTP input UX:**
- 6 individual single-digit input boxes (not one field)
- Auto-advances to next box on digit entry
- Backspace goes to previous box
- Paste support: paste "123456" fills all 6 boxes instantly
- Auto-submits when 6th digit entered (no submit button needed)
- Wrong OTP: shake animation + "Incorrect code" error, OTP boxes clear + refocus first box
- Countdown timer: "Code expires in 14:32" (live countdown)
- Resend button: disabled until countdown reaches 0, then enabled — sends new OTP, resets countdown
- Max attempts: 5 wrong attempts → lock for 10 minutes (configurable in settings)

### 31.2 Password Reset Flow

1. User enters email on `/forgot-password`
2. If email exists → generate OTP → send → redirect to `/reset-password?email={encoded_email}`
3. OTP input page (same 6-box component, reusable `<OtpInput />` Vue component)
4. Valid OTP → show new password + confirm password fields (same page, transition animation)
5. Submit new password → `password_changed` notification sent → redirect to login with success toast

**Security:** OTP stored as `bcrypt` hash — never plaintext. Email is shown partially masked on OTP page: `j***@gmail.com`.

### 31.3 Login OTP (2FA via Email)

If admin enables "Email OTP as 2FA" in settings (alternative to TOTP):
1. User enters email + password → credentials valid
2. Redirect to OTP verification page (same component)
3. OTP sent to email automatically
4. Verified → logged in

### 31.4 OTP Configuration (Admin → Settings → Security)

```
otp_length              int     6 (or 4, configurable)
otp_expiry_minutes      int     15
otp_max_attempts        int     5
otp_lockout_minutes     int     10
otp_resend_cooldown_sec int     60
```

### 31.5 Reusable OtpInput Vue Component

```typescript
// components/UI/OtpInput.vue
// Props: length (4|6), autoSubmit (bool), onComplete (callback)
// Emits: complete(code: string)
// Features: auto-advance, backspace, paste, shake animation, countdown
```

Used in: email verification, password reset, login 2FA, admin login 2FA.

---


---


## PART 09 — USER MODEL

### 4.1 Users Table ✅

```sql
id                      bigint PK
ulid                    char(26) UNIQUE          -- public-facing ID (no sequential enumeration)
name                    varchar(255)
email                   varchar(255) UNIQUE
email_verified_at       timestamp NULL
password                varchar(255)
avatar                  varchar(255) NULL
provider                varchar(50) NULL          -- google, github, facebook
provider_id             varchar(255) NULL

-- Credits
credits                 decimal(12,4) DEFAULT 0
credits_used_today      decimal(12,4) DEFAULT 0
credits_used_month      decimal(12,4) DEFAULT 0
daily_limit             decimal(12,4) NULL        -- NULL = use global setting
monthly_limit           decimal(12,4) NULL

-- Plan / Subscription (used if isProAvailable())
plan_id                 bigint NULL FK → plans.id
subscription_status     enum('active','trialing','past_due','canceled','none') DEFAULT 'none'
subscription_ends_at    timestamp NULL
trial_ends_at           timestamp NULL

-- Referral system
referral_code           varchar(20) UNIQUE        -- auto-generated
referred_by             bigint NULL FK → users.id
referral_earnings       decimal(12,4) DEFAULT 0
referral_count          int DEFAULT 0

-- Security
two_factor_secret       varchar(255) NULL
two_factor_enabled      boolean DEFAULT false
two_factor_confirmed_at timestamp NULL
otp_code                varchar(10) NULL
otp_expires_at          timestamp NULL
login_attempts          int DEFAULT 0
locked_until            timestamp NULL

-- Preferences
language                varchar(10) DEFAULT 'en'
timezone                varchar(50) DEFAULT 'UTC'
theme_preference        enum('light','dark','system') DEFAULT 'system'
personal_api_keys       json NULL                 -- user-supplied API keys (per provider)
brand_voice             text NULL                 -- stored brand context

-- Status
is_active               boolean DEFAULT true
is_banned               boolean DEFAULT false
ban_reason              text NULL

-- Meta
last_login_at           timestamp NULL
last_login_ip           varchar(45) NULL
email_marketing         boolean DEFAULT true
remember_token          varchar(100) NULL
created_at, updated_at, deleted_at
```

**Additional tables:**

`credit_transactions` — id, user_id, amount (+/-), balance_after, type (purchase/usage/refund/bonus/referral), description, meta json, created_at

`user_api_keys` — id, user_id, provider, api_key (encrypted), is_active, created_at

`login_history` — id, user_id, ip, user_agent, country, city, success boolean, created_at

### 4.2 User Auth Flow ✅

- Registration: email/password or social (Google, GitHub, Facebook, Reddit, Twitter X via Socialite)
- Email verification (queue-based)
- Login: email + password → optional 2FA (TOTP or email OTP)
- Password reset via email otp
- Referral: `/register?ref=CODE` → stores `referred_by` on new user, awards referral credits on first purchase (if enabled in settings)

### 4.3 User Dashboard ✅

User sees their own overview:
- Credit balance + usage chart (Chart.js — last 7 days)
- Quick access to all AI tools
- Recent documents/generations
- Active plan details (if `isProAvailable()`), cancel subscription, upgrade plan
- Referral link + earnings
- Recent transactions
- Payout history/request/settings
- Support tickets
- Account settings
- Security settings (password change, 2fa)

---

## PART 10 — ADMIN MODEL & RBAC ✅

### 3.1 Separate Admin Auth

Admin uses **entirely separate** model, guard, and auth flow — completely isolated from user auth.

**`app/Models/Admin.php`** — extends `Authenticatable`, uses `admins` table.

**Table: `admins`**
```sql
id                  bigint PK
name                varchar(255)
email               varchar(255) UNIQUE
password            varchar(255)
avatar              varchar(255) NULL
role_id             bigint FK → admin_roles.id
is_active           boolean DEFAULT true
two_factor_secret   varchar(255) NULL (TOTP for Google Authenticator)
two_factor_enabled  boolean DEFAULT false
otp_secret          varchar(10) NULL (for email OTP)
otp_expires_at      timestamp NULL
last_login_at       timestamp NULL
last_login_ip       varchar(45) NULL
remember_token      varchar(100) NULL
created_at, updated_at
```

**Config: `config/auth.php`**
```php
'guards' => [
    'admin' => ['driver' => 'session', 'provider' => 'admins'],
],
'providers' => [
    'admins' => ['driver' => 'eloquent', 'model' => Admin::class],
],
```

**Login flow:**
1. Email + password → verify credentials
2. If 2FA enabled → redirect to TOTP verification page
3. If OTP method → send OTP to email → verify
4. On success → redirect to admin dashboard

### 3.2 Admin RBAC (Role-Based Access Control)

**Table: `admin_roles`**
```sql
id, name (e.g. 'Super Admin', 'Support', 'Content Manager'), slug, description, is_system boolean, created_at
```

**Table: `admin_permissions`**
```sql
id, name, slug (e.g. 'users.view', 'users.edit', 'settings.manage', 'ai.manage'), group, description
```

**Table: `admin_role_permissions`** (pivot)
```sql
role_id FK, permission_id FK
```

Pre-seeded permissions organized by group:
- `dashboard.*` — view dashboard, view analytics
- `users.*` — view, create, edit, delete, impersonate, manage credits
- `admins.*` — view, create, edit, delete (Super Admin only)
- `roles.*` — view, create, edit, delete (Super Admin only)
- `settings.*` — general, ai, mail, payment, license, advanced
- `ai.*` — manage providers, models, limits, costs
- `content.*` — templates, prompts, categories
- `plans.*` — view, create, edit, delete subscription plans
- `payments.*` — view transactions, refund, export
- `addons.*` — install, activate, configure
- `themes.*` — activate, configure
- `translations.*` — manage languages and translations
- `reports.*` — view all reports and exports
**check others features if excluded from here**

**`app/Http/Middleware/AdminPermission.php`** — checks `auth('admin')->user()->hasPermission('slug')`.

**`AdminCan` Vue directive** — `v-admin-can="'users.edit'"` to hide UI elements based on permissions.

### 3.3 Admin Dashboard

Built with **Chart.js** (loaded via npm). Dashboard cards and charts:

**Stats cards (real-time):**
- Total users / new today / new this month
- Total revenue / revenue today / MRR (if subscriptions enabled)
- Total AI requests / tokens used today / estimated cost today
- Total credits / credits used today / estimated credits cost today
- Active subscriptions count (if `isProAvailable()`)

**Charts:**
- User signups — line chart (7days, this month, 90 days)
- Revenue — bar chart (7days, this month, 90 days)
- AI usage by tool — doughnut chart (7days, this month, 90 days)
- Token cost breakdown by AI provider — bar chart (7days, this month, 90 days)
- Revenue vs Cost — bar chart / line chart (7days, this month, 90 days)
- Pro subscriptions (if `isProAvailable()`) - line chart (7days, this month, 90 days)
- Usage location - Geo chart (7days, this month, 90 days)

**List style**
- Traffic sources (direct, social media, referal, ads, others..)
- Top AI tools used (max 6) - (by user usage count, by cost, by token usage)
- Top AI models used (max 6) - (by user usage count, by cost, by token usage)
- Recently registered users (max 6)


**Quick actions:**
- Send announcement to all users
- View latest support tickets
- Add AI tools
- Make a note (in admin note section will be shows as my notes with upcoming agenda, note will be created with modal)
- Maintenance mode toggle

**Export Dashboard Reports**
- Using `PART 63 — EXPORT CENTER (EXCEL/CSV & PDF)` for all export reports

### 3.4 Admin Panel Sections (Full)

**Users Management:**
- List with filters (status, plan, date, search)
- View user detail: profile, credit history, AI usage, payment history, login history
- Edit user: name, email, password, credits, plan, status, 2FA reset
- Impersonate user
- Export users CSV/Excel
- Bulk actions: activate, deactivate, add credits, delete

**AI Management:**
- API key management per provider (OpenAI, Anthropic, Google, xAI, etc.)
- Multiple keys per provider (load-balanced, round-robin)
- Per-model settings: enable/disable, cost per 1K tokens input/output, max tokens, rate limit
- Global token limits: per request, per day per user, per month per user
- Over-usage protection: soft limit (warning) and hard limit (block)
- Cost tracking: total cost, cost per user, cost per tool, cost alerts

**Content Management:**
- AI templates: create, edit, delete, reorder, group by category
- Prompt library: admin-seeded prompts visible to all users
- Custom chatbot personas
- Blog/pages (built-in CMS for landing page content)

**Plans & Subscriptions (only if `isProAvailable()`):**
- Create/edit plans: name, price, billing cycle (monthly/yearly/lifetime), credits, features list
- Feature flags per plan: which AI tools are accessible, image/video generation limits
- Trial period settings
- Free plan configuration

**Payment Management:**
- Gateway toggle: Stripe, PayPal, Paddle, Razorpay, SSLCommerz, Bank Transfer, Crypto (CoinGate)
- Transaction list with search/filter/export
- Refund processing
- Revenue reports
- Tax settings per country

**Addon & Theme Manager:**
- Upload .zip addon/theme package
- Install, activate, deactivate, delete
- Per-addon settings form (generated from `settings.json`)
- License gate display (shows lock icon if addon needs extended license)

**Reports:**
- AI usage report (by user, tool, model, date range)
- Revenue report
- Export all to CSV/Excel

**Site Settings (grouped tabs — see Section 2.1)**

---


---


---

## 🔷 LAYER 3 — AI CORE ✅

## PART 11 — AI ENGINE ("Laravel AI SDK" Core)

### 5.1 Provider Registry

**`app/Services/AI/ProviderRegistry.php`**

Supported providers (all configurable from admin):
- OpenAI (GPT-4o, GPT-4o-mini, GPT-4-turbo, o1, o3, o4-mini)
- Anthropic (Claude Sonnet 4.5, Claude Opus 4, Claude Haiku)
- Google (Gemini 2.0 Flash, Gemini 2.5 Pro, Gemini 2.5 Flash)
- xAI (Grok 3, Grok 3 Mini)
- DeepSeek (DeepSeek-R1, DeepSeek-V3)
- Mistral
- OpenRouter (unified gateway to 100+ models)
- Perplexity (for web search)

Multiple API keys per provider → round-robin load balancing → automatic failover on rate limit.

**User personal API keys:** If user has set their own key for a provider, use their key for that provider (bypasses admin key, does not charge credits for that request).

### 5.2 Laravel AI SDK Engine Integration

**`app/Services/AI/AiService.php`**

```php
class AiService
{
    // Simple completion
    public function complete(CompletionRequest $request): CompletionResponse

    // Streaming completion (returns Generator for SSE)
    public function stream(CompletionRequest $request): Generator

    // RAG: embed + query
    public function embedText(string $text, string $provider = 'openai'): array  // vector
    public function ragQuery(string $query, VectorStore $store, int $topK = 5): RagResponse

    // Agent execution
    public function runAgent(AgentDefinition $agent, string $userMessage, array $tools = []): AgentResponse

    // Document ingestion
    public function ingestDocument(UploadedFile $file, int $userId, string $collectionId): IngestedDocument
    // Supports: PDF, DOCX, TXT, CSV, XLSX, URL (web scrape)
}
```

### 5.3 Token & Cost Control

**`app/Services/AI/TokenGuard.php`**

Before every AI request:
1. Check user daily credit limit (user-specific or global setting)
2. Check user monthly credit limit
3. Check global daily budget (admin setting: "max total API spend per day")
4. If hard limit reached: throw `CreditLimitException` → return 402 response
5. If soft limit (80% of limit): add warning header to response → frontend shows toast warning

After every AI request:
1. Calculate actual token cost: `input_tokens × input_cost_per_1k / 1000 + output_tokens × output_cost_per_1k / 1000`
2. Deduct from user credits via `deduct_credits()`
3. Log to `ai_usage_logs` table
4. Update `credits_used_today` and `credits_used_month` on user

**Table: `ai_usage_logs`**
```sql
id, user_id, provider, model, tool (e.g. 'ai_writer', 'ai_chat', 'image_gen'),
input_tokens, output_tokens, total_tokens,
estimated_cost_usd decimal(10,6),
credits_deducted decimal(10,4),
request_id varchar(100), -- from provider response
created_at
```

### 5.4 AI Feature Modules

Each module is a dedicated Service class + Controller + Vue page:

#### AI Writer
- 100+ templates (seeded) organized by category
- Custom template builder (admin creates, users can also create private templates)
- Form inputs → system prompt + user prompt assembly → stream to editor
- Advanced AI Editor: rich text editor (Tiptap), AI sidebar (improve, rewrite, translate, summarize, expand, shorten)
- Brand Voice context: injected into every prompt if user has set it
- Export: PDF, DOCX, TXT, HTML
- Document manager: folders, favorites, search, bulk delete

#### AI Chat Pro
- Multi-model selector per conversation
- Conversation history (stored, searchable)
- System prompt customization per chat
- File attachment: PDF, DOCX, CSV → parsed and included in context
- Image upload (vision models)
- Web search mode (Perplexity API or SerpAPI)
- Memory: key facts extracted from conversations, injected in future chats
- Folders for organizing conversations
- Share conversation (public link)
- Voice input (Web Speech API)

#### AI Image Generator
- Providers: DALL-E 3, Stable Diffusion 3, Flux Pro, Midjourney (via API proxy), Ideogram
- Image-to-image, text-to-image
- Upscaling, remove background, style transfer
- Prompt enhancer (AI rewrites user prompt for better results)
- Image library with folders
- Cost/credit display per generation

#### AI Video Generator
- Providers: Sora, Kling AI, Google Veo, Minimax
- Text-to-video, image-to-video
- Video library + download

#### AI Voice & Audio
- Text-to-speech: ElevenLabs, OpenAI TTS, Azure TTS, Google Cloud TTS
- Speech-to-text: OpenAI Whisper
- Voice cloning: ElevenLabs
- AI Music: Suno, Udio (via API)
- Audio file manager

#### AI Code Generator
- Language selector (50+ languages)
- Code explanation, debugging, optimization, documentation
- Syntax-highlighted output (highlight.js)
- Run code (sandbox via Judge0 or Piston API — configurable)

#### AI Chat Bot Builder
- Create custom chatbots with: name, avatar, persona, system prompt
- Training sources: website URL (scraper), PDF upload, text/Q&A pairs, CSV
- Vector embeddings stored per chatbot (SDK RAG)
- Embeddable widget (JS snippet) for external websites
- CRM inbox: view all conversations from embedded bots
- WhatsApp integration (Twilio/360dialog — addon)
- Telegram integration (addon)

#### Knowledge Base (RAG Documents)
- Upload documents → automatic chunking + embedding (Laravel AI SDK, `laravel/ai`)
- Collections/workspaces per user
- Chat with any collection
- Admin can create shared knowledge bases available to all users

#### AI Social Media Suite (Extended feature, available to all users if admin enables)
- Connect: LinkedIn, X/Twitter, Instagram, Facebook, TikTok
- Schedule posts with AI-generated caption + image
- Calendar view
- Bulk scheduling from CSV
- Analytics per post
- AI-suggested best posting times

#### AI Article Wizard
- Step 1: Topic + keywords + audience
- Step 2: AI generates outline (editable)
- Step 3: AI writes each section
- Step 4: AI generates featured image (optional)
- Step 5: SEO meta generation
- Step 6: Export or publish to WordPress (via REST API)

#### AI Workflow Builder
- Visual drag-and-drop node editor (Vue Flow)
- Node types: AI Writer, AI Chat, Image Gen, Web Search, Email Send, Webhook, Condition, Loop
- Trigger types: manual, schedule (cron), webhook, on event
- Saved workflows per user
- Execution logs

#### AI Plagiarism & Content Detector
- Plagiarism check via Copyscape API or Originality.ai
- AI content detection (GPTZero or Sapling integration)

#### AI Presentation Maker
- Topic → AI generates slide structure
- Slide deck editor (built-in, no external dependency)
- Export to PPTX (using PhpPresentation)

---

## PART 12 — AI INTEGRATIONS & CREDENTIALS ✅

All third-party API integrations manageable from Admin → Settings → Integrations. Credentials stored encrypted in `settings` table.

### 33.1 AI Model Providers

| Provider | Keys Required | Features |
|----------|-------------|---------|
| OpenAI | API Key | GPT-4o, DALL-E 3, TTS, Whisper |
| Anthropic | API Key | Claude Sonnet, Claude Opus, Claude Haiku |
| Google AI | API Key | Gemini 2.0 Flash, Gemini 2.5 Pro, Gemini 2.5 Flash |
| xAI | API Key | Grok 3, Grok 3 Mini |
| DeepSeek | API Key | DeepSeek-R1, DeepSeek-V3 |
| Mistral | API Key | Mistral Large, Mistral Nemo |
| Perplexity | API Key | Sonar (web search AI) |
| OpenRouter | API Key | 200+ models via single endpoint |
| Cohere | API Key | Command R+, embeddings |
| Groq | API Key | Ultra-fast inference (Llama, Mixtral) |
| Together AI | API Key | Open source models |
| Replicate | API Key | Stable Diffusion, custom models |

### 33.2 Image Tools

| Provider | Keys Required | Used For |
|----------|-------------|---------|
| DALL-E 3 | (OpenAI key) | AI image generation |
| Stable Diffusion (Replicate) | Replicate key | Image generation |
| Flux Pro (fal.ai) | fal.ai key | Image generation |
| Ideogram | API Key | Image generation |
| Midjourney (unofficial proxy) | Proxy URL + key | Image generation |
| Stability AI | API Key | SDXL, SD3 |
| Pixabay | API Key | Stock image search (for templates) |
| Pexels | API Key | Stock image search |
| Unsplash | Access Key + Secret | Stock image search |
| Remove.bg | API Key | Background removal |
| Clipdrop | API Key | Image editing tools |

### 33.3 Video Tools

| Provider | Keys Required | Used For |
|----------|-------------|---------|
| Kling AI | API Key + Secret | Text/image to video |
| Google Veo (via Vertex AI) | Google credentials | Video generation |
| Minimax Video | API Key | Video generation |
| Runway ML | API Key | Video generation, editing |
| Sora (OpenAI) | OpenAI key (when available) | Video generation |
| D-ID | API Key | Talking avatar videos |
| HeyGen | API Key | AI avatar video |
| Synthesia | API Key | AI presenter video |
| Pika Labs | API Key | Short video generation |

### 33.4 Voice & Audio Tools

| Provider | Keys Required | Used For |
|----------|-------------|---------|
| ElevenLabs | API Key | TTS, voice clone |
| OpenAI TTS | (OpenAI key) | TTS (alloy, echo, fable, onyx, nova, shimmer) |
| Azure Cognitive Speech | Key + Region | TTS, STT |
| Google Cloud TTS | Service account JSON | TTS |
| Amazon Polly | AWS Key + Secret + Region | TTS |
| Murf AI | API Key | TTS |
| PlayHT | User ID + API Key | TTS, voice clone |
| Whisper (OpenAI) | (OpenAI key) | Speech to text |
| AssemblyAI | API Key | STT, transcription, audio intelligence |
| Deepgram | API Key | Real-time STT |
| Suno AI | (cookie/unofficial) | AI music generation |
| Udio | (unofficial) | AI music generation |
| Lalal.ai | API Key | Voice/instrument separator |

### 33.5 Productivity & Integration Tools

| Provider | Keys Required | Used For |
|----------|-------------|---------|
| Notion | Integration Token | Export to Notion, read Notion pages |
| Google Drive | OAuth Client ID + Secret | Export/import documents |
| Google Docs | (Google Drive OAuth) | Export to Google Docs |
| WordPress | Site URL + App Password | Publish blog posts to WordPress |
| Zapier | Webhook URLs | Workflow integrations |
| Slack | Bot Token + Signing Secret | Notifications, share content |
| Gamma | API Key | AI presentation generation |
| Canva | Client ID + Secret | Design integrations |
| HubSpot | Private App Token | CRM integration |
| Airtable | Personal Access Token | Database integrations |

### 33.6 Utility & Enhancement Tools

| Provider | Keys Required | Used For |
|----------|-------------|---------|
| SerpAPI | API Key | Web search in AI chat |
| Google Search | API Key + CSE ID | Web search fallback |
| Bing Search | API Key | Web search |
| Google reCAPTCHA | Site Key + Secret | Form protection |
| hCaptcha | Site Key + Secret | Form protection (GDPR alternative) |
| Akismet | API Key | Comment spam filter |
| Copyscape | Email + API Key | Plagiarism detection |
| Originality.ai | API Key | AI content detection + plagiarism |
| GPTZero | API Key | AI content detection |
| Sapling | API Key | AI content detection |
| DeepL | Auth Key | Professional translation |
| Google Translate | API Key | Translation fallback |
| ExchangeRate API | API Key | Currency rate updates |
| Fixer.io | API Key | Currency rate updates (alternative) |
| IPInfo | Token | IP geolocation (login history) |
| Twilio | Account SID + Auth Token | SMS OTP |
| Firebase FCM | Server Key | Push notifications (mobile) |
| CoinGate | API Key | Crypto payments |
| Stripe | Publishable + Secret Key | Payments |
| PayPal | Client ID + Secret | Payments |
| SSLCommerz | Store ID + Password | Payments (BD) |
| Razorpay | Key ID + Secret | Payments (India) |

### 33.7 Credentials UI (Admin → Settings → Integrations)

Organized into collapsible groups matching the categories above. Each integration card:
- Provider logo/icon
- Name + description (what it's used for in MakeAI)
- Input fields (masked password inputs for secrets)
- "Test Connection" button → hits a test endpoint and shows ✅ / ❌ with error message
- Enable/disable toggle (disables the feature in-app without deleting keys)
- "Docs" link → opens provider's API docs in new tab
- Status badge: Connected / Not configured / Error

Integrations are lazy-loaded in tabs to avoid page performance issues:
- Tab 1: AI Models
- Tab 2: Image & Media
- Tab 3: Voice & Video
- Tab 4: Productivity
- Tab 5: Utilities & Payments

---


---


## PART 13 — AI TOOL ACCESS CONTROL ✅

Admin → AI Tools → Access Settings

Per-tool control over whether login is required to use the tool.

### 35.1 Global Setting

Admin → Settings → General → AI Tools:
- **Default access mode** for all tools: `public` / `login_required` / `plan_required`
- This is the fallback for any tool that doesn't have a specific override

### 35.2 Per-Tool Override

Each tool in the templates list has an `access_level` field:

```sql
access_level  enum('inherit','public','login_required','free_plan','pro_plan') DEFAULT 'inherit'
```

- `inherit` — uses global default setting
- `public` — anyone can use (no login, no credits deducted — free preview mode)
- `login_required` — must be logged in (free registered users can use)
- `free_plan` — logged in + has active account (credits deducted)
- `pro_plan` — requires paid subscription (only if `isProAvailable()`)

### 35.3 Public Tool Behavior

When a tool is `public` and used by a guest:
- No credit deduction
- Output length limited (admin configurable: e.g. max 200 words for public use)
- "Sign up to get full output" upsell shown after truncated result
- Rate limited by IP (e.g. 5 requests per hour per IP, configurable)
- Watermark text optionally appended to output (configurable)
- Results not saved to user's document library

### 35.4 Admin Bulk Control

Admin → AI Tools → Access Settings:
- Table of all tools with current access level
- Bulk edit: select multiple → change access level
- Category-level override: set access level for entire category at once
- Quick presets:
  - "Make all tools public" → sets all to public
  - "Require login for all" → sets all to login_required
  - "Pro tools: set all to pro_plan"
  - "Reset all to inherit"

### 35.5 Frontend Behavior

When an unauthenticated user tries to use a `login_required` tool:
- Modal appears: "Sign in to use this tool" with Login + Register buttons
- Tool form is visible but submit is intercepted
- No redirect away from the page (modal overlay only)

When a free user tries a `pro_plan` tool:
- Modal: "Upgrade to Pro to unlock this tool" with plan comparison and upgrade CTA
- If `isProAvailable() === false` (regular license) → modal shows: "Premium features are not available on this installation"

---

All tools stored in `ai_tools` table and seeded via `database/seeders/AiToolSeeder.php`. Each template belongs to a category, has a unique `slug`, system prompt, user input fields definition (JSON), and can be enabled/disabled from admin panel.

### Tools Table Structure

```sql
ai_tools
  id
  category_id          FK → categories (type='ai_tool')
  name                 varchar(255)
  slug                 varchar(100) UNIQUE
  description          text
  icon                 varchar(100)          -- Tabler icon class
  color                varchar(20)           -- hex for card accent
  prompt_system        text                  -- system prompt (admin editable)
  prompt_user          text                  -- user prompt template with {field} placeholders
  fields               json                  -- input field definitions (see below)
  output_type          enum('text','markdown','html','code','list') DEFAULT 'markdown'
  model_override       varchar(100) NULL     -- force specific model, null = use user's selected model
  max_tokens_override  int NULL
  is_active            boolean DEFAULT true
  is_featured          boolean DEFAULT false
  requires_pro         boolean DEFAULT false -- only available on paid plan
  sort_order           int DEFAULT 0
  usage_count          bigint DEFAULT 0      -- incremented on each use
  created_at, updated_at
```

**Fields JSON format:**
```json
[
  {
    "name": "product_name",
    "label": "Product Name",
    "type": "text",           // text | textarea | select | number | toggle | language_select | tone_select | length_select
    "placeholder": "e.g. iPhone 16 Pro",
    "required": true,
    "max_length": 100
  },
  {
    "name": "tone",
    "label": "Tone of Voice",
    "type": "tone_select",    // renders preset tone dropdown: Professional, Friendly, Casual, Formal, Humorous, Persuasive, Inspirational
    "required": false
  },
  {
    "name": "language",
    "label": "Output Language",
    "type": "language_select",
    "required": false,
    "default": "English"
  },
  {
    "name": "length",
    "label": "Output Length",
    "type": "length_select",  // Short / Medium / Long / Custom (word count input)
    "required": false
  }
]
```

---

### CATEGORY: 🛒 Ecommerce

| # | Name | Slug | Description |
|---|------|------|-------------|
| 1 | Product Description | `product-description` | Compelling product descriptions that sell |
| 2 | Product Features | `product-features` | Key feature bullets for any product |
| 3 | Product Name Ideas | `product-name-ideas` | Creative and catchy product name suggestions |
| 4 | Why Choose This Product | `why-choose-product` | Persuasive reasons to buy a specific product |
| 5 | Customer Review Generator | `customer-review` | Authentic-sounding product reviews |
| 6 | Review Responder | `review-responder` | Professional responses to customer reviews |
| 7 | Amazon Product Listing | `amazon-listing` | Full Amazon listing: title, bullets, description |
| 8 | Shopify Product Page | `shopify-product` | Complete Shopify product page copy |
| 9 | Product Comparison | `product-comparison` | Compare two products objectively |
| 10 | Upsell / Cross-sell Message | `upsell-message` | Persuasive upsell/cross-sell copy |
| 11 | Flash Sale Copy | `flash-sale-copy` | Urgency-driven sale announcement copy |
| 12 | Abandoned Cart Email | `abandoned-cart-email` | Win-back email for abandoned carts |

---

### CATEGORY: ✍️ Blog & Content

| # | Name | Slug | Description |
|---|------|------|-------------|
| 13 | Blog Article (Full) | `blog-article` | Complete long-form blog article |
| 14 | Blog Intro | `blog-intro` | Engaging blog post introduction |
| 15 | Blog Outline | `blog-outline` | Structured outline for any blog topic |
| 16 | Blog Conclusion | `blog-conclusion` | Memorable blog post conclusion |
| 17 | Blog Section Writer | `blog-section` | Write a specific section of a blog post |
| 18 | Blog Ideas Generator | `blog-ideas` | Creative blog topic ideas for a niche |
| 19 | Article Rewriter | `article-rewriter` | Rewrite existing article uniquely |
| 20 | Content Improver | `content-improver` | Enhance quality and readability of content |
| 21 | Paragraph Generator | `paragraph-generator` | Well-written paragraph on any topic |
| 22 | TL;DR Summarization | `tldr-summary` | Bite-sized summaries of long content |
| 23 | Explain to a Child | `explain-to-child` | Simplify complex topics for children |
| 24 | News Article Writer | `news-article` | News article from journalist perspective |
| 25 | Press Release | `press-release` | Professional press release for announcements |
| 26 | eBook Generator | `ebook-generator` | Full eBook outline and content on any topic |
| 27 | Undetectable AI Rewriter | `undetectable-rewriter` | Rewrite AI content to sound human |
| 28 | Proofreading | `proofreading` | Grammar, style, and clarity improvements |
| 29 | Rephrase / Paraphrase | `rephrase` | Rephrase text while keeping meaning |
| 30 | Text Expander | `text-expander` | Expand short text into detailed content |
| 31 | Text Summarizer | `text-summarizer` | Condense long text into summary |
| 32 | Content from RSS Feed | `rss-content` | Generate unique content from RSS feed URL |
| 33 | YouTube to Blog Post | `youtube-to-blog` | Convert YouTube video transcript to blog post |
| 34 | Wikipedia to Article | `wiki-to-article` | Rewrite Wikipedia content into original article |

---

### CATEGORY: 📱 Social Media

| # | Name | Slug | Description |
|---|------|------|-------------|
| 35 | Instagram Caption | `instagram-caption` | Engaging captions for Instagram posts |
| 36 | Instagram Hashtags | `instagram-hashtags` | Relevant trending hashtags for Instagram |
| 37 | Instagram Reel Script | `instagram-reel-script` | Short-form script for IG Reels |
| 38 | TikTok Script | `tiktok-script` | Hook-driven TikTok video script |
| 39 | TikTok Caption | `tiktok-caption` | Captions and hashtags for TikTok |
| 40 | Facebook Post | `facebook-post` | Engaging Facebook post for pages/groups |
| 41 | Facebook Headline | `facebook-headline` | Attention-grabbing Facebook headline |
| 42 | Facebook Video Script | `facebook-video-script` | Script for Facebook video content |
| 43 | X / Twitter Tweet | `twitter-tweet` | High-engagement tweet for any topic |
| 44 | X / Twitter Thread | `twitter-thread` | Multi-tweet thread on any topic |
| 45 | Viral Tweet Ideas | `viral-tweet-ideas` | Ideas for potentially viral tweets |
| 46 | LinkedIn Post | `linkedin-post` | Professional and engaging LinkedIn post |
| 47 | LinkedIn Profile Summary | `linkedin-summary` | Compelling LinkedIn About section |
| 48 | YouTube Video Title | `youtube-title` | SEO-friendly YouTube video titles |
| 49 | YouTube Video Description | `youtube-description` | Full YouTube video description with keywords |
| 50 | YouTube Video Tags | `youtube-tags` | Relevant tags for YouTube videos |
| 51 | YouTube Video to Blog | `youtube-to-blog-post` | Blog post from YouTube video |
| 52 | Pinterest Pin Description | `pinterest-description` | Compelling Pinterest pin descriptions |
| 53 | Social Media Reply | `social-media-reply` | Professional replies to comments/messages |
| 54 | AMA Post | `ama-post` | Ask Me Anything post for any platform |
| 55 | Trending Content Ideas | `trending-ideas` | Ideas based on current trends |
| 56 | Clickbait Title Generator | `clickbait-title` | Click-worthy titles for social posts |
| 57 | Social Media Bio | `social-bio` | Profile bio for any social platform |
| 58 | Content Calendar Planner | `content-calendar` | Weekly/monthly social content plan |
| 59 | Hashtag Strategy | `hashtag-strategy` | Full hashtag strategy for a niche |
| 60 | Video Description | `video-description` | General video description for any platform |
| 61 | Video Ideas Generator | `video-ideas` | Creative video content ideas |

---

### CATEGORY: 📣 Advertising

| # | Name | Slug | Description |
|---|------|------|-------------|
| 62 | Google Ads Headline | `google-ads-headline` | High CTR Google Ads headlines |
| 63 | Google Ads Description | `google-ads-description` | Compelling Google Ads descriptions |
| 64 | Facebook Ad Copy | `facebook-ad` | Complete Facebook ad copy (primary + headline + CTA) |
| 65 | Instagram Ad Copy | `instagram-ad` | Instagram ad copy with CTA |
| 66 | YouTube Ads Script | `youtube-ads-script` | 15/30/60 second YouTube ad script |
| 67 | TV Commercial Script | `tv-commercial` | Full TV advertisement script |
| 68 | Ad Headline Variations | `ad-headline-variations` | Multiple headline A/B test variations |
| 69 | Ad Script | `ad-script` | Generic ad script for any platform |
| 70 | Advertising Ideas | `advertising-ideas` | Creative advertising campaign ideas |
| 71 | PPC Ad Campaign | `ppc-campaign` | Complete PPC campaign copy (multiple ad groups) |
| 72 | Retargeting Ad Copy | `retargeting-ad` | Ad copy for retargeting campaigns |
| 73 | Native Ad Copy | `native-ad` | Sponsored/native content ad copy |
| 74 | App Store Ad Copy | `app-store-ad` | App Store / Google Play ad copy |
| 75 | Promotional SMS | `promo-sms` | Short promotional SMS message |
| 76 | App & Push Notification | `push-notification` | Mobile push notification copy |
| 77 | AIDA Framework | `aida` | Attention-Interest-Desire-Action copy |
| 78 | PAS Framework | `pas` | Problem-Agitate-Solution copy framework |

---

### CATEGORY: 📧 Email Marketing

| # | Name | Slug | Description |
|---|------|------|-------------|
| 79 | Email Generator | `email-generator` | Professional email for any purpose |
| 80 | Email Reply Generator | `email-reply` | Smart replies to incoming emails |
| 81 | Cold Email | `cold-email` | Effective cold outreach email |
| 82 | Follow-up Email | `follow-up-email` | Follow-up email sequence |
| 83 | Welcome Email | `welcome-email` | Warm welcome email for new subscribers |
| 84 | Invitation Email | `invitation-email` | Event or product launch invitation email |
| 85 | Newsletter Content | `newsletter-content` | Engaging newsletter content |
| 86 | Apology Email | `apology-email` | Sincere professional apology email |
| 87 | Sales Email | `sales-email` | Persuasive sales email sequence |
| 88 | Re-engagement Email | `re-engagement-email` | Win back inactive subscribers |
| 89 | Onboarding Email Sequence | `onboarding-sequence` | Multi-email onboarding flow |
| 90 | Announcement Email | `announcement-email` | Product/feature announcement email |
| 91 | Survey / Feedback Request | `survey-email` | Polite email asking for feedback |
| 92 | Referral Request Email | `referral-email` | Email asking customers for referrals |
| 93 | Thank You Email | `thank-you-email` | Professional thank you email |

---

### CATEGORY: 💼 Business

| # | Name | Slug | Description |
|---|------|------|-------------|
| 94 | Business Plan | `business-plan` | Full structured business plan |
| 95 | Executive Summary | `executive-summary` | Concise executive summary for business |
| 96 | SWOT Analysis | `swot-analysis` | Strengths, Weaknesses, Opportunities, Threats |
| 97 | Marketing Plan | `marketing-plan` | Structured marketing strategy and plan |
| 98 | Sales Pitch | `sales-pitch` | Persuasive sales pitch for any product |
| 99 | Startup Idea Generator | `startup-ideas` | Innovative startup ideas with market analysis |
| 100 | Business Strategy | `business-strategy` | Strategic plans for business growth |
| 101 | Cost-Benefit Analysis | `cost-benefit` | Decision-making cost-benefit framework |
| 102 | Brainstorming Session | `brainstorming` | Structured creative brainstorming output |
| 103 | Meeting Agenda | `meeting-agenda` | Professional meeting agenda |
| 104 | Meeting Minutes | `meeting-minutes` | Summary of meeting discussion and action items |
| 105 | Project Proposal | `project-proposal` | Formal project proposal document |
| 106 | Job Description | `job-description` | Comprehensive job posting description |
| 107 | Interview Questions | `interview-questions` | Role-specific interview question set |
| 108 | Performance Review | `performance-review` | Employee performance review write-up |
| 109 | Company Mission & Vision | `mission-vision` | Mission statement and vision for a company |
| 110 | OKR Generator | `okr-generator` | Objectives and Key Results for any team/goal |
| 111 | Investor Pitch Deck Script | `pitch-deck-script` | Slide-by-slide investor pitch script |
| 112 | Partnership Proposal | `partnership-proposal` | B2B partnership proposal letter |
| 113 | NDA / Agreement Summary | `agreement-summary` | Plain-English summary of legal agreement |

---

### CATEGORY: 🎓 Academic & Education

| # | Name | Slug | Description |
|---|------|------|-------------|
| 114 | Essay Writer | `essay-writer` | Well-structured academic essay |
| 115 | Thesis Statement | `thesis-statement` | Strong thesis statement for given topic |
| 116 | Research Paper Outline | `research-outline` | Structured academic research outline |
| 117 | Cover Letter | `cover-letter` | Professional job application cover letter |
| 118 | Resume Builder | `resume-builder` | ATS-friendly resume content |
| 119 | Application Letter | `application-letter` | Formal application letter |
| 120 | Scholarship Essay | `scholarship-essay` | Compelling scholarship application essay |
| 121 | Study Guide | `study-guide` | Concise study guide on any topic |
| 122 | Lesson Plan | `lesson-plan` | Structured lesson plan for educators |
| 123 | Quiz / Test Questions | `quiz-generator` | Multiple choice and short answer questions |
| 124 | Math Problem Solver | `math-solver` | Step-by-step math problem solutions |
| 125 | Science Explainer | `science-explainer` | Clear explanation of scientific concepts |
| 126 | Historical Summary | `history-summary` | Summary of historical events or periods |
| 127 | Book Summary | `book-summary` | Key insights and summary of any book |
| 128 | Bullet Point Answers | `bullet-answers` | Concise bullet-point answers to questions |
| 129 | Citation Generator | `citation-generator` | APA/MLA/Chicago citation formatting |

---

### CATEGORY: 💻 Development & Tech

| # | Name | Slug | Description |
|---|------|------|-------------|
| 130 | Code Generator | `code-generator` | Generate code in any programming language |
| 131 | Code Explainer | `code-explainer` | Plain-English explanation of code snippets |
| 132 | Bug Finder & Fixer | `bug-fixer` | Identify and fix bugs in code |
| 133 | Code Optimizer | `code-optimizer` | Optimize code for performance |
| 134 | Code Refactorer | `code-refactorer` | Refactor code for readability/maintainability |
| 135 | Unit Test Generator | `unit-test` | Generate unit tests for given code |
| 136 | API Documentation | `api-docs` | Generate API documentation from code/spec |
| 137 | Code Review | `code-review` | Professional code review with suggestions |
| 138 | Regex Generator | `regex-generator` | Regular expression for given pattern |
| 139 | Database Schema | `db-schema` | SQL schema design for described system |
| 140 | Git Commit Message | `git-commit` | Conventional Git commit message |
| 141 | Changelog Creator | `changelog-creator` | Formatted changelog from update description |
| 142 | Teach Code | `teach-code` | Educational coding tutorial on any concept |
| 143 | Tech Stack Advisor | `tech-stack` | Recommend tech stack for a project |
| 144 | DevOps Script | `devops-script` | Shell/bash/Docker scripts for common tasks |
| 145 | Error Message Explainer | `error-explainer` | Plain-English error message explanation |

---

### CATEGORY: 🌐 Website & SEO

| # | Name | Slug | Description |
|---|------|------|-------------|
| 146 | SEO Blog Post | `seo-blog` | SEO-optimized blog article |
| 147 | Meta Title & Description | `meta-seo` | SEO meta title and description |
| 148 | Keyword Generator | `keyword-generator` | Relevant keywords for any topic/niche |
| 149 | Keyword Extractor | `keyword-extractor` | Extract keywords from existing content |
| 150 | Rewrite with Keywords | `keyword-rewrite` | Rewrite content with target keywords |
| 151 | Website Tagline | `website-tagline` | Catchy tagline for a website/brand |
| 152 | Website Copywriting | `website-copy` | Full website page copy (any page type) |
| 153 | Landing Page Copy | `landing-page-copy` | High-conversion landing page copy |
| 154 | Call to Action | `call-to-action` | Compelling CTAs for websites |
| 155 | FAQ Generator | `faq-generator` | FAQ section for any product/service |
| 156 | Services Page | `services-page` | Services section copy |
| 157 | Features Page | `features-page` | Product features page copy |
| 158 | Testimonials Generator | `testimonials` | Authentic-sounding testimonials |
| 159 | Sitemap Generator | `sitemap-generator` | Website sitemap structure |
| 160 | UX Copy / Microcopy | `ux-copy` | UI labels, tooltips, onboarding microcopy |
| 161 | Privacy Policy | `privacy-policy` | GDPR-compliant privacy policy |
| 162 | Terms & Conditions | `terms-conditions` | Terms of service document |
| 163 | Schema Markup | `schema-markup` | JSON-LD structured data for SEO |
| 164 | Internal Linking Strategy | `internal-links` | Internal linking plan for a website |

---

### CATEGORY: 🎨 Creative Writing

| # | Name | Slug | Description |
|---|------|------|-------------|
| 165 | Story Generator | `story-generator` | Complete short story from prompt |
| 166 | Story Outline | `story-outline` | Plot outline for novels/stories |
| 167 | Character Creator | `character-creator` | Detailed fictional character profile |
| 168 | Dialogue Writer | `dialogue-writer` | Natural dialogue between characters |
| 169 | Song Lyrics | `song-lyrics` | Original song lyrics for any genre |
| 170 | Poem Writer | `poem-writer` | Poetry in any style (haiku, sonnet, free verse) |
| 171 | Joke Generator | `joke-generator` | Clean jokes for any audience |
| 172 | Riddle Creator | `riddle-creator` | Creative riddles with answers |
| 173 | Storytelling Post | `storytelling-post` | Narrative storytelling for social/blog |
| 174 | Children's Story | `childrens-story` | Age-appropriate children's story |
| 175 | Motivational Quote | `motivational-quote` | Original motivational quotes |
| 176 | Caption for Art/Photo | `art-caption` | Descriptive captions for artwork or photos |
| 177 | Script Writer | `script-writer` | Short film or stage play script |
| 178 | Viral Content Ideas | `viral-ideas` | Ideas designed to spread virally |

---

### CATEGORY: 🧑‍💼 Personal & Career

| # | Name | Slug | Description |
|---|------|------|-------------|
| 179 | Personal Bio | `personal-bio` | Compelling personal biography |
| 180 | Career Advice | `career-advice` | Personalized career guidance |
| 181 | LinkedIn Recommendation | `linkedin-recommendation` | Professional LinkedIn recommendation letter |
| 182 | Performance Self-Review | `self-review` | Self-evaluation for performance reviews |
| 183 | Resignation Letter | `resignation-letter` | Professional resignation letter |
| 184 | Complaint Letter | `complaint-letter` | Formal complaint letter |
| 185 | Reference Letter | `reference-letter` | Professional reference/recommendation letter |
| 186 | Networking Message | `networking-message` | Professional networking outreach message |
| 187 | Salary Negotiation Script | `salary-negotiation` | Script for salary negotiation conversation |
| 188 | Personal Statement | `personal-statement` | Personal statement for applications |

---

### CATEGORY: 🏥 Health & Fitness

| # | Name | Slug | Description |
|---|------|------|-------------|
| 189 | Workout Plan | `workout-plan` | Personalized workout plan |
| 190 | Meal Plan | `meal-plan` | Custom meal plan based on dietary goals |
| 191 | Recipe Generator | `recipe-generator` | Recipe from ingredients or dietary preference |
| 192 | Fitness Goal Planner | `fitness-goals` | Goal-setting plan for fitness objectives |
| 193 | Mental Health Tips | `mental-health-tips` | Evidence-based mental wellness tips |
| 194 | Supplement Guide | `supplement-guide` | Informational supplement guide |
| 195 | Sleep Improvement Plan | `sleep-plan` | Tips and plan for better sleep quality |

---

### CATEGORY: 🏠 Real Estate

| # | Name | Slug | Description |
|---|------|------|-------------|
| 196 | Property Listing | `property-listing` | Compelling real estate listing description |
| 197 | Property Description | `property-description` | Detailed property description |
| 198 | Real Estate Email | `real-estate-email` | Outreach email for real estate |
| 199 | Neighborhood Guide | `neighborhood-guide` | Guide to a local neighborhood/area |
| 200 | Property Investment Analysis | `investment-analysis` | Basic property investment analysis |

---

### CATEGORY: 🎮 Entertainment & Fun

| # | Name | Slug | Description |
|---|------|------|-------------|
| 201 | Event Planner | `event-planner` | Detailed event itinerary and checklist |
| 202 | Travel Planner | `travel-planner` | Day-by-day travel itinerary |
| 203 | Gift Ideas Generator | `gift-ideas` | Personalized gift ideas for any occasion |
| 204 | Trivia Questions | `trivia-questions` | Fun trivia questions on any topic |
| 205 | Game Ideas | `game-ideas` | Creative game concepts and mechanics |
| 206 | Party Theme Ideas | `party-ideas` | Creative party theme suggestions |
| 207 | Movie/Show Recommendations | `recommendations` | Personalized entertainment recommendations |
| 208 | Bucket List Generator | `bucket-list` | Personalized bucket list ideas |

---

### CATEGORY: 🌍 Language & Translation

| # | Name | Slug | Description |
|---|------|------|-------------|
| 209 | Translator | `translator` | Translate text to any language |
| 210 | Grammar Checker | `grammar-checker` | Grammar and style correction |
| 211 | Synonym Finder | `synonym-finder` | Synonyms and alternative word choices |
| 212 | Vocabulary Builder | `vocabulary-builder` | Learn new words with context and usage |
| 213 | Tone Changer | `tone-changer` | Rewrite text in different tone |
| 214 | Formal to Casual | `formality-converter` | Convert formal text to casual or vice versa |
| 215 | Language Learning Exercise | `language-exercise` | Practice exercises for language learners |

---

### CATEGORY: 🎯 Marketing Strategy

| # | Name | Slug | Description |
|---|------|------|-------------|
| 216 | Brand Voice Guide | `brand-voice-guide` | Brand voice and tone documentation |
| 217 | Competitor Analysis | `competitor-analysis` | Structured competitor analysis |
| 218 | Target Audience Profile | `audience-profile` | Detailed buyer persona creation |
| 219 | Value Proposition | `value-proposition` | Clear and compelling value proposition |
| 220 | Positioning Statement | `positioning-statement` | Brand positioning statement |
| 221 | Go-to-Market Strategy | `gtm-strategy` | Launch strategy for new product/service |
| 222 | Content Strategy | `content-strategy` | 3/6/12-month content strategy plan |
| 223 | Influencer Outreach | `influencer-outreach` | Outreach message for influencer collaboration |
| 224 | Product Launch Copy | `product-launch` | Full product launch campaign copy |
| 225 | Case Study | `case-study` | Customer success case study |
| 226 | Testimonial Request | `testimonial-request` | Email/message asking for testimonials |
| 227 | Referral Program Copy | `referral-program` | Referral program description and incentives |
| 228 | Viral Marketing Ideas | `viral-marketing` | Ideas for viral marketing campaigns |

---

### CATEGORY: 🤝 Customer Support

| # | Name | Slug | Description |
|---|------|------|-------------|
| 229 | Support Ticket Reply | `ticket-reply` | Professional customer support response |
| 230 | Live Chat Response | `chat-response` | Quick live chat support messages |
| 231 | Refund/Cancellation Response | `refund-response` | Empathetic refund/cancellation reply |
| 232 | Escalation Response | `escalation-response` | De-escalation for angry customers |
| 233 | Knowledge Base Article | `kb-article` | Help center article on any topic |
| 234 | Onboarding Guide | `onboarding-guide` | User onboarding guide/checklist |
| 235 | Feature Announcement | `feature-announcement` | In-app or email feature announcement |
| 236 | Chatbot Script | `chatbot-script` | Conversation flow for customer service bot |

---

### CATEGORY: ⚖️ Legal & Finance

| # | Name | Slug | Description |
|---|------|------|-------------|
| 237 | Contract Summary | `contract-summary` | Plain-English summary of contracts |
| 238 | Invoice Template | `invoice-template` | Professional invoice content |
| 239 | Financial Report Summary | `financial-summary` | Summary of financial reports/data |
| 240 | Grant Proposal | `grant-proposal` | Nonprofit/research grant proposal |
| 241 | Disclaimer Generator | `disclaimer` | Legal disclaimer for websites/apps |
| 242 | Cookie Policy | `cookie-policy` | GDPR cookie policy content |
| 243 | Fundraising Pitch | `fundraising-pitch` | Compelling fundraising pitch copy |

---

### CATEGORY: 🏗️ Miscellaneous / Productivity

| # | Name | Slug | Description |
|---|------|------|-------------|
| 244 | Pros & Cons List | `pros-cons` | Balanced pros and cons analysis |
| 245 | Action Plan | `action-plan` | Step-by-step action plan for any goal |
| 246 | Decision Matrix | `decision-matrix` | Structured decision-making framework |
| 247 | Problem Statement | `problem-statement` | Clear problem statement definition |
| 248 | SMART Goals | `smart-goals` | SMART goal framework for any objective |
| 249 | Prompt Generator | `prompt-generator` | Meta: generate AI prompts for any purpose |
| 250 | Daily Planner | `daily-planner` | Structured daily schedule/plan |
| 251 | Newsletter Subject Lines | `newsletter-subjects` | High open-rate email subject lines |
| 252 | Announcement Copy | `announcement-copy` | General announcement for any purpose |
| 253 | Thank You Note | `thank-you-note` | Warm personal or professional thank you |
| 254 | Instructions / How-To | `how-to-guide` | Step-by-step instructional guide |
| 255 | Comparison Article | `comparison-article` | Detailed comparison between two topics |

---

### Tool Admin Management

Admin → AI Tools → Tools:
- List all Tools with category filter, active/inactive toggle, featured toggle
- Edit any template: name, description, system prompt, user prompt, fields, model override
- Create custom Tool (admin-created, same structure)
- Duplicate existing template as starting point
- Reorder within category via drag-and-drop
- Bulk enable/disable by category
- Import/export Tools as JSON (for sharing between installs)
- Usage statistics per template (total uses, uses today, uses this month)
- Most popular Tools widget on admin dashboard

Admin → AI Tools → Categories:
- Create/edit/delete categories
- Each category: name, slug, icon, color, description, sort order, is_active
- Assign `requires_pro` at category level (all tools in that category become pro-only)

### User-side Tool UX

- `/ai-tools` — grid of all active tools, filterable by category, searchable
- Featured Tools shown first (configurable from admin)
- Each tool card: icon, name, short description, category badge, "Try it" button
- Recently used tools section on user dashboard (last 6)
- Favorite tools (bookmark) per user
- Usage count shown on card (social proof): "Used 12,483 times"
- New badge on Tools created within last 30 days

---

## PART 14 — AI TOOLS, TEMPLATES & CATEGORIES ✅

---

### 14.0 CRITICAL CONCEPT — READ FIRST

> **This section is the single source of truth for how AI tools, templates, and categories work together. Every agent/developer must read this before touching any related code.**

---

### 14.1 Terminology — What Each Word Means

These terms have distinct meanings. They are NOT interchangeable:

| Term | What it is | Who creates it | Where stored |
|------|-----------|---------------|-------------|
| **Category** | A grouping label for AI tools (e.g. "Blog & Content") | Admin (full CRUD) | `categories` table |
| **AI Tool** | A single-task AI generator (e.g. "Blog Article Generator") | Developer (seeded) + Admin (can create more) | `ai_tools` table |
| **Site Template** | A full pre-built page experience (e.g. "Social Media Manager") | Developer only (seeded) — admin can EDIT, NOT create | `site_templates` table |
| **Generation** | The AI output produced when a user runs a tool | System (auto) | `documents` + `ai_usage_logs` |

**Critical naming rules — memorize these:**
- **"Tool"** = single AI task. Table: `ai_tools`. URL: `/tools/{slug}`
- **"Template"** = full page experience bundling multiple tools. Table: `site_templates`. URL: `/app/{slug}`
- **Never** use "template" to mean an AI tool — they are completely different things
- The old table name `ai_templates` has been permanently renamed to `ai_tools`

---

### 14.2 Categories — Fully Dynamic, Admin-Controlled ✅

**RULE: Categories are NEVER hardcoded in PHP or Vue files.**

Categories are database rows. Admin can create, edit, delete, rename, reorder, and change icons at any time. The frontend always reads from the database — it never has a hardcoded list of category names.

**`categories` table (type = 'ai_tool'):**
```sql
id
name          varchar(255)        -- "Blog & Content" (admin can rename anytime)
slug          varchar(255) UNIQUE -- "blog-content" (auto-generated, used in URLs)
description   text NULL
icon          varchar(100) NULL   -- Tabler icon class: "ti-file-text"
color         varchar(20) NULL    -- hex: "#10b981" (used for card accent)
parent_id     bigint NULL FK      -- for subcategories (max 2 levels)
type          enum                -- 'ai_tool' | 'blog' | 'general'
sort_order    int DEFAULT 0       -- admin drag-to-reorder
is_active     boolean DEFAULT true
meta_title    varchar(255) NULL
meta_description text NULL
created_at, updated_at
```

**What admin can do with categories:**
- Create any number of new categories
- Rename existing categories (slug stays stable to avoid broken URLs)
- Change icon and color per category
- Reorder categories via drag-and-drop
- Deactivate a category (hides it + all its tools from users)
- Delete a category (tools become "uncategorized" — not deleted)
- Create subcategories (parent_id set)

**What admin CANNOT break:**
- Deleting a category does NOT delete its tools — they move to uncategorized
- Renaming a category does NOT change tool slugs

**Predefined categories seeded on install (admin can edit these but cannot delete if `is_system = true`):**

| # | Name | Slug | Icon | Color |
|---|------|------|------|-------|
| 1 | Blog & Content | blog-content | ti-file-text | #10b981 |
| 2 | Social Media | social-media | ti-brand-instagram | #ec4899 |
| 3 | Advertising | advertising | ti-speakerphone | #f59e0b |
| 4 | Email Marketing | email-marketing | ti-mail | #3b82f6 |
| 5 | Ecommerce | ecommerce | ti-shopping-cart | #8b5cf6 |
| 6 | Business | business | ti-briefcase | #6366f1 |
| 7 | Academic | academic | ti-school | #14b8a6 |
| 8 | Development & Tech | development | ti-code | #f97316 |
| 9 | Website & SEO | website-seo | ti-world | #06b6d4 |
| 10 | Creative Writing | creative-writing | ti-pencil | #a855f7 |
| 11 | Personal & Career | personal-career | ti-user | #64748b |
| 12 | Health & Fitness | health-fitness | ti-heart | #ef4444 |
| 13 | Real Estate | real-estate | ti-building | #78716c |
| 14 | Entertainment | entertainment | ti-device-gamepad | #84cc16 |
| 15 | Language | language | ti-language | #0ea5e9 |
| 16 | Marketing Strategy | marketing | ti-chart-arrows | #d946ef |
| 17 | Customer Support | customer-support | ti-headset | #22c55e |
| 18 | Legal & Finance | legal-finance | ti-scale | #94a3b8 |
| 19 | Productivity | productivity | ti-checklist | #fb923c |

**In code — always load categories from DB:**
```php
// ✅ CORRECT — dynamic, reads from DB
$categories = Category::where('type', 'ai_tool')
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->get();

// ❌ WRONG — hardcoded, breaks when admin renames
$categories = ['Blog & Content', 'Social Media', 'Advertising'];
```

```typescript
// ✅ CORRECT — Vue reads from Inertia props (passed from DB)
const { categories } = usePage().props

// ❌ WRONG — hardcoded array in Vue
const categories = ['blog-content', 'social-media']
```

---

### 14.3 Tools  — Structure ✅

Each row in `ai_tools` is one AI tool. Here is the complete field reference:

```sql
ai_tools
  -- Identity
  id
  ulid            char(26) UNIQUE              -- public-facing ID
  slug            varchar(100) UNIQUE          -- URL: /ai-tools/blog-article
  name            varchar(255)                 -- "Blog Article Generator"
  description     text                         -- shown on tool card + page
  icon            varchar(100)                 -- Tabler icon: "ti-file-text"
  color           varchar(20)                  -- hex accent color for icon bg

  -- Category (FK — NOT hardcoded)
  category_id     bigint FK → categories.id   -- ALWAYS a FK, never a string

  -- AI Behavior
  prompt_system   text                         -- system prompt (admin-editable)
  prompt_user     text                         -- user prompt template with {placeholders}
  fields          json                         -- input field definitions (see 14.4)
  output_type     enum                         -- text|markdown|html|code|list|image|audio|video
  model_override  varchar(100) NULL            -- null = user picks model
  max_tokens_override int NULL                 -- null = global setting
  temperature     decimal(3,2) DEFAULT 0.70    -- creativity (0.0–1.0)
  supports_brand_voice boolean DEFAULT true    -- inject user's brand voice

  -- Access Control
  is_active       boolean DEFAULT true         -- admin can disable per tool
  is_featured     boolean DEFAULT false        -- shown first in listing
  is_system       boolean DEFAULT true         -- seeded tools (false = admin-created)
  requires_pro    boolean DEFAULT false        -- needs paid plan
  access_level    enum                         -- inherit|public|login_required|free_plan|pro_plan

  -- Page Content Sections (all toggleable per-tool)
  about_content       longtext NULL            -- rich HTML about the tool
  how_it_works        json NULL                -- [{step, icon, title, description}]
  usage_examples      json NULL                -- [{title, input, output}]
  faq_items           json NULL                -- [{question, answer}]
  show_about          boolean DEFAULT true
  show_how_it_works   boolean DEFAULT true
  show_usage_examples boolean DEFAULT true
  show_faqs           boolean DEFAULT true
  show_reviews        boolean DEFAULT true

  -- SEO
  meta_title          varchar(255) NULL        -- null = auto-generated
  meta_description    text NULL                -- null = auto-generated

  -- Stats (auto-maintained by system)
  usage_count         bigint DEFAULT 0         -- incremented via low queue
  avg_rating          decimal(3,2) DEFAULT 0
  review_count        int DEFAULT 0
  avg_output_tokens   int DEFAULT 400          -- used for credit estimate
  avg_latency_ms      int NULL                 -- for slow-tool detection

  sort_order          int DEFAULT 0
  created_at, updated_at
```

---

### 14.4 Tool Fields JSON — Complete Reference ✅

The `fields` column defines what input form the user sees. It is a JSON array.

**Every supported field type:**

```json
[
  {
    "name": "topic",
    "type": "text",
    "label": "Topic",
    "placeholder": "e.g. Laravel best practices",
    "required": true,
    "max_length": 200
  },
  {
    "name": "description",
    "type": "textarea",
    "label": "Description",
    "placeholder": "Describe your product...",
    "required": false,
    "rows": 4,
    "max_length": 1000
  },
  {
    "name": "tone",
    "type": "tone_select",
    "label": "Tone of Voice",
    "required": false,
    "default": "Professional",
    "options": ["Professional","Friendly","Casual","Formal","Humorous","Persuasive","Inspirational","Empathetic"]
  },
  {
    "name": "language",
    "type": "language_select",
    "label": "Output Language",
    "required": false,
    "default": "English"
  },
  {
    "name": "length",
    "type": "length_select",
    "label": "Output Length",
    "required": false,
    "default": "Medium",
    "options": [
      {"label": "Short (~100 words)",     "value": "short"},
      {"label": "Medium (~300 words)",    "value": "medium"},
      {"label": "Long (~600 words)",      "value": "long"},
      {"label": "Very Long (~1200 words)","value": "very_long"}
    ]
  },
  {
    "name": "count",
    "type": "number",
    "label": "Number of results",
    "min": 1, "max": 20, "default": 5,
    "required": false
  },
  {
    "name": "creativity",
    "type": "slider",
    "label": "Creativity",
    "min": 0, "max": 1, "step": 0.1, "default": 0.7,
    "required": false
  },
  {
    "name": "format",
    "type": "select",
    "label": "Output Format",
    "options": ["Bullet Points","Numbered List","Paragraph"],
    "default": "Bullet Points",
    "required": false
  },
  {
    "name": "include_emoji",
    "type": "toggle",
    "label": "Include Emoji",
    "default": false
  },
  {
    "name": "keywords",
    "type": "tags_input",
    "label": "Target Keywords",
    "placeholder": "Type keyword and press Enter",
    "required": false
  },
  {
    "name": "audience",
    "type": "text",
    "label": "Target Audience",
    "placeholder": "e.g. Small business owners",
    "required": false
  },
  {
    "name": "model",
    "type": "model_select",
    "label": "AI Model",
    "required": false
  },
  {
    "name": "image",
    "type": "image_upload",
    "label": "Reference Image",
    "required": false,
    "accept": "image/*",
    "max_size_mb": 5
  },
  {
    "name": "document",
    "type": "file_upload",
    "label": "Upload Document",
    "required": false,
    "accept": ".pdf,.docx,.txt",
    "max_size_mb": 10
  },
  {
    "name": "code_input",
    "type": "code_input",
    "label": "Your Code",
    "language": "php",
    "required": true
  },
  {
    "name": "url",
    "type": "url",
    "label": "Website URL",
    "placeholder": "https://example.com",
    "required": false
  }
]
```

**How `DynamicForm.vue` renders this:**
- Reads `fields` array from tool API response
- Renders each item as the correct input component
- `tone_select` → pre-built dropdown, options not in JSON (hardcoded in component)
- `language_select` → dropdown loaded from `$page.props.languages` (from DB)
- `model_select` → dropdown loaded from enabled AI models (from DB)
- All other types → standard form elements

---

### 14.5 How Categories and Tools Connect ✅

```
categories (type='ai_tool')
  id: 1, name: "Blog & Content", slug: "blog-content", ...
  id: 2, name: "Social Media",   slug: "social-media", ...

ai_tools
  id: 1, slug: "blog-article",      category_id: 1, name: "Blog Article Generator"
  id: 2, slug: "blog-intro",        category_id: 1, name: "Blog Intro Writer"
  id: 3, slug: "instagram-caption", category_id: 2, name: "Instagram Caption"
  id: 4, slug: "twitter-tweet",     category_id: 2, name: "Tweet Writer"
```

**One tool belongs to exactly ONE category** (via `category_id` FK).

**Routes:**
```
/ai-tools                          → all tools grid (filterable by category)
/ai-tools/category/blog-content    → tools in "Blog & Content" category
/ai-tools/blog-article             → specific tool page
```

**API:**
```
GET /api/v1/tools                  → all active tools (with category eager-loaded)
GET /api/v1/tools/categories       → all active categories
GET /api/v1/tools/{slug}           → single tool detail
GET /api/v1/tools?category=blog-content → filter by category slug
```

---

### 14.6 Admin Panel — Tool & Category Management ✅

**Admin → AI Tools → Categories:**
- Full CRUD for categories
- Drag-to-reorder (`sort_order` updated)
- Icon picker (Tabler icon search)
- Color picker (hex)
- Deactivate toggle (hides category + all its tools)
- Cannot delete a category if it has tools — must reassign tools first (or bulk move)
- System categories (seeded) can be edited but have a lock icon indicating "predefined"

**Admin → AI Tools → Tools:**
- Lists all tools, grouped by category (with category filter)
- Each row: icon, name, category badge, output_type badge, access_level badge, usage count, active toggle
- Active/inactive toggle per tool (hides from users without deleting)
- Featured toggle (tool appears first in listing)
- Click to open full Tool editor (5 tabs: Basic, Prompts, Fields, Page Content, SEO)
- "+ New Tool" → admin can create custom tools with same structure as seeded ones
- Duplicate any existing tool as starting point
- Bulk actions: enable, disable, change category, change access_level, delete

**Key admin behaviors:**
- Disabling a tool hides it from users immediately (cached invalidated)
- Disabling a category hides ALL its tools immediately
- Tool `slug` is set once on creation and never auto-changes (stable URLs)

---

### 14.7 The 255 Predefined Tools — Summary by Category

> All 255 tools are seeded via `AiToolSeeder`. Each has a default `prompt_system`, `prompt_user`, and `fields` JSON. Admin can edit any of these after install.

| Category | Tools | Key tools |
|----------|-------|-----------|
| Blog & Content | 22 | blog-article, blog-outline, article-rewriter, tldr-summary, content-improver |
| Social Media | 27 | instagram-caption, twitter-thread, linkedin-post, tiktok-script, youtube-description |
| Advertising | 17 | google-ads-headline, facebook-ad, youtube-ads-script, aida, pas |
| Email Marketing | 15 | cold-email, follow-up-email, welcome-email, newsletter-content, sales-email |
| Ecommerce | 12 | product-description, amazon-listing, abandoned-cart-email, review-responder |
| Business | 20 | business-plan, swot-analysis, pitch-deck-script, meeting-minutes, okr-generator |
| Academic | 16 | essay-writer, cover-letter, resume-builder, lesson-plan, quiz-generator |
| Development | 16 | code-generator, bug-fixer, unit-test, api-docs, git-commit |
| Website & SEO | 19 | meta-seo, landing-page-copy, faq-generator, schema-markup, privacy-policy |
| Creative Writing | 14 | story-generator, song-lyrics, poem-writer, dialogue-writer, script-writer |
| Personal & Career | 10 | personal-bio, cover-letter, resignation-letter, salary-negotiation |
| Health & Fitness | 7 | workout-plan, meal-plan, recipe-generator, mental-health-tips |
| Real Estate | 5 | property-listing, neighborhood-guide, investment-analysis |
| Entertainment | 8 | travel-planner, gift-ideas, trivia-questions, event-planner |
| Language | 7 | translator, grammar-checker, tone-changer, synonym-finder |
| Marketing Strategy | 13 | brand-voice-guide, competitor-analysis, audience-profile, gtm-strategy |
| Customer Support | 8 | ticket-reply, kb-article, onboarding-guide, chatbot-script |
| Legal & Finance | 7 | contract-summary, privacy-policy, disclaimer, fundraising-pitch |
| Productivity | 12 | pros-cons, smart-goals, action-plan, how-to-guide, prompt-generator |

**Full slug list is in `database/seeders/AiToolSeeder.php` — 255 entries total.**

---

### 14.8 Caching Strategy for Categories and Tools ✅

Categories and tool lists are cached in Redis because they are read on every page load:

```php
// Cache keys
'makeai:categories:ai_tool'       → all active ai_tool categories, TTL forever
'makeai:tool:{slug}'              → single tool by slug, TTL 1h
'makeai:tool:list'                → all active tools (lightweight — no prompts), TTL 1h
'makeai:tool:list:category:{slug}'→ tools filtered by category slug, TTL 1h
```

**Cache invalidation:**
```php
// When any category is saved/deleted:
Cache::forget('makeai:categories:ai_tool');
Cache::forget("makeai:tool:list:category:{$category->slug}");

// When any tool is saved/deleted:
Cache::forget("makeai:tool:{$tool->slug}");
Cache::forget('makeai:tool:list');
Cache::tags(['tools'])->flush(); // if using tag-based cache
```

**In code — always use the cached service:**
```php
// ✅ CORRECT
$categories = AiCategoryService::getActive();   // reads from cache
$tool = AiToolService::getBySlug('blog-article'); // reads from cache

// ❌ WRONG — direct DB query on every request
$categories = Category::where('type','ai_tool')->where('is_active',true)->get();
```

---

### 14.9 Access Control Per Tool ✅

Each tool has an `access_level` field. Resolution order:

```
Tool's own access_level
  → if 'inherit': use global setting from settings('default_tool_access_level')
  → else: use tool's own value

Possible values:
  'public'         → anyone can use (guest + logged in), IP rate limited, output truncated
  'login_required' → must be logged in (free registered users)
  'free_plan'      → logged in + has account in good standing (credits available)
  'pro_plan'       → active paid subscription (only if isProAvailable())
  'inherit'        → fall back to global default setting
```

**`CheckCredits` middleware runs for ALL non-public tools:**
```php
// Middleware order on AI generation routes:
'auth'              → must be logged in (unless public)
'verified'          → email must be verified
'not.banned'        → not banned
'check.credits'     → has credits + within limits
'throttle.ai:text_gen' → rate limit check
```

**For `public` access tools (guest users):**
- No credit deduction
- Output truncated to `settings('public_tool_max_words', 200)` words
- IP rate limited: `settings('public_tool_rate_limit', '5 per hour')`
- "Sign up to get full output" upsell shown after truncated result
- Result NOT saved to document library

---

### 14.10 Tool Page URL & Routing ✅

All 255 tools use the **same Vue page**: `resources/js/Pages/AI/ToolPage.vue`.
There is **NO separate Vue file per tool** — the page dynamically renders based on tool data from the controller.

#### 14.10.1 Frontend Routes (web.php)

```php
// ─── AI Tools (frontend) ─────────────────────
Route::get('/ai-tools', [TemplateController::class, 'index'])->name('ai.tools.index');
Route::get('/ai-tools/category/{slug}', [TemplateController::class, 'category'])->name('ai.tools.category');
Route::get('/ai-tools/{slug}', [TemplateController::class, 'show'])->name('ai.tools.show');
```

> **Controller:** `App\Http\Controllers\AI\TemplateController` — handles Tool catalog, single tool page, and category filtering.
> **Vue route names:** `ai.tools.index`, `ai.tools.category`, `ai.tools.show` — referenced in `ToolPage.vue`, `Templates.vue`, `CategoryPage.vue`, and navigation components.

#### 14.10.2 API Routes (api.php) ✅

```php
// ─── Public Tool Catalog ─────
Route::prefix('v1')->group(function () {
    Route::prefix('tools')->group(function () {
        Route::get('/', [ToolCatalogController::class, 'index']);
        Route::get('categories', [ToolCatalogController::class, 'categories']);
        Route::get('{slug}', [ToolCatalogController::class, 'show']);
        Route::get('{slug}/reviews', [ToolReviewController::class, 'index']);
    });

    // ─── Generation Endpoints ─────
    Route::middleware(['throttle:text_gen', 'check.credits'])->prefix('generate')->group(function () {
        Route::post('stream', [GenerateController::class, 'stream']);    // SSE streaming
        Route::post('text', [GenerateController::class, 'text']);        // Sync JSON
    });
    Route::middleware('throttle:text_gen')->prefix('generate')->group(function () {
        Route::get('estimate', [GenerateController::class, 'estimate']); // Cost estimate
    });

    // ─── Tool Review Mutations (auth required) ─────
    Route::middleware('auth:sanctum')->prefix('tools')->group(function () {
        Route::post('{slug}/reviews', [ToolReviewController::class, 'store']);
        Route::post('reviews/{review}/vote', [ToolReviewController::class, 'vote']);
    });
});
```

#### 14.10.3 Admin Routes (admin.php) ✅

```php
// ─── AI Tools CRUD ───
Route::prefix('ai')->name('admin.ai.')->middleware('admin.permission:ai.tools')->group(function () {
    Route::resource('tools', AiToolController::class);
    Route::post('/tools/{tool}/toggle', [AiToolController::class, 'toggle'])->name('tools.toggle');
    Route::post('/tools/reviews/{review}/action', [AiToolController::class, 'reviewAction'])->name('tools.reviews.action');
    Route::resource('categories', AiToolCategoryController::class)->except(['create', 'show', 'edit']);
    Route::post('/categories/{category}/toggle-active', [AiToolCategoryController::class, 'toggleActive'])->name('categories.toggle-active');
    Route::post('/categories/{category}/toggle-pro', [AiToolCategoryController::class, 'togglePro'])->name('categories.toggle-pro');
    Route::post('/categories/{category}/toggle-login', [AiToolCategoryController::class, 'toggleLogin'])->name('categories.toggle-login');
});
```

#### 14.10.4 TemplateController::show() — Real Implementation ✅

```php
// App\Http\Controllers\AI\TemplateController
public function show(string $slug)
{
    $toolData = $this->toolCatalog->toolBySlug($slug);    // ToolCatalogCacheService (cached)

    $tool = AiTool::find($toolData['id']);

    if (! $tool || ! $tool->is_active || ! $tool->category?->is_active) {
        abort(404);
    }

    // Admin preview?slug={slug}&preview=1 bypasses is_active check
    if (request()->query('preview') === '1' && auth('admin')->check()) {
        $tool->is_active = true;
        $tool->setRelation('category', $tool->category()->withTrashed()->first());
    }

    // Access control redirects (non-public tools)
    if ($this->toolAccess->requiresAuth($tool) && ! auth()->check()) {
        return redirect()->route('login')->with('error', translate('You must be logged in to access this tool.'));
    }
    if ($tool->isProRequired() && ! auth()->user()?->isPro()) {
        return redirect()->back()->with('error', translate('This tool requires a Pro plan.'));
    }

    $seo = $this->seoService->getMeta($tool);             // ToolSeoService
    $schemas = $this->seoService->getSchemas($tool);       // 4 Schema.org types

    $relatedTools = $toolData['show_related_tools'] ?? false
        ? $tool->relatedTools(3)->map->only(['name', 'slug', 'description', 'icon', 'color', 'avg_rating'])
        : [];

    // Reviews with pagination + stats distribution
    $reviews = ($toolData['show_reviews'] ?? false)
        ? $tool->approvedReviews()->with('user:id,name,avatar')
            ->orderByDesc('helpful_count')->orderByDesc('created_at')->paginate(10)
        : null;

    // Credit cost estimation (shown in UI before generation)
    $estimatedCredits = null;
    $showCreditCosts = (bool) settings('show_tool_credit_costs', true);
    if ($showCreditCosts && auth()->check()) {
        $estimatedCredits = app(PromptBuilder::class)->estimateCost($tool, $model);
    }

    return Inertia::render('AI/ToolPage', [
        'tool'       => $toolData,           // cached array (prompts NOT included — security)
        'seo'        => $seo,
        'schemas'    => $schemas,
        'relatedTools' => $relatedTools,
        'reviews'       => $reviews?->items() ?? [],
        'reviewsPagination' => $reviews ? [...],
        'reviewStats'  => $this->reviewStats($tool),
        'estimatedCredits' => $estimatedCredits,
        'showCreditCosts'  => $showCreditCosts,
        'languages'   => Language::active()->orderByDesc('is_default')->orderBy('name')->get(),
        'models'      => AiModel::active()->ofType('chat')->get(),
        'authUser'    => auth()->user()?->only('id', 'name', 'credits'),
        'canReview'   => auth()->check() && /* user has completed a generation for this tool */,
    ]);
}
```

#### 14.10.5 ToolPage.vue — Page Sections & URL Tabs ✅

The tool page has **7 tabbed sections** below the generator form, each togglable per-tool from admin:

| Tab | Key | Visible When | Schema Generated |
|-----|-----|-------------|------------------|
| About | `show_about` + `about_content` not empty | ✓ | — |
| How It Works | `show_how_it_works` + steps exist | ✓ | `HowTo` schema |
| Examples | `show_usage_examples` + examples exist | ✓ | — |
| FAQs | `show_faqs` + items exist | ✓ | `FAQPage` schema |
| Reviews | `show_reviews` (with pagination, sort, stats, helpful votes) | ✓ | `SoftwareApplication.aggregateRating` |
| Related | `show_related_tools` + tools exist | ✓ | — |

**Tab state in URL:** The `activeTab` is synced with `?tab={key}` query string for shareable deep links (e.g. `/ai-tools/blog-article?tab=reviews`).

#### 14.10.6 SEO & Schema.org

Generated by `App\Services\AI\ToolSeoService`:

1. **Meta tags** — title (tool meta or auto-generated), description, keywords, canonical URL, OG tags, Twitter Card
2. **SoftwareApplication schema** — always present, includes aggregate rating when `review_count >= 5`
3. **FAQPage schema** — auto-generated from `faq_items` JSON when `show_faqs = true`
4. **HowTo schema** — auto-generated from `how_it_works` JSON when `show_how_it_works = true`
5. **BreadcrumbList schema** — always present (Home > AI Tools > [Category] > Tool Name)

#### 14.10.7 View Tracking

Tool page views are tracked via Redis counter (`tool_views:{slug}`), incremented on every visit.
Flushed to the `tool_page_views` table hourly via `scheduler:tool-views-flush` → aggregates `views_count` on `ai_tools`.

#### 14.10.8 Slug Stability & 301 Redirects

- Tool `slug` is set once on creation and **never auto-changes** after creation — stable URLs
- If admin manually changes a slug, the old slug is stored in `tool_slug_history` table
- A middleware catches requests to old slugs and issues **301 Permanent Redirect** to the new URL
- Cache keys use the slug: `makeai:tool:{slug}`, `makeai:tool_related:{slug}`

#### 14.10.9 Admin Preview Mode

Admins can preview inactive tools by adding `?preview=1` to the URL:
- `/ai-tools/{slug}?preview=1` — bypasses `is_active` and `category.is_active` checks
- Only works when authenticated as admin
- Tool remains invisible to regular users and search engines

#### 14.10.10 Sitemap

Auto-generated at `/sitemap.xml` — includes all active tool URLs, category pages, blog posts, and static pages.
Generated on-demand with 24h cache, invalidated on tool/category create/update/delete.

**CRITICAL: `prompt_system` and `prompt_user` are NEVER sent to the frontend.**
They are server-side only. The frontend only receives the `fields` array and display metadata.

---

### 14.11 Checklist — Categories & Tools ✅

- [ ] `category_id` is always a FK integer — never a hardcoded string category name in `ai_tools`
- [ ] Frontend never has a hardcoded array of category names — always reads from DB via API/props
- [ ] Categories page in admin: create, edit, rename, reorder, deactivate all work
- [ ] Deactivating a category hides ALL its tools from users immediately (cache cleared)
- [ ] Deleting a category moves tools to uncategorized — does NOT delete tools
- [ ] Tool `slug` never auto-changes after creation — stable URLs
- [ ] All 255 tools seeded with correct `category_id` (FK, not name string)
- [ ] `DynamicForm.vue` renders all 16 field types from `fields` JSON correctly
- [ ] `tone_select` options hardcoded in Vue component (not in DB) — 8 tone options
- [ ] `language_select` loaded from `$page.props.languages` (DB-driven)
- [ ] `model_select` loaded from enabled AI models (DB-driven)
- [ ] `prompt_system` and `prompt_user` never sent to frontend (server-side only)
- [ ] Cache invalidated when category or tool saved/deleted
- [ ] Admin can create new tools with same structure as seeded ones (`is_system = false`)
- [ ] Admin-created tools can be deleted; seeded tools (`is_system = true`) cannot
- [ ] Tool page (ToolPage.vue) is ONE component handling ALL 255 tools — no per-tool files
- [ ] `access_level = 'inherit'` correctly falls back to global `settings('default_tool_access_level')`
- [ ] Public tools: output truncated, IP rate limited, upsell shown, NOT saved to documents

---


## PART 14B — SITE TEMPLATES

> Site Templates are full pre-built page experiences. They are completely different from AI Tools.
> Admin can EDIT existing templates but CANNOT create new ones.
> Only developers add new templates by seeding or deploying new code.

---

### 14B.1 What Is a Site Template? ✅

A Site Template is a **complete page experience** — it has its own layout, color scheme, navigation, and bundles a curated set of AI tools together under a specific use case.

**Examples of predefined site templates:**

| Slug | Name | Description | Bundled tools (examples) |
|------|------|-------------|--------------------------|
| `default` | Default Homepage | The standard homepage with section builder | — |
| `social-media-manager` | Social Media Manager | Dashboard for social content creation | instagram-caption, twitter-thread, linkedin-post, tiktok-script, hashtag-strategy, content-calendar |
| `marketing-suite` | Marketing Suite | End-to-end marketing content wizard | landing-page-copy, facebook-ad, google-ads-headline, email-generator, value-proposition, competitor-analysis |
| `content-studio` | Content Studio | Editorial content creation hub | blog-article, blog-outline, article-rewriter, content-improver, seo-blog, meta-seo |
| `ecommerce-toolkit` | eCommerce Toolkit | Product and store content tools | product-description, amazon-listing, review-responder, abandoned-cart-email, upsell-message, flash-sale-copy |
| `developer-assistant` | Developer Assistant | Code-focused dark-theme IDE experience | code-generator, bug-fixer, code-optimizer, unit-test, api-docs, git-commit |
| `ai-chatbot-builder` | AI Chatbot Builder | Conversational bot creation interface | chatbot-script, faq-generator, kb-article, onboarding-guide |
| `academic-writer` | Academic Writer | Student and researcher writing tools | essay-writer, thesis-statement, research-outline, citation-generator, study-guide |

**Key rules:**
- Templates are **seeded by developers** — not created via admin panel
- Admin can **edit** (colors, text, SEO, custom HTML) but **not create or delete** templates
- Each template can be set as the **homepage** via Admin → Settings → General
- Each template can be added to the **navigation** as a menu link
- Templates can be **enabled/disabled** per plan (free vs pro)

---

### 14B.2 `site_templates` Table

```sql
site_templates
  id
  slug              varchar(100) UNIQUE     -- 'social-media-manager' (never changes)
  name              varchar(255)            -- "Social Media Manager" (admin can edit)
  tagline           varchar(500) NULL       -- hero subtitle (admin can edit)
  description       text NULL               -- shown in admin template picker
  preview_image     varchar(500) NULL       -- screenshot in admin selector (1280×800px)
  icon              varchar(100) NULL       -- Tabler icon for menu/nav
  layout_component  varchar(100)            -- Vue component name: 'SocialMediaTemplate'
                                            -- references a file in resources/js/Templates/
  bundled_tool_slugs json                   -- ["instagram-caption","twitter-thread",...] ordered
  requires_pro      boolean DEFAULT false   -- only available on extended license

  -- Admin-editable appearance
  color_primary     varchar(20) NULL        -- override #hex (null = use global primary)
  color_secondary   varchar(20) NULL        -- override #hex (null = use global secondary)
  color_bg          varchar(20) NULL        -- page background override
  color_surface     varchar(20) NULL        -- card/panel background override
  color_text        varchar(20) NULL        -- body text override
  font_heading      varchar(100) NULL       -- Google Font name override (null = global)
  font_body         varchar(100) NULL       -- Google Font name override (null = global)

  -- Admin-editable content
  hero_headline     varchar(500) NULL       -- main heading on template landing
  hero_subheadline  text NULL               -- subtitle text
  hero_cta_text     varchar(100) NULL       -- "Get Started" button text
  hero_cta_url      varchar(500) NULL       -- null = registration/login
  hero_bg_image     varchar(500) NULL       -- uploaded background image
  custom_html_head  text NULL               -- injected in <head> (scripts, fonts)
  custom_html_body  text NULL               -- injected before </body>
  custom_css        text NULL               -- scoped CSS override for this template

  -- SEO (all admin-editable)
  meta_title        varchar(255) NULL
  meta_description  text NULL
  og_image          varchar(500) NULL

  -- Admin controls
  is_active         boolean DEFAULT true    -- false = hidden from all users + nav
  sort_order        int DEFAULT 0

  -- Timestamps
  created_at, updated_at
  -- NO deleted_at — templates cannot be deleted
```

---

### 14B.3 Admin Editor — What Admin Can Edit

**Admin → Appearance → Site Templates**

Admin sees a grid of all seeded templates with:
- Preview thumbnail
- Name + tagline
- Active/inactive toggle
- "Edit" button → opens editor
- NO "New Template" button — this is intentional by design

**Template editor tabs:**

**Tab 1 — Appearance**
- Primary color (color picker, null = inherit global)
- Secondary color (color picker)
- Background color (color picker)
- Surface/card color (color picker)
- Text color (color picker)
- Heading font (Google Font dropdown, null = inherit global)
- Body font (Google Font dropdown, null = inherit global)
- Live preview iframe (right panel, updates as user changes values)
- "Reset to defaults" button (clears all overrides → inherits global design system)

**Tab 2 — Content**
- Hero headline (text input, `{app_name}` variable supported)
- Hero subheadline (textarea)
- Hero CTA button text
- Hero CTA URL (null = goes to register/login)
- Hero background image (upload, max 2MB)
- Template name (shown in admin picker and nav)
- Template tagline

**Tab 3 — Custom Code**
- Custom CSS (CodeMirror, scoped to template — no global bleed)
- Custom HTML (head) — for analytics, third-party scripts
- Custom HTML (body end) — for chat widgets, pixels
- Warning banner: "Custom code can break the template. Test carefully."

**Tab 4 — SEO**
- Meta title (input + 60 char counter)
- Meta description (textarea + 160 char counter)
- OG image upload
- SERP snippet preview (live)
- Canonical URL (auto-set, read-only)
- No-index toggle

**Tab 5 — Tools** *(view only — admin cannot add/remove bundled tools)*
- Shows the list of tools bundled in this template
- "These tools are defined by the developer and cannot be changed here."
- Each tool: icon, name, category badge, active indicator
- If a bundled tool is disabled, it shows a warning: "This tool is disabled. Enable it in AI Tools to restore functionality."

---

### 14B.4 Homepage Setting

**Admin → Settings → General → Homepage**

```
Homepage: [ Default Landing Page ▾ ]
          ┌─────────────────────────────────┐
          │ ● Default Landing Page          │  ← uses homepage builder sections
          │ ○ Social Media Manager          │
          │ ○ Marketing Suite               │
          │ ○ Content Studio                │
          │ ○ eCommerce Toolkit             │
          │ ○ Developer Assistant           │
          │ ○ AI Chatbot Builder            │
          │ ○ Academic Writer               │
          └─────────────────────────────────┘
          Only active templates appear in this list.
          Pro-only templates shown with lock icon if !isProAvailable().
```

**Implementation:**
```php
// settings('homepage_template') returns slug: 'default' | 'social-media-manager' | etc.

// routes/web.php
Route::get('/', function () {
    $slug = settings('homepage_template', 'default');

    if ($slug === 'default') {
        return app(HomepageController::class)->show();
    }

    $template = SiteTemplate::where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

    return Inertia::render("Templates/{$template->layout_component}", [
        'template' => SiteTemplateResource::make($template),
        'tools'    => AiToolService::getBySlugList($template->bundled_tool_slugs),
    ]);
})->name('home');
```

---

### 14B.5 Template Navigation Integration

Admin can add any active template as a menu link in Menu Builder (Part 29):

```sql
menu_items
  type enum: 'url' | 'page' | 'blog_category' | 'ai_category' | 'route' | 'site_template'

-- When type = 'site_template':
  site_template_id  bigint NULL FK → site_templates.id
  -- label auto-filled from template.name but admin can override
```

Templates with `requires_pro = true` show a lock icon in the nav builder and are automatically hidden from the rendered menu when `!isProAvailable()`.

---

### 14B.6 Vue Template Components

Each site template has its own Vue component in:
```
resources/js/Templates/
  SocialMediaManagerTemplate.vue
  MarketingSuiteTemplate.vue
  ContentStudioTemplate.vue
  EcommerceToolkitTemplate.vue
  DeveloperAssistantTemplate.vue
  AiChatbotBuilderTemplate.vue
  AcademicWriterTemplate.vue
```

**All template components receive the same props:**
```typescript
interface TemplateProps {
  template: SiteTemplateResource  // colors, content, meta, custom code
  tools: AiToolResource[]         // the bundled tools for this template
}
```

**Template component responsibilities:**
- Apply `template.color_primary` etc. as CSS custom properties scoped to the component
- Render `template.hero_headline`, `template.hero_subheadline`, `template.hero_cta_text`
- Show the bundled `tools` as tool cards in its layout
- Inject `template.custom_css` in a `<style>` tag scoped to the component
- Inject `template.custom_html_head` via Inertia `<Head>`
- Inject `template.custom_html_body` at bottom of component

**Color application (scoped CSS custom properties):**
```vue
<template>
  <div class="template-wrapper" :style="cssVars">
    <!-- template content -->
  </div>
</template>

<script setup lang="ts">
const cssVars = computed(() => ({
  '--t-primary':   props.template.color_primary   ?? 'var(--color-primary-500)',
  '--t-secondary': props.template.color_secondary ?? 'var(--color-secondary-500)',
  '--t-bg':        props.template.color_bg        ?? 'var(--surface-bg)',
  '--t-surface':   props.template.color_surface   ?? 'var(--surface-card)',
  '--t-text':      props.template.color_text      ?? 'var(--color-gray-700)',
}))
</script>

<style scoped>
/* Use --t-* variables inside the template component */
.template-wrapper { background: var(--t-bg); }
.template-card    { background: var(--t-surface); }
.template-btn     { background: var(--t-primary); }
</style>
```

---

### 14B.7 `SiteTemplateResource` — What Admin Edits Are Exposed to Frontend ✅

```php
// app/Http/Resources/SiteTemplateResource.php
return [
    'slug'             => $this->slug,
    'name'             => $this->name,
    'tagline'          => $this->tagline,
    'layout_component' => $this->layout_component,

    // Appearance overrides (null = use global design system)
    'color_primary'    => $this->color_primary,
    'color_secondary'  => $this->color_secondary,
    'color_bg'         => $this->color_bg,
    'color_surface'    => $this->color_surface,
    'color_text'       => $this->color_text,
    'font_heading'     => $this->font_heading,
    'font_body'        => $this->font_body,

    // Content
    'hero_headline'    => $this->hero_headline ?? settings('app_name'),
    'hero_subheadline' => $this->hero_subheadline ?? settings('app_tagline'),
    'hero_cta_text'    => $this->hero_cta_text ?? translate('Get Started'),
    'hero_cta_url'     => $this->hero_cta_url ?? route('register'),
    'hero_bg_image'    => $this->hero_bg_image ? Storage::url($this->hero_bg_image) : null,

    // Custom code (HTML-escaped for safety display, raw for injection)
    'custom_css'       => $this->custom_css,
    'custom_html_head' => $this->custom_html_head,
    'custom_html_body' => $this->custom_html_body,

    // SEO
    'meta_title'       => $this->meta_title ?? ($this->name . ' — ' . settings('app_name')),
    'meta_description' => $this->meta_description ?? $this->tagline,
    'og_image'         => $this->og_image ? Storage::url($this->og_image) : settings('app_og_image'),
];
// bundled_tool_slugs and layout_component never exposed raw — tools passed separately as AiToolResource[]
```

---

### 14B.8 Caching ✅

```php
// Cache keys for site templates
'makeai:site_template:{slug}'     TTL: forever (invalidated on save)
'makeai:site_templates:active'    TTL: forever (invalidated on any save/toggle)
'makeai:homepage_template'        TTL: forever (invalidated when homepage setting changes)
```

---

### 14B.9 Checklist: Site Templates ✅

- [ ] Admin can edit all 5 tabs (Appearance, Content, Custom Code, SEO, Tools) for every template
- [ ] Admin CANNOT create new templates — "+ New Template" button does not exist anywhere
- [ ] Admin CANNOT delete templates — no delete button in UI or API
- [ ] Color overrides applied as scoped CSS vars — never bleed into global design system
- [ ] `color_primary = null` correctly falls back to global `--color-primary-500`
- [ ] Font override loads correct Google Font when set, falls back to global fonts when null
- [ ] "Reset to defaults" clears all color/font overrides (sets all to null)
- [ ] Homepage selector shows only active templates + "Default" option
- [ ] Pro-only templates locked (shown with padlock) when `!isProAvailable()`
- [ ] Template route resolves correctly: `settings('homepage_template')` → correct Vue component
- [ ] `bundled_tool_slugs` respects tool `is_active` state — disabled tools show warning in Tab 5
- [ ] Custom CSS is scoped — cannot override styles outside the template wrapper
- [ ] Custom HTML head injected via Inertia `<Head>` (SSR-rendered)
- [ ] Custom HTML body injected at bottom of template component
- [ ] SERP preview in SEO tab updates live as meta title/description is typed
- [ ] Template OG image renders in social share preview
- [ ] Menu Builder correctly shows `site_template` type items with template names
- [ ] Template cache invalidated on every save
- [ ] All 8 predefined templates seeded with sensible default content

---

## PART 14B.10 — CHATBOT SITE TEMPLATE (claude.ai / ChatGPT-style) ✅

> This is a dedicated site template (`slug: ai-chat`) that turns the entire site into a
> ChatGPT / Claude-style conversational AI experience.
> It uses the same Site Template architecture as all other templates (Part 14B),
> but has a completely unique layout and a rich set of chatbot-specific settings.

---

### 14B.10.1 Template Identity

```
Slug:             ai-chat
Name:             AI Chat
Vue Component:    AiChatTemplate.vue  (resources/js/Templates/AiChatTemplate.vue)
Layout:           claude.ai / ChatGPT — persistent left sidebar + main chat area
Footer:           HIDDEN — never shown, even if footer builder has content
Header:           Site default header (from Header Builder) — 60px fixed top
```

---

### 14B.10.2 Layout Structure

```
┌─────────────────────────────────────────────────────────────────┐
│  Site Default Header (60px, from Header Builder)                │
├────────────────────┬────────────────────────────────────────────┤
│  Chat Sidebar      │  Main Chat Area                            │
│  260px · fixed     │  flex-1                                    │
│                    │                                            │
│  [+ New Chat]      │  STATE A — Welcome Screen                  │
│  [⊞ Projects]  Pro │    Greeting heading                        │
│  ─────────────    │    8 Product cards (3+3+2 grid)            │
│  Today             │    Suggested prompts row (chips)           │
│  • Chat title      │                                            │
│  • Chat title      │  STATE B — Active Conversation             │
│  Yesterday         │    Chat messages (streaming)               │
│  • Chat title      │    Token usage indicator                   │
│  ─────────────    │                                            │
│  [Products] ←      │  Message input bar (always visible)        │
│    2 pinned +      │    Model selector · Attach · Send          │
│    [more ▾]        │                                            │
│  ─────────────    │                                            │
│  [User menu]       │                                            │
└────────────────────┴────────────────────────────────────────────┘
```

**Height calculation:**
```css
.chat-template-wrapper { height: calc(100vh - 60px); display: flex; overflow: hidden; }
/* 60px = site header height */
/* Footer display: none — always, regardless of footer builder settings */
```

---

### 14B.10.3 Chat Sidebar — Detailed Spec

**New Chat button:**
```
[+ New Chat]   ← primary color button, full width, top of sidebar
```
- Clears main area → shows Welcome Screen (State A)
- Resets selected product to none
- Keyboard shortcut: `Ctrl+Shift+O` / `Cmd+Shift+O`

**Projects section (Pro only — hidden if `!isProAvailable()`):**
```
⊞ Projects
  • Project Alpha        ← click → filters chat history to this project
  • Client Work
  [+ New Project]
```
- Project = named group of conversations
- User creates projects from "+" button or from chat context menu (right-click / ⋯)
- Each conversation can belong to one project (optional)
- Projects sorted by last activity

**Recent Chats list:**
```
Today
  • Write a landing page copy     [⋯]  ← hover shows context menu
  • Fix React useState bug        [⋯]
Yesterday
  • Instagram caption ideas       [⋯]
  • Email to client               [⋯]
Last 7 Days
  • Python CSV parser             [⋯]
Older
  • Marketing plan outline        [⋯]
```
- Shows last 50 conversations, grouped by recency
- Each item: truncated title (first user message, max 40 chars)
- Hover → shows `[⋯]` context menu: Rename, Move to Project, Delete
- Active conversation: highlighted with primary color left border
- Title is auto-generated from first user message (or AI can name it after first exchange)

**Products section in sidebar (shown ONLY during active conversation):**
```
Products
  [💻 Code]  [✍️ Write]  [more ▾]
```
- Shows ONLY 2 product chips inline when a conversation is active
- `[more ▾]` toggle → expands to show all 8 products in a small popover/dropdown
- Clicking a product chip during a conversation: switches system prompt from next message onward
  - Shows a subtle divider in chat: "— Switched to Code mode —"
- Collapsed by default (2 visible) to save sidebar space
- The 2 pinned products are the 2 with highest `sort_order` (admin-configurable)
- During Welcome Screen (no active chat): products shown in main area grid, NOT in sidebar

**User menu (sidebar bottom):**
```
[Avatar] User Name
         Plan badge (Free / Pro)
         ─────────────
         ⚙ Settings
         💳 Billing  (if isProAvailable())
         🚪 Sign out
```

---

### 14B.10.4 Welcome Screen — State A

Shown when no conversation is active (fresh page load or after "New Chat").

```
What can I help with?
Choose a product or start typing below

┌─────────┐ ┌─────────┐ ┌─────────┐
│ 💻 Code │ │ ✍️ Write │ │ 🎨 Design│
│ Write,  │ │ Blogs,  │ │ UI briefs│
│ fix &   │ │ emails  │ │ brand &  │
│ debug   │ │ & essays│ │ creative │
└─────────┘ └─────────┘ └─────────┘
┌─────────┐ ┌─────────┐ ┌─────────┐
│📣 Market│ │📱 Social│ │📊 Analyze│
│ Ads,    │ │Captions,│ │ Data &  │
│ funnels │ │ threads │ │ insights│
└─────────┘ └─────────┘ └─────────┘
┌─────────┐ ┌─────────┐
│🖼️ Image │ │🌐 Research│
│Generate │ │Web-aware│
│ & edit  │ │answers  │
└─────────┘ └─────────┘

Suggested prompts:
[Fix a bug in my code] [Write a cold email] [Create Instagram captions]
[Analyze this data]    [Generate an image]  [Research this topic]
```

- Clicking a product card: sets that product as active → moves focus to input bar
- Selected product card gets highlighted border
- Suggested prompts change dynamically based on selected product
- Keyboard: Tab through products, Enter to select

---

### 14B.10.5 The 8 Chat Products

```sql
-- chatbot_products table
id, slug, name, icon, color_hex, system_prompt (text), default_model (varchar),
starter_prompts (json), sort_order (int), is_active (bool), created_at, updated_at
```

| # | Slug | Name | Icon (Tabler) | Color | Default Model | Purpose |
|---|------|------|---------------|-------|---------------|---------|
| 1 | `chat-code` | Code | `ti-code` | `#1F75FE` | deepseek-v3 OR gpt-4o | Write, fix, explain, document code in 50+ languages. Syntax highlighting on output. |
| 2 | `chat-write` | Write | `ti-pencil` | `#16a34a` | gpt-4o OR claude-sonnet | Blogs, emails, essays, reports, creative fiction, copywriting. |
| 3 | `chat-design` | Design | `ti-palette` | `#9333ea` | claude-sonnet | UI/UX briefs, brand guidelines, design feedback, component specs, Figma prompts. |
| 4 | `chat-marketing` | Marketing | `ti-speakerphone` | `#ea580c` | gpt-4o | Ad copy, funnels, GTM strategy, positioning, competitor analysis, SEO content. |
| 5 | `chat-social` | Social Media | `ti-brand-instagram` | `#db2777` | gpt-4o-mini OR claude-haiku | Platform-aware captions, threads, hooks, hashtags, content calendars. |
| 6 | `chat-analyze` | Analyze | `ti-chart-bar` | `#ca8a04` | gpt-4o | Data interpretation, report summarization, research, comparisons, insights. |
| 7 | `chat-image` | Image | `ti-photo` | `#0891b2` | dall-e-3 OR flux-pro | AI image generation. Clicking this product switches input to image mode (prompt → image). |
| 8 | `chat-research` | Research | `ti-world-search` | `#4f46e5` | perplexity-sonar OR gpt-4o+serpapi | Web-aware answers with cited sources. Auto-enables web search tool. |

**Starter prompts per product (stored in `starter_prompts` JSON column):**
```json
{
  "chat-code":       ["Fix a bug in my code", "Write a Python script", "Explain this regex", "Add TypeScript types", "Write unit tests for this"],
  "chat-write":      ["Write a blog post about...", "Improve my email draft", "Write a product description", "Summarize this text", "Proofread and fix grammar"],
  "chat-design":     ["Review my UI design", "Create a brand color palette", "Write a design brief", "Suggest UI improvements", "Generate Figma component ideas"],
  "chat-marketing":  ["Write Facebook ad copy", "Create a go-to-market plan", "Write a value proposition", "Plan a product launch", "Analyze this landing page"],
  "chat-social":     ["Write 5 Instagram captions", "Create a Twitter thread", "Suggest trending hashtags", "Write a LinkedIn post", "Create a content calendar"],
  "chat-analyze":    ["Analyze this data", "Summarize this report", "Compare these two options", "Explain this concept simply", "Extract key insights"],
  "chat-image":      ["Generate a product banner", "Create a logo concept", "Design a social media graphic", "Illustrate this scene", "Create a thumbnail"],
  "chat-research":   ["Latest news about...", "Research competitors in...", "Find statistics on...", "Summarize recent developments in...", "What is the current status of..."]
}
```

---

### 14B.10.6 Automatic Model Selection per Product

When user selects a product (or starts a new chat with a product), the system auto-selects the **best available model** for that product's purpose.

**Resolution order:**
```
1. User's personal API key for the preferred provider (if set in User Settings → API Keys)
   → Use their key, no credits charged
2. Admin has configured preferred model for this product in chatbot settings
   → Use admin-configured model
3. Auto-select from enabled providers based on product's preferred_providers list
   → First enabled provider in preference order
4. Fallback: settings('default_chat_model') — admin-set global default
5. Last resort: first enabled model in ProviderRegistry
```

**Preferred provider order per product (seeded defaults, admin-overridable):**
```php
'chat-code'      => ['deepseek-v3', 'gpt-4o', 'claude-sonnet-4-5', 'gemini-2-0-flash'],
'chat-write'     => ['gpt-4o', 'claude-sonnet-4-5', 'gemini-2-5-pro', 'mistral-large'],
'chat-design'    => ['claude-sonnet-4-5', 'gpt-4o', 'gemini-2-5-pro'],
'chat-marketing' => ['gpt-4o', 'claude-sonnet-4-5', 'gemini-2-5-pro'],
'chat-social'    => ['gpt-4o-mini', 'claude-haiku-4-5', 'gemini-2-0-flash'],
'chat-analyze'   => ['gpt-4o', 'claude-sonnet-4-5', 'gemini-2-5-pro'],
'chat-image'     => ['dall-e-3', 'flux-pro', 'ideogram', 'stability-sd3'],
'chat-research'  => ['perplexity-sonar', 'gpt-4o', 'gemini-2-5-pro'],
// perplexity-sonar auto-enables web_search tool
// dall-e-3/flux-pro switches UI to image generation mode
```

**UI behavior on auto-select:**
- Model badge in input bar updates immediately: `[GPT-4o ▾]` → `[DeepSeek V3 ▾]`
- Only show ai model that are configured by admin
- User can override by clicking model badge → dropdown of all enabled models
- If no model available for preferred providers: fallback applies silently
- If NO models configured at all: show inline warning in input bar: "⚠ No AI model configured. Please ask the administrator to add an API key."

---

### 14B.10.7 Active Conversation — State B

**Message display:**
- User messages: right-aligned, rounded bubble, primary-50 background
- AI messages: left-aligned, no bubble background, full width, markdown rendered
- Code blocks: syntax highlighted (highlight.js), copy button top-right
- Streaming: blinking cursor `▋` appended to last token during generation
- Images (chat-image product): displayed inline in message bubble

**Token usage indicator (shown below AI message after completion):**
```
[i] 847 tokens used · 2.1 credits charged · gpt-4o
```
- Shows: input tokens + output tokens combined, credits deducted, model used
- Collapsed by default — click `[i]` to expand token breakdown
- Color: gray-400, font-size: 11px — unobtrusive
- If user's plan has credit limit: shows remaining credits inline: `· 341 credits left`

**Per-message token breakdown (expanded on click):**
```
Input:  623 tokens
Output: 224 tokens
Total:  847 tokens
Model:  gpt-4o
Cost:   $0.0042 (shown only to admin in reports, NOT to users)
Credits used: 2.1
Credits remaining: 341.2
```

**Access control gating in chat (all will be controlled by admin from settings -):**
```
Guest (not logged in):
  → Can send 3 messages (IP-limited, settings-configurable)
  → After limit: "Create a free account to continue"
  → All messages visible but input disabled with login CTA

Free user:
  → Credit-gated per settings('chat_credits_per_message')
  → Model limited to settings('chat_free_plan_models') — e.g. only gpt-4o-mini
  → Token limit per message: settings('chat_max_tokens_free') — e.g. 2000
  → No Projects feature
  → Chat history: last 30 conversations only

Pro user:
  → Full credit allocation per plan
  → All models available (those admin has configured)
  → Token limit per message: settings('chat_max_tokens_pro') — e.g. 8000
  → Projects feature enabled
  → Unlimited chat history
  → File attachment enabled (PDF, image, CSV)
```

---

### 14B.10.8 Message Input Bar

```
┌─────────────────────────────────────────────────────────────┐
│ 📎  Ask anything...                    [GPT-4o ▾]  [↑ Send] │
└─────────────────────────────────────────────────────────────┘
```

**Elements:**
- `📎` Attach button (left): opens file picker — PDF, image (PNG/JPG), CSV, TXT — max 10MB
  - File attached: shows filename chip inside input bar with `✕` to remove
  - Only available when current model supports file input (vision/document capable)
  - If model doesn't support files: shows tooltip "Switch to a model that supports attachments"
- Text input: `textarea` auto-grows (1 row → max 6 rows), Enter to send, Shift+Enter for newline
- Model selector badge `[GPT-4o ▾]`: click → dropdown of all enabled models grouped by provider
  - Selected model persists per conversation (stored in `conversations.model`)
  - Changes for next message only — does not regenerate previous messages
- Send button: arrow-up icon, primary color, disabled while streaming
  - During streaming: changes to Stop button `[■ Stop]` — cancels the SSE stream

**Keyboard shortcuts:**
```
Enter          → Send message
Shift+Enter    → Newline in input
Escape         → Cancel/close any open dropdown
Ctrl+K / Cmd+K → Open command palette (search conversations)
Ctrl+Q   → New chat
```

---

### 14B.10.9 Token Usage & Access Control Settings

**Admin → Appearance → Site Templates → AI Chat → Tab: Access & Limits**

```
Chat Access Settings
─────────────────────────────────────────────────────
Guest (not logged in)
  [ ] Allow guest messages (no login required)
  [3] Max messages per guest session (IP-tracked, sliding window)
  Model for guests: [gpt-4o-mini ▾]  (only fast/cheap models)
  Max tokens per guest message: [500 ___]

Free Plan Users
  [✓] Credits per chat message: [1.0 ___] credits
  Model access: [gpt-4o-mini, gemini-flash ___] (multi-select)
  Max tokens per message: [2000 ___]
  Max chat history stored: [30 ___] conversations
  [✓] Allow file attachments
  Max file size for free: [5 ___] MB

Pro Plan Users  (shown only if isProAvailable())
  [✓] Credits per chat message: [0.5 ___] credits  (discounted vs free)
  Model access: [All enabled models ▾]
  Max tokens per message: [8000 ___]
  Unlimited chat history: [✓]
  [✓] Allow file attachments
  Max file size for pro: [20 ___] MB
  [✓] Enable Projects feature
  Max projects per user: [unlimited / 10 ___]

Token Tracking
  [✓] Show token usage below each AI message
  [✓] Show credits charged below each AI message
  [✓] Show remaining credits (when < 100 credits left: always show)
  [ ] Show cost in USD (only for admin users — never show cost to regular users)
```

All values stored in `settings` table, group: `chatbot`. Editable without redeploy.

---

---

### 14B.10.11 DB Tables for Chatbot Template

```sql
-- chatbot_products (the 8 product modes)
CREATE TABLE chatbot_products (
    id               bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             varchar(100) NOT NULL UNIQUE,
    name             varchar(100) NOT NULL,
    icon             varchar(100) NOT NULL,        -- Tabler icon class e.g. 'ti-code'
    color_hex        varchar(7)   NOT NULL,        -- e.g. '#1F75FE'
    system_prompt    text         NOT NULL,        -- sent as system message on every chat
    preferred_models json         NOT NULL,        -- ordered array of model slugs
    default_model    varchar(150) NULL,            -- resolved model slug (cached)
    starter_prompts  json         NOT NULL,        -- array of 5 strings
    sort_order       int          DEFAULT 0,
    is_active        boolean      DEFAULT true,
    created_at       timestamp,
    updated_at       timestamp
);

-- conversations (each chat session)
CREATE TABLE conversations (
    id               bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ulid             char(26)     NOT NULL UNIQUE,
    user_id          bigint UNSIGNED NOT NULL,
    project_id       bigint UNSIGNED NULL,         -- FK → chat_projects.id
    product_slug     varchar(100) NULL,            -- FK → chatbot_products.slug
    title            varchar(255) NULL,            -- auto-generated or user-renamed
    model            varchar(150) NULL,            -- model used for this conversation
    total_tokens     int          DEFAULT 0,       -- running total
    total_credits    decimal(10,4) DEFAULT 0,
    message_count    int          DEFAULT 0,
    last_message_at  timestamp    NULL,
    created_at       timestamp,
    updated_at       timestamp,

    INDEX idx_user_updated (user_id, updated_at DESC)
);

-- conversation_messages
CREATE TABLE conversation_messages (
    id               bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id  bigint UNSIGNED NOT NULL,
    role             enum('user','assistant','system') NOT NULL,
    content          text         NOT NULL,        -- markdown for assistant, plain for user
    model            varchar(150) NULL,            -- which model generated this (assistant only)
    input_tokens     int          DEFAULT 0,
    output_tokens    int          DEFAULT 0,
    credits_charged  decimal(10,4) DEFAULT 0,
    attachments      json         NULL,            -- [{type,name,url,size}]
    product_switch   varchar(100) NULL,            -- if non-null: this message triggered a product switch
    created_at       timestamp,

    INDEX idx_conversation (conversation_id, created_at ASC)
);

-- chat_projects (Pro only)
CREATE TABLE chat_projects (
    id               bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          bigint UNSIGNED NOT NULL,
    name             varchar(255) NOT NULL,
    description      text         NULL,
    color_hex        varchar(7)   NULL,            -- project color dot in sidebar
    created_at       timestamp,
    updated_at       timestamp
);
```

---

### 14B.10.12 API Routes for Chat Template

```
-- Conversations
GET    /api/v1/chat                         list conversations (paginated, grouped by date)
POST   /api/v1/chat                         create new conversation { product_slug?, model? }
GET    /api/v1/chat/{ulid}                  get conversation + messages
DELETE /api/v1/chat/{ulid}                  delete conversation
PUT    /api/v1/chat/{ulid}                  update { title, project_id, model }

-- Messages (streaming)
POST   /api/v1/chat/{ulid}/message          send message → SSE stream
                                            body: { content, product_slug?, attachments? }
                                            Response: text/event-stream
                                            Headers: X-Accel-Buffering: no

-- Projects (Pro only — gated by isProAvailable())
GET    /api/v1/chat/projects                list user's projects
POST   /api/v1/chat/projects                create project { name, description?, color_hex? }
PUT    /api/v1/chat/projects/{id}           update project
DELETE /api/v1/chat/projects/{id}           delete project (conversations move to no-project)

-- Products
GET    /api/v1/chat/products                list active products (public — used to render product cards)
```

**SSE stream format (POST `/api/v1/chat/{ulid}/message`):**
```
data: {"type":"token","content":"Hello"}
data: {"type":"token","content":" there"}
data: {"type":"usage","input_tokens":45,"output_tokens":120,"credits":0.8,"model":"gpt-4o"}
data: {"type":"done"}
```

---

### 14B.10.13 Inertia Pages for Chat Template

```
resources/js/Pages/Chat/
  Index.vue        → GET /chat          (welcome screen → no active conversation)
  Show.vue         → GET /chat/{ulid}   (active conversation)

resources/js/Templates/
  AiChatTemplate.vue  → shared layout wrapper (sidebar + header + main slot)

resources/js/Components/Chat/
  ChatSidebar.vue       → left panel (new chat, projects, recent chats, pinned products)
  ChatWelcome.vue       → welcome screen with product grid + starter prompts
  ChatMessages.vue      → message list with streaming
  ChatMessage.vue       → single message (user/assistant) with token usage
  ChatInput.vue         → input bar (textarea, model selector, attach, send/stop)
  ProductCard.vue       → product card for welcome grid
  ProductChip.vue       → mini product chip for sidebar (during active chat)
  ModelSelector.vue     → dropdown for model switching
  TokenUsage.vue        → token/credits display below assistant message
  ChatContextMenu.vue   → right-click menu for chat list items (rename, move, delete)
```

---

### 14B.10.14 Checklist: Chatbot Template

- [ ] `AiChatTemplate.vue` layout: header shown, footer hidden, `height: calc(100vh - 60px)`
- [ ] Left sidebar: new chat button, projects (Pro-gated), recent chats grouped by date, products section
- [ ] Products section in sidebar: shows only 2 chips, `[more ▾]` expands all 8 in popover
- [ ] Welcome screen: 8 product cards in 3+3+2 grid, starter prompts update on product select
- [ ] Clicking product card: auto-selects best available model per preferred_models order
- [ ] Model badge in input bar updates immediately on product selection
- [ ] Model selector dropdown: shows all admin-enabled models, grouped by provider
- [ ] If no model configured: inline warning "No AI model configured" in input bar
- [ ] Chat messages: user right, AI left, markdown rendered, code blocks with syntax highlight + copy
- [ ] Streaming: blinking cursor during generation, Stop button replaces Send
- [ ] Token usage indicator below each AI message (collapsed by default, expand on click)
- [ ] Token indicator shows: tokens used, credits charged, model name, credits remaining (when low)
- [ ] Guest limit: IP-tracked message count, login CTA after limit reached
- [ ] Free user model restriction: only models in `chatbot.chat_free_plan_models` setting
- [ ] Pro user: all enabled models, projects, unlimited history, higher token limits
- [ ] File attachment: only when model supports it, tooltip when unsupported
- [ ] Settings: guest limits, free plan limits, pro limits — all in `settings` table, admin-editable
- [ ] `conversations` table indexed on `(user_id, updated_at DESC)` — list query uses index
- [ ] `conversation_messages` indexed on `(conversation_id, created_at ASC)` — message load uses index
- [ ] SSE route has `X-Accel-Buffering: no` header + Nginx `proxy_read_timeout 120s`
- [ ] POST + ReadableStream (NOT EventSource) on frontend for streaming
- [ ] Projects feature: create, rename, delete, move conversations — Pro-gated throughout
- [ ] All 8 products seeded with system prompts, starter prompts, preferred models
- [ ] `chatbot_products` table: admin can edit name/icon/color/system_prompt via admin panel
- [ ] Chat history: free users capped at last 30 conversations (older auto-archived, not deleted)
- [ ] ULID used in all conversation URLs — never expose auto-increment `id`

---

## PART 15 — AI TOOLS DEVELOPMENT GUIDELINES

> This part covers how every AI tool is architected, rendered, and connected — from DB template to streaming output. Follow this pattern for all 255 tools consistently.

---

### 15.1 Core Data Flow (Every Tool)

```
User fills form
  → Vue validates fields
    → POST /api/v1/generate/text (or /stream)
      → CheckCredits middleware
        → TokenGuard::before()
          → ProviderRegistry resolves model
            → AiService::stream() or complete()
              → SSE stream / JSON response
                → TokenGuard::after() deducts credits
                  → ai_usage_logs insert
                    → Document saved
                      → Frontend renders output
```

---

### 15.2 Database: `ai_tools` Fields Reference

```sql
slug                varchar(100)   -- unique, used in API routes and Vue routing
prompt_system       text           -- developer-written, admin-editable
prompt_user         text           -- template with {field_name} placeholders
fields              json           -- input field definitions (see below)
output_type         enum           -- text | markdown | html | code | list | image | audio | video
model_override      varchar        -- null = user picks; set to force a specific model
max_tokens_override int            -- null = global setting
requires_pro        boolean
access_level        enum           -- inherit | public | login_required | free_plan | pro_plan
```

**Fields JSON — all supported field types:**

```json
[
  { "name": "topic",        "type": "text",            "label": "Topic",            "placeholder": "e.g. Laravel best practices", "required": true, "max_length": 200 },
  { "name": "description",  "type": "textarea",        "label": "Description",      "placeholder": "Describe your product...",    "required": false, "rows": 4 },
  { "name": "tone",         "type": "tone_select",     "label": "Tone of Voice",    "required": false },
  { "name": "language",     "type": "language_select", "label": "Output Language",  "required": false, "default": "English" },
  { "name": "length",       "type": "length_select",   "label": "Output Length",    "required": false, "default": "Medium" },
  { "name": "count",        "type": "number",          "label": "How many?",        "min": 1, "max": 20, "default": 5 },
  { "name": "creativity",   "type": "slider",          "label": "Creativity",       "min": 0, "max": 1, "step": 0.1, "default": 0.7 },
  { "name": "format",       "type": "select",          "label": "Format",           "options": ["Bullet Points","Numbered","Paragraph"], "default": "Bullet Points" },
  { "name": "include_emoji","type": "toggle",          "label": "Include Emoji",    "default": false },
  { "name": "keywords",     "type": "tags_input",      "label": "Target Keywords",  "placeholder": "Add keyword + Enter" },
  { "name": "audience",     "type": "text",            "label": "Target Audience",  "placeholder": "e.g. Small business owners" },
  { "name": "model",        "type": "model_select",    "label": "AI Model",         "required": false }
]
```

**Built-in field components (pre-rendered, no custom code needed):**

| type | Renders as |
|------|-----------|
| `text` | Single-line input |
| `textarea` | Multi-line textarea |
| `tone_select` | Dropdown: Professional / Friendly / Casual / Formal / Humorous / Persuasive / Inspirational / Empathetic |
| `language_select` | Dropdown of all active languages from `languages` table |
| `length_select` | Dropdown: Short (≈100w) / Medium (≈300w) / Long (≈600w) / Very Long (≈1200w) |
| `model_select` | Dropdown of enabled models (filtered by user plan) |
| `select` | Dropdown with `options` array |
| `number` | Number input with min/max/step |
| `slider` | Range slider (0–1 for temperature, etc.) |
| `toggle` | On/off switch |
| `tags_input` | Multi-value tag input (type + Enter) |
| `color` | Color picker |
| `image_upload` | Image upload (for image-to-text tools) |
| `file_upload` | File upload (PDF/DOCX for summarize tools) |
| `code_input` | Monaco/CodeMirror input with language selector |
| `url` | URL input with validate-on-blur |

---

### 15.3 System Prompt Engineering Pattern

Every tool's `prompt_system` follows this structure:

```
You are an expert [ROLE].
[CONTEXT about what the tool does.]
[RULES: numbered list of output constraints.]
[FORMAT: exactly how output should be structured.]
[QUALITY: what makes a good vs bad output.]
Always respond in {language}.
```

**Example — Blog Article tool:**
```
You are an expert content writer and SEO specialist.
Write comprehensive, engaging blog articles that rank in search engines and provide real value to readers.

Rules:
1. Use clear headings (H2 and H3) to structure the content
2. Write in a {tone} tone suitable for {audience}
3. Include the target keywords naturally — never keyword stuff
4. Every section must add unique value — no filler content
5. Output length target: {length}

Format:
- Start with a compelling hook (no generic "In today's world...")
- Use H2 for main sections, H3 for subsections
- Use bullet points or numbered lists where appropriate
- End with a clear conclusion and CTA
- Output clean Markdown

Always respond in {language}.
```

**Example — Product Description tool:**
```
You are a conversion-focused copywriter who writes product descriptions that sell.

Rules:
1. Lead with the most important benefit, not a feature
2. Use sensory and emotional language
3. Address the reader directly using "you"
4. Include {count} key selling points as bullet points
5. Keep sentences short — average under 15 words
6. Tone: {tone}

Format:
[Opening hook — 1-2 sentences]
[Body — benefits-focused paragraph]
[Key features — {count} bullet points starting with a strong verb]
[Closing CTA — 1 sentence]

Always respond in {language}.
```

**User prompt template pattern:**

```
Topic/Product: {topic}
Description: {description}
Target Audience: {audience}
Tone: {tone}
Length: {length}
Keywords to include: {keywords}
Additional instructions: {additional}
```

Admin can edit both `prompt_system` and `prompt_user` from Admin → AI Tools → Templates → Edit.

---

### 15.4 Frontend Tool Page Architecture

Every tool uses the **same Vue page component** — `resources/js/Pages/AI/ToolPage.vue`.
The template slug from the URL (`/ai-tools/{slug}`) is used to fetch the tool definition and dynamically render the correct form.

```
URL: /ai-tools/blog-article
  → ToolPage.vue loaded
    → GET /api/v1/tools/blog-article
      → Response: { name, description, fields[], output_type, model_override, ... }
        → DynamicForm.vue renders fields[] as form inputs
          → User fills form → clicks Generate
            → streaming output in OutputPanel.vue
```

**ToolPage.vue layout:**

```
┌─────────────────────────────────────────────────────┐
│  Breadcrumb: AI Tools > Blog & Content > Blog Article│
├────────────────────┬────────────────────────────────┤
│                    │                                │
│   INPUT PANEL      │     OUTPUT PANEL               │
│   (left, 400px)    │     (right, flex-1)            │
│                    │                                │
│  Tool name + icon  │  [Model selector]  [Copy] [Save]│
│  Tool description  │  ─────────────────────────────│
│                    │                                │
│  [DynamicForm]     │  StreamingText or              │
│  (fields rendered  │  rendered Markdown/Code/etc.   │
│   from fields[])   │                                │
│                    │  Word count  Reading time       │
│  [Generate btn]    │                                │
│  [Credit cost]     │  [Export ▾] [Copy] [Save Doc]  │
│                    │  [Regenerate] [Edit in Editor] │
└────────────────────┴────────────────────────────────┘
```

**Mobile layout:** panels stack vertically — output scrolls below input form.

---

### 15.5 Streaming Output (SSE)

For text generation tools, output streams token by token via Server-Sent Events.

**Laravel controller:**
```php
// app/Http/Controllers/AI/GenerateController.php
public function stream(StreamRequest $request): StreamedResponse
{
    // 1. Validate fields
    // 2. TokenGuard::before() — check credits
    // 3. Build prompt from template
    // 4. Return StreamedResponse:
    return response()->stream(function () use ($request) {
        $stream = $this->aiService->stream($completionRequest);
        foreach ($stream as $token) {
            echo "data: " . json_encode(['token' => $token]) . "\n\n";
            ob_flush(); flush();
        }
        echo "data: [DONE]\n\n";
        ob_flush(); flush();
        // 5. After stream: TokenGuard::after(), log usage, save document
    }, 200, [
        'Content-Type'  => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',  // critical for Nginx
    ]);
}
```

**Vue composable `useStream.ts`:**
```typescript
export function useStream() {
  const output = ref('')
  const isStreaming = ref(false)
  const error = ref<string | null>(null)

  async function generate(slug: string, fields: Record<string, any>) {
    output.value = ''
    isStreaming.value = true
    error.value = null

    const es = new EventSource(
      `/api/v1/generate/stream?` + new URLSearchParams({ slug, ...fields })
    )
    // OR use fetch + ReadableStream for POST with body

    es.onmessage = (e) => {
      if (e.data === '[DONE]') { isStreaming.value = false; es.close(); return }
      const { token } = JSON.parse(e.data)
      output.value += token
    }
    es.onerror = () => {
      isStreaming.value = false
      error.value = 'Generation failed. Please try again.'
      es.close()
    }
  }

  return { output, isStreaming, error, generate }
}
```

**POST streaming (for fields that need request body):**
Use `fetch()` with `ReadableStream` instead of `EventSource` (EventSource only supports GET).

```typescript
const response = await fetch('/api/v1/generate/stream', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrf },
  body: JSON.stringify({ slug, ...fields })
})
const reader = response.body!.getReader()
const decoder = new TextDecoder()
while (true) {
  const { done, value } = await reader.read()
  if (done) break
  const chunk = decoder.decode(value)
  // parse SSE lines from chunk
}
```

---

### 15.6 Prompt Assembly Service

**`app/Services/AI/PromptBuilder.php`**

```php
class PromptBuilder
{
    public function build(AiTemplate $template, array $fields, User $user): CompletionRequest
    {
        // 1. Replace {field_name} in prompt_system and prompt_user
        $system = $this->interpolate($template->prompt_system, $fields);
        $user_prompt = $this->interpolate($template->prompt_user, $fields);

        // 2. Inject brand voice if user has set it and tool supports it
        if ($user->brand_voice && $template->supports_brand_voice) {
            $system .= "\n\nBrand voice context:\n" . $user->brand_voice;
        }

        // 3. Apply length instruction
        $system .= $this->getLengthInstruction($fields['length'] ?? 'medium');

        // 4. Resolve model
        $model = $template->model_override
            ?? $fields['model']
            ?? settings('default_ai_model', 'gpt-4o-mini');

        // 5. Apply user's personal API key for provider if set
        $apiKey = $this->resolveApiKey($model, $user);

        return new CompletionRequest(
            model: $model,
            systemPrompt: $system,
            userMessage: $user_prompt,
            maxTokens: $template->max_tokens_override ?? settings('default_max_tokens', 2000),
            temperature: (float) ($fields['creativity'] ?? 0.7),
            apiKey: $apiKey,
        );
    }

    private function getLengthInstruction(string $length): string
    {
        return match($length) {
            'short'     => "\nOutput length: approximately 100 words.",
            'medium'    => "\nOutput length: approximately 300 words.",
            'long'      => "\nOutput length: approximately 600 words.",
            'very_long' => "\nOutput length: approximately 1200 words.",
            default     => ''
        };
    }
}
```

---

### 15.7 Tool-Specific Output Types & Rendering

| output_type | How rendered in OutputPanel |
|-------------|---------------------------|
| `text` | Plain text in `<pre>` tag, monospace font |
| `markdown` | Rendered via `marked.js` or `markdown-it` — full HTML with styles |
| `html` | Rendered in sandboxed `<iframe srcdoc>` |
| `code` | Syntax-highlighted via `highlight.js`, language auto-detected, copy button |
| `list` | Parsed as bullet/numbered list, each item copyable individually |
| `image` | `<img>` tag with download button (for image generation tools) |
| `audio` | HTML5 `<audio>` player with download link (for TTS tools) |
| `video` | HTML5 `<video>` player with download link |
| `json` | Pretty-printed JSON with syntax highlighting |

---

### 15.8 Credit Cost Display

Before generating, show the user estimated credit cost:

```
Estimated cost: ~0.8 credits   [ⓘ based on ~400 output tokens]
Your balance: 145.2 credits
```

Calculation:
```php
// Estimate based on average output for this template
$estimatedTokens = $template->avg_output_tokens ?? 400;
$model = resolve_model($fields);
$cost = estimate_token_cost($estimatedTokens, $model);
$credits = $cost * settings('credits_per_usd', 100);
```

After generation, show actual cost:
```
Used: 0.6 credits  (312 tokens)
```

---

### 15.9 Save to Documents

After generation, output can be saved:

- **Auto-save** (toggle in user settings): every generation auto-creates a document
- **Manual save**: "Save" button → modal asks for title (pre-filled from topic field) + folder picker
- Saved to `documents` table: `{ user_id, title, content, tool_slug, word_count, folder_id }`
- "Edit in Editor" button: opens saved document in full Tiptap editor (`/documents/{id}/edit`)

---

### 15.10 Regenerate & Variations

OutputPanel action buttons:
- **Regenerate**: re-runs exact same prompt, gets fresh output (replaces current)
- **Variations** (optional, if admin enables): generates 3 alternatives simultaneously (uses 3× credits), shown as tabs
- **Improve**: opens AI sidebar with "Improve this output" pre-selected

---

### 15.11 Special Tool Categories & Their Extra Logic

#### Image Generation Tools
- Extra fields: `size` (512×512, 1024×1024, 1792×1024, etc.), `style` (vivid/natural), `quality` (standard/HD)
- Provider selector: DALL-E 3 / Flux / Stable Diffusion / Ideogram (only providers with valid API key)
- Output: image card with download, copy URL, regenerate, save to image library
- Prompt enhancer toggle: AI rewrites user prompt for better image results before sending to provider

#### Code Generation Tools
- Extra fields: `language` (50+ options), `framework` (context-sensitive)
- Output: CodeMirror/highlight.js block with: copy, download as file, run (if sandbox enabled), explain this code button
- Language auto-detected from output for syntax highlighting

#### TTS (Text to Speech) Tools
- Extra fields: `voice` (loaded from provider), `speed` (0.5–2.0), `pitch`
- Voice preview: play 3-second sample of selected voice before generating
- Output: audio player + download MP3/WAV
- Character limit enforced (provider-specific, shown as counter)

#### Document Analysis / Summarize Tools
- Extra field: `file_upload` (PDF/DOCX/TXT) OR `text_input` (paste content)
- File upload → server extracts text → injected into user prompt as `{document_content}`
- Large files chunked and processed in sections

#### Translation Tools
- Source language auto-detect toggle
- Target language = `language_select` field
- Output split: original (left) | translated (right) side-by-side on desktop

#### Rewrite / Paraphrase Tools
- Input = textarea (paste original content)
- Side-by-side diff view toggle: shows original vs rewritten
- Similarity score shown (rough Levenshtein % — how different from original)

#### Social Media Post Tools
- Platform-specific character counter (Twitter: 280, LinkedIn: 3000, etc.)
- Hashtag extractor: after generation, one-click extracts all hashtags into copyable list
- Copy formatted for each platform button (removes hashtags for LinkedIn, etc.)

#### Email Tools
- Subject line generator included (separate output field)
- Preview: renders as actual email (header + body + footer) in iframe

#### SEO Tools
- After generation: keyword density counter highlights target keywords in output
- Meta title: character counter with Google SERP width simulation (600px pixel width)
- Meta description: 160 char limit, SERP snippet preview updates live

---

### 15.12 Tools That Use External APIs (Beyond LLM)

Some tools call non-LLM APIs. These go through their own Service class:

| Tool | External API | Service Class |
|------|-------------|---------------|
| Plagiarism Checker | Copyscape / Originality.ai | `PlagiarismService` |
| AI Content Detector | GPTZero / Sapling | `AiDetectorService` |
| Grammar Checker | LanguageTool (self-hosted or API) | `GrammarService` |
| Background Remover | Remove.bg | `BackgroundRemoveService` |
| Image Upscaler | Replicate (Real-ESRGAN) | `ImageUpscaleService` |
| Stock Image Search | Pixabay + Pexels + Unsplash | `StockImageService` |
| Web Search (in chat) | SerpAPI / Bing / Perplexity | `WebSearchService` |
| YouTube Transcript | youtube-transcript PHP package | `YoutubeService` |
| URL Scraper | Browsershot / Goutte | `WebScraperService` |
| Speech to Text | Whisper / AssemblyAI | `SpeechToTextService` |

Each service:
1. Checks if relevant API key is configured in settings (throws `IntegrationNotConfiguredException` if not)
2. Makes the API call
3. Deducts credits (if applicable — some tools have fixed credit cost, not token-based)
4. Returns structured result

---

### 15.13 Token Guard — Full Implementation

```php
// app/Services/AI/TokenGuard.php

class TokenGuard
{
    public function before(User $user, AiTemplate $template, string $model): void
    {
        $estimatedCost = estimate_token_cost(
            $template->avg_output_tokens ?? 500,
            $model
        );

        // Check user daily limit
        $dailyLimit = $user->daily_limit ?? settings('user_daily_credit_limit', 0);
        if ($dailyLimit > 0 && $user->credits_used_today + $estimatedCost > $dailyLimit) {
            throw new CreditLimitException('daily');
        }

        // Check user monthly limit
        $monthlyLimit = $user->monthly_limit ?? settings('user_monthly_credit_limit', 0);
        if ($monthlyLimit > 0 && $user->credits_used_month + $estimatedCost > $monthlyLimit) {
            throw new CreditLimitException('monthly');
        }

        // Check user credit balance
        if ($user->credits < $estimatedCost) {
            throw new InsufficientCreditsException($user->credits, $estimatedCost);
        }

        // Check global daily budget
        $globalBudget = settings('global_daily_ai_budget_usd', 0);
        if ($globalBudget > 0) {
            $spentToday = Cache::get('ai_spend_today_usd', 0);
            if ($spentToday >= $globalBudget) {
                throw new GlobalBudgetExceededException();
            }
        }

        // Soft warning at 80% of limit
        if ($dailyLimit > 0 && $user->credits_used_today / $dailyLimit >= 0.8) {
            // add to response headers — frontend shows warning toast
            request()->attributes->set('credit_warning', 'daily_80');
        }
    }

    public function after(User $user, int $inputTokens, int $outputTokens, string $model): float
    {
        $inputCost  = $inputTokens  / 1000 * settings("model_{$model}_input_cost",  0.002);
        $outputCost = $outputTokens / 1000 * settings("model_{$model}_output_cost", 0.002);
        $totalUsd   = $inputCost + $outputCost;
        $credits    = $totalUsd * settings('credits_per_usd', 100);

        deduct_credits($user->id, $credits, "AI generation: {$model}");

        // Update daily/monthly usage counters
        $user->increment('credits_used_today',  $credits);
        $user->increment('credits_used_month',  $credits);

        // Update global spend tracker in Redis
        Cache::increment('ai_spend_today_usd', (int)($totalUsd * 1000));

        return $credits;
    }
}
```

---

### 15.14 Tool Page — Content Sections (About, How It Works, Usage, FAQs, Reviews)

Each tool page (`/ai-tools/{slug}`) has a **content area below the generator** containing rich informational sections. All sections are optional and togglable per-tool from admin.

#### Database additions to `ai_tools`

```sql
-- Add to ai_tools table
about_content         longtext NULL        -- rich HTML (Tiptap)
how_it_works          json NULL            -- array of steps [{step, title, description, icon}]
usage_examples        json NULL            -- array of [{title, input_snapshot, output_snapshot}]
faq_items             json NULL            -- array of [{question, answer}] (inline, not from faqs table)
show_about            boolean DEFAULT true
show_how_it_works     boolean DEFAULT true
show_usage_examples   boolean DEFAULT true
show_faqs             boolean DEFAULT true
show_reviews          boolean DEFAULT true
meta_title            varchar(255) NULL    -- SEO: override auto-generated
meta_description      text NULL           -- SEO: override auto-generated
```

#### 15.14.1 About Section

- Rich HTML content editable from admin template editor (Tiptap MinimalEditor)
- Shown below the generator in a `<section id="about">` card
- Can include: what the tool does, who it's for, key benefits, any limitations
- If `about_content` is null → section hidden automatically (no empty block shown)

#### 15.14.2 How It Works Section

Steps stored as JSON array:

```json
[
  { "step": 1, "icon": "ti-forms",     "title": "Fill in your details",   "description": "Enter your topic, tone, and other preferences in the form above." },
  { "step": 2, "icon": "ti-brain",     "title": "AI generates content",   "description": "Our AI analyzes your inputs and generates high-quality content in seconds." },
  { "step": 3, "icon": "ti-copy",      "title": "Copy or save",           "description": "Copy the output instantly or save it to your document library for later." }
]
```

Rendered as: horizontal step row (desktop) / vertical timeline (mobile). Icons from Tabler. Accent color matches tool's category color.

Admin edits steps inline in template editor: add/remove/reorder steps, edit icon/title/description per step.

#### 15.14.3 Usage Examples Section

Shows real input → output examples so users understand what to expect.

```json
[
  {
    "title": "Tech Product Description",
    "input": { "product_name": "MagSafe Wallet", "tone": "Professional", "length": "Medium" },
    "output": "Keep your essentials close with the MagSafe Wallet — engineered to attach instantly to any MagSafe-compatible iPhone..."
  }
]
```

Rendered as: tabbed cards (1–3 examples). Each card shows:
- Input fields as key-value chips
- Output in a styled preview box (truncated at 200 chars with "see full" expand)
- "Try this example" button → pre-fills the form with those input values

#### 15.14.4 FAQ Section (Per-Tool)

Stored as JSON on the template (not from global `faqs` table — tool-specific).

```json
[
  { "question": "How long will the generated blog post be?", "answer": "Depending on the length setting you choose: Short ≈100 words, Medium ≈300 words, Long ≈600 words, Very Long ≈1200 words." },
  { "question": "Can I use the output commercially?", "answer": "Yes. All AI-generated content is yours to use commercially without any restrictions." }
]
```

Rendered as accordion (Tiptap MinimalEditor for answers in admin).
Schema.org `FAQPage` JSON-LD injected automatically from this data (see 41.15).

#### 15.14.5 Review / Rating System

Simple star-rating + comment system tied to each AI tool.

**Table: `tool_reviews`**
```sql
id
template_slug     varchar(100) FK → ai_tools.slug
user_id           bigint FK → users.id
rating            tinyint            -- 1–5
comment           text NULL
is_approved       boolean DEFAULT false   -- admin must approve
is_featured       boolean DEFAULT false   -- show in featured slot
helpful_count     int DEFAULT 0
created_at, updated_at
```

**Rules:**
- Only logged-in users who have used the tool at least once can leave a review
- One review per user per tool (edit allowed within 30 days)
- Admin must approve before public display (or auto-approve — settings toggle)
- Aggregate: `avg_rating` and `review_count` cached in `ai_tools` table

**Add to `ai_tools`:**
```sql
avg_rating     decimal(3,2) DEFAULT 0.00
review_count   int DEFAULT 0
```

**Review UI on tool page:**

```
★★★★☆  4.3 out of 5   (127 reviews)
────────────────────────────────────
★★★★★  ████████████  68%
★★★★☆  ████████      18%
★★★☆☆  ████          9%
★★☆☆☆  ██            3%
★☆☆☆☆  █             2%
────────────────────────────────────
[Write a Review] ← only if user is logged in + has used tool

Featured reviews (2–3 shown):
  [Avatar] John D. — ★★★★★
  "This saved me 2 hours of writing..."
  [Helpful? 👍 12]

[Load more reviews]
```

**Livewire component `ToolReviews`:**
- Renders review list + rating distribution
- Submit review form (star selector + optional comment textarea)
- Helpful/not helpful toggle per review (stored in `tool_review_votes` table)
- Pagination (10 per page, "Load more" button)
- Sort: Most Recent / Most Helpful / Highest Rated / Lowest Rated

**Admin → AI Tools → Reviews:**
- List: tool, user, rating, comment, date, status (pending/approved)
- Approve / Reject / Feature / Delete
- Bulk approve
- Reply to review (admin reply shown below user comment)
- Auto-approve toggle in Admin → Settings → AI

---

### 15.15 Tool Page — SEO & Schema Markup

Every tool page gets **best-practice SEO** — meta tags, Open Graph, Twitter Card, and Schema.org JSON-LD all auto-generated from tool data.

#### 15.15.1 Meta Tags (auto-generated)

```php
// app/Services/AI/ToolSeoService.php

public function getMeta(AiTemplate $tool): array
{
    $name = settings('app_name');

    return [
        'title'           => $tool->meta_title
                          ?? "{$tool->name} — Free AI Tool | {$name}",
        'description'     => $tool->meta_description
                          ?? "Use {$tool->name} for free. {$tool->description} Powered by GPT-4o and Claude.",
        'keywords'        => "{$tool->name}, AI {$tool->category->name}, free AI tool, AI content generator",
        'canonical'       => url("/ai-tools/{$tool->slug}"),
        'og_title'        => $tool->meta_title ?? "{$tool->name} | {$name}",
        'og_description'  => $tool->meta_description ?? $tool->description,
        'og_image'        => $tool->og_image ?? settings('app_og_image'),
        'og_type'         => 'website',
        'twitter_card'    => 'summary_large_image',
    ];
}
```

Passed to Inertia shared props → rendered in `<head>` by `AppHead.vue` component.

#### 15.15.2 Schema.org JSON-LD (injected per tool page)

All schemas injected as `<script type="application/ld+json">` in `<head>`.

**1. SoftwareApplication schema** — makes Google show ratings in search results:

```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Blog Article Generator",
  "description": "Generate complete, SEO-optimized blog articles in seconds using AI.",
  "url": "https://yoursite.com/ai-tools/blog-article",
  "applicationCategory": "WebApplication",
  "operatingSystem": "Web",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "USD"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.3",
    "reviewCount": "127",
    "bestRating": "5",
    "worstRating": "1"
  },
  "featureList": [
    "AI-powered blog writing",
    "SEO optimization",
    "Multiple tones and languages",
    "Export to DOCX and PDF"
  ],
  "provider": {
    "@type": "Organization",
    "name": "MakeAI",
    "url": "https://yoursite.com"
  }
}
```

**2. FAQPage schema** — auto-generated from `faq_items` JSON (when `show_faqs = true`):

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How long will the generated blog post be?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Depending on the length setting: Short ≈100 words, Medium ≈300 words..."
      }
    }
  ]
}
```

**3. HowTo schema** — auto-generated from `how_it_works` JSON (when `show_how_it_works = true`):

```json
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "How to use Blog Article Generator",
  "step": [
    {
      "@type": "HowToStep",
      "position": 1,
      "name": "Fill in your details",
      "text": "Enter your topic, tone, and preferences in the form."
    }
  ]
}
```

**4. BreadcrumbList schema:**

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home",       "item": "https://yoursite.com" },
    { "@type": "ListItem", "position": 2, "name": "AI Tools",   "item": "https://yoursite.com/ai-tools" },
    { "@type": "ListItem", "position": 3, "name": "Blog & Content", "item": "https://yoursite.com/ai-tools/category/blog-content" },
    { "@type": "ListItem", "position": 4, "name": "Blog Article Generator" }
  ]
}
```

**`ToolSeoService::getSchemas(AiTemplate $tool): array`** — returns all applicable schemas as PHP arrays, encoded to JSON in `AppHead.vue`.

Star rating appears in Google search results only when:
- `review_count >= 5` (Google's minimum threshold)
- Schema is valid (test at schema.org/validator)
- Page is indexed

#### 15.15.3 Tool Page `<head>` Component

```vue
<!-- AppHead.vue — used on every tool page via Inertia Head -->
<template>
  <Head>
    <title>{{ meta.title }}</title>
    <meta name="description"        :content="meta.description" />
    <meta name="keywords"           :content="meta.keywords" />
    <link rel="canonical"           :href="meta.canonical" />

    <!-- Open Graph -->
    <meta property="og:title"       :content="meta.og_title" />
    <meta property="og:description" :content="meta.og_description" />
    <meta property="og:image"       :content="meta.og_image" />
    <meta property="og:url"         :content="meta.canonical" />
    <meta property="og:type"        :content="meta.og_type" />
    <meta property="og:site_name"   :content="appName" />

    <!-- Twitter Card -->
    <meta name="twitter:card"       :content="meta.twitter_card" />
    <meta name="twitter:title"      :content="meta.og_title" />
    <meta name="twitter:description":content="meta.og_description" />
    <meta name="twitter:image"      :content="meta.og_image" />

    <!-- JSON-LD Schemas (all of them) -->
    <component
      v-for="(schema, i) in schemas"
      :key="i"
      :is="'script'"
      type="application/ld+json"
      v-html="JSON.stringify(schema)"
    />
  </Head>
</template>
```

---

### 15.16 Tool Page — Full Layout (Updated)

```
URL: /ai-tools/blog-article
┌─────────────────────────────────────────────────────────────┐
│ Breadcrumb: Home > AI Tools > Blog & Content > Blog Article │
├─────────────────────────────────────────────────────────────┤
│ [Tool icon]  Blog Article Generator          ★★★★☆ 4.3(127)│
│              Generate complete SEO blog articles in seconds │
│              [Category badge]  [Free badge / Pro badge]     │
├─────────────────────┬───────────────────────────────────────┤
│   INPUT PANEL       │   OUTPUT PANEL                        │
│   (left, 400px)     │   (right, flex-1)                     │
│                     │                                       │
│   DynamicForm       │   StreamingText / rendered output     │
│                     │                                       │
│   [Generate]        │   [Copy][Save][Export▾][Edit]         │
│   ~0.8 credits      │   Word count  Reading time            │
└─────────────────────┴───────────────────────────────────────┘

Below generator (section visibility controlled per-tool from admin):

┌── About ────────────────────────────────────────────────────┐
│  Rich HTML content about the tool                           │
└─────────────────────────────────────────────────────────────┘

┌── How It Works ─────────────────────────────────────────────┐
│  [1. Fill form]  →  [2. AI generates]  →  [3. Copy/Save]   │
└─────────────────────────────────────────────────────────────┘

┌── Usage Examples ───────────────────────────────────────────┐
│  [Tab: Tech Product] [Tab: Food Brand] [Tab: SaaS App]      │
│  Input chips → Output preview → [Try this example]         │
└─────────────────────────────────────────────────────────────┘

┌── Frequently Asked Questions ───────────────────────────────┐
│  [Accordion FAQ items]                                      │
└─────────────────────────────────────────────────────────────┘

┌── User Reviews ─────────────────────────────────────────────┐
│  ★★★★☆ 4.3/5  (127 reviews)                                 │
│  [Rating distribution bar chart]                           │
│  [Write a Review — if eligible]                            │
│  [Review cards with helpful votes]                         │
│  [Load more]                                               │
└─────────────────────────────────────────────────────────────┘

┌── Related Tools ────────────────────────────────────────────┐
│  [Tool card] [Tool card] [Tool card]  (same category)       │
└─────────────────────────────────────────────────────────────┘
```

---

### 15.17 Admin Controls for Tool Page Sections

**Admin → AI Tools → Templates → Edit → "Page Content" tab:**

Per-tool toggles:
```
Show About section          [toggle]
Show How It Works           [toggle]
Show Usage Examples         [toggle]
Show FAQs                   [toggle]
Show Reviews                [toggle]
Show Related Tools          [toggle]
```

**Global defaults in Admin → Settings → AI:**
```
tool_page_show_about         boolean  true
tool_page_show_how_it_works  boolean  true
tool_page_show_usage         boolean  true
tool_page_show_faqs          boolean  true
tool_page_show_reviews       boolean  true
tool_page_auto_approve_reviews boolean false
tool_page_min_reviews_for_schema int  5
```

Per-tool setting overrides global default.

---

### 15.18 Checklist: Tool Page Content & SEO

- [ ] `ToolSeoService` generates correct title, description, canonical for every tool
- [ ] `SoftwareApplication` schema renders with correct `aggregateRating` when `review_count >= 5`
- [ ] `FAQPage` schema only rendered when `show_faqs = true` AND `faq_items` not empty
- [ ] `HowTo` schema only rendered when `show_how_it_works = true` AND steps exist
- [ ] `BreadcrumbList` schema always rendered on tool pages
- [ ] All 4 schemas validate at schema.org/validator with no errors
- [ ] Google Rich Results Test passes for both FAQ and SoftwareApplication schemas
- [ ] `avg_rating` and `review_count` on `ai_tools` updated after each review approval
- [ ] Users cannot review a tool they haven't used (check `ai_usage_logs`)
- [ ] One review per user per tool enforced at DB level (unique constraint: `user_id + template_slug`)
- [ ] Review auto-approve setting works (immediate vs pending)
- [ ] "Try this example" pre-fills form fields correctly for all field types
- [ ] Section visibility toggles work per-tool AND respect global defaults
- [ ] Tool OG image renders in Facebook/Twitter share preview (test with debugger tools)
- [ ] Inertia SSR renders all `<head>` tags server-side (critical for SEO crawlers)
- [ ] Related tools section shows 3 tools from same category, excluding current tool
- [ ] Admin review list: pending reviews show count badge in admin sidebar

---

### 15.19 Admin: Template Editor (Full Tabs)

Admin → AI Tools → Templates → Edit `[template-name]`

Full-screen editor with **5 tabs:**

```
┌──────────────────────────────────────────────────────────────┐
│  Edit Template: Blog Article Generator            [Save] [×] │
├──────────────────────────────────────────────────────────────┤
│  [Basic] [Prompts] [Fields] [Page Content] [SEO]             │
├──────────────────────────────────────────────────────────────┤

TAB 1 — Basic
  Name _________________  Slug ___________________
  Category [▾]  Icon [picker]  Color [color picker]
  Description [textarea — shown on tool card]
  Output type [▾]  Model override [▾]  Max tokens [__]
  Requires Pro [toggle]  Featured [toggle]  Active [toggle]
  Access level [▾]  Supports brand voice [toggle]
  Avg output tokens [__]  (used for credit estimate)

TAB 2 — Prompts
  System Prompt [monospace textarea, full width, resizable]
  User Prompt   [monospace textarea, full width, resizable]
  Available variables chip list (click to insert):
    {topic} {description} {tone} {language} {length}
    {audience} {keywords} {count} {format} {creativity}
    + any custom field names from Fields tab

TAB 3 — Fields
  [Drag-and-drop list of current fields]
  Each field row: [drag handle] [type badge] [label] [required?] [edit ✎] [delete ×]
  [+ Add Field] → type picker modal (grid of all 16 field types)
  Field edit modal: name, label, placeholder, required, default, options (for select)

TAB 4 — Page Content
  About section:       [toggle show]  [Tiptap MinimalEditor]
  How It Works:        [toggle show]  [Step builder: add/remove/reorder steps]
    Each step: icon picker, title, description
  Usage Examples:      [toggle show]  [Example builder: add/remove]
    Each example: title, input fields (key-value), output text
  FAQs:                [toggle show]  [FAQ builder: add/remove/reorder]
    Each FAQ: question input, answer (Tiptap MinimalEditor)
  Reviews:             [toggle show]

TAB 5 — SEO
  Meta title       [input + char count (60)]  [Auto-generate from name]
  Meta description [textarea + char count (160)]  [Auto-generate]
  OG image         [upload or use default]
  SERP preview:    [live preview box showing Google search result]
  Schema preview:  [read-only JSON display of all schemas that will be injected]
  [Validate Schema] button → opens schema.org/validator in new tab
```

**Test panel (always visible at bottom of editor):**
- Collapsible panel: "Test Generation"
- Fill test values for each field → [Run Test] → output appears in OutputPanel
- Uses admin API key, does not deduct credits, does not log to `ai_usage_logs`

---

### 15.20 Admin: AI Tools Stats Dashboard

Admin → AI Tools → Overview:

- **Usage chart** (Chart.js bar): top 10 most used tools (last 30 days)
- **Cost by tool** (Chart.js doughnut): AI spend per tool slug
- **Cost by provider** (Chart.js bar): OpenAI vs Anthropic vs Gemini etc.
- **Tokens over time** (Chart.js line): daily token usage last 30 days
- **Per-tool table**: name, category, total uses, total tokens, total cost USD, avg cost per use, avg rating, review count, last used
- **Slow tools alert**: tools where avg latency > 10s flagged in red
- **Top rated tools** widget: top 5 by avg_rating (min 5 reviews)
- Export all stats as CSV

---

### 15.21 Master Checklist: AI Tools (All Sections Combined)

**Core generation:**
- [ ] `DynamicForm.vue` renders all 16 field types correctly
- [ ] `tone_select` has all 8 tones, `language_select` loads from `languages` table
- [ ] `length_select` maps to correct word count instruction in `PromptBuilder`
- [ ] Streaming: tokens appear real-time, blinking cursor during stream
- [ ] POST-body streaming (`fetch` ReadableStream) — not GET `EventSource`
- [ ] `X-Accel-Buffering: no` header on all streaming responses (Nginx compatibility)
- [ ] Credit estimate shown before, actual cost shown after generation
- [ ] `TokenGuard::before()` blocks if daily/monthly/balance/global limit exceeded
- [ ] `TokenGuard::after()` always runs even if stream errors mid-way
- [ ] `ai_usage_logs` row inserted for every attempt (success AND fail)
- [ ] Output type routes correctly: markdown→marked.js, code→highlight.js, html→iframe
- [ ] Image output: download, save to library, regenerate all work
- [ ] TTS: audio player, voice preview, character limit counter all work
- [ ] Brand voice injected when user has it AND `supports_brand_voice = true`
- [ ] Personal API key used when user configured one for the provider
- [ ] Auto-save creates document when user setting enabled
- [ ] "Edit in Editor" opens Tiptap with correct content
- [ ] Public tools: output truncated, upsell shown, IP rate limit enforced
- [ ] Pro-gated tools: upgrade modal shown for free users
- [ ] Tool `usage_count` incremented via low-priority queue job

**Tool page content sections:**
- [ ] About section renders rich HTML correctly, hidden when `about_content` is null
- [ ] How It Works: horizontal (desktop) / vertical timeline (mobile)
- [ ] "Try this example" pre-fills all form fields correctly for every field type
- [ ] FAQ accordion opens/closes smoothly, all answers display
- [ ] Reviews: rating distribution bars calculate percentages correctly
- [ ] Users blocked from reviewing tools they haven't used
- [ ] One-review-per-user enforced (DB unique constraint + application layer)
- [ ] Helpful vote toggling works (no double-voting)
- [ ] `avg_rating` + `review_count` on template updated after every approval
- [ ] Section visibility toggles per-tool override global defaults correctly
- [ ] Related tools: shows 3 from same category, current tool excluded

**SEO & Schema:**
- [ ] `SoftwareApplication` schema renders with correct `aggregateRating` when `review_count >= 5`
- [ ] `FAQPage` schema only when `show_faqs = true` AND `faq_items` not empty
- [ ] `HowTo` schema only when `show_how_it_works = true` AND steps exist
- [ ] `BreadcrumbList` always present on tool pages
- [ ] All schemas pass schema.org/validator with zero errors
- [ ] Google Rich Results Test passes for SoftwareApplication + FAQ schemas
- [ ] Meta title under 60 chars, description under 160 chars for all 255 tools
- [ ] Canonical URL correct and unique per tool
- [ ] OG image renders in Facebook/Twitter share preview
- [ ] **Inertia SSR renders all `<head>` tags server-side** — critical for crawlers
- [ ] Auto-generated meta falls back gracefully if `meta_title`/`meta_description` null

**Admin template editor:**
- [ ] All 5 tabs save independently (no data loss when switching tabs)
- [ ] Step builder: drag-to-reorder, icon picker, all 3 fields editable
- [ ] FAQ builder: drag-to-reorder, Tiptap MinimalEditor for answers
- [ ] Usage example builder: key-value input pairs work for all field types
- [ ] SERP preview updates live as meta title/description is typed
- [ ] Schema preview shows correct JSON based on current field values
- [ ] Test generation: uses admin API key, no credit deduction, no usage log
- [ ] Variable chip list updates when new fields are added in Fields tab


---

---

## 🔷 LAYER 4 — CONTENT & CMS

## PART 16 — BLOG SYSTEM ✅

### 41.1 Blog Post Table

```sql
blog_posts
  id
  ulid                  char(26) UNIQUE
  author_id             bigint FK → admins.id
  title                 varchar(500)
  slug                  varchar(500) UNIQUE
  excerpt               text NULL
  content               longtext                  -- Tiptap HTML
  featured_image        varchar(500) NULL
  featured_image_alt    varchar(255) NULL
  status                enum('draft','published','scheduled','private') DEFAULT 'draft'
  published_at          timestamp NULL
  scheduled_at          timestamp NULL
  is_featured           boolean DEFAULT false
  is_sticky             boolean DEFAULT false      -- always shown at top of listing
  allow_comments        boolean DEFAULT true
  views_count           bigint DEFAULT 0
  reading_time          int DEFAULT 0             -- auto-calculated in minutes

  -- SEO
  meta_title            varchar(255) NULL
  meta_description      text NULL
  meta_keywords         varchar(500) NULL
  og_title              varchar(255) NULL
  og_description        text NULL
  og_image              varchar(500) NULL
  canonical_url         varchar(500) NULL
  schema_type           enum('Article','BlogPosting','NewsArticle') DEFAULT 'BlogPosting'
  no_index              boolean DEFAULT false

  -- Layout
  template              enum('default','full_width','sidebar_left','sidebar_right','no_sidebar') DEFAULT 'default'
  show_author           boolean DEFAULT true
  show_date             boolean DEFAULT true
  show_reading_time     boolean DEFAULT true
  show_share_buttons    boolean DEFAULT true
  show_related_posts    boolean DEFAULT true
  show_toc              boolean DEFAULT false      -- table of contents

  created_at, updated_at, deleted_at
```

### 41.2 Related Tables

**`blog_categories`**
```sql
id, name, slug, description, parent_id NULL FK (self), icon, color,
meta_title, meta_description, og_image,
is_active boolean DEFAULT true, sort_order int,
posts_count int DEFAULT 0,   -- cached counter
created_at, updated_at
```

**`blog_tags`**
```sql
id, name, slug, description,
meta_title, meta_description,
posts_count int DEFAULT 0,
created_at, updated_at
```

**`blog_post_categories`** (pivot)
```sql
post_id FK, category_id FK
```

**`blog_post_tags`** (pivot)
```sql
post_id FK, tag_id FK
```

**`blog_post_views`** — for unique view tracking
```sql
id, post_id FK, ip_address varchar(45), user_id NULL FK, viewed_at timestamp
```
Views tracked via Redis (increment on each hit, flush to DB hourly via scheduled job).

### 41.3 Blog Post Editor (Admin)

Admin → Content → Blog Posts → Create/Edit

**Layout:** Full-screen editor (like Notion/Ghost)
- Left: content editor area (Tiptap `FullEditor` variant)
- Right: collapsible settings panel (700px → slides open)

**Settings panel tabs:**

*General*
- Status selector (Draft / Published / Scheduled / Private)
- Publish date (date+time picker — shown when Scheduled)
- Featured image (upload + alt text)
- Excerpt (textarea, auto-generated from content if empty on publish)
- Author (dropdown — admin users)
- Reading time (auto-calculated, manual override)
- Sticky post toggle
- Featured post toggle
- Allow comments toggle

*Categories & Tags*
- Category multi-select (with create-new inline)
- Tags input (type + enter, autocomplete from existing tags, create new on-the-fly)

*SEO*
- Meta title (input + char count, 60 char limit)
- Meta description (textarea + char count, 160 char limit)
- Focus keyword (highlighted in content preview)
- SEO score meter (real-time: checks title contains keyword, meta length, content length, heading structure, image alt text) — traffic light indicator (red/yellow/green)
- OG title + OG description + OG image upload
- Canonical URL override
- Schema type selector
- No-index toggle
- Preview: how it appears in Google search results (SERP snippet preview)

*Layout*
- Template selector (with visual preview thumbnails)
- Show/hide: author, date, reading time, share buttons, related posts, TOC

**Auto-save:** Drafts auto-saved to DB every 60 seconds. Unsaved indicator in title bar.

**Revision history:** Every save creates a revision. Admin can view diff and restore any previous version. Last 50 revisions kept.

**AI Assist buttons in editor:**
- Generate title from content
- Generate excerpt from content
- Generate meta title + description
- Generate tags from content
- Improve selected paragraph
- Continue writing from cursor

### 41.4 Table of Contents (TOC)

When `show_toc = true`:
- Auto-generated from H2/H3 headings in post content
- Rendered as sticky sidebar widget on desktop
- Collapsible accordion on mobile
- Smooth scroll to heading on click
- Active heading highlighted as user scrolls (Intersection Observer)
- Admin can manually position TOC via shortcode `[toc]` inside content (renders inline instead of sidebar)

### 41.5 Related Posts Logic

When `show_related_posts = true`, shown at bottom of post:
- **Algorithm (priority order):**
  1. Posts sharing most tags with current post
  2. Posts in same primary category
  3. Recent posts as fallback
- Max 3 posts shown (configurable in settings)
- Display: card grid (thumbnail + title + date + reading time)
- Cached per post (Redis, TTL 24h, invalidated when post updated)

### 41.6 Blog Listing Page (`/blog`)

- Paginated grid (9 posts per page, configurable)
- Sticky post always first
- Featured posts shown with larger card
- Filter by category (sidebar or tabs)
- Filter by tag (tag cloud widget)
- Search (Livewire `LiveSearch` — searches title + excerpt + content)
- Sort: Latest / Most Popular / Most Commented
- RSS feed at `/blog/rss` (auto-generated, linked in `<head>`)
- Sitemap entries auto-generated (Laravel sitemap package)

### 41.7 Category & Tag Pages

`/blog/category/{slug}` — same layout as blog listing, filtered
`/blog/tag/{slug}` — same layout, filtered
Both show category/tag description at top, full SEO meta.

### 41.8 Admin Blog Management

**Admin → Content → Blog Posts:**
- Sortable table: title, status badge, categories, author, views, date
- Filters: status, category, author, date range, search
- Bulk actions: publish, draft, delete, add category, add tag
- Quick edit (inline): title, slug, status, date without opening editor
- Duplicate post
- Export posts CSV

**Admin → Content → Blog Categories:**
- Hierarchical tree view (parent → children)
- Inline edit name/slug
- Reorder via drag-and-drop
- Merge categories (moves all posts from one to another)

**Admin → Content → Blog Tags:**
- Table with post count
- Bulk delete unused tags
- Merge tags

**Admin → Settings → Blog:**
```
posts_per_page          int     9
related_posts_count     int     3
related_posts_algorithm varchar  tags_first / category_first / recent
auto_excerpt_length     int     160   (characters)
default_author          bigint  FK → admins.id
default_allow_comments  boolean true
rss_posts_count         int     20
show_reading_time       boolean true
words_per_minute        int     200   (for reading time calc)
blog_sidebar            boolean true
blog_sidebar_position   enum    right / left
```

---

## PART 17 — CUSTOM PAGES & CMS

### 18.1 Custom Page Builder ✅

**Table: `pages`**
```sql
id, title varchar(255), slug varchar(255) UNIQUE,
content longtext,                         -- WYSIWYG HTML content
excerpt text NULL,
meta_title varchar(255) NULL,
meta_description text NULL,
meta_keywords varchar(500) NULL,
og_image varchar(500) NULL,               -- social share image
template enum('default','full_width','blank','landing') DEFAULT 'default',
featured_image varchar(500) NULL,
show_title boolean DEFAULT true,
show_breadcrumbs boolean DEFAULT true,
show_featured_image boolean DEFAULT true,
show_sidebar boolean DEFAULT false,
sidebar_position enum('left','right') DEFAULT 'right',
container_width enum('default','wide','full','narrow') DEFAULT 'default',
status enum('draft','published','scheduled') DEFAULT 'draft',
published_at timestamp NULL,
password varchar(255) NULL,               -- password-protected pages
parent_id bigint NULL FK (self),
sort_order int DEFAULT 0,
is_system boolean DEFAULT false,          -- true for privacy, terms, contact (not deletable)
created_by bigint FK → admins.id,
created_at, updated_at, deleted_at
```

Content editor: **Tiptap** (same as AI Editor) with full toolbar — headings, bold, italic, lists, tables, images, embeds, code blocks, horizontal rule, YouTube embed.

Page options panel (right sidebar in editor):
- Template selector
- Container width override
- Show/hide: title, breadcrumbs, featured image, sidebar
- Featured image upload
- SEO fields (meta title, description, keywords)
- OG image upload
- Schedule publish
- Password protection
- Parent page (for hierarchy/breadcrumbs)

### 18.2 Default System Pages

Auto-created on install (seeded, `is_system = true`):
- `/privacy-policy` — Privacy Policy (default template content)
- `/terms-of-service` — Terms of Service
- `/contact` — Contact page (renders contact form)
- `/about` — About page (placeholder)
- `/faq` — FAQ page (accordion blocks)
- `/cookie-policy` — Cookie Policy

These pages are linked in footer automatically. Admin can edit content but cannot delete them.

### 18.3 Contact Form

**Table: `contact_messages`**
```sql
id, name, email, subject, message text, ip_address,
is_read boolean DEFAULT false, replied_at timestamp NULL,
created_at
```

**Livewire component `ContactForm`:**
- Fields: name, email, subject (dropdown or text, configurable), message
- Google reCAPTCHA v3 integration (site key in settings)
- Honeypot field for spam protection
- On submit: save to DB + send notification email to admin
- Auto-reply email to sender (optional, template in settings)
- Rate limiting: max 3 submissions per IP per hour

Admin → Messages: list, mark read, reply via email (reply form in admin, sends email + logs reply), delete, export CSV.

---


---


## PART 18 — CATEGORIES & ORGANIZATION

### 19.1 Category System ✅

**Table: `categories`**
```sql
id, name varchar(255), slug varchar(255) UNIQUE,
description text NULL, icon varchar(100) NULL,
color varchar(20) NULL,                    -- hex for category badge
parent_id bigint NULL FK (self),
type enum('ai_tool','blog','general') DEFAULT 'general',
sort_order int DEFAULT 0,
is_active boolean DEFAULT true,
meta_title, meta_description,
created_at, updated_at
```

Hierarchical (parent/child), max 2 levels deep.

### 19.2 AI Tools Category Assignment

AI tools (templates, chatbots, generators) are assignable to categories.

**`ai_tool_category`** pivot: `ai_tool_id`, `category_id`

Admin → AI Tools → Categories:
- Create/edit/delete categories
- Reorder via drag-and-drop
- Assign icon (Tabler icon picker) and color
- Category page at `/ai-tools/{category-slug}` — lists all tools in that category

Frontend:
- AI tools grid on dashboard filterable by category
- Category sidebar widget in AI tools section
- Featured categories section on homepage (configurable — select which categories to show)

---

## PART 19 — TESTIMONIALS & FAQS

### 28.1 Testimonials ✅

**Table: `testimonials`**
```sql
id, name varchar(255), role varchar(255) NULL,
company varchar(255) NULL, avatar varchar(500) NULL,
content text, rating tinyint DEFAULT 5,
is_active boolean DEFAULT true, is_featured boolean DEFAULT false,
sort_order int DEFAULT 0, source enum('manual','google','trustpilot','import') DEFAULT 'manual',
created_at, updated_at
```

Admin → Content → Testimonials:
- Add/edit/delete testimonials
- Upload avatar photo
- Star rating (1–5)
- Featured toggle (shown in homepage slider)
- Drag-and-drop sort order
- Bulk import from CSV
- AI Generate button: inputs company type + tone → AI generates realistic testimonials for demo/seeding purposes

### 28.2 FAQs ✅

**Table: `faqs`**
```sql
id, question varchar(500), answer text,
category_id bigint NULL FK → faq_categories.id,
is_active boolean DEFAULT true, sort_order int DEFAULT 0,
created_at, updated_at
```

**Table: `faq_categories`**
```sql
id, name varchar(255), slug varchar(100), sort_order int, created_at
```

Admin → Content → FAQs:
- Add/edit/delete FAQs
- Assign to category
- Drag-and-drop reorder within category
- AI Generate button: input topic/product → AI generates relevant FAQ set (5–20 questions)
- Bulk import from CSV
- FAQs usable in: homepage section, `/faq` page, per-page FAQ widget

---

## PART 20 — RICH TEXT EDITOR (Full Tiptap) ✅

The AI Editor and all rich text areas use **Tiptap v2** with a comprehensive extension set. No feature cut — full word-processor capability.

### 32.1 Full Extension List

**Text Formatting:**
- Bold, Italic, Underline, Strikethrough
- Subscript, Superscript
- Inline Code
- Highlight (multiple colors via color picker)
- Text color (full color picker)
- Background/mark color
- Clear formatting

**Headings & Structure:**
- Headings H1–H6
- Paragraph
- Horizontal Rule
- Hard Break
- Blockquote (nestable)

**Lists:**
- Bullet list (unordered)
- Ordered list (numbered)
- Task list (checkboxes — interactive)
- List indent/outdent
- Nested lists

**Tables:**
- Insert table (row × column picker)
- Add/delete row above/below
- Add/delete column left/right
- Merge cells
- Split cells
- Toggle header row/column
- Table background color per cell
- Resizable columns

**Media:**
- Image upload (drag-and-drop or file picker → uploads to storage)
- Image from URL
- Image resize (drag handles)
- Image alignment: left / center / right / float
- Image alt text
- Image caption
- Video embed (YouTube/Vimeo URL → auto-embed as iframe)
- File attachment (upload → renders as downloadable link)

**Code:**
- Inline code
- Code block with syntax highlighting (lowlight — supports 30+ languages)
- Language selector for code blocks
- Copy code button on code blocks

**Links:**
- Insert/edit link (URL, title, target: _blank/_self)
- Unlink
- Auto-link detection (paste URL → auto-converts)
- Link preview on hover

**Advanced:**
- Text alignment: left / center / right / justify
- Line height options
- Font family selector (Google Fonts: Inter, Roboto, Georgia, Courier New, and more)
- Font size (px selector: 12–72px)
- Column layout: 2-col / 3-col drag-and-drop content blocks
- Details/Summary (accordion/spoiler block)
- Emoji picker (native emoji, search by name)
- Mention (@username autocomplete)
- Slash commands (`/` triggers command palette: type to search and insert any block)
- Drag handle per block (drag to reorder any block)

**History:**
- Undo / Redo (keyboard shortcuts: Ctrl+Z / Ctrl+Y)
- Version history (autosave every 30s, last 20 versions, restore any version)

**Export:**
- Export as: DOCX (using `docx` npm package), PDF (via `html2pdf.js`), Markdown, Plain Text, HTML
- Copy as Markdown button
- Copy as HTML button

**Word / Character Count:**
- Live word count + character count in status bar
- Reading time estimate

**AI Sidebar (within editor):**
- Selected text → AI actions: Improve, Shorten, Expand, Rephrase, Translate, Change tone, Summarize, Fix grammar, Continue writing
- No selection → full-document actions: Summarize, Generate title, Generate meta description
- AI output appears in diff view: accept / reject / accept partial

### 32.2 Toolbar Layout

**Main toolbar (always visible):**
```
[Undo][Redo] | [H1][H2][H3][¶] | [B][I][U][S] | [Color][Highlight] |
[AlignL][AlignC][AlignR][AlignJ] | [BulletList][OrderedList][TaskList] |
[Link][Image][Table][Code] | [Quote][HR] | [AI✨] | [WordCount] | [Export▼]
```

**Floating toolbar (appears on text selection):**
```
[B][I][U][S][Code] | [Link] | [Color] | [AI: Improve▾]
```

**Bubble menu (appears near selection for quick actions):**
- Bold, Italic, Link, AI Improve (most common actions)

**Slash command palette (`/`):**
- Groups: Text, Headings, Lists, Media, Embeds, Advanced
- Search filter
- Keyboard navigation

### 32.3 Editor Variants

Three size/feature variants of the same Tiptap instance:

| Variant | Used in | Features |
|---------|---------|---------|
| `FullEditor` | AI Writer, Blog posts, Custom pages | All features above |
| `CommentEditor` | Comments, support tickets | Bold, Italic, Link, Code, Lists only |
| `MinimalEditor` | Short descriptions, FAQ answers | Bold, Italic, Link only — single toolbar row |

Configured via `variant` prop: `<RichTextEditor variant="full" v-model="content" />`

---


---


---

## 🔷 LAYER 5 — COMMUNICATION

## PART 21 — MAIL SYSTEM ✅

### 22.1 Mail Configuration (Admin → Mail → Configuration)

**Settings group: `mail`** — stored in `settings` table:

```
mail_driver        enum: smtp / mailgun / ses / postmark / sendgrid / log / array
mail_host          varchar
mail_port          int
mail_username      varchar
mail_password      encrypted
mail_encryption    enum: tls / ssl / null
mail_from_address  varchar
mail_from_name     varchar

// Driver-specific
mailgun_domain     varchar
mailgun_secret     encrypted
mailgun_endpoint   varchar (api.mailgun.net / api.eu.mailgun.net)

ses_key            encrypted
ses_secret         encrypted
ses_region         varchar

postmark_token     encrypted
sendgrid_api_key   encrypted
```

Admin UI — Mail → Configuration:
- Driver selector (tabs: SMTP / Mailgun / SES / Postmark / SendGrid)
- Per-driver form fields (show/hide based on selected driver)
- **Test Email** button — sends a test email to admin's email address, shows success/fail toast with SMTP error message if failed
- "Apply & Reconnect" button — writes to `.env` + runs `php artisan config:clear`

### 22.2 Mail Templates (Admin → Mail → Templates)

All mail templates stored in DB, fully editable by admin. System uses DB template if exists, falls back to default file-based template.

**Table: `mail_templates`**
```sql
id
slug          varchar(100) UNIQUE        -- machine identifier e.g. 'email_verify_otp'
name          varchar(255)               -- human label e.g. 'Email Verification OTP'
subject       varchar(500)               -- supports variables: {site_name}, {user_name}, etc.
content       longtext                   -- HTML body, supports variables
is_active     boolean DEFAULT true
is_system     boolean DEFAULT true       -- system templates cannot be deleted, only edited
requires_pro  boolean DEFAULT false      -- if true, only shown/sent when isProAvailable()
category      enum('auth','account','subscription','newsletter','custom')
last_edited_by bigint NULL FK → admins.id
created_at, updated_at
```

### 22.3 Predefined System Templates

Seeded on install. All have `is_system = true`.

**Category: auth**

| Slug | Name | requires_pro |
|------|------|-------------|
| `email_verify_otp` | Email Verification OTP | false |
| `reset_password_otp` | Reset Password OTP | false |
| `login_otp` | Login OTP (2FA via email) | false |

**Category: account**

| Slug | Name | requires_pro |
|------|------|-------------|
| `welcome` | Welcome / New Account Created | false |
| `password_changed` | Password Changed Notification | false |
| `email_changed` | Email Address Changed | false |
| `account_suspended` | Account Suspended | false |
| `account_activated` | Account Activated | false |
| `credits_added` | Credits Added to Account | false |
| `credits_low` | Low Credit Balance Warning | false |
| `referral_earned` | Referral Commission Earned | false |
| `admin_announcement` | Admin Announcement / Broadcast | false |

**Category: subscription** *(only visible + sent when `isProAvailable() === true`)*

| Slug | Name | requires_pro |
|------|------|-------------|
| `subscription_started` | Subscription Started / Welcome to Plan | true |
| `subscription_renewed` | Subscription Renewed Successfully | true |
| `subscription_expiring_soon` | Subscription Expiring Soon (3 days) | true |
| `subscription_expired` | Subscription Expired | true |
| `subscription_canceled` | Subscription Canceled | true |
| `subscription_upgraded` | Plan Upgraded | true |
| `subscription_downgraded` | Plan Downgraded | true |
| `subscription_payment_failed` | Payment Failed / Retry Notice | true |
| `subscription_trial_started` | Trial Started | true |
| `subscription_trial_expiring` | Trial Expiring Soon (1 day) | true |
| `subscription_trial_ended` | Trial Ended — Upgrade Now | true |
| `invoice_paid` | Invoice / Receipt | true |

**Category: newsletter**

| Slug | Name | requires_pro |
|------|------|-------------|
| `newsletter_confirm` | Newsletter Subscription Confirmation (double opt-in) | false |
| `newsletter_unsubscribed` | Unsubscribe Confirmation | false |
| `newsletter_campaign` | Newsletter Campaign (base template) | false |

### 22.4 Custom Templates

Admin → Mail → Templates → "New Template" button

Creates a custom template (`is_system = false`, `category = 'custom'`).

Custom templates can be:
- Used as newsletter campaign base
- Sent manually to selected users (Admin → Users → select → "Send Email" → pick template)
- Called programmatically via `MailTemplateService::send($slug, $user, $data)`

### 22.5 Template Editor UI

Admin → Mail → Templates → click any template → full-screen editor:

**Left panel — Editor:**
- Subject line input (with variable chips: click to insert)
- **Visual HTML editor** (Quill or TipTap in HTML mode) — WYSIWYG
- **Raw HTML tab** — switch between visual and code view
- Variable reference panel: shows all available variables for this template with descriptions

**Right panel — Preview:**
- Live preview rendered inside `<iframe>` with the global mail layout wrapper
- "Preview as user" — fills variables with dummy data
- Mobile/Desktop toggle for preview width
- "Send Test Email" button — sends to admin's email with dummy variables filled

**Global mail layout wrapper** (`resources/views/emails/layout.blade.php`):
- Site logo (from settings)
- Header background color (configurable in Mail → Settings)
- Content area
- Footer: site name, address (from settings), unsubscribe link (auto-appended to marketing emails)
- Social icons (from social settings)
- Admin can edit the global wrapper too (Admin → Mail → Layout)

### 22.6 Available Variables per Template

Variables wrapped in `{variable_name}` syntax, replaced by `MailTemplateService` before sending.

**Global variables (available in all templates):**
```
{site_name}         {site_url}          {site_logo_url}
{support_email}     {current_year}      {unsubscribe_url}
```

**User variables:**
```
{user_name}         {user_email}        {user_avatar_url}
{user_credits}      {user_plan}         {referral_code}
{referral_link}
```

**OTP / Auth variables:**
```
{otp_code}          {otp_expires_in}    {reset_link}
{verify_link}       {ip_address}        {device}
{location}
```

**Subscription variables (requires_pro templates):**
```
{plan_name}         {plan_price}        {billing_cycle}
{next_billing_date} {cancel_link}       {upgrade_link}
{invoice_number}    {invoice_url}       {amount_paid}
{currency}          {trial_ends_at}     {expiry_date}
```

**Credits variables:**
```
{credits_added}     {credits_balance}   {transaction_id}
{credits_threshold}
```

### 22.7 MailTemplateService

**`app/Services/MailTemplateService.php`**

```php
class MailTemplateService
{
    public function send(
        string $slug,
        User|Admin $recipient,
        array $data = [],
        ?string $overrideEmail = null
    ): void
    // 1. Fetch template by slug from DB (cached)
    // 2. Check is_active — skip if false
    // 3. Check requires_pro — skip if !isProAvailable()
    // 4. Replace variables in subject + content
    // 5. Wrap in global layout
    // 6. Dispatch SendTemplatedEmail job (queued on 'emails' queue)

    public function preview(string $slug, array $dummyData = []): string
    // Returns rendered HTML for preview iframe

    public function getVariables(string $slug): array
    // Returns list of available variables for editor hint panel

    public function sendBulk(string $slug, Collection $users, array $data = []): void
    // Dispatches bulk send job (batched, 50 per chunk)
}
```

**`app/Jobs/SendTemplatedEmail.php`** — implements `ShouldQueue`, uses `emails` queue, retries 3 times with exponential backoff.

### 22.8 Admin Mail Menu Structure

```
Mail
├── Configuration          (driver setup, test send)
├── Templates
│   ├── Auth               (verify otp, reset otp, login otp)
│   ├── Account            (welcome, password changed, credits, etc.)
│   ├── Subscription       (only visible if isProAvailable())
│   ├── Newsletter         (confirm, unsubscribe, campaign base)
│   └── Custom             (admin-created templates)
├── Layout Editor          (global email wrapper HTML/CSS)
└── Mail Logs              (sent emails log: to, subject, template, status, sent_at)
```

**Table: `mail_logs`**
```sql
id, template_slug, recipient_email, recipient_name,
subject varchar(500), status enum('sent','failed','bounced'),
error_message text NULL, sent_at timestamp, created_at
```

Admin can view mail logs, resend failed emails, search by recipient/template/date.

---

## PART 22 — NEWSLETTER SYSTEM ✅

Extends Part 16 with Mailchimp sync and popup functionality.

### 34.1 Newsletter Drivers

Admin → Mail → Newsletter → Driver:
- **Internal** (default) — uses `newsletter_subscribers` table + configured mail driver
- **Mailchimp** — syncs to Mailchimp list, campaigns sent from Mailchimp
- **Both** — stores locally AND syncs to Mailchimp

**Mailchimp settings:**
```
mailchimp_api_key       encrypted
mailchimp_server_prefix varchar   -- e.g. us1, us21
mailchimp_list_id       varchar   -- audience/list ID
mailchimp_double_optin  boolean
mailchimp_tags          varchar   -- default tags for new subscribers (comma-separated)
```

When Mailchimp is active:
- New subscriber → added to Mailchimp list via API (`POST /3.0/lists/{id}/members`)
- Unsubscribe → updated in Mailchimp (`PATCH` status to `unsubscribed`)
- Campaigns sent via Mailchimp dashboard (MakeAI just manages the list)
- OR: MakeAI campaign → `POST /3.0/campaigns` → create + send via Mailchimp API

### 34.2 Popup Newsletter Form

**Livewire component `NewsletterPopup`:**

Trigger options (admin configurable per popup):
- **Exit intent** — detects mouse leaving viewport (desktop)
- **Time delay** — appears after X seconds (configurable)
- **Scroll depth** — appears after X% page scroll
- **Page views** — appears on Nth page view per session
- **First visit only** — never shown again after dismiss (cookie 30 days)

Popup settings (Admin → Mail → Newsletter → Popup):
- Enable/disable popup
- Trigger type + trigger value
- Title + description text
- Input placeholder text
- Submit button text
- Success message
- Background image or color
- Show/hide on mobile toggle
- Cookie duration (days) after dismiss
- Don't show to logged-in subscribers toggle

**Implementation:** Livewire component included in main layout, visible=false by default. JS event listeners trigger Livewire dispatch to show modal. Form submission handled server-side by Livewire.

### 34.3 Inline Newsletter Form

Embeddable via `@livewire('newsletter-subscribe')` anywhere:
- Horizontal layout (email input + button side by side) — for headers/heroes
- Vertical layout (stacked) — for sidebar/footer
- Layout auto-detected based on container width or explicitly set via prop

Admin can place the newsletter section via Homepage Builder (see Part 31) and Sidebar Builder (see Part 20.4).

---


---


## PART 23 — IN-APP NOTIFICATIONS (Reverb) ✅

### 30.1 Architecture

Real-time notifications for both admin panel and user dashboard using **Laravel Echo + Laravel Reverb** (first-party WebSocket server, built into Laravel 11+).

Fallback: if no WebSocket server configured → **polling mode** (Livewire polls every 30s).

Admin settings → Notifications:
- Driver: reverb / pusher / polling
- Reverb credentials: REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST, REVERB_PORT (auto-filled from .env by installer)
- Pusher credentials (optional alternative): app_id, key, secret, cluster
- Test connection button

### 30.2 Notification Table

**Table: `notifications`** — uses Laravel's default notifications table structure:
```sql
id          uuid PK
type        varchar(255)           -- e.g. App\Notifications\NewUserRegistered
notifiable_type varchar(255)       -- App\Models\User or App\Models\Admin
notifiable_id   bigint
data        json                   -- notification payload
read_at     timestamp NULL
created_at, updated_at
```

### 30.3 User Notifications

Events that trigger notifications to users:

| Event | Notification |
|-------|-------------|
| Credits added | "✅ {amount} credits added to your account" |
| Credits low (threshold) | "⚠️ Your credits are running low ({balance} remaining)" |
| Credits exhausted | "❌ You've run out of credits. Top up to continue." |
| Subscription started | "🎉 Welcome to {plan_name}! Your subscription is active." |
| Subscription renewing soon | "📅 Your subscription renews in 3 days" |
| Subscription expired | "⚠️ Your subscription has expired" |
| Payment successful | "💳 Payment of {amount} received. Invoice: #{id}" |
| Payment failed | "❌ Payment failed for your subscription. Please update billing." |
| Referral earned | "🎁 You earned {amount} credits from a referral!" |
| Admin announcement | Custom message from admin broadcast |
| Document processing complete | "✅ Your document '{name}' has been processed" |
| Video/Image generation complete | "✅ Your {type} is ready to view" |
| Password changed | "🔐 Your password was changed. Wasn't you? Secure your account." |
| New login from new device | "🔐 New login detected from {city}, {country}" |

### 30.4 Admin Notifications

Events that trigger notifications to admins (all admins or specific roles):

| Event | Notification | Role |
|-------|-------------|------|
| New user registered | "👤 New user: {name} ({email})" | all |
| New payment received | "💰 New payment: {amount} from {user}" | all |
| Payment failed | "❌ Payment failed: {user}" | all |
| New support ticket | "🎫 New ticket: '{subject}' from {user}" | support |
| New contact form message | "✉️ New contact: '{subject}' from {email}" | all |
| License expiring soon | "⚠️ License re-verification failed — {days} days grace period remaining" | super_admin |
| Update available | "🆕 MakeAI v{version} is available. Update now." | super_admin |
| Server health warning | "🚨 Health check failed: {check_name}" | super_admin |
| High AI cost alert | "💸 Daily AI spend reached {threshold}% of budget" | super_admin |
| New comment pending | "💬 New comment pending moderation" | content |

### 30.5 Notification Bell UI

**User panel bell:**
- Badge with unread count (red dot if >0)
- Dropdown: last 10 notifications with icon, message, time ago, read/unread state
- Click notification → mark as read + navigate to relevant page
- "Mark all as read" button
- "View all notifications" link → `/notifications` page (full list, filterable, paginated)

**Admin panel bell:**
- Same structure but shows admin notifications
- Separate from user notifications

### 30.6 Send Notification from Admin

Admin → Users → [User] → "Send Notification" button:
- Title + message input
- Type: info / success / warning / error (affects icon + color)
- Deliver via: in-app only / in-app + email / email only
- Schedule: now / specific datetime

Admin → Content → Announcements → "Broadcast Notification":
- Send to: all users / users on specific plan / specific user IDs (comma separated) / users by email list
- Same form as above

---

## PART 24 — SUPPORT TICKET SYSTEM ✅

### 42.1 Tables

**`support_departments`**
```sql
id, name, slug, description, email NULL,   -- reply-from email for this dept
assigned_role_id NULL FK → admin_roles.id,  -- auto-assign tickets to this role
is_active boolean DEFAULT true, sort_order int, created_at
```

Default departments seeded: General, Technical, Billing, Feature Request.

**`support_tickets`**
```sql
id
ticket_number     varchar(20) UNIQUE    -- e.g. TKT-2024-00042 (auto-generated)
user_id           bigint FK → users.id
department_id     bigint FK → support_departments.id
assigned_to       bigint NULL FK → admins.id
subject           varchar(500)
status            enum('open','in_progress','waiting_user','resolved','closed') DEFAULT 'open'
priority          enum('low','medium','high','urgent') DEFAULT 'medium'
source            enum('web','email','api') DEFAULT 'web'
first_response_at timestamp NULL         -- SLA tracking
resolved_at       timestamp NULL
closed_at         timestamp NULL
last_reply_at     timestamp NULL
last_reply_by     enum('user','admin') NULL
satisfaction_rating tinyint NULL        -- 1-5 stars (user rates after resolve)
satisfaction_comment text NULL
created_at, updated_at
```

**`support_ticket_replies`**
```sql
id
ticket_id         bigint FK
author_type       enum('user','admin')
author_id         bigint               -- user_id or admin_id
content           longtext             -- Tiptap CommentEditor HTML
attachments       json NULL            -- array of file paths
is_internal_note  boolean DEFAULT false  -- admin-only notes, not visible to user
is_ai_draft       boolean DEFAULT false  -- flagged as AI-suggested reply
created_at, updated_at, deleted_at
```

**`support_ticket_attachments`**
```sql
id, ticket_id FK, reply_id NULL FK, file_name, file_path, file_size, mime_type, uploaded_by_type, uploaded_by_id, created_at
```

**`support_canned_responses`**
```sql
id, title, content, department_id NULL FK, created_by FK → admins.id, usage_count int DEFAULT 0, created_at, updated_at
```

### 42.2 User-Side Ticket Flow

**`/support`** — user support portal:
- "New Ticket" button
- List of own tickets: ticket number, subject, department, status badge, last updated, unread reply indicator
- Filter by status

**Create ticket form:**
- Subject
- Department selector
- Priority (user can select low/medium/high — urgent only by admin)
- Message (Tiptap CommentEditor — bold, italic, link, lists, code, image paste)
- Attachments (drag-and-drop, max 5 files, 10MB each, allowed: jpg/png/gif/pdf/txt/zip/mp4)
- CAPTCHA if guest (guests cannot create tickets — login required)

**Ticket detail page `/support/tickets/{number}`:**
- Timeline view of all replies (user + admin interleaved)
- Admin internal notes not shown
- Reply form at bottom (same editor)
- Attachment upload in reply
- Status shown in header
- "Mark as Resolved" button (user can self-resolve)
- Satisfaction rating modal appears when status changes to resolved
- Unread replies highlighted with accent border

### 42.3 Admin-Side Ticket Management

**Admin → Support → All Tickets:**
- Kanban view (columns: Open / In Progress / Waiting User / Resolved) OR table view (toggle)
- Columns: #, subject, user, department, priority badge, status badge, assigned to, last reply, SLA timer
- Filters: status, priority, department, assigned admin, date range, search
- Bulk actions: assign to, change status, change priority, delete
- Color-coded priority rows
- SLA breach indicator (red timer when response overdue)
- Unread replies badge on each ticket

**Ticket detail (admin):**
- Full reply timeline
- Internal notes tab (admin-only — yellow background, lock icon)
- "AI Suggest Reply" button:
  1. sends ticket subject + all replies to AI
  2. AI generates suggested reply
  3. Shown in editor as draft (flagged `is_ai_draft = true`)
  4. Admin edits if needed + sends
- Canned responses: dropdown in editor toolbar → search + insert predefined response
- Assign to admin dropdown
- Change status + priority inline
- Merge ticket (merge duplicate into another)
- Ban user from creating tickets
- Internal note form (separate input below main reply form)
- Attachment preview inline (images), download link (files)

**Admin → Support → Departments:** CRUD + auto-assignment rules

**Admin → Support → Canned Responses:** CRUD, searchable in ticket editor

**Admin → Support → Settings:**
```
tickets_enabled              boolean  true
guest_tickets                boolean  false
max_attachments_per_reply    int      5
max_attachment_size_mb       int      10
allowed_attachment_types     varchar  jpg,png,pdf,txt,zip
auto_close_resolved_days     int      7     -- auto-close resolved tickets after N days
sla_first_response_hours     int      24
sla_resolution_hours         int      72
notify_admin_new_ticket      boolean  true
notify_user_reply            boolean  true
satisfaction_rating_enabled  boolean  true
ai_reply_suggestion          boolean  true
```

**SLA tracking:**
- First response SLA: `first_response_at` must be set within `sla_first_response_hours`
- Visual countdown timer in admin ticket list
- Overdue = red, approaching = amber, ok = green
- SLA report in Admin → Reports

---

## PART 25 — ANNOUNCEMENT SYSTEM ✅

### 29.1 Announcement Types

Three announcement channels, each manageable from Admin → Content → Announcements:

**1. Top Bar Announcement (Sitewide Banner)**

Sticky bar above navigation. Settings:
- Message text (supports HTML links)
- Background color + text color
- Show close button toggle (dismissible per session)
- CTA button: text + URL (optional)
- Target audience: all / guests only / logged-in only / specific plan
- Schedule: start_at + end_at (or always on)
- Icon (Tabler icon, optional)

**2. Popup Announcement / Promotion Modal**

Full overlay modal. Settings:
- Title + content (rich text)
- Image upload (banner image inside modal)
- Primary CTA button: text + URL
- Secondary CTA / close link text
- Trigger: on page load / on exit intent / after X seconds / after X% scroll
- Show frequency: every visit / once per session / once per user (stored in DB for logged-in)
- Target: all / guests / logged-in / specific plan
- Schedule: start_at + end_at
- Cookie duration (days) for dismissal

**3. In-App Notification Announcements**
→ See Part 34 (In-App Notifications)

**Table: `announcements`**
```sql
id
type            enum('topbar','popup','notification')
title           varchar(255) NULL
content         text NULL
bg_color        varchar(20) NULL
text_color      varchar(20) NULL
cta_text        varchar(100) NULL
cta_url         varchar(500) NULL
image           varchar(500) NULL
target_audience enum('all','guests','auth','free','pro') DEFAULT 'all'
trigger_type    varchar(50) NULL          -- for popup: onload/exit/delay/scroll
trigger_value   varchar(50) NULL          -- delay seconds or scroll %
show_frequency  enum('always','session','once') DEFAULT 'session'
is_active       boolean DEFAULT true
starts_at       timestamp NULL
ends_at         timestamp NULL
created_by      bigint FK → admins.id
created_at, updated_at
```

---


---


---


---

## 🔷 LAYER 6 — MONETIZATION

## PART 26 — SUBSCRIPTION SYSTEM (Pro)

Only rendered/accessible when `isProAvailable() === true`.

### Plans Table
```sql
id, name, slug, description, price_monthly, price_yearly, price_lifetime,
credits_monthly (how many credits per billing period),
features json (array of feature flags),
is_featured boolean, is_active boolean, trial_days int DEFAULT 0,
sort_order int, created_at, updated_at
```

### Payment Gateways
Each gateway has its own Service class in `app/Services/Payment/`:
- **Stripe** — subscriptions + one-time, webhooks for status sync (using laravel cashier)
- **PayPal** — subscriptions + one-time
- **Paddle** — handles VAT automatically (using laravel cashier)
- **Razorpay** — India market
- **SSLCommerz** — Bangladesh market
- **CoinGate** — crypto payments
- **Paystack** — Africa market
- **Bank Transfer** — manual, admin approval
- **2Checkout / Verifone**

Each gateway implements `PaymentGatewayInterface`:
```php
interface PaymentGatewayInterface
{
    public function createSubscription(Plan $plan, User $user, array $paymentData): SubscriptionResult;
    public function createOneTimePayment(float $amount, string $currency, User $user): PaymentResult;
    public function cancelSubscription(string $subscriptionId): bool;
    public function handleWebhook(Request $request): void;
    public function refund(string $transactionId, float $amount): bool;
}
```

### Subscription Logic
- User subscribes → credits allocated → `subscription_status = 'active'`
- Monthly/yearly renewal via gateway webhook → credits refill
- Cancellation → access until `subscription_ends_at`
- Trial period → `subscription_status = 'trialing'`, `trial_ends_at` set
- Feature flags from plan stored on user session for fast access
- Coupon system: admin creates coupons (% or fixed, one-time or recurring, expiry date)
- Affiliate/Referral: configurable commission % on referred user's first purchase

---

## PART 27 — AFFILIATE & REFERRAL SYSTEM ✅

### 43.1 Tables

**`affiliate_programs`** — global settings (one row)
```sql
id
is_active                boolean DEFAULT false
commission_type          enum('percentage','fixed') DEFAULT 'percentage'
commission_value         decimal(8,2) DEFAULT 20    -- 20% or fixed amount
commission_on            enum('first_purchase','all_purchases','subscription') DEFAULT 'first_purchase'
cookie_days              int DEFAULT 30             -- referral cookie lifetime
min_payout               decimal(10,2) DEFAULT 20  -- min balance to request payout
payout_methods           json                       -- ['paypal','bank_transfer','credits']
auto_approve_commissions boolean DEFAULT false
commission_hold_days     int DEFAULT 14             -- days before commission is withdrawable
created_at, updated_at
```

**`affiliate_referrals`**
```sql
id
referrer_id              bigint FK → users.id
referred_id              bigint FK → users.id
referral_code            varchar(20)
ip_address               varchar(45)
landed_at                timestamp              -- when referred user first visited via link
converted_at             timestamp NULL         -- when they registered
created_at
```

**`affiliate_commissions`**
```sql
id
referrer_id              bigint FK → users.id
referred_id              bigint FK → users.id
order_id                 bigint NULL FK → payments.id
amount                   decimal(10,4)           -- commission amount in platform currency
status                   enum('pending','approved','paid','rejected','cancelled') DEFAULT 'pending'
approved_at              timestamp NULL
paid_at                  timestamp NULL
notes                    text NULL
created_at, updated_at
```

**`affiliate_payouts`**
```sql
id
user_id                  bigint FK → users.id
amount                   decimal(10,2)
method                   enum('paypal','bank_transfer','credits')
status                   enum('pending','processing','paid','rejected') DEFAULT 'pending'
payout_details           json                    -- PayPal email / bank details
admin_note               text NULL
processed_by             bigint NULL FK → admins.id
processed_at             timestamp NULL
created_at, updated_at
```

### 43.2 User Affiliate Dashboard (`/affiliate`)

**Overview cards:**
- Total earnings (all time)
- Pending earnings (approved, not yet paid)
- Available for withdrawal
- Total referrals (registered)
- Successful conversions (made a purchase)
- Conversion rate %

**Referral link section:**
- Unique referral URL: `https://site.com/register?ref={code}`
- One-click copy button
- QR code generator (downloadable PNG)
- Social share buttons (Facebook, X, WhatsApp, LinkedIn, Telegram)
- Custom alias option: `https://site.com/ref/{custom-slug}` (if enabled in settings)

**Performance chart (Chart.js):**
- Line chart: clicks vs registrations vs conversions (last 30 days)
- Toggle: daily / weekly / monthly view

**Referrals table:**
- Referred user (masked: `j***@gmail.com`), date joined, status (registered/purchased), commission earned
- Paginated, last 50 entries

**Commissions table:**
- Date, order amount, commission amount, status badge (pending/approved/paid)
- Filter by status

**Payout section:**
- Current available balance (large display)
- "Request Payout" button (disabled if below minimum)
- Payout request form: amount, method selector, details (PayPal email / bank info)
- Payout history table: date, amount, method, status

**Marketing materials section:**
- Banner images (uploaded by admin) available to download
- Email templates for promoting the platform (pre-written, copyable)
- Pre-written social media posts (copyable)

**Terms & conditions** (configurable text from admin settings)

### 43.3 Admin Affiliate Management

**Admin → Reports → Affiliate:**
- Overview stats: total affiliates, total commissions paid, pending payouts, top earners
- Affiliates table: user, referral code, total referrals, total commissions, status
- Commission approval queue (if `auto_approve = false`)
- Payout requests table: approve/reject/process with note

**Admin → Settings → Affiliate:**
- All `affiliate_programs` fields editable
- Upload marketing banners (multiple sizes: 728×90, 300×250, 160×600)
- Write pre-made promotional email templates
- Write pre-made social posts
- Affiliate terms & conditions (rich text)
- Enable/disable affiliate program globally (toggle shows/hides `/affiliate` from user menu)

### 43.4 Referral Tracking Mechanics

1. User visits `/register?ref=CODE` → cookie set (`affiliate_ref=CODE`, duration = `cookie_days` days)
2. User registers → `affiliate_referrals` row created, `users.referred_by` set
3. Referred user makes a purchase → `affiliate_commissions` row created with `status=pending`
4. If `auto_approve = true` → immediately `status=approved`, added to referrer's `referral_earnings`
5. After `commission_hold_days` → commission becomes withdrawable (available balance)
6. Referrer requests payout → `affiliate_payouts` row created → admin processes manually or via PayPal Payouts API

---

## PART 28 — ADS SYSTEM ✅

Admin can place ads in predefined zones across the frontend. Fully controllable from admin panel.

### 14.1 Ad Zones

Predefined zones (hardcoded in layout, rendered via `@ads('zone_slug')`):
- `header_banner` — below top navigation (728×90 leaderboard)
- `sidebar_top` — top of right sidebar
- `sidebar_bottom` — bottom of right sidebar
- `content_top` — above page content
- `content_bottom` — below page content
- `between_posts` — between blog post list items (every N posts, configurable)
- `between_ai_tools` — between ai tools index items (every N items, configurable)
- `tool_page_top` — top of tool page (above content below title hero)
- `tool_page_bottom` — bottom of tool page (below content before tabs)
- `temaplate_page` — top of template page (above content below title hero)
- `chat_banner` — above AI chat input box
- `dashboard_top` — top of user dashboard
- `footer_banner` — above footer
- `custom_zone_1` — custom zone 1
- `custom_zone_2` — custom zone 2
`
### 14.2 Ad Types

**Table: `ads`**
```sql
id, zone varchar(100), type enum('adsense','custom_html','image_link'),
title varchar(255),                      -- admin reference only
adsense_client varchar(100) NULL,        -- ca-pub-xxxxxxxx
adsense_slot varchar(50) NULL,           -- ad unit slot ID
adsense_format varchar(50) NULL,         -- auto, rectangle, etc.
custom_html longtext NULL,               -- arbitrary HTML/JS
image_url varchar(500) NULL,             -- for image_link type
link_url varchar(500) NULL,
link_target enum('_blank','_self') DEFAULT '_blank',
is_active boolean DEFAULT true,
show_to enum('all','guests','logged_in','free_users','paid_users') DEFAULT 'all',
start_at timestamp NULL,
end_at timestamp NULL,
impressions int DEFAULT 0,
clicks int DEFAULT 0,
sort_order int DEFAULT 0,
created_at, updated_at
```

### 14.3 Global AdSense Setup

In admin Settings → Ads:
- AdSense Publisher ID (ca-pub-xxxx) — stored once, used for all adsense-type ads
- Auto ads toggle (injects AdSense auto ads script globally)
- Disable ads for subscribed users toggle (if `isProAvailable()`)
- Disable ads for specific plan IDs

### 14.4 Blade Directive

```php
// AppServiceProvider registers:
Blade::directive('ads', function ($zone) {
    return "<?php echo app(\App\Services\AdsService::class)->render($zone); ?>";
});
```

`AdsService::render()` — queries active ad for zone, checks `show_to` against current user, increments impressions (async job), returns rendered HTML.

Click tracking: image/link ads wrap in `/ads/click/{id}?redirect={url}` route that increments clicks then redirects.

---


---


---

## 🔷 LAYER 7 — FRONTEND & APPEARANCE

## PART 29 — FRONTEND ARCHITECTURE ✅

### 7.1 Setup

- **Inertia.js** with **SSR enabled** (`@inertiajs/server` + Node.js SSR server or `php artisan inertia:start-ssr`)
- **Vue 3 Composition API** with `<script setup>` throughout — no Options API
- **TypeScript** for all Vue files
- **Tailwind CSS v4** — custom design tokens in `tailwind.config.ts`
- **Pinia** for global state (user, credits, notifications)
- **VueUse** for composables (useLocalStorage, useClipboard, useDark, etc.)

### 7.2 Layouts

- `GuestLayout.vue` — for login, register, landing pages
- `AppLayout.vue` — main user dashboard (sidebar + header + content)
- `AdminLayout.vue` — admin panel (separate sidebar + header)
- `MinimalLayout.vue` — for chatbot embed, full-screen chat

### 7.3 Key Components

- `AiEditor.vue` — Tiptap-based rich text editor with AI sidebar
- `StreamingText.vue` — renders SSE stream token by token
- `CreditDisplay.vue` — live credit balance with usage bar
- `ModelSelector.vue` — dropdown to pick AI model (filtered by plan)
- `DocumentManager.vue` — folder tree + file list
- `ChatInterface.vue` — full chat UI (messages, input, attachments, model selector)
- `ImageGrid.vue` — masonry grid for generated images
- `PricingTable.vue` — plans comparison (only if `isProAvailable()`)

### 7.4 Dark Mode

Fully supported. Uses Tailwind `dark:` classes + VueUse `useDark()`. Preference stored in DB (`users.theme_preference`) and synced to `localStorage`.

### 7.5 i18n in Vue

```typescript
// composables/useTranslate.ts
export function useTranslate() {
  const translations = usePage().props.translations as Record<string, string>
  const t = (key: string, replace?: Record<string, string>): string => {
    let text = translations[key] ?? key
    if (replace) {
      Object.entries(replace).forEach(([k, v]) => { text = text.replace(`:${k}`, v) })
    }
    return text
  }
  return { t }
}
// Usage: const { t } = useTranslate(); t('Welcome back, :name', { name: user.name })
```

Translations are passed from `HandleInertiaRequests` as shared props (only current language, cached).

---

## PART 30 — LOCALIZATION OF VUE COMPONENTS ✅

### 30.1 Architecture Overview

MakeAI uses a **hybrid localization approach:**
- Backend: `translate()` PHP helper (DB-based, admin-editable)
- Frontend: `useTranslate()` Vue composable (translations passed via Inertia shared props)
- Dates/Numbers/Currency: `Intl` API (locale-aware, no extra library needed)

All three must be consistent — if backend sends `created_at` as a timestamp, frontend formats it using the user's locale, not hardcoded `en-US`.

---

### 30.2 Translation Flow (Backend → Frontend)

**`HandleInertiaRequests.php` shared props:**
```php
public function share(Request $request): array
{
    $locale = $request->user()?->language ?? session('locale', settings('default_language', 'en'));
    $language = Language::where('code', $locale)->first();

    return [
        ...parent::share($request),
        'locale' => [
            'code'       => $locale,                          // 'bn', 'ar', 'en'
            'name'       => $language?->name ?? 'English',
            'is_rtl'     => $language?->is_rtl ?? false,
            'date_format'=> $language?->date_format ?? 'MMM D, YYYY',
            'time_format'=> $language?->time_format ?? 'h:mm A',
            'number_format' => [
                'decimal'   => $language?->decimal_separator ?? '.',
                'thousands' => $language?->thousands_separator ?? ',',
            ],
        ],
        'translations' => TranslationService::getForLocale($locale), // cached
        'currency' => [
            'code'    => settings('default_currency', 'USD'),
            'symbol'  => settings('currency_symbol', '$'),
            'position'=> settings('currency_position', 'before'), // before | after
            'decimals'=> (int) settings('currency_decimals', 2),
        ],
    ];
}
```

**`TranslationService::getForLocale(string $locale): array`**
- Fetches all `translations` rows for the locale
- Returns flat key→value array: `{ "Welcome back": "স্বাগত জানাই", "Generate": "তৈরি করুন" }`
- Cached in Redis: `makeai:translations:{locale}` TTL 24h
- Falls back to English for any missing key

---

### 30.3 `useTranslate` Composable

```typescript
// composables/useTranslate.ts
import { usePage } from '@inertiajs/vue3'

export function useTranslate() {
    const page = usePage()
    const translations = computed(() =>
        (page.props.translations as Record<string, string>) ?? {}
    )

    // t('Welcome back, :name', { name: 'John' }) → 'স্বাগত জানাই, John'
    const t = (key: string, replace?: Record<string, string | number>): string => {
        let text = translations.value[key] ?? key
        if (replace) {
            Object.entries(replace).forEach(([k, v]) => {
                text = text.replace(new RegExp(`:${k}`, 'g'), String(v))
            })
        }
        return text
    }

    return { t }
}

// Usage in any Vue component:
// const { t } = useTranslate()
// t('Generate')                              → 'তৈরি করুন'
// t('Hello, :name', { name: user.name })    → 'হ্যালো, John'
// t('Used :count credits', { count: 5 })    → '৫ ক্রেডিট ব্যবহার হয়েছে'
```

**Global registration** in `app.ts` — available as `$t` in all templates:
```typescript
app.config.globalProperties.$t = t
// Template usage: {{ $t('Generate') }}
```

---

### 30.4 RTL Support

When `locale.is_rtl === true` (Arabic, Persian, Urdu, Hebrew):

**`app.vue` root layout:**

<html
  :lang="$page.props.locale.code"
  :dir="$page.props.locale.is_rtl ? 'rtl' : 'ltr'"
  :class="{ rtl: $page.props.locale.is_rtl }"
>


**Tailwind RTL classes** — use `rtl:` variant throughout:
```html
<!-- Sidebar: left on LTR, right on RTL -->
<aside class="left-0 rtl:left-auto rtl:right-0">

<!-- Icon before text: flips on RTL -->
<span class="mr-2 rtl:mr-0 rtl:ml-2">

<!-- Text alignment -->
<p class="text-left rtl:text-right">

<!-- Flex direction -->
<div class="flex-row rtl:flex-row-reverse">

<!-- Padding/margin -->
<div class="pl-4 rtl:pl-0 rtl:pr-4">
```

**Tailwind config — enable RTL variant:**
```typescript
// tailwind.config.ts
export default {
  plugins: [require('tailwindcss-rtl')],
  // or native Tailwind v4 rtl: support
}
```

**CSS custom property for directional values:**
```css
/* In theme-variables.css */
:root          { --start: left;  --end: right; }
[dir="rtl"]    { --start: right; --end: left;  }

/* Usage */
.sidebar { border-inline-end: 1px solid var(--border-color); }
.input-icon { inset-inline-start: 0.75rem; }
```

Using CSS logical properties (`margin-inline-start`, `padding-inline-end`, `inset-inline-start`) wherever possible — these auto-flip for RTL without any class changes.

---

### 30.5 Date & Time Formatting

**Composable `useDateFormat.ts`:**
```typescript
import { usePage } from '@inertiajs/vue3'

export function useDateFormat() {
    const locale = computed(() => usePage().props.locale)

    // Format: "Jan 15, 2025"
    const formatDate = (date: string | Date): string => {
        return new Intl.DateTimeFormat(locale.value.code, {
            year: 'numeric', month: 'short', day: 'numeric'
        }).format(new Date(date))
    }

    // Format: "2:30 PM"
    const formatTime = (date: string | Date): string => {
        return new Intl.DateTimeFormat(locale.value.code, {
            hour: 'numeric', minute: '2-digit', hour12: true
        }).format(new Date(date))
    }

    // Format: "Jan 15, 2025 at 2:30 PM"
    const formatDateTime = (date: string | Date): string => {
        return new Intl.DateTimeFormat(locale.value.code, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        }).format(new Date(date))
    }

    // Relative: "2 hours ago", "3 days ago"
    const formatRelative = (date: string | Date): string => {
        const rtf = new Intl.RelativeTimeFormat(locale.value.code, { numeric: 'auto' })
        const diff = (new Date(date).getTime() - Date.now()) / 1000
        const units: [Intl.RelativeTimeFormatUnit, number][] = [
            ['year', 31536000], ['month', 2592000], ['week', 604800],
            ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]
        ]
        for (const [unit, seconds] of units) {
            if (Math.abs(diff) >= seconds) {
                return rtf.format(Math.round(diff / seconds), unit)
            }
        }
        return rtf.format(0, 'second')
    }

    return { formatDate, formatTime, formatDateTime, formatRelative }
}

// Usage:
// formatDate('2025-01-15')          → 'জানু ১৫, ২০২৫' (Bengali) | 'Jan 15, 2025' (English)
// formatRelative('2025-01-15T10:00')→ '২ ঘণ্টা আগে' (Bengali) | '2 hours ago' (English)
```

**Bengali/Arabic numerals:** `Intl.DateTimeFormat` and `Intl.NumberFormat` automatically use native numerals for locales that use them (Bengali: ০১২৩৪৫৬৭৮৯, Arabic: ٠١٢٣٤٥٦٧٨٩) — no manual conversion needed.

---

### 30.6 Number & Currency Formatting

**Composable `useNumberFormat.ts`:**
```typescript
export function useNumberFormat() {
    const { locale, currency } = usePage().props

    // Format: 1,234,567.89 or ১২,৩৪,৫৬৭.৮৯ (Bengali lakh system)
    const formatNumber = (value: number, decimals = 0): string => {
        return new Intl.NumberFormat(locale.code, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value)
    }

    // Format: $29.99 | ৳ 2,999 | €29,99
    const formatCurrency = (value: number, currencyOverride?: string): string => {
        const code = currencyOverride ?? currency.code
        try {
            return new Intl.NumberFormat(locale.code, {
                style: 'currency',
                currency: code,
                minimumFractionDigits: currency.decimals,
                maximumFractionDigits: currency.decimals,
            }).format(value)
        } catch {
            // Fallback for currencies Intl doesn't know
            const formatted = value.toFixed(currency.decimals)
            return currency.position === 'before'
                ? `${currency.symbol}${formatted}`
                : `${formatted} ${currency.symbol}`
        }
    }

    // Format large numbers: 1.2K, 45.3K, 1.2M
    const formatCompact = (value: number): string => {
        return new Intl.NumberFormat(locale.code, {
            notation: 'compact', compactDisplay: 'short'
        }).format(value)
    }

    // Credit balance: always 2 decimal places
    const formatCredits = (value: number): string => formatNumber(value, 2)

    return { formatNumber, formatCurrency, formatCompact, formatCredits }
}

// Usage:
// formatCurrency(29.99)        → '$29.99' | '৳30.00' | '€29,99'
// formatCompact(125400)        → '125K' | '১.২৫ লাখ' (Bengali)
// formatCredits(145.5)         → '145.50'
```

---

### 30.7 Languages Table — Additional Columns

```sql
ALTER TABLE languages ADD COLUMN
  date_format          varchar(50)  DEFAULT 'MMM D, YYYY',
  time_format          varchar(50)  DEFAULT 'h:mm A',
  decimal_separator    char(1)      DEFAULT '.',
  thousands_separator  char(1)      DEFAULT ',',
  number_system        varchar(20)  DEFAULT 'latn'   -- latn | beng | arab
```

Admin → Settings → Languages → Edit Language:
- These columns editable per language
- "Test formatting" preview: shows how 1234567.89 and current date look with selected settings

---

### 30.8 Language Switcher Component

```vue
<!-- LanguageSwitcher.vue -->
<!-- Shown in: top nav, user settings, onboarding, mobile menu footer -->
<template>
  <div class="lang-switcher">
    <button @click="open = !open" class="btn-ghost btn-sm">
      {{ currentLang.flag }} {{ currentLang.name }}
      <ChevronDown :size="14" />
    </button>
    <div v-if="open" class="lang-dropdown">
      <button
        v-for="lang in languages"
        :key="lang.code"
        @click="switchLanguage(lang.code)"
        :class="{ active: lang.code === currentLocale }"
      >
        {{ lang.flag }} {{ lang.name }}
        <Check v-if="lang.code === currentLocale" :size="14" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const switchLanguage = async (code: string) => {
    // For guests: sets session via POST /locale
    // For logged-in: updates users.language via PATCH /user/language
    // Then Inertia.reload() to refresh shared props with new translations
    await axios.post('/locale', { language: code })
    router.reload({ only: ['translations', 'locale'] })
}
</script>
```

**Route:**
```php
Route::post('/locale', fn(Request $r) => session(['locale' => $r->language]))->name('locale.switch');
Route::patch('/user/language', [UserController::class, 'updateLanguage'])->middleware('auth');
```

Language switch takes effect **without full page reload** — Inertia partial reload of `translations` and `locale` shared props is enough.

---

### 30.9 Admin Translation Manager

Admin → Settings → Languages → Translations:

**Two views:**

**1. Per-language view:**
- Select language from dropdown
- Table: Original (English) | Translation | Last updated | Edit button
- Filter: Untranslated only / All / Search by key
- Inline edit: click translation cell → input appears → Enter to save
- **AI Auto-translate button:** selects all untranslated keys → sends to DeepL or GPT-4o → fills translations in bulk (admin reviews before saving)
- Import JSON (overwrite or merge)
- Export JSON for offline editing

**2. All-languages matrix view:**
- Rows: translation keys | Columns: each active language
- Good for spotting which languages have gaps
- Color: green = translated, red = missing, yellow = auto-translated (needs review)

**Sync command:**
```bash
php artisan translations:sync
# Scans all PHP/Vue files for translate() and t() calls
# Adds any new keys to translations table (with null value for non-English languages)
# Removes keys that no longer exist in codebase
```

---

### 30.10 Checklist: Localization

- [ ] All user-facing strings in Vue use `$t()` or `t()` — zero hardcoded English
- [ ] All user-facing strings in Blade/PHP use `translate()` — zero hardcoded English
- [ ] RTL layout tested with Arabic: sidebar on right, icons flipped, text right-aligned
- [ ] CSS logical properties used for directional spacing (no `ml-`, `pr-` in RTL-sensitive components)
- [ ] `Intl.DateTimeFormat` used for all dates — no manual `format()` calls
- [ ] Bengali locale shows Bengali numerals in dates and numbers automatically
- [ ] `formatRelative()` outputs locale-native strings ("২ ঘণ্টা আগে" for Bengali)
- [ ] `formatCurrency()` fallback works for BDT (Intl doesn't always handle ৳ correctly)
- [ ] Language switch: partial Inertia reload (no full page refresh)
- [ ] Language preference saved to DB for logged-in users, session for guests
- [ ] `translations:sync` command finds all `t()` calls in `.vue` files (not just PHP)
- [ ] AI auto-translate reviewed before publish (admin confirmation step)
- [ ] Translation cache invalidated when admin saves new translations
- [ ] Onboarding language picker works before user sets language in settings
- [ ] Email templates sent in user's language (MailTemplateService reads `user->language`)

---


---


## PART 31 — APPEARANCE & DESIGN SYSTEM ✅

### 16.1 Custom Color Scheme & Typography

**Separate configuration for Admin panel and each Frontend Theme.**

**Table: `appearance_settings`**
```sql
id, scope enum('admin','theme_default','theme_sleek',...),
key varchar(100), value text, created_at, updated_at
```

**Admin Appearance Settings (Admin → System → Admin Panel):**

Colors:
- Primary color (sidebar active, buttons, links)
- Sidebar background color
- Sidebar text color
- Top navbar background
- Top navbar text color
- Accent/highlight color

Typography:
- Admin font family (dropdown: Inter, Poppins, DM Sans, Nunito, Plus Jakarta Sans + popular languages 1/2 standard font e.g for bangla noto sans bengali/hind siliguri... — loaded from Google Fonts or self-hosted)
- Base font size (13px / 14px / 15px ... )
- Font weight for headings (400/500)

Live preview: changes reflected in real-time via CSS custom properties injected in `<head>` from `appearance_settings`.

**Frontend Theme Appearance Settings (Admin → Appearance → Theme -> settings -> Typography)**

Colors:
- Primary color
- Secondary color
- Accent color
- Background color (page bg)
- Surface color (cards, panels)
- Text primary color
- Text secondary color
- Link color
- Button color + hover
- Header background
- Footer background

Typography:
- Body font family
- Heading font family (can differ from body)
- Base font size
- Heading weight
- Line height
- Letter spacing (normal/tight/wide)

All values injected as CSS custom properties in `:root {}` via a dynamic `GET /css/theme-variables.css` route (cached, invalidated on settings change).

### 16.2 Frontend Container Width & Background

**Per-theme settings:**
- Site container width: `full` (100%), `boxed` (1080px) `xl` (1280px), `2xl` (1536px), `custom` (px value input)
- Content area max-width override
- Page background: solid color picker, gradient (2-color picker + direction), image upload
- Background image settings: cover/contain/repeat, fixed/scroll, position (center/top/bottom)
- Card/panel background color
- Border radius preset: `sharp` (0px), `soft` (8px), `rounded` (16px), `pill` (9999px) — affects all buttons and cards globally

### 16.3 Live Search System

**Livewire component `LiveSearch`:**

```
Trigger: user types 3+ characters in global search bar (debounce 300ms)
```

Searches across (configurable per category in settings):
- AI tools/templates (by name + description)
- Blog posts (by title + excerpt)
- Pages (by title)
- Prompt library (by title + content)
- Users (admin only — by name/email)

Results grouped by type, shown in dropdown below search bar.

Keyboard navigation: arrow up/down to select, Enter to go, Escape to close.

**`app/Http/Livewire/LiveSearch.php`** — uses `FULLTEXT` MySQL index on searchable columns, falls back to `LIKE` if fulltext not available.

Highlighted matched text in results (wraps matched portion in `<mark>` tag).

Click on result → navigate to relevant page.

---

## PART 32 — HOMEPAGE BUILDER ✅

Admin → Site builder → Homepage

Visual section-based homepage builder. The homepage is composed of draggable sections — enable, disable, reorder, and configure each section from admin panel. No code editing required.

### 27.1 Available Sections

Each section has its own settings panel:

**Hero Section**
- Layout preset: centered / left-aligned / split (text left + image right) / video background
- Headline (supports `{app_name}` variable)
- Subheadline / description
- Primary CTA button: text, link, style
- Secondary CTA button: text, link, style
- Background: color / gradient / image upload / video URL
- Hero image/illustration upload (right side for split layout)
- Animated typing text (multiple phrases that cycle)
- Show trust badges row (e.g. "Trusted by 10,000+ users")
- Statistics counters row (e.g. "1M+ words generated", "50K+ users") — animated count-up on scroll

**Features Section**
- Section title + subtitle
- Layout: 3-column grid / 2-column / alternating left-right
- Feature items (unlimited): icon (Tabler), title, description, optional image/screenshot
- CTA below features

**AI Tools Showcase**
- Section title + subtitle
- Display mode: category tabs + tool grid / featured tools carousel / all tools masonry
- Max items to show (configurable)
- "View All Tools" link

**How It Works**
- Section title
- Steps (numbered): icon, title, description
- Layout: horizontal steps / vertical timeline

**Pricing Section**
- Show/hide (only if `isProAvailable()` or admin wants to show custom pricing)
- Toggle: show plans from DB or show custom pricing table (static)
- Billing cycle toggle (monthly/yearly)
- Highlighted plan (most popular badge)

**Testimonials Section**
- Section title
- Display mode: slider / grid / masonry
- Testimonials source: from `testimonials` table (admin-managed) or manual entries
- Show rating stars toggle
- Show avatar toggle

**FAQ Section**
- Section title
- FAQs source: from `pages` FAQ page / from `faqs` table / manual entries in section
- Layout: accordion / two-column accordion
- Max items to show

**Stats / Social Proof Bar**
- Counter items: icon, number (animated), label
- Background: colored / transparent
- Logo cloud: upload partner/client logos displayed in a row (grayscale, hover colored)

**CTA Banner Section**
- Headline + subheadline
- Primary + secondary buttons
- Background: solid color / gradient / image
- Full-width or contained

**Blog / Latest Posts Section**
- Section title
- Number of posts to show
- Layout: 3-column grid / list / featured first

**Newsletter Section**
- Section title + description
- Embeds Livewire `NewsletterSubscribe` component
- Background style

**Integration / Technology Logos**
- Section title (e.g. "Powered by leading AI models")
- Upload logos with optional tooltip names
- Display as scrolling ticker or static grid

**Custom HTML Section**
- Full HTML/JS/CSS block — for any custom embed or content

### 27.2 Section Management UI

- Drag-and-drop reorder
- Each section has: enable/disable toggle, "Edit" gear icon, "Delete" button (for non-core sections)
- "Add Section" button → modal with section type picker (card grid with previews)
- Live preview button → opens homepage in new tab
- Mobile preview toggle in editor (simulates 390px viewport)
- Save publishes immediately (no draft mode for homepage)

### 27.3 Homepage Settings (General)

- SEO: meta title, meta description, OG image override for homepage
- Preloader: enable/disable, upload preloader animation (Lottie JSON or GIF)
- Scroll-to-top button: enable/disable, position (right/left), show after X px scroll
- Cookie consent banner: enable/disable, message text, accept button text, link to cookie policy
- Chat widget embed: paste third-party chat widget code (Tawk.to, Crisp, Intercom, etc.)

---


---


## PART 33 — MENU, HEADER, FOOTER & SIDEBAR BUILDERS

### 39.1 Collapsible Sidebar Menu Architecture

Both the **admin panel sidebar** and the **frontend main navigation** use an accordion-style collapsible menu system. State (open/closed per group) is persisted in `localStorage` so the user's preferred open/closed state survives page navigation and refresh.

**Admin sidebar Vue component `AdminSidebar.vue`:**

```typescript
// Each menu group is a collapsible accordion item
interface MenuGroup {
  label: string
  icon: string           // Tabler icon class
  route?: string         // if set, clicking label navigates (no children)
  children?: MenuItem[]  // if set, clicking label toggles collapse
  badge?: string         // e.g. 'Pro', 'New', unread count
  permission?: string    // hide group if admin lacks permission
  proOnly?: boolean      // hide if !isProAvailable()
}

// Collapse behavior:
// - Click parent with children → toggles open/closed with smooth CSS transition (max-height animation)
// - Active child route → parent auto-expands on page load
// - localStorage key: `admin_menu_state` → { users: true, content: false, ... }
// - "Collapse all" button at bottom of sidebar
// - Sidebar itself collapsible to icon-only mode (mini sidebar) — toggle button at top
//   In mini mode: hover on icon shows tooltip with group label + flyout submenu
```

**Mini sidebar (icon-only) mode:**
- Toggle button (hamburger ↔ chevron) at top of sidebar
- Collapsed width: 64px — shows only icons
- Hover on any icon → flyout panel appears to the right with full submenu list
- State stored in `localStorage` as `admin_sidebar_collapsed: true/false`
- On mobile (<1024px): sidebar is always hidden by default, toggled via top bar hamburger → slides in as overlay drawer

**Frontend main menu collapsible (for mega menu / dropdown):**
- Top-level items with children → hover (desktop) or click (mobile) reveals dropdown
- Mobile: hamburger opens offcanvas → all items with children have expand/collapse chevron
- Active item and its parent both get `active` class
- Smooth CSS transition (`max-height` + `opacity`) for all collapses

### 39.3 Menu Behavior Rules

- **Auto-expand active group:** On any page load, the sidebar group containing the current route is automatically expanded. All others remain in their last `localStorage` state.
- **Nested groups (2-level deep):** `Announcements`, `Mail → Templates`, `Appearance → Colors & Typography`, `Settings → Integrations` support one level of nested collapsing within the parent group. Max nesting depth: 2.
- **Permission gating:** Menu items are filtered client-side (Vue `v-if`) AND server-side (middleware) based on admin role permissions. Items the admin cannot access are completely hidden — not just disabled.
- **Pro gating:** Items/groups with `proOnly: true` are hidden entirely when `isProAvailable() === false`. They do not appear as locked/greyed — they simply don't exist in the menu.
- **Badge system:**
  - Red dot / number badge: unread notifications count on bell icon, pending comments count on Comments, pending support tickets
  - "Pro" amber badge: on Plans & Billing group label and Subscription template item
  - "New" green badge: on menu items for features added in last release (configurable via `menu_badges` setting)
  - "1 update" blue badge: on Updates item when `update_available` setting is true
- **Smooth animation:** collapse/expand uses CSS `max-height` transition (300ms ease-in-out), not `display:none` toggle. Chevron icon rotates 180° when open.
- **Keyboard accessible:** Arrow keys navigate menu items, Enter activates, Escape closes open group.

### 39.4 Frontend Main Menu Collapsible

**Desktop (≥1024px):**
- Top navigation bar with horizontal menu items
- Items with children → on hover: dropdown appears (200ms delay, 150ms fade-in)
- Mega menu items → full-width panel with columns (built in Header Builder)
- Active page item and its parent both highlighted

**Mobile (<1024px):**
- Hamburger button in top bar → slides in offcanvas from left (300ms)
- Overlay backdrop (click to close)
- All menu items listed vertically
- Items with children → chevron on right → tap to expand/collapse (accordion)
- Only one group open at a time (clicking another closes the current)
- Close button (×) at top of offcanvas
- Language switcher + dark mode toggle at bottom of offcanvas

**State management for frontend menu (Vue composable `useMenu.ts`):**
```typescript
// Tracks which mobile menu group is open
const openGroup = ref<string | null>(null)
const toggle = (slug: string) => {
  openGroup.value = openGroup.value === slug ? null : slug
}
// Desktop dropdown open state managed by CSS :hover + small JS delay
```

---


---


## PART 34 — SOCIAL FEATURES

### 20.1 Social Share Buttons ✅

**`app/View/Components/SocialShare.php`** + Vue component `SocialShare.vue`

Supported networks (each toggleable in admin settings):
- Facebook
- X / Twitter
- LinkedIn
- WhatsApp
- Telegram
- Pinterest
- Reddit
- Email (mailto link)
- Copy link (clipboard)

Display styles (configurable per placement): icon only, icon + count, icon + label.

Share count display: uses each platform's public share count API where available (Facebook Graph API — requires app token, others estimated or hidden if API unavailable).

Placements: blog posts, AI-generated content (share your generated text/image), custom pages.

### 20.2 Social Follow Counters ✅

Admin → Settings → Social Media:
- Add social profile URLs: Facebook page, X/Twitter, Instagram, LinkedIn, YouTube, TikTok, GitHub, Discord invite
- For each: toggle display, enable follower count fetch
- Follower count API keys: Facebook Graph API token, YouTube Data API key (others use unofficial methods with fallback to manual count entry)
- Manual count override: admin can enter counts manually if API unavailable
- Auto-refresh interval: every 24h (cron job)

**`social_follow_counts`** table: `platform`, `count bigint`, `updated_at`

**Social Follow Widget** (usable in sidebar, footer):
- Display mode: icons row / icon + count / full card per platform
- Filter: show only platforms with accounts configured

---


---


---

## 🔷 LAYER 8 — ADMIN PANEL

## PART 35 — ADMIN DASHBOARD & SYSTEM TOOLS

### 15.1 Site Health Monitor  ✅

Admin → System → Site Health

Checks displayed as pass/warn/fail cards:

**Server:**
- PHP version (required 8.3+)
- Required PHP extensions (curl, zip, gd, mbstring, pdo, redis, fileinfo, tokenizer, xml, etc.)
- `storage/` and `bootstrap/cache/` writable
- Max upload size (recommend 64MB+)
- Max execution time (recommend 120s+)

**Application:**
- APP_KEY set
- APP_DEBUG = false in production (warn if true)
- Queue worker running (check Horizon status via Redis)
- Scheduler last ran (check `last_scheduler_run` in settings, updated by `php artisan schedule:run`)
- Cache driver = redis (warn if file/array)
- Session driver = redis (warn if file)

**Services:**
- Database connection (ping)
- Redis connection (ping)
- Mail: test SMTP connection
- AI providers: ping each configured provider API (show latency)
- Storage disk: local/S3 write test

**License:**
- License valid / expiry warning
- Domain match

Each check has a "Fix" suggestion link or button where applicable.

### 15.2 One-Click Update (Envato)  ✅

Admin → System → Updates

Flow:
1. Check for updates: calls Envato API (`https://api.envato.com/v3/market/catalog/item?id={ITEM_ID}`) → compares latest version vs installed version (stored in settings)
2. "Update Available" badge shown in admin sidebar when new version exists (cached 6h)
3. Admin clicks "Download & Install Update":
   a. Download zip from Envato using purchase code + Envato API
   b. Extract to temp directory
   c. Run pre-update checks (disk space, backup prompt)
   d. Copy files (skip `config/`, `.env`, `storage/`, `addons/`, `resources/themes/`)
   e. Run `php artisan migrate --force`
   f. Run `php artisan config:cache && route:cache && view:clear`
   g. Update version in settings
   h. Show success/rollback option
4. Automatic database backup before update (optional, toggled in settings — dumps to `storage/backups/`)
5. Rollback: stores previous version zip, "Rollback" button available for 24h post-update

**`app/Console/Commands/CheckUpdates.php`** — runs daily via scheduler, sets `update_available` in settings.

### 15.4 Cron Job Setup  ✅

Admin → System → Cron Jobs

Page shows:
- Required cron entry: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`
- Auto-detected document root with actual path filled in
- "Copy to clipboard" button
- Last run time + status
- Schedule list: shows all registered scheduled tasks, their frequency, last/next run time
- Manual run button per task (for testing)
- cPanel auto-setup: if cPanel detected (check for `~/.cpanel` or `CPANEL` env), show instructions with screenshots

**Scheduled tasks registered in `routes/console.php`:**
```php
Schedule::command('credits:reset-daily')->dailyAt('00:00');
Schedule::command('credits:reset-monthly')->monthlyOn(1, '00:00');
Schedule::command('license:verify')->weekly();
Schedule::command('currency:update-rates')->daily();
Schedule::command('updates:check')->daily();
Schedule::command('subscriptions:process-renewals')->hourly();
Schedule::command('social:publish-scheduled')->everyFiveMinutes();
Schedule::command('ai:cleanup-temp-files')->daily();
Schedule::command('analytics:aggregate')->hourly();
```

### 15.5 Maintenance Mode  ✅

Admin → System → Maintenance

Toggle with:
- Enable/disable maintenance mode (uses Laravel's `php artisan down/up` + custom view)
- Custom maintenance message (WYSIWYG)
- Custom maintenance page title
- Estimated restoration time (displayed to visitors)
- Bypass secret token (admins can access via `?secret=TOKEN`)
- Allowed IPs list (comma-separated, always bypass)
- Maintenance page background image upload

Custom maintenance view at `resources/views/maintenance.blade.php` — standalone HTML, no layout dependency, shows logo + message + countdown timer if `estimated_restoration_time` is set.

---


---


## PART 36 — ADMIN MENU STRUCTURE (Collapsible)

### 17.1 Menu Builder ✅

Admin → Appearance → Menus

**Table: `menus`**
```sql
id, name, slug (top_header / bottom_header / footer_1 / footer_2 / mobile_offcanvas / sidebar), created_at
```

**Table: `menu_items`**
```sql
id, menu_id FK, parent_id NULL FK (self-referential),
label varchar(255),
type enum('url','page','blog_category','ai_category','route'),
url varchar(500) NULL,
page_id bigint NULL FK,
route_name varchar(100) NULL,
target enum('_self','_blank') DEFAULT '_self',
icon varchar(100) NULL,               -- Tabler icon class e.g. 'ti-home'
badge_text varchar(50) NULL,          -- e.g. 'New', 'Hot'
badge_color varchar(20) NULL,
is_active boolean DEFAULT true,
requires_auth enum('none','guest','auth','pro') DEFAULT 'none',
sort_order int DEFAULT 0,
mega_menu boolean DEFAULT false,       -- enables mega menu panel for this item
mega_menu_content longtext NULL,       -- HTML content for mega menu panel
created_at
```

**Admin UI:**
- Drag-and-drop reordering (nested, up to 3 levels)
- Add items from: custom URL, pages, blog categories, AI tool categories, named routes
- Edit label, icon, badge, target, visibility rules per item
- Mega menu toggle per top-level item → opens HTML editor for mega menu panel content

**Rendered menus:** `MenuService::render(string $slug): Collection` — cached per slug, invalidated on save.

**Blade/Vue usage:**
```php
@menu('top_header')   // Blade
// or Vue prop via Inertia shared data: $page.props.menus.top_header
```

### 17.2 Header Builder ✅

Admin → Appearance → Header

Visual drag-and-drop header layout builder. Available blocks:
- Logo (image upload + alt text + link)
- Navigation menu (select from saved menus)
- Search bar (triggers LiveSearch)
- CTA button (text, link, color, style: filled/outline)
- Language switcher
- Dark mode toggle
- User avatar / login button
- Notification bell
- Credit balance display
- Custom HTML block
- Social icons row

Layout options:
- Header layout preset: Classic (logo left, nav center, actions right), Centered (logo center, nav below), Minimal (logo + hamburger only for mobile)
- Sticky header toggle
- Transparent header on homepage toggle
- Header height (px)
- Show/hide header on scroll toggle

Mobile header: separate simplified builder — logo + hamburger (opens `mobile_offcanvas` menu).

### 17.3 Footer Builder ✅

Admin → Appearance → Footer

Drag-and-drop column layout (1/2/3/4 column grid):

Available footer widgets/blocks per column:
- About text (logo + description paragraph)
- Menu list (select from saved menus)
- Contact info (address, phone, email with icons)
- Social follow icons
- Newsletter subscribe form (embeds `@livewire('newsletter-subscribe')`)
- Custom HTML
- Recent blog posts (last N posts, N configurable)
- AI tool categories list

Bottom bar (sub-footer):
- Copyright text (supports `{year}` variable)
- Bottom menu (select from saved menus)
- Payment icons (Visa, Mastercard, PayPal, Stripe — toggleable)
- Back to top button toggle

### 17.4 Widget Builder ✅

Admin → Appearance → Widget

Available sidebar widgets:
- Search box
- Categories list (blog or AI tools)
- Recent posts
- Tag cloud
- Newsletter subscribe
- Ad zone (select from defined zones)
- Social follow
- Custom HTML
- popular tools
- recently added tools

Drag-and-drop reorder. Each widget has its own settings (e.g. Recent Posts: how many, show thumbnail toggle).

Sidebar position: left/right/hidden (per page template).

---


---


---

## 🔷 LAYER 9 — COMMUNITY

## PART 37 — COMMUNITY FEATURES (Livewire)  ✅

Use **Laravel Livewire v3** for all real-time interactive community features — these are server-driven and do not require Vue, keeping them SEO-friendly and lightweight.

### 13.1 Newsletter System

**Table: `newsletter_subscribers`**
```sql
id, email, name NULL, status enum('subscribed','unsubscribed','bounced'),
token varchar(64) UNIQUE,   -- for unsubscribe link
subscribed_at timestamp, unsubscribed_at timestamp NULL, created_at
```

**Table: `newsletter_campaigns`**
```sql
id, subject, content longtext, sent_at timestamp NULL,
recipient_count int DEFAULT 0, opened_count int DEFAULT 0,
status enum('draft','sending','sent'), created_at
```

**Admin features:**
- Subscriber list with export CSV
- Compose campaign (rich HTML editor with Quill)
- Send campaign (queued, batch sending via configured mail driver)
- Open tracking (1px pixel tracking image)
- Unsubscribe page at `/newsletter/unsubscribe/{token}`
- Double opt-in toggle (settings)

**Frontend Livewire component `NewsletterSubscribe`:**
- Inline email input + subscribe button
- Real-time validation feedback
- Success/already-subscribed/error states
- Embeddable anywhere via `@livewire('newsletter-subscribe')`

### 13.2 Comments System

**Table: `comments`**
```sql
id, commentable_type, commentable_id,  -- polymorphic (blog_posts, pages, ai_tools)
user_id bigint FK NULL,                 -- NULL = guest comment (if allowed)
parent_id bigint NULL FK,              -- for nested replies (max 2 levels)
content text,
status enum('pending','approved','spam') DEFAULT 'pending',
guest_name varchar(100) NULL,
guest_email varchar(255) NULL,
ip_address varchar(45),
likes_count int DEFAULT 0,
created_at, updated_at, deleted_at
```

**Livewire component `CommentSection`:**
- Threaded display (parent + replies)
- Submit comment (logged in users auto-approved if settings allow, guests go to pending)
- Reply to comment (1 level deep)
- Like/unlike comment (debounced, stored in `comment_likes` table)
- Edit own comment (within 15 minutes)
- Delete own comment
- Report comment
- Pagination (load more button)
- Admin moderation: approve/spam/delete from admin panel Comments section
- Real-time new comment indicator (Livewire polling, interval configurable)

**Admin settings:**
- Enable/disable comments globally
- Auto-approve logged-in user comments toggle
- Allow guest comments toggle
- Require approval for all toggle
- Notify admin on new comment (email)
- Akismet spam filter API key (optional)

### 13.3 Favorites / Bookmarks System

**Table: `favorites`**
```sql
id, user_id FK, favoriteable_type, favoriteable_id,  -- polymorphic
created_at
```

Polymorphic: works on `blog_posts`, `ai_tools`, `chatbots`, `generated_images`, `prompt_library`.

**Livewire component `FavoriteButton`:**
- Heart/bookmark icon button
- Toggle on click (optimistic UI update)
- Count display (optional)
- `@livewire('favorite-button', ['model' => $post, 'showCount' => true])`

**User dashboard Favorites page:** grouped by type (Blog Posts, Templates, Images, Prompts) with masonry/list view toggle.

---


---


---

## 🔷 LAYER 10 — API & INFRASTRUCTURE

## PART 38 — MOBILE APP API ROUTES

### 44.1 API Architecture

Base URL: `/api/v1/`

Authentication: **Laravel Sanctum** — token-based (no cookies).
Token issued on login, stored on device.
Token scopes: `*` (full access) for now, expandable later.

All responses follow a consistent envelope:
```json
{
  "success": true,
  "data": { ... },
  "message": "OK",
  "meta": {
    "pagination": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 98 }
  }
}
```

Error responses:
```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": { "email": ["The email field is required."] },
  "code": 401
}
```

### 44.2 Auth Endpoints

```
POST   /api/v1/auth/register              -- register (name, email, password, ref_code?)
POST   /api/v1/auth/login                 -- email + password → returns token + user
POST   /api/v1/auth/logout                -- revoke current token
POST   /api/v1/auth/otp/send              -- send OTP to email (for verify/reset)
POST   /api/v1/auth/otp/verify            -- verify OTP code { email, otp, type }
POST   /api/v1/auth/password/reset        -- reset password after OTP verified
POST   /api/v1/auth/social/{provider}     -- Google/Apple sign-in { id_token }
GET    /api/v1/auth/me                    -- current authenticated user
PUT    /api/v1/auth/me                    -- update profile (name, avatar, timezone, language)
PUT    /api/v1/auth/me/password           -- change password
DELETE /api/v1/auth/me                    -- delete account request
POST   /api/v1/auth/2fa/enable            -- enable TOTP 2FA → returns QR URI
POST   /api/v1/auth/2fa/confirm           -- confirm TOTP setup with code
POST   /api/v1/auth/2fa/disable           -- disable 2FA
POST   /api/v1/auth/2fa/verify            -- verify TOTP code on login
```

### 44.3 User Endpoints

```
GET    /api/v1/user/dashboard             -- stats: credits, usage today, recent docs
GET    /api/v1/user/credits               -- credit balance + usage summary
GET    /api/v1/user/credits/transactions  -- paginated credit transaction history
GET    /api/v1/user/notifications         -- paginated notifications list
POST   /api/v1/user/notifications/read    -- mark notification(s) as read { ids: [] | 'all' }
GET    /api/v1/user/subscription          -- current plan + status + expiry
GET    /api/v1/user/referral              -- referral code, link, earnings, stats
GET    /api/v1/user/api-keys              -- list personal API keys
POST   /api/v1/user/api-keys             -- add personal API key { provider, key }
DELETE /api/v1/user/api-keys/{id}         -- delete personal API key
```

### 44.4 AI Tools Endpoints

```
GET    /api/v1/tools                      -- list all active tools (with category, access_level)
GET    /api/v1/tools/categories           -- list tool categories
GET    /api/v1/tools/{slug}               -- single tool detail + fields definition
GET    /api/v1/tools/recent              -- user's recently used tools (last 10)
GET    /api/v1/tools/favorites           -- user's favorited tools
POST   /api/v1/tools/favorites           -- toggle favorite { tool_slug }

POST   /api/v1/generate/text             -- generate text (non-streaming) { tool_slug, fields: {} }
POST   /api/v1/generate/stream           -- streaming text (SSE) { tool_slug, fields: {} }
POST   /api/v1/generate/image            -- generate image { prompt, provider, size, style }
POST   /api/v1/generate/tts             -- text to speech { text, provider, voice }
POST   /api/v1/generate/stt             -- speech to text (multipart/form-data audio file)
POST   /api/v1/generate/code            -- code generation { language, prompt }

GET    /api/v1/chat                      -- list conversations (paginated)
POST   /api/v1/chat                      -- create new conversation { model, system_prompt? }
GET    /api/v1/chat/{id}                 -- conversation + messages
POST   /api/v1/chat/{id}/messages        -- send message (streaming SSE) { content, attachments? }
DELETE /api/v1/chat/{id}                 -- delete conversation
PUT    /api/v1/chat/{id}                 -- update conversation (title, model)
```

### 44.5 Document Endpoints

```
GET    /api/v1/documents                 -- paginated list { folder_id?, search?, sort? }
GET    /api/v1/documents/{id}            -- single document content
POST   /api/v1/documents                 -- create document { title, content, folder_id? }
PUT    /api/v1/documents/{id}            -- update document
DELETE /api/v1/documents/{id}            -- delete
POST   /api/v1/documents/{id}/duplicate  -- duplicate
GET    /api/v1/folders                   -- list folders
POST   /api/v1/folders                   -- create folder { name }
PUT    /api/v1/folders/{id}              -- rename folder
DELETE /api/v1/folders/{id}              -- delete folder (moves docs to root)
GET    /api/v1/documents/{id}/export     -- export { format: docx|pdf|md|txt }
```

### 44.6 Media Endpoints

```
GET    /api/v1/images                    -- paginated generated image gallery
GET    /api/v1/images/{id}               -- single image details
DELETE /api/v1/images/{id}               -- delete
POST   /api/v1/images/{id}/favorite      -- toggle favorite

GET    /api/v1/audio                     -- paginated generated audio list
DELETE /api/v1/audio/{id}                -- delete

GET    /api/v1/videos                    -- paginated generated videos
DELETE /api/v1/videos/{id}               -- delete
```

### 44.7 Knowledge Base & RAG Endpoints

```
GET    /api/v1/knowledge-bases           -- list user's collections
POST   /api/v1/knowledge-bases           -- create collection { name }
DELETE /api/v1/knowledge-bases/{id}      -- delete collection
POST   /api/v1/knowledge-bases/{id}/upload   -- upload document (multipart)
GET    /api/v1/knowledge-bases/{id}/documents -- list documents in collection
DELETE /api/v1/knowledge-bases/{id}/documents/{docId}
POST   /api/v1/knowledge-bases/{id}/chat -- RAG chat { message, conversation_id? }
```

### 44.8 Support Ticket Endpoints

```
GET    /api/v1/support/tickets           -- list own tickets (paginated)
POST   /api/v1/support/tickets           -- create ticket { subject, department_id, priority, message, attachments? }
GET    /api/v1/support/tickets/{number}  -- ticket detail + replies
POST   /api/v1/support/tickets/{number}/replies    -- add reply { content, attachments? }
PUT    /api/v1/support/tickets/{number}/resolve    -- mark as resolved
POST   /api/v1/support/tickets/{number}/rating     -- submit satisfaction rating { rating: 1-5, comment? }
GET    /api/v1/support/departments       -- list active departments
```

### 44.9 Blog & Content Endpoints

```
GET    /api/v1/blog/posts               -- paginated posts { category?, tag?, search?, sort? }
GET    /api/v1/blog/posts/{slug}        -- single post + related + comments
GET    /api/v1/blog/categories          -- list categories
GET    /api/v1/blog/tags                -- list tags (popular)
GET    /api/v1/blog/posts/{slug}/comments   -- paginated comments
POST   /api/v1/blog/posts/{slug}/comments  -- add comment { content, parent_id? }
```

### 44.10 Subscription & Payment Endpoints (Pro)

```
GET    /api/v1/plans                    -- list available plans (if isProAvailable())
POST   /api/v1/subscribe               -- initiate subscription { plan_id, gateway, billing_cycle }
GET    /api/v1/subscription             -- current subscription details
DELETE /api/v1/subscription             -- cancel subscription
GET    /api/v1/invoices                 -- paginated invoice list
GET    /api/v1/invoices/{id}            -- single invoice + download URL
GET    /api/v1/payments                 -- payment history
```

### 44.11 Affiliate Endpoints

```
GET    /api/v1/affiliate                -- dashboard stats
GET    /api/v1/affiliate/referrals      -- referral list (paginated)
GET    /api/v1/affiliate/commissions    -- commission history (paginated)
GET    /api/v1/affiliate/payouts        -- payout request history
POST   /api/v1/affiliate/payouts        -- request payout { amount, method, details: {} }
```

### 44.12 Misc Endpoints

```
GET    /api/v1/app/config               -- public app config (name, logo URL, features enabled, currencies, languages)
GET    /api/v1/app/languages            -- active languages list
GET    /api/v1/app/translations/{code}  -- translation strings for a language code
GET    /api/v1/pages/{slug}             -- public page content (privacy, terms, etc.)
POST   /api/v1/contact                  -- submit contact form { name, email, subject, message }
POST   /api/v1/newsletter/subscribe     -- subscribe to newsletter { email, name? }
```

### 44.13 API Rate Limiting

```php
// config/api_rate_limits.php (values from settings table)
'auth'       => '10 per minute per IP',
'generate'   => '30 per minute per user',  // AI generation
'chat'       => '60 per minute per user',
'upload'     => '10 per minute per user',
'public'     => '60 per hour per IP',      // unauthenticated endpoints
```

All rate limit headers returned: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

### 44.14 API Documentation

Auto-generated with **Dedoc Scramble** (`dedoc/scramble`) — served at `/api/docs` (toggleable in settings, hidden in production unless admin enables).

---


---


## PART 45 — QUEUE & JOB ARCHITECTURE

### 45.1 Queue Infrastructure

- **Driver:** Redis (via `predis/predis` or `phpredis`)
- **Queue manager:** Laravel Horizon (process management, monitoring, metrics)
- **Horizon dashboard:** `/horizon` (protected by `HorizonServiceProvider` — only accessible to Super Admin)

### 45.2 Named Queues & Worker Config

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-otp' => [
            'queue'      => ['otp'],
            'processes'  => 3,       // high priority — OTP must send fast
            'tries'      => 1,       // no retry on OTP (expired by then)
            'timeout'    => 30,
        ],
        'supervisor-ai' => [
            'queue'      => ['ai'],
            'processes'  => 5,       // AI jobs are slow, need more workers
            'tries'      => 2,
            'timeout'    => 120,     // 2 min per AI job
            'backoff'    => [5, 30],
        ],
        'supervisor-emails' => [
            'queue'      => ['emails'],
            'processes'  => 3,
            'tries'      => 3,
            'timeout'    => 60,
            'backoff'    => [10, 30, 60],
        ],
        'supervisor-media' => [
            'queue'      => ['media'],  // image/video/audio generation
            'processes'  => 3,
            'tries'      => 2,
            'timeout'    => 300,    // 5 min for video generation
            'backoff'    => [30, 60],
        ],
        'supervisor-default' => [
            'queue'      => ['default', 'webhooks', 'social', 'embeddings'],
            'processes'  => 4,
            'tries'      => 3,
            'timeout'    => 60,
            'backoff'    => [5, 15, 30],
        ],
        'supervisor-low' => [
            'queue'      => ['low'],   // analytics, view counts, non-critical
            'processes'  => 1,
            'tries'      => 1,
            'timeout'    => 30,
        ],
    ],
],
```

### 45.3 Complete Job Registry

**Queue: `otp`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `SendOtpEmail` | Register, forgot password, login 2FA | Sends OTP email via configured driver. No retry — OTP expired by retry time. |

**Queue: `ai`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `ProcessTextGeneration` | Non-streaming AI tool usage | Runs AI completion, saves result to document, deducts credits |
| `ProcessAgentRun` | AI Workflow node execution | Runs multi-step AI agent with tool calls |
| `ProcessRagQuery` | Knowledge base chat (non-streaming) | Vector search + AI completion |
| `GenerateEmbeddings` | Document upload to knowledge base | Chunks text, generates embeddings via Laravel AI SDK, stores in vector DB |
| `ReprocessFailedAiJob` | Admin retry from dashboard | Re-queues a failed AI job with fresh token check |

**Queue: `media`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `GenerateImage` | Image generation request | Calls provider API (DALL-E/Flux/etc.), saves to storage, notifies user |
| `GenerateVideo` | Video generation request | Long-running — calls Kling/Runway/etc., polls until complete, notifies |
| `GenerateAudio` | TTS request | Calls ElevenLabs/OpenAI TTS, saves audio file, notifies |
| `GenerateMusic` | Music generation | Calls Suno/Udio API, polls for result, saves file |
| `ProcessSpeechToText` | STT upload | Calls Whisper/AssemblyAI, returns transcript |
| `RemoveBackground` | Image bg removal | Calls Remove.bg API, saves processed image |
| `UpscaleImage` | Image upscale request | Calls upscale provider, saves result |

**Queue: `emails`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `SendTemplatedEmail` | Any mail event (welcome, subscription, etc.) | Renders DB template + variables, sends via configured driver, logs result |
| `SendBulkEmail` | Admin broadcast / newsletter campaign | Chunks recipients (50/batch), dispatches `SendTemplatedEmail` per chunk |
| `SyncMailchimpSubscriber` | Newsletter subscribe/unsubscribe | Syncs single subscriber to Mailchimp API |
| `ResendFailedEmail` | Admin clicks resend in mail logs | Fetches failed log entry, re-dispatches `SendTemplatedEmail` |

**Queue: `webhooks`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `ProcessStripeWebhook` | Stripe webhook POST | Handles: payment_succeeded, subscription_updated, invoice.paid, etc. |
| `ProcessPayPalWebhook` | PayPal IPN/webhook | Handles payment + subscription events |
| `ProcessSSLCommerzWebhook` | SSLCommerz IPN | Validates and processes payment confirmation |
| `ProcessPaddleWebhook` | Paddle webhook | Handles subscription lifecycle events |
| `DeliverOutboundWebhook` | User workflow node / API event | Delivers POST to user-configured webhook URL with retry + signature |

**Queue: `social`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `PublishScheduledPost` | Cron every 5 min | Checks `scheduled_posts` for due posts, publishes to connected platforms |
| `FetchSocialFollowerCounts` | Cron daily | Refreshes follower counts for all configured social profiles |
| `PublishToLinkedIn` | Post publish | Calls LinkedIn API to publish post/image/carousel |
| `PublishToTwitter` | Post publish | Calls X API v2 to create tweet/thread |
| `PublishToInstagram` | Post publish | Calls Instagram Graph API (create container → publish) |
| `PublishToFacebook` | Post publish | Calls Facebook Graph API |

**Queue: `embeddings`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `IngestDocument` | File uploaded to knowledge base | Extracts text (PDF/DOCX/CSV/URL), chunks, generates embeddings, stores in vector DB |
| `IngestUrl` | URL added to chatbot training | Scrapes URL content, processes into embeddings |
| `DeleteDocumentEmbeddings` | Document deleted from KB | Removes all vectors for that document from vector store |

**Queue: `default`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `SendInAppNotification` | Any notifiable event | Broadcasts via Reverb + stores in DB |
| `ProcessLicenseVerification` | Cron weekly | Re-verifies license against Envato API, updates status |
| `UpdateExchangeRates` | Cron daily | Fetches latest rates from configured API, updates `currencies` table |
| `CheckForUpdates` | Cron daily | Hits Envato API to check for new version, sets `update_available` in settings |
| `AggregateAnalytics` | Cron hourly | Flushes Redis view/usage counters to MySQL |
| `ProcessSubscriptionRenewals` | Cron hourly | Checks expiring subscriptions, triggers renewal or sends expiry notification |
| `ResetDailyCredits` | Cron daily 00:00 | Resets `credits_used_today` for all users |
| `ResetMonthlyCredits` | Cron 1st of month | Resets `credits_used_month` for all users |
| `AutoCloseResolvedTickets` | Cron daily | Closes support tickets resolved > N days ago |
| `CleanupTempFiles` | Cron daily | Deletes temp files in storage older than 24h |
| `CleanupExpiredOtps` | Cron hourly | Nullifies expired OTP codes in users table |
| `BackupDatabaseBeforeUpdate` | On update trigger | Dumps MySQL to `storage/backups/` before one-click update |
| `ProcessAffiliateCommissions` | After payment confirmed | Creates commission record, auto-approves if setting enabled |

**Queue: `low`**
| Job | Trigger | Description |
|-----|---------|-------------|
| `IncrementPostViews` | Blog post viewed | Batched view count increment (Redis → MySQL flush) |
| `IncrementToolUsage` | AI tool used | Increments `ai_tools.usage_count` |
| `IncrementAdImpressions` | Ad zone rendered | Increments `ads.impressions` async |
| `TrackAdClick` | Ad link clicked | Increments `ads.clicks` then redirects |
| `TrackAffiliateClick` | Referral link visited | Logs click, sets cookie |

### 45.4 Failed Job Handling

- **`failed_jobs` table** — standard Laravel failed jobs table
- **Horizon UI** — shows failed jobs with exception, stack trace, payload
- **Admin → System → Queue** page (separate from Horizon):
  - Total pending jobs per queue (live, from Redis)
  - Failed jobs count with "Retry All" and "Flush Failed" buttons
  - Per-job retry button
  - Horizon process status (running/stopped) with start/stop controls
  - Last 20 failed jobs with exception message
- **Slack/email alert** when failed jobs exceed threshold (configurable in settings)

### 45.5 Scheduled Tasks Summary

All registered in `routes/console.php`:

```php
// Every 5 minutes
Schedule::job(new PublishScheduledPost)->everyFiveMinutes();

// Every hour
Schedule::job(new CleanupExpiredOtps)->hourly();
Schedule::job(new AggregateAnalytics)->hourly();
Schedule::job(new ProcessSubscriptionRenewals)->hourly();

// Daily
Schedule::job(new ResetDailyCredits)->dailyAt('00:00');
Schedule::job(new UpdateExchangeRates)->dailyAt('02:00');
Schedule::job(new CheckForUpdates)->dailyAt('03:00');
Schedule::job(new FetchSocialFollowerCounts)->dailyAt('04:00');
Schedule::job(new CleanupTempFiles)->dailyAt('05:00');
Schedule::job(new AutoCloseResolvedTickets)->dailyAt('06:00');

// Weekly
Schedule::job(new ProcessLicenseVerification)->weekly();

// Monthly (1st of month)
Schedule::job(new ResetMonthlyCredits)->monthlyOn(1, '00:00');

// Scheduler heartbeat (for site health monitor)
Schedule::call(fn() => settings_set('last_scheduler_run', now()))->everyMinute();
```

---


---


---

## 🔷 LAYER 11 — DEPLOYMENT ✅

## PART 40 — SEEDER DATA (Complete)

Add to `database/seeders/DatabaseSeeder.php` call order:

```php
$this->call([
    // Foundation
    SettingsSeeder::class,          // all default settings including branding
    CurrencySeeder::class,          // USD, EUR, GBP, BDT, INR, AED, SAR, CAD, AUD
    LanguageSeeder::class,          // English (default) + 10 common languages
    AdminRoleSeeder::class,         // Super Admin, Support, Content Manager, Finance
    AdminPermissionSeeder::class,   // all permission slugs
    AdminSeeder::class,             // from installation wizard input

    // Content
    CategorySeeder::class,          // all AI tool categories + blog categories + faq categories
    AiToolSeeder::class,        // all 255 templates with default prompts
    FaqSeeder::class,               // 20 default FAQs
    PageSeeder::class,              // privacy, terms, contact, about, faq, cookie
    MailTemplateSeeder::class,      // all 23 predefined mail templates
    HomepageSeeder::class,          // default homepage section layout and order
    MenuSeeder::class,              // default top_header, footer_1, mobile_offcanvas menus
    TestimonialSeeder::class,       // 6 sample testimonials
    AnnouncementSeeder::class,      // empty (no default announcements)

    // Demo only — activated via DEMO_MODE=true in .env
    // DemoSeeder::class,
]);
```

---

## PART 41 — FILE STRUCTURE

```
app/
  Console/Commands/       -- artisan commands (license:verify, credits:reset-daily, etc.)
  Exceptions/             -- CreditLimitException, LicenseException, etc.
  Helpers/                -- helpers.php, license.php
  Http/
    Controllers/
      Admin/              -- all admin panel controllers
      Api/V1/             -- REST API controllers
      Auth/               -- login, register, social, 2FA, OTP
      User/               -- dashboard, profile, settings
      AI/                 -- one controller per AI module
    Middleware/
      AdminAuth.php
      AdminPermission.php
      CheckCredits.php
      LicenseMiddleware.php
      HandleInertiaRequests.php
    Requests/             -- FormRequest classes per feature
  Models/
    Admin.php, User.php, Setting.php, Plan.php, etc.
  Services/
    AI/
      AiService.php
      ProviderRegistry.php
      TokenGuard.php
      StreamService.php
    Payment/              -- one file per gateway
    LicenseService.php
    ThemeService.php
    AddonService.php
    TranslationService.php
    CurrencyService.php
  Traits/
    HasCredits.php        -- on User model
    HasRBAC.php           -- on Admin model

resources/
  themes/
    default/
  js/
    Pages/
      Admin/
      Auth/
      User/
      AI/
    Components/
      UI/                 -- Button, Input, Modal, etc.
      AI/                 -- AiEditor, StreamingText, ModelSelector, etc.
      Admin/              -- DataTable, StatsCard, Chart, etc.
    Layouts/
    Composables/
    Stores/               -- Pinia stores
    types/                -- TypeScript interfaces

addons/                   -- installable addons directory

config/
  ai.php                  -- AI defaults
  license.php             -- Envato item ID, API endpoint
  addons.php              -- loaded addons list

database/
  migrations/
  seeders/
```

---

## PART 42 — DEMO MODE ✅

Critical for Envato preview and buyer confidence.

### 21.1 Demo Mode Architecture

Admin → Settings → Demo Mode:
- Enable/disable demo mode
- Demo admin credentials (shown on login page)
- Demo user credentials (shown on login page)

**`app/Http/Middleware/DemoMode.php`**

When demo mode is active:
- Intercepts all `POST`, `PUT`, `PATCH`, `DELETE` requests
- **Allowed** writes (demo must feel functional):
  - AI generation requests (text, image, chat — limited credits)
  - Login/logout
  - Theme/dark mode preference toggle
  - Language switch
  - Search queries
- **Blocked** writes with toast message "This action is disabled in demo mode":
  - Password changes
  - Email changes
  - API key saves
  - Payment/billing actions
  - Delete operations
  - Admin settings saves (except appearance)
  - License changes
  - User creation/deletion

**Demo data seeder** (`database/seeders/DemoSeeder.php`):
- 50 sample users with varied plans and usage stats
- 200 sample AI-generated documents
- 30 sample blog posts
- 20 sample images in gallery
- 10 sample chatbots with training data
- Sample revenue data for last 12 months (hardcoded in DB)
- Sample AI usage logs for dashboard charts to look realistic
- 5 sample newsletter campaigns with open rates
- Demo admin: `admin@demo.com` / `demo12345`
- Demo user: `user@demo.com` / `demo12345`

**Frontend demo banner:**
- Sticky top banner when demo mode active: "You are viewing a demo. Some actions are disabled. [Buy Now →]" with link to Envato item
- Banner dismissible per session
- Color configurable in admin (default: amber/warning)

**Demo reset** (optional): artisan command `php artisan demo:reset` — re-seeds demo data, clears generated content older than 1 hour. Can be scheduled to run every hour to keep demo clean.

---


---


---


---

## 🔷 LAYER 12 — CHECKLISTS

## PART 43 — MASTER DEVELOPER CHECKLIST

### Branding
- [ ] Zero hardcoded "MakeAI" strings — search entire codebase before submission
- [ ] Logo upload auto-generates all favicon sizes correctly
- [ ] Dark/light logo swap works in both admin and frontend
- [ ] `manifest.json` reflects current app name, icon, and theme color
- [ ] OG image used correctly in social share meta tags

### Auth & OTP
- [ ] 6-box OTP input: auto-advance, paste, auto-submit on 6th digit, shake on error
- [ ] Countdown timer accurate, resend re-enables correctly after cooldown
- [ ] 5 wrong attempts → 10-minute lockout activates
- [ ] Email masked on OTP page (`j***@gmail.com`)
- [ ] Password reset: OTP → new password on same page with transition (no mid-flow redirect)
- [ ] No verification links anywhere — only OTP codes for email verification and password reset

### Rich Text Editor (Tiptap)
- [ ] All toolbar actions functional (formatting, lists, tables, media, code, links)
- [ ] Slash command palette opens, filters, and inserts all block types
- [ ] Drag handle reorders any block correctly
- [ ] Table: merge cells, resize columns, header row toggle all work
- [ ] Image: upload, URL, resize handles, alignment, alt text, caption all work
- [ ] Code block: syntax highlighting for 30+ languages, copy button works
- [ ] Autosave every 30s, version history stores 20 versions, restore works
- [ ] Export: DOCX, PDF, Markdown, Plain Text produce valid files
- [ ] AI sidebar: selected-text actions stream correctly, diff view accept/reject works
- [ ] Word count and reading time update in real-time
- [ ] Full / Comment / Minimal variants render with correct feature subsets

### In-App Notifications
- [ ] Laravel Reverb server running (`php artisan reverb:start`) and accessible on configured port
- [ ] WebSocket connection established on page load (Laravel Reverb)
- [ ] Reverb credentials auto-filled in .env by installation wizard
- [ ] Pusher as optional fallback: switching driver in settings reconnects Echo client without page reload
- [ ] Polling fallback (30s) activates when no WebSocket configured
- [ ] Unread badge updates in real-time without page refresh
- [ ] Bell dropdown shows last 10 with correct time-ago format
- [ ] "Mark all as read" clears badge
- [ ] Admin and user notifications fully separate
- [ ] All event triggers fire correctly (credits, subscriptions, payments, login, etc.)

### Newsletter
- [ ] Mailchimp sync: new subscriber added within 5 seconds
- [ ] Mailchimp unsubscribe syncs correctly
- [ ] Popup triggers all work independently: exit intent, delay, scroll depth, page views
- [ ] Popup dismissal cookie persists for configured duration
- [ ] Inline form: horizontal and vertical layouts correct
- [ ] Double opt-in confirmation email sent when enabled

### Homepage Builder
- [ ] All section types render with default settings immediately after install
- [ ] Drag-and-drop reorder persists after page reload
- [ ] Toggle enable/disable takes effect without cache clear
- [ ] Typing text animation cycles correctly
- [ ] Count-up stats animate on scroll into viewport (Intersection Observer)
- [ ] Mobile preview simulates 390px correctly
- [ ] Cookie consent banner appears and sets cookie on accept
- [ ] Custom `<head>` / `<body>` code injection works without breaking layout

### Integrations
- [ ] "Test Connection" returns clear success/error for every provider
- [ ] All credentials stored encrypted, never returned in API responses
- [ ] Disabling an integration hides related UI in user panel immediately
- [ ] Missing AI provider shows warning in admin dashboard

### Tool Access Control
- [ ] Public tools: IP rate limiting enforces correctly, output truncates at configured limit
- [ ] Login-required: modal intercepts without navigating away
- [ ] Pro-plan gate: upgrade modal shows correct plan comparison
- [ ] Bulk access level change applies to all selected tools
- [ ] `inherit` falls back to global default correctly

### Announcements
- [ ] Top bar shows/hides per target audience (guest/auth/plan)
- [ ] Top bar dismissal persists for session (not shown again on refresh)
- [ ] Popup: all 4 trigger types work correctly
- [ ] Popup frequency: always/session/once all behave correctly
- [ ] Scheduled start/end dates respected (tested with past/future dates)
- [ ] Broadcast notification reaches all targeted users via WebSocket + fallback

### Mail System
- [ ] All 6 drivers work (SMTP, Mailgun, SES, Postmark, SendGrid, log)
- [ ] Test email shows actual error message from SMTP on failure
- [ ] All 23 templates seeded with professional English content
- [ ] Subscription templates invisible in UI and skipped in send logic when `isProAvailable() === false`
- [ ] All variable types replaced correctly including nested subscription variables
- [ ] Bulk email batches 50/chunk, queued — HTTP request completes immediately
- [ ] Failed emails logged and re-sendable from mail logs
- [ ] OTP emails on high-priority queue worker (not delayed by bulk sends)

### Menu & Navigation
- [ ] Admin sidebar: active group auto-expands on page load
- [ ] Collapse state persists in localStorage across navigation
- [ ] Mini sidebar (icon-only) mode: hover flyout works correctly
- [ ] Mobile sidebar: opens as overlay drawer, closes on backdrop click
- [ ] Nested groups (2-level) expand/collapse independently
- [ ] Permission-gated items completely hidden (not greyed) for unauthorized admins
- [ ] Pro-gated items completely hidden when isProAvailable() === false
- [ ] Update badge shows correctly when update is available
- [ ] Pending counts (comments, tickets) update in real-time via WebSocket
- [ ] Frontend mobile menu: only one group open at a time (accordion)
- [ ] Frontend desktop dropdown: hover delay and fade-in smooth
- [ ] Mega menu renders correctly (from Header Builder config)
- [ ] Keyboard navigation works in admin sidebar (arrow keys, Enter, Escape)

### Envato Submission
- [ ] Demo site live with all features working + realistic data
- [ ] License verification tested with real purchase code on production domain
- [ ] Installation wizard tested: fresh Ubuntu 22.04 VPS + cPanel shared hosting
- [ ] One-click update: `.env`, `storage/`, `addons/`, `resources/themes/` never overwritten
- [ ] Demo mode: AI generation works (rate-limited), all writes blocked, banner visible with "Buy Now" link
- [ ] All `console.log`, `dd()`, `dump()`, `ray()` removed from codebase
- [ ] `.env` excluded from zip, `.env.example` includes all variables with comments
- [ ] `composer install --no-dev` + `npm run build` produces clean production build
- [ ] PHP 8.3 and PHP 8.4 compatibility confirmed (run on both)
- [ ] PSR-12 passes: `./vendor/bin/pint --test`
- [ ] PestPHP test suite: `./vendor/bin/pest` all green
- [ ] Documentation covers: installation, admin guide, addon development, theme development, API reference

---

## PART 44 — ENVATO SUBMISSION CHECKLIST

Before submitting to CodeCanyon:
- [ ] Demo site live with all features working
- [ ] Installation wizard tested on fresh server
- [ ] License verification working (use a real purchase code for testing)
- [ ] Documentation: installation guide, API reference, admin guide, addon/theme development guide
- [ ] Video preview (90 seconds max for preview, longer for full demo)
- [ ] Screenshots: 590×300 preview + 590×300 thumbnail + feature screenshots
- [ ] Change log ready
- [ ] Support email configured
- [ ] All `console.log` and `dd()` removed
- [ ] `.env.example` included, `.env` excluded from zip
- [ ] `composer install --no-dev` for production build
- [ ] `npm run build` for production assets
- [ ] PHP CodeSniffer passes (PSR-12)
- [ ] PestPHP test suite runs green

---

## NOTES FOR DEVELOPER

1. **Always use `translate()` helper** for any user-facing string — never hardcode English text directly in Blade or Vue templates.
2. **Always check `isProAvailable()`** before rendering any subscription-related UI — even if admin manually enables a feature, always gate it.
3. **Never store API keys in plaintext** — always use `Crypt::encryptString()` via the `encrypted` setting type.
4. **Addon settings override** global settings when the same key exists — addon settings take priority in their own scope.
5. **Theme views** override the default views using Laravel's view namespacing — a theme's `views/dashboard.blade.php` replaces the default.
6. **All AI requests must pass through `TokenGuard`** — no exceptions, even admin-initiated requests.
7. **Use ULIDs** (`$table->ulid()`) as the public-facing user identifier — never expose auto-increment integer IDs in URLs or API responses.
8. **Queue all AI jobs** that take longer than 3 seconds — never block the HTTP response for long AI completions.
9. **Rate limit AI endpoints** per user per model per minute using Laravel's `ThrottleRequests` with dynamic limits from settings.
10. **Test on PHP 8.3 and PHP 8.4** before submission.

---


---


---

*End of MakeAI Complete Development Master Prompt*
*Version 3.0 · Parts: 40 · AI Templates: 255 · Mail Templates: 23 · API Endpoints: 80+ · Queue Jobs: 45+ · Integrations: 60+*

---

---

## 🔷 LAYER 13 — QUALITY & PERFORMANCE

## PART 45 — ERROR HANDLING & CUSTOM ERROR PAGES

### 45.1 Custom Error Pages

All error pages are **fully branded** — never show Laravel's default error page to users.

**Files:**
```
resources/views/errors/
  404.blade.php    -- Page Not Found
  500.blade.php    -- Server Error
  503.blade.php    -- Maintenance Mode (see Part 18.5)
  429.blade.php    -- Too Many Requests (rate limit)
  403.blade.php    -- Forbidden
  401.blade.php    -- Unauthenticated
```

Each error page:
- Uses standalone HTML (no Laravel layout dependency — works even if app is broken)
- Shows app logo from `settings('app_logo_light')` — with absolute URL fallback
- Clean centered layout with error illustration (SVG inline, no external image dependency)
- Helpful message explaining what happened in plain language
- Action buttons appropriate to context:
  - 404 → "Go to Homepage" + "Search Tools"
  - 500 → "Go to Homepage" + "Contact Support"
  - 429 → "You've made too many requests. Try again in {X} seconds." + countdown timer
  - 403 → "Go Back" + "Contact Support"
  - 401 → "Sign In" + "Register"
- In `APP_DEBUG=true` (dev only): show Laravel exception details below branded layout

**`app/Exceptions/Handler.php`** — register custom renderers:
```php
public function register(): void
{
    $this->renderable(function (CreditLimitException $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code'    => 'CREDIT_LIMIT',
                'message' => translate('You have reached your credit limit.'),
                'type'    => $e->getLimitType(), // 'daily' | 'monthly' | 'balance'
                'retry_after' => $e->getRetryAfter(), // timestamp for daily reset
            ], 402);
        }
    });

    $this->renderable(function (InsufficientCreditsException $e, Request $request) {
        return response()->json([
            'success'   => false,
            'code'      => 'INSUFFICIENT_CREDITS',
            'message'   => translate('Not enough credits. Please top up your balance.'),
            'balance'   => $e->getCurrentBalance(),
            'required'  => $e->getRequiredAmount(),
        ], 402);
    });

    $this->renderable(function (IntegrationNotConfiguredException $e, Request $request) {
        return response()->json([
            'success'  => false,
            'code'     => 'INTEGRATION_NOT_CONFIGURED',
            'message'  => translate('This feature requires :integration to be configured.', [
                'integration' => $e->getIntegration()
            ]),
        ], 503);
    });
}
```

### 45.2 AI Generation Error Messages

User-facing messages for all AI failure modes (never show raw API errors):

| Error condition | User message | Action shown |
|----------------|-------------|-------------|
| Credit limit (daily) | "Daily limit reached. Resets at midnight." | Upgrade plan / Buy credits |
| Credit limit (monthly) | "Monthly limit reached." | Upgrade plan |
| Insufficient balance | "Not enough credits." | Top up balance |
| Global budget exceeded | "AI is temporarily unavailable. Try again in a few minutes." | Retry button |
| Model rate limit (429) | "AI is busy right now. Retrying in {n}s…" | Auto-retry (3x) |
| Model unavailable | "Selected model is temporarily down. Try a different model." | Model selector |
| Content policy violation | "Your request was flagged by content filters. Please modify your input." | Edit input |
| Context too long | "Your input is too long. Please shorten it." | Character count shown |
| Timeout (>30s no response) | "Generation timed out. Try a shorter length or different model." | Retry |
| No API key configured | "This AI provider is not configured. Please contact support." | — |
| Network error | "Connection error. Please check your internet and try again." | Retry |

**Auto-retry logic (Vue):** For rate limit (429) errors — automatic retry with exponential backoff: 5s → 10s → 20s. Shows countdown: "Retrying in 10s… [Cancel]". After 3 failed retries → show error message.

### 45.3 Server Configuration Guide (Included in Documentation)

**Nginx config for MakeAI:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/makeai/public;
    index index.php;

    # SSE streaming — disable buffering
    location /api/v1/generate/stream {
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 120s;
        fastcgi_read_timeout 120s;
        add_header X-Accel-Buffering no;
        try_files $uri $uri/ /index.php?$query_string;
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # Laravel Reverb WebSocket
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
    }

    # Main app
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 120;
    }

    location ~ /\.ht { deny all; }
}
```

**Supervisor config (`/etc/supervisor/conf.d/makeai.conf`):**
```ini
[program:makeai-horizon]
process_name=%(program_name)s
command=php /var/www/makeai/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/makeai-horizon.log

[program:makeai-reverb]
process_name=%(program_name)s
command=php /var/www/makeai/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/makeai-reverb.log

[program:makeai-ssr]
process_name=%(program_name)s
command=node /var/www/makeai/bootstrap/ssr/ssr.js
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/makeai-ssr.log
```

This config included in `docs/server-setup.md` in the distributed zip.

### 45.4 Checklist: Error Handling

- [ ] All 6 custom error pages branded, standalone HTML, no external dependencies
- [ ] 429 page shows actual retry countdown timer (reads `Retry-After` header)
- [ ] `CreditLimitException`, `InsufficientCreditsException` return correct JSON codes
- [ ] Auto-retry: 3 attempts with 5/10/20s backoff, cancel button works
- [ ] Content policy violation shows helpful edit-input message (not raw API error)
- [ ] Model rate limit auto-retries silently (user sees countdown, not raw 429)
- [ ] Timeout after 30s: clear message + retry option
- [ ] `APP_DEBUG=false` in production: no stack traces ever shown to users
- [ ] Nginx SSE config tested: streaming works on nginx (not just artisan serve)
- [ ] Supervisor processes: Horizon + Reverb + SSR all auto-restart on crash
- [ ] Nginx WebSocket proxy tested: Reverb connection established through proxy

---

## PART 46 — GDPR & DATA PRIVACY ✅

### 46.1 User Data Export

User → Settings → Privacy → "Download My Data"

Generates a zip file containing:
```
my-data-{date}.zip
  profile.json          -- name, email, registration date, settings
  documents/            -- all saved documents as .md files
  chat-history/         -- all conversations as .json files
  generated-images/     -- all generated images
  generated-audio/      -- all generated audio files
  usage-history.csv     -- ai_usage_logs for this user
  credit-transactions.csv
  login-history.csv
```

**Flow:**
1. User requests export → `ExportUserDataJob` dispatched to `default` queue
2. Job builds zip (can take 1–5 minutes for heavy users)
3. When ready → in-app notification + email with download link
4. Download link expires in 48 hours
5. File deleted from storage after download or expiry

**Table: `data_export_requests`**
```sql
id, user_id FK, status enum('pending','processing','ready','downloaded','expired'),
file_path varchar NULL, expires_at timestamp NULL, created_at, updated_at
```

Rate limit: max 1 export request per 24 hours per user.

### 46.2 Account Deletion

User → Settings → Privacy → "Delete My Account"

**Flow:**
1. User clicks → confirmation modal: "This action is permanent. Type DELETE to confirm."
2. Types DELETE → sends OTP to email for final verification
3. OTP verified → account scheduled for deletion
4. **Soft delete + 30-day grace period** (configurable):
   - User logged out immediately
   - `users.deleted_at` set, `is_active = false`
   - All sessions revoked
   - Email sent: "Your account will be permanently deleted on {date}. Log in to cancel."
5. During grace period: user can log in → cancels deletion
6. After 30 days: `PermanentlyDeleteUserJob` runs:
   - Deletes: documents, chat history, generated media, usage logs, credit records
   - Anonymizes: comments (author → "Deleted User"), reviews (kept for tool ratings)
   - Cancels active subscriptions via payment gateway
   - Removes from newsletter lists

### 46.3 Privacy Settings Page

User → Settings → Privacy:

- **Data & Export:** "Download all my data" button + export history
- **Account Deletion:** "Delete account" button
- **Marketing preferences:** Email marketing opt-in/out toggle
- **Cookie preferences:** Manage which cookie categories are accepted (functional / analytics / marketing)
- **Session management:** List all active sessions (device, IP, last seen), revoke individual sessions or "Sign out all devices"
- **Third-party connections:** List connected social accounts (Google, GitHub), disconnect buttons
- **AI data:** Toggle "Allow my generations to improve the model" (sends flag to providers that support opt-out)

### 46.4 Cookie Consent (Detailed)

Extends homepage builder cookie banner (Part 31.3) with granular consent:

**Categories:**
- **Necessary** — always on, cannot disable (session, CSRF, auth)
- **Functional** — preferences, language, theme (on by default)
- **Analytics** — Google Analytics, Mixpanel (off by default)
- **Marketing** — ad tracking, retargeting pixels (off by default)

**Consent stored:**
- Guest: `localStorage` + cookie (`cookie_consent`)
- Logged-in: also saved to `users` table (`cookie_consent json`)

**Google Analytics / GTM:** Only loads after `analytics` consent given.

### 46.5 Checklist: GDPR

- [ ] Data export zip generates correctly with all file types
- [ ] Export job handles users with 1000s of documents without timeout
- [ ] Download link expires correctly after 48h, file deleted from storage
- [ ] Grace period cancellation: user can log in and cancel deletion during 30 days
- [ ] Permanent deletion: all PII removed, comments anonymized, reviews kept
- [ ] Active subscription cancelled on deletion (no continued billing)
- [ ] Cookie consent loads before any analytics scripts
- [ ] Analytics scripts blocked until consent given
- [ ] Session list shows correct devices/IPs, revoke works immediately
- [ ] "Sign out all devices" revokes all Sanctum tokens

---

## PART 47 — USER ONBOARDING FLOW ✅

### 47.1 Welcome Onboarding (After registration Only)

Shown once after first email OTP verification. Stored in `users.onboarding_completed_at`.

**Step 1 — Welcome screen:**
- "Welcome to {site_name}, {name}! Let's get you set up in 2 minutes."
- Animated illustration
- "Let's go →" button
- "Skip for now" button always visible

**Step 2 — Choose your primary use case:**
```
[ 📝 Content Creator ]   [ 📣 Marketer ]   [ 💻 Developer ]
[ 🎓 Student/Researcher] [ 🛒 eCommerce ]   [ 💼 Business Owner ]
```
Selection stored in `users.use_case`. Used to reorder tool categories on dashboard.
- "Skip for now" button always visible

**Step 3 — Recommended tools** (based on use case):
"Here are your top tools based on your goals:"
- 6 tool cards shown (pre-selected based on use case mapping)
- User can favorite (bookmark) them directly from this screen
- "Skip for now" button always visible

**Step 4 — Try your first generation:**
- Pre-selected tool based on use case (e.g. Content Creator → Blog Intro)
- Pre-filled example inputs
- One-click generate → streaming output shown in modal
- "Amazing! Your first AI content is ready." → copy / save buttons
- This "wow moment" is critical for activation
- "Skip for now" button always visible

**Step 5 — Profile completion (optional, skippable):**
- Upload avatar
- Set basic_info (country, profession, date_of_birth...)
- Set brand voice (textarea with example placeholder)
- Set preferred language
- "Skip for now" button always visible

**Onboarding modal:** Full-screen modal overlay with progress dots (●●○○○), skip button in corner, smooth slide transitions between steps.

### 47.2 Dashboard Completion Checklist

After onboarding, a checklist widget on dashboard until all items completed:

```
Getting Started with MakeAI  [3/6 completed]  ██████░░░░  50%

✅ Create your account
✅ Verify your email
✅ Complete your profile
☐  Generate your first document        [Try now →]
☐  Save a tool to favorites            [Browse tools →]
☐  Set up your brand voice             [Settings →]
```

- Widget hidden permanently after all 6 items completed OR user clicks "Dismiss"
- Each item has a direct action link
- Completion % shown as progress bar

### 47.3 Contextual Tooltips

First time a user visits each major section, show a dismissible tooltip:
- Documents page: "All your AI-generated content is saved here"
- Chat page: "Start a conversation with any AI model"
- Knowledge Base: "Upload your documents and chat with them"

Tooltip shown state stored per-user in `users.dismissed_tooltips json`.

### 47.4 Checklist: Onboarding

- [ ] Onboarding shown exactly once per user (check `onboarding_completed_at`)
- [ ] Use case selection correctly reorders tool categories on dashboard
- [ ] Try-first-generation: pre-fills form, streaming works inside modal
- [ ] "Wow moment" generation counted in `ai_usage_logs` for credit tracking
- [ ] Dashboard checklist: all 6 items detect completion correctly
- [ ] Checklist widget dismisses permanently and never reappears
- [ ] Tooltips dismissed per-user, not per-session (survive logout)
- [ ] Onboarding modal has working "Skip" that marks `onboarding_completed_at`

---

## PART 48 — KEYBOARD SHORTCUTS & COMMAND PALETTE ✅

### 48.1 Global Keyboard Shortcuts

Registered globally in `app.vue` via `useKeyboardShortcuts` composable:

| Shortcut | Action |
|----------|--------|
| `?` | Open keyboard shortcuts reference modal |
| `Ctrl+K` / `⌘K` | Open command palette |
| `Ctrl+/` / `⌘/` | Focus global search |
| `Escape` | Close modal / dropdown / sidebar |
| `Ctrl+Shift+D` | Toggle dark/light mode |
| `Ctrl+Shift+S` | Go to settings |
| `Ctrl+Shift+N` | New document |
| `Ctrl+Shift+C` | New chat |

**In tool pages:**

| Shortcut | Action |
|----------|--------|
| `Ctrl+Enter` | Generate (submit form) |
| `Ctrl+C` (focused on output) | Copy output |
| `Ctrl+S` | Save document |
| `Ctrl+Shift+E` | Open in editor |
| `Ctrl+R` | Regenerate |

**In Tiptap editor:**

All standard Tiptap shortcuts plus:
| Shortcut | Action |
|----------|--------|
| `Ctrl+Shift+X` | Export menu |
| `Ctrl+Z` / `Ctrl+Y` | Undo / Redo |
| `/` at line start | Slash command palette |

### 48.2 Command Palette (`Ctrl+K`)

Inspired by Linear/Vercel command palette. Opens as centered modal with search input.

**Items searchable:**
- All 255 AI tools (with icon, category)
- User's recent documents (last 20)
- User's recent conversations (last 10)
- Navigation links (Settings, Dashboard, Documents, etc.)
- Admin links (if admin — Settings, Users, etc.)
- Actions: New Document, New Chat, Dark Mode Toggle, Clear Cache (admin)

**UX:**
- Opens instantly (< 50ms) — all items pre-indexed in Pinia store on app load
- Fuzzy search (fuse.js)
- Arrow keys navigate, Enter selects, Escape closes
- Results grouped by type: Tools / Documents / Chats / Navigation
- Recent searches stored in `localStorage` (last 5)
- Selected item navigates or performs action immediately

### 48.3 Checklist: Shortcuts & Command Palette

- [ ] `Ctrl+K` opens palette within 50ms (items pre-loaded, not fetched on open)
- [ ] Fuzzy search returns relevant results for typos (e.g. "bolg" → Blog Article)
- [ ] Arrow key navigation + Enter selection works
- [ ] All 255 tools searchable with name + category
- [ ] `Ctrl+Enter` submits tool form only when form is focused (not other pages)
- [ ] Shortcut reference modal `?` lists all shortcuts accurately
- [ ] Shortcuts disabled when user is typing in input/textarea

---

## PART 49 — AUDIT LOG (ADMIN ACTIONS)

### 49.1 Table

```sql
admin_activity_logs
  id
  admin_id          bigint FK → admins.id
  action            varchar(100)      -- e.g. 'user.credits.add', 'settings.update', 'template.delete'
  subject_type      varchar(100) NULL -- e.g. 'App\Models\User'
  subject_id        bigint NULL
  old_values        json NULL         -- previous state (for updates)
  new_values        json NULL         -- new state (for updates)
  ip_address        varchar(45)
  user_agent        varchar(500) NULL
  created_at
```

### 49.2 Logged Actions

All admin actions automatically logged via `AdminActivityObserver` and middleware:

**User management:** view user, edit user, delete user, ban/unban, add/deduct credits, impersonate, export users

**Settings:** any settings save (logs which keys changed + old vs new values)

**AI:** enable/disable model, change API key (logs that key was changed, NOT the actual key value), edit template

**Plans & Billing:** create/edit/delete plan, refund, manual subscription change

**Content:** publish/delete blog post, approve/reject comment, approve/reject review

**System:** clear cache, trigger update, enable/disable maintenance mode, change license

**Admins:** create admin, change role, change permissions, delete admin

### 49.3 Admin Audit Log UI

Admin → Admins → Activity Log:

- Timeline list: timestamp, admin avatar + name, action description (human-readable), subject link
- Filters: admin (dropdown), action category, date range
- Search: by action or subject
- Click any row → detail modal showing old_values vs new_values diff
- Export as CSV
- Retention: keep logs for 1 year, auto-delete older (configurable)

Human-readable action descriptions:
```
"John (admin) added 50 credits to user@example.com"
"Sarah (support) changed site name from 'Old Name' to 'New Name'"
"John (admin) deleted blog post 'Best AI Tools 2025'"
```

### 49.4 Checklist: Audit Log

- [ ] `AdminActivityObserver` fires on all Eloquent events for admin-controlled models
- [ ] Sensitive values never logged: passwords, API keys, payment tokens
- [ ] `old_values` / `new_values` diff works for JSON settings changes
- [ ] Human-readable descriptions generated for all action types
- [ ] Impersonation sessions logged: "Admin X started impersonating User Y"
- [ ] Audit log cannot be deleted by any admin (read-only, even Super Admin)
- [ ] Export to CSV works for filtered date ranges

---


---


## PART 50 — TESTING STRATEGY (PestPHP)

### 50.1 Test Suite Structure

```
tests/
  Feature/
    Auth/
      OtpVerificationTest.php
      LoginTest.php
      PasswordResetTest.php
      SocialAuthTest.php
    AI/
      TextGenerationTest.php
      StreamingTest.php
      TokenGuardTest.php
      TemplateTest.php
    User/
      CreditSystemTest.php
      DocumentTest.php
      ReferralTest.php
    Admin/
      AdminAuthTest.php
      RbacTest.php
      SettingsTest.php
      UserManagementTest.php
    Payment/
      StripeWebhookTest.php
      SubscriptionTest.php
      CreditPurchaseTest.php
    Mail/
      MailTemplateTest.php
      OtpEmailTest.php
    Blog/
      BlogPostTest.php
      BlogSeoTest.php
    Support/
      TicketTest.php
    Api/
      AuthApiTest.php
      GenerateApiTest.php
      DocumentApiTest.php
  Unit/
    PromptBuilderTest.php
    TokenGuardTest.php
    LicenseServiceTest.php
    CurrencyTest.php
    TranslationTest.php
```

### 50.2 Critical Test Cases

**OTP flow:**
```php
it('sends OTP on registration and verifies correctly', function () {
    $user = User::factory()->unverified()->create();
    // Assert OTP email queued
    // POST /auth/otp/verify with correct code → 200
    // Assert email_verified_at set
});

it('blocks after 5 wrong OTP attempts', function () {
    // 5 wrong attempts → 423 Locked response
    // Correct OTP still rejected during lockout
});
```

**Credit system:**
```php
it('deducts credits after successful generation', function () {
    $user = User::factory()->withCredits(100)->create();
    // POST /api/v1/generate/text
    // Assert credits decreased
    // Assert ai_usage_logs row created
});

it('blocks generation when credits insufficient', function () {
    $user = User::factory()->withCredits(0)->create();
    // POST /api/v1/generate/text → 402
    // Assert credits unchanged
});
```

**License gating:**
```php
it('hides subscription features when regular license', function () {
    settings_set('license_type', 1);
    // GET /api/v1/plans → 403 or empty
    // isProAvailable() === false
});
```

### 50.3 GitHub Actions CI Pipeline

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env: { MYSQL_DATABASE: makeai_test, MYSQL_ROOT_PASSWORD: secret }
      redis:
        image: redis:7
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: 'pdo_mysql,redis,gd' }
      - run: composer install --no-dev
      - run: cp .env.testing .env && php artisan key:generate
      - run: php artisan migrate --seed --env=testing
      - run: ./vendor/bin/pest --parallel
```

### 50.4 Model Factories (Key ones)

```php
// database/factories/UserFactory.php
public function withCredits(float $amount): static
public function unverified(): static
public function withPlan(string $planSlug): static    // if isProAvailable()
public function admin(): static                        // creates admin model instead

// database/factories/AiTemplateFactory.php
public function withFields(array $fields): static
public function free(): static
public function pro(): static
```

### 50.5 Checklist: Testing

- [ ] `./vendor/bin/pest` runs green with 0 failures on fresh install
- [ ] `./vendor/bin/pest --parallel` completes under 3 minutes
- [ ] OTP: correct code, wrong code, expired code, lockout all tested
- [ ] Credit deduction: success, insufficient balance, daily limit, monthly limit all tested
- [ ] License gating: regular license blocks subscription endpoints
- [ ] Stripe webhook: payment_succeeded creates subscription correctly
- [ ] Admin RBAC: support role cannot access settings (403)
- [ ] GitHub Actions CI runs on every push to main/develop
- [ ] Test database uses separate `.env.testing` (never touches production DB)
- [ ] Factories create realistic data with correct relationships

---

## PART 51 — DATABASE OPTIMIZATION

### 51.1 Index Strategy

```sql
-- users
INDEX idx_users_email         (email)
INDEX idx_users_referral_code (referral_code)
INDEX idx_users_referred_by   (referred_by)

-- ai_usage_logs (high volume — partition by month)
INDEX idx_usage_user_date  (user_id, created_at)
INDEX idx_usage_tool_date  (tool_slug, created_at)
INDEX idx_usage_provider   (provider, created_at)
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at))

-- documents
FULLTEXT INDEX ft_documents (title, content)
INDEX idx_docs_user_folder  (user_id, folder_id)
INDEX idx_docs_tool_slug    (tool_slug)

-- blog_posts
FULLTEXT INDEX ft_blog (title, excerpt, content)
INDEX idx_blog_status_date (status, published_at)
INDEX idx_blog_author      (author_id)

-- ai_tools
INDEX idx_templates_category  (category_id, is_active, sort_order)
INDEX idx_templates_slug       (slug)
INDEX idx_templates_featured   (is_featured, is_active)

-- support_tickets
INDEX idx_tickets_status_priority (status, priority, created_at)
INDEX idx_tickets_user            (user_id)
INDEX idx_tickets_assigned        (assigned_to, status)

-- tool_reviews
UNIQUE INDEX uq_review_user_tool (user_id, template_slug)
INDEX idx_reviews_approved       (template_slug, is_approved)

-- notifications
INDEX idx_notif_unread (notifiable_type, notifiable_id, read_at)

-- settings
UNIQUE INDEX uq_settings_key (key)
INDEX idx_settings_group    (group)
```

### 51.2 Redis Cache Keys Reference

```
makeai:settings:{key}              TTL: forever (cleared on write)
makeai:settings:all                TTL: forever (full settings hash)
makeai:translations:{lang_code}    TTL: 24h
makeai:menu:{slug}                 TTL: forever (cleared on menu save)
makeai:tool:{slug}                 TTL: 1h
makeai:tool:list                   TTL: 1h
makeai:user:credits:{user_id}      TTL: 60s
makeai:license:status              TTL: 7 days
makeai:update:available            TTL: 6h
makeai:exchange_rates              TTL: 24h
makeai:ai_spend_today_usd          TTL: until midnight (dynamic)
makeai:post_views:{post_id}        TTL: 1h (flush to DB hourly)
makeai:social_counts               TTL: 24h
makeai:homepage_sections           TTL: forever (cleared on save)
makeai:tool_related:{slug}         TTL: 24h
```

### 51.3 Query Optimization Rules

1. **Never `SELECT *`** on large tables — always specify columns
2. **`ai_usage_logs`** queries must always include `user_id` or `created_at` range (uses partition + index)
3. **Documents full-text search**: use `MATCH() AGAINST()` not `LIKE '%term%'`
4. **User credit balance**: always read from Redis cache, not DB directly
5. **Settings**: never query `settings` table in a loop — use `settings('key')` which reads from cache
6. **Tool lists**: cached as a collection, never queried per-request
7. **Eager load relationships**: `with(['category', 'author'])` on blog/tool queries — never N+1
8. **Paginate all admin lists**: never `->get()` on unbounded queries — always `->paginate()`

### 51.4 Checklist: Database Performance

- [ ] All indexes from 49.1 present in migrations
- [ ] `ai_usage_logs` partitioning set up correctly (test with 1M+ rows)
- [ ] Fulltext indexes work: blog search and document search tested
- [ ] No N+1 queries: run Laravel Debugbar on key pages, 0 duplicate queries
- [ ] Redis cache hit rate > 95% for settings and translations (check Horizon metrics)
- [ ] User credit reads always from Redis (not DB direct query)
- [ ] Admin user list with 10,000 users loads under 200ms


---

---

## 🔷 LAYER 14 — CORE FEATURE ENHANCEMENTS ✅

> These 10 features are built into the MakeAI core (not addons). They extend the base platform with
> high-value UX improvements that increase retention, plan upgrades, and perceived product quality.
> All follow the same architectural rules as the rest of the codebase.

---

## PART 53 — AI PLAYGROUND (MODEL SANDBOX)

### 53.1 Overview

A raw model testing sandbox available to every logged-in user. Two side-by-side panels, each with
independent provider + model selection and parameter controls. A single shared user message fires
both simultaneously. No tool templates — direct prompt access for power users.

**User-facing URL:** `/playground`
**Sidebar location:** Under "Tools" group, labelled "AI Playground" with icon `ti-flask`

### 53.2 Database

No new DB tables. Session history stored in Pinia + `localStorage` only (last 20 prompts).
Share snapshots stored in Redis only (TTL 7 days, no DB row):

```
Redis key: makeai:playground_share:{uuid}   TTL: 604800s
Value:     JSON { prompt, params_left, params_right, output_left, output_right, created_at }
```

### 53.3 Backend

**`app/Http/Controllers/PlaygroundController.php`**

```php
// GET /playground
public function index(): Response
    // Return Inertia 'Playground/Index'
    // Pass: available providers + models from ProviderRegistry (no API keys exposed)

// POST /playground/run   — streaming
public function run(Request $request): StreamedResponse
    // Validate: provider, model, messages[], temperature (0–2), max_tokens, top_p
    // TokenGuard::before() — check credit limit (estimate from input length)
    // $this->ai->stream(new CompletionRequest(...))
    // Stream with: Content-Type: text/event-stream, X-Accel-Buffering: no
    // After stream complete: TokenGuard::after() with actual token count

// POST /playground/share
public function share(Request $request): JsonResponse
    // Validate payload (max 10k chars combined)
    // Store in Redis with uuid key, TTL 604800
    // Return { url: '/playground/s/{uuid}' }

// GET /playground/s/{uuid}   — public, no auth
public function showShare(string $uuid): Response
    // Redis::get("makeai:playground_share:{$uuid}") or abort(404)
    // Inertia 'Playground/Share' — read-only view
```

### 53.4 Routes

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/playground', [PlaygroundController::class, 'index'])->name('playground.index');
    Route::post('/playground/run', [PlaygroundController::class, 'run'])->name('playground.run');
    Route::post('/playground/share', [PlaygroundController::class, 'share'])->name('playground.share');
});
Route::get('/playground/s/{uuid}', [PlaygroundController::class, 'showShare'])->name('playground.share.show');
```

### 53.5 Vue — `resources/js/Pages/Playground/Index.vue`

```
Layout: AppLayout
Two-column grid (each col ~50%), divider between them

LEFT PANEL & RIGHT PANEL (identical structure):
  ┌─────────────────────────────────────────────┐
  │ [Provider ▾]  [Model ▾]  [⟳ Sync]           │
  │ ─── Parameters (collapsible) ───────────── │
  │  Temp [──●──] 0.7   Max Tokens [____1000]  │
  │  Top P [──●──] 1.0  Presence P [____0.0]   │
  ├─────────────────────────────────────────────┤
  │ System Prompt (collapsible textarea)        │
  ├─────────────────────────────────────────────┤
  │ OUTPUT (streaming text area)                │
  │                                             │
  │ Tokens: 312 in / 487 out  ~$0.0003  [Copy] │
  └─────────────────────────────────────────────┘

SHARED AREA (between panels, full width):
  [User message textarea                      ]
  [Run Both ↗]  [History ▾]  [Clear]
  [Save as AI Tool ↗]  [Share Snapshot]

Pinia store: usePlaygroundStore
  State: leftPanel{provider,model,systemPrompt,params,output,tokens,cost,streaming}
         rightPanel{same}  sharedMessage  syncPanels  history[]
  Actions: runPanel(side) — fires independent fetch() streams, NOT sequential
  Persistence: history[] saved to localStorage via pinia-plugin-persistedstate
```

**Key implementation rules:**
- Both panels fire two **independent** `fetch()` calls simultaneously — never `await` one before the other
- `syncPanels` toggle: writing either system prompt textarea updates both (Vue `watch`)
- Token count parsed from `usage` object in final SSE chunk
- "Save as AI Tool" navigates to `/admin/ai-tools/create?system_prompt={encoded}` — pre-fills, does NOT auto-create
- Share snapshot truncates each output to 5,000 chars before storing in Redis

### 53.6 Checklist

- [ ] Two panels stream simultaneously (two independent `fetch()` calls)
- [ ] Streaming uses POST + `ReadableStream` + `X-Accel-Buffering: no` (no EventSource)
- [ ] TokenGuard runs on every `/playground/run` call
- [ ] Credit estimate shown before run; actual deducted after stream completes
- [ ] Share URL returns 404 after 7-day TTL (Redis key expired)
- [ ] History survives page reload (localStorage via Pinia persist plugin)
- [ ] No DB writes during playground session — only Redis for shares

---

## PART 54 — PROMPT VERSIONING & HISTORY

### 54.1 Overview

Every AI generation saves a versioned snapshot of the exact prompt + settings used. Users can
browse generation history, diff versions, restore old prompts, and mark favorites.
Stored in a new `generation_history` table.

### 54.2 Database

```sql
generation_history
  id              ulid PRIMARY KEY
  user_id         bigint FK → users.id
  tool_slug       varchar(100)          -- which AI tool was used
  document_id     ulid FK → documents.id NULL  -- if saved to doc
  prompt_system   text                  -- resolved system prompt (after brand voice injection)
  prompt_user     text                  -- resolved user prompt (with field values substituted)
  field_values    json                  -- raw form inputs { topic: "...", tone: "..." }
  model           varchar(100)
  provider        varchar(50)
  temperature     decimal(3,2)
  max_tokens      int
  output_preview  text                  -- first 500 chars of output (for list display)
  tokens_input    int
  tokens_output   int
  is_favorited    boolean DEFAULT false
  label           varchar(100) NULL     -- user can label a version e.g. "Best version"
  created_at      timestamp
  INDEX (user_id, tool_slug, created_at)
  INDEX (user_id, is_favorited)
```

### 54.3 Service — `app/Services/GenerationHistoryService.php`

```php
// Called automatically after every successful AI generation
public function record(User $user, array $data): GenerationHistory
    // $data = [tool_slug, document_id, prompt_system, prompt_user, field_values,
    //           model, provider, temperature, max_tokens, output_preview, tokens_in, tokens_out]
    // Create GenerationHistory record
    // Prune: keep latest 200 records per user (delete oldest beyond 200)

public function getHistory(User $user, ?string $toolSlug = null, int $perPage = 20): LengthAwarePaginator
    // Query with optional tool_slug filter, ordered by created_at DESC

public function restore(GenerationHistory $history): array
    // Return field_values + model + provider + temperature + max_tokens
    // Frontend uses this to re-fill tool form

public function diff(GenerationHistory $a, GenerationHistory $b): array
    // Return word-level diff of prompt_user strings
    // Use PHP diff library or simple explode+compare
    // Return [{ word: string, status: 'added'|'removed'|'unchanged' }]

public function toggleFavorite(GenerationHistory $history): void
    // Flip is_favorited boolean
```

### 54.4 Integration Point

In `GenerateTextController` (or wherever AI tool generation completes), after streaming finishes:

```php
// Record to history (non-blocking — dispatch a job)
dispatch(new RecordGenerationHistoryJob($user, [
    'tool_slug'      => $toolSlug,
    'document_id'    => $savedDocument?->id,
    'prompt_system'  => $resolvedSystemPrompt,
    'prompt_user'    => $resolvedUserPrompt,
    'field_values'   => $request->validated(),
    'model'          => $modelUsed,
    'provider'       => $providerUsed,
    'temperature'    => $temperature,
    'max_tokens'     => $maxTokens,
    'output_preview' => Str::limit($output, 500),
    'tokens_input'   => $usage['input_tokens'],
    'tokens_output'  => $usage['output_tokens'],
]));
```

### 54.5 Routes & Controller

```php
Route::middleware(['auth'])->prefix('history')->name('history.')->group(function () {
    Route::get('/',                                  [HistoryController::class, 'index'])->name('index');
    Route::get('/tool/{toolSlug}',                   [HistoryController::class, 'byTool'])->name('by-tool');
    Route::post('/{history}/restore',                [HistoryController::class, 'restore'])->name('restore');
    Route::post('/{history}/favorite',               [HistoryController::class, 'favorite'])->name('favorite');
    Route::post('/{historyA}/diff/{historyB}',       [HistoryController::class, 'diff'])->name('diff');
    Route::put('/{history}/label',                   [HistoryController::class, 'label'])->name('label');
    Route::delete('/{history}',                      [HistoryController::class, 'destroy'])->name('destroy');
});
```

### 54.6 Vue — History Panel (in-tool sidebar)

On every AI tool page, the output section gets a **"History" tab** beside the "Output" tab.

```
Output  |  History
─────────────────────────────────────────────
[★ Favorites only] [Filter: this tool / all]
─────────────────────────────────────────────
● v5 — 2 hours ago — "Write a blog about..."
  Topic: Laravel · Tone: Professional · ★
  [Restore] [Diff with current] [Label] [×]

● v4 — Yesterday — "Write a blog about..."
  Topic: Vue 3 · Tone: Casual
  [Restore] [Diff with current] [Label] [×]
─────────────────────────────────────────────
```

**Diff modal:** Word-level diff displayed inline — green for added words, red strikethrough for removed. Shown when user clicks "Diff with current" (compares selected history entry vs. the current form state).

**Restore:** Clicking "Restore" fills the tool form with the saved `field_values` + `model` + `provider` + `temperature`. User can then re-run or tweak.

### 54.7 Checklist

- [ ] `RecordGenerationHistoryJob` dispatched on `'ai'` queue — never blocks HTTP response
- [ ] Auto-prune to 200 records per user enforced in job (delete oldest)
- [ ] History only accessible by the owning user (query always scoped: `where('user_id', auth()->id())`)
- [ ] Restore correctly fills ALL form fields including model/provider selectors
- [ ] Diff view shows word-level changes, not just character-level
- [ ] Favorited history persists; unfavoriting works without page reload

---

## PART 55 — QUICK TOOL CHAINING

### 55.1 Overview

Users can connect up to 5 AI tools in sequence. Output of Tool A becomes input of Tool B.
This is a lightweight "Quick Chain" — not the full Workflow Builder addon. No branching, no
conditionals. Linear pipe only. Designed for common patterns like:
`Blog Outline → Blog Article → SEO Meta Tags`

**User-facing URL:** `/chains` and `/chains/{chain}/run`
**Sidebar location:** Under "Tools" group, labelled "Quick Chains" with icon `ti-link`

### 55.2 Database

```sql
tool_chains
  id              ulid PRIMARY KEY
  user_id         bigint FK → users.id
  name            varchar(100)
  steps           json    -- [ { tool_slug, field_map, static_inputs } ]
                          -- field_map: { "content": "{{previous_output}}" }
                          -- static_inputs: { "tone": "professional" }
  last_run_at     timestamp NULL
  run_count       int DEFAULT 0
  created_at      timestamp
  updated_at      timestamp

tool_chain_runs
  id              ulid PRIMARY KEY
  chain_id        ulid FK → tool_chains.id
  user_id         bigint FK → users.id
  status          enum('running','completed','failed') DEFAULT 'running'
  step_outputs    json    -- [ { step: 1, tool_slug, output, tokens } ]
  total_tokens    int DEFAULT 0
  total_credits   int DEFAULT 0
  started_at      timestamp
  completed_at    timestamp NULL
  INDEX (user_id, created_at)
```

**`steps` JSON structure:**
```json
[
  {
    "step": 1,
    "tool_slug": "blog-outline",
    "static_inputs": { "topic": "Laravel 12 new features", "tone": "professional" },
    "field_map": {}
  },
  {
    "step": 2,
    "tool_slug": "blog-article",
    "static_inputs": { "tone": "professional" },
    "field_map": { "outline": "{{step_1_output}}" }
  },
  {
    "step": 3,
    "tool_slug": "seo-meta-tags",
    "static_inputs": {},
    "field_map": { "content": "{{step_2_output}}" }
  }
]
```

### 55.3 Service — `app/Services/ToolChainService.php`

```php
public function runChain(ToolChain $chain, array $initialInputs, User $user): ToolChainRun
    // Create ToolChainRun record with status=running
    // Dispatch RunToolChainJob
    // Return run (frontend polls or listens on Reverb)

public function executeStep(array $step, string $previousOutput, array $initialInputs): string
    // Resolve inputs: merge static_inputs + field_map (replace {{step_N_output}} placeholders)
    // Resolve tool's prompt_system + prompt_user using same PromptBuilder as normal tool execution
    // Call $this->ai->complete(new CompletionRequest(...))
    // Return output text

public function buildFieldMap(string $template, array $stepOutputs): string
    // Replace {{step_1_output}}, {{step_2_output}}, {{previous_output}} with actual values
```

### 55.4 Job — `RunToolChainJob` (queue: `'ai'`)

```php
public function handle(): void
    foreach ($this->chain->steps as $step) {
        $output = $this->chainService->executeStep($step, $previousOutput, $this->initialInputs);
        $stepOutputs[] = ['step' => $step['step'], 'tool_slug' => $step['tool_slug'], 'output' => $output];
        $previousOutput = $output;

        // Broadcast progress to user's private Reverb channel:
        // ChainStepCompleted { run_id, step_number, total_steps, output_preview }
    }
    // Set run status=completed, save step_outputs, completed_at
    // Broadcast ChainCompleted { run_id }
    // Deduct total credits, log to ai_usage_logs per step
```

### 55.5 Vue — `resources/js/Pages/Chains/Index.vue` & `Builder.vue`

**Chain Builder (`/chains/create`):**
```
┌─────────────────────────────────────────────────────┐
│  Chain Name: [________________________]              │
│                                                     │
│  STEP 1  [Blog Outline ▾]                           │
│  Inputs:  Topic [_________________]                 │
│           Tone  [Professional ▾]                    │
│                          ↓ output flows down        │
│  STEP 2  [Blog Article ▾]                           │
│  Inputs:  Outline → {{step_1_output}} (auto-mapped) │
│           Tone    [Professional ▾]                  │
│                          ↓                          │
│  STEP 3  [SEO Meta Tags ▾]                          │
│  Inputs:  Content → {{step_2_output}} (auto-mapped) │
│                                                     │
│  [+ Add Step]  (max 5)          [Save Chain]        │
└─────────────────────────────────────────────────────┘
```

Auto-mapping logic: when a step is selected, find the first text/textarea field in the tool's
`fields` JSON and pre-fill its `field_map` with `{{previous_output}}`.

**Chain Runner (`/chains/{chain}/run`):**
- Shows initial inputs form (only Step 1's unfilled static_inputs)
- "Run Chain" button → dispatches job → shows live progress:
  ```
  Step 1/3: Blog Outline    [✓ Done]
  Step 2/3: Blog Article    [⟳ Running...]
  Step 3/3: SEO Meta Tags   [○ Waiting]
  ```
- Each completed step shows collapsible output preview
- Final step: "Save All as Documents" button (saves each step output as separate document)

### 55.6 Checklist

- [ ] Each step calls TokenGuard — total credits checked before chain starts (estimate per step)
- [ ] If any step fails: chain marked failed, all completed steps still saved in `step_outputs`
- [ ] `{{previous_output}}` and `{{step_N_output}}` placeholders resolved correctly
- [ ] Max 5 steps enforced in builder UI and in job validation
- [ ] Reverb broadcasts per-step progress — frontend shows live step completion
- [ ] Chain run history visible per chain (`/chains/{chain}/runs`)

---

## PART 56 — USER USAGE DASHBOARD

### 56.1 Overview

Every user gets a personal analytics page showing their own AI usage: credits, top tools, generation
history stats. Currently only admin sees usage data. This gives users visibility into their own
activity — increasing perceived value of higher credit plans.

**User-facing URL:** `/usage`
**Sidebar location:** Under user account section, labelled "My Usage" with icon `ti-chart-bar`

### 56.2 Data Sources

No new tables needed. All data read from existing tables:

| Metric | Source |
|--------|--------|
| Credits used today | `users.credits_used_today` (Redis cached) |
| Credits used this month | `users.credits_used_month` (Redis cached) |
| Credits remaining | `users.credits` (Redis) |
| Daily usage last 30 days | `ai_usage_logs` GROUP BY DATE(created_at) |
| Top 5 tools used | `ai_usage_logs` GROUP BY tool ORDER BY COUNT DESC LIMIT 5 |
| Total generations | COUNT `ai_usage_logs` WHERE user_id |
| Total tokens consumed | SUM `ai_usage_logs.tokens_input + tokens_output` |
| Most used model | `ai_usage_logs` GROUP BY model ORDER BY COUNT DESC LIMIT 1 |
| Peak usage hour | `ai_usage_logs` GROUP BY HOUR(created_at) |
| Recent generations | `generation_history` ORDER BY created_at DESC LIMIT 10 |

### 56.3 Controller — `UsageDashboardController`

```php
// GET /usage  — Inertia page
public function index(): Response
    $user = auth()->user();
    $stats = Cache::remember("usage_stats_{$user->id}", 300, function () use ($user) {
        return [
            'credits_remaining'    => $user->credits,
            'credits_used_today'   => $user->credits_used_today,
            'credits_used_month'   => $user->credits_used_month,
            'plan_credit_limit'    => $user->plan?->credits ?? settings('free_credits_monthly'),
            'total_generations'    => AiUsageLog::where('user_id', $user->id)->count(),
            'total_tokens'         => AiUsageLog::where('user_id', $user->id)
                                        ->sum(DB::raw('tokens_input + tokens_output')),
            'daily_usage'          => $this->getDailyUsage($user, 30),    // array of {date, credits}
            'top_tools'            => $this->getTopTools($user, 5),        // array of {tool_slug, count}
            'most_used_model'      => $this->getMostUsedModel($user),
            'peak_hour'            => $this->getPeakHour($user),
            'recent_history'       => GenerationHistory::where('user_id', $user->id)
                                        ->latest()->limit(10)->get(['tool_slug','output_preview','created_at']),
        ];
    });
    return Inertia::render('Usage/Index', compact('stats'));

// POST /usage/export  — download CSV
public function export(): StreamedResponse
    // Stream CSV of user's ai_usage_logs last 90 days
    // Columns: date, tool, model, provider, tokens_input, tokens_output, credits_used
```

### 56.4 Vue — `resources/js/Pages/Usage/Index.vue`

```
┌── Stat Cards (4 across) ──────────────────────────────────────┐
│  Credits Left    Used Today    Used This Month   Total Gens   │
│  1,240           48            892               2,341         │
└───────────────────────────────────────────────────────────────┘

┌── Daily Usage (last 30 days) — Chart.js bar chart ────────────┐
│  Credits per day sparkline, hover tooltip with date + amount   │
└───────────────────────────────────────────────────────────────┘

┌── Top Tools ──────────────────┐  ┌── Insights ──────────────┐
│  1. Blog Article      412 runs│  │  Most used model: GPT-4o  │
│  2. Instagram Caption 287 runs│  │  Peak hour: 2pm–3pm       │
│  3. Email Writer      198 runs│  │  Avg tokens/gen: 842      │
│  4. Code Generator    145 runs│  │  Most active day: Tuesday │
│  5. SEO Meta Tags      89 runs│  └──────────────────────────┘
└───────────────────────────────┘

┌── Recent Generations ──────────────────────────────────────────┐
│  Blog Article     "Laravel 12 new features..."    2 hours ago  │
│  Email Writer     "Follow-up after no response..." Yesterday   │
│  ...                                                           │
│                                  [Export CSV]                  │
└───────────────────────────────────────────────────────────────┘
```

**Chart.js usage:** Import `Chart` from `chart.js/auto` in the component. Bar chart for daily usage.
Use `var(--color-text-secondary)` for axis labels to respect dark mode.

**Credit limit bar:** If user has a plan with credit limit: show progress bar
`credits_used_month / plan_credit_limit` with color: green < 60%, amber < 90%, red ≥ 90%.

### 56.5 Checklist

- [ ] Stats cached per-user in Redis for 5 minutes (cache key: `usage_stats_{user_id}`, TTL 300)
- [ ] Cache cleared when `ai_usage_logs` row inserted for this user
- [ ] Daily usage query uses `DATE(created_at)` with index on `(user_id, created_at)` (already in P51)
- [ ] CSV export streams without loading all rows into memory (`LazyCollection`)
- [ ] `isProAvailable()` check only needed for plan limit display — base usage stats available to all
- [ ] Page loads under 200ms (cached stats + indexed queries)

---

## PART 57 — AI TOOL FAVORITES & COLLECTIONS

### 57.1 Overview

Users star AI tools and organize them into named personal collections (like playlists). Collections
appear in the sidebar for quick access. Admin can create "Featured Collections" visible to all users.

### 57.2 Database

```sql
user_tool_favorites
  id           ulid PRIMARY KEY
  user_id      bigint FK → users.id
  tool_slug    varchar(100) FK → ai_tools.slug
  created_at   timestamp
  UNIQUE (user_id, tool_slug)
  INDEX (user_id)

user_collections
  id           ulid PRIMARY KEY
  user_id      bigint FK → users.id NULL   -- NULL = admin-created featured collection
  name         varchar(100)
  description  text NULL
  icon         varchar(50) NULL            -- Tabler icon name e.g. 'ti-fire'
  color        varchar(7) NULL             -- hex color e.g. '#10b981'
  is_featured  boolean DEFAULT false       -- admin-created collections shown to all users
  sort_order   tinyint DEFAULT 0
  created_at   timestamp
  updated_at   timestamp

user_collection_tools
  id             ulid PRIMARY KEY
  collection_id  ulid FK → user_collections.id
  tool_slug      varchar(100)
  sort_order     tinyint DEFAULT 0
  added_at       timestamp
  UNIQUE (collection_id, tool_slug)
```

### 57.3 Service — `app/Services/FavoritesService.php`

```php
public function toggleFavorite(User $user, string $toolSlug): bool
    // Toggle: create if not exists, delete if exists
    // Clear user's favorites cache: Redis::del("user_favorites_{$user->id}")
    // Return: true = now favorited, false = unfavorited

public function getFavorites(User $user): Collection
    // Redis::remember("user_favorites_{$user->id}", 3600, fn() =>
    //   UserToolFavorite::where('user_id', $user->id)->pluck('tool_slug'))

public function isFavorited(User $user, string $toolSlug): bool
    // Read from cached favorites collection

// Collections
public function getUserCollections(User $user): Collection
    // User's own + featured collections (is_featured=true)
    // Eager load: collection_tools count

public function addToCollection(string $collectionId, string $toolSlug, User $user): void
public function removeFromCollection(string $collectionId, string $toolSlug, User $user): void
public function reorderCollection(string $collectionId, array $orderedSlugs, User $user): void
    // Uses vue-draggable-plus reorder → PATCH endpoint
```

### 57.4 Routes & Controllers

```php
// Favorites (simple toggle — no dedicated page needed)
Route::post('/tools/{toolSlug}/favorite', [FavoriteController::class, 'toggle'])->name('tools.favorite');
// Returns JSON { favorited: bool }

// Collections
Route::prefix('collections')->name('collections.')->group(function () {
    Route::get('/',                              [CollectionController::class, 'index'])->name('index');
    Route::post('/',                             [CollectionController::class, 'store'])->name('store');
    Route::get('/{collection}',                  [CollectionController::class, 'show'])->name('show');
    Route::put('/{collection}',                  [CollectionController::class, 'update'])->name('update');
    Route::delete('/{collection}',               [CollectionController::class, 'destroy'])->name('destroy');
    Route::post('/{collection}/tools',           [CollectionController::class, 'addTool'])->name('tools.add');
    Route::delete('/{collection}/tools/{slug}',  [CollectionController::class, 'removeTool'])->name('tools.remove');
    Route::patch('/{collection}/reorder',        [CollectionController::class, 'reorder'])->name('reorder');
});

// Admin — Featured Collections
Route::middleware('auth:admin')->prefix('admin/collections')->group(function () {
    Route::get('/',       [AdminCollectionController::class, 'index']);
    Route::post('/',      [AdminCollectionController::class, 'store']);
    Route::put('/{id}',   [AdminCollectionController::class, 'update']);
    Route::delete('/{id}',[AdminCollectionController::class, 'destroy']);
});
```

### 57.5 UI Integration Points

**On every AI tool card (tool grid + tool page header):**
```html
<button @click="toggleFavorite(tool.slug)"
        :class="isFavorited(tool.slug) ? 'text-amber-500' : 'text-gray-400'">
  <i class="ti ti-star-filled" v-if="isFavorited(tool.slug)"></i>
  <i class="ti ti-star" v-else></i>
</button>
```

**Sidebar:** Under "Tools" group, show user's collections as collapsible items.
Each collection shows its icon + name + tool count badge.

**Collections page (`/collections`):**
- List of user's collections (cards with name, tool count, color accent)
- "Favorites" virtual collection always first (auto-populated from `user_tool_favorites`)
- Create collection button: name + icon picker + color picker
- Inside collection: tool grid (reorderable with vue-draggable-plus)

### 57.6 Checklist

- [ ] Star toggle is optimistic (UI updates instantly, API call fires async)
- [ ] Favorites cached in Redis per-user, cleared on toggle
- [ ] "Favorites" collection is virtual — computed from `user_tool_favorites`, not a DB collection row
- [ ] Admin featured collections visible to ALL users (not just the creator)
- [ ] Collection tool reorder persists via PATCH `/collections/{id}/reorder`
- [ ] Deleting a collection does NOT delete the tools — only the collection record + junction rows

---

## PART 58 — AI OUTPUT RATING SYSTEM

### 58.1 Overview

Users rate each AI generation with thumbs up / thumbs down immediately after output completes.
Admin sees aggregate ratings per tool — low-rated tools surface as an alert.
Different from the existing `tool_reviews` system (that is public star ratings with comments).
This is quick per-generation feedback, private to admin analytics.

### 58.2 Database

```sql
ai_output_ratings
  id              ulid PRIMARY KEY
  user_id         bigint FK → users.id
  tool_slug       varchar(100)
  document_id     ulid FK → documents.id NULL
  generation_history_id ulid FK → generation_history.id NULL
  rating          tinyint    -- 1 = thumbs up, 0 = thumbs down
  feedback_text   varchar(500) NULL  -- optional short note (shown after thumbs down)
  model           varchar(100)
  provider        varchar(50)
  created_at      timestamp
  INDEX (tool_slug, rating, created_at)
  INDEX (user_id)
```

### 58.3 Aggregation

Admin dashboard shows a **Tool Quality widget**:

```sql
-- Cached in Redis: makeai:tool_quality_scores   TTL: 1h
SELECT
  tool_slug,
  COUNT(*) as total_ratings,
  SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as positive,
  ROUND(SUM(rating) / COUNT(*) * 100, 1) as satisfaction_pct
FROM ai_output_ratings
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY tool_slug
ORDER BY satisfaction_pct ASC
```

**Admin alert trigger:** If any tool drops below 60% satisfaction (min 10 ratings in 30 days),
add it to the admin notification panel: "⚠️ Blog Article tool has 54% satisfaction rate this month."

### 58.4 Controller & Route

```php
// POST /ratings  — no auth requirement for POST but user must own the generation
Route::post('/ratings', [OutputRatingController::class, 'store'])->middleware('auth')->name('ratings.store');
// Body: { tool_slug, document_id?, generation_history_id?, rating (0|1), feedback_text? }
// Returns 204 No Content

// Admin stats
Route::get('/admin/ratings', [AdminRatingController::class, 'index']); // table of tool scores
Route::get('/admin/ratings/{toolSlug}', [AdminRatingController::class, 'show']); // breakdown
```

### 58.5 Vue — Output Rating Component

Placed in `ToolOutputSection.vue` immediately after generation completes:

```
Generated output text...
─────────────────────────────────────────────
Was this output helpful?  [👍] [👎]
```

After rating:
- Thumbs up: shows green "Thanks for your feedback!" — disappears after 2s
- Thumbs down: shows small textarea "What was wrong? (optional)" + "Submit" button

```typescript
// In useToolOutput composable (already exists for managing streaming output)
const submitRating = async (rating: 0 | 1, feedback?: string) => {
    await axios.post('/ratings', {
        tool_slug: props.toolSlug,
        document_id: savedDocumentId.value,
        generation_history_id: historyId.value,
        rating,
        feedback_text: feedback,
    })
    rated.value = true
}
```

Rating component hidden if:
- Generation failed / empty output
- User already rated this generation (`rated` ref = true)
- Output is older than 24h (don't show for restored history)

### 58.6 Checklist

- [ ] Rating only submittable once per generation (frontend: `rated` ref; backend: unique check on `generation_history_id`)
- [ ] Thumbs down feedback is optional — never blocks dismissal
- [ ] Admin quality widget cached in Redis, recalculated hourly via scheduled job
- [ ] Alert fires only when tool has ≥ 10 ratings in 30 days (prevents false positives from new tools)
- [ ] `ai_output_ratings` table partitioned by month (same as `ai_usage_logs`) — add to P06 migrations

---

## PART 59 — VARIANT GENERATION

### 59.1 Overview

On any AI tool, the user can optionally request 2 or 3 output variants in a single generation.
Variants are displayed in tabs. User picks the best one and can save it. Credits deducted per variant.

### 59.2 Config

No new DB table. Variant count stored as a generation option. The variant count choice is persisted
in `users.preferences json` under key `default_variant_count` (default: 1).

Admin can configure per-tool in `ai_tools` table — new column:

```sql
ALTER TABLE ai_tools ADD COLUMN max_variants tinyint DEFAULT 3;
-- 1 = variants disabled for this tool (e.g. image tools handle this differently)
-- 2 or 3 = user can choose up to this many variants
```

### 59.3 Backend — Variant Execution

```php
// In GenerateTextController::stream() — modify to handle variants

if ($variantCount > 1) {
    // Fire $variantCount parallel streams
    // Each stream: same prompt, temperature += 0.1 per variant to ensure diversity
    //   variant 1: temperature = $base
    //   variant 2: temperature = min($base + 0.15, 1.5)
    //   variant 3: temperature = min($base + 0.30, 2.0)
    // Return SSE stream with event prefix: "variant_1:", "variant_2:", "variant_3:"
    // Frontend listens and routes chunks to correct tab
    // Credits deducted: variantCount × base credit cost
}
```

**SSE variant format:**
```
data: {"variant": 1, "chunk": "Laravel 12 introduces..."}
data: {"variant": 2, "chunk": "The newest release of Laravel..."}
data: {"variant": 3, "chunk": "With the launch of Laravel 12..."}
data: {"event": "done", "variants": 3, "tokens": [312, 298, 334]}
```

Note: All variants stream concurrently using PHP fibers or multiple `$this->ai->stream()` calls
yielded in round-robin fashion. Use `array_map` with Generators and yield chunks as they arrive.

### 59.4 Vue — Variant Tabs

In `ToolOutputSection.vue`, when `variantCount > 1`:

```
[Variant 1]  [Variant 2]  [Variant 3]      ← tabs, streaming independently
──────────────────────────────────────────────
  Output text of selected variant...

  [Use This Version]  [Copy]  [Save to Docs]
```

- Each tab streams independently — user can switch tabs while both are still generating
- "Use This Version" marks the chosen variant and saves it (discards others)
- Unselected variants are kept in memory for the session (not saved to DB unless user picks them)
- Variant selector: small pill tabs, not full-width tabs (avoid visual clutter on short outputs)

**Variant count selector** (shown before generation):
```
Variants: [1]  [2]  [3]    (pill toggle, only shown if tool.max_variants > 1)
Estimated credits: 3 × 2 = 6 credits
```

### 59.5 Checklist

- [ ] Credits deducted for ALL variants regardless of which one user picks
- [ ] `max_variants = 1` on tool disables the variant selector entirely for that tool
- [ ] Temperature offset logic: variant 1 = base temp, 2 = base+0.15, 3 = base+0.30 (capped at 2.0)
- [ ] SSE events correctly prefixed per variant — frontend routes chunks to correct tab
- [ ] Only the user-selected variant is saved to documents / generation history
- [ ] `default_variant_count` pref stored in `users.preferences` — persists between sessions

---

## PART 60 — AI TOOL EMBED WIDGET

### 60.1 Overview

Any AI tool can be embedded as an `<iframe>` on an external website. The embed renders the tool's
input form and streams output back — full functionality in an embeddable widget.
Admin controls which tools are embeddable. Users get a unique embed code per tool.

### 60.2 Database

```sql
tool_embeds
  id              ulid PRIMARY KEY
  user_id         bigint FK → users.id
  tool_slug       varchar(100)
  token           varchar(64) UNIQUE    -- random 64-char token, part of embed URL
  label           varchar(100) NULL     -- user-facing name e.g. "Blog Writer on MyBlog.com"
  allowed_origins json NULL             -- ["https://myblog.com"] — CORS whitelist; null = any origin
  password_hash   varchar(255) NULL     -- bcrypt hash; null = no password protection
  theme           enum('light','dark','auto') DEFAULT 'auto'
  primary_color   varchar(7) NULL       -- override embed accent color e.g. '#3b82f6'
  show_branding   boolean DEFAULT true  -- "Powered by {app_name}" footer
  usage_count     int DEFAULT 0
  last_used_at    timestamp NULL
  is_active       boolean DEFAULT true
  created_at      timestamp
  updated_at      timestamp
  INDEX (user_id, tool_slug)
```

### 60.3 Embed URL & Rendering

**Embed URL:** `GET /embed/{token}` — renders a minimal HTML page (not Inertia, plain Blade):

```php
// EmbedController.php
public function show(string $token): Response
    $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

    // CORS: check Origin header against allowed_origins (if set)
    // Password: if password_hash set, show password gate first

    // Render: resources/views/embed/tool.blade.php
    // Passes: embed config, tool fields JSON, branding settings
    // No AppLayout — minimal CSS (Tailwind CDN), no sidebar, no nav
    // Increments usage_count asynchronously (Redis INCR, synced hourly)

public function run(Request $request, string $token): StreamedResponse
    // Same TokenGuard + AiService streaming as normal tool execution
    // Credits deducted from the EMBED OWNER's account (not the visitor)
    // Response: SSE stream
```

**`embed/tool.blade.php`** — standalone HTML page:
```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3/dist/tailwind.min.css">
  <style>/* embed-specific minimal styles, respect --embed-primary-color */</style>
</head>
<body class="bg-white dark:bg-gray-900 p-4">
  <!-- Tool input form (rendered from tool.fields JSON) -->
  <!-- Vanilla JS for form submission + SSE stream rendering -->
  <!-- No Vue, no Inertia — must work as standalone iframe -->
  <!-- Powered by {app_name} footer (if show_branding=true) -->
</body>
</html>
```

> **Important:** Embed pages use **vanilla JS only** — no Vue, no Inertia.
> They must function as a fully self-contained iframe.

### 60.4 Routes

```php
// Public embed routes (no auth)
Route::get('/embed/{token}',          [EmbedController::class, 'show'])->name('embed.show');
Route::post('/embed/{token}/run',     [EmbedController::class, 'run'])->name('embed.run');
Route::post('/embed/{token}/unlock',  [EmbedController::class, 'unlock'])->name('embed.unlock');

// Authenticated embed management
Route::middleware('auth')->prefix('tool-embeds')->name('embeds.')->group(function () {
    Route::get('/',            [ToolEmbedController::class, 'index'])->name('index');
    Route::post('/',           [ToolEmbedController::class, 'store'])->name('store');
    Route::put('/{embed}',     [ToolEmbedController::class, 'update'])->name('update');
    Route::delete('/{embed}',  [ToolEmbedController::class, 'destroy'])->name('destroy');
    Route::post('/{embed}/regenerate-token', [ToolEmbedController::class, 'regenerateToken'])->name('regen');
});

// Admin — toggle tool embeddability
Route::middleware('auth:admin')->post('/admin/ai-tools/{slug}/embeddable',
    [AdminToolController::class, 'toggleEmbeddable']);
```

### 60.5 Vue — Embed Management Page

**`/tool-embeds`** in user dashboard:

```
My Tool Embeds
──────────────────────────────────────────────────────────────────────
[+ Create Embed]

Tool              Label              Uses    Status    Actions
Blog Article      MyBlog.com Widget   142    Active    [Edit] [Copy Code] [Delete]
Email Writer      —                    23    Active    [Edit] [Copy Code] [Delete]
──────────────────────────────────────────────────────────────────────

Create / Edit Embed modal:
  Tool: [Blog Article ▾]
  Label: [MyBlog.com Widget__________]
  Allowed Origins: [https://myblog.com    ] [+ Add]
  Password: [____________] (optional)
  Theme: [Auto ●] [Light ○] [Dark ○]
  Primary Color: [#3b82f6 ⬛]
  Show Branding: [✓]
  [Save]

Embed Code (shown after save):
  <iframe src="https://yourdomain.com/embed/abc123xyz"
          width="100%" height="600" frameborder="0"></iframe>
  [Copy Code]
```

### 60.6 Admin Control

In `ai_tools` table, add column: `is_embeddable boolean DEFAULT true`
Admin can toggle per-tool from the AI Tools admin list.
If `is_embeddable = false`, users cannot create embeds for that tool.

### 60.7 Checklist

- [ ] Credits deducted from embed OWNER's account — visitor has no MakeAI account needed
- [ ] Embed owner must have sufficient credits; if not: embed shows "Service temporarily unavailable"
- [ ] CORS check: if `allowed_origins` is set, reject requests from unlisted origins (403)
- [ ] Password gate: rendered server-side in Blade, unlock via POST before showing form
- [ ] Embed iframe uses `postMessage` to parent for dynamic height resizing
- [ ] No Vue/Inertia in embed — fully vanilla JS + Tailwind CDN
- [ ] `is_embeddable = false` blocks embed creation and returns 403 on existing embed URLs for that tool
- [ ] Usage count tracked in Redis (INCR), synced to DB hourly via scheduled job

---

## PART 61 — SMART CREDIT TOP-UP ALERTS

### 61.1 Overview

When a user's credits drop below a configurable threshold, MakeAI automatically:
1. Shows an in-app banner on the dashboard
2. Sends a single email alert

This is a passive upsell mechanism — no human intervention needed.
Only active when `isProAvailable()` is true (subscription system enabled).

### 61.2 Config

Admin settings (in the Billing / Credits settings tab):

```php
settings('credit_alert_threshold')     // default: 100 (credits remaining)
settings('credit_alert_cooldown_days') // default: 7 (days between repeat emails for same user)
```

### 61.3 Trigger Point

In `TokenGuard::after()`, after every credit deduction:

```php
// After deducting credits, check if user just crossed the threshold
if (isProAvailable() && $user->credits <= settings('credit_alert_threshold')) {
    $cacheKey = "credit_alert_sent_{$user->id}";
    if (!Cache::has($cacheKey)) {
        dispatch(new SendCreditAlertJob($user));
        Cache::put($cacheKey, true, now()->addDays(settings('credit_alert_cooldown_days', 7)));
    }
}
```

### 61.4 Job — `SendCreditAlertJob` (queue: `'mail'`)

```php
public function handle(): void
    // Send mail: LowCreditsAlert mail template (existing mail template system — add to P21)
    // Mail vars: user name, credits_remaining, top_up_url = /billing/credits
    // Create in-app notification: NotificationType::LOW_CREDITS
    //   title: "Your credits are running low"
    //   body:  "You have {credits} credits left. Top up to keep generating."
    //   action_url: /billing/credits
    //   icon: ti-battery-1
```

### 61.5 In-App Banner

In `AppLayout.vue` (already wraps all user pages), add a `CreditAlertBanner` component:

```vue
<!-- Shown when: isProAvailable AND user.credits <= threshold AND user hasn't dismissed -->
<CreditAlertBanner v-if="showCreditAlert" @dismiss="dismissAlert" />
```

```
┌─────────────────────────────────────────────────────────────────┐
│ ⚡ Your credits are running low — 84 remaining.                 │
│ Top up now to keep generating without interruption.  [Top Up →] │
│                                                           [×]   │
└─────────────────────────────────────────────────────────────────┘
```

Dismiss stores in `users.preferences.credit_alert_dismissed_at`. Banner reappears after 24h
(not the full cooldown period — banner is less intrusive than email).

### 61.6 Mail Template

Add `LowCreditsAlert` to the mail template system (P21):

```
Template key: low_credits_alert
Subject:      "⚡ Your {app_name} credits are running low"
Variables:    {user_name}, {credits_remaining}, {top_up_url}, {app_name}, {app_logo_url}
```

### 61.7 Checklist

- [ ] `isProAvailable()` checked before ANY credit alert logic — no alerts on non-billing installs
- [ ] Cooldown cache key ensures max 1 email per user per `credit_alert_cooldown_days` setting
- [ ] Alert does NOT fire if user has auto-recharge enabled (future feature — skip if `users.auto_recharge = true`)
- [ ] Banner dismissed state stored in `users.preferences` (survives logout)
- [ ] `low_credits_alert` mail template editable from admin mail templates panel

---

## PART 62 — COMMAND PALETTE ENHANCEMENTS

### 62.1 Overview

The existing command palette (P48) covers tools, documents, and navigation. These enhancements
add: recent generations, favorite tools quick-access, "copy last output" shortcut, "new generation"
shortcut for any tool, and a "switch model" quick command.

### 62.2 New Command Categories

Extend `useCommandPalette` composable to include these new item groups:

**Group: Recents** (new — shown when palette opens with empty query)
```
Recently generated:
  ⚡ Blog Article — "Laravel 12 new features..."   2h ago
  ⚡ Email Writer — "Follow-up email..."           Yesterday
  ⚡ SEO Meta Tags — "10 best practices..."        2 days ago
```
Source: last 5 from `generation_history` (loaded into Pinia on app boot, refreshed on generation).

**Group: Favorites** (new — appears as pinned top section)
```
★ Starred Tools:
  Blog Article · Instagram Caption · Email Writer
```
Source: `user_tool_favorites` (loaded into Pinia on app boot, same as existing favorites system).
If user has no favorites, this group is hidden.

**Group: Quick Actions** (additions to existing "Actions" group)

| Command | Shortcut | Action |
|---------|----------|--------|
| `Copy last output` | `Ctrl+Shift+L` | Copies the most recent generation to clipboard |
| `New generation: {tool}` | — | Navigates to tool and focuses the first input |
| `Switch model` | — | Opens model picker overlay on current tool page |
| `Clear history` | — | Prompts confirmation, then clears generation history |

### 62.3 Implementation

**`useCommandPalette.ts`** — extend existing composable:

```typescript
// New state
const recentGenerations = computed(() => historyStore.recent.slice(0, 5).map(h => ({
    id:       `recent_${h.id}`,
    group:    'Recents',
    label:    aiToolsStore.getBySlug(h.tool_slug)?.name ?? h.tool_slug,
    sublabel: Str.limit(h.output_preview, 60),
    meta:     timeAgo(h.created_at),
    icon:     'ti-history',
    action:   () => router.visit(route('tools.show', h.tool_slug)),
})))

const favoriteTools = computed(() => favoritesStore.slugs.map(slug => ({
    id:     `fav_${slug}`,
    group:  'Favorites',
    label:  aiToolsStore.getBySlug(slug)?.name ?? slug,
    icon:   'ti-star-filled',
    action: () => router.visit(route('tools.show', slug)),
})))

// New quick actions
const copyLastOutput = {
    id:     'copy_last_output',
    group:  'Quick Actions',
    label:  'Copy last output',
    icon:   'ti-clipboard',
    kbd:    'Ctrl+Shift+L',
    action: () => {
        const last = historyStore.recent[0]
        if (last) { navigator.clipboard.writeText(last.output_preview); toast.success('Copied!') }
    }
}
```

**Updated palette result order** (when empty query):
1. Favorites (if any)
2. Recents (last 5 generations)
3. All Tools
4. Documents
5. Navigation
6. Quick Actions

**Updated palette result order** (when query typed):
1. Tools (fuzzy match on name + category)
2. Documents (fuzzy match on title)
3. Recents (fuzzy match on tool name + output preview)
4. Navigation
5. Quick Actions

### 62.4 Keyboard Shortcut Addition

```typescript
// In useKeyboardShortcuts composable (app.vue)
// Add to existing shortcuts:
{ keys: ['ctrl', 'shift', 'l'], action: copyLastOutput, description: 'Copy last output' }
```

Add `Ctrl+Shift+L` to the keyboard shortcut reference modal (`?` key).

### 62.5 Checklist

- [ ] Favorite tools group hidden when user has no favorites (not shown as empty section)
- [ ] Recents group hidden when user has no generation history
- [ ] `Ctrl+Shift+L` copies full output (not just the 500-char preview stored in history) — reads from current page output if available, else falls back to preview
- [ ] All new palette items are keyboard-navigable (arrow keys + Enter)
- [ ] Palette search correctly fuzzy-matches new groups alongside existing
- [ ] Updated shortcut modal (`?`) lists `Ctrl+Shift+L` with description

---

## PART 64 — EXPORT CENTER (EXCEL/CSV & PDF) ✅

> **Packages (non-negotiable):**
> - Excel/CSV: `maatwebsite/excel` — queued, chunked, styled xlsx + csv
> - PDF: `mpdf/mpdf` — pure PHP, shared-hosting safe, RTL + Unicode support
> - No `barryvdh/laravel-snappy` or `wkhtmltopdf` — requires server binary, breaks shared hosting installs

```bash
composer require maatwebsite/excel mpdf/mpdf
```

---

### 64.1 ExportService (Central Entry Point)

**`app/Services/ExportService.php`**

All controllers call ExportService — never call `Excel::` or `Mpdf` directly in controllers.

```php
namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Illuminate\Http\Response;
use Illuminate\Support\LazyCollection;

class ExportService
{
    // --- EXCEL / CSV ---

    // Immediate download (small datasets < 5,000 rows)
    public function downloadExcel(string $exportClass, string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
        return Excel::download(new $exportClass, $filename . '.xlsx');

    // Queued export for large datasets (> 5,000 rows)
    // Stores file in storage/exports/{admin_id}/{filename}
    // Notifies admin via in-app notification when ready
    public function queueExcel(string $exportClass, string $filename, int $adminId): void
        $path = 'exports/' . $adminId . '/' . $filename . '-' . now()->format('Y-m-d-His') . '.xlsx';
        Excel::queue(new $exportClass, $path, 'local')
            ->chain([new NotifyExportReadyJob($adminId, $path, $filename)]);

    // Simple CSV — streams directly without PhpSpreadsheet overhead
    // Use for very large datasets (100k+ rows) — memory safe
    public function streamCsv(string $filename, array $headers, LazyCollection $rows, callable $mapper): Response
        return response()->stream(function () use ($headers, $rows, $mapper) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            $rows->each(fn($row) => fputcsv($handle, $mapper($row)));
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
            'X-Accel-Buffering'   => 'no',
        ]);

    // --- PDF ---

    // Render a Blade view to PDF and stream as download
    public function downloadPdf(string $view, array $data, string $filename, array $options = []): Response
        $mpdf = new Mpdf(array_merge([
            'mode'        => 'utf-8',
            'format'      => 'A4',
            'margin_top'  => 20,
            'margin_bottom' => 20,
            'margin_left' => 15,
            'margin_right' => 15,
        ], $options));
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML(view($view, $data)->render());
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
}
```

---

### 64.2 Export Classes (maatwebsite/excel)

Each export class lives in `app/Exports/Admin/`. All implement at minimum:
`FromQuery` (memory-safe chunk fetching) + `WithHeadings` + `WithMapping`.
Large exports also implement `ShouldQueue` + `WithChunkReading`.

---

#### `UsersExport`

```php
// app/Exports/Admin/UsersExport.php
use Maatwebsite\Excel\Concerns\{FromQuery, WithHeadings, WithMapping, ShouldQueue, WithChunkReading, Exportable};

class UsersExport implements FromQuery, WithHeadings, WithMapping, ShouldQueue, WithChunkReading
{
    use Exportable;
    public int $chunkSize = 500;

    public function __construct(
        private ?string $status = null,
        private ?string $planId = null,
        private ?string $dateFrom = null,
        private ?string $dateTo = null,
    ) {}

    public function query(): Builder
        return User::query()
            ->with('plan')
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->planId, fn($q) => $q->where('plan_id', $this->planId))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->select(['id','name','email','status','credits','credits_used_month','plan_id','created_at','last_active_at']);

    public function headings(): array
        return ['Name','Email','Status','Plan','Credits Remaining','Credits Used (Month)','Joined','Last Active'];

    public function map($user): array
        return [
            $user->name,
            $user->email,
            ucfirst($user->status),
            $user->plan?->name ?? 'Free',
            $user->credits,
            $user->credits_used_month,
            $user->created_at->format('Y-m-d'),
            $user->last_active_at?->format('Y-m-d') ?? 'Never',
        ];
}
```

---

#### `AiUsageExport`

```php
// app/Exports/Admin/AiUsageExport.php
class AiUsageExport implements FromQuery, WithHeadings, WithMapping, ShouldQueue, WithChunkReading
{
    use Exportable;
    public int $chunkSize = 1000;

    public function __construct(
        private ?string $userId = null,
        private ?string $toolSlug = null,
        private ?string $provider = null,
        private string $dateFrom = '',
        private string $dateTo = '',
    ) {}

    public function query(): Builder
        return AiUsageLog::query()
            ->with('user:id,name,email')
            ->when($this->userId,   fn($q) => $q->where('user_id', $this->userId))
            ->when($this->toolSlug, fn($q) => $q->where('tool', $this->toolSlug))
            ->when($this->provider, fn($q) => $q->where('provider', $this->provider))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->select(['id','user_id','tool','model','provider','tokens_input','tokens_output','cost_usd','credits_used','status','response_time_ms','created_at'])
            ->orderBy('created_at', 'desc');

    public function headings(): array
        return ['Date','User','Tool','Model','Provider','Input Tokens','Output Tokens','Cost (USD)','Credits Used','Status','Response Time (ms)'];

    public function map($log): array
        return [
            $log->created_at->format('Y-m-d H:i'),
            $log->user ? $log->user->name . ' (' . $log->user->email . ')' : 'Deleted User',
            $log->tool,
            $log->model,
            ucfirst($log->provider),
            $log->tokens_input,
            $log->tokens_output,
            number_format($log->cost_usd, 6),
            $log->credits_used,
            ucfirst($log->status),
            $log->response_time_ms,
        ];
}
```

---

#### `RevenueExport`

```php
// app/Exports/Admin/RevenueExport.php
// Only registered when isProAvailable()

class RevenueExport implements FromQuery, WithHeadings, WithMapping, ShouldQueue, WithChunkReading
{
    use Exportable;
    public int $chunkSize = 500;

    public function __construct(
        private string $dateFrom,
        private string $dateTo,
        private ?string $gateway = null,
        private ?string $status = null,
    ) {}

    public function query(): Builder
        return Payment::query()
            ->with('user:id,name,email', 'plan:id,name')
            ->when($this->gateway, fn($q) => $q->where('gateway', $this->gateway))
            ->when($this->status,  fn($q) => $q->where('status', $this->status))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->orderBy('created_at', 'desc');

    public function headings(): array
        return ['Date','Transaction ID','User','Plan','Amount','Currency','Gateway','Status','Invoice #'];

    public function map($payment): array
        return [
            $payment->created_at->format('Y-m-d H:i'),
            $payment->transaction_id,
            $payment->user?->name . ' (' . $payment->user?->email . ')',
            $payment->plan?->name ?? '—',
            number_format($payment->amount, 2),
            strtoupper($payment->currency),
            ucfirst($payment->gateway),
            ucfirst($payment->status),
            $payment->invoice_number ?? '—',
        ];
}
```

---

#### `AffiliateCommissionsExport`

```php
// app/Exports/Admin/AffiliateCommissionsExport.php
class AffiliateCommissionsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function query(): Builder
        return AffiliateCommission::query()
            ->with('referrer:id,name,email', 'referredUser:id,name,email')
            ->orderBy('created_at', 'desc');

    public function headings(): array
        return ['Date','Referrer','Referred User','Order Amount','Commission','Rate %','Status','Paid At'];

    public function map($c): array
        return [
            $c->created_at->format('Y-m-d'),
            $c->referrer->name . ' (' . $c->referrer->email . ')',
            $c->referredUser->name,
            number_format($c->order_amount, 2),
            number_format($c->commission_amount, 2),
            $c->commission_rate . '%',
            ucfirst($c->status),
            $c->paid_at?->format('Y-m-d') ?? '—',
        ];
}
```

---

### 64.3 PDF Report Views (mPDF Blade Templates)

All PDF templates live in `resources/views/admin/reports/pdf/`.
Each template receives its data array and uses inline CSS only (mPDF does not support external stylesheets).

**Base layout shared by all PDF reports** — `resources/views/admin/reports/pdf/layout.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
  .header { border-bottom: 2px solid #10b981; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
  .header-logo { font-size: 18px; font-weight: bold; color: #10b981; }
  .header-meta { font-size: 10px; color: #6b7280; text-align: right; }
  .report-title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
  .report-subtitle { font-size: 11px; color: #6b7280; margin-bottom: 20px; }
  .stats-row { display: flex; gap: 12px; margin-bottom: 20px; }
  .stat-box { flex: 1; background: #f3f4f6; border-radius: 6px; padding: 10px 12px; }
  .stat-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
  .stat-value { font-size: 18px; font-weight: bold; color: #111827; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background: #f9fafb; border-bottom: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; }
  td { border-bottom: 1px solid #f3f4f6; padding: 7px 10px; font-size: 10px; color: #374151; }
  tr:last-child td { border-bottom: none; }
  .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 600; }
  .badge-green  { background: #d1fae5; color: #065f46; }
  .badge-red    { background: #fee2e2; color: #991b1b; }
  .badge-amber  { background: #fef3c7; color: #92400e; }
  .badge-gray   { background: #f3f4f6; color: #374151; }
  .footer { border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 24px; font-size: 9px; color: #9ca3af; display: flex; justify-content: space-between; }
  .page-break { page-break-after: always; }
</style>
</head>
<body>
<div class="header">
  <div class="header-logo">{{ settings('app_name') }}</div>
  <div class="header-meta">
    Generated: {{ now()->format('M d, Y H:i') }}<br>
    By: {{ auth('admin')->user()->name }}
  </div>
</div>
@yield('content')
<div class="footer">
  <span>{{ settings('app_name') }} — Admin Report</span>
  <span>Page {PAGENO} of {nbpg}</span>
</div>
</body>
</html>
```

---

**AI Usage Report PDF** — `resources/views/admin/reports/pdf/ai-usage.blade.php`:

```blade
@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">AI Usage Report</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}  ·  {{ $totalRows }} records</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">Total Requests</div>
    <div class="stat-value">{{ number_format($stats['total_requests']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Total Tokens</div>
    <div class="stat-value">{{ number_format($stats['total_tokens']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Total Cost (USD)</div>
    <div class="stat-value">${{ number_format($stats['total_cost'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Unique Users</div>
    <div class="stat-value">{{ number_format($stats['unique_users']) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Date</th><th>User</th><th>Tool</th><th>Model</th>
      <th>Tokens In</th><th>Tokens Out</th><th>Cost (USD)</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->created_at->format('Y-m-d H:i') }}</td>
      <td>{{ $row->user?->name ?? 'Deleted' }}</td>
      <td>{{ $row->tool }}</td>
      <td>{{ $row->model }}</td>
      <td>{{ number_format($row->tokens_input) }}</td>
      <td>{{ number_format($row->tokens_output) }}</td>
      <td>${{ number_format($row->cost_usd, 6) }}</td>
      <td>
        <span class="badge {{ $row->status === 'success' ? 'badge-green' : 'badge-red' }}">
          {{ ucfirst($row->status) }}
        </span>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
```

---

**Revenue Report PDF** — `resources/views/admin/reports/pdf/revenue.blade.php`:

```blade
@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">Revenue Report</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value">${{ number_format($stats['total_revenue'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Transactions</div>
    <div class="stat-value">{{ number_format($stats['transaction_count']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Avg. Transaction</div>
    <div class="stat-value">${{ number_format($stats['avg_transaction'], 2) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Refunds</div>
    <div class="stat-value">${{ number_format($stats['total_refunds'], 2) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Date</th><th>Transaction ID</th><th>User</th><th>Plan</th>
      <th>Amount</th><th>Gateway</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->created_at->format('Y-m-d') }}</td>
      <td style="font-family: monospace; font-size: 9px;">{{ $row->transaction_id }}</td>
      <td>{{ $row->user?->name ?? '—' }}</td>
      <td>{{ $row->plan?->name ?? '—' }}</td>
      <td>${{ number_format($row->amount, 2) }} {{ strtoupper($row->currency) }}</td>
      <td>{{ ucfirst($row->gateway) }}</td>
      <td>
        <span class="badge {{ match($row->status) { 'paid' => 'badge-green', 'refunded' => 'badge-red', 'pending' => 'badge-amber', default => 'badge-gray' } }}">
          {{ ucfirst($row->status) }}
        </span>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
```

---

**User Report PDF** — `resources/views/admin/reports/pdf/users.blade.php`:

```blade
@extends('admin.reports.pdf.layout')
@section('content')
<div class="report-title">User Report</div>
<div class="report-subtitle">{{ $dateFrom }} — {{ $dateTo }}</div>

<div class="stats-row">
  <div class="stat-box">
    <div class="stat-label">Total Users</div>
    <div class="stat-value">{{ number_format($stats['total']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">New This Period</div>
    <div class="stat-value">{{ number_format($stats['new']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Active (30d)</div>
    <div class="stat-value">{{ number_format($stats['active']) }}</div>
  </div>
  <div class="stat-box">
    <div class="stat-label">Pro Subscribers</div>
    <div class="stat-value">{{ number_format($stats['pro']) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr><th>Name</th><th>Email</th><th>Plan</th><th>Credits</th><th>Status</th><th>Joined</th></tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->name }}</td>
      <td>{{ $row->email }}</td>
      <td>{{ $row->plan?->name ?? 'Free' }}</td>
      <td>{{ number_format($row->credits) }}</td>
      <td>
        <span class="badge {{ $row->status === 'active' ? 'badge-green' : 'badge-red' }}">
          {{ ucfirst($row->status) }}
        </span>
      </td>
      <td>{{ $row->created_at->format('Y-m-d') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
```

---

### 64.4 Export Center Controller

**`app/Http/Controllers/Admin/ExportCenterController.php`**

```php
// Admin → Reports → Export Center
// GET /admin/reports/export-center
public function index(): Response
    // Inertia 'Admin/Reports/ExportCenter'
    // Pass: available export types, recent queued exports list (stored files in storage/exports/{admin_id}/)

// POST /admin/reports/export
public function export(Request $request): JsonResponse|BinaryFileResponse|Response
    $request->validate([
        'type'      => 'required|in:users,ai-usage,revenue,affiliates,audit-log',
        'format'    => 'required|in:xlsx,csv,pdf',
        'date_from' => 'nullable|date',
        'date_to'   => 'nullable|date',
        // type-specific filters passed as extra fields
    ]);

    $type     = $request->type;
    $format   = $request->format;
    $dateFrom = $request->date_from ?? now()->subDays(30)->toDateString();
    $dateTo   = $request->date_to   ?? now()->toDateString();
    $filename = $type . '-' . $dateFrom . '-to-' . $dateTo;

    // Route to correct export class / PDF view
    return match ([$type, $format]) {

        // XLSX — queue if > 5000 rows, immediate if small
        ['users',    'xlsx'] => $this->smartExcel(new UsersExport(...$request->only(['status','plan_id','date_from','date_to'])), $filename),
        ['ai-usage', 'xlsx'] => $this->smartExcel(new AiUsageExport(...$request->only(['user_id','tool_slug','provider','date_from','date_to'])), $filename),
        ['revenue',  'xlsx'] => $this->smartExcel(new RevenueExport($dateFrom, $dateTo, $request->gateway, $request->status), $filename),
        ['affiliates','xlsx'] => $this->exportService->downloadExcel(AffiliateCommissionsExport::class, $filename),

        // CSV — always stream (memory safe)
        ['users',    'csv']  => $this->exportService->streamCsv($filename, [...], User::lazy(), fn($u) => [...]),
        ['ai-usage', 'csv']  => $this->exportService->streamCsv($filename, [...], AiUsageLog::lazy()->where(...), fn($l) => [...]),
        ['revenue',  'csv']  => $this->exportService->streamCsv($filename, [...], Payment::lazy()->where(...), fn($p) => [...]),

        // PDF
        ['users',    'pdf']  => $this->exportService->downloadPdf('admin.reports.pdf.users',   $this->getUsersPdfData($request),    $filename),
        ['ai-usage', 'pdf']  => $this->exportService->downloadPdf('admin.reports.pdf.ai-usage', $this->getAiUsagePdfData($request),   $filename),
        ['revenue',  'pdf']  => $this->exportService->downloadPdf('admin.reports.pdf.revenue',  $this->getRevenuePdfData($request),   $filename),

        default => response()->json(['message' => 'Unsupported export type/format'], 422),
    };

// Decide: immediate download vs queued based on estimated row count
private function smartExcel(object $exportClass, string $filename): mixed
    $estimatedRows = $exportClass->query()->count();
    if ($estimatedRows > 5000) {
        $this->exportService->queueExcel(get_class($exportClass), $filename, auth('admin')->id());
        return response()->json(['message' => 'Export queued. You will be notified when it's ready.', 'queued' => true]);
    }
    return $this->exportService->downloadExcel(get_class($exportClass), $filename);

// PDF data helpers — fetch summarized data for PDF header stats + paginated rows
private function getUsersPdfData(Request $request): array
    // Returns: stats[], rows (paginated — max 5000 rows for PDF)

private function getAiUsagePdfData(Request $request): array
    // Returns: stats[], rows, dateFrom, dateTo, totalRows

private function getRevenuePdfData(Request $request): array
    // Returns: stats[], rows, dateFrom, dateTo
```

---

### 64.5 Export Center Vue Page

**`resources/js/Pages/Admin/Reports/ExportCenter.vue`**

```
Admin → Reports → Export Center
──────────────────────────────────────────────────────────────────

┌── Export Builder ────────────────────────────────────────────────┐
│                                                                  │
│  Data type:  [Users ▾]  [AI Usage ▾]  [Revenue ▾]  [More ▾]     │
│                                                                  │
│  Date range: [From ____/____/____]  [To ____/____/____]         │
│              [Last 7d] [Last 30d] [This month] [Custom]         │
│                                                                  │
│  Filters: (change based on selected type)                        │
│    Users:     Status [All ▾]  Plan [All ▾]                       │
│    AI Usage:  Tool [All ▾]  Provider [All ▾]  Model [All ▾]      │
│    Revenue:   Gateway [All ▾]  Status [All ▾]                    │
│                                                                  │
│  Format:  [XLSX]  [CSV]  [PDF]                                   │
│                                                                  │
│  [Export Now ↓]                                                  │
│   ↳ If large dataset: "Queued — we'll notify you when ready"     │
└──────────────────────────────────────────────────────────────────┘

┌── Recent Exports ────────────────────────────────────────────────┐
│  Filename                      Size    Date         Actions      │
│  users-2025-01-01-to-2025-01-31.xlsx  234KB  2 min ago  [↓] [×] │
│  ai-usage-2025-01-01-...pdf          182KB  1 hour ago  [↓] [×] │
└──────────────────────────────────────────────────────────────────┘
```

**Format selector logic:**
- XLSX: always available
- CSV: always available — recommended for very large exports
- PDF: available for all types but limited to latest 5,000 rows (show note: "PDF limited to 5,000 rows. Use XLSX/CSV for full data.")

**Queued export flow:**
- POST returns `{ queued: true, message: "..." }`
- Frontend shows toast: "Export queued — you'll get a notification when it's ready"
- Admin receives in-app notification with download link when job completes
- File stored in `storage/app/exports/{admin_id}/` — accessible via authenticated download route

**Inline export buttons** (on other admin pages like Users list, Transactions, etc.):
These trigger the same endpoint with pre-filled parameters:
```vue
<!-- In UsersIndex.vue toolbar -->
<ExportButton type="users" :filters="activeFilters" />
```
`ExportButton` is a shared component that opens a small dropdown: `[XLSX] [CSV] [PDF]`
and calls POST `/admin/reports/export` with current page filters pre-applied.

---

### 64.6 Routes

```php
Route::middleware(['auth:admin', 'admin.permission:reports.export'])->group(function () {
    Route::get('/admin/reports/export-center',   [ExportCenterController::class, 'index'])->name('admin.reports.export-center');
    Route::post('/admin/reports/export',          [ExportCenterController::class, 'export'])->name('admin.reports.export');
    Route::get('/admin/reports/exports/{file}',   [ExportCenterController::class, 'download'])->name('admin.reports.export.download');
    Route::delete('/admin/reports/exports/{file}', [ExportCenterController::class, 'deleteFile'])->name('admin.reports.export.delete');
});
```

---

### 64.7 Job — `NotifyExportReadyJob` (queue: `mail`)

```php
// app/Jobs/NotifyExportReadyJob.php
class NotifyExportReadyJob implements ShouldQueue
{
    public function __construct(
        private int $adminId,
        private string $filePath,   // relative to storage/app/
        private string $filename,
    ) {}

    public function handle(): void
        $admin = Admin::find($this->adminId);

        // Create in-app notification
        AdminNotification::create([
            'admin_id'   => $this->adminId,
            'type'       => 'export_ready',
            'title'      => 'Export ready: ' . $this->filename,
            'body'       => 'Your export is ready to download.',
            'action_url' => route('admin.reports.export.download', ['file' => basename($this->filePath)]),
            'icon'       => 'ti-download',
        ]);

        // Broadcast via Reverb to admin's private channel
        broadcast(new AdminNotificationEvent($this->adminId, 'export_ready', $this->filename));

        // Auto-delete exported file after 24h
        dispatch(new DeleteExportFileJob($this->filePath))->delay(now()->addHours(24));
}
```

---

### 64.8 Scheduled Cleanup

```php
// In routes/console.php — add to existing scheduled tasks:
Schedule::command('exports:cleanup')->daily();
// Deletes all files in storage/app/exports/ older than 24 hours
```

```php
// app/Console/Commands/CleanupExports.php
public function handle(): void
    $files = Storage::files('exports');
    foreach ($files as $file) {
        if (Storage::lastModified($file) < now()->subHours(24)->timestamp) {
            Storage::delete($file);
        }
    }
    $this->info('Export files cleaned.');
```

---

### 64.9 Checklist

- [ ] `composer require maatwebsite/excel mpdf/mpdf` added to project dependencies
- [ ] `ExportService` is the only class that calls `Excel::` or `Mpdf` — no direct calls in controllers
- [ ] All Excel exports implement `ShouldQueue` + `WithChunkReading` — never load all rows into memory
- [ ] `smartExcel()`: count rows first — queue if > 5,000, immediate download if ≤ 5,000
- [ ] CSV exports use `LazyCollection` + `fputcsv` streaming — no `->get()` on full table
- [ ] PDF exports capped at 5,000 rows — clearly communicated to admin in UI
- [ ] PDF Blade templates use `DejaVu Sans` font (bundled with mPDF) for full Unicode/RTL support
- [ ] `{PAGENO}` and `{nbpg}` in PDF footer are mPDF placeholders — NOT PHP variables
- [ ] `settings('app_name')` used in PDF header — never hardcoded "MakeAI"
- [ ] Exported files stored in `storage/app/exports/{admin_id}/` — not public disk
- [ ] Download route streams file via `Storage::download()` — never exposes raw path
- [ ] `admin.permission:reports.export` middleware gates all export routes
- [ ] `NotifyExportReadyJob` auto-schedules `DeleteExportFileJob` 24h after creation
- [ ] `exports:cleanup` artisan command registered in scheduler (daily)
- [ ] `ExportButton` shared component reused across all admin list pages (Users, Transactions, etc.)
- [ ] Queued export failure: job `failed()` method sends error notification to admin
- [ ] `isProAvailable()` checked before rendering Revenue export options (no revenue without billing)


---

---

## 🔷 LAYER 10 — API & INFRASTRUCTURE (continued) ✅

## PART 66 — BROADCASTING & INFRASTRUCTURE FALLBACKS (Redis-Optional)

> **Goal:** MakeAI must run fully on shared hosting (no Redis) AND on VPS with Redis — same codebase,
> zero manual code changes. Admin selects broadcasting driver from admin panel. All fallbacks are
> automatic and transparent to end users.

---

### 66.1 The Three-Tier Broadcasting Strategy

```
Admin Panel Setting: broadcasting_driver
         │
         ▼
┌─────────────────────────────────────────────────────┐
│  "reverb"  → Redis available? ──YES──► Laravel Reverb (WebSocket, self-hosted)
│                                └─NO──► Auto-downgrade to HTTP Polling + warn in Site Health
│                                                                                              │
│  "pusher"  → Pusher API keys set? ──YES──► Pusher WebSocket
│                                   └─NO──► Show config warning in admin, use Polling          │
│                                                                                              │
│  "polling" → HTTP Polling (30s interval) — zero config, works everywhere                   │
└─────────────────────────────────────────────────────┘
```

**Key rule:** Broadcasting driver change takes effect immediately — no server restart, no cache clear.
Laravel Echo on the frontend detects the driver from `$page.props.broadcasting` (Inertia shared data).

---

### 66.2 Settings Table Entries

```php
// Added to SettingsSeeder.php — group: 'broadcasting'
[
    ['key' => 'broadcasting_driver',      'value' => 'reverb',  'type' => 'string',  'group' => 'broadcasting'],
    ['key' => 'pusher_app_id',            'value' => '',        'type' => 'encrypted','group' => 'broadcasting'],
    ['key' => 'pusher_app_key',           'value' => '',        'type' => 'string',  'group' => 'broadcasting'],
    ['key' => 'pusher_app_secret',        'value' => '',        'type' => 'encrypted','group' => 'broadcasting'],
    ['key' => 'pusher_app_cluster',       'value' => 'mt1',     'type' => 'string',  'group' => 'broadcasting'],
    ['key' => 'polling_interval_seconds', 'value' => '30',      'type' => 'integer', 'group' => 'broadcasting'],
]
```

---

### 66.3 BroadcastingService

**`app/Services/BroadcastingService.php`**

```php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class BroadcastingService
{
    /**
     * Resolve the effective broadcasting driver at runtime.
     * Never read BROADCAST_DRIVER env directly — always use this method.
     */
    public function resolveDriver(): string
    {
        $selected = settings('broadcasting_driver', 'reverb'); // reverb | pusher | polling

        return match ($selected) {
            'reverb'  => $this->isRedisAvailable() ? 'reverb' : 'polling',
            'pusher'  => $this->isPusherConfigured() ? 'pusher' : 'polling',
            default   => 'polling',
        };
    }

    public function isRedisAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isPusherConfigured(): bool
    {
        return filled(settings('pusher_app_key'))
            && filled(settings('pusher_app_secret'))
            && filled(settings('pusher_app_id'));
    }

    /**
     * Returns config array for frontend (passed via Inertia shared data).
     * Never exposes secret keys.
     */
    public function frontendConfig(): array
    {
        $driver = $this->resolveDriver();

        return match ($driver) {
            'reverb' => [
                'driver'  => 'reverb',
                'key'     => config('broadcasting.connections.reverb.key'),
                'host'    => config('broadcasting.connections.reverb.options.host'),
                'port'    => config('broadcasting.connections.reverb.options.port'),
                'scheme'  => config('broadcasting.connections.reverb.options.scheme'),
            ],
            'pusher' => [
                'driver'  => 'pusher',
                'key'     => settings('pusher_app_key'),   // public key only — safe to expose
                'cluster' => settings('pusher_app_cluster', 'mt1'),
            ],
            default => [
                'driver'          => 'polling',
                'interval_seconds'=> (int) settings('polling_interval_seconds', 30),
            ],
        };
    }

    /**
     * Used in Site Health Monitor to surface warnings.
     */
    public function healthStatus(): array
    {
        $selected = settings('broadcasting_driver', 'reverb');
        $effective = $this->resolveDriver();
        $degraded  = $selected !== $effective;

        return [
            'selected'  => $selected,
            'effective' => $effective,
            'degraded'  => $degraded,
            'reason'    => $degraded ? $this->degradationReason($selected) : null,
        ];
    }

    private function degradationReason(string $selected): string
    {
        return match ($selected) {
            'reverb' => 'Redis is not available. Reverb requires Redis. Falling back to HTTP polling.',
            'pusher' => 'Pusher API keys are not configured. Falling back to HTTP polling.',
            default  => 'Unknown',
        };
    }
}
```

---

### 66.4 Inertia Shared Data — Broadcasting Config

**`app/Http/Middleware/HandleInertiaRequests.php`** — `share()` method এ add করো:

```php
use App\Services\BroadcastingService;

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        // ... existing shared data ...
        'broadcasting' => app(BroadcastingService::class)->frontendConfig(),
    ]);
}
```

Frontend এ `$page.props.broadcasting` হিসেবে সবসময় available থাকবে।

---

### 66.5 Frontend Echo Bootstrap

**`resources/js/bootstrap.ts`** — Echo initialization এখানে হবে:

```typescript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { usePage } from '@inertiajs/vue3'

export function initEcho(): Echo | null {
    const broadcasting = usePage().props.broadcasting as BroadcastingConfig

    if (broadcasting.driver === 'reverb') {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key:         broadcasting.key,
            wsHost:      broadcasting.host,
            wsPort:      broadcasting.port ?? 80,
            wssPort:     broadcasting.port ?? 443,
            forceTLS:    broadcasting.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        })
        return window.Echo
    }

    if (broadcasting.driver === 'pusher') {
        window.Pusher = Pusher
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key:         broadcasting.key,
            cluster:     broadcasting.cluster,
            forceTLS:    true,
        })
        return window.Echo
    }

    // polling — Echo not initialized, composable handles polling
    return null
}
```

---

### 66.6 useNotifications Composable — Polling Fallback

**`resources/js/composables/useNotifications.ts`**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

export function useNotifications() {
    const unreadCount   = ref(0)
    const notifications = ref<Notification[]>([])
    let pollingTimer: ReturnType<typeof setInterval> | null = null
    let echoChannel: any = null

    const fetchNotifications = async () => {
        const { data } = await axios.get('/user/notifications')
        notifications.value = data.notifications
        unreadCount.value   = data.unread_count
    }

    const handleNewNotification = (notification: Notification) => {
        notifications.value.unshift(notification)
        unreadCount.value++
    }

    onMounted(() => {
        const broadcasting = usePage().props.broadcasting as any

        if (broadcasting.driver !== 'polling' && window.Echo) {
            // WebSocket path (Reverb or Pusher)
            const userId = usePage().props.auth?.user?.id
            echoChannel = window.Echo
                .private(`App.Models.User.${userId}`)
                .notification(handleNewNotification)
        } else {
            // Polling fallback — fetch on mount + interval
            fetchNotifications()
            const interval = (broadcasting.interval_seconds ?? 30) * 1000
            pollingTimer = setInterval(fetchNotifications, interval)
        }
    })

    onUnmounted(() => {
        if (pollingTimer) clearInterval(pollingTimer)
        if (echoChannel) echoChannel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')
    })

    return { unreadCount, notifications, fetchNotifications }
}
```

---

### 66.8 Site Health Monitor Integration

**`app/Services/SiteHealthService.php`** এ add করো:

```php
public function broadcastingCheck(): HealthCheck
{
    $status = app(BroadcastingService::class)->healthStatus();

    if ($status['degraded']) {
        return HealthCheck::warn(
            title:      'Broadcasting Degraded',
            detail:     $status['reason'],
            suggestion: 'Go to Settings → Broadcasting to configure your driver.',
        );
    }

    $label = match ($status['effective']) {
        'reverb'  => 'Laravel Reverb (WebSocket)',
        'pusher'  => 'Pusher (WebSocket)',
        'polling' => 'HTTP Polling (30s)',
    };

    return HealthCheck::pass(
        title:  'Broadcasting',
        detail: "Active driver: {$label}",
    );
}
```

Site Health display:
```
✅ Broadcasting   — Laravel Reverb (WebSocket)        [VPS with Redis]
🟡 Broadcasting   — Reverb selected but Redis missing  [shared hosting warning]
                    Falling back to HTTP Polling
                    → Go to Settings › Broadcasting
✅ Broadcasting   — Pusher (WebSocket)                 [Pusher configured]
✅ Broadcasting   — HTTP Polling (30s interval)        [polling selected]
```

---

### 66.9 Infrastructure Fallback Summary Table

| Feature | With Redis (VPS) | Without Redis (Shared Hosting) |
|---------|-----------------|-------------------------------|
| **Cache** | Redis (fast) | File driver (auto) |
| **Queue** | Redis + Horizon | Database driver (auto) |
| **Sessions** | Redis | File driver (auto) |
| **Broadcasting** | Reverb / Pusher | Pusher or Polling (admin choice) |
| **Rate Limiting** | Sliding window (Redis sorted sets) | Fixed window (DB table) |
| **Real-time Notifications** | WebSocket push | HTTP polling (30s) |
| **Horizon Dashboard** | Available | Hidden (not available without Redis) |

---

### 66.10 `.env.example` Entries

```env
# Broadcasting — set by installation wizard based on server detection
# Options: reverb | pusher | polling (stored in settings table, not .env)
# .env only needs REVERB_* if using Reverb
REVERB_APP_ID=makeai
REVERB_APP_KEY=makeai-key
REVERB_APP_SECRET=makeai-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Pusher keys are stored encrypted in settings table — NOT in .env
# Configure via Admin → Settings → Broadcasting
```

---

### 66.11 Installation Wizard Update (Step 1 — Server Requirements)

Step 1 এ Redis row এ এই UI দেখাবে:

```
Redis            ✅ Found     → Reverb + Horizon available
                 ⚠️ Not found → File/DB fallback mode
                               Broadcasting: configure Pusher or use Polling
                               (All features work, WebSocket notifications delayed 30s)
```

Wizard এ Redis not found হলে:
- `CACHE_DRIVER=file` set
- `QUEUE_CONNECTION=database` set
- `queue:table` + `queue:failed-table` migration auto-run
- Broadcasting driver default → `polling` (admin can change to Pusher later)

---

### 66.12 Checklist: Broadcasting & Infrastructure Fallbacks

- [ ] `BroadcastingService::resolveDriver()` returns correct driver based on settings + availability check
- [ ] Redis unavailable + Reverb selected → auto-downgrade to polling (no crash, no error page)
- [ ] Pusher selected + keys missing → auto-downgrade to polling + admin warning shown
- [ ] `$page.props.broadcasting` always present in Inertia shared data
- [ ] `useNotifications` composable: WebSocket path tested (Reverb + Pusher), polling path tested
- [ ] Polling interval respects `polling_interval_seconds` setting (default 30s)
- [ ] Pusher "Test Connection" returns clear success/error message
- [ ] Reverb "Test Connection" correctly reports Redis availability
- [ ] Site Health Monitor shows correct status for all 4 broadcasting states
- [ ] Horizon dashboard hidden in admin menu when Redis unavailable
- [ ] Queue `database` driver: `jobs` + `failed_jobs` tables created by wizard automatically
- [ ] Rate limiting: DB fallback active when Redis unavailable (fixed window, `rate_limit_hits` table)
- [ ] `pusher/pusher-php-server` added to `composer.json` (needed for test connection)
- [ ] `pusher-js` added to `package.json` (needed for Echo Pusher driver)
- [ ] Pusher App Secret + App ID stored encrypted in settings table — never in `.env` or frontend
- [ ] Installation wizard auto-detects Redis and sets correct default drivers in `.env`
- [ ] All fallback modes tested on a clean shared hosting environment (no Redis, no Horizon)

---

# PART 67 — RAG TOOLS SUITE (Chat with PDF / Website / YouTube / KB Writer)
---

## 67.1 Overview

A suite of user-facing RAG-powered tools built **entirely on top of the existing Knowledge Base
infrastructure** (P11 Knowledge Base, `embeddings` queue, `IngestDocument`, `IngestUrl`,
`ProcessRagQuery`, `DeleteDocumentEmbeddings`). No new vector pipeline is created — every RAG tool
is a thin UX layer over an **ephemeral, auto-created knowledge base collection**.

**Tools shipped in this suite (8 tools):**

| # | Name | Slug | Source type | Description |
|---|------|------|-------------|-------------|
| 1 | Chat with PDF | `chat-pdf` | file (pdf) | Upload PDF → chat with page-level citations |
| 2 | Chat with Document | `chat-document` | file (docx/txt/md) | Same flow for Word/text/Markdown |
| 3 | Chat with Spreadsheet | `chat-spreadsheet` | file (csv/xlsx) | Q&A over tabular data |
| 4 | Chat with Website | `chat-website` | url | Enter URL → scrape → chat |
| 5 | Chat with YouTube | `chat-youtube` | youtube | Transcript fetch → chat / summarize / chapters |
| 6 | Long Document Summarizer | `long-doc-summarizer` | file | Map-reduce summary of docs too large for context |
| 7 | Multi-Document Compare | `doc-compare` | files (2–3) | Side-by-side AI comparison with citations |
| 8 | Write from Knowledge Base | `kb-writer` | existing collection | Generate content grounded ONLY in user's KB collection |

**Core architectural rules (non-negotiable):**

1. **Laravel AI SDK (`laravel/ai`) only** — embeddings + retrieval + completion all via `AiService`
   and the SDK's RAG primitives. Never raw OpenAI SDK, never LLPhant.
2. **Reuse the KB pipeline.** A RAG tool session = a hidden `knowledge_bases` row with
   `is_ephemeral = true`. Ingestion dispatches the **existing** `IngestDocument` / `IngestUrl`
   jobs on the `embeddings` queue. Deletion uses the **existing** `DeleteDocumentEmbeddings` job.
3. **New tool type `rag`** in `ai_tools.type` enum — alongside existing types. RAG tools render a
   different execution UI (source input → ingestion progress → chat) instead of the one-shot
   form → output flow.
4. **Streaming:** POST + `ReadableStream` (never `EventSource`), `X-Accel-Buffering: no` header,
   `finally` block in every streaming controller method.
5. **Citations are first-class:** standardized `sources` SSE event (see 67.7) rendered identically
   across all 8 tools.
6. `settings('app_name')` everywhere — never hardcode "MakeAI".
7. `users.ulid` / session ULIDs in all public URLs.

---

## 67.2 Database

```sql
-- Extend existing ai_tools.type enum:
ALTER TABLE ai_tools MODIFY type ENUM('text','image','audio','video','code','rag') NOT NULL;

-- Extend existing knowledge_bases table:
ALTER TABLE knowledge_bases
  ADD COLUMN is_ephemeral  BOOLEAN DEFAULT FALSE AFTER name,
  ADD COLUMN source_tool   VARCHAR(100) NULL AFTER is_ephemeral,   -- 'chat-pdf', 'chat-website', etc.
  ADD COLUMN expires_at    TIMESTAMP NULL AFTER source_tool;       -- ephemeral cleanup target
-- Ephemeral collections are HIDDEN from the user's Knowledge Base page
-- (WHERE is_ephemeral = false in all KB list queries).

-- New table: RAG tool sessions
CREATE TABLE rag_sessions (
  id                 CHAR(26) PRIMARY KEY,            -- ULID (public-facing)
  user_id            BIGINT UNSIGNED NOT NULL,        -- FK → users.id
  tool_slug          VARCHAR(100) NOT NULL,           -- FK → ai_tools.slug
  knowledge_base_id  BIGINT UNSIGNED NOT NULL,        -- FK → knowledge_bases.id (ephemeral or user-picked)
  title              VARCHAR(255) NULL,               -- auto: filename / page title / video title
  source_meta        JSON NULL,                       -- { filename, pages, url, video_id, duration, file_size }
  status             ENUM('ingesting','ready','failed') DEFAULT 'ingesting',
  ingest_error       VARCHAR(500) NULL,
  saved_to_kb        BOOLEAN DEFAULT FALSE,           -- user clicked "Save to Knowledge Base"
  created_at         TIMESTAMP,
  updated_at         TIMESTAMP,
  INDEX (user_id, tool_slug),
  INDEX (status, created_at)
);

-- New table: RAG session messages (chat history per session)
CREATE TABLE rag_messages (
  id           CHAR(26) PRIMARY KEY,                  -- ULID
  session_id   CHAR(26) NOT NULL,                     -- FK → rag_sessions.id
  role         ENUM('user','assistant') NOT NULL,
  content      LONGTEXT NOT NULL,
  sources      JSON NULL,                             -- [{ doc, page?, chunk_index, score }]
  input_tokens  INT NULL,
  output_tokens INT NULL,
  credits_used  DECIMAL(10,4) NULL,
  created_at   TIMESTAMP,
  INDEX (session_id, created_at)
);
```

**Migration notes:**
- `rag_sessions.id` and `rag_messages.id` are ULIDs (consistent with platform rule — never expose
  auto-increment integers).
- `kb-writer` sessions point `knowledge_base_id` at a **user-selected permanent** collection —
  no ephemeral KB is created for that tool.

---

## 67.3 Seeder — RAG Tools

Add to the AI tools seeder. New category **📚 Document AI** (dynamic — created via the existing
category system, never hardcoded in code):

```php
// Each tool row in ai_tools:
[
  'name'        => 'Chat with PDF',
  'slug'        => 'chat-pdf',
  'type'        => 'rag',
  'description' => 'Upload any PDF and chat with it. Get instant answers with page citations.',
  'icon'        => 'ti-file-type-pdf',
  'fields'      => json_encode([
      'source_type'   => 'file',
      'accept'        => ['pdf'],
      'max_file_mb'   => null,      // null = use settings('rag_max_file_mb')
      'multi_file'    => false,
  ]),
  'is_active'   => true,
],
// ... repeat for all 8 tools with appropriate fields JSON:
// chat-document:     source_type=file, accept=[docx,txt,md]
// chat-spreadsheet:  source_type=file, accept=[csv,xlsx]
// chat-website:      source_type=url
// chat-youtube:      source_type=youtube
// long-doc-summarizer: source_type=file, accept=[pdf,docx,txt,md], mode=summarize
// doc-compare:       source_type=file, accept=[pdf,docx,txt,md], multi_file=true, min_files=2, max_files=3
// kb-writer:         source_type=collection (picker of user's permanent KB collections), mode=generate
```

---

## 67.4 Service — `RagToolService`

**`app/Services/RagToolService.php`** — the ONLY class that orchestrates RAG tool flows.
Uses `AiService` (Laravel AI SDK) for embeddings, retrieval, and completion. Controllers never
touch the SDK directly.

```php
class RagToolService
{
    public function __construct(
        private AiService $ai,
        private CreditService $credits,
    ) {}

    // ── Session lifecycle ─────────────────────────────────────────────

    /**
     * Create a session + ephemeral KB, dispatch ingestion.
     * For source_type=file: store upload → dispatch IngestDocument (queue: embeddings)
     * For source_type=url: dispatch IngestUrl (queue: embeddings)
     * For source_type=youtube: dispatch IngestYoutubeTranscript (NEW job, queue: embeddings)
     * For source_type=collection (kb-writer): no ingestion — bind to existing KB, status='ready'
     */
    public function createSession(User $user, AiTool $tool, array $input): RagSession;

    /** Poll-able status for the ingestion progress UI (also broadcast via Reverb — see 67.9). */
    public function sessionStatus(RagSession $session): array;
        // ['status' => ..., 'progress' => 0-100, 'error' => ?, 'source_meta' => [...]]

    /** Promote ephemeral KB → permanent (visible in Knowledge Base page). */
    public function saveToKnowledgeBase(RagSession $session, string $name): KnowledgeBase;
        // Sets knowledge_bases.is_ephemeral = false, expires_at = null, rag_sessions.saved_to_kb = true

    /** Delete session: dispatch DeleteDocumentEmbeddings for ephemeral KBs, delete rows + files. */
    public function deleteSession(RagSession $session): void;

    // ── Query (streaming) ─────────────────────────────────────────────

    /**
     * RAG chat turn:
     *  1. Embed query (AiService → SDK embeddings, model from settings('rag_embedding_model'))
     *  2. Retrieve top-K chunks from session's KB (K = settings('rag_top_k', 6))
     *  3. Build grounded prompt: system instruction + retrieved chunks + chat history (last N turns)
     *  4. Stream completion via AiService (CompletionRequest DTO)
     *  5. Yield 'sources' event FIRST, then token events, then usage/done
     *  6. Persist both messages to rag_messages with sources + token usage
     *  7. Deduct credits via CreditService (completion tokens only — retrieval is free)
     */
    public function streamQuery(RagSession $session, string $message): \Generator;

    /** Long-doc summarizer: map-reduce. Chunk summaries (queued batches) → final merge summary. */
    public function summarizeLong(RagSession $session, array $options): \Generator;

    /** Doc-compare: retrieve per-document, prompt enforces per-doc citation tags [A]/[B]/[C]. */
    public function streamCompare(RagSession $session, string $aspect): \Generator;

    /** KB-writer: retrieval-grounded long-form generation. System prompt HARD-constrains the model
     *  to use ONLY retrieved context; instructs "say 'not found in your knowledge base' otherwise". */
    public function streamKbWrite(RagSession $session, array $brief): \Generator;
}
```

**Grounding system prompt rule (all tools):** retrieved chunks injected as numbered context blocks
`[1] … [2] …`; model instructed to reference chunk numbers; service maps chunk numbers back to
`{doc, page, score}` for the `sources` event. The grounding system prompt is stored in
`settings('rag_system_prompt')` (admin-editable) — **never exposed to frontend** (P-rule: no
`system_prompt` in Inertia shared data).

---

## 67.5 New Job — `IngestYoutubeTranscript` (queue: `embeddings`)

```php
// app/Jobs/IngestYoutubeTranscript.php
// 1. Extract video ID from URL (supports youtube.com/watch, youtu.be, shorts)
// 2. Fetch transcript: youtube captions endpoint (timedtext) — no API key needed for public CC;
//    fallback: settings-configurable Whisper transcription of audio (admin toggle
//    settings('rag_youtube_whisper_fallback'), default OFF — costs credits)
// 3. Chunk transcript WITH timestamps preserved in chunk metadata: { start: 123.4, end: 156.2 }
// 4. Generate embeddings via Laravel AI SDK → store in vector DB (same pipeline as IngestDocument)
// 5. Update rag_sessions: status='ready', source_meta={ video_id, title, duration, channel }
// failed(): rag_sessions.status='failed', ingest_error='No captions available for this video.'
```

YouTube citations render as **clickable timestamps** (`12:34`) that link to
`https://youtube.com/watch?v={id}&t={seconds}`.

---

## 67.6 Controllers & Routes

**`app/Http/Controllers/RagToolController.php`**

```php
// GET /tools/rag/{slug} — render RAG tool page (Inertia: Tools/RagTool.vue)
public function show(string $slug): Response
    // Tool must exist, type='rag', is_active. Pass: tool config (fields JSON),
    // user's recent sessions for THIS tool (last 10), settings: rag_max_file_mb, allowed types.

// POST /tools/rag/{slug}/sessions — create session (multipart for files)
public function store(Request $request, string $slug): JsonResponse
    // Validate per fields JSON (mimes, max size from settings('rag_max_file_mb', 25)).
    // Rate limited (sliding window — P rate-limit system, key: rag-ingest).
    // Returns: { session: { id, status: 'ingesting' } }

// GET /tools/rag/sessions/{ulid}/status — ingestion progress (polling fallback per P66)
public function status(string $ulid): JsonResponse

// POST /tools/rag/sessions/{ulid}/chat — streaming RAG chat
public function chat(Request $request, string $ulid): StreamedResponse
    // Guard: session belongs to auth user, status='ready'.
    // Pre-check credits (CreditService::canAfford estimate) → 402-style JSON if insufficient.
    // StreamedResponse with headers:
    //   Content-Type: text/event-stream
    //   X-Accel-Buffering: no
    //   Cache-Control: no-cache
    // try { foreach ($ragToolService->streamQuery(...) as $event) { echo ...; flush(); } }
    // finally { /* persist partial output, close stream, release lock */ }   ← MANDATORY

// POST /tools/rag/sessions/{ulid}/save-to-kb   { name }
// DELETE /tools/rag/sessions/{ulid}
// GET /tools/rag/sessions/{ulid}               — session + messages (reopen past session)
```

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->prefix('tools/rag')->name('rag.')->group(function () {
    Route::get('/{slug}',                    [RagToolController::class, 'show'])->name('show');
    Route::post('/{slug}/sessions',          [RagToolController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{ulid}',           [RagToolController::class, 'session'])->name('sessions.show');
    Route::get('/sessions/{ulid}/status',    [RagToolController::class, 'status'])->name('sessions.status');
    Route::post('/sessions/{ulid}/chat',     [RagToolController::class, 'chat'])->name('sessions.chat');
    Route::post('/sessions/{ulid}/save-to-kb', [RagToolController::class, 'saveToKb'])->name('sessions.save');
    Route::delete('/sessions/{ulid}',        [RagToolController::class, 'destroy'])->name('sessions.destroy');
});
```

**Mobile API (extend P44.7):**

```
POST   /api/v1/rag-tools/{slug}/sessions          -- create session (multipart)
GET    /api/v1/rag-tools/sessions/{ulid}/status
POST   /api/v1/rag-tools/sessions/{ulid}/chat     -- SSE stream
POST   /api/v1/rag-tools/sessions/{ulid}/save-to-kb
DELETE /api/v1/rag-tools/sessions/{ulid}
GET    /api/v1/rag-tools/sessions                 -- list user's sessions { tool_slug? }
```

---

## 67.7 SSE Event Contract (standard across ALL RAG tools)

```
data: {"type":"sources","items":[{"doc":"report.pdf","page":12,"chunk":4,"score":0.89},{"doc":"report.pdf","page":31,"chunk":11,"score":0.84}]}
data: {"type":"token","content":"According"}
data: {"type":"token","content":" to"}
...
data: {"type":"usage","input_tokens":1450,"output_tokens":230,"credits":1.2,"model":"gpt-4o"}
data: {"type":"done"}

-- on error mid-stream:
data: {"type":"error","message":"Generation failed. You were not charged."}
```

- `sources` is ALWAYS emitted before the first token (retrieval completes before generation).
- For `chat-youtube`, source items carry `{"start":754}` instead of `page`.
- For `doc-compare`, source items carry `{"doc_label":"A"}`.
- Frontend renders source chips under each assistant message; identical component everywhere.

---

## 67.8 Vue Pages & Components

```
resources/js/Pages/Tools/
  RagTool.vue                 → GET /tools/rag/{slug} — orchestrates the 3 states below

resources/js/Components/Rag/
  RagSourceInput.vue          → state 1: dropzone (file) / URL input / YouTube input / KB picker
                                 - drag-drop + click upload, file type + size validation client-side
                                 - shows settings-driven limits: "PDF up to 25 MB"
  RagIngestProgress.vue       → state 2: progress card
                                 - listens on Reverb private channel rag.session.{ulid}
                                 - P66 fallback: polls /status every 2.5s when broadcasting = polling driver
                                 - stages: Uploading → Extracting text → Embedding (n/m chunks) → Ready
                                 - failed state: error message + [Try again]
  RagChat.vue                 → state 3: chat interface
                                 - POST + ReadableStream consumption (NEVER EventSource)
                                 - message list with streaming tokens, stop button (AbortController)
                                 - suggested starter questions (3 chips, generated from doc title/type)
  RagSourceChips.vue          → citation chips under assistant messages
                                 - PDF: "report.pdf · p.12" → opens PdfPreviewDrawer at that page
                                 - YouTube: "12:34" → external link with &t=
                                 - hover: chunk text preview tooltip (first 200 chars)
  RagSessionList.vue          → recent sessions sidebar (last 10 for this tool, reopen/delete)
  SaveToKbModal.vue           → name input → POST save-to-kb → success toast + link to KB page
```

**Layout (Chat with PDF — reference for all):**

```
┌──────────────────────────────────────────────────────────────────┐
│  ← Tools     📄 Chat with PDF                    [Recent ▾]      │
├──────────────────────┬───────────────────────────────────────────┤
│                      │  annual-report-2025.pdf · 48 pages        │
│   PDF PREVIEW        │  ───────────────────────────────────────  │
│   (pdf.js viewer,    │  💬 What was the total revenue in Q3?     │
│    desktop only —    │                                           │
│    hidden < lg)      │  🤖 According to the report, Q3 revenue   │
│                      │     was $4.2M, up 18% YoY...              │
│   citation click →   │     [📄 p.12] [📄 p.31]                   │
│   scrolls to page    │  ───────────────────────────────────────  │
│                      │  [Ask anything about this document…] [→]  │
│                      │  [💾 Save to Knowledge Base]  [🗑 Delete]  │
└──────────────────────┴───────────────────────────────────────────┘
```

- Mobile: preview pane hidden; citation chips open a bottom-sheet text preview instead.
- `kb-writer` replaces chat UI with a brief form (topic, content type, tone, length) + streaming
  long-form output with the standard sources panel + [Save to Documents] (existing documents flow).

---

## 67.9 Broadcasting (per P66)

```php
// Event: RagIngestProgressEvent — broadcast on PrivateChannel("rag.session.{ulid}")
// Payload: { status, progress, stage, error? }
// Driver resolved by BroadcastingService (P66): Reverb → polling fallback → Pusher override.
// RagIngestProgress.vue uses the useNotifications/useEcho composable pattern from P66;
// when frontendConfig().driver === 'polling', component polls the /status endpoint (2.5s interval).
```

---

## 67.10 Credits Model

| Action | Cost |
|--------|------|
| Ingestion (file/url/youtube) | `ceil(chunks / settings('rag_chunks_per_credit', 50))` credits, charged ONCE on successful ingest. Failed ingest = 0 charge. |
| Retrieval (vector search) | Free |
| Chat completion | Standard token-based credit calc (existing CreditService logic) |
| Whisper fallback (YouTube) | Charged per audio minute (existing STT pricing) — only if admin enabled |

- Pre-flight check before ingestion: estimate chunks from file size, block with friendly error if
  insufficient credits ("This document needs ~4 credits to process. You have 2.").
- All charges logged to `ai_usage_logs` with `tool_slug` so RAG tools appear in the Usage
  Dashboard (P56) and admin analytics like every other tool.

---

## 67.11 Cleanup — Scheduled Task

```php
// routes/console.php — add scheduled task #14:
Schedule::command('rag:cleanup-ephemeral')->daily();

// app/Console/Commands/CleanupEphemeralRag.php
// 1. Find knowledge_bases WHERE is_ephemeral = true AND expires_at < now()
//    (expires_at = created_at + settings('rag_ephemeral_retention_days', 7))
// 2. For each: dispatch DeleteDocumentEmbeddings (existing job), delete uploaded source files,
//    delete rag_sessions + rag_messages rows (cascade), delete knowledge_bases row.
// 3. Sessions with saved_to_kb = true: KB is permanent — only the source file in temp storage
//    is removed; session chat history retained.
```

---

## 67.12 Admin Settings (Admin → Settings → AI → RAG Tools)

| Setting key | Default | Description |
|-------------|---------|-------------|
| `rag_max_file_mb` | 25 | Max upload size per file |
| `rag_max_pages` | 300 | Max PDF pages (reject larger with clear error) |
| `rag_top_k` | 6 | Retrieved chunks per query |
| `rag_chunk_size` | 800 | Tokens per chunk |
| `rag_chunk_overlap` | 100 | Token overlap |
| `rag_embedding_model` | text-embedding-3-small | SDK embedding model (provider dropdown) |
| `rag_system_prompt` | (grounding prompt) | Editable, never sent to frontend |
| `rag_ephemeral_retention_days` | 7 | Auto-delete window for unsaved sessions |
| `rag_chunks_per_credit` | 50 | Ingestion pricing knob |
| `rag_youtube_whisper_fallback` | off | Whisper transcription when no captions |

Plus per-tool enable/disable via the standard `ai_tools.is_active` toggle (already exists).
Each RAG tool can be plan-gated via the existing plan→tools mapping; subscription gating respects
`isProAvailable()` — **hidden, not disabled**, when false.

---

## 67.13 Tests (PestPHP)

- Session create validates mimes + size from settings (not hardcoded)
- Ingestion job failure sets `status='failed'` + `ingest_error`, charges 0 credits
- Chat endpoint rejects sessions not owned by auth user (404 — never 403 to avoid ULID probing)
- Chat endpoint rejects `status != 'ready'` with 422
- `sources` SSE event emitted before first `token` event
- Credits: ingestion charged once; retry after failure does not double-charge
- `saveToKnowledgeBase` flips `is_ephemeral`, clears `expires_at`, KB appears in user's KB list
- Cleanup command deletes expired ephemeral KBs + dispatches `DeleteDocumentEmbeddings`
- `kb-writer` only lists the user's own permanent collections in picker

---

## 67.14 Implementation Checklist

- [ ] `ai_tools.type` enum extended with `rag`; existing tool queries unaffected
- [ ] `knowledge_bases` gains `is_ephemeral`, `source_tool`, `expires_at`; KB page queries filter `is_ephemeral = false`
- [ ] `rag_sessions` + `rag_messages` migrations with ULID primary keys
- [ ] 8 tools seeded under dynamic "Document AI" category (category created via category system, NOT hardcoded)
- [ ] `RagToolService` is the only orchestrator; all SDK calls go through `AiService` + `CompletionRequest` DTO
- [ ] Ingestion reuses existing `IngestDocument` / `IngestUrl` jobs on `embeddings` queue — no duplicate pipeline
- [ ] New `IngestYoutubeTranscript` job on `embeddings` queue with timestamp-preserving chunks
- [ ] Streaming: POST + `ReadableStream`, `X-Accel-Buffering: no`, `finally` block in chat controller
- [ ] `sources` SSE event standardized and emitted before first token in all 8 tools
- [ ] Citation chips component reused everywhere (page / timestamp / doc-label variants)
- [ ] Ingest progress: Reverb private channel + P66 polling fallback (2.5s) — works without Redis
- [ ] Credits: ingestion charged once on success, retrieval free, completion standard; pre-flight estimate blocks insufficient balance
- [ ] All usage logged to `ai_usage_logs` with `tool_slug` (appears in P56 dashboard + admin analytics)
- [ ] "Save to Knowledge Base" promotes ephemeral KB; unsaved sessions auto-deleted after retention window
- [ ] `rag:cleanup-ephemeral` registered in scheduler (scheduled task count: 13 → 14)
- [ ] All 10 admin settings live in `settings` table, editable without deployment
- [ ] `settings('app_name')` in all UI strings — zero "MakeAI" hardcoding
- [ ] Plan gating uses existing plan→tools mapping; `isProAvailable()` hides (not disables) gated tools
- [ ] `system_prompt` (`rag_system_prompt`) never reaches frontend via Inertia shared data
- [ ] Rate limiting: `rag-ingest` + `rag-chat` sliding-window limits in settings table
- [ ] Mobile API endpoints added (P44.7 extension) with standard response envelope + rate-limit headers
- [ ] PestPHP tests from 67.13 all passing

---

---

## 🔷 FINAL STATS

## PART 68 — FINAL COMPLETE STATS

*End of MakeAI Complete Development Master Prompt*

| Metric | Count |
|--------|-------|
| Version | 4.2 |
| Total Parts | 64 |
| AI Templates | 255 |
| Mail Templates | 23 |
| API Endpoints | 80+ |
| Queue Jobs | 48+ |
| Scheduled Tasks | 13 |
| Integrations | 60+ |
| Test Cases | 50+ |
| Admin Menu Items | 60+ |
| Checklist Items | 300+ |
| Total Lines | ~11,500 |

---
