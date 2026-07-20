---
title: Branding Your Site — Logo, Colors, Header, and Footer
slug: branding-your-site
page: branding-your-site.html
section: Appearance
license: regular
keywords: [logo, favicon, colors, theme settings, header, footer, typography, fonts, custom css, custom code, dark mode logo, brand, presets, theme presets, apply preset, restyle site]
---

**Admin → Appearance → Themes → Settings** is where you make MakeAI look like your own brand — name, logo, colors, header, footer, fonts, and custom code, all in one screen organized into tabs.

## Applying a theme preset

The **Presets** tab is the first thing you see on this screen. Each preset is a ready-made look — its card shows a live color mockup, a name, a description, and a Light/Dark badge, with **Current** marking whichever one (if any) matches your site right now. Click a preset to open a confirmation showing exactly which sections it will overwrite (for example, "Colors & Typography, Header, Footer"); everything else on your site is left untouched. Applying a preset **cannot be undone**, so it's worth noting your current colors first if you want to go back to them.

Presets aren't something you create from this screen — they ship as JSON files inside your active theme's `presets/` folder. If the Presets tab is empty, your current theme simply doesn't include any yet.

## Setting your logo and site identity

On the **General** tab, under **Site Identity**, upload a **Logo (Light)** and a separate **Logo (Dark)** — the dark version is used automatically when a visitor's browser is in dark mode. You can also set a **Favicon** (ICO and PNG) and an **Open Graph Image** for social sharing. Your actual site name is set separately, under **Settings → General → Site Identity**, and updates everywhere automatically once saved.

## Choosing your colors and fonts

The **Colors** tab has eight named color pickers that control your whole palette: Primary, Secondary, Accent, Body Background, Heading Color, Body Text, Muted Text, and Border Color. Header and footer have their own separate color pickers on their own tabs, so a color changed here won't automatically change those. The **Typography** tab picks fonts from curated dropdowns — Body Font, Heading Font, Base Font Size, Heading Weight, Line Height, Letter Spacing — rather than free font uploads.

## Customizing header and footer

The **Header** tab covers desktop and mobile navigation separately: layout, sticky behavior, which menu powers the nav links, colors, and which header elements show (notifications, social icons, search, login/register buttons). The **Footer** tab starts with a Footer Style picker (six layout presets), then lets you drag and drop content blocks — About Text, Logo, Menu, Contact Info, Custom Text, Categories, Newsletter — into columns, plus a Bottom Footer Section for the copyright line and payment icons.

## Adding custom CSS or tracking code

The **Custom Code** tab has three fields: Custom CSS, Custom Header Code, and Custom Footer Code — there's no separate JavaScript field, so scripts go in the header or footer code boxes.

## Why a branding change isn't showing

- You edited the wrong theme — **Appearance → Themes** lists every installed theme, and only the **active** one's settings affect the live site.
- The change needs a page reload or a cache clear (the admin panel's three-dot menu has a **Clear Cache** action) to appear.
- A dark-mode logo wasn't set, so visitors in dark mode still see the light logo, which can look wrong against a dark header.
- You applied a preset expecting it to change everything — presets only overwrite the sections listed in their confirmation dialog (commonly colors, typography, header, footer, and homepage); logo, favicon, and site identity are never touched by a preset.
