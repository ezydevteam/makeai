# AI Image Pro — Addon Plan

**Status:** ✅ BUILT (2026-07-12) — phases 0–5 implemented, uncommitted. See §13 for what shipped vs. what's left.
**Date:** 2026-07-12
**New addon:** `addons/ai-image-pro/` (slug `ai-image-pro`)
**Supersedes:** `addons/ai-image-editor/`
**Benchmark:** manus.im/tools/ai-image-generator — one surface where you *generate*, then keep working on the result (upscale, cut out the background, resize, export) without leaving the page.

---

## 1. Verdict

**MakeAI cannot generate an image today.** `AiService::generateImage()` exists at `app/Services/AI/AiService.php:579` — moderation gate, media billing, usage logging, all correct — and **nothing in the entire codebase calls it**. No route, no controller, no tool. It is dead code.

The `ai-image-editor` addon is the other half of a product that was never finished: it edits an image you already have, but the only ways to get an image *into* it are (a) upload a file, or (b) pass `?image={id}` — and that second path is broken (see §2). It has no library, no gallery, no generation, and its "one session per user, delete the last one" model means the previous image is destroyed the moment you open a new one.

So this is not a redesign. **It is the missing half of the product, plus a home for the half that already exists.** The provider clients, the Fabric.js mask canvas, and the queue/credit/refund plumbing in `ai-image-editor` are good and get lifted over largely intact — that is where the "UI is looking good" comes from and we keep it. What gets built new is generation, a real image library, and the free local toolbox (resize, crop, compress, convert) that makes the addon feel like a tool suite instead of a single-purpose editor.

**Decisions locked (from you):**
1. `ai-image-pro` **supersedes** `ai-image-editor` — one product, one set of provider keys, one credit table. The old addon is deprecated and its data imported.
2. Generation bills through the **core media pipeline** (`TokenGuard::beforeMedia` / `afterMedia`, `AiModel` rows, real-USD-cost × markup, AI usage logs). The addon only ships its own clients for models `laravel/ai` cannot reach.

---

## 2. Audit — what exists, what's broken

| Thing | Where | State |
|---|---|---|
| Text→image generation | `AiService::generateImage()` (`app/Services/AI/AiService.php:579`) | **Works, never called.** Dead code. |
| Image providers in `laravel/ai` | `vendor/laravel/ai/src/Providers/` | OpenAI, Azure OpenAI, Gemini, Bedrock, OpenRouter, xAI implement `ImageProvider`. **Supports `attachments`** (`Concerns/GeneratesImages.php:21`) → image-to-image and prompt-editing are reachable. Core's `generateImage()` does **not** pass them. |
| Media billing | `TokenGuard::beforeMedia` (`:61`), `afterMedia` (`:276`) | Works. Per-unit, credits derived from `AiModel.meta.cost_per_unit` × markup, falls back to `config('ai.media_costs.image')` = $0.04. Quota/metered aware. |
| Edit ops | `ai-image-editor` | inpaint, outpaint, bg_remove, upscale, style_transfer, object_remove (provider) + color_correction, text_overlay (GD). All working. |
| Provider clients | `ai-image-editor/app/Services/Providers/` | Stability, Replicate, Remove.bg, Clipdrop. Reusable as-is. |
| Mask canvas | `ai-image-editor/.../User/Editor.vue` (801 lines, Fabric.js) | Good. Becomes a component, not a page. |
| Credit correctness | `ApplyImageEdit::refundCredits()` | Already mode-aware (`deduct_credits` / `$user->refundCredits`). Copy this pattern verbatim. |
| Session model | `IeSession` | **One row per user.** `ImageEditorController::show()` runs `IeSession::where('user_id',…)->delete()` before creating a new one — opening a second image cascade-deletes the first image's entire edit history. |
| Library | `ApplyImageEdit::saveToLibrary()` | Writes a JSON blob into `documents.content` with `tool_slug='image-editor'`. No dimensions, no thumbnails, no favorites, no folders. `documents` is a *text* table (`longText content`, `word_count`). |
| `?image={id}` entry | `ImageEditorController::show()` | **Broken.** Queries `documents.ulid`; the `documents` table has no `ulid` column (`database/migrations/2026_05_17_000002_…php:11`). Unknown-column SQL error. |
| `makeai.image.card.actions` hook | Registered in the editor's `AddonServiceProvider` | **Never dispatched.** Nothing in core fires it. Dead hook. |
| Sidebar entry | `resources/themes/default/js/Layouts/UserDashboardLayout.vue:205` | Exists, gated on the `imageEditor` Inertia share. Cut over to `imagePro`. |
| Route catch-all | `routes/web.php:432` | `page.show` negative lookahead lists `image-editor`. **Any new route prefix must be added here** or it gets swallowed by the CMS page router. |
| Image toolkit | — | **GD only.** No Imagick, no Intervention. WebP: yes. AVIF: feature-detect (`function_exists('imageavif')`). |
| Blueprint addon | `addons/ai-video-creator/` | Projects/renders/folders, provider clients, poll job, cleanup job, share page, storage limits. **This is the structure to mirror**, not the editor's. |

