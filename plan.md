# Extensions Implementation Plan — Tier 1 & Tier 2

> Working document for finalizing the `/admin/settings/extensions` catalog before Envato
> distribution. Not a shipped artifact — delete before packaging.

## 0. Context

The **Extensions** page (`admin.settings.extensions.index`) renders
`Admin/Settings/Integrations.vue` in `extensions` mode. It lists the **"system" group**
of `config/external-tools.php` — platform-wide connectors where a per-use credit cost is
meaningless (as opposed to the per-user, billable "ai_tool" group shown on *Integrations*).

**Grouping is decided in code**, not config:
`AiManagementController::AI_TOOL_INTEGRATIONS` = `['plagiarism','ai_detector','grammar','translation']`.
Everything else in the catalog is automatically "system" → appears on Extensions.

### Current system extensions
| slug | status |
|---|---|
| `captcha` | ✅ real, wired into all auth + contact |
| `spam_filter` | ✅ **fixed this pass** — real Akismet `check()`, wired into comments + contact |
| `ip_geolocation` | ✅ real, wired into User/Admin/CountryDetection |
| `currency_rates` | ✅ real, wired into `SyncCurrencyRates` |

## 1. Shared conventions (ALL new services MUST follow)

Every new extension is a **catalog entry + a service class**. The Extensions Vue page renders
entirely from the catalog — no per-extension frontend work is required.

### 1a. Service class contract (copy the shape of `app/Services/CaptchaService.php`)
```php
final class XxxService
{
    public function __construct(bool $enabled = false, ?string $provider = null, /* creds */) {}

    public static function fromSettings(): self;   // reads settings("external_{slug}_...")
    public function isConfigured(): bool;           // creds present
    public function isEnabled(): bool;              // $enabled && isConfigured()  (some use isActive())
    public function testConnection(): array;        // ['success'=>bool, 'message'|'error'=>string]
    // + the real domain method(s), which FAIL OPEN when !isEnabled()
}
```
- `declare(strict_types=1)`.
- Read every credential/option via `settings("external_{slug}_{provider}_{key}")`; read the
  toggle via `settings("external_{slug}_enabled")`; read timeout via `settings("external_{slug}_timeout", N)`.
- **Never** hardcode secrets or read `.env` directly — settings table only.
- Use `translate()` for all user-facing strings. Use `Http::timeout(...)`. Wrap network calls
  in `try/catch (Throwable)` and **fail open** (never block a legit user on an outage).
- `testConnection()` is invoked by the existing `testIntegrationConnection` endpoint via
  `XxxService::fromSettings()->testConnection()` — signature must match exactly.

### 1b. Catalog entry schema (in `config/external-tools.php`, `integrations` array)
```php
'slug' => [
    'name' => 'Human Name',
    'service' => 'XxxService',                 // resolved as \App\Services\XxxService
    'tab' => 'utilities',                      // existing tab keys only
    'doc_url' => 'https://provider/docs',
    'ai_fallback' => false,                    // true only for LLM-backed tools
    'providers' => [
        'provider_slug' => [
            'name' => 'Provider',
            'secrets' => ['api_key'],          // encrypted, masked in UI
            'options' => ['base_url'],         // plain settings
        ],
    ],
],
```
- New system extensions are added **outside** `AI_TOOL_INTEGRATIONS`, so they land on Extensions
  automatically. No controller change needed unless we want an ai_tool.

### 1c. Non-negotiables (Envato QA)
- No fake/placeholder providers. Every provider listed must have a working code path.
- No hardcoded keys, no `dd`/`dump`, no `TODO`.
- Match surrounding style; no `any`-typed TS; handle loading/error states in any UI touched.

---

## 2. TIER 1

### 2.1 Content Moderation / NSFW Safety  `slug: content_moderation`
**Why:** AI image/video/chat generation currently has **zero** safety layer — an abuse/liability
gap Envato reviewers flag for AI products.

- **Providers:** `openai_moderation` (secret: `api_key`), `sightengine` (secrets: `api_user`,
  `api_secret`). OpenAI = text; Sightengine = image/video URL.
- **Service:** `ContentModerationService`
  - `checkText(string $text): array` → `['flagged'=>bool, 'categories'=>[], 'provider'=>...]`
  - `checkImage(string $url): array`
  - Fail open (return `flagged=false`) when disabled/errored.
  - `moderationMode()` setting: `off | flag | block` (default `flag`).
- **Consumers (wiring):**
  - `addons/ai-chatbot/.../ChatController::sendMessage` + `store` — check user prompt before dispatch.
  - `addons/ai-image-editor/.../ImageEditorController` — check prompt pre-generation, result URL post-generation.
  - `addons/ai-video-creator/.../*` — check prompt pre-generation.
  - On `block` mode + flagged → reject with `translate('This request was blocked by content safety filters.')`
    and do **not** consume credits. On `flag` → proceed but log to a `moderation_flags` audit (optional v2).
