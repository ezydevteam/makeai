---
title: Adding an AI Provider Key
slug: ai-provider-keys
page: connecting-your-ai.html
section: AI
license: regular
keywords: [openai, anthropic, gemini, api key, provider, byok, credentials, connection, 401, invalid key, test connection, connection failed, nothing generates, generation not working, default model, fallback model, rotate key, no models, model missing]
---

MakeAI does not ship with AI credentials. Before any tool, chatbot or assistant can
generate anything, you must add at least one provider API key of your own. Keys are stored
in the app's central key pool, so you rotate a key in one place and every feature picks up
the change.

## Adding a key

Go to **Admin → AI Management → Providers**. Each provider card has an **Add Key** action.
Paste the key you obtained from that provider's own dashboard and save.

Use **Test Connection** on the provider card immediately after saving. This performs a real
call against the provider and is the fastest way to tell an invalid key apart from a
misconfigured model — the two failures look identical from the front end.

## Choosing the default and fallback model

The same screen has two separate model pairs, and mixing them up is the most common
misconfiguration:

- **Default Provider / Default Model** — used whenever a tool or feature has no model of its
  own configured ("inherit"). This is what most of your AI tools will actually run on.
- **Fallback Provider / Fallback Model** — only kicks in when the primary request hits a
  quota, rate-limit, or provider-level server error. It is a backup for failures, not a
  second default, and is never used for a normal successful request.

A model only appears in either list if it is **active** and its provider has a working key.
If a model you expect is missing, check the key first — an inactive provider hides all of
its models at once.

## Rotating or removing a key

Deleting a key on the provider card takes effect immediately. Any feature configured to use
a model from that provider will start failing on the next request, so add the replacement
key before removing the old one.

## Why generation still fails with a valid key

Three things other than the key can stop generation, in the order worth checking:

- The **daily AI budget** has been reached, which blocks generation site-wide until the
  budget resets. The AI Assistant's `/health` command reports this directly.
- The user has run out of **credits** — see [Credit Modes — Quota vs Metered](credit-modes),
  because what "out of credits" means depends on which mode the install is running in.
- The model is **inactive**, or belongs to a provider whose key was removed.
