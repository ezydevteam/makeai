# MakeAI — New Addon Proposals (High Attach-Rate Upsells)

> Proposal doc for the next wave of paid addons to sell **alongside the core** on
> CodeCanyon/Envato. Goal: high *attach rate* — addons a buyer feels they **must** add
> to make the core viable for their business. Working document — not a shipped artifact.
>
> The existing `plan.md` (Extensions catalog) is a **different** doc and was left untouched.

---

## 0. Why these, and how they were chosen

Selection criteria (an addon must hit at least 3):

1. **Fills a real gap** — not already in core or an existing addon.
2. **High CodeCanyon demand** — a category buyers actively search for.
3. **Raises buyer LTV** — either unlocks a new customer segment (agencies, stores) or a
   recurring pain (originality, omnichannel).
4. **Reuses existing infra** — credits, `AiService`, queue/jobs, transcription, RAG,
   admin-settings scaffolding — so build cost stays low.
5. **Clean architectural fit** — ships as a standard addon (`addon.json` manifest +
   `AddonServiceProvider` + scoped routes/migrations), no core forks.

### What the core + current addons already cover (do NOT rebuild)
- **Core:** AI text tools, tool **chains** & **collections**, playground, RAG tools,
  **image generation** (`image_generator`, `AiService::generateImage`), embeds/BYOK,
  credits & billing (Stripe/PayPal/Paddle/bank), affiliate, blog, support tickets, ads.
- **Addons:** Assistant, Chatbot, Image **Editor**, Content **Repurposer**, Video Creator,
  Voiceover Studio, Public Knowledge Base, Social Scheduler.

### Confirmed gaps (verified absent in `app/`, `config/`, `routes/`, `addons/`)
Presentations · team/agency workspaces · AI humanizer · conversational WhatsApp/Telegram ·
e-commerce bulk content · meeting notes.

---

## 1. The proposed catalog (ranked by attach potential)

| # | Addon | Slug | Tier | Attach driver | Effort |
|---|-------|------|------|---------------|--------|
| 1 | AI Presentation Studio | `ai-presentation` | A | "One-click decks" is a top-searched category, zero overlap | M |
| 2 | Agency Workspaces & White-Label | `agency-suite` | A | Unlocks the **reseller/agency** buyer — biggest LTV lever | L |
| 3 | AI Humanizer & Originality Guard | `ai-humanizer` | A | Every content buyer fears AI-detection; recurring use | S |
| 4 | Omnichannel AI Agents (WhatsApp + Telegram) | `ai-omnichannel` | B | Extends Chatbot+KB brain to the channels clients live in | M |
| 5 | E-Commerce Content Engine | `ecommerce-content` | B | Bulk product copy → store owners, an entire new segment | M |
| 6 | AI Meeting & Call Notetaker | `ai-meeting-notes` | B | Reuses Voiceover transcription; universal B2B need | S |

Effort key: **S** ≈ 3–5 dev-days · **M** ≈ 1–2 wks · **L** ≈ 3–4 wks.

---

## 2. Addon specs

Each follows the established contract: `addon.json` (name/slug/version, `admin_menu`,
`settings[]`, `permissions[]`, `requires_license`), an `AddonServiceProvider` guarded by
`is_addon_active()`, scoped `routes/web.php`, own migrations, service classes registered as
singletons, credits via `addon_setting()` + the core credit ledger, and per-user gating via
the `addon.*.use` permission + `AddonEnabled` middleware.

### 1 — AI Presentation Studio (`ai-presentation`) · Tier A · Effort M

**What:** Type a topic (or paste an outline / a Repurposer job / a KB article) → get a full
slide deck: titled slides, bullet content, speaker notes, and AI-generated visuals per slide.
Pick a theme, edit inline, export **PPTX + PDF**, or present in-browser.

**Why buyers must-buy:** "AI presentation maker" is one of the highest-volume AI-tool searches
and is completely absent from the core. It also creates a natural **cross-sell loop** with
Repurposer (video → deck) and KB (docs → deck).

- **Reuses:** `AiService` (text), `AiService::generateImage` (slide art), credit ledger, queue.
- **New tables:** `presentations`, `presentation_slides`.
- **Services:** `DeckOutlineService` (topic → structured outline JSON), `DeckRenderService`
  (PPTX via a PhpPresentation lib / PDF via existing `mpdf`).
