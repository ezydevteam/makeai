# Addon Development — Charging Credits the Right Way

A short guide for anyone building an addon that consumes AI credits. Follow this and
your addon will work correctly on **both** license types with no extra effort.

> **TL;DR:** Never check `$user->credits` or call `$user->deductCredits()` directly.
> Use `$user->chargeCredits()` to charge, `$user->refundCredits()` to refund, and let
> `TokenGuard` (or the `deduct_credits()` helper) do the enforcement. These are
> **mode-aware** — they behave correctly whether the site sells credits or not.

---

## 1. Two credit modes (why this matters)

The platform runs in one of two modes, decided automatically by the license:

| Mode | When | What "credits" means |
|---|---|---|
| **Metered** | Extended license **and** subscriptions on (`isProAvailable()` is true) | A **purchasable wallet.** Users buy/top-up credits; a run fails when the balance is too low. |
| **Quota** | Regular license, or billing off (`isProAvailable()` is false) | A **resetting allowance** (per-guest per-IP daily + per-user daily/monthly). There is **no wallet to refill** — usage is metered and resets on schedule. |

Check the current mode with the global helper **`credit_quota_mode()`** — it returns
`true` in quota mode.

**The classic bug:** hard-coding a wallet check like
`if ($user->credits < $cost) abort()`. In quota mode the wallet is never refilled, so
the user hits 0 and is **permanently stuck** — your addon's tools stop working forever.

---

## 2. Do this ✅

### Charge for a run
```php
// Meters the allowance in quota mode; drains the wallet in metered mode.
// Returns false only in metered mode when the balance is too low.
if (! $user->chargeCredits($cost, "My Addon: {$action}", ['tool' => $slug])) {
    // Metered mode, not enough balance — tell the user to top up.
    return back()->with('error', translate('Not enough credits.'));
}
```

Or, from procedural code, the same behavior via the helper:
```php
if (! deduct_credits($user->id, $cost, "My Addon: {$action}")) {
    // ...insufficient (metered only)...
}
```

### Refund a failed run
```php
// Metered → returns credits to the wallet.
// Quota   → winds back the consumed daily/monthly allowance.
$user->refundCredits($cost, 'My Addon: generation failed');
```

### Token-based (LLM) generation
If your addon calls an LLM, route through `TokenGuard` — it already handles both modes,
the guest per-IP gate, the daily/monthly caps, and the global USD budget:
```php
TokenGuard::before($user, $template, $model);        // pre-flight (throws on limit)
// ...call the provider...
TokenGuard::after($user, $inputTokens, $outputTokens, $model, $provider, 'my-addon');
```

### Media / per-unit generation
```php
TokenGuard::beforeMedia($user, 'image', $model, $units);
// ...generate...
TokenGuard::afterMedia($user, 'image', $model, $provider, $units);
```

---

## 3. Don't do this ❌

```php
// ❌ Dead-ends the user at 0 in quota mode — the wallet never refills.
if ($user->credits < $cost) {
    throw new InsufficientCreditsException($user->credits, $cost);
}

// ❌ Always drains the wallet, even in quota mode.
$user->deductCredits($cost, $reason);

// ❌ Refund that adds to the wallet regardless of mode
//    (phantom credits in quota mode; the allowance is never restored).
$user->increment('credits', $cost);
```

If you already have a balance check you can't remove, at least gate it by mode:
```php
if (! credit_quota_mode() && $user->credits < $cost) { /* metered-only wall */ }
```

---

## 4. What each mode enforces (so you don't double-guard)

- **Guest per-IP daily limit** and **user daily/monthly limits** are enforced by
  `TokenGuard::before()` / `beforeMedia()`. If your addon runs LLM/media through
  TokenGuard, you get these for free — don't re-implement them.
- **`chargeCredits()` / `deduct_credits()`** handle the *charge*; they do **not** enforce
  the daily allowance. If your addon charges a flat per-use cost without going through
  `TokenGuard::before`, that's fine — but know that the daily-allowance pre-check isn't
  applied on that path (this matches core behavior for flat-cost tools).
- The **global daily USD budget** kill-switch fires in both modes via `TokenGuard`.

---

## 5. Quick checklist before you ship an addon

- [ ] No raw `$user->credits <` / `hasCredits()` wall that can block in quota mode.
- [ ] No raw `$user->deductCredits()` — use `chargeCredits()` (or `deduct_credits()`).
- [ ] Refunds use `$user->refundCredits()`, not `increment('credits', …)`.
- [ ] LLM/media runs go through `TokenGuard::before/after` (+ `beforeMedia/afterMedia`).
- [ ] Tested on a **Regular license** (`license_type = 1`): a user with 0 wallet
      balance can still generate until their daily allowance is hit, and it refills
      the next day.
- [ ] Tested on an **Extended license + subscriptions on**: wallet balance is enforced
      and drained as expected.

That's it — charge through the mode-aware methods and your addon just works everywhere.
