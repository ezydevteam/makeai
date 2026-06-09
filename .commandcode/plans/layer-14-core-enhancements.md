# Layer 14 — Core Feature Enhancements Implementation Plan

## Overview

Implement all 10 features from Parts 53-62 of AI_SaaS_Master_Prompt.md, plus user-facing export (from Part 63). Ordered in 5 phases by dependency.

## Architecture Rules

- **Controllers:** readonly constructor DI, `Inertia::render('Page/Name', [props])`
- **Services:** `app/Services/`, constructor DI
- **State:** Module-level `reactive()` singletons + composables (no `defineStore`)
- **ULIDs:** `Str::ulid()` in model `booted()::creating` hook
- **JSON columns:** Cast to `'array'` in model `casts()`
- **SSE:** `response()->stream(callback, 200, SSE_HEADERS)`
- **Cache:** `Cache::remember(key, TTL, fn)`, prefix `makeai:`
- **Jobs:** `MyJob::dispatch($args)->onQueue('queue_name')`
- **Reverb broadcasts:** Create `ShouldBroadcast` events (first ones in the project)
- **Ziggy routes:** `route('name', params)` global in Vue templates

---

## PHASE 1 — FOUNDATION

### Part 54: Prompt Versioning & History

**Purpose:** Every AI generation saves a versioned snapshot. Users browse/diff/restore history.

#### Migration

```
database/migrations/2026_06_06_000001_create_generation_history_table.php
```

Table `generation_history`:
- `id` bigint auto-increment (use `$table->id()`)
- `ulid` char(26) unique → route key
- `user_id` bigint FK → users.id, cascadeOnDelete
- `tool_slug` varchar(100)
- `document_id` bigint FK → documents.id, nullable
- `prompt_system` text
- `prompt_user` text
- `field_values` json → cast 'array'
- `model` varchar(100)
- `provider` varchar(50)
- `temperature` decimal(3,2)
- `max_tokens` int
- `output_preview` text (first 500 chars)
- `tokens_input` int
- `tokens_output` int
- `is_favorited` boolean default false
- `label` varchar(100) nullable
- `created_at` timestamp
- Indexes: `(user_id, tool_slug, created_at)`, `(user_id, is_favorited)`

#### Model — `app/Models/GenerationHistory.php`
- `$fillable`: all columns except id/ulid/timestamps
- `casts()`: `field_values` → 'array', `is_favorited` → 'boolean', `temperature` → 'decimal:2'
- `booted()`: generating ULID on creating
- `getRouteKeyName()`: returns 'ulid'
- `user()`: belongsTo
- `document()`: belongsTo (nullable)

#### Service — `app/Services/GenerationHistoryService.php`
- `record(User $user, array $data): GenerationHistory` — create record, prune to 200 per user
- `getHistory(User $user, ?string $toolSlug, int $perPage): LengthAwarePaginator`
- `restore(GenerationHistory $history): array` — return field_values + model + provider + temperature
- `diff(GenerationHistory $a, GenerationHistory $b): array` — word-level diff, return `[{word, status}]`
- `toggleFavorite(GenerationHistory $history): void`

#### Job — `app/Jobs/RecordGenerationHistoryJob.php`
- Implements `ShouldQueue`, queue: `'ai'`
- Constructor: `(User $user, array $data)`
- Auto-prune to 200 records

#### Integration Point
In `GenerateController::stream()`, after stream completes — dispatch:
```php
RecordGenerationHistoryJob::dispatch($user, [...])->onQueue('ai');
```
Same dispatch in `AiService::complete()` and `AiService::runTemplate()`.

#### Controller — `app/Http/Controllers/HistoryController.php`
- `index()` → Inertia 'History/Index' with paginated history
- `byTool(string $toolSlug)` → filtered history
- `restore(GenerationHistory $history)` → JSON response
- `favorite(GenerationHistory $history)` → toggle
- `diff(GenerationHistory $historyA, GenerationHistory $historyB)` → JSON diff array
- `label(Request $request, GenerationHistory $history)` → update label
- `destroy(GenerationHistory $history)` → authorize owner, delete

