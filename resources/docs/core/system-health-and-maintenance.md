---
title: System Health, Maintenance Mode, and Diagnostics
slug: system-health-and-maintenance
page: system-health-and-maintenance.html
section: System
license: regular
keywords: [health check, maintenance mode, rate limits, banned ip, custom style, admin panel appearance, activity log, audit log, horizon, system status, health check failing, queue worker offline, scheduler failed, go live, go maintenance, rate limit exceeded, ban ip, redis, diagnostics]
---

**Admin → System** groups the diagnostic and operational screens for your install: Health Check, Maintenance, Rate Limits, Custom Style, Activity Logs, and — for Redis-based installs — Horizon.

## Reading your Health Check

**Health Check** runs live checks across four tabs — Server, Application, Services, License — each showing a pass/warn/fail badge with a suggested fix when something's wrong. It checks things like PHP version and extensions, storage and cache writability, upload/execution limits, whether the queue worker and scheduler appear active, database and Redis connectivity, and your license's verification and domain-match status. Four summary cards at the top total how many checks passed, warned, or failed.

## Turning on Maintenance Mode

**Maintenance** takes your site offline for everyone except you — your own browser gets a bypass so you can keep working while it's down. Set a **Page title**, an **Estimated restoration time**, a rich-text **Maintenance message**, an optional list of **Allowed IPs** who can also bypass it, and an optional background image. Toggle it on with **Go Maintenance** and off with **Go Live**.

## Setting rate limits and banning IPs

**Rate Limits** covers eight categories — AI Generation, Login/Authentication, OTP/2FA Verification, Contact Form, Comments, Newsletter, Public/Guest Tools, and Social Login — each with a separate limit for Guest, Free, and Premium tiers (a max request count and a rolling time window per tier). The same screen lets you manually ban an IP address (with a reason, a scope, and an optional expiry) and set per-user custom limits that override the tier defaults for one specific account.

## Customizing the admin panel's own look

**Custom Style** is easy to confuse with the public site's theme settings, but it's different: this screen only restyles the **admin panel itself** — its colors, sidebar, nav bar, and fonts — not your public-facing site. For public-site branding, see [Making It Look Like Your Brand](branding-your-site).

## Reviewing the activity log

**Activity Logs** (Super Admin only) shows the last 29 days of admin actions: who did what, when, from which IP and browser, with a details view for the exact payload changed. Each entry carries a plain-English action label and a category badge (Settings, Security, Users, Roles, Billing, Affiliate, Content, Appearance, Marketing, AI, System, Mail). Filter by admin, date range, or a free-text search. Only changes are recorded — creating, editing, and deleting — so simply viewing a screen leaves no entry, and the actions captured are those performed by Super Admin accounts. Any submitted value whose field name looks like a password, secret, token, API key, credential, license, signature, or webhook is redacted before the entry is written, so live credentials never land in the audit table. The log can't be edited or cleared from the admin panel, by design.

## Why a health check keeps failing

- **Queue worker "May be offline"** and **Scheduler fail** both usually trace back to the same root cause — cron isn't actually running. See [Setting Up Cron](cron-and-scheduling).
- **Cache/Session driver warnings** appear whenever you're not using Redis for those — this is a warning, not necessarily something broken, but Redis is recommended for production.
- **License checks fail** — see [Verifying Your License](verifying-your-license) for the domain-mismatch and re-verification triage.
