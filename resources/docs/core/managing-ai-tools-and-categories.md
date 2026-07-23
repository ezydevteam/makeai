---
title: Managing AI Tools and Categories
slug: managing-ai-tools-and-categories
page: managing-ai-tools-and-categories.html
section: AI
license: regular
keywords: [ai tool, create tool, custom tool, add tool, category, enable disable tool, deactivate tool, prompt, prompt template, system prompt, access level, generator, no code, tool not visible, tool fields, field key, category access]
---

**Admin → AI Management → Tools** is where every AI generator on your site — a blog writer, an image maker, anything customers run — is built and managed. Each tool is its own self-contained page with its own prompt, form fields, and output settings.

## Turning a tool on or off

Every row in the Tools list has a toggle switch that activates or deactivates it instantly — an inactive tool disappears from the customer-facing site immediately. Select multiple rows with the checkboxes to activate, deactivate, or delete several tools at once.

## Building a new tool

Click **Add Tool**. The editor is split into tabs: **Basic** (name, category, icon, color, access level), **Prompts** (the system prompt and user prompt template the AI actually receives — use `{field_key}` placeholders to pull in whatever the customer typed), **Fields** (the input form itself — add text boxes, dropdowns, sliders, toggles, and more, each with its own key and label), **Content** (the About text, How It Works steps, examples, and FAQs shown on the tool's page), and **SEO** (meta title, description, social image). No coding is required — the prompt template is where the tool's actual behavior is defined.

## What Categories control

Categories aren't just for tidying up the tools list — they can gate access too. Every category has its own **Access Level**. Any tool left on "Inherit" for its own access level picks up its category's access level instead of the site-wide default, so changing a category's access level can silently change access for every tool inside it left on Inherit.

## Why a tool isn't visible to customers

- The tool's **Active** toggle is off.
- The tool's **Access Level** (or its category's, if the tool is set to Inherit) requires a login or a specific plan the customer doesn't have — see [AI access control](ai-access-control).
- The tool's assigned model has no working provider key — see [Adding an AI provider key](ai-provider-keys).
