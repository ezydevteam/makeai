---
title: FakerAI Batch History, Rollback, and Default Settings
slug: faker-ai-history-and-rollback
page: faker-ai-history-and-rollback.html
section: Addons
license: regular
keywords: [fakerai history, undo demo data, rollback batch, delete fake data, faker ai settings, backdate, ai chunk size]
---

Every FakerAI run is a **batch** you can review and cleanly remove from **Appearance → Addons → FakerAI → History**.

## Reading the History table

Each row is one generation run, showing its type, target, how many records were actually inserted out of how many requested, tokens used (for AI-based types), status (Pending/Processing/Completed/Failed — hover a failed row to see the error), which admin ran it, and when.

## Deleting a batch — what rollback actually does

Click **Delete** on a batch to remove exactly what it created — FakerAI keeps a precise, row-by-row ledger of every insert and every counter increment made during that run, and rolling back replays that ledger in reverse. This is precise, not a guess at "anything that looks fake":

- Records the batch created (fake users, testimonials, reviews, comments) are deleted outright.
- Counters the batch raised (usage counts, view counts, share counts, helpful-vote tallies) are decreased by exactly the amount that batch added, never going below zero.

## The limits of rollback

- If you **edited** a generated record afterward (for example, rewrote a fake testimonial's text), rollback still deletes the whole row — there's no partial-undo or "was this touched" check.
- If a generated record was **already deleted manually**, rollback simply skips it — no error.
- If a counter the batch raised was **also changed by real activity** since (real customers actually using a tool, for instance), rollback still subtracts the batch's original amount — it doesn't know about or reconcile the real activity in between.

## The default language, tone, and pacing settings

FakerAI doesn't have its own dedicated settings screen — its four configurable defaults are managed through the same generic addon-settings framework every addon uses:

- **Default Language** — what AI-written content defaults to.
- **Default Style / Tone** — the default voice for AI-written testimonials, reviews, and comments.
- **Backdate Window (days)** — how far back in time generated records' timestamps are randomly spread, so they don't all appear created in the same instant.
- **Items per AI Request** — how many pieces of content are drafted per AI call, for efficiency.

## Why a rollback didn't fully "undo" what you expected

- A record from that batch was edited after generation — rollback deletes it anyway, but if you were hoping to keep your edits, they're gone too.
- Real customer activity happened on the same tool/post/article after the batch ran — counters return to "batch amount subtracted," not necessarily to their true pre-batch value.
