# Payments & Subscriptions — Setup Guide

Welcome! 👋 This guide walks you through setting up payments — from enabling a
gateway, to creating recurring plans, to understanding exactly how billing works.
No prior payment-integration experience needed. Take it step by step.

> **TL;DR:** Enable a gateway → add its keys → (for auto-renewing plans) create
> the plan in Stripe/PayPal and paste its ID into your plan → set up the webhook →
> make sure the scheduler cron is running. That's it.

---

## 1. Before you start (3 prerequisites)

1. **Extended License.** Charging your users money (subscriptions, credit top-ups,
   affiliate payouts) requires Envato's **Extended License** — this is Envato's
   rule, not ours. On a Regular License the whole paid system stays hidden.
2. **Turn subscriptions on.** Go to **Admin → Settings → Features** and enable
   **Premium Subscriptions**. (This switch only appears on an Extended License.)
3. **Run the scheduler.** Renewals, scheduled downgrades, trial reminders, and
   expiries are driven by Laravel's scheduler. Add this **one cron line** on your
   server:
   ```
   * * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1
   ```
   If subscriptions already renew/expire on your site, this is already done.

---

## 2. Which gateway does what? (one-time vs recurring)

| Gateway | Auto-renewing subscriptions? | Notes |
|---|---|---|
| **Stripe** | ✅ Yes | Recurring **and** one-time. The most complete integration. |
| **PayPal** | ✅ Yes | Recurring **and** one-time. |
| **Paddle** | ❌ One-time | Single payment per period. |
| **Razorpay** | ❌ One-time | Hosted payment link. |
| **Paystack** | ❌ One-time | |
| **SSLCommerz** | ❌ One-time | |
| **CoinGate** | ❌ One-time | Crypto. |
| **2Checkout** | ❌ One-time | |
| **Bank Transfer** | ❌ One-time | Manual — you approve each payment in the admin. |

**"One-time" doesn't mean broken** — the user still gets full access for the period
they paid for. It just doesn't **auto-charge** them again. When the period ends,
their plan expires and they buy again (a **Renew** button makes this one click).

---

## 3. How billing works (the important concepts)

Read this once and the rest of the guide will click.

### Recurring vs one-time is decided *automatically* per purchase
You don't pick "subscription mode" anywhere. The system chooses based on the cart:

| The purchase is… | Result |
|---|---|
| Monthly/Yearly, **base price, no coupon**, on **Stripe or PayPal** | 🔁 **Recurring subscription** (auto-renews) |
| …but a **coupon** is applied | 💳 **One-time** charge (discount honoured, no auto-renew) |
| …but a **country-specific price** applies | 💳 **One-time** charge |
| **Lifetime** plan | 💳 **One-time** charge |
| Any **one-time gateway** (Paddle, Razorpay, bank transfer, …) | 💳 **One-time** charge |

### What applies to what
| Feature | One-time charge | Recurring subscription |
|---|---|---|
| **Coupons / discounts** | ✅ Applied | ❌ Base price only |
| **Country-specific prices** | ✅ Applied | ❌ Base price only |
| **VAT / tax** | ✅ Applied | ✅ (baked into the gateway price) |
| **Gateway processing fee** | ✅ Added | ❌ (gateway price is fixed) |

**Why the difference?** A recurring subscription is tied to a **fixed price** you
create in Stripe/PayPal, so it can't carry an arbitrary coupon or country price.
Those purchases automatically fall back to a one-time charge so the discount is
still honoured — the customer just doesn't auto-renew that discounted period.

### Renewals
Recurring renewals are confirmed by the gateway's **webhook** (see §5). When the
gateway charges the card again, it tells your site, and the plan is extended. This
is why **webhooks are required** for recurring gateways.

### Upgrades, downgrades & cycle switches
- **Upgrade** (higher plan, or monthly→yearly): charged the **prorated difference**
  immediately. On **PayPal** the buyer is redirected to approve the new amount.
- **Downgrade** (lower plan, or yearly→monthly): **scheduled for period end** — no
  charge, no refund; current features stay active until then, then it switches.
- **Downgrade to Free / on a one-time gateway**: ends at period end → Free.
- Full details in [Subscription-Plan-Changes.md](Subscription-Plan-Changes.md).

---

## 4. Set up any gateway (general steps)

1. Go to **Admin → Payment Gateways**.
2. Click the gateway → **enter its API keys/credentials** (from the gateway's own
   dashboard).
3. Toggle **Test / Sandbox mode** on while testing, off when you go live.
4. **Enable** the gateway.
5. Set an optional **processing fee** if you want to pass gateway costs to buyers
   (applies to one-time charges).

That's enough for **one-time** gateways. For **Stripe/PayPal recurring**, do the
extra steps below. ⤵️

---

## 5. Set up recurring plans (Stripe & PayPal)

A recurring subscription needs a matching **price/plan created inside the gateway**,
because the gateway is the one that auto-charges the card. You create it once per
plan+cycle, copy its ID, and paste it into your plan.

### 5a. Stripe

1. **In Stripe** → *Product catalog* → create (or open) a **Product** for the plan.
2. Add a **recurring Price** for each cycle you sell (monthly and/or yearly) at the
   plan's base price and currency.
3. Copy each **Price ID** — it looks like `price_1AbcDefGhiJkl…` (starts with
   `price_`).
