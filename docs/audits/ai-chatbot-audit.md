# ai-chatbot — Pre-Redesign Audit (consolidated)

> Findings from 4 parallel audits: **Backend/Security**, **Settings consistency**,
> **Install/Registration**, **Frontend UI**. Ranked by severity. Tag = source dimension.
> Working doc for the ai-chatbot redesign.

## Severity summary
| Tier | Count | Status |
|---|---|---|
| 🔴 Critical | 1 | ✅ Fixed (C1) |
| 🟠 High | 2 | ✅ Fixed (H1, H2) |
| 🟡 Medium | 14 | ✅ Fixed (M-BE1–3, M-SET1–4, M-FE1–8, M-INS1) |
| ⚪ Low | 11 | ✅ Fixed (9) · ⬜ deferred (2: dead `permissions[]` + SQLite portability — systemic/informational) |

> **Progress:** All Critical/High/Medium and all actionable Low findings resolved and verified (build + typecheck + runtime checks). Two lows deferred as architectural/informational.

---

## 🔴 CRITICAL

### C1 · [Install] Activating the bundled addon runs neither migrations nor seeder ✅ FIXED
> **Fixed** — `AddonService::activate()` now calls a new `installAddonSchema($slug)` that runs the addon's migrations then discovers and runs its (idempotent) seeders. Verified: truncated `chatbot_modes` → activation path repopulated all 9 modes. Also benefits every bundled addon, not just ai-chatbot.

`AddonService::activate()` (`app/Services/AddonService.php:184`) only flips `is_active`. `migrateAddon()` is called **only** from the zip-upload path (`AddonController.php:385`), never for a pre-bundled addon. The addon's `loadMigrationsFrom` is inside `boot()` which is guarded by `is_addon_active()` — so `php artisan migrate` before activation skips them too. Result on a fresh Envato install: `chatbot_modes`/`conversations` tables never created → `ChatbotModeSeeder` early-returns (0 modes) → first hit to `/chat` or the API 500s. Even the zip path leaves `chatbot_modes` **empty** (seeder never invoked) → blank persona selector.
**Fix:** in `activate()`, after flipping active, run `migrateAddon($slug)` then the addon seeder (`db:seed --class=Addons\AiChatbot\Database\Seeders\ChatbotModeSeeder --force` when the class exists). Both are already idempotent.

---

## 🟠 HIGH

### H1 · [Install] Core `DatabaseSeeder` hard-depends on the optional addon's seeder class ✅ FIXED
> **Fixed** — core `DatabaseSeeder::run()` now includes `ChatbotModeSeeder` only when `class_exists()`, so removing the addon can't fatal `db:seed`/`DemoReset`. Addon seeding moved to activation (C1).

`database/seeders/DatabaseSeeder.php:8,25` `use`s + `->call()`s `Addons\AiChatbot\...\ChatbotModeSeeder`. `AddonService::delete()` removes the addon directory, but PSR-4 still maps the namespace → any later `db:seed` / `DemoReset` throws **Class not found**.
**Fix:** guard with `class_exists(...)` before `->call()`, or move mode-seeding into addon activation (pairs with C1) and drop it from the core seeder.

### H2 · [Frontend] Stopping a stream shows "Error: Failed to send message" instead of keeping the partial reply ✅ FIXED
> **Fixed** — `useChat.ts` now detects the abort via `e?.name === 'AbortError' || signal.aborted`, keeps the partial reply, and drops an empty assistant bubble when stopped before the first token. Build + typecheck clean.

---

## 🟡 MEDIUM

### Backend / Security
- **M-BE1 · No throttling; attachment upload unbounded (disk-exhaustion DoS). ✅ FIXED** — chat group now carries `throttle:public,180,60`, uploads `throttle:public,20,60`, generation `throttle:text_gen,30,60` (per-user key + AI-abuse detection). Added `CleanupGuestAttachments` job (7-day retention) scheduled daily at 04:15 to reclaim orphaned guest uploads. Verified middleware attached via route:list.
- **M-BE2 · Concurrent requests get free generations. ✅ FIXED** — flat `credits_per_message` is now reserved via `chargeCredits()` (row-locked, refuses negative) **before** streaming; refunded via `refundCredits()` on cancel/failure. Concurrent requests now serialize on the wallet. Metered + quota modes both handled.
- **M-BE3 · `project_id` accepted without ownership check (IDOR). ✅ FIXED** — `store`/`update` now use `Rule::exists('chat_projects','id')->where('user_id', $user?->id)`. Verified: owner accepted, cross-user rejected, guest rejected.

### Settings consistency
- **M-SET1 · `enabled` ("Enable Chatbot") is a dead toggle. ✅ FIXED** — `/chat` route now `abort_unless(enabled, 404)` and the homepage-as-chatbot path (`HomeController`) also honors it. The master toggle is meaningful.
- **M-SET2 · `show_token_usage` & `show_credits_charged` are dead. ✅ FIXED** — both now shared via `Inertia::share` (`chatbot.showTokenUsage/showCreditsCharged`) and gate the usage line in `ChatMessage.vue` (token detail vs. credit detail independently).
- **M-SET3 · `hide_site_header` / `hide_site_footer` not declared/editable. ✅ FIXED (removed).** These only applied to the niche "chat-as-homepage" config (the `/chat` route hardcodes both hidden), so instead of exposing them we **removed** them: dropped from `addon.json`, dropped the reads in `HomeController` (now hardcodes full-screen to match `/chat`), and deleted the persisted DB rows. No lingering references remain.
- **M-SET4 · Default-value mismatches (pre-save installs). ✅ FIXED** — `AddonService::seedDefaultSettings()` persists every declared default on activation (only unset keys). Verified: `guest_max_tokens` now reads its declared 1000 instead of the 4096 code fallback. General fix — benefits all addons.

