---
title: Managing Pricing Plans
slug: managing-pricing-plans
page: managing-pricing-plans.html
section: Payments
license: extended
keywords: [plan, pricing, stripe price id, paypal plan id, trial, credits allowance, featured plan, country pricing, lifetime plan, cant create plan, plan not showing, edit plan, monthly yearly lifetime, trial days, plan settings, price per credit]
---

**Admin → Premium → Plans** is where you set what each pricing tier gives a subscriber. Plans ship pre-seeded with your install — there is no "Create Plan" button, since plans map to fixed slots the product already understands; you edit the existing plan cards instead of adding new ones from scratch.

## Editing a plan's price and credits

Open a plan card to set its **Monthly credits** allowance, and its pricing under **Default Pricing**: separate Original/Discounted price pairs for Monthly, Yearly, and Lifetime. A live helper compares the plan's price-per-credit against your store's default credit cost and warns if it's a steep discount, so you don't accidentally underprice a plan relative to what the credits actually cost you.

## Connecting a plan to Stripe or PayPal for recurring billing

Paste the **Stripe monthly/yearly price ID** and/or **PayPal monthly/yearly plan ID** into the matching fields — these are what turn on automatic recurring billing for that cycle. There is no recurring ID field for Lifetime, since a lifetime plan is always a single one-time charge. Leaving these blank still lets the plan sell, but as a one-time purchase per period rather than an auto-renewing subscription.

## Trials, featured plans, and per-country pricing

**Default trial days** and **Trial all countries** set a free trial period for the plan. **Featured plan** highlights one plan at a time on your pricing page (only one plan can be featured). Add **Plan Features** as free-text bullet lines shown on the pricing card. Use **Manage Country Pricing** to override price, VAT, and trial settings for specific countries — useful for adjusting to local purchasing power without running separate plans.

## The global Plan Settings

Separately from individual plans, **Plan Settings** (a separate modal from the Plans page) controls which billing cycles are shown at all (Show Monthly/Yearly/Lifetime), whether prices auto-localize by visitor location, which plan a newly registered user is assigned by default, and the button/label text shown on your pricing page.

## Why a plan isn't purchasable

- No working [payment gateway](payment-gateways) is configured — a plan with no gateway behind it renders a checkout the customer can't complete.
- The subscriptions master switch is off — see [Subscriptions and Plans](subscriptions-and-plans).
- The plan's **Show plan** toggle is off, hiding it from the pricing page entirely.
