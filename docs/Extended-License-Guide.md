# Extended License — Setup Guide & FAQ

Welcome! 👋 This guide is for buyers running the app on an **Envato Extended License**,
who want to **charge end users** — via subscription plans and/or credit top-ups. It
walks through enabling billing, how credits and plans work, and answers common questions.

> **TL;DR:** An Extended License unlocks the paid system. Enable **Premium Subscriptions**
> in Features, set your **store currency** and **credit value**, create your **plans**,
> connect a **payment gateway**, and run the **scheduler**. Users then buy plans/credits;
> credits act as a real, purchasable wallet.

For a deep dive on gateways and recurring billing, also read
`Payments-and-Subscriptions-Guide.md`. For add-on development, see
`Addon-Credit-Charging-Guide.md`.

---

## 1. Turn billing on (the one switch that matters)

Billing is only active when **two** things are true:
1. Your license type is **Extended** (Admin → Settings → License), **and**
2. **Premium Subscriptions** is enabled in **Admin → Settings → Features**.

Until both are on, the app behaves like a free tool site (see the Regular License guide).
Once on, the pricing page, checkout, credit top-ups, and the affiliate program appear.

---

## 2. First-time setup (about 15 minutes)

1. **Set your store currency.** **Admin → Settings → General → Default currency.** Every
   price (plans, top-ups, coupons) uses this one currency. Visitors can see prices
   auto-localized to their own currency, but you're always billed and paid in your store
   currency (or an explicit per-country price if you set one).
2. **Set the value of a credit.** **AI Management → Global Settings → Credit Economics →
   Price per credit.** This is what one credit is worth (and what top-ups charge per
   credit). Model credit costs derive automatically from real provider cost × your
   **AI markup**, so you never sell AI below cost.
3. **Create your plans.** **Admin → Premium → Plans.** For each plan set the price,
   billing cycle, and **Monthly credits** (the allowance a subscriber gets each period).
4. **Choose what new users get.** In **Plans → Pricing Settings → "New user gets"**:
   - **No plan** — new users get the default starting credits.
   - **Custom credit limits** — no plan; governed by the per-user limits in AI Settings.
   - **A specific plan** (e.g. your Free plan) — auto-assigned on signup, with its
     credits granted and refreshed monthly.
5. **Connect a payment gateway.** **Admin → Premium → Gateways.** Enable one (Stripe,
   PayPal, Razorpay, Paystack, etc.), add its API keys, and set the webhook URL shown.
6. **Configure top-ups (optional).** **Admin → Premium → Credit Settings** — set the
   price per credit, minimum, quick amounts, and bonus tiers.
7. **Run the scheduler.** Renewals, trial reminders, allowance resets, and monthly
   credit refreshes all depend on it. Add this one cron line:
   ```
   * * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1
   ```

---

## 3. How credits work on an Extended License

Credits are a **real, purchasable wallet balance**:

- Each generation **deducts** credits from the user's balance (bigger models cost more).
- Users get credits from: their **plan's monthly allowance**, **top-up purchases**,
  admin grants, and affiliate/referral rewards.
- If a user's balance is too low, generation is blocked until they top up or renew.
- **Plan credits refresh each period** (see below). **Top-up credits are preserved** —
  a refresh never wipes purchased credits.

### Two independent controls (they don't conflict)
- **Plan "Monthly credits"** = the **balance** a subscriber receives each period.
- **Per-user daily/monthly limits** (AI Settings → Spend Controls) = optional **rate
  caps** on how fast that balance can be spent. Leave at `0` to disable.

Both apply together: a generation must pass the rate caps **and** have enough balance.
The effective monthly usable amount is `min(balance remaining, monthly limit − used)`.

### When plan credits are granted / refreshed
| Event | What happens |
|---|---|
| New subscription / upgrade | Wallet is topped up to the plan's credits |
| Recurring renewal (each billing cycle) | Allowance refreshed for the new period |
| Free-trial start | Plan's credits granted for the trial |
| Free plan (auto-assigned) | Refreshed monthly (calendar month) |
| Top-up purchase | Added on top (never wiped by a refresh) |

Refreshes are **reset-style but safe**: a spent-down allowance is topped back up to the
plan amount, and any balance above it (from top-ups or admin grants) is **preserved**.

---

## 4. Frequently asked questions

**Q: I enabled Extended but still see no pricing page.**
You also need to turn on **Premium Subscriptions** in **Settings → Features**. Both the
Extended license *and* that toggle must be on.

**Q: A subscriber's plan says "2000 monthly credits" — do they get 2000 every month?**
Yes, if the plan is assigned. Paid plans refresh on their **billing cycle** (each
renewal); a free plan assigned to new users refreshes on the **calendar month**. The
refresh tops the balance back up to the plan amount without wiping purchased top-ups.

**Q: If I set a per-user monthly limit AND a plan gives monthly credits, which wins?**
They're different things and both apply. The plan is the **balance** (what they have);
the limit is a **rate cap** (how fast they may spend it). Whichever is hit first blocks.
Leave the per-user limits at `0` if you only want the plan balance to matter.

**Q: Will a monthly refresh delete credits my user bought via top-up?**
No. Refreshes only top a balance **up** to the plan allowance; a balance already above
it (because of top-ups or admin grants) is left untouched.

**Q: What currency are my users charged in?**
Your **store currency** (Settings → General), or an explicit **per-country price** if
you set one for their country. Visitors may *see* prices in their local currency
(auto-localized for display), but the actual charge is your store/per-country currency.

**Q: How do I set what one credit is worth?**
**AI Management → Global Settings → Credit Economics → Price per credit.** It's shared
with Credit Settings (top-ups), so both stay in sync. Model credit costs derive from
real provider cost × your **AI markup**; click **Recalculate Credits** after changing
the markup.

**Q: How do I protect my provider bill?**
Set a **Global Daily AI Budget (USD)** in Spend Controls — AI pauses for the day once
your real spend reaches it. This is independent of what users have paid.

**Q: Do refunds/failed generations return credits?**
Yes — failed or partial generations are handled correctly, and failed media renders are
refunded. Everything is recorded in the user's credit transaction history.

**Q: Can I run the affiliate program?**
Yes, on an Extended License. Enable it in **Settings → Features → Affiliate Program**.
It pays commissions on real purchases, so it requires billing to be meaningful.

**Q: What if I disable subscriptions later (keep Extended)?**
Billing hides again and the app reverts to allowance-based usage, but **existing
subscribers can still view/cancel** their subscription from Billing. Their access is
respected until it ends.

---

## 5. Quick reference

| I want to… | Go to |
|---|---|
| Enable billing | Settings → License (Extended) + Settings → Features (Premium Subscriptions) |
| Set store currency | Settings → General → Default currency |
| Set credit value + markup | AI Management → Global Settings → Credit Economics |
| Create/edit plans | Premium → Plans |
| Choose what new users get | Premium → Plans → Pricing Settings → "New user gets" |
| Connect a gateway | Premium → Gateways |
| Configure top-ups | Premium → Credit Settings |
| Cap my provider bill | AI Management → Global Settings → Global Daily AI Budget (USD) |
| Run affiliate program | Settings → Features → Affiliate Program |

Happy selling! For gateway/webhook specifics, see `Payments-and-Subscriptions-Guide.md`.
