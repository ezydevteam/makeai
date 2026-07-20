---
title: Reading Your Dashboard
slug: dashboard-overview
page: dashboard-overview.html
section: Dashboard
license: regular
keywords: [dashboard, home screen, stats, charts, overview, revenue, signups, analytics, homepage admin]
---

The Dashboard is the first screen you see after logging in (**Admin → Dashboard**). It gives you an at-a-glance summary of signups, AI usage, and — if billing is available on your license — revenue, without opening any other page.

## Changing the time period

Every number on the Dashboard is scoped to a period you choose from the selector at the top: **Today**, **Last 7 days**, **Last 30 days**, **Last 90 days**, or **All Time**. Switching periods refreshes every stat card and chart on the page to match. Numbers are cached for a few minutes, so a change you just made (like a new signup) may take a short while to appear.

## What the stat cards show

Across the top of the Dashboard are quick stat cards: **Total Users**, **AI Requests**, **Output Tokens**, **Internal Usage** (the cost of AI activity your own site generates internally, not from customers), **API Cost**, and — only if your license has billing available — **Revenue**. Each card shows a small trend line and a comparison against the previous period of the same length.

## What the charts cover

Below the stat cards, charts are grouped by topic:

- **Growth & Revenue** — user registrations over time, cost vs. revenue, newsletter subscriber growth, and subscription health (paid-license installs only).
- **AI Usage & Cost** — credit usage broken down by tool, cost by AI provider, usage by provider, and failure rate.
- **Acquisition** — where your traffic is coming from, your top AI tools and models by usage, and your most recently registered users.
- **Engagement & Activity** — affiliate performance (if the affiliate program is enabled), your most popular blog posts (if blogging is enabled), free-to-paid conversion, recent subscriptions, a live recent-activity feed, and usage broken down by country.

A **Quick Actions** panel and a personal **My Notes** widget also sit near the top for shortcuts and reminders you leave for yourself.

## Why a chart or card looks empty

- A **Revenue** card or subscription charts that don't appear at all usually mean billing isn't available on your current license — see [Regular vs Extended license](regular-vs-extended-license) once that page exists, or check **Admin → Settings → License**.
- **Affiliate Performance** and **Popular Blog Posts** only appear once those features are switched on in **Admin → Settings → Features**.
- If a chart shows no data at all for a period where you know activity happened, try switching to **All Time** — a short period like "Today" can legitimately be empty outside business hours.
- If you can't see the Dashboard at all, your admin account is missing the `dashboard.view` permission — ask a Super Admin to check your role under **Admin → Roles → Admins → Manage Roles**.
