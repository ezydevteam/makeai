# AI Assistant — MagicAI-style Multi-Panel UI Plan

**Date:** 2026-07-11
**Scope:** `addons/ai-assistant/` (mostly frontend; a few thin JSON endpoints)
**Reference:** 5 MagicAI screenshots in `/magicai-assistant`
**Status:** proposal

---

## 1. What the screenshots show

MagicAI's assistant is **not** a chat-only bubble like ours. It's a **multi-panel widget** with a persistent bottom tab bar (**Home · Chat · Help**), plus a branded header and footer. Panel by panel:

| # | Screenshot | Panel | Key elements |
|---|---|---|---|
| 105 | Chat (empty) | **Chat** | Header (avatar, name, call icon, ⋯ menu), legal line ("By continuing… Privacy / Terms"), greeting bubble, **social-channel row** (WhatsApp/Telegram/Facebook/Instagram), timestamp, input with **attach + emoji**, "Powered by" footer |
| 106 | Help Center list | **Help** | "Need help? Leave a message" link, **article search box**, article list (title + excerpt), bottom nav |
| 107 | Article detail | **Help** | Full article reader (title, headings, bullet list), scrollable, bottom nav |
| 108 | Send email | **Help → message** | Contact form: Email + Message + "Send email" |
| 109 | Chat + rating | **Chat** | Same chat, plus a **CSAT rating prompt** ("Tell us about your experience": Very Good / Good / Average / Low / Bad), relative timestamps ("2 hours ago") |

The three tabs: **Home** (landing — implied by the nav; not screenshotted), **Chat** (our current widget, enriched), **Help** (KB browser + email fallback).

---

## 2. The good news: most of the backend already exists

This is overwhelmingly a **frontend restructuring** job. Every new panel has backing infrastructure already in the repo:

| Panel need | Reuse | Location |
|---|---|---|
| Help article **list** | `KbArticle` (published scope: title, slug, excerpt, published_at) | `addons/public-knowledge-base/app/Models/KbArticle.php` |
| Help article **detail** | same model + `KbSearchService::getRelatedArticles()` | `KbSearchService.php:329` |
| Help **search** | `KbSearchService` (we already gate it via `KnowledgeBase` facade) | `addons/ai-assistant/app/Support/KnowledgeBase.php` |
| **Leave a message / email** | `ContactMessage` model + `ContactController::store` flow (name, email, subject, message, honeypot); admin inbox already exists | `app/Http/Controllers/ContactController.php:48`, `admin.contact.messages.index` |
| **Legal links** | `site_terms_url`, `site_privacy_url` settings | already shared |
| **Chat / rating / persistence** | everything we built last rounds (SSE, conversations, transcript, feedback + admin read path) | `addons/ai-assistant/` |

New backend is therefore small: a handful of **thin JSON endpoints** in our own controller that read `KbArticle` behind the existing `KnowledgeBase::available()` guard, one endpoint to submit a message (wrapping `ContactMessage`), and an extension to feedback for the 5-point CSAT rating.

---

## 3. Frontend restructuring (the bulk of the work)

Today the widget is chat-only: `FloatingAssistant → AssistantWindow → {Header, Messages, Input}`. We introduce a **panel shell** with tab navigation.

