# MakeAI — Envato / CodeCanyon Distribution Checklist

Pre-submission gate for every release uploaded to CodeCanyon. Work top-to-bottom.
Nothing ships until **every mandatory box is ticked**.

**Legend**
- 🔴 **HARD REJECT** — Envato bounces the item outright; reviewer never sees the rest.
- 🟠 **SOFT REJECT** — Envato asks for a fix and re-review (days of delay per round).
- 🤖 **AUTO** — already enforced by `php scripts/build-release.php`; the box is *verifying the gate ran*, not doing it by hand.
- ✋ **MANUAL** — no automated gate exists; a human must check.

> The build script is the source of truth for what ships (ALLOWLIST-based). Any item marked 🤖
> that fails means the build aborts — never bypass with `--allow-placeholder-key` or hand-editing
> the zip for a real submission.

---

## 0. Build the package the blessed way

- [ ] ✋ Build **only** via `php scripts/build-release.php <version>` — never hand-zip the repo root.
- [ ] ✋ Build WITHOUT `--skip-deps` for the final artifact (forces a clean `vendor/` + `public/build`).
- [ ] ✋ Do **not** pass `--allow-placeholder-key` on a real release (see §4).
- [ ] 🤖 Preflight summary printed `N checks passed` with **zero failures**.
- [ ] ✋ Final zip is the nested layout: outer archive → `script.zip` (code) + docs + license folder, per Envato "main files" convention.

---

## 1. 🔴 Hard-reject blockers (item is dead on arrival)

### 1.1 Secrets & live credentials
- [ ] 🤖 `.env` did **not** leak into the package (gate: ".env leaked into the package").
- [ ] ✋ `.env.example` contains **only placeholders** — no real API keys, DB passwords, SMTP creds, Stripe/Paddle keys, AWS keys, Pusher/Reverb secrets.
- [ ] ✋ Grep the staged package for live secrets before zipping:
  - `sk-`, `pk_live`, `rk_live` (Stripe), `sk_live`, Paddle vendor auth codes
  - AWS `AKIA…`, `aws_secret`, private keys (`BEGIN PRIVATE KEY`, `BEGIN RSA`)
  - real SMTP host/user/pass, real OpenAI/Anthropic/Gemini keys
  - any `@gmail.com` / `@ezydev` / personal or internal e-mail or domain
- [ ] ✋ No `APP_KEY=base64:…` real value committed in `.env.example` (must be blank — installer generates it).
- [ ] 🤖 No developer-only keys left in the shipped `.env.example` — the `DEMO_*` block and `LICENSE_TEST_MODE` are cut out of a buyer package by the "Tailoring .env.example" step (gate: "developer-only … key(s) left in the shipped .env.example"). Any new key of that kind belongs inside a `# @build:demo-start … end` or `# @build:dev-only-start … end` fence in the repo's `.env.example`, or the gate will reject it.
- [ ] ✋ No customer data, seeded real users, or production DB dump in `database/data/data.sql`.
- [ ] ✋ No `.git/`, `.gitignore`-only internal folders (`.cursor`, `.agent`, `.agent-mem`, `.claude`, `.codex`, `.mcp*`, `.vscode`) in the zip. (ALLOWLIST excludes these — confirm.)