#### Routes
```php
Route::middleware(['auth', 'verified'])->prefix('history')->name('history.')->group(function () {
    Route::get('/', [HistoryController::class, 'index'])->name('index');
    Route::get('/tool/{toolSlug}', [HistoryController::class, 'byTool'])->name('by-tool');
    Route::post('/{history}/restore', [HistoryController::class, 'restore'])->name('restore');
    Route::post('/{history}/favorite', [HistoryController::class, 'favorite'])->name('favorite');
    Route::post('/{historyA}/diff/{historyB}', [HistoryController::class, 'diff'])->name('diff');
    Route::put('/{history}/label', [HistoryController::class, 'label'])->name('label');
    Route::delete('/{history}', [HistoryController::class, 'destroy'])->name('destroy');
});
```

#### Vue Pages
- `resources/js/Pages/History/Index.vue` — layout: UserDashboardLayout, filter by tool, favorites toggle
- `resources/js/Components/AI/HistoryTab.vue` — in-tool sidebar tab on ToolPage
- `resources/js/Components/AI/DiffModal.vue` — word-level diff viewer

#### Verification
- [ ] `RecordGenerationHistoryJob` dispatched after every AI generation
- [ ] Auto-prune to 200 records per user
- [ ] History scoped to owning user only
- [ ] Restore fills all form fields including model/provider
- [ ] Diff shows word-level changes
- [ ] Favoriting toggles without page reload

---

### Part 56: User Usage Dashboard

**Purpose:** User-facing analytics page using existing `ai_usage_logs` and `users` credits data. No new tables.

#### Controller — `app/Http/Controllers/UsageDashboardController.php`
- `index()` → Inertia 'Usage/Index', cache key: `usage_stats_{user_id}`, TTL: 300s
- Stats: credits_remaining, credits_used_today, credits_used_month, plan_credit_limit, total_generations, total_tokens, daily_usage (30 days), top_tools (5), most_used_model, peak_hour, recent_history (10)
- `export()` → streams CSV via `LazyCollection` + `fputcsv`

