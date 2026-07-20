# Subscription Plan Changes (Upgrade / Downgrade)

Your users can change plans from the **Pricing** page. The exact behaviour
depends on whether their current subscription is **recurring** (a card/account is
kept on file and billed automatically — **Stripe** and **PayPal** subscriptions)
or a **one-time payment** (Paddle, bank transfer,
Razorpay/Paystack/SSLCommerz/CoinGate/2Checkout — a single payment per period, no
card on file).

> **Stripe and PayPal** support automatic recurring billing and in-place plan
> changes. **Paddle and the other gateways are one-time payments**, so plan
> changes on them follow the one-time rules below (upgrade = new checkout,
> downgrade = ends at period end → Free).

**Upgrade (to a higher plan)**
- **Recurring (Stripe):** the plan is swapped immediately and the card is charged
  the **prorated difference** right away. Renewal date unchanged.
- **Recurring (PayPal):** the user is redirected to PayPal to **approve the new
  price** (PayPal requires this for an increase); once approved, the plan switches
  automatically. Renewal date unchanged.
- **One-time gateways:** the user goes through checkout and pays for the higher
  plan, with credit for the unused portion of their current plan deducted from the
  amount due. A fresh period starts on purchase.

**Downgrade (to a lower plan, or to Free)** — no charge, no refund; current
features stay active until the end of the paid period.
- **Recurring (Stripe), to a lower paid plan:** at the next renewal the account is
  billed the **lower** plan's price and switches to it automatically. The user can
  cancel the scheduled change any time before it applies.
- **PayPal, one-time gateways, or any downgrade to Free:** the current plan simply
  runs to the end of the period, then the account moves to **Free**, and the user
  purchases the lower plan again whenever they want. The user sees a clear message
  explaining this.

> **Why isn't a PayPal downgrade scheduled like Stripe's?** Changing the plan on a
> live PayPal subscription can require the **buyer to approve** the new plan, and
> until they do, PayPal keeps billing the **old** price. Scheduling the switch
> anyway would show the user a lower plan while PayPal still charged them the
> higher amount, so PayPal downgrades end the period cleanly instead. **Upgrades**
> are unaffected — there, the buyer *is* redirected to PayPal to approve.

> **PayPal + coupons/regional prices:** a PayPal *subscription* bills the fixed
> price defined in your PayPal plan, so if a coupon or a country-specific price
> applies at checkout, that purchase falls back to a **one-time** PayPal payment
> (the discount is honoured for that period). Plain base-price PayPal purchases are
> recurring.

Most of this works out of the box. A few things need to be set up correctly.

---

## 1. Enable the scheduler (required)

Scheduled downgrades (and trial reminders, renewals, etc.) are applied by
Laravel's task scheduler. Add this **single cron entry** on your server if you
have not already:

```
* * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1
```

> If subscriptions already renew/expire correctly on your site, this is already
> set up and you don't need to do anything here.

---

## 2. Stripe recurring plans: add each plan's Price IDs (only if you use Stripe subscriptions)

This step **only matters if you sell subscriptions through Stripe's recurring
billing**. If you don't use Stripe, skip to the PayPal section (3) or ignore both
if you only use one-time gateways.

**Why:** for a Stripe recurring subscription, the app tells Stripe to bill the
plan the user is moving to — the prorated difference now on an **upgrade**, or the
lower price at the next renewal on a **downgrade**. To do that, it needs the
Stripe **Price ID** of the target plan. If a plan has no Price ID, in-place
Stripe upgrades/downgrades to it can't be billed correctly (the user is told to
contact support, or the site switches the plan while Stripe keeps the old price),
so set them for every paid plan you sell through Stripe.

### Step by step

1. **In Stripe** → *Product catalog* → open (or create) the product for each plan.
2. Add a **recurring price** for the plan (monthly and/or yearly, matching the
   cycles you sell).
3. Copy the **Price ID** — it looks like `price_1AbcDefGhiJkl...` (starts with
   `price_`).
4. **In your admin panel** → **Premium → Plans** → edit the plan.
5. Paste the ID into **"Stripe monthly price ID"** and/or
   **"Stripe yearly price ID"**, then **Save**.
6. Repeat for every paid plan you offer on Stripe.

That's it. Once every paid plan has its Stripe Price IDs, upgrades and downgrades
bill the correct amount automatically.

### Cancelling a scheduled downgrade

If a user cancels their scheduled downgrade before it takes effect, the app
automatically tells the gateway (Stripe or PayPal) to keep billing the current
plan — no action needed from you.

---

## 3. PayPal recurring plans: add each plan's Plan IDs (only if you use PayPal subscriptions)

To sell **recurring** subscriptions through PayPal, each plan needs a PayPal
**Plan ID**. Without one, PayPal purchases for that plan stay **one-time**
(they still work — they just don't auto-renew or support in-place plan changes).

**Why:** a PayPal subscription is tied to a PayPal *billing plan* (which defines
the price and cycle) created in your PayPal account. The app needs that plan's ID
to start the subscription and to switch it on an upgrade/downgrade.

### Step by step

1. **In PayPal** (Developer Dashboard or the REST API), create a **Product**, then
   a **Billing Plan** for each cycle you sell (monthly / yearly) at the plan's base
   price and currency.
2. Copy each **Plan ID** — it looks like `P-5ML4271244454362WXNWU5NQ` (starts with
   `P-`).
3. **In your admin panel** → **Premium → Plans** → edit the plan.
4. Paste the IDs into **"PayPal monthly plan ID"** and/or **"PayPal yearly plan
   ID"**, then **Save**.
5. Repeat for every paid plan you offer on PayPal.

**Also point PayPal's webhook** at `https://your-site/webhooks/paypal` and enable
these events: `BILLING.SUBSCRIPTION.ACTIVATED`, `BILLING.SUBSCRIPTION.UPDATED`,
`BILLING.SUBSCRIPTION.CANCELLED`, `BILLING.SUBSCRIPTION.EXPIRED`,
`PAYMENT.SALE.COMPLETED` (and set the Webhook ID in the gateway settings). This is
how activations, renewals, plan changes, and cancellations sync back.

### Notes
- **Upgrades** redirect the buyer to PayPal to approve the higher amount; the plan
  switches once they approve.
- **Downgrades** are **not** scheduled on PayPal — the plan runs to the end of the
  period and the account moves to Free (see the downgrade note at the top).
- **Coupons / country-specific prices** fall back to a one-time PayPal payment
  (see the note at the top), because a PayPal plan bills a fixed price.
- **Lifetime** plans are always one-time (no recurring PayPal plan) — they are
  **bought from the pricing page**, never reached by scheduling a "downgrade".