4. **In your admin** → **Premium → Plans** → edit the plan → paste into
   **"Stripe monthly price ID"** and/or **"Stripe yearly price ID"** → **Save**.
5. **Webhook:** in Stripe → *Developers → Webhooks* → add endpoint
   `https://your-site.com/webhooks/stripe`, subscribe to the subscription/invoice
   events, and put the **signing secret** in your Stripe gateway settings.

### 5b. PayPal

1. **In PayPal** (Developer Dashboard, or via API) → create a **Product**, then a
   **Billing Plan** for each cycle (monthly and/or yearly) at the plan's base price
   and currency.
   > ⚠️ PayPal **auto-generates** plan IDs — you can't choose them. A real one looks
   > like `P-5ML4271244454362WXNWU5NQ` (starts with `P-`).
2. Copy each **Plan ID**.
3. **In your admin** → **Premium → Plans** → edit the plan → paste into
   **"PayPal monthly plan ID"** and/or **"PayPal yearly plan ID"** → **Save**.
4. **Webhook:** point PayPal at `https://your-site.com/webhooks/paypal` and enable
   these events, then paste the **Webhook ID** into your PayPal gateway settings:
   - `BILLING.SUBSCRIPTION.ACTIVATED`
   - `BILLING.SUBSCRIPTION.UPDATED`
   - `BILLING.SUBSCRIPTION.CANCELLED`
   - `BILLING.SUBSCRIPTION.EXPIRED`
   - `PAYMENT.SALE.COMPLETED`

> **No plan ID set?** The plan still sells on Stripe/PayPal — it just becomes a
> **one-time** purchase (no auto-renew). So you can launch with one-time and add
> recurring later by filling in the IDs.

> **Localhost note:** payment gateways can't send webhooks to `localhost`. To test
> renewals/cancellations locally, use a tunnel (ngrok, Cloudflare Tunnel, etc.) and
> register that public URL. The initial subscribe→activate works without a tunnel
> because the buyer is redirected back to your site after approving.

---

## 6. Frequently asked questions

**Q: Which gateways support automatic recurring billing?**
Only **Stripe** and **PayPal**. Everything else is a one-time payment per period.

**Q: A user bought a monthly plan but wasn't auto-charged next month. Why?**
Either the gateway is one-time (expected), or — on Stripe/PayPal — the purchase used
a **coupon** or a **country-specific price**, which makes it a one-time charge by
design. Plain base-price monthly/yearly on Stripe/PayPal auto-renews.

**Q: Do coupons work?**
Yes — on **one-time** purchases (including lifetime, and any monthly/yearly bought
with a coupon). They **don't** apply to an auto-renewing subscription, because that
bills the fixed price you set in Stripe/PayPal. A coupon on a monthly plan gives one
discounted period (one-time), not "discount then recurring."

**Q: Do country-specific prices work?**
Same rule as coupons — yes for one-time charges, not for the fixed recurring price.

**Q: How does a renewal actually happen?**
The gateway charges the card on the renewal date and sends your site a **webhook**;
your site extends the plan. That's why webhooks must be configured for Stripe/PayPal.

**Q: What happens when a user upgrades?**
They pay the **prorated difference** right away (unused value of their current plan
is credited). On PayPal they approve the new amount at PayPal first.

**Q: What happens when a user downgrades?**
It's **scheduled for the end of their paid period** — no charge, no refund. They
keep current features until then, then switch. Downgrades to Free (or on one-time
gateways) simply end at period end.

**Q: What about lifetime plans?**
Always a **one-time** payment (there's nothing to auto-renew). Coupons/country
prices apply normally.

**Q: I only have the Regular License. Can I use subscriptions?**
No — charging end users requires the **Extended License** (Envato's rule). The whole
paid system, affiliate program, and their email templates stay hidden on Regular.

**Q: Do I have to set up webhooks?**
For **recurring** (Stripe/PayPal): **yes** — renewals/cancellations rely on them.
For **one-time** gateways: the payment is confirmed on return/redirect, and bank
transfers are approved manually in the admin.

**Q: How do I test before going live?**
Use each gateway's **sandbox/test mode**, run a real test purchase, and (for
recurring) a tunnel so webhooks reach your local site. Always test a full
subscribe → renew → cancel cycle in sandbox before switching to live keys.

**Q: Can users pay in a currency other than USD?**
Set your store currency in **Premium → Plans** (pricing currency). Per-country
prices/currencies can be configured per plan. Make sure the gateway supports your
currency.

---

## 7. Quick launch checklist ✅

- [ ] Extended License active
- [ ] **Premium Subscriptions** enabled (Settings → Features)
- [ ] Scheduler cron running (`schedule:run`)
- [ ] At least one gateway enabled with **live** keys (test mode off)
- [ ] For Stripe/PayPal recurring: **price/plan IDs** filled on each plan
- [ ] For Stripe/PayPal: **webhook** registered + secret/ID saved
- [ ] Ran one real test purchase per gateway in sandbox
- [ ] Tested a renewal + a cancellation (recurring) via a tunnel

You're ready to sell. 🚀 For the finer details of upgrades/downgrades and cycle
switches, see [Subscription-Plan-Changes.md](Subscription-Plan-Changes.md).
