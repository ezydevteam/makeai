---
title: Setting Up the AI Chatbot Addon
slug: ai-chatbot-addon-setup
page: ai-chatbot-addon-setup.html
section: Addons
license: regular
keywords: [ai chatbot, chat page, chatbot settings, custom models, chat modes, persona, guest chat, free tier limits, plan limits, chatbot logo, meta title]
---

The **AI Chatbot** addon adds a full, ChatGPT-style chat page to your own site at `/chat` — not an embeddable widget for other websites, but a real page on your domain that visitors navigate to (you can even set it as your homepage). Configure it from **AI Tools → AI Chatbot → Settings**.

## Turning features on

**General Settings**: **Enable Chatbot** turns the `/chat` page itself on or off. **Enable Knowledge Base** only appears if the Knowledge Base addon is installed and active, and lets chatbot answers ground themselves in your KB articles. **Enable File Upload** and **Enable Voice** (microphone input and read-aloud) toggle those composer features. Upload a **Chatbot Logo** to replace the default branding on the welcome screen and chat header.

## Choosing AI models and personas

**AI Model Controls**: pick a **Default AI Model**, and optionally let visitors pick their own with **Allow Users to Select AI Model**. **Available Modes** turns built-in personas (Code, Write, Design, Marketing, Social Media, Analyze, Image, Research, Mentor) on or off — each shown as a starter tile on the welcome screen. Use the **Custom Models Manager** to build your own named model presets, each with its own base model and a **System Prompt** that shapes its personality and instructions.

## Setting limits for guests and paying customers

**Guest Settings & Limits**: guests can't chat at all unless you turn on **Allow Guests to Chat** — off by default. When enabled, set caps on lifetime message count, tokens per request, chat history length, file upload size, and messages per 5 hours/week/month.

**Free Tier Limits** and one **"[Plan Name] Plan Limits"** section per paid plan you offer: each sets Credits per Message, Max Tokens per Request, Max Chat History Messages, Max File Upload Size, and messaging caps per 5 hours/week/month — this is how you shape how much chat usage each pricing tier actually gets.

## SEO for your chat page

**SEO**: Meta Title (falls back to "AI Chat"), Meta Description, and Meta Keywords for the public `/chat` page.

## Why a chatbot feature isn't available to a visitor

- **Enable Chatbot** is off, taking down `/chat` entirely.
- The visitor is a guest and **Allow Guests to Chat** is off — guests get a sign-in prompt instead of the chat composer.
- **Enable Knowledge Base** is off, or the Knowledge Base addon isn't active, so KB-grounded answers aren't available even if the toggle looks on.
- A **mode** or **custom model** was turned off in Available Modes / Show Custom Models, removing it from the welcome screen.