### 3.1 New shell
- **`AssistantShell.vue`** — replaces `AssistantWindow` as the window root. Holds the active-panel state (`home | chat | help`), renders the shared header + the `AssistantNav` bottom bar, and slots the active panel.
- **`AssistantNav.vue`** — the Home / Chat / Help tab bar (icon + label, active state). Tabs are shown only when their panel is enabled (settings-driven), so a buyer who wants chat-only gets no nav at all and the shell collapses back to today's single-panel look.
- **`AssistantFooter.vue`** — "Powered by" strip (hideable on Extended license, like the rest of the app's white-labelling).

### 3.2 Panels
- **`panels/HomePanel.vue`** — greeting, quick-action buttons ("Start a conversation", "Browse help"), and a short featured-articles list. Effectively a launcher.
- **`panels/ChatPanel.vue`** — our current chat, refactored out of `AssistantWindow`, plus: legal disclaimer line, a **social-channel row** in the greeting, relative timestamps, an **emoji picker** in the input, and the **CSAT rating card** (§3.3).
- **`panels/HelpPanel.vue`** — search box + article list; drills into **`HelpArticle.vue`** (reader + related articles + a "Was this helpful?" vote), with a "Leave a message" link to…
- **`panels/MessagePanel.vue`** — the email/contact form.

### 3.3 Chat enrichments
- **Social row**: WhatsApp / Telegram / Facebook / Instagram / website icons, from new settings; renders only for configured channels.
- **CSAT rating card**: a 5-point chip row. Reuses the feedback pipeline; stores a `satisfaction` value (1–5) distinct from the per-message thumbs (which stay).
- **Relative timestamps** and **emoji picker** (dependency-free, small emoji set) — cosmetic parity.

### 3.4 Reuse, don't rebuild
`AssistantMessages`, `AssistantMessage`, `AssistantInput`, the SSE parser (`useAssistantApi`), markdown (`useAssistantMarkdown`), and the slash-command menu all move under `ChatPanel` unchanged.

---

## 4. New backend endpoints (thin)

All under the existing route file, same throttle categories, same visibility gate (`isVisibleForUser`), and all Help endpoints behind `KnowledgeBase::available()`:

| Method | Route | Purpose | Backed by |
|---|---|---|---|
| GET | `/api/assistant/help/articles` | Paginated published article list (title, slug, excerpt) | `KbArticle` published scope |
| GET | `/api/assistant/help/articles/{slug}` | One article + related | `KbArticle` + `getRelatedArticles()` |
| GET | `/api/assistant/help/search?q=` | Article search | `KbSearchService` |
| POST | `/api/assistant/message` | Submit "leave a message" | `ContactMessage` (tagged `source: ai-assistant`) |
| POST | `/api/assistant/csat` | Submit experience rating (1–5) | extend `AiAssistantFeedback` / new `satisfaction` column |

Help endpoints return `{ articles: [...] }` shapes the widget renders directly — no coupling to the KB addon's own Inertia web routes.

---

## 5. New settings (manifest)

Grouped so the admin controls the whole experience:
- **panels**: `enable_home`, `enable_help`, `enable_message` (booleans — turn tabs on/off; all off ⇒ chat-only, today's behaviour)
- **channels**: `social_whatsapp`, `social_telegram`, `social_facebook`, `social_instagram`, `social_website` (URLs; blank ⇒ hidden)
- **legal**: `show_legal_note` (bool; text uses existing `site_terms_url` / `site_privacy_url`)
- **feedback**: `enable_csat` (bool), `csat_prompt` (string)
- **branding**: `show_powered_by` (bool; forced on for Regular license, hideable on Extended — matches the app's white-label rule)

These render automatically in the admin Settings screen (manifest-driven), with the license-/KB-aware hiding we already wired (`aiAssistantMeta`): the Help tab option is hidden when the KB addon isn't active, `show_powered_by` locked on Regular.

---

## 6. Phased delivery

- **Phase A — Shell + nav (no new features).** Introduce `AssistantShell` + `AssistantNav`, move existing chat into `ChatPanel`. Ships the tab structure with just the Chat tab. Pure refactor; behaviour unchanged when other panels are disabled. *Low risk, unlocks everything else.*
- **Phase B — Help panel.** Article list / reader / search / vote + the JSON endpoints. Highest user value — it turns the widget into a real self-serve help centre and pairs with the RAG we already ship.
- **Phase C — Message panel.** Contact form → `ContactMessage`, surfaced in the existing admin inbox. Small.
- **Phase D — Chat enrichments.** Social row, legal line, timestamps, emoji, CSAT card. Cosmetic parity; independent of A–C.
- **Phase E — Home panel.** Launcher. Lowest priority (it mostly re-surfaces Chat + Help).

Each phase is independently shippable and independently gated by a setting.

---

## 7. Decisions to confirm

1. **Match the tabbed layout, or keep our floating chat and add only the Help browser?** MagicAI's 3-tab shell is more app-like but a bigger lift and a real UX change. A lighter option: keep today's chat window and add an in-chat "Browse help" affordance (Phase B content, no shell). *Recommendation: do the shell (Phase A) — it's what makes the screenshots' UX, and it's a contained refactor.*
2. **CSAT rating**: extend the existing feedback table with a 1–5 `satisfaction`, or keep thumbs-only and skip the rating card? *Recommendation: add it — it's cheap and the admin feedback screen already exists to surface it.*
3. **"Leave a message"**: reuse the global `ContactMessage` inbox (zero new admin UI), or give the assistant its own message store + screen? *Recommendation: reuse `ContactMessage`, tagged by source.*
4. **Home tab**: build it, or ship Chat + Help only? It's the thinnest-value panel. *Recommendation: defer (Phase E) or skip.*

---

## 8. Not in scope
- Voice / call button (screenshot 105 shows a phone icon) — parity would need the voice stack; out of scope, same as the earlier plan.
- The "⋯" header menu is just clear-chat / close; folds into existing controls.
