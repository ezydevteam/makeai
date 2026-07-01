# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Architecture
- Footer builder blocks (back_to_top, dark_mode, etc.) should be independently controlled by the footer configuration, not overridden by global appearance theme settings. The General tab settings should only affect non-footer UI elements (e.g., homepage scroll-to-top button). Confidence: 0.70
- AI generation fields should use global settings (default provider + model, fallback provider + model) instead of hardcoded values. When a feature-specific provider API key is not configured, fall back to the global AI settings provider. Confidence: 0.80
- For RAG embedding model selection, use the fallback/default provider from global AI provider settings when no specific embedding model is configured. Confidence: 0.70
- The project's base Controller (App\Http\Controllers\Controller) does not extend \Illuminate\Routing\Controller and has no middleware() method — apply middleware on routes directly, not via $this->middleware() in controller constructors. Confidence: 0.70

# admin-ui
- Use AppSelect and AppColorPicker components instead of native &lt;select&gt; and color text inputs in admin panel interfaces. Confidence: 0.75
- Admin panel color variables are completely separate from frontend theme color variables — admin uses its own admin-specific primary color and color scheme. Do not reference frontend theme colors (e.g., `--color-primary-500` from the frontend palette) in admin panel styling. Confidence: 0.80

# vue
- When a section visibility toggle disables a section, auto-collapse its settings panel; when enabling, auto-expand it — bidirectional toggle-visibility-to-collapse linkage. Confidence: 0.70
- Destructure `{ t }` from `useTranslate()` — the composable returns `{ t }` not the function directly. Confidence: 0.70
- When using `v-else` with `v-for`, avoid `<template v-else>` wrapping a child with `v-for` — Vue resolves the iteration variable at the `<template>` scope where it doesn't exist. Instead, move `v-else v-for` directly onto the child element. Confidence: 0.65
- Avoid passing refs directly as arguments in inline template handlers (e.g., `@input="handleFileInput(myRef, $event)"`) when the element is behind `v-if` — Vue's template `_cache` can capture stale/null refs across re-renders. Use an arrow wrapper: `@input="(e) => handleFileInput(myRef, e)"` to resolve the ref at call time. Confidence: 0.65
- In Vue 3 `<script setup>`, refs passed as template handler arguments are auto-unwrapped — the ref's `.value` (e.g., `null`) is passed instead of the ref object itself. To mutate a ref from a template event handler, use dedicated named handlers that close over the ref directly (e.g., `onLogoLightInput(e) { logoLightFile.value = e.currentTarget.files?.[0] || null }` with `@input="onLogoLightInput"`), rather than passing refs through template arguments. Confidence: 0.70

# inertia
- Inertia render paths for addon pages must use `Addons/{addon-slug}/Path/To/Page` format (e.g., `Addons/ai-image-editor/Admin/Settings`), not `AddonName::Path/To/Page`. The `app.ts` resolver strips the `Addons/` prefix and looks up `{slug}/Path` in the addon page map. Confidence: 0.75
- In Inertia v2's `useForm`, `data` is a method (`form.data()`), not a property — accessing `form.data.someField` returns `undefined` (accessing a property on the function object). Use direct property access on the form instead: `form.someField`. Confidence: 0.70

# vite
- Vite `@` alias cannot chain with `../` — use direct relative paths (e.g., `../../../addons/...`) instead of `@/../addons/...`. Confidence: 0.85
- Add `addons/` to `server.fs.allow` in `vite.config.ts` so Vite dev server can serve files from the addons directory outside `resources/`. Confidence: 0.70
- Use `import.meta.glob` to build resolver maps for addon components/pages instead of dynamic `import()` with `@vite-ignore`, which fails in Vite dev mode. Confidence: 0.70

# vue
- When merging defaults with props/API data via spread (`{ ...defaults, ...data }`), filter out `undefined` values from the data source first using `Object.fromEntries(Object.entries(data).filter(([, v]) => v !== undefined))`, otherwise `undefined` values in data override the defaults. Confidence: 0.70
- When updating a `<select>`/`AppSelect` dropdown's values (e.g., changing from `'default'` to `'1280px'`), update the options array in addition to the default value — changing only the default without updating available options leaves stale/deprecated values in the UI. Confidence: 0.70
- In toggle `@click` handlers for config values that may be `undefined` (not yet saved), use `!(value ?? defaultValue)` instead of `!value` — `!undefined` is `true`, so the first click just initializes to `true` instead of toggling off. Match the same default used in `aria-checked`. Confidence: 0.70

# architecture
- Addon access should be admin-configurable (all, logged-in, pro users) rather than hard-coded to Pro-only via `isProAvailable()`. Confidence: 0.70

