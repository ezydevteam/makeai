---
title: Core Site Settings — General, Features, and Social Counters
slug: core-site-settings
page: core-site-settings.html
section: Settings
license: regular
keywords: [site name, tagline, currency, timezone, language, feature toggles, follow counters, social counts, enable blog, enable registration]
---

**Admin → Settings → General**, **Features**, and **Social** cover your site's basic identity, which major features are switched on, and the follow-counter numbers shown on your public pages.

## Site identity, URLs, and locale

**General** has three sections: **Site Identity** (Site name, Tagline, Description), **Site URL & Links** (Site URL, Support email, Support URL, Terms of Service URL, Privacy Policy URL), and **Language, Currency & Timezone** (Default language, Timezone, Default currency, Currency symbol, Currency position, Currency decimals). Picking a currency auto-fills its symbol, position, and decimal count from a built-in catalog — you can still edit any of those afterward.

## Turning major features on or off

**Features** is a flat list of toggles: Premium Subscriptions, Affiliate Program, Support Tickets, Contact Form, Blog, Notifications, User Registration, Email Verification, and Tools Review Approval. Premium Subscriptions and Affiliate Program only appear at all on an Extended license — both are monetization features enforced server-side regardless of what's submitted, so there's no way to turn either on from a Regular license.

## Showing social follow counts

**Social** covers eight fixed networks — Facebook, X, Instagram, LinkedIn, YouTube, TikTok, GitHub, and Discord — each independently set to either **Manual** (you type in a static follower count) or **API** (fetched automatically using a provider key, on a refresh interval you set in hours). A global **Default display mode** controls whether counters show as icons only, icon plus count, or full cards.

## Why a feature or counter isn't showing

- **Premium Subscriptions** or **Affiliate Program** won't show as toggleable at all on a Regular license — this is enforced by the license type, not a setting you can override.
- A social counter reads zero or stale — check whether it's set to **API** mode with a valid key, and that the refresh interval has actually elapsed since the key was added.
- A disabled feature (like Blog or Contact Form) hides its entire sidebar section and public pages, not just a single screen.