#### Routes
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/usage', [UsageDashboardController::class, 'index'])->name('usage.index');
    Route::post('/usage/export', [UsageDashboardController::class, 'export'])->name('usage.export');
});
```

#### Vue — `resources/js/Pages/Usage/Index.vue`
- Layout: UserDashboardLayout
- 4 stat cards + Chart.js bar chart + credit limit progress bar
- Top 5 tools + Insights panel + Recent generations + Export CSV

#### Verification
- [ ] Stats cached per-user 5 min TTL
- [ ] Chart.js dynamically imported (tree-shaking)
- [ ] Page loads under 200ms (cached)

---

## PHASE 2 — BUILD ON FOUNDATION

### Part 57: AI Tool Favorites & Collections

**Purpose:** Reuse existing polymorphic `favorites` table for tool favorites. Add collections system.

#### Migration — `create_collections_tables`
**`user_collections`:** id, ulid, user_id FK (nullable for featured), name, description, icon, color, is_featured, sort_order, timestamps
**`user_collection_tools`:** id, collection_id FK, tool_slug, sort_order, added_at, unique(collection_id, tool_slug)

#### Models — `UserCollection`, `UserCollectionTool`
- ULID route key, relationships

#### Service — extend favorites
- `toggleFavorite(User, string $toolSlug): bool` — uses existing `Favorite` polymorphic model
- `getFavorites(User): Collection` — cached Redis, TTL 3600
- Collection CRUD methods

#### Controller — `CollectionController`
- Full CRUD + addTool/removeTool/reorder

#### Favorite Toggle
```php
Route::post('/tools/{toolSlug}/favorite', [ToolFavoriteController::class, 'toggle'])->name('tools.favorite');
```
Returns JSON `{ favorited: bool }`, optimistic UI.

#### Vue
- Favorite star button on every tool card/tool page
- Collections page with grid, virtual "Favorites" collection, vue-draggable-plus
- Sidebar integration

#### Verification
- [ ] Star toggle optimistic + cached
- [ ] "Favorites" collection is virtual (computed)
- [ ] Admin featured collections visible to all

---

### Part 58: AI Output Rating System

**Purpose:** Thumbs up/down per-generation feedback. Admin aggregate tool quality.

#### Migration — `create_ai_output_ratings_table`
- id, ulid, user_id FK, tool_slug, document_id FK nullable, generation_history_id FK nullable
- rating tinyint (1=up, 0=down), feedback_text varchar(500) nullable
- model, provider, created_at
- Indexes: (tool_slug, rating, created_at), (user_id), unique(user_id, generation_history_id)

#### Model — `AiOutputRating`
- ULID, relationships

#### Controller + Route
```php
Route::post('/ratings', [OutputRatingController::class, 'store'])->middleware(['auth', 'verified'])->name('ratings.store');
```

#### Vue — `Components/AI/OutputRating.vue`
- 👍👎 buttons in OutputPanel after generation
- States: idle, submitted, error
- Hidden after 24h or if already rated

#### Admin
- Redis cached: `makeai:tool_quality_scores`, TTL 1h
- Alert: tool below 60% with ≥ 10 ratings

#### Verification
- [ ] One rating per generation enforced
- [ ] Admin quality widget cached hourly

---

### Part 61: Smart Credit Top-Up Alerts

**Purpose:** In-app banner + email when credits drop below threshold.

#### Migration — `add_preferences_to_users_table`
```php
$table->json('preferences')->nullable();
```
Cast in User model: `'preferences' => 'array'`

#### Job — `SendCreditAlertJob`
- Queue: `'mail'`, sends email + in-app notification

#### Trigger in `TokenGuard::after()`
```php
if (isProAvailable() && $user->credits <= settings('credit_alert_threshold', 100)) {
    $cacheKey = "credit_alert_sent_{$user->id}";
    if (!Cache::has($cacheKey)) {
        SendCreditAlertJob::dispatch($user)->onQueue('mail');
        Cache::put($cacheKey, true, now()->addDays(settings('credit_alert_cooldown_days', 7)));
    }
}
```

#### Vue — `Components/CreditAlertBanner.vue`
- Shown in UserDashboardLayout when credits ≤ threshold
- Dismiss stores in `users.preferences.credit_alert_dismissed_at`
- Reappears after 24h

#### Verification
- [ ] `isProAvailable()` gated
- [ ] Cooldown cache prevents duplicate emails
- [ ] Banner dismiss survives logout

---

## PHASE 3 — COMPLEX FEATURES

### Part 53: AI Playground (Model Sandbox)

**Purpose:** Side-by-side model comparison. No DB — reactive state + localStorage. Shares in Redis.

#### Service — `PlaygroundService`
- `runStream(...)` — wraps AiService streaming
- `share(array): string` — Redis `makeai:playground_share:{uuid}`, TTL 604800s
- `getShare(string): ?array`

#### Controller — `PlaygroundController`
- `index()` → Inertia 'Playground/Index'
- `run(Request)` → StreamedResponse (TokenGuard, SSE)
- `share(Request)` → JsonResponse
- `showShare(uuid)` → Inertia 'Playground/Share' (public, no auth, read-only)

#### Routes
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/playground', [PlaygroundController::class, 'index'])->name('playground.index');
    Route::post('/playground/run', [PlaygroundController::class, 'run'])->name('playground.run');
    Route::post('/playground/share', [PlaygroundController::class, 'share'])->name('playground.share');
});
Route::get('/playground/s/{uuid}', [PlaygroundController::class, 'showShare'])->name('playground.share.show');
```

#### Vue — `Pages/Playground/Index.vue`
- Layout: UserLayout (full-screen)
- Two-column grid, each with provider/model dropdowns + parameters + streaming output
- Shared message area, "Run Both" fires 2 independent fetch() calls
- State: `usePlayground.ts` composable with `reactive()` singleton + localStorage

#### Verification
- [ ] Two panels stream simultaneously (independent fetch calls)
- [ ] TokenGuard on every run
- [ ] Share expires after 7 days (Redis TTL)
- [ ] No DB writes during session

---

### Part 59: Variant Generation

**Purpose:** 2-3 output variants per generation with temperature offset.