- **Credits:** `credits_per_deck` (default 25), optional `credits_per_slide_image`.
- **Export:** PPTX download + reuse core PDF pipeline; "present mode" is a Vue route.
- **Risk:** PPTX generation lib is the only new dependency — spike this first.

### 2 — Agency Workspaces & White-Label (`agency-suite`) · Tier A · Effort L

**What:** Let a customer create **sub-workspaces** (clients/teams) under their account, each
with its own members, a **shared or allocated credit pool**, isolated history, and
**per-workspace branding** (logo, name, custom domain/subdomain, hidden "MakeAI" mentions).

**Why buyers must-buy:** This converts the product from "a tool I use" into "a business I
resell." Agencies and SaaS operators — the buyers with real budget — will not purchase the
core *without* it. Highest LTV lever in the catalog.

- **Reuses:** existing auth/roles/permissions, credit ledger (adds a pool layer), billing.
- **New tables:** `workspaces`, `workspace_members`, `workspace_credit_allocations`,
  `workspace_brandings`.
- **Services:** `WorkspaceContext` (resolves active workspace per request, scopes queries),
  `CreditPoolService` (allocate/deduct against pool then member), `BrandingResolver`
  (overrides logo/name/theme in Inertia shared props + mail).
- **Cross-cutting:** a global scope + middleware so history/tools/credits filter by workspace;
  guard other addons behind the resolved workspace where relevant.
- **Settings:** max workspaces per plan, allow custom domains, white-label toggle (gate behind
  license tier), default per-workspace credit cap.
- **Risk:** touches many read paths (scoping). Ship behind a feature flag; add a data-isolation
  test suite. Biggest build — plan it as its own milestone.

### 3 — AI Humanizer & Originality Guard (`ai-humanizer`) · Tier A · Effort S

**What:** Paste or import any text → **humanize** it (multiple strength levels + tone), then
show a **built-in AI-detection score** and an optional **plagiarism** pre-check before the user
publishes. One panel: rewrite ⇄ re-score until "green."

**Why buyers must-buy:** Fear of AI-detection is the #1 objection content buyers have. The core
already exposes `ai_detector` and `plagiarism` as *check* extensions but has **no humanizer** —
this closes the loop and is a daily-use tool, driving credit consumption.

