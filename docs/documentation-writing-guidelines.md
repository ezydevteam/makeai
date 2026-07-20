# Documentation Writing Guidelines

**Applies to:** the bundled corpus — `resources/docs/core/*.md` for core pages and
`addons/<slug>/docs/*.md` for addon pages — and the public site at
`https://makeai-docs.ezydev.net`
**Companion doc:** [`product-docs-roadmap.md`](product-docs-roadmap.md) — what to write and in
what order. This file is *how* to write it.

---

## 1. The thing that makes this different

Every page you write has **three readers**, and they want different things:

1. **A buyer** reading the site, usually stuck, usually mid-task, usually not a developer.
2. **A BM25 retriever**, which decides whether the page is even *found* when the admin asks
   the assistant a question.
3. **An LLM**, which is handed 2–3 sections of the page — never the whole thing, never the
   surrounding pages — and must answer from them without inventing the rest.

Ordinary docs advice covers reader 1. Readers 2 and 3 are why this file exists. A page can be
beautifully written, correct, and still useless: if it is never retrieved, or if the section
that gets retrieved doesn't make sense on its own, the assistant confidently answers from the
wrong page and the admin goes looking for a screen that doesn't exist.

**Write so that any single H2 section, lifted out and shown alone, is still true and still
useful.** That one rule drives most of what follows.

---

## 2. File and front-matter

One topic per file, named after its slug.

**Where it goes decides who can read it.** A page about core belongs in `resources/docs/core/`
— it ships in the release zip and is rendered into the offline `documentation/docs.html`. A
page about an addon belongs in that addon's own `addons/<slug>/docs/` folder: it travels in
the addon's package, so only buyers who own the addon get it, and it is deliberately left out
of `docs.html` (addon docs are published online only).

```markdown
---
title: Adding an AI Provider Key
slug: ai-provider-keys
page: ai-provider-keys.html
section: AI
license: regular
keywords: [openai, anthropic, gemini, api key, byok, credentials, 401, invalid key]
---
```

| Field | Required | What it does |
|---|---|---|
| `title` | yes | The citation label the admin sees. Also boosts retrieval — so it must contain the words people search with, not a clever heading. |
| `slug` | yes | Stable id. Used in tests and as the citation key. |
| `page` | no | The **exact published filename** on the docs site. |
| `section` | yes | Grouping for the site nav; also boosts retrieval. |
| `license` | yes | `regular` or `extended`. |
| `keywords` | yes | Terms an admin would type that the prose doesn't contain. |

### `page:` — the no-dead-links rule

**A citation links only when the page has a `page:` value.** Without one, the assistant renders
the source as a plain label instead of a link.

This is deliberate and you must not work around it. Links are never derived from the slug,
because the docs site's filenames don't track our slugs — deriving would mint a confident 404
on every page not yet published. **Add `page:` only once the page is genuinely live**, and set
it to the real filename (`ai-provider-keys.html`), not a guess.

A citation that doesn't link is a small disappointment. A citation that 404s destroys trust in
every other citation on the page.

### `license:` — get this right or the assistant lies

`license: extended` makes the page **invisible** on a Regular install — it cannot be retrieved,
cited, or quoted.

Tag as `extended` anything about subscriptions, plans, payment gateways, paid tiers, or the
"pro" concept. Get it wrong in the permissive direction and the assistant will cheerfully walk
a Regular buyer through configuring a feature their license does not include: a confident,
well-cited, completely unusable answer. That is worse than "I don't know."

When in doubt, ask: *can a Regular-license buyer actually do this?* If no → `extended`.

### `keywords:` — write down the words you didn't use

Keywords are weighted **above** body text, and they are the cheapest retrieval fix available.
Put in them what the prose deliberately leaves out:

- **Error strings and codes** the admin will paste in: `401`, `invalid key`, `insufficient credits`.
- **Provider and product names**: `openai`, `anthropic`, `stripe`, `paddle`.
- **Synonyms and old labels**: if the UI now says "Providers" but everyone still calls it
  "API keys", both belong here.
- **The blunt phrasing of the problem**: `not working`, `stuck`, `nothing happens`.

If a page isn't being retrieved by the question it exists to answer, the fix is nearly always
`keywords:` — not rewriting the prose.

---

## 3. Structure

```markdown
Intro paragraph. No heading. One or two sentences on what this page is for and who needs it.

## One task per H2

## Another task
```

- **The intro is its own retrievable chunk**, so make it a real answer, not a throat-clear.
  "This page covers provider keys" is wasted. "MakeAI ships with no AI credentials; you must
  add at least one provider key before anything can generate" is a chunk worth retrieving.
