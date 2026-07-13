# AI Assistant — Improvement Plan

**Status:** proposal, awaiting decisions (see §7)
**Date:** 2026-07-11
**Scope:** `addons/ai-assistant/`
**Benchmark:** MagicAI (LiquidThemes), demo.magicproject.ai + docs.magicproject.ai

---

## 1. Verdict

Our assistant is a **floating widget wrapped around a single stateless text stream**. It streams tokens and it has a canned-response rules engine. That is essentially all it does.

It has no conversation persistence (history is a `localStorage` blob), no grounding on our own content, no tool-calling, no slash commands (despite the manifest advertising them), no per-plan monetization, and a handful of access-control and billing defects that are live today.

Meanwhile, **the hard parts are already built and sitting unused two directories away**: `ai-chatbot` has SSE, DB-backed conversations, attachments, per-plan gating, and a feedback read path; `public-knowledge-base` has a complete, working RAG pipeline. The fastest path to a competitive assistant is mostly *integration*, not invention.

**A note on evidence.** The MagicAI benchmark below is vendor-sourced — their live demo, their docs, and their CodeCanyon listing. No independent hands-on review of their assistant exists (no G2/Trustpilot listing; the "reviews" in circulation are marketing reproductions). Their changelog also names models I could not verify against any provider's own announcements. Treat their feature list as *claimed* capability. Our own audit, by contrast, is read from our source and spot-verified.

---

## 2. Where we stand vs MagicAI

| Capability | MagicAI | Us | Gap |
|---|---|---|---|
| Streaming chat | Yes | Yes (plaintext sentinels, not SSE) | Minor |
| Conversation persistence + history | Yes (folders, pinning, memory) | **None** — localStorage | **Critical** |
| RAG on own docs/site/files | Yes (External Chatbot: files, URLs, KB) | **None** | **Critical** |
| Tool-calling / real actions | Yes (visual AI Agent: Gmail, Telegram, reports, branching) | **None** | **Critical** |
| Slash commands / Skills | Yes (`/skill-creator`, importable `.skill` files) | **Advertised, does not exist** | **Critical** |
| Multiple assistants / personas | Yes (Chat Templates: name, avatar, instructions) | One global system prompt | High |
| Per-plan gating + credit cost | Yes (plan-gated models, paid extensions, shared credit pool) | **None** | High |
| Guest access | Yes (temporary chat, guest daily limit) | **Blocked** (401) despite `show_to: all` | High |
| File upload | Yes (document chat) | Wired, but text round-trips through the browser | Medium |
| Voice (STT/TTS/realtime/cloning) | Yes — deep stack | None | Medium |
| Multi-provider consensus ("AI Council") | Yes | None | Low (novelty) |
| Deep Research mode w/ citations | Yes | None | Medium |
| Admin analytics / feedback review | Not documented | Table exists, **no read path** | Medium |
| Conversation export | Yes (PDF/Word/Text) | None | Low |
| Model selection by user | Plan-gated | Admin-fixed only | Low |

The three that actually decide whether we're competitive: **persistence, grounding, and actions.** Everything else is trim.

---

## 3. Phase 0 — Correctness first (blocking)

These are defects, not features. Each is verified against source. Shipping features on top of these would be building on sand, and two of them are active money/security leaks.

| # | Defect | Evidence | Why it matters |
|---|---|---|---|
| 0.1 | **`show_to: pro_only` does not check the user's plan.** `isProAvailable()` returns `is_extended_license() && settings('subscriptions_enabled')` — a *global install flag*. | `app/Helpers/license.php:44-47`; `AiAssistantService::isVisibleForUser()` | Every free user passes the "pro only" gate on any Extended-license install. Silent monetization leak; the setting does the opposite of what the admin believes. |
| 0.2 | **Widget renders regardless of `enabled` / `show_to` / `admin_enabled`.** The loader only checks `!settings \|\| isChatPage`. | `AiAssistantLoader.vue:18-22`; `AddonServiceProvider.php:25-53` shares the prop unconditionally | Turning the addon "off" on the frontend doesn't turn it off. Guests see a bubble that 401s into "Something went wrong." |
| 0.3 | **Admin chat never calls `TokenGuard::before()`.** | `AiAssistantController.php:234-285` vs frontend `:97` | Bypasses the `global_daily_ai_budget_usd` kill-switch — the operator's last defense against a runaway bill. `admin_enabled` defaults to **true**, so this is on out of the box. |
| 0.4 | **Frontend billing is conditional on the provider returning usage stats.** `TokenGuard::after()` runs only `if (isset($chunk['input_tokens']))`. | `AiAssistantController.php:139-147` | If the stream aborts or the provider omits usage, **the request is free**. |
| 0.5 | **Daily limit uses the `Redis` facade directly and fails open.** | `AiAssistantService.php:154-186` (`return true; // allow on Redis failure`) | This install is `CACHE_STORE=database`. Any customer without Redis gets **no limit at all**, silently — an uncapped AI endpoint behind only `throttle:30,1`. |
| 0.6 | **Canned-rule replies skip the limit check** but still increment the counter. | `AiAssistantController.php:50-65` (returns before `checkDailyLimit()` at `:67`) | Quota consumed without being enforced. |
| 0.7 | **The manifest advertises slash commands that do not exist.** | `addon.json:5` — *"streaming chat, admin slash commands, and feedback"*; zero implementing code | On an Envato-packaged product this is a refund / review-rejection vector. Either build it (§5) or cut the claim now. |
| 0.8 | **The one test is broken** — calls `route('addon.ai.user.chat')`, which isn't a registered name (real: `addon.ai-assistant.chat`). | `tests/Feature/ChatTest.php:17` | It throws `RouteNotFoundException` — it has never passed. Effective coverage: **zero**. |
| 0.9 | **`ERROR:` in model output truncates the reply.** The client treats any `\nERROR:` in the buffer as fatal. Also, `daily_limit_reached` is shown to users as raw text, and server-side `addslashes()` mangles apostrophes in error copy. | `AssistantWindow.vue:203-215`; controller `:68-74, :100` | Corrupts legitimate answers; user-visible jank. Fixed for free by moving to SSE (§4.1). |

