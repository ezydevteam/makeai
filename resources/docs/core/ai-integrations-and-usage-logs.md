---
title: AI Integrations and Usage Logs
slug: ai-integrations-and-usage-logs
page: ai-integrations-and-usage-logs.html
section: AI
license: regular
keywords: [integrations, plagiarism checker, ai detector, grammar checker, translation, usage logs, ai requests, tokens, cost, credits spent]
---

**Admin → AI Management → Integrations** and **Usage & Logs** cover two different things: connecting paid third-party AI utilities, and monitoring what your AI usage actually costs day to day.

## Connecting a third-party AI utility

Integrations configures four specific add-on services, each independent: a **Plagiarism Checker** (Copyscape or Originality.ai), an **AI Content Detector** (GPTZero or Sapling), a **Grammar Checker** (LanguageTool), and **Translation** (DeepL or Google Translate). For each, enable it, choose a provider, paste in that provider's own API key, and set a **Fixed Credit Cost** for what one use should charge a customer. Use **Test Connection** after saving a key to confirm it works before customers rely on it.

This screen is only for AI-content utilities — it is separate from the plain **Extensions** page under Settings, which handles non-AI system connectors like CAPTCHA or analytics.

## Reading your usage logs

**Usage & Logs** shows a live table of every individual AI request: which user made it, which provider and model handled it, which tool it came from, how many tokens it used (in/out), what it cost in USD, how many credits it spent, whether it succeeded or failed, and how long it took. Summary cards at the top total AI Requests, Credits Spent, Estimated Cost, and Failed Requests, each compared against the previous week. Filter by user, provider, status, or a date range; click any row for the full request details.

## Why an integration or a logged request shows as failed

- The integration's API key is invalid or the provider account is out of its own quota — re-run **Test Connection** to confirm.
- A logged request with **Status: Failed** usually points to the underlying AI provider, not this product — check that provider's own status page if failures cluster around one specific model.
- If Usage & Logs shows no data at all for a period you expected activity, confirm you're not filtering by a Provider or Status that excludes it.
