# PART 05 — RATE LIMITING STRATEGY: Implementation Plan

## Summary

Replace all existing Laravel `RateLimiter` calls with a new Redis sliding-window `RateLimiterService`. Build `ThrottleAiRequests` middleware for route-level enforcement, add tiered limits (guest/free/pro) stored in settings, implement IP bans, AI abuse detection, admin configuration panel, and a frontend `useRateLimit` composable for ToolPage.

---

## 1. New Files

### 1.1 Migration
**`database/migrations/2025_06_02_000001_create_rate_limit_tables.php`**

Creates two tables:
- **`banned_ips`**: `id`, `ip_address` (varchar 45, unique), `reason` (nullable), `category` (nullable), `banned_at`, `expires_at` (nullable), `banned_by` (FK → admins, nullOnDelete)
- **`user_rate_limit_overrides`**: `id`, `user_id` (FK → users, cascadeOnDelete), `category` (varchar 100), `max_attempts` (int), `window_seconds` (int), `expires_at` (nullable), `timestamps`, unique on `[user_id, category]`

### 1.2 Models
**`app/Models/BannedIp.php`**
- `$timestamps = false`, casts: `banned_at`, `expires_at` → datetime
- Scope: `scopeActive($q)` — where expires_at is null or > now()
- Relation: `bannedBy(): BelongsTo` → Admin
- Fillable: `ip_address`, `reason`, `category`, `banned_at`, `expires_at`, `banned_by`

**`app/Models/UserRateLimitOverride.php`**
- Casts: `expires_at` → datetime
- Scope: `scopeActive($q)` — where expires_at is null or > now()
- Relation: `user(): BelongsTo` → User
- Fillable: `user_id`, `category`, `max_attempts`, `window_seconds`, `expires_at`

### 1.3 Core Service
**`app/Services/RateLimiterService.php`**

Full sliding-window implementation using Redis sorted sets:
```
PREFIX = 'rl:'

public function attempt(string $category, string $key, ?int $maxAttempts, ?int $windowSeconds, ?User $user): array
  // Returns: [allowed, limit, remaining, reset_at, retry_after_seconds]

public function hit(string $category, string $key, ?int $windowSeconds): void
  // Record a hit after successful validation (e.g., failed login)

public function clear(string $category, string $key): void
  // Clear all entries for a key (e.g., successful login)

public function status(string $category, string $key, ?int $maxAttempts, ?int $windowSeconds): array
  // Read-only check, doesn't increment

public function banIp(string $ip, string $reason, string $category, ?int $adminId, ?Carbon $expiresAt): void

public function unbanIp(string $ip): void

public function isIpBanned(string $ip): bool
  // Check active bans

public function checkAiAbuse(string $ip, string $category): bool
  // Track rate limit hits per IP for text_gen; if hits exceed threshold (settings: rl_ai_abuse_threshold), auto-ban IP

private function resolveTier(?User $user): string
  // guest | free_user | pro_user — based on isProAvailable() + user subscription status

private function getLimitForTier(string $category, string $tier): array
  // Reads from user_rate_limit_overrides (if active) → falls back to settings table (group: rate_limits) → falls back to defaults

private function slidingWindowCheck(string $redisKey, int $maxAttempts, int $windowSeconds): array
  // Redis pipeline: ZREMRANGEBYSCORE → ZCARD → ZADD → EXPIRE
  // Returns full result array

private function buildRedisKey(string $category, string $key): string
  // "rl:{category}:{key}"
```

**Sliding window Redis algorithm:**
1. `$now = microtime(true) * 1_000_000` (microseconds as score)
2. `$windowStart = $now - ($windowSeconds * 1_000_000)`
3. Pipeline: `ZREMRANGEBYSCORE key 0 windowStart` — remove expired entries
4. `$count = ZCARD key` — count remaining in window
5. If `$count < $maxAttempts`: `ZADD key now {now+randomOffset}` + `EXPIRE key windowSeconds+1` → return `[allowed=true, remaining=max-count-1]`
6. Else: `ZRANGE key 0 0 WITHSCORES` → get oldest timestamp → compute `retryAfter = oldest+window-now` → return `[allowed=false, remaining=0, retry_after]`

