---
title: The Daily AI Budget and Spend Controls
slug: daily-ai-budget
page: daily-ai-budget.html
section: AI
license: regular
keywords: [daily budget, spend limit, kill switch, guest limit, per-user limit, overspend, cost control, budget reached]
---

**Admin → AI Management → Providers → Spend Controls** is your safety net against an unexpectedly large AI bill. It sets hard ceilings on usage that apply site-wide, independent of what any individual customer's plan allows.

## The global daily AI budget

The **Global Daily AI Budget (USD)** is a site-wide kill switch: once total AI spend for the day reaches this amount, generation stops for **everyone** — every customer, every tool, every guest — until the budget resets the next day. This is the setting to check first any time generation fails for multiple people at once rather than one account.

## Guest and per-user limits

Separately from the global budget, **Guest Daily Credit Limit** caps how much a non-logged-in visitor can generate per day (useful for a free trial without requiring signup), and **Per-User Daily/Monthly Credit Limit** caps how much a single logged-in account can generate in a day or month, regardless of their plan's own allowance. These exist to stop one account — compromised, automated, or simply enthusiastic — from consuming a disproportionate share of your budget.

## Why generation is failing for everyone, not just one person

- The **Global Daily AI Budget** has been reached — this is the most common cause of a site-wide outage that looks like a broken AI provider key but isn't. It resets automatically the next day.
- A newly-lowered budget can take effect immediately, so a value changed mid-day can cut off generation sooner than expected.
- This is separate from an individual customer being out of credits — see [Credit Modes](credit-modes) for what "out of credits" means for a single account.
