# Translation pipeline

Translations live in `lang/{code}.json` — files, not the database. See
`app/Services/TranslationFileStore.php` for why.

## State

7,127 translatable strings, split into chunks of 120. Chunks 001–015 are done and already
merged (the storefront, chunks 001–012, is complete in all five languages).

Chunks 016–060 are also merged. Remaining: **chunks 061–062 — 132 keys.**

Those last two are labels declared as *data* inside `addons/*/addon.json` — admin menu
entries, setting labels, permission names, addon names and descriptions. No regex over source
files can reach them, so `TranslationKeyScanner` reads the manifests directly. They are what
renders in the admin sidebar under "Addons".

Target languages: `bn` Bengali · `ar` Arabic · `es` Spanish · `fr` French · `hi` Hindi.
English is the source and needs no catalogue.

Addon strings are merged into the core `lang/*.json`, not into each addon package, because
`TranslationFileStore` reads only `lang/{code}.json`. A buyer who owns no addons simply carries
a few hundred entries that never match anything — harmless — while one who buys an addon later
finds it already translated.

## Workflow

1. Take one file from `chunks/`, e.g. `chunk-016.json`.
2. Send it to the translator with the prompt in `PROMPT.md`.
3. Save the reply as `incoming/out-016.json`.
4. Repeat for as many chunks as you like — they can be batched.
5. Merge and check:

```bash
php scripts/i18n/apply.php     # merges every incoming/*.json, renames applied ones to .done
php scripts/i18n/verify.php    # checks placeholders, identity entries, empty values
```

`apply.php` refuses a file whose five locales do not cover the same number of keys, which is the
usual symptom of a translation pass that quietly dropped entries. Rejected files are left in
place with the reason printed, so you can re-run the chunk and try again.

## What verify.php reports

- **lost-placeholders** — a `:token` in the source that has no counterpart in the translation.
  Always a real bug: the value renders with a missing name, count or date. Fix and re-apply.
- **identity** / **empty** — should always be 0; `TranslationFileStore` drops these on write, so
  a non-zero count means something bypassed it.
- **ascii-only** — a warning, not a failure. A Bengali/Arabic/Hindi value that is still pure
  ASCII is usually untranslated, but brand terms (`AdSense`, `Top P`, `BYOK`) legitimately are.

## After translating

Catalogues are plain files, so they survive `migrate:fresh` and ship inside the release. Commit
`lang/*.json` and rebuild the package so buyers get them:

```bash
php scripts/build-release.php 1.0.0
```

`lang/` is in the build's `ALLOW_DIRS` and in its writable skeleton, and the installer's system
check verifies the directory is writable — the admin translation screen writes these files.
