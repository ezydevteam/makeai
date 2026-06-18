# MakeAI — Performance, Security & SEO Audit + Implementation Prompt

**For: Qwen (AI coding agent)**
**Mode: AUDIT FIRST, THEN IMPLEMENT GAPS**

---

## 0. ROLE & MISSION

You are auditing **MakeAI**, a Laravel 13 + Vue 3 (Inertia SSR) AI SaaS platform built for sale on Envato CodeCanyon. Your job has two phases, executed strictly in order:

**Phase 1 — AUDIT.** Go through every checklist item below. For each item, inspect the actual codebase (migrations, models, middleware, controllers, Vue components, config files, `.env.example`, build config) and mark it:
- ✅ **PASS** — implemented correctly, cite the file/line as proof
- ❌ **MISSING** — not implemented at all
- ⚠️ **PARTIAL** — implemented but incomplete or wrong (explain what's wrong)

Produce a single audit report table (Performance / Security / SEO sections) before touching any code.

**Phase 2 — IMPLEMENT.** For every ❌ and ⚠️ item, implement the fix using the exact patterns and tech stack rules in Section 1. Do not invent new architecture — match existing project conventions exactly (see Section 1).

Do not skip Phase 1 to "save time." A fix without an audit trail is not acceptable — Shehab needs to know what was broken and what was changed.

---

## 1. NON-NEGOTIABLE PROJECT CONVENTIONS (read before fixing anything)

- PHP 8.3+, Laravel 13+, MySQL 8+, Redis, Laravel Horizon, Laravel Reverb (WebSockets)
- Vue 3 — Composition API / `<script setup>` only, TypeScript, Inertia.js with **SSR enabled**
- Tailwind CSS v4, Pinia, Tabler Icons
- **AI calls only via `laravel/ai` SDK through `AiService` + `CompletionRequest` DTO.** Never raw OpenAI SDK, never LLPhant (LLPhant is permanently banned — if you see it, that's a bug to fix, not a pattern to follow).
- Zero hardcoded app name — always `settings('app_name')`, never the literal string "MakeAI" in PHP or Vue.
- `users.ulid` is the only public-facing user identifier — auto-increment `id` must never leak into a URL, API payload, or frontend prop.
- Streaming responses: POST + `ReadableStream` only, never `EventSource`. Every streaming response MUST carry `X-Accel-Buffering: no`.
- Rate limiting is **sliding window** via Redis sorted sets, not Laravel's default fixed-window throttle.
- Settings come from `settings('key')` (Redis-cached) — never raw `DB::table('settings')` queries, especially not inside a loop.
- 9 named queues: `otp`, `ai`, `media`, `emails`, `webhooks`, `social`, `embeddings`, `default`, `low`.

If a performance/security/SEO fix would require breaking one of these rules, stop and flag it instead of silently deviating.

---

## 2. PART A — PERFORMANCE AUDIT

### A1. Backend / Database

| # | Check | How to verify |
|---|-------|---------------|
| A1.1 | All indexes from the database optimization spec exist on `users`, `ai_usage_logs`, `documents`, `blog_posts`, `ai_tools`, `support_tickets`, `tool_reviews`, `notifications`, `settings` | `php artisan db:show --table=ai_usage_logs` or inspect migration files directly |
| A1.2 | `ai_usage_logs` is partitioned by month (or has a documented archival strategy if partitioning isn't available on shared hosting) | Check migration for `PARTITION BY RANGE` |
| A1.3 | No `SELECT *` on large tables — every query specifies columns | `grep -rn "::all()" app/ \| grep -v "::select"`, `grep -rn "select(\['\*'\])" app/` |
| A1.4 | No N+1 queries on list/index pages (blog, tools, documents, admin user list) | Run with Laravel Debugbar/Telescope enabled locally, load each list page, check query count |
| A1.5 | All admin and user-facing list endpoints use `->paginate()`, never unbounded `->get()` | `grep -rn "->get()" app/Http/Controllers/Admin/` and check each result |
| A1.6 | `ai_usage_logs` queries always filter by `user_id` or a `created_at` range | Manual review of `AiUsageLog::` query call sites |
| A1.7 | Document/blog full-text search uses `MATCH() AGAINST()`, not `LIKE '%term%'` | `grep -rn "LIKE '%" app/` |
| A1.8 | Eager loading used everywhere relations are accessed in a loop (`with([...])`) | Review controllers that render lists with relations (category, author, reviews) |
| A1.9 | `Cache::remember` / Redis used for: categories, tool list, tool-by-slug, settings, translations, menus, exchange rates, license status — matching the documented cache key scheme (`makeai:categories:*`, `makeai:tool:*`, `makeai:settings:*`, etc.) | `grep -rn "Cache::remember\|Cache::forget" app/Services/` and confirm TTLs match spec |
| A1.10 | Cache invalidation exists for every cached key (save/delete hooks clear the right keys) | Trace each cache-write site to its corresponding `Cache::forget` |
| A1.11 | User credit balance reads come from Redis, never a direct DB hit on the hot path | `grep -rn "credits" app/Services/CreditService.php` (or equivalent) |
| A1.12 | Horizon queue config matches the 9-queue spec with correct process counts/timeouts/backoff per queue | `config/horizon.php` |
| A1.13 | Nothing slow (AI calls, email sending, media/video generation, embeddings, webhook delivery) runs synchronously in an HTTP request — verify each is dispatched as a Job | `grep -rn "::dispatch(" app/` cross-checked against controllers that should NOT block |
| A1.14 | Redis cache hit rate is observable (Horizon metrics or custom logging) for settings/translations | Confirm a way to measure this exists, even if just Horizon's built-in dashboard |

### A2. Frontend / Assets

| # | Check | How to verify |
|---|-------|---------------|
| A2.1 | Vite production build produces code-split chunks per route (Inertia pages lazy-loaded via dynamic `import()`) | Check `resources/js/app.ts` resolve function — should use `import.meta.glob` or per-page dynamic import, not one giant bundle |
| A2.2 | Images use modern formats (WebP/AVIF) with fallback, and `loading="lazy"` / `decoding="async"` below the fold | Inspect Vue components rendering tool icons, blog thumbnails, landing page images |
| A2.3 | Fonts (Plus Jakarta Sans, Inter, JetBrains Mono) are self-hosted, subsetted, and use `font-display: swap` | Check `resources/css` or wherever `@font-face` is declared |
| A2.4 | Critical CSS / above-the-fold rendering isn't blocked by unnecessary JS on the landing/tool pages | Manual check of `<head>` script placement, `defer`/`async` on non-critical scripts |
| A2.5 | No unused/duplicate npm packages bloating the bundle (e.g. confirm LLPhant or any leftover dead dependency is removed) | `npm run build -- --report` or `vite-bundle-visualizer`, plus `package.json` review |
| A2.6 | Tailwind v4 build purges unused classes correctly (no megabyte-scale CSS output) | Check built CSS file size after `npm run build` |
| A2.7 | Third-party scripts (analytics, chat widgets, etc.) are loaded `async`/`defer` and don't block first paint | Inspect root layout `app.blade.php` / SSR entry |
| A2.8 | API responses for list endpoints set appropriate `Cache-Control` headers where content is public and cacheable (e.g. public blog posts) | Check response headers on `/blog`, public tool pages |

### A3. Streaming / Real-time

| # | Check | How to verify |
|---|-------|---------------|
| A3.1 | Every SSE/streaming controller sets `X-Accel-Buffering: no` and uses a `finally` block to clean up resources/release locks even on client disconnect | `grep -rn "X-Accel-Buffering" app/Http/Controllers/` |
| A3.2 | Nginx config (or documented installation instructions for the buyer) sets `proxy_buffering off` and an appropriate `fastcgi_read_timeout` for streaming routes | Check deployment docs / sample nginx conf shipped with the product |
| A3.3 | Reverb (WebSocket) connections have a documented fallback chain: Reverb → Pusher-compatible → HTTP polling, so the product doesn't break on hosts that block WebSockets | Check the broadcast service / front-end Echo config |

### A4. Core Web Vitals Targets (verify against real Lighthouse run)

Run Lighthouse (mobile + desktop) against: landing page, a representative AI tool page, blog post page, dashboard.

| Metric | Target |
|--------|--------|
| LCP (Largest Contentful Paint) | < 2.5s |
| INP (Interaction to Next Paint) | < 200ms |
| CLS (Cumulative Layout Shift) | < 0.1 |
| Lighthouse Performance score | ≥ 90 (mobile), ≥ 95 (desktop) |
| Lighthouse Best Practices score | ≥ 95 |

If any page misses these, identify the specific blocking resource/render-blocking script/oversized image and fix it — don't just report the number.

---

## 3. PART B — SECURITY AUDIT

### B1. Authentication & Session

| # | Check | How to verify |
|---|-------|---------------|
| B1.1 | OTP codes are hashed with `Hash::make()` / `bcrypt` before storage, never plaintext | `grep -rn "otp_code" app/` |
| B1.2 | OTP: 5 wrong attempts → 10-minute user lockout; 50 attempts/IP/hour → IP flagged | Review `OtpService` / auth controller logic |
| B1.3 | Login: 5 failed attempts per email per 15 min → lockout; 20 failed/IP/hour → IP ban via `banned_ips` table, checked in global middleware before route resolution | Trace `LoginController` and the global middleware stack |
| B1.4 | Admin auth guard is completely isolated from user auth guard (separate tables, separate session/cookie namespace) | `config/auth.php`, `App\Models\Admin` |
| B1.5 | Session cookies set `Secure`, `HttpOnly`, and `SameSite=Lax` (or `Strict` where appropriate) in production | `config/session.php` + `.env.example` |
| B1.6 | Password hashing uses Laravel's default bcrypt/argon2, never MD5/SHA1, with no custom weakening | `config/hashing.php` |
| B1.7 | Optional 2FA (TOTP) for admins, if specced, is actually enforced where enabled — not just stored as a flag | Review admin login flow |
| B1.8 | `APP_DEBUG=false` is the documented production default in `.env.example`, and a check exists (or installation doc warns) that debug mode must never ship enabled — stack traces must never leak to end users | `.env.example`, error page config |

### B2. Web Application Security (OWASP basics)

| # | Check | How to verify |
|---|-------|---------------|
| B2.1 | CSRF protection active on all state-changing routes (Laravel's default `VerifyCsrfToken`, with explicit, justified exceptions only — e.g. signed webhook endpoints) | `app/Http/Middleware/VerifyCsrfToken.php` `$except` list |
| B2.2 | No raw SQL string interpolation anywhere — all queries use parameter binding or the query builder/Eloquent | `grep -rn "DB::select(\"" app/` and inspect for string-concatenated variables |
| B2.3 | Mass assignment protected — every model defines `$fillable` (preferred) or a deliberate `$guarded`, never `protected $guarded = []` on a model that accepts user input | `grep -rn '$guarded = \[\]' app/Models/` |
| B2.4 | User-generated content (blog comments, document content, AI output rendered in the UI) is escaped/sanitized before render — Vue's default escaping isn't bypassed with raw `v-html` on untrusted content without sanitization (e.g. DOMPurify) | `grep -rn "v-html" resources/js/` |
| B2.5 | File uploads validated by MIME type **and** actual content (`mime_content_type()` / magic-byte check in the service layer), not extension alone; size limits enforced | Review `FileUploadService` or equivalent |
| B2.6 | Security headers present on every response: `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` (or `SAMEORIGIN` if iframe embedding is intentional, e.g. the embed widget), `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` (HSTS) | Check for a `SecurityHeaders` middleware; if missing, implement (template below) |
| B2.7 | HTTPS is enforced in production (redirect HTTP → HTTPS), and the install guide tells buyers to enable it on their host | `App\Providers\AppServiceProvider::boot()` for `URL::forceScheme('https')`, or middleware |
| B2.8 | CORS config (`config/cors.php`) is scoped to actual needed origins/paths, not a wildcard `*` on credentialed routes | `config/cors.php` |
| B2.9 | API keys (provider keys, integration keys) are encrypted at rest via `Crypt::encryptString()` and never exposed to the frontend via Inertia shared props | `grep -rn "custom_api_key\|system_prompt" resources/js/` — these must never appear in a `console.log`-able prop |
| B2.10 | Admin permission checks happen at **both** the middleware layer (route-level) and inside the controller for destructive actions (delete, refund, ban) | Spot-check 3–4 destructive admin actions |
| B2.11 | Webhook endpoints (Stripe, PayPal, SSLCommerz, Razorpay, Paddle, CoinGate, Envato) verify signatures before processing — no webhook trusts payload content without signature validation | Review each `*WebhookController` |
| B2.12 | The Envato/license verification flow only ever calls the author-hosted license proxy — the distributed product never embeds a personal Envato API token | Search the codebase shipped to buyers for any literal Envato API token/secret |
| B2.13 | `console.log` / `dd()` / `dump()` / var_dump leftovers are not present in shipped code | `grep -rn "console.log\|dd(\|dump(" app/ resources/js/` (excluding test files) |

### B3. Rate Limiting & Abuse Prevention

| # | Check | How to verify |
|---|-------|---------------|
| B3.1 | Sliding-window rate limiting (Redis sorted sets) implemented for AI generation endpoints per guest/free/pro tier, matching documented limits | Review `RateLimiterService` + `ThrottleAiRequests` middleware |
| B3.2 | `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers present on **every** API response (not only 429s); `Retry-After` present on 429s | Inspect actual response headers on a real request |
| B3.3 | Public (guest) tool access is IP rate-limited and credit-free, with output truncation and no document persistence | Review public-access tool controller path |
| B3.4 | Per-user rate-limit overrides table exists and is checked before falling back to global settings | `user_rate_limit_overrides` table + service logic |
| B3.5 | Banned IPs are checked in global middleware **before** any route-specific logic runs | Middleware order in `bootstrap/app.php` / `Http/Kernel.php` |

### B4. Dependency & Infrastructure Hygiene

| # | Check | How to verify |
|---|-------|---------------|
| B4.1 | `composer audit` reports no known high/critical vulnerabilities in production dependencies | Run `composer audit` |
| B4.2 | `npm audit` reports no known high/critical vulnerabilities | Run `npm audit --production` |
| B4.3 | `.env` is excluded from the distributed zip; `.env.example` documents every required variable with no real secrets committed | Check `.gitignore` and the build/release script |
| B4.4 | No secrets (API keys, DB passwords, Envato tokens) committed anywhere in git history or config files | `git log -p \| grep -i "api_key\|secret\|password"` (on the dev repo, not the buyer-facing zip) |
| B4.5 | Laravel Horizon and Telescope dashboards are protected — only Super Admin can access `/horizon`, and Telescope (if present) is disabled or gated in production | `HorizonServiceProvider::gate()`, `config/telescope.php` |

---

## 4. PART C — SEO AUDIT

### C1. Rendering & Indexability

| # | Check | How to verify |
|---|-------|---------------|
| C1.1 | Inertia SSR is actually running in production (Node SSR server up), and `<head>` tags (title, meta, schema JSON-LD) are present in the **raw server-rendered HTML**, not only after client-side hydration | `curl` the page and check the HTML before any JS executes — schemas must be visible in the raw response |
| C1.2 | Every public page has a unique, descriptive `<title>` and meta description — no duplicate titles across tool pages, blog posts, or landing sections | Sample several tool pages, blog posts |
| C1.3 | Canonical URL (`<link rel="canonical">`) set correctly on every page, including paginated list views (page 2, 3... point to the correct canonical, not all to page 1 incorrectly, and not omitted) | Inspect blog list pagination, tool category pagination |
| C1.4 | `robots.txt` exists, is admin-configurable or at minimum correctly blocks `/admin`, `/api`, `/horizon`, auth pages, while allowing public tool/blog content | Fetch `/robots.txt` |
| C1.5 | `sitemap.xml` is dynamically generated (includes all active AI tools, published blog posts, static pages), kept in sync on publish/unpublish, and referenced from `robots.txt` | Fetch `/sitemap.xml`, confirm a `SitemapController` or scheduled command regenerates it |
| C1.6 | Soft-deleted/unpublished/draft content returns proper 404/410, not a 200 with empty content | Test against a draft blog post or disabled tool slug |
| C1.7 | `<html lang="...">` attribute reflects the active locale, and RTL languages set `dir="rtl"` correctly | Switch language, inspect `<html>` tag |
| C1.8 | Multi-language pages use `hreflang` tags pointing to language-specific URLs (if the product supports per-language URLs/subdomains) | Check `<head>` when multiple languages are active |
| C1.9 | Admin, auth, dashboard, and account pages have `<meta name="robots" content="noindex,nofollow">` so private/dynamic pages don't get indexed | Inspect `<head>` on `/login`, `/dashboard`, `/admin/*` |

### C2. Structured Data (Schema.org)

| # | Check | How to verify |
|---|-------|---------------|
| C2.1 | Every AI tool page renders all 4 required schemas: `SoftwareApplication` (with `aggregateRating` only when `review_count >= 5`), `FAQPage` (only when `show_faqs=true` and items exist), `HowTo` (only when `show_how_it_works=true` and steps exist), `BreadcrumbList` (always) | View page source on a tool page with reviews/FAQs enabled and one without — confirm conditional logic is respected |
| C2.2 | Blog posts render `Article`/`BlogPosting` schema with author, datePublished, dateModified, image | View source on a blog post |
| C2.3 | All schemas validate cleanly at Google's Rich Results Test / schema.org validator — zero errors, zero warnings | Run actual validation against a live/staging tool page and blog post URL |
| C2.4 | `avg_rating` / `review_count` used in `SoftwareApplication.aggregateRating` are recalculated correctly after each approved review (no stale cached rating) | Trigger a review approval, re-check schema output |
| C2.5 | Organization/WebSite schema present on the homepage (sitelinks search box, logo, social profiles) if specced | Check homepage source |

### C3. On-Page & Technical SEO

| # | Check | How to verify |
|---|-------|---------------|
| C3.1 | Every page has exactly one `<h1>`, with a logical heading hierarchy below it (no skipped levels, no multiple h1s) | Inspect tool page, blog post, landing page DOM |
| C3.2 | All meaningful images have descriptive `alt` text (not empty, not the filename) — especially tool icons, blog thumbnails, OG images | `grep -rn "<img" resources/js/` for missing/empty `alt` |
| C3.3 | Open Graph (`og:title`, `og:description`, `og:image`, `og:url`, `og:type`) and Twitter Card tags present and correct on tool pages, blog posts, and the homepage; OG image actually renders correctly when tested with Facebook/Twitter debugger tools | Test 2–3 URLs in the respective debugger tools |
| C3.4 | Internal linking: related tools section, blog related posts, breadcrumbs all use real `<a>`/Inertia `<Link>` elements (crawlable), not JS-only `onClick` navigation | Inspect related-content components |
| C3.5 | URL slugs are human-readable and stable (tool slugs, blog slugs) — no exposed numeric IDs or ULIDs in public-facing URLs | Sample several URLs across the app |
| C3.6 | Meta title/description respect length best practices (title ~50–60 chars, description ~150–160 chars) where auto-generated by `ToolSeoService` | Spot check generated values against character counts |
| C3.7 | Pagination on blog/tool category listings uses proper `<link rel="prev/next">` or equivalent and doesn't create thin/duplicate content | Inspect paginated list pages |

---

## 5. SECURITY HEADERS — REFERENCE IMPLEMENTATION (use only if missing)

If `B2.6` is ❌, implement a middleware like this (adapt CSP directives to actual asset/CDN domains in use — Tabler Icons, Google Fonts if any, Stripe.js, etc.):

```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://js.stripe.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "connect-src 'self' wss: https:",
            "frame-src 'self' https://js.stripe.com",
        ]));

        return $response;
    }
}
```

Register it globally (not just on web routes) and verify it doesn't break Reverb WebSocket connections (`connect-src` must allow `wss:`) or Stripe/payment gateway iframes.

---

## 6. DELIVERABLE FORMAT

Submit results as:

1. **Audit Report** — one table per Part (A/B/C), every item marked ✅ / ❌ / ⚠️ with a one-line citation (file path or command output) as evidence.
2. **Fix Log** — for every ❌/⚠️ item, what file(s) you changed and why, in the same numbered order as the checklist (e.g. "B2.6 — created `app/Http/Middleware/SecurityHeaders.php`, registered in `bootstrap/app.php`").
3. **Re-verification** — after fixes, re-run the same checks and confirm each previously-failing item now passes. Don't mark something fixed without re-checking it.
4. **Open Items** — anything you could not fix without a decision from Shehab (e.g. a missing third-party API key, a CSP directive that conflicts with an addon), listed explicitly rather than silently skipped.

Do not modify anything outside the scope of Performance, Security, and SEO. If you notice an unrelated bug, log it separately instead of fixing it inline.