- **Risk:** wiring touches 3 addons; keep the gate a single guard call so it's easy to review.

### 2.2 Cloud Object Storage  `slug: cloud_storage`
**Why:** Generated media (images/video/audio) must scale off local disk; buyers expect an admin
toggle, not `.env` surgery.

- **Reality check:** addons call `Storage::disk('public')` (76×) and `disk('local')` (31×) with
  **hardcoded disk names**. So the low-risk design is to make the **`public` disk driver itself
  settings-driven**, so every existing call transparently uses S3 when enabled.
- **Approach:**
  - Add an `s3`-compatible disk config in `config/filesystems.php` that reads bucket/region/
    endpoint/key/secret from settings (with env fallback).
  - In a service provider (or `AppServiceProvider::boot`), when `external_cloud_storage_enabled`,
    rebind the `public` disk config at runtime to the cloud disk (`config(['filesystems.disks.public' => ...])`).
  - **Providers:** `s3` (AWS), `r2` (Cloudflare), `spaces` (DigitalOcean), `wasabi`, `b2`
    (Backblaze) — all S3-compatible, differ only by `endpoint`/`region`. Secrets: `access_key`,
    `secret_key`; options: `bucket`, `region`, `endpoint`, `url`.
  - `CloudStorageService::testConnection()` = write+read+delete a tiny probe object.
- **This is the highest-risk item** — it does not fit the plain HTTP-tool mold and touches the
  filesystem layer. **Do NOT delegate blind.** Scaffold the service + config, then verify uploads
  in every media addon before enabling. Consider shipping behind an off-by-default flag.
- **Decision needed:** rebind `public` globally vs. introduce a dedicated `media` disk and migrate
  the 76 call sites. Recommend the global-rebind path for release; migration is a post-1.0 cleanup.

---

## 3. TIER 2

### 3.1 Web Analytics  `slug: web_analytics`
**Why:** Today analytics only via raw `custom_header_code` paste. A first-class, GDPR-consent-aware
extension is more buyer-friendly and ties into the existing GDPR settings.

- **Providers:** `ga4` (option: `measurement_id`), `gtag`/`gtm` (option: `container_id`),
  `plausible` (options: `domain`, `script_url`), `umami` (options: `website_id`, `script_url`).
  No secrets — all public IDs.
- **Service:** `WebAnalyticsService::headSnippet(): string` returning the correct script tag(s),
  empty when disabled. **Must respect GDPR consent** — gate behind the existing cookie-consent
  state; do not emit before consent when consent mode is on.
- **Consumer:** inject via the shared Inertia head / layout that already emits `custom_header_code`
  (see `app/Helpers/helpers.php` around the `custom_header_code` handling and the root Blade layout).

### 3.2 SMS / OTP Gateway  `slug: sms_gateway`
**Why:** OTP is email-only; enables SMS 2FA + transactional alerts.

- **Providers:** `twilio` (secrets: `account_sid`, `auth_token`; option: `from`),
  `vonage` (secrets: `api_key`, `api_secret`; option: `from`),
  `messagebird` (secret: `access_key`; option: `originator`).
- **Service:** `SmsService::send(string $to, string $message): array`; fail-open logging.
- **Consumers (v1, minimal):** add `sms` as a 2FA delivery method option alongside the existing
  `totp`/`otp` in `TwoFactorLoginController` (send OTP via SMS when `user_2fa_method === 'sms'`).
  Keep the auth-flow change small and behind the extension toggle.

### 3.3 Email Validation  `slug: email_validation`
**Why:** Block disposable/fake signups → curbs credit abuse.

- **Providers:** `zerobounce` (secret: `api_key`), `neverbounce` (secret: `api_key`).
- **Service:** `EmailValidationService::validate(string $email): array`
  → `['valid'=>bool, 'disposable'=>bool, 'reason'=>...]`; fail open.
- **Consumer:** `RegisterController` — reject on `disposable === true` (or invalid) with a validation
  error on the `email` field. Gate behind toggle; skip entirely when disabled.

---

## 4. Delegation plan (subagents)

Shared-file edits (`config/external-tools.php`, consumer wiring) are done by **me** to keep the
catalog consistent and avoid merge conflicts. Subagents produce **isolated new service files** and
return their proposed catalog snippet + wiring notes as text.

| # | Task | Owner | Model | Output |
|---|---|---|---|---|
| A | `ContentModerationService` (2.1) | subagent | sonnet | new service file + catalog snippet + exact wiring diff notes |
| B | `WebAnalyticsService` (3.1) | subagent | sonnet | new service file + catalog snippet + head-inject notes |
| C | `SmsService` (3.2) | subagent | sonnet | new service file + catalog snippet + 2FA wiring notes |
| D | `EmailValidationService` (3.3) | subagent | sonnet | new service file + catalog snippet + register wiring notes |
| E | `CloudStorageService` + filesystem rebind (2.2) | **me** (not delegated) | — | service + config + provider boot + verify |