### 1.4 Middleware
**`app/Http/Middleware/ThrottleAiRequests.php`**

Replaces Laravel's built-in `throttle` middleware. Registered with alias `throttle` in `bootstrap/app.php`.

```
Constructor: inject RateLimiterService

handle(Request $request, Closure $next, string $category = 'public', ?int $maxAttempts = null, ?int $windowSeconds = null): Response
  1. Check IP ban via RateLimiterService::isIpBanned() → 429 if banned
  2. Build key via buildKey($request, $category)
  3. Call RateLimiterService::attempt(category, key, maxAttempts, windowSeconds, user)
  4. If not allowed → return 429 JSON with:
     - [success: false, message, retry_after, code: 'RATE_LIMITED']
     - Headers: Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
  5. If text_gen category → RateLimiterService::checkAiAbuse(ip)
  6. Call $next($request)
  7. Attach X-RateLimit-* headers to response

private buildKey(Request $request, string $category): string
  text_gen: user->id ?? 'ip:'.$ip
  auth: email.'|'.$ip
  otp: actorId.'|'.$ip  (resolve from session or user)
  contact: $ip
  comments: user->id ?? 'ip:'.$ip
  newsletter: email.'|'.$ip
  social_auth: provider.'|'.$ip
  public: $ip
  default: $ip
```

### 1.5 Admin Controller
**`app/Http/Controllers/Admin/RateLimitController.php`**

```
CATEGORIES constant:
  text_gen, auth, otp, contact, comments, newsletter, public, social_auth

index(): Inertia render → Admin/Security/RateLimits
  Props: tiers (current tier limits), bannedIps (paginated), overrides (paginated), categories

updateTiers(Request): POST /tiers
  Validates: { tiers: array of { category, guest_max, guest_window, free_max, free_window, pro_max, pro_window } }
  Writes to settings table via settings_set()

banIp(Request): POST /ban
  Validates: ip_address, reason, category, expires_in_hours
  Calls RateLimiterService::banIp()

unbanIp(BannedIp): DELETE /ban/{bannedIp}
  Calls RateLimiterService::unbanIp()

storeOverride(Request): POST /overrides
  Validates: user_id, category, max_attempts, window_seconds, expires_at (optional)
  Creates UserRateLimitOverride

deleteOverride(UserRateLimitOverride): DELETE /overrides/{override}
  Deletes override
```

### 1.6 Frontend Admin Page
**`resources/js/Pages/Admin/Security/RateLimits.vue`**

Three sections:
1. **Tier Limits Table** — per-category rows showing guest/free/pro limits, inline edit, save
2. **Banned IPs** — table with IP, reason, category, banned_at, expires, unban button, "Ban New IP" form
3. **User Overrides** — table with user, category, override values, expires, delete button, "Add Override" form

### 1.7 Frontend Composable
**`resources/js/Composables/useRateLimit.ts`**

```ts
export function useRateLimit() {
  // state: reactive { limit, remaining, resetAt, retryAfter, isLimited }
  // countdown: ref(0)
  // parseHeaders(headers: Headers): reads X-RateLimit-* and Retry-After
  // startCountdown(seconds): interval-based countdown timer
  // stopCountdown(): cleanup
  // formattedCountdown: computed "M:SS" string
  // isNearLimit: computed (remaining <= 5 && > 0)
  // onUnmounted → stopCountdown
  return { state, countdown, formattedCountdown, isNearLimit, parseHeaders, startCountdown, stopCountdown }
}
```

### 1.8 Settings Seeder
**`database/seeders/SettingSeeder.php`** (append to existing seeder)

Add `group = 'rate_limits'` settings for all 8 categories × 3 tiers × 2 params (max + window):