#### Migration
- `$table->tinyInteger('max_variants')->default(3)` on ai_tools
- `default_variant_count` in users.preferences JSON

#### Backend — `GenerateController::stream()`
- Parse `variant_count` from request (capped at tool.max_variants)
- Fire parallel streams: variant 1=base_temp, 2=base+0.15, 3=base+0.30 (capped at 2.0)
- SSE events prefixed: `{"variant": 1, "chunk": "..."}`
- Credits: variantCount × base cost
- Final: `{"event": "done", "variants": 3, "tokens": [...]}`

#### Vue
- Variant selector (pill toggle) in DynamicForm before generation
- Variant tabs in OutputPanel with "Use This Version" + streaming per tab

#### Verification
- [ ] Credits deducted for all variants
- [ ] `max_variants=1` disables selector
- [ ] Only chosen variant saved to history/documents

---

## PHASE 4 — HEAVY INTEGRATIONS

### Part 55: Quick Tool Chaining

**Purpose:** Linear pipe of up to 5 AI tools. Reverb progress broadcasts.

#### Migrations — `create_tool_chains_tables`
**`tool_chains`:** id, ulid, user_id FK, name, steps json, last_run_at, run_count, timestamps
**`tool_chain_runs`:** id, ulid, chain_id FK, user_id FK, status enum, step_outputs json, total_tokens, total_credits, started_at, completed_at

#### Models — `ToolChain`, `ToolChainRun`
- ULID route key, JSON casts

#### Service — `ToolChainService`
- `runChain()`, `executeStep()`, `buildFieldMap()` (resolve `{{step_N_output}}` placeholders)

#### Job — `RunToolChainJob` (queue: `'ai'`)
- Iterates steps, broadcasts per-step via Reverb

#### Broadcasting Events (first in project)
- `ChainStepCompleted` implements ShouldBroadcast → `private-chain.{user_id}`
- `ChainCompleted` implements ShouldBroadcast → `private-chain.{user_id}`

#### Controller — `ChainController`
- CRUD + runForm/run

#### Vue
- `Chains/Index.vue`, `Chains/Builder.vue` (max 5 steps, auto-mapping), `Chains/Run.vue` (live progress)
- `useChain.ts` composable for Reverb listener

#### Verification
- [ ] Each step calls TokenGuard
- [ ] Failed step preserves completed outputs
- [ ] Reverb broadcasts per-step progress

---

### Part 60: AI Tool Embed Widget

**Purpose:** Any AI tool embeddable as standalone `<iframe>`. Vanilla JS + Blade.

#### Migration — `create_tool_embeds_table`
- id, ulid, user_id FK, tool_slug, token varchar(64) unique, label, allowed_origins json, password_hash, theme enum, primary_color, show_branding, usage_count, last_used_at, is_active, timestamps
- Add `$table->boolean('is_embeddable')->default(true)` to ai_tools

#### Model — `ToolEmbed`
- ULID, JSON casts, manual tool() relation via slug

#### Controllers
- `EmbedController` (public): show() → Blade view, run() → StreamedResponse, unlock()
- `ToolEmbedController` (auth): CRUD + regenerateToken

#### Blade — `resources/views/embed/tool.blade.php`
- Standalone HTML (no Vue/Inertia), Tailwind CDN, vanilla JS SSE consumer
- CORS check, optional password gate, `postMessage()` height resize

#### Routes
```php
// Public (no auth)
Route::get('/embed/{token}', [EmbedController::class, 'show'])->name('embed.show');
Route::post('/embed/{token}/run', [EmbedController::class, 'run'])->name('embed.run');
Route::post('/embed/{token}/unlock', [EmbedController::class, 'unlock'])->name('embed.unlock');

// Auth management
Route::middleware(['auth', 'verified'])->prefix('tool-embeds')->name('embeds.')->group(function () {
    Route::get('/', [ToolEmbedController::class, 'index'])->name('index');
    Route::post('/', [ToolEmbedController::class, 'store'])->name('store');
    Route::put('/{embed}', [ToolEmbedController::class, 'update'])->name('update');
    Route::delete('/{embed}', [ToolEmbedController::class, 'destroy'])->name('destroy');
    Route::post('/{embed}/regenerate-token', [ToolEmbedController::class, 'regenerateToken'])->name('regen');
});
```