### 1.2 Encoded / obfuscated / malicious code
- [ ] ✋ No `eval(`, `base64_decode(` on code payloads, `gzinflate`, `str_rot13` used to hide logic.
      (The license verifier legitimately base64-decodes a **key/signature** — that's fine and documented.)
- [ ] ✋ No ionCube / obfuscated blobs unless declared; Envato allows encoded *self-authored* code only if disclosed and the item still functions on standard hosting.
- [ ] ✋ No "phone-home" beyond the documented license activation + docs-site citation links.

### 1.3 Licensing of bundled assets (Envato is ruthless here)
- [ ] ✋ Every third-party library has a compatible license (MIT/Apache/BSD/GPL-compatible). No "all rights reserved" bundled without permission.
- [ ] ✋ Fonts, icons, images, audio, video — all either self-made, CC0, or licensed **with proof**. Stock previews are NOT redistribution licenses.
- [ ] ✋ Composer deps are all OSS (check `composer.lock`). Note any **premium** package (e.g. `laravel/*` first-party is fine; a paid nova/spark-style dep is not shippable).
- [ ] ✋ `LICENSE` file present and states the buyer's terms consistently with the Envato Regular/Extended split (`docs/Regular-License-Guide.md`, `docs/Extended-License-Guide.md`).
- [ ] ✋ Demo/preview screenshots on the item page use only licensed assets.

### 1.4 Ships & installs at all
- [ ] 🤖 `vendor/autoload.php`, `artisan`, `index.php`, `build/manifest.json` present in package.
- [ ] 🤖 No Vite dev-server marker (`public/hot`) leaked.
- [ ] 🤖 `vendor/phpunit` and other **dev deps** absent (real build installs `--no-dev`).
- [ ] 🤖 `node_modules` did not leak anywhere.
- [ ] 🤖 No file > 50 MB.
- [ ] 🤖 `database/data/data.sql` present and every table it references has a matching shipped migration.
- [ ] ✋ Fresh install on clean MySQL + PHP 8.3 completes via the web installer end-to-end (see §6).

---

## 2. 🟠 Soft-reject blockers (fix-and-resubmit)

### 2.1 Hardcoded values (the #1 reviewer nitpick)
- [ ] ✋ No hardcoded URLs — every base URL derives from `APP_URL` / config. Grep for `http://localhost`, `127.0.0.1`, `:8000`, `:5173`, ngrok/tunnel hosts, `makeai-docs.ezydev.net` outside the one documented `DOCS_SITE`/`ProductDocsService::SITE_URL` constant.
- [ ] ✋ No hardcoded absolute filesystem paths (`D:\laragon`, `/home/`, `/Users/`, `/var/www/…`).
- [ ] ✋ No hardcoded e-mail addresses / support addresses in code — pull from settings/config.
- [ ] ✋ No hardcoded credentials, ports, or per-developer values in config defaults.
- [ ] ✋ No hardcoded currency, tax rate, price, or plan values that should be admin-configurable.
- [ ] ✋ No hardcoded API model IDs that the admin catalog is supposed to own (settings-driven).
- [ ] ✋ Timezone/locale come from config, not baked in.

### 2.2 Errors, debug & noise
- [ ] ✋ `APP_ENV=production`, `APP_DEBUG=false` in `.env.example`.
- [ ] ✋ No `dd(`, `dump(`, `var_dump(`, `console.log(` (prod), `ray(`, `logger()->debug` spam left in shipping code.
- [ ] ✋ No PHP notices/warnings/deprecations on a fresh install with `display_errors=On` (reviewers test on strict hosts).
- [ ] ✋ No `TODO` / `FIXME` / `HACK` / commented-out dead blocks in shipped source (grep `app/`, `resources/`, `routes/`, addons).
- [ ] ✋ Browser console clean on main pages (no 404s for assets, no JS errors).

### 2.3 Security hygiene (reviewers actively probe these)
- [ ] ✋ All forms CSRF-protected; no `VerifyCsrfToken` blanket excludes beyond documented webhooks.
- [ ] ✋ No raw SQL string interpolation — parameterized/Eloquent only.
- [ ] ✋ File uploads validated (mime, size, extension) and stored outside webroot or with safe names.
- [ ] ✋ Output escaped in Blade/Inertia; no unescaped user input (`{!! !!}` audited).
- [ ] ✋ SSRF guard (`app/Services/Security/SsrfGuard.php`) covers all user-supplied URL fetches.
- [ ] ✋ Auth on every admin route; no IDOR on user-scoped resources (addons audited: file leaks, cross-tenant).
- [ ] ✋ Webhook endpoints verify signatures (Stripe/Paddle/SMS).
- [ ] ✋ Rate limiting on auth, OTP, and AI-cost endpoints.

### 2.4 Functionality completeness
- [ ] ✋ No dead links / stub pages / "coming soon" in a paid feature.
- [ ] ✋ Every advertised feature on the item page actually works in the shipped build.
- [ ] ✋ Bundled addons: only what the license entitles ship (core build **excludes** `addons/` by design — paid addons distributed separately). Confirm no addon code leaked into `core/`.
- [ ] ✋ Payment flows tested in sandbox: Stripe + Paddle checkout, webhooks, plan change, cancel, refund path.
- [ ] ✋ Quota vs metered credit modes both charge/refund correctly (addons use mode-aware charge, no raw `increment('credits')`).

### 2.5 Responsiveness & UX (ThemeForest-grade, still checked on CodeCanyon SaaS)
- [ ] ✋ Responsive at 320 / 768 / 1024 / 1440 widths; no horizontal scroll, no overlap.
- [ ] ✋ RTL not broken if advertised; dark mode consistent if advertised.
- [ ] ✋ Favicon, meta tags, and title present; no "Laravel" default branding left over.

---

## 3. Documentation (mandatory — missing/'weak docs = 🟠 soft reject)

- [ ] ✋ `documentation/` (HTML or PDF) bundled, covering: **requirements, installation, configuration, updating, FAQ, changelog, support**.
- [ ] ✋ Server requirements listed explicitly: PHP **8.3+**, MySQL/MariaDB version, required extensions (from `composer.json`: bcmath, ctype, curl, dom, fileinfo, iconv, json, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, zip), plus Redis (Horizon/queues) and any cron/queue worker needs.
- [ ] ✋ Cron + queue worker setup documented (`distribution/deploy/cron.txt`, `supervisor.conf.example`, `nginx.conf.example`).
- [ ] ✋ Third-party API setup documented (OpenAI/Anthropic/etc., Stripe, Paddle, SMS, S3, Reverb/Pusher) — where to get keys, where to paste them.
- [ ] ✋ Addon installation + licensing documented.
- [ ] ✋ **Changelog** updated with the new version and dated entries.
- [ ] ✋ Version number bumped consistently: item release, `package.json`, in-app version constant, docs.
- [ ] ✋ No internal planning docs shipped (`plan.md`, `ADDONS_PLAN.md`, `AGENT*.md`, `.cursor/`, audit notes). (ALLOWLIST should exclude — verify none leaked.)

---

## 4. 🔴 License system (project-specific, catastrophic if wrong)

- [ ] 🤖 `app/Support/LicenseKey.php` present and `PUBLIC_KEY !== PLACEHOLDER`.
- [ ] ✋ **The real Ed25519 public key replaced the placeholder** in `LicenseKey::PUBLIC_KEY`.
      Sentinel to catch: `MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=`. With the placeholder in place the
      app **builds, installs, and looks fine but no buyer can ever activate** — this is the single most
      dangerous silent failure. See `license-packaging-blockers` memory.
- [ ] ✋ Same real key applied everywhere it's read: `LicenseService`, `AddonLicenseService`, `ThemeLicenseService`.
- [ ] ✋ Private signing key is **NOT** in the repo or package (only the public key ships).
- [ ] ✋ Envato Purchase Code activation flow tested against a real/sandboxed purchase code.
- [ ] ✋ Graceful, clear error when activation fails (wrong code, already used, network down) — no white screen.

---

## 5. Zero-config distribution layout

- [ ] 🤖 Webroot ships only `index.php` + public assets; all app code lives in `core/` (APP_DIR) one level down.
- [ ] ✋ `distribution/core.htaccess` / `web.config` deny direct access to `core/` (so `core/.env` is never web-served). Verify on Apache **and** IIS.
- [ ] 🤖 Reference stub folders shipped empty (only the stub, no leaked content) — storage/addons landing dirs.
- [ ] ✋ `robots.txt`, `favicon.ico` present in webroot.
- [ ] ✋ File permissions guidance in docs: `storage/`, `bootstrap/cache/` writable.

---

## 6. Final fresh-install smoke test (do this on a clean box every release)

- [ ] ✋ Unzip exactly what a buyer downloads (not the repo) onto clean PHP 8.3 + MySQL.
- [ ] ✋ Run the web installer: requirements check passes, DB config, admin account, license activation.
- [ ] ✋ `APP_KEY` auto-generated; `.env` created with correct values.
- [ ] ✋ `data.sql` imports cleanly; no migration errors.
- [ ] ✋ Log in as admin → dashboard loads, no errors.
- [ ] ✋ Register a normal user → core AI feature runs end-to-end → credits charged correctly.
- [ ] ✋ Queue worker + Horizon boot; a queued job (e.g. mail, AI job) completes.
- [ ] ✋ Outbound mail sends (test SMTP) — signup verification + password reset.
- [ ] ✋ Uninstall/reinstall clean: no leftover state blocks a second install.

---

## 7. Item-page / submission metadata (not in the zip, but Envato-mandatory)

- [ ] ✋ Live demo URL works, is seeded with sane demo data, and matches the shipped version.
- [ ] ✋ Demo admin credentials provided (or clearly "request access").
- [ ] ✋ Screenshots/preview reflect the actual current UI.
- [ ] ✋ Feature list on the page matches what actually ships (no vaporware = avoids buyer disputes + takedowns).
- [ ] ✋ Correct category, tags, and **compatible-with** / **software version** fields.
- [ ] ✋ Regular vs Extended license description accurate.

---

## Quick pre-flight grep (run against the STAGED package, not the repo)

```bash
# secrets & live keys
grep -rInE 'sk_live|pk_live|rk_live|AKIA[0-9A-Z]{16}|BEGIN (RSA|PRIVATE) KEY|password\s*=\s*["'\''][^"'\'' ]{6,}' core/ | grep -v vendor/

# hardcoded hosts / paths / personal identifiers
grep -rInE 'localhost|127\.0\.0\.1|:5173|:8000|ngrok|D:\\\\laragon|/home/|/Users/|@gmail\.com|ezydev' core/ | grep -v vendor/

# debug leftovers
grep -rInE '\b(dd|dump|var_dump|ray)\s*\(|console\.log|TODO|FIXME|HACK' core/app core/resources core/routes

# placeholder license key still in place
grep -rn 'MzItYnl0ZS1wdWJsaWMta2V5LXBsYWNlaG9sZGVyISE=' core/app/Support/LicenseKey.php && echo '!!! PLACEHOLDER KEY — DO NOT SHIP'
```

*(A hit in `vendor/` for the first two may be a false positive — inspect before acting.)*

---

### Sign-off

- Version: `__________`   Date: `__________`   Built by: `__________`
- [ ] All 🔴 boxes ticked  · [ ] All 🟠 boxes ticked  · [ ] Fresh-install smoke test passed  · [ ] Real license key confirmed