---

## 3. Product scope

Three tiers, deliberately separated because they bill and behave differently:

**A. Generate** — the manus-style front door.
- Text→image, 1/2/4 variants, aspect-ratio pills (1:1, 16:9, 9:16, 4:3, 3:2), quality, style presets.
- Advanced: negative prompt, seed (+ "reuse seed"), reference image (image-to-image), strength.
- Prompt-edit ("change the jacket to red") on an existing asset via `attachments`.
- Variations of any asset in the library.
- Prompt enhancer — one cheap LLM call through the existing chat pipeline to expand a terse prompt.

**B. Provider tools** — queued, cost credits, use the ported clients.
- Remove background · Replace background (cut out → generate a new one)
- Upscale 2× / 4×
- Erase / inpaint (mask) · Object remove (mask)
- Expand / outpaint (canvas grow)
- Style transfer

**C. Local tools** — GD, synchronous, **0 credits**, no queue. This is the tier that makes it a "tool suite" rather than a paywalled editor, and it's the tier manus leans on for top-of-funnel.
- Resize (px or %), Downscale, Crop (freeform + social presets: IG 1080², YT thumb 1280×720, OG 1200×630, X header…)
- Rotate / flip
- Compress (quality slider, or "get under N KB" binary search)
- Convert (PNG ⇄ JPG ⇄ WebP, AVIF when the build supports it)
- Watermark, text overlay, color correction (ported)

Everything in B and C is callable **from the result of A** — that chaining is the whole point. A generated image lands in the grid with hover actions (Upscale · Remove BG · Edit · Variations · Download), and each action produces a *new asset with a parent link*, so the lineage is never lost.

---

## 4. Architecture

```
addons/ai-image-pro/
  addon.json                       # slug, settings, permissions, conflicts:["ai-image-editor"]
  AddonServiceProvider.php         # bindings, routes, migrations, Inertia::share('imagePro')
  app/
    Http/Controllers/
      User/StudioController.php    # generate surface + inline ops
      User/LibraryController.php   # gallery, folders, favorites, bulk
      User/AssetController.php     # show/download/delete/share
      User/ToolController.php      # local (GD) ops — synchronous
      Admin/ImageProAdminController.php     # overview/analytics
      Admin/ImageProSettingsController.php  # settings
    Http/Requests/                 # GenerateRequest, OperationRequest, LocalToolRequest
    Models/
      AipAsset.php  AipJob.php  AipFolder.php  AipPreset.php
    Services/
      OperationRegistry.php        # ← single source of truth for every op
      ImageProService.php          # dispatch: registry → engine
      GenerationService.php        # core media pipeline (TokenGuard + AiManager)
      LocalImageService.php        # GD: resize/crop/compress/convert/rotate/watermark/text/color
      AssetService.php             # persist, thumbnail, dimensions, lineage, dedupe
      CreditService.php            # flat-rate charge/refund for tier-B ops
      Providers/                   # ported: Stability, Replicate, RemoveBg, Clipdrop
                                   # new: FluxClient (fal/Replicate), IdeogramClient
    Jobs/
      RunImageJob.php              # tier A+B (queue: media)
      PollProviderJob.php          # async providers (Replicate predictions)
      CleanupExpiredAssets.php     # retention
    Console/Commands/
      ImportImageEditorData.php    # ie_edits/ie_sessions → aip_assets
  database/migrations/  (4)
  database/seeders/ImageProSeeder.php   # style presets, AiModel image rows, permissions
  resources/js/
    Pages/User/{Studio,Library}.vue
    Pages/Admin/{Settings,Overview}.vue
    Components/…                   # see §7
  routes/web.php
  tests/Feature/…
```