#### Vue — `Pages/ToolEmbeds/Index.vue`
- Management page with embed code copy

#### Verification
- [ ] Credits from embed owner, not visitor
- [ ] CORS check on public endpoints
- [ ] No Vue/Inertia in embed pages
- [ ] Usage tracked in Redis, synced hourly

---

## PHASE 5 — UTILITY

### Part 62: Command Palette Enhancements

**Purpose:** Global `Ctrl+K` command palette with Recent Generations, Favorites, Quick Actions.

#### Composable — `useCommandPalette.ts`
- State: query, isOpen, selectedIndex
- Groups: Favorites, Recents, All Tools, Documents, Navigation, Quick Actions
- Fuzzy matching, keyboard navigation

#### Component — `CommandPalette.vue`
- Overlay modal, grouped results, keyboard shortcuts
- Mount globally in app.ts

#### Quick Actions
- Copy last output (`Ctrl+Shift+L`)
- New generation: {tool} → navigate + focus
- Switch model → model picker

#### Verification
- [ ] `Ctrl+K` opens, Escape closes
- [ ] Empty groups hidden
- [ ] All items keyboard-navigable

---

### Part 63: User-Facing Export

**Purpose:** Extend ExportService for user exports of generations/documents.

#### Controller — `UserExportController`
- `export(Request)` → XLSX/CSV/PDF of user's data (scoped to auth user)

#### Export Classes — `User/GenerationHistoryExport`, `User/DocumentsExport`
- FromQuery + WithHeadings + WithMapping

#### Routes
```php
Route::middleware(['auth', 'verified'])->post('/export', [UserExportController::class, 'export'])->name('user.export');
```

#### Verification
- [ ] Exports scoped to owning user only
- [ ] CSV streams via LazyCollection

---

## IMPLEMENTATION ORDER

1. **Phase 1:** Part 54 (History) + Part 56 (Usage Dashboard)
2. **Phase 2:** Part 57 (Collections) + Part 58 (Ratings) + Part 61 (Credit Alerts)
3. **Phase 3:** Part 53 (Playground) + Part 59 (Variants)
4. **Phase 4:** Part 55 (Chaining) + Part 60 (Embed Widget)
5. **Phase 5:** Part 62 (Command Palette) + Part 63 (User Export)

## SUGGESTED IMPROVEMENTS

1. **Generation History — diff engine:** Instead of a custom PHP word-diff, use a small JS library like `diff` (npm) on the frontend for real-time diff without API round-trip. The server just returns the two text strings.

2. **Collections — add ability to share collections** via a public link (like playground shares). Makes the feature more viral.

3. **Credit Alerts — add a "snooze" option** to the banner (e.g., "Remind me tomorrow" vs "Don't show again this week").

4. **Playground — add "Compare with Template"** mode where left panel is a saved AI Tool template and right panel is raw playground. Best of both worlds for power users.

5. **Command Palette — extract into its own micro-feature** initially, then enhance. Currently the composable doesn't exist but is referenced in shortcuts. Start with a minimal working palette (tools + navigation + recents) then layer on favorites, quick actions, etc.

6. **Embed Widget — consider adding a React/Vue wrapper option** later. A thin npm package that wraps the iframe into a proper component with typed props would increase adoption.

7. **User Usage Dashboard — add a "weekly digest" email opt-in** that auto-generates a summary of their usage stats. High engagement + touchpoint for upsells.

8. **Variant Generation — store all variants temporarily** (not just the chosen one) so users can switch between them in the session without regenerating.

## NEW FILES TALLY

| Type | Count |
|------|-------|
| Migrations | 8 |
| Models | 8 |
| Services | 4 |
| Controllers | 9 |
| Jobs | 3 |
| Broadcast Events | 2 |
| Vue Pages | 10 |
| Vue Components | 5 |
| Composables | 4 |
| Blade Views | 1 |
| Export Classes | 2 |
**Total** | **56 files** |
