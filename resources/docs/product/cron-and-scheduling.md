---
title: Setting Up Cron
slug: cron-and-scheduling
section: Getting Started
license: regular
keywords: [cron, scheduler, schedule, background jobs, cleanup, renewals, crontab]
---

MakeAI relies on a scheduler for work that must happen without a user present: resetting
daily allowances, cleaning up expired uploads, processing renewals, and sending queued
notifications. If cron is not running, none of that happens — and the failure is silent.
The site keeps working, it just quietly stops doing anything on a timer.

## The cron entry

Add a single entry to your server's crontab that runs Laravel's scheduler every minute.
Everything else is scheduled inside the application, so this is the only line you ever need
to add — you do not add a cron entry per feature.

```
* * * * * cd /path/to/your/site && php artisan schedule:run >> /dev/null 2>&1
```

Replace the path with the directory containing `artisan`. On shared hosting, the control
panel's "Cron Jobs" screen is the same thing with a form around it.

## Checking that it works

The AI Assistant's `/health` command reports cron status directly, and is the quickest
check. It considers cron healthy if the scheduler has run within the last five minutes.

If it reports cron as not running:

- Confirm the path in the crontab entry actually contains `artisan`.
- Confirm the `php` in the crontab is the same PHP version the site runs on. A server with
  several PHP versions installed will often default the cron user to an older one, which
  fails on the application's minimum version requirement.
- Run the command by hand from an SSH session. If it works interactively but not on a
  timer, the problem is the crontab entry or its environment, not the application.

## What breaks without it

Daily credit allowances never reset, so quota-mode users are permanently stuck at whatever
they had spent when cron stopped. Subscription renewals are not processed. Temporary
uploads accumulate on disk. None of these produce an error at the time — they simply do not
happen, which is why cron is worth verifying at install rather than discovering later.
