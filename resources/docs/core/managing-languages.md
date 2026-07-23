---
title: Managing Languages and Translations
slug: managing-languages
page: managing-languages.html
section: Languages
license: regular
keywords: [language, translation, locale, rtl, multilingual, add language, ai translate, language switcher, translate strings, language not switching, add locale, rtl language, missing translation]
---

**Admin → Languages** manages every language your site can display in, translated string by string rather than through flat locale files.

## Adding a new language

Click **Add Language** and fill in its name, ISO code, flag, whether it's right-to-left, and its date/time/number formats. Saving copies every existing translation key from your other languages into the new one (defaulted to the key itself) so you have a complete list to fill in — open the language's own **Translations** screen to actually translate each string, either by hand or with the **AI Translate** bulk action that fills in everything still missing.

## What ships by default

Your site starts with 8 active languages: English (the default), Bengali, Arabic (right-to-left), Spanish, Chinese, Russian, Portuguese, and French.

## How customers switch languages

There's no automatic browser-language detection — customers pick their language manually from the language switcher (a flag-and-name dropdown). Once chosen, it's saved to their account so it sticks across future visits; for guests, it's kept for the current session.

## Why some text isn't translating

- The string simply hasn't been translated for that language yet — it displays as the raw key or falls back to the default language until someone fills it in.
- A newly added language starts with every string defaulted to its key, not blank — run **AI Translate** or translate manually to replace those placeholders.
- The customer never manually switched languages — since there's no auto-detection, a non-English visitor sees English by default unless they pick otherwise.
