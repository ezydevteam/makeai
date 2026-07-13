# Regular License — Setup Guide & FAQ

Welcome! 👋 This guide is for buyers running the app on an **Envato Regular License**.
It explains exactly how the app behaves, how to control usage, and answers the
questions Regular-license owners ask most. No developer experience needed.

> **TL;DR:** A Regular License means you **cannot charge your end users** (Envato's
> rule). So billing is off, and **credits become a free, resetting usage allowance**
> instead of something users buy. You control how much people can generate with a few
> simple limits in **Admin → AI Management → Global Settings**. Tools never "run out"
> permanently — allowances refresh automatically.

---

## 1. What a Regular License gives you

- ✅ The full AI tool suite, admin panel, blog, pages, users, appearance, etc.
- ✅ A **free tool site** — visitors and registered users can generate content, capped
  by allowances you set.
- ❌ **No billing** — no pricing page, checkout, subscriptions, credit top-ups, or
  affiliate program. These are hidden automatically and safe to ignore.

If you want to **sell** subscriptions or credit top-ups to your users, you need the
**Extended License** — see `Extended-License-Guide.md`.

---

## 2. First-time setup (5 minutes)

1. **Install** the app following the installer, then log into the admin panel.
2. **Confirm your license type.** In **Admin → Settings → License**, make sure it's set
   to **Regular**. (Billing features stay hidden on Regular — this is expected.)
3. **Set your usage allowances.** Go to **Admin → AI Management → AI Providers →
   Global Settings → Spend Controls**. This is your main control surface:
   - **Guest Daily Credit Limit** — how much an anonymous visitor (per IP) can use per
     day on public tools. `0` = no guest limit.
   - **Per-User Daily Credit Limit** — how much a logged-in user can use per day.
   - **Per-User Monthly Credit Limit** — how much a logged-in user can use per month.
   - **Global Daily AI Budget (USD)** — a safety valve: all AI pauses for the day once
     your real provider spend hits this. Protects your OpenAI/Anthropic bill.
   - `0` disables any individual limit.
4. **Add your AI provider key.** In **AI Management**, open a provider (e.g. OpenAI),
   add your API key, and pick your default model.
5. **(Optional) Set new-user starting credits.** New registered users start with the
   "default new-user credits" value; you can raise the per-user limits above to control
   ongoing usage.

That's it — your site is live as a free AI tool site.

---

## 3. How credits work on a Regular License

Think of credits as a **resetting allowance**, not money:

- Each AI generation uses a few credits (bigger/smarter models cost more).
- Usage is metered against your **daily** and **monthly** limits, which **reset
  automatically** (daily and on the 1st of each month).
- A user can **never get permanently stuck** — even at zero, their allowance refreshes.
- **Guests** are limited per IP per day, so a public tool can't be abused anonymously.
- Your **Global Daily USD Budget** still protects your real provider bill in all cases.

You do **not** need to think about "wallets", "top-ups", or "selling credits" — those
are Extended-license concepts and are turned off.

### The credit multiplier (optional)
In **Global Settings → Credit Economics**, the **Credit multiplier** sets how many
credits each model costs relative to its real API cost. Higher = allowances are used up
faster. Leave the defaults unless you want tighter/looser consumption.

---

## 4. Frequently asked questions

**Q: Why is there no pricing page / "Buy Credits" / subscriptions?**
Because a Regular License doesn't permit charging your users. Those features are hidden
by design. Upgrade to an Extended License to enable them.

**Q: My users hit "credit limit reached" — did they run out forever?**
No. That's the daily or monthly **allowance** limit you set. It resets automatically
(daily / monthly). Raise the per-user limits in Global Settings if you want more.

**Q: How do I stop anonymous visitors from abusing my public tools?**
Set a **Guest Daily Credit Limit** in Spend Controls. It caps usage per IP per day.
Set it to `0` only if you want unlimited guest use.

**Q: How do I protect my OpenAI/Anthropic bill?**
Set a **Global Daily AI Budget (USD)**. When your real spend for the day reaches it, all
AI generation pauses until the next day. This works on every license.

**Q: A "Pro only" tool shows as unavailable. Why?**
Some tools can be marked as requiring a paid subscription. Since billing is off on a
Regular License, those tools show as "not available on this site." Set their access
level to public/login in the tool's settings to make them usable.

**Q: Can I install add-ons?**
Yes. Add-ons work on a Regular License and respect the same allowance system — their
usage meters against your limits just like core tools (no wallet dead-ends).

**Q: Do I need to run a cron job?**
Recommended. A scheduler resets daily/monthly allowances. Add this one line on your
server:
```
* * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1
```

**Q: What currency does the admin use?**
Set your store currency once in **Admin → Settings → General**. On a Regular License
it mainly affects internal cost tracking (you're not charging users).

**Q: Can I later upgrade to Extended?**
Yes — buy the Extended License on Envato, set the license type to Extended in the admin,
enable **Premium Subscriptions**, and the billing features appear. Your allowances keep
working as before. See `Extended-License-Guide.md`.

---

## 5. Quick reference

| I want to… | Go to |
|---|---|
| Limit guest usage | AI Management → Global Settings → Spend Controls → Guest Daily |
| Limit logged-in usage | Spend Controls → Per-User Daily / Monthly |
| Cap my provider bill | Spend Controls → Global Daily AI Budget (USD) |
| Add an AI provider key | AI Management → (provider) → Add Key |
| Change model credit costs | Global Settings → Credit Economics → Credit multiplier |
| Set store currency | Settings → General |

Enjoy your free AI tool site! For selling access, see the Extended License guide.