| Setting Key Pattern | Guest | Free | Pro |
|---|---|---|---|
| `rl_text_gen_{tier}_max` | 5 | 30 | 120 |
| `rl_text_gen_{tier}_window` | 3600 | 60 | 60 |
| `rl_auth_{tier}_max` | 5 | 10 | 20 |
| `rl_auth_{tier}_window` | 900 | 900 | 900 |
| `rl_otp_{tier}_max` | 5 | 5 | 5 |
| `rl_otp_{tier}_window` | 900 | 900 | 900 |
| `rl_contact_{tier}_max` | 3 | 5 | 10 |
| `rl_contact_{tier}_window` | 3600 | 3600 | 3600 |
| `rl_comments_{tier}_max` | 5 | 10 | 20 |
| `rl_comments_{tier}_window` | 60 | 60 | 60 |
| `rl_newsletter_{tier}_max` | 3 | 3 | 3 |
| `rl_newsletter_{tier}_window` | 3600 | 3600 | 3600 |
| `rl_public_{tier}_max` | 5 | 15 | 30 |
| `rl_public_{tier}_window` | 3600 | 3600 | 3600 |
| `rl_social_auth_{tier}_max` | 10 | 10 | 10 |
| `rl_social_auth_{tier}_window` | 300 | 300 | 300 |

Plus:
- `rl_ai_abuse_threshold` = 100 (hits per day to flag)
- `rl_ai_abuse_window` = 60 (window in minutes for abuse tracking)
- `rl_ai_abuse_ban_duration` = 86400 (24h auto-ban)

---

## 2. Files to Modify

### 2.1 `bootstrap/app.php`
**Override the `throttle` alias** to point to our `ThrottleAiRequests`:
```php
$middleware->alias([
    // keep existing...
    'throttle' => \App\Http\Middleware\ThrottleAiRequests::class,  // was Laravel's built-in
]);
```
This means ALL existing `->middleware('throttle:...')` calls now route to our middleware.

### 2.2 `app/Providers/AppServiceProvider.php`
**REMOVE** all 8 `RateLimiter::for(...)` blocks in the `boot()` method. These are lines defining:
- `ai_text_gen`, `admin-login`, `admin-2fa`, `admin-password-email`, `admin-password-reset`
- `password-email`, `password-reset`, `user-2fa`

Also remove `use Illuminate\Cache\RateLimiting\Limit;` import. Keep other boot logic intact.

### 2.3 `routes/api.php`
Update AI generation routes:
```php
// OLD: throttle:ai_text_gen → NEW: throttle:text_gen
Route::middleware(['throttle:text_gen', 'check.credits'])->prefix('generate')->group(function () {
    Route::post('stream', [GenerateController::class, 'stream']);
    Route::post('text', [GenerateController::class, 'text']);
});
Route::middleware('throttle:text_gen')->prefix('generate')->group(function () {
    Route::get('estimate', [GenerateController::class, 'estimate']);
});
```

### 2.4 `routes/web.php`
Replace all throttle middleware calls:

| Route | Current | New |
|---|---|---|
| `/live-search` GET | `throttle:60,1` | `throttle:public,60,60` |
| `auth/{provider}/redirect` GET | `throttle:10,1` | `throttle:social_auth` |
| `auth/{provider}/callback` GET | `throttle:10,1` | `throttle:social_auth` |
| `forgot-password` POST | `throttle:password-email` | `throttle:otp,3,3600` |
| `reset-password/verify` POST | `throttle:password-reset` | `throttle:otp,5,900` |
| `reset-password` POST | `throttle:password-reset` | `throttle:otp,5,900` |
| `two-factor` POST | `throttle:user-2fa` | `throttle:otp,5,900` |
| `dashboard/settings/two-factor` POST | `throttle:user-2fa` | `throttle:otp,5,900` |
| `dashboard/settings/two-factor/disable` POST | `throttle:user-2fa` | `throttle:otp,5,900` |
| `dashboard/settings/two-factor/recovery-codes` POST | `throttle:user-2fa` | `throttle:otp,5,900` |
| `/comments` POST | `throttle:5,1` | `throttle:comments` |
| `/comments/{comment}/like` POST | `throttle:20,1` | `throttle:comments,20,60` |
| `/comments/{comment}/report` POST | `throttle:5,1` | `throttle:comments` |
| `/newsletter/subscribe` POST | `throttle:3,60` | `throttle:newsletter` |
| `/contact` POST | (none) | ADD `throttle:contact` |

