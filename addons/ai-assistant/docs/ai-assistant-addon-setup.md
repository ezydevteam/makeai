---
title: Setting Up the AI Assistant Addon
slug: ai-assistant-addon-setup
page: ai-assistant-addon-setup.html
section: Addons
license: regular
keywords: [ai assistant, floating widget, chat bubble, widget position, header button, persona, system prompt, assistant name, access control, daily message limit, message automation, canned reply]
---

The **AI Assistant** addon adds a floating chat widget to your site — one version for your own admin panel (a built-in helper that knows your product), and a separate, independently-configurable version for your public-facing site's visitors. Configure both from **Appearance → Addons → AI Assistant**, which opens a settings screen with six tabs.

## Turning it on for admins vs. the public site

**General** tab: **Enable in Admin Panel** and **Enable on Frontend** are two separate switches — you can run one without the other. **Widget Position** places it as a floating bubble (bottom-left or bottom-right) or, with **Header Button**, replaces the bubble with a button inside your theme's header (falls back to a bubble automatically on themes or screens without a header slot). **Auto Open on First Visit** pops it open once per browser session. **Hide on These Pages** accepts one path per line with wildcard support — login and installer pages are always excluded regardless of this list.

## Giving it a name and personality

**Persona** tab: set the **Assistant Name** and a short **Designation** (subtitle shown under the name), upload a square **Avatar**, and write a **Greeting Message** — leave it blank to have signed-in visitors greeted by their first name automatically.

## Choosing its AI model

**AI Model** tab: pick a **Provider** and **Model** — leave both blank to inherit your site-wide default model. **Max Tokens** caps how long a single answer can be; **Temperature** controls how creative vs. focused answers are (0 = focused and repeatable, 1 = more creative).

## Controlling who can use it and how much

**Access** tab: **Who Can Use It** (Everyone / Logged-in Only / Paid Plans Only), plus three separate daily message limits — **Guest** (counted per IP address), **Member**, and **Pro** (0 means unlimited on any of these). This is a hard cap on message *count*, separate from and in addition to normal AI credit spending.

## Writing custom instructions

**System Prompts** tab: separate instruction fields for the frontend and admin versions of the assistant — these are never sent to the browser, so they're safe for internal guidance the assistant shouldn't reveal.

## Setting up instant canned replies

**Message Automation** tab: define trigger phrases (matched as "Contains" or "Exact") with an instant reply — these fire without calling the AI at all, so they cost no credits. Replies support `{site_name}`, `{user_name}`, `{user_email}`, and `{current_page}` placeholders.

## Why the widget isn't appearing

- Its **Enable in Admin Panel** or **Enable on Frontend** toggle is off for the surface you're checking — they're independent.
- The current page matches an entry in **Hide on These Pages**.
- **Widget Position** is set to Header Button but your theme has no header slot for it — it should fall back to the floating bubble automatically; if it doesn't appear at all, switch back to a bottom-corner position to confirm.
- **Who Can Use It** is set to Paid Plans Only and the visitor you're testing with doesn't have one.
