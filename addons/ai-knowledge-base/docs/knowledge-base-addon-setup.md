---
title: Setting Up the AI Knowledge Base Addon
slug: knowledge-base-addon-setup
page: knowledge-base-addon-setup.html
section: Addons
license: regular
keywords: [knowledge base, help center, kb settings, ai answers, top-k chunks, embed widget, kb install code, vote buttons, guest search, ai grounding]
---

The **AI Knowledge Base** addon builds a public help center at your own URL — visitors search it and get AI-generated answers cited to your own articles, not general AI knowledge. Configure it from **Appearance → Addons → AI Knowledge Base → Settings**, across three sections.

## Basic setup

**General**: **Enable Public KB** switches the whole help center on; leave it off while you're preparing content. Set a **Public URL Slug** (default `help`), a **Page Title**, and a **Page Meta Description**. Upload a **Help Center Logo**, and pick which of your site's menus appear as the header and footer navigation. **Show Vote Buttons** lets visitors mark an article helpful or not; **Allow Guest Search** controls whether visitors need to be logged in at all — turn it off and every KB page requires sign-in.

## Tuning the AI answers

**AI Configuration**: choose an **AI Provider** and **AI Model** (only providers with an active key show up). **Top-K Chunks** controls how many article passages get pulled into each answer — higher values give the AI more context but can slow responses down. **Max Answer Tokens** caps how long an answer can be. An optional **Answer System Prompt** lets you set tone or persona — the underlying rule that answers must come only from your KB content, with sources cited, is always applied on top of whatever you write here.

## The floating widget

**Widget**: a separate, optional floating search widget you can drop onto any page on your site (not just the KB itself) — turn it on with **Enable Widget**, pick an **Accent Color**, and copy the install code snippet shown on the same screen.

## Why this addon also affects your AI Chatbot

If you also run the **AI Chatbot** addon, its own "Enable Knowledge Base" toggle uses this same article content to ground chatbot answers — meaning articles you publish here can improve chatbot answers too, not just your KB search box.

## Why an AI answer isn't showing, or looks generic

- No articles are **Published** yet, or their embedding is still **Pending/Processing** — check the Embed Status chart on Analytics.
- **Enable Public KB** is off, hiding the whole help center regardless of AI Configuration.
- The visitor's question doesn't closely match any published article — the AI is instructed to answer only from your content, so an unrelated question gets a fallback rather than a general-knowledge answer.
- The site-wide daily AI budget has been reached — the KB gracefully falls back to a "temporarily unavailable" message rather than failing.
