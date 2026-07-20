---
title: Setting Up the FakerAI Addon — Populating a Fresh Install With Demo Data
slug: faker-ai-addon-setup
page: faker-ai-addon-setup.html
section: Addons
license: regular
keywords: [fakerai, demo data, fake users, fake testimonials, fake reviews, fake comments, populate empty site, sample content, seed data]
---

The **FakerAI** addon fills a brand-new, empty-looking install with realistic demo content — fake customer accounts, testimonials, tool reviews, blog comments, and usage counters — so your site doesn't look empty before real customers arrive. Run it from **Appearance → Addons → FakerAI → Generator**.

## What each generator type actually creates

Nine generator types, grouped by area:

- **Users** — creates real, active, email-verified fake member accounts (email addresses ending in a non-routable `@faker.local` domain) with an AI-drafted name, profession, and country.
- **Testimonials** — AI-written customer quotes with a name, role, company, and star rating, saved as real Testimonial entries (about 1 in 5 randomly marked Featured). You choose a **Source** label (Manual, Google, or Trustpilot) and a rating band.
- **Tool Reviews** — AI-written star reviews on your AI tools; each review gets its own newly-created fake reviewer account, and your tools' average rating updates automatically.
- **Tool Usage** — no AI involved; simply raises the "uses" and "views" counters shown on your tool cards. No visible content is created, just bigger numbers.
- **Tool Favorites** — no AI; creates fake user accounts and links them as having favorited selected tools, raising favorite counts.
- **Blog Comments** — AI-written guest comments (not tied to a member account) on your published posts.
- **Blog Views** — no AI; logs fake pageview history and raises each post's view counter.
- **Blog Share Counts** — no AI, no new records; just raises the "shared X times" counter on selected posts.
- **Knowledge Base Helpful Ratings** — no AI; splits a vote total into helpful/not-helpful counts on KB articles, skewed positive. Only available if the Knowledge Base addon is active.

## Running a generation

On the **Generator** page: pick **What to generate**, then a **Target** if that type needs one (specific tools, blog posts, or KB articles — or "Spread across all"), then **How many** (server-enforced ceilings apply: 200 for AI-written types, 2,000 for Tool Favorites/Blog Views, 100,000 for pure-counter types). AI-based types also let you set a **Language**, a **Tone**, and optional **Extra guidance** text. There's no preview — clicking **Generate** queues it as a background job immediately; you'll see a confirmation and can track progress on the **History** page.

## Why generation is charging you nothing

Every AI-written generator runs on your site's own internal system account, never a real customer's credit balance — the addon is explicitly built so it "never touches a customer's credits." You still need at least one working AI provider key configured (**AI Management → Providers**) for the AI-based generator types to run at all; the pure-counter types (Tool Usage, Tool Favorites, Blog Views, Blog Shares, KB Ratings) don't call AI and work without one.

## Why a generator type isn't available

- **Knowledge Base Helpful Ratings** only appears if the separate Knowledge Base addon is installed and active.
- A generator that needs a target (tools, posts, or articles) is disabled if there's nothing yet to target — for example, Blog Comments needs at least one published post to exist first.
- An AI-based generator fails immediately if no AI provider key is configured anywhere on the site.