### 2.5 `routes/admin.php`
Replace named throttles and add Rate Limit admin routes:

| Route | Current | New |
|---|---|---|
| `admin/login` POST | `throttle:admin-login` | `throttle:auth` |
| `admin/forgot-password` POST | `throttle:admin-password-email` | `throttle:otp,3,3600` |
| `admin/reset-password/verify` POST | `throttle:admin-password-reset` | `throttle:otp,5,900` |
| `admin/reset-password` POST | `throttle:admin-password-reset` | `throttle:otp,5,900` |
| `admin/2fa` POST | `throttle:admin-2fa` | `throttle:otp` |
| `admin/security/two-factor` POST | `throttle:admin-2fa` | `throttle:otp` |
| `admin/security/two-factor/disable` POST | `throttle:admin-2fa` | `throttle:otp` |
| `admin/security/two-factor/recovery-codes` POST | `throttle:admin-2fa` | `throttle:otp` |

Add inside `admin.auth` group:
```php
// Security → Rate Limits
Route::prefix('security/rate-limits')->name('admin.security.rate-limits.')->group(function () {
    Route::get('/', [RateLimitController::class, 'index'])->name('index');
    Route::post('/tiers', [RateLimitController::class, 'updateTiers'])->name('tiers.update');
    Route::post('/ban', [RateLimitController::class, 'banIp'])->name('ban');
    Route::delete('/ban/{bannedIp}', [RateLimitController::class, 'unbanIp'])->name('unban');
    Route::post('/overrides', [RateLimitController::class, 'storeOverride'])->name('overrides.store');
    Route::delete('/overrides/{override}', [RateLimitController::class, 'deleteOverride'])->name('overrides.delete');
});
```

### 2.6 `app/Http/Controllers/Auth/LoginController.php`
Replace `RateLimiter::tooManyAttempts()` / `RateLimiter::hit()` / `RateLimiter::clear()` / `RateLimiter::availableIn()` calls:

```php
// OLD:
use Illuminate\Cache\RateLimiter;
// ...
$throttleKey = 'login:'.$request->ip();
$maxAttempts = (int) settings('login_throttle_max', 5);
if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
    $seconds = RateLimiter::availableIn($throttleKey);
    throw ValidationException::withMessages([...])->with('retry_after', $seconds);
}
RateLimiter::hit($throttleKey, 900);
// On success:
RateLimiter::clear($throttleKey);

// NEW:
use App\Services\RateLimiterService;
// ...
$rateLimiter = app(RateLimiterService::class);
$result = $rateLimiter->attempt('auth', $request->ip(), null, null, null);
if (! $result['allowed']) {
    throw ValidationException::withMessages([...])->with('retry_after', $result['retry_after_seconds']);
}
$rateLimiter->hit('auth', $request->ip());
// On success:
$rateLimiter->clear('auth', $request->ip());
```

### 2.7 `app/Http/Controllers/Admin/AdminLoginController.php`
Replace all 4 `RateLimiter` usage zones (login, 2FA verify, password email, password reset) with `RateLimiterService` calls. Same pattern as LoginController. Use category `'auth'` for login and `'otp'` for 2FA/password operations.

### 2.8 `app/Http/Controllers/Auth/PasswordResetController.php`
Replace 2 `RateLimiter` zones (password-email, password-reset) with `RateLimiterService` calls using category `'otp'`.

### 2.9 `app/Http/Controllers/Auth/SocialAuthController.php`
Replace `RateLimiter::hit()` in `redirect()` and `RateLimiter::tooManyAttempts()`/`hit()` in `callback()` with `RateLimiterService` calls using category `'social_auth'` and key `$provider.'|'.$request->ip()`.

### 2.10 `app/Http/Controllers/ContactController.php`
Replace `RateLimiter::attempt('contact-submission:'.$request->ip(), 3, 3600)` callback pattern with direct `RateLimiterService::attempt('contact', $request->ip(), 3, 3600)`. Use the result array [`allowed`] instead of boolean.