- **Reuses:** `AiService` (rewrite passes), existing `ai_detector` + `plagiarism` external-tool
  services for scoring (call them; don't reimplement), credit ledger.
- **New tables:** `humanizer_jobs` (optional — could be stateless with history entries).
- **Services:** `HumanizeService` (multi-pass rewrite w/ configurable strength presets).
- **Credits:** `credits_per_humanize` scaled by word count; re-score reuses the extension cost.
- **Positioning note:** Frame as *originality assistance / plagiarism avoidance* (legitimate
  commercial category), not "detector evasion." Keep marketing copy defensible.

### 4 — Omnichannel AI Agents: WhatsApp + Telegram (`ai-omnichannel`) · Tier B · Effort M

**What:** Deploy the existing **Chatbot + Knowledge Base** brain to **WhatsApp Cloud API** and
**Telegram** bots. Inbound messages → RAG-grounded AI replies; **human-handoff** to the admin
inbox; per-channel greeting, office hours, and lead capture.

**Why buyers must-buy:** The core chatbot lives only on the website. Clients want AI answering
where their customers actually are. Strong, obvious complement to `ai-chatbot` +
`public-knowledge-base` (sell as a bundle).

- **Reuses:** chatbot conversation engine, KB/RAG retrieval, existing `SocialService` patterns.
- **New tables:** `channel_connections` (provider creds/webhook secrets), `channel_conversations`.
- **Services:** `WhatsAppGateway`, `TelegramGateway` (send/receive), `InboundRouter`
  (dedupe → resolve conversation → chatbot reply → optional handoff).
- **Routes:** signed webhook endpoints per provider (verify signatures; idempotent).
- **Credits:** `credits_per_ai_reply`; inbound routing is free.
- **Risk:** webhook verification + WhatsApp template/24h-window rules — document setup clearly
  for buyers (a common Envato support pain if under-documented).

### 5 — E-Commerce Content Engine (`ecommerce-content`) · Tier B · Effort M

**What:** Connect **WooCommerce** (REST) / **Shopify** (Admin API) → pull products → **bulk
generate** SEO titles, descriptions, bullet features, meta tags, and image **alt-text**, in the
store's language → review → **push back** to the store. Scheduled re-generation for new products.

**Why buyers must-buy:** Opens an entirely new buyer segment (store owners/dropshippers) who
would otherwise skip the product. Bulk = heavy, sticky credit consumption.

- **Reuses:** `AiService`, queue/bulk-job pattern (mirror Repurposer's `ProcessBulkRepurposeJob`),
  credit ledger, translation extension.
- **New tables:** `store_connections`, `product_content_jobs`, `product_content_items`.
- **Services:** `WooConnector`, `ShopifyConnector` (fetch/update), `ProductCopyService`.
- **Credits:** `credits_per_product`; batch caps via settings (mirror `max_bulk_items`).
- **Risk:** API pagination + write-back throttling; make write-back opt-in per item (preview
  first) to avoid overwriting live catalogs — align with the core "soft delete / confirm" rule.

### 6 — AI Meeting & Call Notetaker (`ai-meeting-notes`) · Tier B · Effort S

**What:** Upload (or in-browser record) a meeting/call → **transcript** with speaker labels →
**summary**, decisions, and **action items** → export to PDF/DOCX or email. Optional CRM/webhook
push.

**Why buyers must-buy:** Near-universal B2B need and a very low build cost because it **reuses
the Voiceover addon's transcription pipeline** almost wholesale. High-margin filler that rounds
out the suite.

- **Reuses:** Voiceover `TranscribeAudio` job / transcription provider, `mpdf` (PDF), `docx` lib
  already in `package.json`, credit ledger.
- **New tables:** `meeting_notes`, `meeting_action_items`.
- **Services:** `MeetingSummaryService` (transcript → structured minutes JSON).
- **Credits:** `credits_per_minute` for transcription + `credits_per_summary`.
- **Dependency note:** cleanest if it can *depend on* the transcription service. Either declare a
  soft dependency on `ai-voiceover` or extract a shared `TranscriptionService` both consume.

---

## 3. Suggested bundling & pricing (Envato)

- **"Growth Bundle"** = Presentation + Humanizer + Meeting Notes → productivity buyers.
- **"Agency Bundle"** = Agency Suite + Omnichannel + E-Commerce → reseller/agency buyers (premium).
- Price single addons in line with existing addons; discount the bundles to lift attach rate.
- Each addon keeps `requires_license: 1` and its own `envato_item_id` (null until listed).

---

## 4. Build roadmap

**Phase 1 — Quick, high-attach wins (validate demand fast)**
1. `ai-humanizer` (S) — smallest build, broad appeal, reuses detector/plagiarism extensions.
2. `ai-meeting-notes` (S) — reuses Voiceover transcription.
3. `ai-presentation` (M) — spike the PPTX lib first; flagship of the Growth bundle.

**Phase 2 — Segment expanders**
4. `ecommerce-content` (M) — new buyer segment.
5. `ai-omnichannel` (M) — bundle with Chatbot/KB.

**Phase 3 — LTV lever (own milestone)**
6. `agency-suite` (L) — build behind a feature flag with a dedicated workspace-isolation test
   suite; do not rush, it touches core read paths.

---

## 5. Shared prerequisites (do once, before Phase 1)

- [ ] Confirm the addon scaffold generator / copy an existing addon (`ai-repurposer`) as the
      template for manifest + provider + admin settings Vue.
- [ ] Decide the **shared transcription** story (extract `TranscriptionService` vs. soft-depend
      on `ai-voiceover`) — blocks #6 and simplifies #4.
- [ ] Verify the credit ledger exposes a clean `deduct(user, amount, meta)` the new services can
      call (Repurposer already does this — reuse that path).
- [ ] Real Ed25519 license key must be in place before packaging any of these (see the existing
      license-packaging blocker note).

---

### Open questions for the owner
1. Which buyer do we prioritize — **productivity users** (Phase 1 first) or **agencies/resellers**
   (pull `agency-suite` forward)? It's the biggest scoping decision.
2. Is a new dependency (PPTX generator for #1) acceptable, or should Presentations export
   PDF-only in v1 to avoid it?
3. Should Humanizer be marketed standalone or bundled only, given the positioning sensitivity?
