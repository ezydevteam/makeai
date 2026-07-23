---
title: AI Assistant Slash Commands and Reviewing Feedback
slug: ai-assistant-slash-commands-and-feedback
page: ai-assistant-slash-commands-and-feedback.html
section: Addons
license: regular
keywords: [slash commands, docs command, health command, /docs, /health, /reset, assistant feedback, thumbs up thumbs down, feedback report, conversation history, assistant credits, command not working]
---

Beyond ordinary chat, the **AI Assistant** widget supports typed slash commands, and every message can be rated by the person it was shown to — reviewable from **Appearance → Addons → Assistant Feedback**.

## The built-in slash commands

Type `/` in the chat box to see the menu (only shown if **Enable Slash Commands** is on in Settings). On your public site, visitors get `/help`, `/clear`, `/usage`, `/credits`, `/plan`, and `/docs <question>`. In your admin panel, you additionally get `/stats`, `/health`, and `/tickets` — `/docs` there answers from MakeAI's own built-in product documentation instead of your Knowledge Base.

Most commands (`/help`, `/usage`, `/credits`, `/plan`, `/stats`, `/health`, `/tickets`) answer instantly from local data with no AI call and no credit cost. Only `/docs` involves a real AI call (to compose an answer grounded in the retrieved content), so it's the one command that does spend credits.

## Why `/docs` might be missing from the menu

On the public site, `/docs` only appears if your Knowledge Base addon is installed and active — it has no product documentation of its own to fall back to. In the admin panel, it's always available since it's grounded on documentation bundled with your release.

## Reviewing feedback

Every finished assistant reply shows copy, thumbs-up, and thumbs-down icons. **Assistant Feedback** totals these as Total, Positive, and Negative counts with a computed satisfaction percentage, and lists every rated message with its text, any optional comment left, who left it (or "Guest"), and which page it happened on. This screen is read-only — reviewing feedback here doesn't take any action, it's for spotting patterns in what's working or not.

## How conversation history works

Signed-in visitors get a saved history of past conversations they can reopen or delete, and the assistant uses their recent messages as context automatically. Guests don't get saved history — their chat is temporary and clears once the browser session ends, with an on-screen note reminding them to sign in if they want it kept.

## Why a conversation isn't being remembered

- The visitor wasn't signed in — guest conversations are intentionally temporary and never saved server-side.
- The daily message limit for that visitor's tier (guest/member/pro) was reached — see [Setting Up the AI Assistant Addon](ai-assistant-addon-setup) for those limits.
