# MakeAI Documentation — Roadmap

**Status:** pipeline shipped, content pending
**Date:** 2026-07-12
**Scope:** `resources/docs/product/` (bundled corpus) + `https://makeai-docs.ezydev.net` (public site)

The retrieval pipeline that lets the **admin** AI Assistant answer from the MakeAI
documentation is built, tested and merged. Nothing below is blocked on code. What remains is
writing the documentation itself — and doing it once, in a way that serves both the public
site and the assistant's offline corpus.

---

## 1. What already works

| Piece | Where | State |
|---|---|---|
| Lexical retrieval (BM25F) over a markdown corpus | `app/Services/Docs/ProductDocsService.php` | Done |
| Availability gate, never throws | `addons/ai-assistant/app/Support/ProductDocs.php` | Done |
| Admin chat auto-grounds on the docs | `AiAssistantController::handleChat()` | Done |
| `/docs` = MakeAI docs in admin, site KB on the public widget | `SlashCommandRegistry` | Done |
| Citations render, and link only when the page is published | `AssistantMessage.vue`, `types.ts` | Done |
| Extended-only pages hidden on a Regular license | `ProductDocsService::licensePermits()` | Done |
| Tests | `addons/ai-assistant/tests/Feature/ProductDocsTest.php` | 11 passing |

Properties worth not regressing: **free** (no embedding call per question), **offline** (no
table, no network, no migration), **versioned** (the corpus ships in the zip, so it describes
the version the buyer actually has), and **no admin settings** — it is a built-in.

The five pages currently in `resources/docs/product/` are **pipeline fixtures, not the
manual**. They exist so the retrieval had something real to rank. Expect to replace them.

---

## 2. The one structural decision to make first

Two artefacts must exist and must not drift: the **public docs site** and the **bundled
markdown corpus**. Maintaining them separately guarantees they diverge.

**Recommendation: markdown is the source of truth; the site is generated from it.**

Write each page once as markdown in `resources/docs/product/`, and build the static site from
that folder. Then the assistant's corpus and the public site are the same content by
construction, slugs line up for free, and a doc fix is one commit rather than two.

The alternative — author on the site, export a snapshot into the repo — needs an export
pipeline that has to keep working forever, and every drift between them becomes an assistant
that cites a page saying something different from what the admin reads when they click it.

**Decide this before writing page six.** Everything below assumes markdown-first.

---

## 3. The corpus contract

Every page is a markdown file in `resources/docs/product/` with this front-matter:

```markdown
---
title: Adding an AI Provider Key      # shown as the citation label
slug: ai-provider-keys                # stable id; changing it breaks nothing but the tests
page: ai-provider-keys.html           # exact published filename on the docs site
section: AI                           # grouping; also boosts retrieval
license: regular                      # or: extended
keywords: [openai, api key, byok]     # terms an admin would type but the prose omits
---

Intro paragraph (no heading) — becomes its own retrievable chunk.

## One task per H2
Sections are the retrieval unit. A section should answer one question completely.
```

Rules the pipeline actually enforces or relies on:

- **`page:` is the only thing that produces a link.** No `page:` → the citation renders as a
  plain label. Links are never derived from the slug, because the site's filenames don't
  track our slugs and a fabricated link is a confident 404. Add `page:` only once the page is
  genuinely published.
- **`license: extended`** hides the page entirely on a Regular install. Use it for anything
  about subscriptions, plans, gateways, or paid tiers. Getting this wrong means the assistant
  walks a Regular buyer through a feature they do not have.
- **Sections are chunks.** Write H2s that stand alone; a section retrieved on its own must
  still make sense, because that is exactly how the model will see it.
- **`keywords:` are for the words admins type that the prose doesn't use** — error strings,
  provider names, old menu labels, synonyms. They are weighted above body text.
- Headings in the **gerund** ("Adding a key") are fine — the stemmer maps them to the
  infinitive an admin actually types ("how do I add a key").

---

## 4. Proposed page inventory

