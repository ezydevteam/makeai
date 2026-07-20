---
title: Coupons, Transactions, and Credit Top-Up Settings
slug: coupons-transactions-and-credit-settings
page: coupons-transactions-and-credit-settings.html
section: Payments
license: extended
keywords: [coupon, discount code, transactions, payment history, refund, approve payment, bank transfer proof, credit top-up, buy credits, quick amounts, bonus credits]
---

**Admin → Premium** has three more screens beyond Plans and Gateways: **Coupons** for discount codes, **Transactions** for your payment history, and **Credits** for how customers top up their balance.

## Creating a coupon

A coupon has a code, a discount type (**Percent** or **Fixed**), and a value, plus optional limits: a max discount cap, a total redemption cap, a per-user redemption limit, restricting it to one plan, and restricting it to a segment of users (all/active/inactive/free/pro/recently joined). Applying a coupon always converts that purchase into a **one-time charge** for the discounted total — a coupon can never discount an ongoing recurring subscription, even on a monthly or yearly plan.

## Reviewing transactions

**Admin → Premium → Transactions** lists every payment attempt with the customer, item purchased, amount, gateway, status, and date. For a **Bank Transfer** payment sitting at **Pending**, open the row to view the uploaded payment proof, then **Approve** or **Reject** it — approving activates the plan or adds the credits immediately; rejecting accepts an optional note explaining why. There is no refund button on this screen — refunds must be issued from the gateway's own dashboard (Stripe, PayPal, etc.).

## Configuring how customers buy extra credits

**Admin → Premium → Credits** controls credit top-ups: a master **Top-Up Availability** toggle, a **Minimum Top-Up Amount**, a list of **Quick Select Amounts** shown as preset buttons at checkout, and **Bonus Credit Tiers** that award extra credits for larger purchases (for example, "spend $50 or more, get a 10% bonus"). The actual **price per credit** itself is not set here — it's the same value as **Price per credit** under AI Management → Providers → Credit Economics, kept in sync wherever you edit it.

## Why a coupon or top-up isn't working

- A coupon shows "invalid" — check its **Starts on**/**Expires on** dates and whether it's restricted to a specific plan the customer isn't buying.
- A percent coupon can never exceed 100% — the system rejects any attempt to save one above that.
- Top-ups aren't showing as an option for customers — the **Top-Up Availability** toggle on the Credits page is off.
- A Bank Transfer payment isn't activating — it's sitting in **Pending** on the Transactions page waiting for manual approval.