**Namespace:** `Addons\AiImagePro` · **Route prefix:** `/ai-image` · **Route names:** `addon.aip.user.*`, `addon.aip.admin.*` · **Permissions:** `addon.aip.use`, `addon.aip.settings` · **Table prefix:** `aip_` · **Queue:** `media` · **Disk:** `public`.

### 4.1 The Operation Registry

Every op is declared once, in `OperationRegistry`, and everything else (UI availability, credit cost, validation, provider choice, job routing) reads from it. This is the fix for the editor's current shape, where the same eight-way `match` is copy-pasted across the service, the controller, and the service provider.

```php
// OperationRegistry::all() returns, per key:
[
  'upscale' => [
    'label'     => 'Upscale',
    'icon'      => 'ti ti-arrows-maximize',
    'tier'      => 'provider',            // generate | provider | local
    'engine'    => 'replicate',           // resolved from settings
    'inputs'    => ['image'],             // image | mask | prompt | reference | text
    'billing'   => 'flat',                // 'media' (TokenGuard) | 'flat' (setting) | 'free'
    'setting'   => 'credits_upscale',
    'async'     => true,
    'available' => fn () => (bool) addon_setting('ai-image-pro', 'replicate_api_key'),
  ],
  'resize' => [
    'tier' => 'local', 'engine' => 'gd', 'inputs' => ['image'],
    'billing' => 'free', 'async' => false, 'available' => fn () => true,
  ],
  // …
]
```

The Studio page is rendered from `OperationRegistry::forUser()` → each tool tile knows its own cost, availability, and required inputs. Adding an op later = one array entry + one handler.

### 4.2 Generation path (tier A)

```
StudioController::generate()
  → moderation gate on the prompt (ContentModerationService, existing)
  → TokenGuard::beforeMedia($user, 'image', $model, units: $count)   // pre-flight, throws on insufficient
  → AipJob::create(status: queued)
  → RunImageJob → GenerationService::generate()
       → app(AiManager::class)->imageProvider($provider)->image(
             prompt: …, attachments: [Image::fromStorage($ref, 'public')], size: …, quality: …, model: …
         )
       → moderation gate on each output URL
       → AssetService::store()  // download bytes → public disk → thumbnail → dimensions → AipAsset
       → TokenGuard::afterMedia($user, 'image', $model, $provider, units: count($images),
             ['type' => 'image_generation', 'tool_slug' => 'ai-image-pro'])
  → failure → AipJob failed + no afterMedia call (nothing was billed)
```

