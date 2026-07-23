# Demo-Mode Switcher — Implementation Plan

**Status:** Planned (not yet implemented)
**Date:** 2026-07-24

## What we're building

A demo-only UI (visible only when `DEMO_ENABLED=true`) with **two independent
controls**:

1. **Nav menu** (left/center of the demo bar) — pure page navigation.
   - **Home** ▸ Home 1, Home 2, Home 3 → each is a different **target page/layout**.
   - **Tool Page** ▸ Page 1, Page 2… → different tool pages.
   - This ONLY changes which page you're looking at. It does **not** touch the
     preset / theme style.

2. **Right-side icon → modal** — the **demo selector**.
   - Opens a modal listing selectable demos: **Default, Creative** (theme presets),
     **AI Chatbot** (addon-owned homepage), etc.
   - Picking one swaps the active **preset / addon flavor**, and the choice
     **sticks** as you navigate around with the nav menu.

## Hard constraint (drives the whole design)

`app/Http/Middleware/DemoMode.php` blocks **all POST/PUT/PATCH/DELETE** in demo
mode. So **nothing here may persist a setting.** Both controls are GET-only:
- Nav menu = plain links to target pages.
- Demo selector = a whitelisted **GET** route that sets a **cookie**; the selection
  is applied as an **in-memory, per-request override** — never written to the DB.

## How the app resolves style today (grounding)

- Demo flag: `config('demo.enabled')` → shared as `page.props.app.demo`
  (`HandleInertiaRequests.php:201`). `AppLayout.vue:18` already gates the banner on it.
- Everything style-related resolves through `settings()` → `Setting::getValue()`
  (`app/Models/Setting.php:260`), which is request-cached.
- Theme/preset: `active_theme`, `active_theme_preset`, and the resolved
  `frontend_theme_settings` / `frontend_header_settings` / `frontend_homepage_config`
  blobs (via `ThemeSettingsService`). Presets are JSON in
  `resources/themes/default/presets/{default,creative,…}.json`; `ThemePresetService`
  lists (`available()`) and applies them.
- Addon-owned homepage: `HomeController::index()` reads `settings('homepage_template')`
  and, if not `default`, renders via `HomepageProviderRegistry` (`options()` lists
  the registered addon homepages, e.g. ai-chatbot).

Because all of these read through `settings()`, a single in-memory override layer
on `Setting::getValue()` makes the whole stack follow the demo selection.

---

## Part A — Nav menu (target-page navigation)

### A1. Variant catalog — `config/demo.php`
```php
'nav' => [
    'home' => [
        ['key' => 'home-1', 'label' => 'Home 1', 'url' => '/?demo_home=1'],
        ['key' => 'home-2', 'label' => 'Home 2', 'url' => '/?demo_home=2'],
        ['key' => 'home-3', 'label' => 'Home 3', 'url' => '/?demo_home=3'],
    ],
    'tools' => [
        ['key' => 'tool-1', 'label' => 'Page 1', 'url' => '/ai-tools?demo_tool=1'],
        ['key' => 'tool-2', 'label' => 'Page 2', 'url' => '/ai-tools?demo_tool=2'],
    ],
],
```
`url` values are whatever target routes the demo should showcase — either real
routes or a `?demo_home=N` param that the theme's `Home.vue` / `ToolPage.vue`
reads to pick a layout (hero_variant, section order). No preset change here.

### A2. `DemoNav.vue` (new)
`resources/themes/default/js/Components/DemoNav.vue` — two hover dropdowns built
from `config('demo.nav')` (shared via Inertia). Each item is an Inertia `<Link>`.
Reuse the submenu markup already in `AppHeader.vue:719-754`.

### A3. Optional per-page layout switch
If Home 1/2/3 should differ in layout (not just be separate pages), `Home.vue`
reads `?demo_home` and picks a `hero_variant` / section arrangement; `ToolPage.vue`
reads `?demo_tool` similarly. Home already switches on `hero_variant`
(`Home.vue:83-87`), so this is a small addition.

---

## Part B — Demo selector (preset / addon, sticky)

### B1. Selectable-demo catalog
A `DemoSelectionResolver` service builds the modal's list from two live sources:
- **Theme presets** → `ThemePresetService::available()` (Default, Creative, …).
- **Addon homepages** → `HomepageProviderRegistry::options()` (AI Chatbot, …).

Each entry: `{ key, label, type: 'preset'|'addon', thumbnail? }`. Shared to Inertia
alongside `app.demo` so the modal can render and highlight the active one.

