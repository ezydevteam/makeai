---
title: Keeping Your Website Updated
slug: updating-your-site
page: updating-your-site.html
section: System
license: regular
keywords: [update, new version, apply update, rollback, changelog, upgrade, update version, backup before update]
---

**Admin → System → Update Version** checks for and installs new releases of MakeAI safely, without touching a config file.

## Checking for updates

Click **Check for Updates** to see either "You are up to date" or a notice with the new version number and its changelog. An update banner also appears automatically elsewhere in the admin panel when a new version is available — snooze it for 24 hours or dismiss it for that version specifically.

## Backing up before you update

MakeAI automatically backs up your database as the very first step of every update, before anything else changes — so a failed or interrupted update has something to recover from. That automatic backup isn't a substitute for your own full-site backup (files included): use your hosting provider's backup tool beforehand for real peace of mind, especially before a major version update.

## Installing the update

Click **Apply Update** and let it run — MakeAI downloads the new version, backs up your database, and applies the changes automatically. If you were given an update file directly instead, use the manual upload option to apply it from a `.zip` file rather than downloading it. If something goes wrong shortly after updating, a **Rollback** option is available for 24 hours after the update, reverting to the backup taken just before it ran.

## Why an update failed or something broke afterward

- The update process was interrupted (lost connection, hosting timeout) — use **Rollback** within 24 hours to revert to the pre-update backup.
- A file permission issue on your hosting blocked the update from writing new files — check your hosting's error log for the exact permission error.
- After updating, clear the cache from the admin panel's three-dot menu — a stale cache can make an otherwise-successful update look broken.
