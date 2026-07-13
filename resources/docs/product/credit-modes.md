---
title: Credit Modes — Quota vs Metered
slug: credit-modes
section: Credits
license: regular
keywords: [credits, quota, metered, allowance, wallet, balance, top up, daily limit, tokenguard]
---

Credits mean two different things in MakeAI depending on how the install is licensed and
configured, and almost every confusing credit question traces back to not knowing which
mode is active.

## The two modes

**Quota mode** — credits are a *resetting allowance*. Users get a daily or monthly amount,
it refills on its own, and there is no way to buy more. This is the mode you are in on a
Regular license, and on an Extended license with subscriptions switched off.

**Metered mode** — credits are a *spendable wallet*. Users hold a balance, spend it down,
and can top it up. This requires an Extended license **and** subscriptions enabled.

The switch is not a setting you toggle directly. Metered mode turns on precisely when both
of those conditions hold; otherwise the install is in quota mode.

## What changes between them

In quota mode, nothing in the UI should invite a user to buy credits, because there is
nothing to buy. Features that quote a balance in metered mode instead quote the remaining
allowance and say that it resets. Running out is temporary and self-healing.

In metered mode, a user who reaches zero stays at zero until they top up. Generation is
refused rather than deferred.

## Why a user is being refused

Work through it in this order:

1. **Which mode is the install in?** Extended license plus subscriptions enabled means
   metered; anything else means quota.
2. **In quota mode**, check the user's daily limit and how much of it they have used today.
   A zero limit means unlimited, not blocked.
3. **In metered mode**, check the wallet balance.
4. **In either mode**, check the site-wide daily AI budget. It stops generation for
   *everyone* regardless of individual balances, and it is the cause that is easiest to
   miss because the user-facing error looks like a personal credit problem.
