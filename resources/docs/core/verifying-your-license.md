---
title: Verifying Your License
slug: verifying-your-license
page: verifying-your-license.html
section: System
license: regular
keywords: [purchase code, verify license, license activation, regular license, extended license, domain mismatch, grace period, deactivate license]
---

**Admin → System → Verify License** activates your copy against your Envato purchase code and shows exactly what your license unlocks.

## Activating your license

Paste your **Purchase Code** (the format shown as `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`) from your Envato Downloads page and submit. Once verified, the screen shows your **License Type** (Regular or Extended), the buyer name, purchase date, when it last re-verified, and a domain-binding status — green if the current domain matches where the license was activated, red if it doesn't.

## What each license type unlocks

In the product's own words: a **Regular license** covers a single end product where end users aren't charged, and enables all core AI features. An **Extended license** additionally allows charging end users — it unlocks subscription plans, billing, the affiliate program, and every other Pro-gated feature described throughout this documentation.

## Re-verifying or deactivating

Use **Re-verify Now** to force a fresh check against Envato's servers — useful after a domain change or if you're troubleshooting a grace-period warning. **Deactivate License** wipes the stored license data from this install and blocks Extended-only features immediately; you'd do this before moving the license to a different domain.

## Why you're seeing a grace-period or domain-mismatch warning

- **Domain mismatch** — the license was verified on a different domain than the one it's currently running on. Re-verify from the correct domain, or deactivate here and activate again from the new one.
- **Grace period active** — a scheduled re-verification failed (usually a connectivity issue reaching Envato), and Extended features will stop working once the grace period shown in the warning banner runs out. Re-verify manually to clear it before that happens.
- **Grace period expired** — Extended-only frontend features are now blocked entirely until a successful re-verification.