### B2. GET route to select — `routes/web.php`
```php
Route::get('/__demo/select', DemoSelectController::class)->name('demo.select');
```
Controller validates `request('demo')` against the catalog, sets an **encrypted
cookie** `demo_selection` (or clears it for `default`), and redirects back. GET →
passes the DemoMode write-block. (Route is a no-op / 404 when `demo.enabled` is
false.)

### B3. In-memory override — the seam
- Add a static override map to `Setting`:
  ```php
  protected static array $overrides = [];
  public static function overrideForRequest(array $kv): void { self::$overrides = $kv; }
  ```
  and short-circuit at the top of `getValue()`:
  ```php
  if (array_key_exists($resolvedKey, self::$overrides)) return self::$overrides[$resolvedKey];
  ```
- `DemoSelectionMiddleware` (registered globally, **guards on `config('demo.enabled')`**,
  runs before controllers): reads the `demo_selection` cookie, asks
  `DemoSelectionResolver` for the override map, calls `Setting::overrideForRequest(...)`.
  - **Preset demo:** parse `presets/{key}.json` and override the resolved
    theme-settings blob keys (`frontend_theme_settings`, `frontend_header_settings`,
    `frontend_footer_settings`, `frontend_homepage_config`) in memory — i.e. what
    `ThemePresetService::apply()` does, but into the override map instead of the DB.
  - **Addon demo:** override `homepage_template => key` (+ `active_theme` if the
    addon ships a theme).
- Because it's request-scoped memory, it never persists and never trips the
  write-block. Everything downstream (`settings()`, `ThemeSettingsService`,
  `HomeController`, theme CSS vars) then resolves the selected demo automatically,
  and it **sticks across nav-menu navigation** via the cookie.

### B4. `DemoSelectorButton.vue` + `DemoSelectorModal.vue` (new)
- Button = the right-side icon in the demo bar. Modal lists the catalog as cards;
  clicking a card navigates (GET) to `route('demo.select', { demo: key })`.
- Active demo highlighted from the shared active-key prop.

---

## Part C — Wiring & tests

### C1. Demo bar assembly — `AppLayout.vue`
Under the existing demo banner (`v-if="isDemo"`), render a row:
`[ DemoNav ......... DemoSelectorButton ]`. All gated on `isDemo`.

### C2. Share props — `HandleInertiaRequests.php` (~line 201, beside `app.demo`)
`demo_nav` (config), `demo_selectable` (catalog), `demo_active` (current cookie key).

### C3. Tests — extend `tests/Feature/DemoModeTest.php`
- `GET /__demo/select?demo=creative` sets the cookie and 302s back.
- With the cookie present + `DEMO_ENABLED=true`, `GET /` renders with the preset's
  resolved values (override applied).
- With `DEMO_ENABLED=false`, the cookie is **ignored** and the route is inert
  (no production leakage, nothing persisted).
- Nav-menu links (`?demo_home=2`) return 200 and don't alter the active preset.

## Files touched
- `config/demo.php` — nav catalog
- `app/Models/Setting.php` — `$overrides` + `overrideForRequest()` + `getValue()` guard
- `app/Services/DemoSelectionResolver.php` (new)
- `app/Http/Middleware/DemoSelectionMiddleware.php` (new) + kernel registration
- `app/Http/Controllers/DemoSelectController.php` (new) + route
- `app/Http/Middleware/HandleInertiaRequests.php` — share demo nav/catalog/active
- `resources/themes/default/js/Components/DemoNav.vue` (new)
- `resources/themes/default/js/Components/DemoSelectorButton.vue` (new)
- `resources/themes/default/js/Components/DemoSelectorModal.vue` (new)
- `resources/themes/default/js/Layouts/AppLayout.vue` — mount the demo bar
- `resources/themes/default/js/Home.vue`, `AI/ToolPage.vue` — optional `?demo_home`/`?demo_tool`
- `tests/Feature/DemoModeTest.php`

## Key design notes
- **Two controls, two mechanisms.** Nav = stateless links (target page only).
  Selector = sticky cookie + in-memory setting override (preset/addon flavor).
- **No writes anywhere** — respects the demo write-block by construction.
- **Zero production impact** — every seam guards on `config('demo.enabled')`;
  with the flag off, the middleware, route, and components are inert.
- **Auto-discovers demos** — presets from `ThemePresetService::available()`,
  addon homepages from `HomepageProviderRegistry::options()`; drop in a new preset
  JSON or register an addon homepage and it appears in the modal automatically.
