---
title: AI Chatbot Analytics and How Usage Is Billed
slug: ai-chatbot-analytics-and-usage
page: ai-chatbot-analytics-and-usage.html
section: Addons
license: regular
keywords: [chatbot analytics, conversation history, chat sharing, chatbot credits, who pays for chatbot messages, model popularity, chatbot feedback]
---

**AI Tools → AI Chatbot → Analytics** reports on chat activity, and each of your logged-in customers pays for their own chatbot usage from their own credit balance.

## What Analytics shows

Four headline stats — Total Conversations, Total Messages, Total Tokens, Credits Charged — each compared against last week. Below that: a 30-day Conversations vs. Messages trend chart, a Model Popularity ranking, a Mode Popularity ranking (which built-in personas get used most), a Customer Satisfaction percentage from thumbs-up/down ratings, and a feed of the 10 most recent feedback comments.

## What a customer can do in the chat page

Signed-in customers get a full sidebar with chat history, projects, tags, and the ability to pin, rename, delete, share, or export (Markdown, JSON, or PDF) any conversation. A conversation can also be shared via a public unlisted link. Starting a new chat shows persona tiles (if modes are enabled) with starter prompts, or a visitor can just type.

## Whose account pays for a message

**Guests never spend credits** — if you allow guest chat at all, they're bounded only by the message-count and token caps you configure, not by credits. **Signed-in customers are charged from their own credit balance**, at the per-message rate set by their plan (Free Tier or a specific paid plan) in your addon settings — not billed to your own internal account. If a plan's "Credits per Message" is left at 0, that plan's messages are effectively free within its other limits.

## How conversation history is kept

Signed-in customers' conversations are tied to their account and persist indefinitely across devices. Guest conversations are tied to the browser session — they persist across page reloads in that same session, but aren't a permanent, cross-device history.

## Why credits charged doesn't match what you expected

- A plan's **Credits per Message** setting is 0 (unmetered) — check [Setting Up the AI Chatbot Addon](ai-chatbot-addon-setup) for where these live per plan.
- The conversation included guest messages, which are never charged regardless of plan settings.
- **Show Credits Charged** is off in General Settings, hiding the per-message cost from the chat UI even though it's still being tracked in Analytics.