Twenty pages, mapped to the product's real admin surface. This is the list to agree on before
writing, because it is also the site's navigation.

### Getting Started — `regular`
1. System requirements
2. Installation
3. Setting up cron *(fixture exists)*
4. Storage and file uploads
5. Email / SMTP configuration

### AI — `regular`
6. Adding an AI provider key *(fixture exists)*
7. Choosing models and the site default
8. The daily AI budget kill-switch
9. AI access rules (who may use which tools)
10. RAG settings
11. Usage logs — reading them, and what they cost you

### Tools — `regular`
12. Enabling and disabling AI tools
13. Tool categories and presets
14. Tool reviews and moderation

### Credits — `regular`
15. Credit modes: quota vs metered *(fixture exists)* — **the page most likely to prevent
    support tickets; write it first**
16. Daily limits and allowances

### Payments — `extended`
17. Subscriptions and plans *(fixture exists)*
18. Payment gateways
19. Why a subscriber isn't being treated as pro

### Platform — `regular`
20. Installing and activating addons *(fixture exists)*
21. Themes and branding
22. Regular vs Extended license — what each unlocks
23. Troubleshooting

(Twenty-three, not twenty. Trim at review.)

---

## 5. Sequencing

**Phase A — agree the inventory and the markdown-first decision.** Cheap now, expensive later.

**Phase B — write the highest-leverage pages first.** Not alphabetically: order by how often
the admin is currently stuck. Credit modes, provider keys, cron, and license tiers between
them account for most of what an admin cannot work out from the UI alone.

**Phase C — publish the site from the corpus**, with filenames matching `page:`. Fix the
unrendered `{{APP_NAME}}` placeholders while you are there (see §7).

**Phase D — backfill `page:` across the corpus** as each page goes live, turning the
assistant's citations from labels into links.

**Phase E — verification** (§6).

Phases B and C can overlap; D strictly follows C.

---

## 6. How we will know the docs are good

Not "are they written" — **can the assistant answer from them.**

**Golden questions.** A table of real admin questions and the page that should be the top
hit. This is the acceptance test for retrieval quality, and it scales as the corpus grows:

| Question an admin actually asks | Expected top hit |
|---|---|
| "how do I add an OpenAI api key" | `ai-provider-keys` |
| "why is generation failing for everyone" | `daily-ai-budget` |
| "users say they're out of credits but they topped up" | `credit-modes` |
| "cron isn't running" | `cron-and-scheduling` |
| "subscriber isn't showing as pro" | `subscriptions-and-plans` (Extended only) |

Add a row per page as it is written; assert it in `ProductDocsTest`. A page that cannot be
retrieved by the question it exists to answer is not finished — usually the fix is `keywords:`,
not prose.

**A link checker.** Once `page:` values exist, assert every one of them resolves (200) on the
docs site. This is what stops a renamed page silently turning every citation into a 404.

**A front-matter lint.** Unique slugs, required fields present, `license:` is one of the two
permitted values. Cheap to add as an artisan command or a test.

---

## 7. Known issues carried into this work

**The live docs site publishes unrendered template placeholders.** `overview.html` and
`installation-guide.html` currently serve the literal string `{{APP_NAME}}` to buyers ("...
{{APP_NAME}} Help Center"). Not a repo bug — it is live on the site — but it must be fixed
before the site is the thing we point buyers and the assistant's citations at.

**The addon test suites never run in CI.** `phpunit.xml` only scans `tests/Unit` and
`tests/Feature`, so `php artisan test` (151 tests) has never included the 72 tests under
`addons/ai-assistant/tests/`. They pass, but only when invoked by path. The golden-question
tests above will live in that suite — so if they are meant to guard anything, this needs an
`Addons` testsuite entry first.

**Renaming a fixture slug breaks a test.** `ProductDocsTest` asserts on `ai-provider-keys` and
`cron-and-scheduling`. When the fixtures are replaced by real pages, update the golden
questions in the same commit.