**Estimate:** ~2–3 days. Do this before anything in §4.

---

## 4. Phase 1 — Foundation

### 4.1 Real SSE, and route all AI calls through `AiService`
Replace the hand-rolled `READY` / `ERROR:` plaintext protocol with typed SSE frames (`token` / `kb_sources` / `done` / `error`), copying `ai-chatbot`'s wire format (`ChatController.php:460, 814, 841-846`).

Then delete the duplicated billing logic: `AiService::stream()` (`app/Services/AI/AiService.php:416-471`) *already* resolves the provider, calls `TokenGuard::before()`, streams, accumulates usage, calls `after()`, and calls `recordFailure()` on abort. The controller currently hand-rolls all of this and gets it wrong (0.3, 0.4). Note `AiService` is **already injected into both the controller and the service and never used** — the wiring is there, unused.

This one change closes 0.3, 0.4, and 0.9, and deletes ~60 lines per chat method.

Also adopt `ProviderRegistry::resolveWithFailover()` (`:79`) instead of the naive `resolve()`, and add an `AbortController` stop button (`ai-chatbot`: `useChat.ts:146`).

### 4.2 Persist conversations
Add `assistant_conversations` + `assistant_messages`, mirroring `ai-chatbot`'s `Conversation` / `ConversationMessage`. Stop trusting client-supplied history (today the browser sends its own transcript back, `max:20`, fully spoofable).

Also fix a quality bug this exposes: frontend chat **flattens multi-turn history into a single string** (`"User: …\nAssistant: …"` as one user message, `AssistantWindow.vue:449-463`), while admin chat correctly builds a role-tagged `messages[]` (`:218-222`). The frontend is getting measurably worse answers than admin for no reason.

This unlocks history, cross-device continuity, analytics, and makes the feedback table meaningful (today it stores a `message_hash` with no message to join to).

### 4.3 Guest access
`show_to: all` is a lie — the route sits behind `auth`. Either honor it (session-keyed conversations, as `ai-chatbot` does via its `ChatGuestAccess` middleware and nullable `user_id`) or remove the option. Guest chat is a real acquisition surface and MagicAI leads with it ("Temporary Chat — Login to save your chat history").

**Estimate:** ~1 week.

---

## 5. Phase 2 — The three things that close the gap

### 5.1 Grounding (RAG) — *highest leverage, lowest cost*
A support assistant that can't answer "how do I cancel my subscription" from our own docs is a generic chatbot with our logo on it.

**We do not need to build this.** `addons/public-knowledge-base` has a complete pipeline: `KbSearchService::getRelevantContext(query, topK)` (`:252`) returns ranked chunks without generating an answer — exactly the injection point we want. Ingestion (`IngestKbArticle` job, `IngestAllKbArticles` command), embeddings (`kb_embeddings`), and cosine search are all done.

Cross-addon integration is already a solved pattern: copy `Addons\AiChatbot\Support\KnowledgeBase` (`:15-32`), a small facade whose `available()` guard safely tests for the KB addon. Then emit retrieved sources as a `kb_sources` SSE frame and render citations.

⚠️ **Do not** use `AiService::answerWithKnowledgeBase()` / `searchKnowledgeBase()` (`:753, :768`) — they depend on `KnowledgeBaseSearchService`, which **is not bound in any service provider** and currently throws *"Knowledge base search is not yet available."*