- **H2 sections are the retrieval unit.** One question, answered completely, per H2.
- **Don't split one answer across two H2s.** The retriever may hand the model only one of them.
- **Don't let an H2 depend on the one above it.** No "now do the same for the second key" — the
  section above may not be there.
- **Keep a section under ~1,200 characters.** Beyond that it gets split on paragraph
  boundaries and you lose control of where the seam lands.
- **H3s are fine** and are treated the same way. Don't go deeper.

### Heading style

Write headings the way an admin *searches*, not the way a table of contents reads.

- Good: `## Adding a key`, `## Rotating or removing a key`, `## Why generation still fails`
- Bad: `## Configuration`, `## Advanced`, `## Notes`, `## Miscellaneous`

Gerunds ("Adding", "Installing") are encouraged — the retriever stems them to the infinitive
the admin actually types ("how do I **add** a key"). A heading of `Configuration` matches
nothing anyone would ever type.

---

## 4. Voice

Write for **an admin who is stuck right now**, not for a reader browsing at leisure.

- **Second person, present tense, active voice.** "Go to Admin → AI → Providers." Not "The
  administrator should navigate to..."
- **Lead with the answer.** The first sentence of a section is the answer; the explanation goes
  after it, for whoever wants it.
- **Name the exact UI path** in the form the product uses: **Admin → AI → Providers**. If the
  screen's label changes, the docs are wrong — that is a feature, it means the docs are
  checkable.
- **Never invent a screen, setting name or menu path.** The assistant is instructed to refuse
  rather than improvise; if the docs improvise, the assistant launders the invention into a
  confident answer. If you are unsure a screen exists, open it.
- **Say what breaks.** Most support tickets are not "how do I do X" but "I did X and Y
  happened". A page that only documents the happy path answers half the questions it should.
- **No marketing.** Nobody reads documentation to be sold to.
- **No cross-page hand-waving.** "See the other guide" is useless to a model that was handed
  this section alone. Either state the fact, or name the page explicitly by title.

### Troubleshooting sections earn their keep

End any page describing a configurable feature with a section on **why it isn't working**, as
an ordered list of causes, most common first. These sections are retrieved constantly, because
they are phrased the way people ask.

```markdown
## Why generation still fails with a valid key

- The **daily AI budget** has been reached, which blocks generation site-wide.
- The user is **out of credits** — what that means depends on the credit mode.
- The model is **inactive**, or its provider's key was removed.
```

---

## 5. Formatting

- **Bold** for UI labels and the load-bearing noun in a list item. Not for emphasis generally.
- `Code` for filenames, commands, settings keys, error strings.
- Fenced blocks for anything the admin copies. Fenced content is **stripped before indexing**,
  so a page whose only distinguishing content is inside a code fence will not be retrieved —
  the prose around it must carry the meaning.
- Tables only for genuinely tabular facts. They chunk badly; a table that needs a paragraph of
  explanation should be a paragraph.
- No images in the bundled corpus (the assistant cannot see them). Images live on the site
  only, and the prose must remain complete without them.

---

## 6. The live site

- **Flat filenames, `*.html`.** `installation-guide.html`, not `/guides/installation/`.
- **The filename must match the page's `page:` front-matter, exactly and permanently.**
  Renaming a published page silently turns every assistant citation pointing at it into a 404.
  If a rename is unavoidable, update `page:` in the same change and leave a redirect.
- **Render your templates.** The site currently serves the literal string `{{APP_NAME}}` in
  page titles and breadcrumbs to real buyers. Nothing may ship with unrendered placeholders.
- **The site and the corpus are the same content.** Per the roadmap, markdown is the source of
  truth and the site is generated from it. Do not fix a typo on the site only — it will be
  overwritten, and until it is, the assistant is quoting a different sentence than the one the
  admin is reading.

---

## 7. Before you publish a page

- [ ] Front-matter complete; `slug` unique; `license` correct.
- [ ] `page:` set **only if** the page is actually live at that filename.
- [ ] Every H2 makes sense read alone, with no page above it and no page below.
- [ ] Every UI path named in the page has been opened and verified.
- [ ] `keywords:` contains the error strings, provider names and blunt phrasings the prose omits.
- [ ] A "why it isn't working" section exists, if the page describes anything configurable.
- [ ] A golden question for the page is added to `ProductDocsTest` — and the page is the top
      hit for it. **If it isn't retrieved, it isn't finished.**
