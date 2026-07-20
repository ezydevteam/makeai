---
title: Social Login and Third-Party Extensions
slug: oauth-and-extensions
page: oauth-and-extensions.html
section: Settings
license: regular
keywords: [oauth, social login, google login, facebook login, captcha, recaptcha, akismet, spam filter, analytics, sms gateway, email validation, extensions, log in with google, sign in with google, customer login providers, google sign in]
---

**Admin → Settings → OAuth** and **Extensions** connect two different kinds of third-party services: letting customers sign in with an existing account, and non-AI system utilities like CAPTCHA, analytics, and spam filtering.

## Letting customers sign in with Google, Facebook, and others

**OAuth → Login Providers** supports six services: Google, GitHub, Facebook, Reddit, Twitter, and LinkedIn. Each has its own card with an enable toggle, a Client ID, and a Client Secret (encrypted before storage), plus a read-only **Redirect URL** with a copy button — paste that exact URL into the provider's own developer console when setting up the connection. A provider can't be saved as enabled until both its Client ID and Client Secret are filled in.

The separate **Frontend Display** setting on this same page controls how social **share** buttons look on your site (icon only, or icon plus label) — it's unrelated to the login providers below it, despite living on the same screen.

## Connecting system extensions

**Extensions** lists eight non-AI system connectors, each with an enable toggle, a provider choice where more than one exists, a timeout setting, and that provider's credential fields:

- **CAPTCHA Protection** — Google reCAPTCHA or hCaptcha
- **Spam Filter** — Akismet
- **IP Geolocation** — IPInfo
- **Currency Exchange Rates** — ExchangeRate API or Fixer.io
- **Content Moderation** — OpenAI Moderation or Sightengine, with an Enforcement Mode (Off / Flag & log / Block unsafe requests)
- **Web Analytics** — Google Analytics 4, Google Tag Manager, Plausible, or Umami, with a "Wait for cookie consent" option
- **SMS Gateway** — Twilio, Vonage, or MessageBird
- **Email Validation** — ZeroBounce or NeverBounce, with a "what to block" option

This screen is separate from **AI Management → Integrations**, which only covers AI-content utilities like plagiarism and grammar checking — Extensions never charges credits per use.

## Why a login button or extension isn't working

- A login provider shows disabled because either its Client ID or Client Secret is still empty — both are required together.
- The **Redirect URL** wasn't copied into the provider's own developer console exactly as shown, causing a mismatch error during sign-in.
- An extension's **Test** button fails — double-check the credential was pasted without extra whitespace, and that the provider account itself is active.
