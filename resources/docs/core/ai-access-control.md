---
title: Controlling Who Can Use Which AI Tools
slug: ai-access-control
page: ai-access-control.html
section: AI
license: regular
keywords: [access level, access control, guest, login required, premium, plan gating, restrict tool, tool visibility]
---

**Admin → AI Management → Access Control** lets you decide, tool by tool, who is allowed to use it: anyone, only logged-in users, or only customers on a specific paid plan.

## The access levels

Each tool (or its category, if the tool is set to Inherit) can be set to one of: **Inherit** (use the category's level, or the site-wide default if the category is also Inherit), **Guest** — usable with no login at all, **Login Required** — must have an account, **Premium (Any Plan)** — must have any active paid subscription, or a specific **Plan: {Plan Name}** level generated automatically for each of your pricing plans.

## Changing access for one or many tools at once

The Access Control table lists every tool with a **Current Access** badge and an inline edit link. Filter by category or current access level to find what you're looking for, then tick multiple rows and use the bulk **Access Level** selector to apply one setting to all of them at once — much faster than editing tools one by one when reorganizing a whole category.

## Why a customer says a tool is "locked" or missing

- Their account doesn't meet the tool's access level — most often, the tool requires a specific paid plan they're not subscribed to.
- The tool is set to **Inherit** and its **category's** access level is more restrictive than expected — check the category, not just the tool, per [Managing AI tools and categories](managing-ai-tools-and-categories).
- A guest (not logged in) will never see anything above **Guest**-level tools, regardless of what the tool's page otherwise shows.
