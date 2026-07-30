---
title: The AI Image Pro Studio, Library, and How Credits Are Charged
slug: ai-image-pro-studio-and-credits
page: ai-image-pro-studio-and-credits.html
section: Addons
license: regular
keywords: [image studio, prompt composer, image library, folders, favorite image, credits per image, flat credit cost, free image tools, image overview stats, how many credits does upscale cost, cost of an operation, credits per operation]
---

Customers generate and edit images in the **Studio**, keep results in their **Library**, and every operation is billed one of three different ways depending on what kind of operation it is.

## What customers see in the Studio

The prompt composer has a text box, an aspect-ratio picker, a style picker (if you've set up Style Presets), a variant-count picker, and — if you've allowed model choice — a model picker. Advanced fields (negative prompt, seed) appear only if you've turned them on. A **Tools** button opens Create/Enhance/Adjust operation panels; any tool the customer isn't allowed to use shows as locked with a "Sign in" or "Upgrade" prompt rather than being hidden entirely. Operations that need a mask (Erase & Replace, Remove Object) or canvas extension (Expand) open a dedicated editor with a brush tool.

## What customers see in the Library

The Library (requires an account) lists past results with search, filters by source (Generated/Edited/Uploaded), a favorites filter, and a folder sidebar for organizing images. A storage meter shows used space against your configured cap. Customers can multi-select images for bulk favorite/move/delete, and each image shows its dimensions, file size, prompt, model, and creation date.

## How credits are actually charged — three different ways

- **Generate, Variations, and Edit with Prompt** are priced **per model**, not a flat number — the price is whatever you set for that specific model on the Generation tab's Per-image Credit Price table (or its real computed cost if you leave it blank). Two different models can legitimately cost different amounts for the same action.
- **The seven Enhance operations** (Remove Background, Replace Background, Upscale, Erase & Replace, Remove Object, Expand, Style Transfer) each have a single **flat credit cost** you set on the Operations tab — the same price no matter which provider or model handles it. This is charged up front and automatically refunded if the job fails.
- **The eight local Adjust tools** (resize, crop, rotate & flip, compress, convert format, watermark, add text, adjust colors) are **free by default**, since they run on your own server rather than a paid AI provider — you can still put a credit price on any of them individually.

## Reading the admin Overview stats

**AI Tools → AI Image Pro → Overview** shows Jobs, Images, Credits, and a Failure Rate stat (shown in neutral color since a rising failure rate is bad news, not good), each compared to the prior period. Below that: usage broken down by operation and by model, and a table of the 15 most recent failed jobs with their error messages.

## Why a generation failed or credits look wrong

- Check the **Recent Failures** table on Overview for the exact error message — most failures trace back to a missing or invalid API key for that operation's provider.
- A blocked/moderated image is never charged — if a generation seems to have produced nothing but no credits were deducted, content moderation likely rejected the output.
- A flat-cost Enhance operation failed — credits for it are refunded automatically, so a failed job shouldn't leave a customer short.