# code-style
See [code-style/taste.md](code-style/taste.md)
# browser-compat
- `crypto.randomUUID()` requires secure context (HTTPS or localhost); use `Math.random().toString(36) + Date.now().toString(36)` as a fallback for non-secure origins. Confidence: 0.70

# tailwind
- For gradient text effects, use Tailwind v4 utility classes (`bg-gradient-to-r`, `bg-clip-text`, `!text-transparent`) instead of inline `:style` with `backgroundClip: 'text'` — inline CSS `background-clip: text` does not render correctly in this project's setup. Confidence: 0.70
- Avoid referencing browser-only globals (`window`, `document`) directly in Vue template expressions — use computed refs or `onMounted` instead. Confidence: 0.75

# admin-sidebar
- For addons with multiple `admin_menu` items, the sidebar renders a grouped dropdown — the group header uses `addon_name` (from addon.json `name` field), and sub-items use their individual `label` fields. For single-item addons, the `label` is rendered directly as the link text (no group header). When renaming an addon in the sidebar, update both `name` for the group header AND each `label` in `admin_menu` entries — but keep sub-item labels distinct (e.g., "Overview", "Settings") rather than duplicating the addon name. Confidence: 0.70

# admin-hero
- For the homepage hero variant with category grid + featured tools marquee, make category and featured tool selections admin-configurable via AppSelect multiselect dropdowns (with a DB fallback for featured tools when none are selected), rather than deriving them programmatically from tool data. Show these settings conditionally — only when the relevant hero variant is selected. Confidence: 0.70

# admin-rbac
- Admin permissions use a pivot table (`admin_role_permissions` with `role_id` + `permission_id`) rather than a JSON column on `admin_roles`. Use `DB::table('admin_role_permissions')->updateOrInsert(...)` to grant permissions, not `json_encode` on a `permissions` column. Confidence: 0.70

# laravel-eloquent
- Aggregate methods like `max('updated_at')` return raw database values (strings), not Carbon instances — Eloquent date casting does not apply. Use `->latest('updated_at')->value('updated_at')` or `->latest('updated_at')->first()?->updated_at` if you need a Carbon object with methods like `->timestamp`. Confidence: 0.70

# laravel-queue
- For long-running queue jobs, use `public int $timeout` (e.g., 600-1800) instead of `$tries = 1` to prevent silent failures on timeout. Confidence: 0.70
- When opening file handles with `fopen()`, wrap the write/read logic in `try/finally` to guarantee `fclose()` always runs. Confidence: 0.70
- Log warnings when database `increment()`/`decrement()` returns 0, indicating the target row may not exist. Confidence: 0.65

# workflow
- When presenting multiple improvement recommendations, implement all identified fixes rather than asking for selection. Confidence: 0.90
- When adding a feature toggle to admin settings, update every required location: Vue props interface, useForm data object, featureToggles array, FeatureSettingsController (edit + update methods), FeatureSettingsRequest validation rules, and the database settings table row. Missing any one causes the toggle to not render or fail to save. Confidence: 0.80

# design/user-dashboard
- All `/user/dashboard/*` pages must use unified card styling: `shadow-[0_18px_40px_rgba(15,23,42,0.06)]`, a single `1px border`, no `shadow-sm`, no `ring-1`, and consistent border-radius. Check existing pages (e.g., support, dashboard index) as the reference implementation before styling new dashboard pages. Confidence: 0.85

# workflow
- Never add unrequested text, cards, content sections, or make design/styling assumptions. If the user says "unified style," replicate the exact patterns from existing pages rather than inventing new layouts or adding creative flourishes. Confidence: 0.85
- When implementing "unified dashboard style" for a page, always check existing reference pages (e.g., `/user/dashboard/support`, `/user/dashboard` index) first and mirror their structure exactly — card layout, input field styles, button styles, title/section patterns, back button placement. Confidence: 0.80

# architecture
- Avoid theme presets entirely for Envato-targeted themes — they confuse buyers and create unnecessary problems. Instead, use a single settings.json merged from the modern preset JSON as the baseline, with no preset switching UI. Confidence: 0.85
- Derive special display effects from content patterns (e.g., pipe-separated text in a heading field triggers typewriter animation) rather than adding dedicated toggles and separate input fields. Confidence: 0.85
- Before adding a new config field, check if extending an existing field's options (e.g., adding a new value to a dropdown) would suffice instead of creating a parallel field that duplicates the same concern. Confidence: 0.80
- Rename FrontendPresetService to reflect its actual role (e.g., FrontendSettingsService) since it no longer handles presets — it reads defaults from settings.json and merges stored overrides. Confidence: 0.65