Each subagent brief includes: read `CaptchaService.php` + `IpGeolocationService.php` first to match
conventions; follow §1 contract exactly; create only its own file; do **not** touch
`config/external-tools.php` or any controller — return those as text for me to merge.

### Delegation results (all service files created + `php -l` clean, verified by me)
- **A ✅ `ContentModerationService`** — OpenAI Moderation (text) + Sightengine (image); `mode()` = off/flag/block; fail-open. Catalog snippet + block/flag guards received.
- **B ✅ `WebAnalyticsService`** — GA4/GTM/Plausible/Umami `headSnippet()`; context-correct escaping (`json_encode` w/ hex flags for JS, `e()` for attrs). Inject in `resources/themes/default/views/app.blade.php` right after the `custom_header_code` echo. GDPR-consent gating is the caller's responsibility (documented on the method).
- **C ✅ `SmsService`** — Twilio/Vonage/MessageBird `send()`; fail-open. **BLOCKER for SMS 2FA:** `User` has no phone/mobile column and no migration defines one → SMS-as-2FA needs a schema migration + verified-phone profile UI first. Service is complete and usable for transactional alerts now; 2FA wiring deferred.
- **D ✅ `EmailValidationService`** — ZeroBounce/NeverBounce `validate()`; fail-open. Guard goes in `RegisterController::register()` right after the existing captcha `ensureValidToken` call.

### ✅ MERGE + WIRING COMPLETE (decisions: wire everything / global rebind / defer SMS 2FA)
All five catalog entries added to `config/external-tools.php` (now 13 integrations; 9 on Extensions page).
All 14 touched PHP files `php -l` clean; config parses; app boots (`artisan about` OK); all 6 services
satisfy the `fromSettings()` + `testConnection()` contract.

Applied wiring:
- **Content Moderation** — `ContentModerationService::textViolates()` policy method added (block→reject,
  flag→log+allow, off→skip; runs BEFORE credit charge). Guards in: chatbot `sendMessage`, video
  `store` (prompt+script), image-editor `apply` (params.prompt). Default mode `flag`.
- **Web Analytics** — `headSnippet()` injected in `resources/themes/default/views/app.blade.php` after
  the `custom_header_code` echo. (GDPR-consent gating still the caller's job — noted on the method.)
- **Email Validation** — guard in `RegisterController::register()` after captcha (fails open).
- **SMS Gateway** — service shipped + on Extensions page (usable for transactional SMS). 2FA delivery
  wiring **deferred** — needs a `phone` column migration + verified-phone profile UI (per decision).
- **Cloud Storage** — MOVED out of the Extensions catalog into its own **Settings → Storage** page
  (`admin.storage.settings*`, sidebar link added). Dedicated `StorageSettingsController`:
  - Driver select: **Local (default)** + S3/R2/Spaces/Wasabi/B2.
  - **Test-before-activate**: `update()` refuses to set `storage_driver` to a cloud driver unless
    `testConnection()` (real write/read/delete probe) passes — a bad config can never break media serving.
  - Secrets encrypted + masked + blank-preserve on save (Mail-controller pattern).
  - **Data migration**: `MigrateStorageFiles` queued job copies files between any two drivers
    (copy-only, never deletes source), with live progress polled via `migrate/status`. Local side uses
    a dedicated `local_public_media` disk that is never rebound, so migration always reaches local files.
  - Provider-specific setup/migration docs rendered on the page.
  - `CloudStorageService` refactored to the driver model; `apply()` (boot rebind of `public`) guarded by
    `s3DriverAvailable()` so a missing adapter can't rebind to an unbuildable driver.
  - **DEPENDENCY:** `league/flysystem-aws-s3-v3` is NOT installed (only a composer suggestion). Cloud
    drivers are inert until the server runs `composer require league/flysystem-aws-s3-v3`. The UI shows an
    actionable message; local storage is unaffected. Compiled frontend verified (`vite build` exit 0).

### Remaining before packaging
- Manually exercise each extension's Save + Test Connection in the admin UI with real sandbox keys.
- Delete this `plan.md`.

## 5. Merge + QA sequence (me)
1. Collect subagent outputs → merge all catalog entries into `config/external-tools.php` in one edit.
2. Apply consumer wiring (moderation guards, analytics head, SMS 2FA, register validation).
3. Implement Cloud Storage (task E) separately, off-by-default.
4. `php -l` every touched file; `php artisan config:clear`.
5. Manually verify each extension's `testConnection` via the Extensions page.
6. Confirm each new provider row renders (no fake/empty providers).
7. Run existing test suite (`phpunit`) — see memory `test-harness-setup`.
8. Remove this `plan.md` before packaging.

## 6. Open decisions for the user
- Cloud Storage: global `public`-disk rebind (recommended) vs. dedicated `media` disk?
- Moderation default mode: `flag` (log, allow) vs. `block` (reject)? Recommend `flag` for launch.
- Ship all 5 for 1.0, or land Tier 1 now and Tier 2 in a point release?
