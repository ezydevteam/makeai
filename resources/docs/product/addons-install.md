---
title: Installing and Activating Addons
slug: addons-install
section: Addons
license: regular
keywords: [addon, plugin, install, activate, deactivate, extension, module]
---

Addons extend MakeAI without modifying its core. Each one is self-contained: its own
routes, migrations, settings and admin screens live inside its own folder, and it only runs
when it is active.

## Installing

Upload the addon from **Admin → Addons**. Installing runs the addon's migrations, registers
its settings, and adds its own entries to the admin menu.

Being present on disk is not the same as being installed. An addon folder that was shipped
with the release but never installed has no database tables — features that depend on it
must be treated as absent, not merely as switched off.

## Activating and deactivating

An installed addon can be activated or deactivated from the same screen. Deactivating stops
the addon's service provider from booting at all: its routes disappear, its scheduled jobs
stop, and any feature that integrates with it sees it as unavailable.

Deactivating does **not** drop its tables or delete its settings. Reactivating restores the
addon with all of its data intact, which makes deactivation a safe way to switch a feature
off temporarily.

## When one addon depends on another

Some features span two addons — for example, chat features that answer from your Knowledge
Base need the Knowledge Base addon to be installed *and* active. When the dependency is
missing, the dependent feature hides itself rather than failing: the related toggle
disappears from the settings screen, and answers simply come back ungrounded.

If a toggle you expect is missing from an addon's settings, check whether the addon it
depends on is installed and active. That is nearly always the cause.