### 2.11 `app/Http/Middleware/CheckCredits.php`
Remove the public tool rate limit logic (lines 60-75 that use `Cache::get/put` with `public_tool_rate:{ip}:{slug}`). The `throttle:public` middleware on routes now handles this via settings. Keep the credit/access check logic intact.

### 2.12 `resources/js/Pages/ToolPage.vue`
Integrate `useRateLimit` composable:

```vue
<script setup>
import { useRateLimit } from '@/Composables/useRateLimit'
const { state, countdown, formattedCountdown, isLimited, isNearLimit, parseHeaders } = useRateLimit()
</script>

<template>
  <button :disabled="isLimited || isStreaming" @click="generate">
    <template v-if="isLimited">
      Try again in {{ formattedCountdown }}
    </template>
    <template v-else>
      Generate ✨
    </template>
  </button>
  <div v-if="isNearLimit && !isLimited" class="rate-limit-warning">
    {{ state.remaining }} requests remaining this minute
  </div>
</template>
```

In the `generate()` function, after the fetch call, parse rate limit headers:
```ts
parseHeaders(response.headers)
```

---

## 3. Implementation Order

1. **Migration** — run to create `banned_ips` and `user_rate_limit_overrides` tables
2. **Models** — `BannedIp`, `UserRateLimitOverride`
3. **RateLimiterService** — core service with sliding window logic
4. **ThrottleAiRequests middleware** — register alias in `bootstrap/app.php`
5. **Settings seeder** — seed rate_limits group values
6. **AppServiceProvider** — remove old RateLimiter::for() definitions
7. **Route updates** — api.php, web.php, admin.php throttle changes
8. **Controller updates** — LoginController, AdminLoginController, PasswordResetController, SocialAuthController, ContactController
9. **CheckCredits** — remove Cache-based public rate limit
10. **Admin RateLimitController + RateLimits.vue page**
11. **useRateLimit composable**
12. **ToolPage.vue integration**

---

## 4. Verification

### 4.1 Manual Testing
- Hit a rate-limited endpoint rapidly until 429 is returned
- Verify `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers on response
- Verify `Retry-After` header on 429 response
- Wait and verify rate limit resets correctly (sliding window)
- Test boundary: send 1 request, wait window-1 seconds, send max+1 — should be rejected (no boundary burst)
- Test guest/free/pro tier resolution: login as free user vs pro user, verify different limits
- Test per-user override: set override for user, verify it takes priority
- Test IP ban: ban an IP, verify 429 on all requests from that IP
- Test AI abuse: simulate 10+ rate limit hits, verify IP auto-ban
- Test clear on success: failed logins accumulate, successful login clears

### 4.2 Automated Tests
- **Unit test**: `tests/Unit/Services/RateLimiterServiceTest.php`
  - Sliding window correctness
  - Tier resolution (guest/free/pro)
  - User override priority
  - Redis key cleanup
- **Feature test**: `tests/Feature/RateLimitingTest.php`
  - Auth endpoints (login, register, OTP, 2FA)
  - AI text generation endpoints
  - Contact form
  - Comments (store, like, report)
  - Newsletter subscribe
  - Rate limit headers present on all responses
  - 429 response format

### 4.3 Checklist (from spec)
- [ ] Sliding window implementation tested: no boundary burst possible
- [ ] All rate limit values stored in `settings` table — editable from admin without code deploy
- [ ] Guest/free/pro tiers correctly resolved per user auth state
- [ ] `Retry-After`, `X-RateLimit-*` headers present on ALL API responses (not just 429)
- [ ] 429 error page shows countdown timer reading `Retry-After` header
- [ ] Frontend `useRateLimit` composable shows live countdown on Generate button
- [ ] Login: 5 failures per email/15min → lockout; 20 per IP/hr → ban
- [ ] Banned IPs checked in global middleware (before route resolution)
- [ ] Per-user rate limit overrides work (override takes priority over global settings)
- [ ] AI abuse: 10+ rate limit hits/day → user flagged in admin
- [ ] Rate limit keys use sliding window (Redis sorted sets), not simple counters
- [ ] Redis key TTL set correctly — keys expire cleanly after decay window
- [ ] Admin can view flagged users list with reason + action buttons
