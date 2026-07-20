---
title: Setting Up the AI Image Pro Addon
slug: ai-image-pro-addon-setup
page: ai-image-pro-addon-setup.html
section: Addons
license: regular
keywords: [ai image pro, image generation, image models, background removal, upscale, inpaint, outpaint, style transfer, stability ai, replicate, remove.bg, clipdrop, fal.ai, ideogram, operation registry]
---

The **AI Image Pro** addon is a full image-generation and editing suite — text-to-image, variations, background removal, upscaling, inpainting, outpainting, object removal, style transfer, plus free local tools like resize and compress. Every operation, its provider, its access level, and its cost is controlled from **AI Tools → AI Image Pro → Settings**, across nine tabs.

## Turning it on

**General** tab: **Enable AI Image Pro** switches the whole addon on. Set separate **Studio Access Level** and **Library Access Level** (Guest, Login Required, or Premium) to control who can generate images at all versus who can save a personal library of past results.

## Enabling and pricing individual operations

**Operations** tab: one row per operation — a toggle, its access level, which provider handles it, and its credit cost. Sixteen operations ship enabled by default, grouped into three kinds:

- **Create** — Generate, Variations, Edit with Prompt. These run on whatever image model the customer picks, so their price is set per model (see below), not here.
- **Enhance** — Remove Background, Replace Background, Upscale, Erase & Replace, Remove Object, Expand (outpaint), Style Transfer. Each has a flat, admin-set credit cost regardless of which model handles it.
- **Adjust** — eight free local tools (Resize, Crop, Rotate & Flip, Compress, Convert Format, Watermark, Add Text, Adjust Colors) that run on your own server, not a paid AI provider. Free by default, but you can put a credit price on any of them.

An operation shows a warning if its assigned provider has no API key configured yet.

## Setting up generation

**Generation** tab: write the Studio's heading text, choose which image models are enabled (leave empty to allow every active model) and which one is the default, decide whether customers can pick their own model, and set the maximum number of variants generated per request. The **Per-image Credit Price** table lists every enabled model with its real cost and lets you override the price per model — leave a model blank to charge its actual computed cost. You can also turn on an optional negative-prompt field, a seed field, and set the aspect ratios offered in the composer.

## Connecting image providers

**Providers** tab: API keys for six providers used by the Enhance and premium generation operations — Stability AI, Replicate, Remove.bg, Clipdrop, fal.ai, and Ideogram. Leaving a field blank keeps whatever key is already stored. Ordinary text-to-image generation instead uses the models and keys already configured under **AI Management → Providers**, not this tab.

## Why an operation isn't available to a customer

- The operation's toggle is off on the **Operations** tab.
- Its assigned provider has no API key set — check the Providers tab.
- The customer's account doesn't meet the operation's access level (Guest/Login/Premium).
- They've hit their daily operation limit — see [AI Image Pro Limits and Customization](ai-image-pro-limits-and-customization).