Provider image URLs **expire** (OpenAI's are short-lived), so `AssetService::store()` always downloads and re-hosts. Never persist a provider URL as the asset URL.

**Two small core changes are needed** (§9): `AiService::generateImage()` must accept `attachments` and `count`, or the addon calls `AiManager` directly and wraps it in the same TokenGuard calls. I recommend **extending core's method** — it keeps the moderation + billing + failure-logging in one place, and it finally gives the dead method a caller.

### 4.3 Provider-tool path (tier B)

Identical to the editor's existing flow, which is already correct: pre-check balance (`if (! credit_quota_mode() && $user->credits < $cost)`), `deduct_credits()` up front, queue `RunImageJob`, and on failure/moderation-block call `$user->refundCredits()`. Costs come from flat `addon_setting` integers because Remove.bg / Clipdrop / Replicate-upscale are not `AiModel`-priced.

### 4.4 Local-tool path (tier C)

No queue, no credits, no job row. `ToolController` → `LocalImageService` → new `AipAsset` (derived) → JSON response in a few hundred ms. Guarded by: `throttle:60,1`, a max input dimension, and a memory pre-check — GD allocates `width × height × 4` bytes, so a 8000×8000 PNG is ~256 MB and must be rejected *before* `imagecreatefrom*` rather than fatalling the request.

---

## 5. Data model

**`aip_assets`** — the library. Every image, whatever produced it.
`id, ulid, user_id, folder_id?, job_id?, parent_id?` (lineage) `, source` (generated|uploaded|derived) `, disk, path, url, thumb_path, mime, width, height, bytes, prompt?, negative_prompt?, model?, provider?, seed?, params json, is_favorite, expires_at?, timestamps, softDeletes`
Indexes: `(user_id, created_at)`, `(user_id, is_favorite)`, `parent_id`.

**`aip_jobs`** — one row per generate/provider run (tier A+B only).
`id, ulid, user_id, operation, status` (queued|processing|completed|failed) `, engine, model?, input_asset_id?, mask_path?, params json, batch_size, credits_charged, billing_mode` (media|flat|free) `, provider_job_id?` (Replicate prediction id) `, error_message?, started_at?, completed_at?, timestamps`

**`aip_folders`** — `id, user_id, name, timestamps` (mirror `VcFolder`).

**`aip_presets`** — admin-managed style presets: `id, name, slug, prompt_suffix, negative_prompt, thumb_path, sort, is_active`.

Deliberately **not** reusing `documents`: it's a text table, and an image gallery needs dimensions, thumbnails, favorites, folders and soft-delete. We can still *mirror* completed generations into `documents` behind an `also_save_to_documents` setting so the existing Library page isn't empty for users who look there.

---

## 6. Billing summary

| Op | Billing | Cost source |
|---|---|---|
| Generate, variations, prompt-edit | `media` | `AiModel` (type=`image`) `meta.cost_per_unit` × markup; per variant |
| Inpaint, outpaint, object remove, style transfer | `flat` | `credits_inpaint` etc. (addon settings) |
| Background remove/replace, upscale | `flat` | `credits_bg_remove`, `credits_upscale` |
| Resize, crop, compress, convert, rotate, watermark, text, color | `free` | 0 |

Non-negotiables, per the credit-mode rules already established in this codebase: charge via `deduct_credits()` / `TokenGuard::afterMedia`, refund via `$user->refundCredits()`, **never** raw `increment/decrement('credits')`, guard every balance pre-check with `! credit_quota_mode()`, and render "credits left" in the UI from `isProAvailable` / `userDailyCreditLimit` / `creditsUsedToday` (the badge logic at the top of the current `Editor.vue` is the reference implementation — reuse it).

---

## 7. UI

**`/ai-image` — Studio** (the manus surface). Single page, three zones:

- **Left rail** — tool switcher, grouped: *Create* (Generate, Variations, Edit with prompt) · *Enhance* (Upscale, Remove BG, Replace BG, Erase, Expand, Style) · *Adjust* (Resize, Crop, Compress, Convert, Rotate, Watermark, Text). Each tile shows its credit cost, or "Free", or a lock + "Configure in admin" when the provider key is missing.
- **Center** — the composer: big prompt textarea, model select, aspect pills, count (1/2/4), style-preset chips, advanced accordion (negative prompt, seed, reference drop-zone, strength). Below it the **result grid**: skeleton tiles while queued → images fade in. Hover actions on every tile: Upscale · Remove BG · Edit · Variations · Download · Favorite · Delete.
- **Right (or bottom on mobile)** — session history strip; clicking any past result loads it back into the working canvas.

**Editor modal** — the existing Fabric.js canvas from `Editor.vue`, extracted into `<MaskCanvas>` + `<EditorToolbar>` and opened over the Studio for the mask ops (erase, object remove, expand). No page navigation — you never lose the grid.

**`/ai-image/library`** — full gallery: folders, filters (source, model, favorites, date), lightbox with full metadata (prompt, seed, model, parent), bulk actions (bulk remove-bg, bulk resize, bulk download as ZIP, bulk delete), and "regenerate with this seed".

**Components:** `PromptComposer`, `ModelPicker`, `AspectPicker`, `StylePresetChips`, `ResultGrid`, `ResultTile`, `ToolRail`, `ToolPanel` (per-op params, driven by the registry), `MaskCanvas`, `CropCanvas`, `AssetLightbox`, `CreditBadge`, `JobPoller` (composable — one poller for all in-flight jobs, not one per tile).

**Nav:** `Inertia::share('imagePro' => ['enabled' => …, 'ops' => …])`, then swap the `imageEditor` block at `UserDashboardLayout.vue:205` for an `imagePro` block with two children (Studio, Library).

---

## 8. Admin

- **Settings** — provider keys (Stability, Replicate, Remove.bg, Clipdrop, Flux/fal, Ideogram) + per-op provider selects + per-op flat credit costs + limits (max input MB, max output dimension, max batch size, retention days, per-plan storage cap) + toggles (auto-save to library, mirror to documents, allow guest local tools).
- **Overview** — generations/day, credits spent by op, top models, failure rate by provider, storage used. (Mirror `VideoAdminController`.)
- **Style presets** — CRUD on `aip_presets`.

---

## 9. Core touchpoints

Small and enumerable — this is the full list of files outside `addons/ai-image-pro/` that change:

1. `routes/web.php:432` — add `ai-image` to the `page.show` negative lookahead. **Miss this and every addon route 404s into the CMS.**
2. `resources/themes/default/js/Layouts/UserDashboardLayout.vue:205` — replace the Image Editor entry with Image Pro (Studio + Library).
3. `app/Services/AI/AiService.php:579` — extend `generateImage()` with `attachments` and `count` params (backward-compatible defaults). Enables image-to-image and prompt-editing through the billed path.
4. `phpunit.xml` — add an `addons` testsuite. Addon tests are currently **not** in any testsuite, so `--filter` finds zero and the whole suite is unverified. Fix this before writing tests we can't run.
5. `database/seeders/` (or the addon seeder) — `AiModel` rows for image models (`type='image'`, `meta.cost_per_unit`) so media pricing resolves per model instead of falling back to the $0.04 config default.

---

## 10. Deprecating `ai-image-editor`

- `addon.json` of the new addon declares `"conflicts": ["ai-image-editor"]`; activating Pro deactivates the editor.
- `php artisan aip:import-image-editor` — copies `ie_edits` outputs (status=completed) into `aip_assets` (source=`derived`, lineage preserved by `version_number` order), copies the four provider API keys from the old addon's settings into the new ones, and reports what it moved. Idempotent.
- Route shims in the Pro addon: `/image-editor*` → 301 to `/ai-image`, registered only when the old addon is inactive.
- Old addon directory stays in the tree for one release with a deprecation note in its `description`, then is deleted.

---

## 11. Phases

| # | Phase | Deliverable | Tests |
|---|---|---|---|
| 0 | **Foundations** | Manifest, service provider, 4 migrations, models, `OperationRegistry`, admin Settings page, sidebar + route-regex wiring | Addon activates, migrates, seeds; registry availability reflects keys |
| 1 | **Generate** | `GenerationService` + core `generateImage()` extension, `RunImageJob`, `AssetService` (download/thumb/dimensions), Studio page with composer + result grid, save to library | Media billing charges N units for N variants; failure bills nothing; expired provider URL never persisted; moderation block refunds |
| 2 | **Local toolbox** | `LocalImageService` (resize, crop, compress, convert, rotate, watermark) + tool panels; sync, 0 credits, memory guard | Each op produces a derived asset; oversized input rejected pre-allocation; no credit movement |
| 3 | **Provider tools** | Port Stability/Replicate/Remove.bg/Clipdrop clients + `MaskCanvas`; bg-remove, upscale, erase, object-remove, expand, style; flat billing + mode-aware refunds | Refund on failure in **both** quota and metered mode; mask required where declared |
| 4 | **Library** | Gallery, folders, favorites, lineage view, bulk ops, ZIP download, share links | Ownership enforced on every asset route; bulk op charges per item |
| 5 | **Cutover** | `aip:import-image-editor`, conflict + redirects, editor deprecated; admin Overview | Import is idempotent; old URLs redirect |
| 6 | **Growth (optional)** | Public SEO tool pages (`/tools/image/{remove-background,resize,compress,convert}`) with guest daily limits; retention/cleanup job; per-plan storage caps; REST API | Guest limits enforced (`TokenGuard::assertGuestCanSpend`); cleanup respects `expires_at` |

Phases 0–3 are the shippable product. 4–6 are what make it competitive with manus.

---

## 12. Risks & open questions

**Risks**
- **Storage growth.** A generation product fills a disk fast. Ship `expires_at` + `CleanupExpiredAssets` + a per-plan storage cap in Phase 1's data model even if the cleanup job lands in Phase 6 — retrofitting retention onto an unbounded table is painful.
- **GD ceiling.** No Imagick means no AVIF guarantee, mediocre resampling quality on big downscales, and real memory risk. Mitigate with a hard `max_input_dimension`, a pre-allocation memory check, and `imagecopyresampled` (never `imagecopyresized`).
- **Async providers.** Replicate returns a prediction id, not an image — `PollProviderJob` with backoff, and a job-level timeout that refunds. The editor's `ReplicateClient` blocks on a polling loop inside the job today; that's acceptable on the `media` queue but should get a timeout ceiling.
- **`gpt-image-*` parameter drift.** `laravel/ai` defaults to `gpt-image-2`; sizes/quality enums differ per model. The `ModelPicker` must be driven by the `AiModel` rows + a per-model capability map, not a hard-coded list, or users will pick combinations the provider rejects.
- **Double provider keys.** Until the import command runs, an operator could have keys in both addons. The import command must be part of the cutover, not optional.

**Open questions for you**
1. **Route prefix** — `/ai-image` (proposed), or something shorter like `/studio` / `/images`? It also becomes the public tool-page namespace later.
2. **Day-1 providers** — OpenAI is a given. Which of Flux (via fal or Replicate), Stability SD3, Ideogram, Gemini do you want in v1.0? Each is a client + a capability map + docs.
3. **Guest tool pages** (Phase 6) — free public resize/compress/convert/remove-bg pages are the strongest signup funnel manus has, and the guest-credit plumbing already exists. In scope, or out?
4. **Envato** — separate item, or bundled with the main product? That decides whether the editor's deprecation can be immediate or has to be graceful for existing buyers.
5. **Retention default** — delete generated assets after N days on free plans? (Proposed: never for paid, 30 days for free, admin-configurable.)

---

## 13. Build log — what actually shipped (2026-07-12)

**Decisions taken:** `/ai-image` prefix · admin chooses providers · per-operation access levels (guest / login / premium / `plan:*`) · addon-packaged · admin sets retention. **Nothing hardcoded** — every provider, credit cost, access level, limit, retention window and watermark rule is an admin setting.

### Delivered
- **`OperationRegistry`** — 18 operations across 3 tiers. Single source of truth: UI, controllers, validation, billing, job routing and access all read from it. Admin overrides live in one JSON setting merged over shipped defaults; the registry **refuses** overrides of `tier`/`billing`/`inputs` so an operator can't relabel a paid engine as free.
- **`ImageAccessService`** — per-op access levels (incl. dynamic `plan:*`), guest + user daily caps, storage quota, retention (free vs paid), watermark policy. An access level that no longer exists falls back to the shipped default rather than falling open.
- **Generation** — core media pipeline. `AiService::generateImage()` extended with `attachments` + `count` (backward-compatible), enabling image-to-image and prompt-editing. It had **zero callers before this** — the product could not generate an image at all.
- **Provider tier** — Stability, Replicate, Remove.bg, Clipdrop ported (now return raw binary; keys from addon settings) + new Fal and Ideogram clients. Replicate polling is bounded with a timeout ceiling.
- **Local tier** — GD, synchronous, free: resize, crop, rotate, compress (incl. target-KB binary search), convert, watermark, text, colour. Guarded against GD's `w×h×4` memory blowup *before* decode.
- **UI** — Studio (tool rail, prompt composer, result grid with chaining hover-actions, Fabric.js mask modal, single job poller), Library (folders, filters, bulk ops, lineage, storage meter), Admin Settings (8 tabs, every knob) and Admin Overview.
- **Cutover** — `aip:import-image-editor` (idempotent: assets, API keys, credit costs), `conflicts: ["ai-image-editor"]`.
- **Core wiring** — composer PSR-4, `ai-image` added to the CMS catch-all regex, sidebar nav, `Addons` phpunit testsuite.

### Verified
`105/105` Addons tests · `48/48` Unit · `114/114` Feature (no core regressions) · 40 PHP files lint clean · `vue-tsc` clean · `vite build` clean (all 4 pages bundled) · migrations run · 21 routes register · seeder idempotent.

### Bugs found and fixed en route
- **`public string $queue` in a job is a fatal error** (`Queueable` declares it untyped, no default) — this is what made `ai-image-editor`'s test suite unloadable. Fixed there too.
- Addon test suites were in **no** phpunit testsuite, so they never ran. Now they do (4 files excluded with reasons: 3 are Pest-syntax, 1 is the deprecated editor's).

### Not yet done
- **Uncommitted** — nothing has been committed to git.
- **Never driven in a browser.** The UI typechecks and builds, but no page has been clicked through.
- **No provider key has been exercised end-to-end.** All 4 seeded image `AiModel` rows are `is_active=false` until keys are set, so no real generation or edit has round-tripped against a live API.
- Phase 6 (public guest tool pages, per-plan storage caps, REST API) not started.