**Estimate:** 2–3 days. This is the single best ratio of competitive value to effort in the whole plan.

### 5.2 Tools / actions — *the real differentiator*
This is what separates an assistant from a chatbot, and it's MagicAI's strongest card (their visual "AI Agent" builder). It's also where we can plausibly *beat* them, because we control the platform: an assistant that can actually **do things in the app** — look up the user's credit balance, their subscription, their recent generations; navigate them to the right page; draft content into a tool.

Reality check: `AiDriverInterface` (`app/Services/AI/Contracts/AiDriverInterface.php:22-29`) **has no `tools` parameter** — native function-calling is not plumbed through our provider abstraction. `AgentService` exists but is *prompt-emulated* JSON tool use (`:70`), not native calling.

So this needs real work, in order:
1. Extend `AiDriverInterface` + the OpenAI/Anthropic/OpenRouter adapters to pass `tools` and surface `tool_calls`.
2. Define a small, safe, **read-mostly** tool registry (account status, credits, plan, recent history, KB search, "navigate to X").
3. Gate every write-capable tool behind explicit confirmation.

**Estimate:** 2–3 weeks. Biggest item in the plan. Recommend a read-only v1.

### 5.3 Slash commands
Currently advertised and absent (0.7). A modest, honest version — `/help`, `/clear`, `/docs <query>`, plus admin `/stats`, `/users` — is a few days' work once the tool registry (5.2) exists, and makes the manifest true. Ship 0.7's copy fix now; ship the feature here.

---

## 6. Phase 3 — Monetization & admin

- **Per-plan gating.** Copy `ai-chatbot`'s `getPlanSetting()` (`ChatController.php:1046-1066`) → `plan_{slug}_{key}` / `free_{key}` settings for message caps, token caps, history depth, model access. Today the assistant has *no* per-plan controls at all.
- **Rolling-window limits** (5h / weekly / monthly) instead of one crude daily counter — `ai-chatbot` already has them (`:482, :507, :532`).
- **Feedback read path.** `ai_assistant_feedback` is write-only; nothing in the repo reads it. Also, **admin thumbs-up/down always fails silently** — `AssistantMessage.vue:62` POSTs to a route behind the *user* `auth` guard, 401s, and the error is swallowed by `.catch(() => {})`. Fix the guard, add an admin review screen.
- **Content-safety gate** before credits are spent (`ai-chatbot`: `ChatController.php:573-581`). The assistant has none.
- Respect `credit_quota_mode()` in every charge path (per the existing `addon-credit-mode-compatibility` constraint — no raw `increment('credits')`).

---

## 7. Decisions I need from you

1. **Scope of ambition.** Three coherent stopping points:
   - **(a) Make it honest and safe** — Phase 0 only. ~3 days. Fixes the leaks, cuts the false manifest claim. No new features.
   - **(b) Make it competitive** — Phases 0–2 minus tools. ~2 weeks. Persistence + RAG + SSE. This is the best value-per-week, and RAG alone changes what the product *is*.
   - **(c) Make it a differentiator** — add 5.2 tools. +2–3 weeks, and it means touching the provider abstraction.

   **My recommendation: (b) now, (c) as a follow-up.** Tool-calling is the right long-term bet, but it needs the SSE + persistence foundation under it first, and it shouldn't block the RAG win.

2. **Guest chat — yes or no?** It's an acquisition surface and MagicAI leads with it, but it's also an abuse surface that needs the limiter fixed first (0.5). Currently `show_to: all` silently doesn't work.

3. **Slash commands: build or retract?** If we're packaging for Envato soon, the manifest claim must not ship as-is either way.

4. **Do we merge with `ai-chatbot`?** Worth naming the elephant: `ai-chatbot` already has SSE, conversations, attachments, plan gating, modes, projects, tags, sharing, export, and analytics. Much of this plan is "make ai-assistant more like ai-chatbot." A serious alternative is to make the assistant a *thin embedded surface over the ai-chatbot engine* rather than a parallel implementation. That would collapse most of Phases 1 and 3 into integration work — but it couples two addons that are currently licensed and sold separately, which may be exactly why they're separate. **This is a product call, not a technical one, and I'd want your read before assuming either way.**

---

## 8. Explicitly not proposed

- **Multi-provider consensus ("AI Council").** Novel, demoable, expensive per message, and thin on real user value. Skip.
- **Voice stack.** MagicAI is deep here (STT, realtime, cloning, 3 TTS vendors). Matching it is a project unto itself and unrelated to what an *assistant* is for. Note we already have an `ai-voiceover` addon — if voice matters, that's the place, not here.
- **`PromptBuilder`** for assistant chat — it's `AiTool`-template-oriented (`app/Services/AI/PromptBuilder.php:27`) and not a fit. Don't force it.
