---
title: Installing and Activating Addons
slug: addons-install
page: addons-install.html
section: Addons
license: regular
keywords: [addon, plugin, install, activate, deactivate, extension, module, install a new addon, how do I install an addon, upload addon zip, addon won't install, upload failed, zip too large, enter license, addon not showing, activate missing, addon license code]
---

Addons extend MakeAI without modifying its core. Each one is self-contained: its own
routes, migrations, settings and admin screens live inside its own folder, and it only runs
when it is active.

## Installing

Upload the addon's `.zip` file (max 20 MB) from **Admin → Appearance → Addons** using the
**Upload Addon** button. Installing runs the addon's migrations, registers its settings, and
adds its own entries to the admin menu.

Being present on disk is not the same as being installed. An addon folder that was shipped
with the release but never installed has no database tables — features that depend on it
must be treated as absent, not merely as switched off.

## Activating, deactivating, and licensing

An installed addon can be activated or deactivated from its row's action menu on the same
screen. Some addons require a license: if one does, the action menu shows **Enter License**
instead of **Activate** until you paste in a valid Envato purchase code — this code is
separate from your MakeAI core purchase code. Select multiple addons with the checkboxes to
activate or deactivate several at once.

Deactivating stops the addon's service provider from booting at all: its routes disappear,
its scheduled jobs stop, and any feature that integrates with it sees it as unavailable.

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

## Why an addon won't install or activate

- **The upload is rejected.** The `.zip` is over the 20 MB limit, or it isn't a valid addon
  package. Re-download it from your account and upload the original file as-is — don't unzip
  and re-zip it first.
- **The row shows Enter License instead of Activate.** That addon requires its own Envato
  purchase code, separate from your MakeAI core code. Paste the addon's code to unlock the
  Activate action.
- **The addon's features never appear even though its folder is on the server.** Being
  present on disk is not the same as being installed — upload it through **Upload Addon** so
  its migrations run and its tables are created. A folder copied in by hand is treated as
  absent.
- **A toggle or feature is missing from an addon's settings.** It almost always depends on
  another addon that isn't installed and active, as described above. If the dependency is
  present, check the Addon Manager for an **UPDATE** badge — an addon running behind its
  packaged version can hide or misbehave on newer features until it is updated.