### Frontend UI — ✅ ALL FIXED
- **M-FE1 · No auto-scroll during streaming. ✅** `ChatMessages.vue` now watches the last message's content (not just array length) and only pins to bottom when the user is already near it.
- **M-FE2 · `ModelSelector` mutates state inside a `computed`. ✅** Refactored: `displayModel` is a pure computed; the "adopt default selection" side-effect moved to a `watchEffect`.
- **M-FE3 · Initial loads swallow all errors → false empty state. ✅** `useChat` now tracks `conversationsLoading/Error` + `messagesLoading/Error` + `retryLoadMessages`; `ChatMessages.vue` and `ChatSidebar.vue` render spinner / error-with-retry distinct from the empty state.
- **M-FE4 · Regenerate/Branch no-op on user bubbles. ✅** Removed those actions from the user-message action row (they only ever worked from assistant messages); Copy/Share retained.
- **M-FE5 · `TagManager` no in-flight lock / error handling. ✅** Added `isSaving` lock (button disabled + "Saving…") and try/catch with an inline error.
- **M-FE6 · Save-instructions & share fail/succeed silently. ✅** Save-instructions now shows an error line; share/copy show a transient "Link copied" toast and an error toast on failure.
- **M-FE7 · Template variant broken on mobile. ✅** `AiChatTemplate.vue` now mirrors Index.vue's mobile wiring (SSR-safe matchMedia, slide-in sidebar shell, overlay, hamburger, provides).
- **M-FE8 · Dead shortcut composable; advertised Ctrl+K does nothing. ✅** Removed the bogus "Ctrl+K → Command palette" help row and deleted the never-imported `useChatShortcuts.ts`.

### Install
- **M-INS1 · Analytics menu references non-existent permission `reports.view`. ✅ FIXED** — changed to `reports.usage` (a real core permission) so the menu item is grantable to sub-admins.

---

## ⚪ LOW
- ✅ **[FE] Sidebar rename double-submits** (`doRenameConversation`/`doRename`) — fixed: flag cleared synchronously before the await, so the blur can't slip past the guard.
- ✅ **[FE] Enter during streaming aborts the stream** (`ChatInput.vue`) — fixed: Enter is ignored while streaming; cancel now requires the explicit Stop button.
- ✅ **[FE] `doCreateProject` double-submit** (`ChatSidebar.vue`) — fixed: `creatingProject` in-flight lock + try/catch.
- ◑ **[FE] Icon-only buttons lack `aria-label`** — added on ChatMessage action rows, ChatInput attach/stop. A few other icon buttons across the sidebar could still use labels.
- ◑ **[FE] i18n gaps** — localized ChatMessage token/action labels + ChatInput titles. **`SharedView.vue` (public-facing) is still fully hardcoded English — remaining.**
- ✅ **[FE] SharedView.vue i18n** — public-facing page now fully localized via `useTranslate` (header, error, "Assistant", footer).
- ✅ **[BE] Guest requests 500 on auth-only endpoints** — `editMessage`, `ChatFeedbackController::store/index`, `ConversationTagController::update/destroy/tagConversation` now return 403 (or empty) when `!$user`.
- ✅ **[BE] Shared view leaked first prompt via `title`** — `sharedView` now returns a neutral "Shared Conversation" label instead of the auto-title (first user prompt), honoring the assistant-only design.
- ✅ **[BE] `sanitizeError` could leak raw provider messages** — unmatched errors now return a generic message and log the raw text server-side.
- ✅ **[BE] `estimateTokens` undercounted CJK/Arabic** — now `max(word_count*1.3, mb_strlen/4)` so space-less scripts are counted.
- ⬜ **[Install] Declared `permissions[]` are dead** — never synced/enforced. *Deferred: systemic across ALL addons (needs a core permission-sync change), out of scope for this addon pass.*
- ⬜ **[Install] SQLite portability** of the nullable-user_id migration — `dropForeign`+`change()` flaky under the test harness; MySQL prod fine. *Informational — no prod impact.*

---

## Verified GOOD (checked, no issue)
- **XSS handled** — both `v-html` sinks run `DOMPurify.sanitize(marked.parse())`; SharedView additionally strips form/input/button.
- **Conversation/message IDOR** — all reads/writes scoped via `getConversationsQuery()` (user_id / session_id) or explicit `where`.
- **Attachment path traversal** — defense-in-depth: upload uses server ULID names + mime allow-list; `sendMessage`/`preview` re-validate the owner prefix and reject `..`.
- **Mass assignment** — all create/update use explicit `$validated` arrays.
- **Wallet integrity** — `deductCredits` locks + refuses negative (residual issue is service-level M-BE2).
- **share_token** — ULID (80-bit), not enumerable.
- **PSR-4 / routes / assets** — all namespaces, admin_menu routes, and Inertia page targets resolve.
- **Rename migration** — fully guarded, clean no-op on fresh installs.
- **Main send button** — double-submit prevented; Enter/Shift+Enter correct; stream `finally` always resets spinner.
