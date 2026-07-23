---
title: Subscriptions and Plans
slug: subscriptions-and-plans
page: subscriptions-and-plans.html
section: Payments
license: extended
keywords: [subscription, plan, billing, stripe, paddle, checkout, recurring, upgrade, pro, subscriber not pro, pro not working, master switch, metered mode, subscription lapsed, grant plan, pro only]
---

Selling recurring plans requires an **Extended license**. On a Regular license there is no
paid tier to sell, and the subscription features described here are not available.

## Turning subscriptions on

Subscriptions have a master switch that must be enabled before any plan can be sold. Until
it is on, the install behaves as though it had no paid tier at all: pricing pages have
nothing to show, "pro only" access rules collapse to "any signed-in user", and credits stay
in quota mode.

Enabling subscriptions is also what moves the install into **metered credit mode**, where
credits become a spendable, top-up-able wallet rather than a resetting allowance. This is a
significant behavioural change across the whole product, not just the billing screens — see
the credit modes documentation before switching it on.

## Creating a plan

A plan defines what a subscriber gets: their credit allocation, which models and tools they
may reach, and their price and billing interval. Plans are what "pro" means throughout the
product — access rules that say "pro only" are asking whether the user holds an active
paid plan, a lifetime plan, or a plan granted to them by an admin.

## Gateways

Configure at least one payment gateway before making a plan purchasable. A plan with no
working gateway behind it will render a checkout the user cannot complete.

## Why a subscriber is not being treated as pro

- The subscriptions master switch is off, which makes *every* user non-pro regardless of
  what they have paid.
- Their subscription has lapsed. Renewals are processed by the scheduler, so if cron is not
  running, active subscriptions will silently start expiring.
- The plan was granted directly rather than purchased, and has an end date that has passed.
