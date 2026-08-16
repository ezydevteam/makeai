# Translation prompt (paste into Gemini, once per chunk)

Attach or paste the contents of one `scripts/i18n/chunks/chunk-0NN.json` file, then send the
prompt below. Save the reply as `scripts/i18n/incoming/out-0NN.json` and run `php scripts/i18n/apply.php`.

---

You are translating the UI strings of MakeAI, a commercial AI SaaS platform. The strings below
are the admin panel — settings screens, moderation queues, payment gateway config, system
diagnostics — so the register is that of professional software, not marketing copy.

I will give you a JSON array of English source strings. Return **one JSON object** with exactly
five top-level keys — `bn`, `ar`, `es`, `fr`, `hi` — where each maps the **entire** English array
to translations in that language:

```json
{
  "bn": { "<english source>": "<Bengali>", ... },
  "ar": { "<english source>": "<Arabic>", ... },
  "es": { "<english source>": "<Spanish>", ... },
  "fr": { "<english source>": "<French>", ... },
  "hi": { "<english source>": "<Hindi>", ... }
}
```

Languages: `bn` Bengali · `ar` Arabic (Modern Standard) · `es` Spanish (Spain) · `fr` French
(France) · `hi` Hindi.

## Rules

1. **The English source string is the JSON key and must be reproduced byte-for-byte.** Do not
   fix its typos, spacing, capitalisation or punctuation. If the source says `AI Mangement`, the
   key stays `AI Mangement`. The key is how the app looks the translation up — an altered key
   silently never matches.

2. **Every language must contain every key.** All five objects must have the same length as the
   input array. Nothing omitted, nothing merged, nothing added.

3. **Preserve `:placeholder` tokens exactly** — `:count`, `:name`, `:date`, `:plan`, `:gateway`,
   `:amount`, and so on. They may move within the sentence to suit the grammar, but the token
   itself is never translated, pluralised or spaced differently.
   - Watch for a placeholder with a literal suffix: in `:countd ago` the placeholder is `:count`
     and `d` is a literal "days" abbreviation. Translate around it — do not write `:countd` as a
     single unit and do not drop the `d`.
   - `{year}` (curly braces) is also a placeholder. Keep it as-is.

4. **Keep brand and technical terms in English**: AI, API, 2FA, OTP, TOTP, BYOK, SEO, CTA, PRO,
   Pro, SLA, MRR, URL, IP, SMS, QR, CSRF, SSL, Top P, MakeAI, and product names — Stripe, PayPal,
   Paddle, AdSense, Akismet, Redis, Amazon SES, Google Authenticator, 1Password, Authy, YouTube,
   Vimeo, Envato. Translate the sentence around them.
   - This includes the addon product names — **AI Assistant, AI Chatbot, AI Image Pro,
     AI Knowledge Base, FakerAI** — which must read identically in every language so the admin
     sidebar matches the marketplace listing. They are filtered out of the chunks automatically,
     but if one appears inside a longer sentence, leave that part in English.
   - Where the target language has a genuinely standard native term, use it: VAT → `IVA` (es),
     `TVA` (fr), but keep `VAT` in bn/hi where English is what users actually see.

5. **Do not translate literal data**: e-mail addresses (`admin@example.com`), URLs, file paths,
   config keys (`REDIS_HOST`, `.env`, `APP_KEY`), code samples (`sk-...`), format examples
   (`21:30`, `01/31/2026`, `2026-01-31`, `+1 (555) 123-4567`), or admin menu paths that name
   English screens (`Settings → License`, `Content › FAQs`). Reproduce them unchanged.

6. **Write like a native product, not a literal gloss.** Use each language's own UI conventions:
   Spanish inverted `¿` `¡`, French narrow spacing before `? ! : ;` and guillemets `« »`, Arabic
   right-to-left phrasing with correct `ـ` forms, Bengali and Hindi in natural sentence order.
   Prefer the imperative for buttons, and match the source's formality (informal *tú*/*vous* is
   already established: Spanish uses *tú*, French uses *vous*).

7. **Preserve trailing punctuation and ellipses.** `Saving...` → `Guardando...`, not `Guardando`.
   A source ending in `.` keeps a full stop; one without stays without.

8. If a string genuinely has no sensible translation (a symbol, a number, a bare brand name),
   return it unchanged as the value. Do not omit the key — identity entries are filtered out
   automatically downstream.

## Output

Return **only** the JSON object. No markdown fences, no commentary, no trailing text. It must
parse with a strict JSON parser, use real UTF-8 characters (not `\uXXXX` escapes), and escape
inner double quotes as `\"`.

Here is the array to translate:

<paste the contents of chunk-0NN.json here>
