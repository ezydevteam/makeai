# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Architecture
- AI generation fields should use global settings (default provider + model, fallback provider + model) instead of hardcoded values. When a feature-specific provider API key is not configured, fall back to the global AI settings provider. Confidence: 0.80
- For RAG embedding model selection, use the fallback/default provider from global AI provider settings when no specific embedding model is configured. Confidence: 0.70
- The project's base Controller (App\Http\Controllers\Controller) does not extend \Illuminate\Routing\Controller and has no middleware() method — apply middleware on routes directly, not via $this->middleware() in controller constructors. Confidence: 0.70

# admin-ui
- Use AppSelect and AppColorPicker components instead of native &lt;select&gt; and color text inputs in admin panel interfaces. Confidence: 0.75

# vue
- Destructure `{ t }` from `useTranslate()` — the composable returns `{ t }` not the function directly. Confidence: 0.70
- When using `v-else` with `v-for`, avoid `<template v-else>` wrapping a child with `v-for` — Vue resolves the iteration variable at the `<template>` scope where it doesn't exist. Instead, move `v-else v-for` directly onto the child element. Confidence: 0.65

# inertia
- Inertia render paths for addon pages must use `Addons/{addon-slug}/Path/To/Page` format (e.g., `Addons/ai-image-editor/Admin/Settings`), not `AddonName::Path/To/Page`. The `app.ts` resolver strips the `Addons/` prefix and looks up `{slug}/Path` in the addon page map. Confidence: 0.75

# vite
- Vite `@` alias cannot chain with `../` — use direct relative paths (e.g., `../../../addons/...`) instead of `@/../addons/...`. Confidence: 0.85
- Add `addons/` to `server.fs.allow` in `vite.config.ts` so Vite dev server can serve files from the addons directory outside `resources/`. Confidence: 0.70
- Use `import.meta.glob` to build resolver maps for addon components/pages instead of dynamic `import()` with `@vite-ignore`, which fails in Vite dev mode. Confidence: 0.70

# vue
- When merging defaults with props/API data via spread (`{ ...defaults, ...data }`), filter out `undefined` values from the data source first using `Object.fromEntries(Object.entries(data).filter(([, v]) => v !== undefined))`, otherwise `undefined` values in data override the defaults. Confidence: 0.70

# architecture
- Addon access should be admin-configurable (all, logged-in, pro users) rather than hard-coded to Pro-only via `isProAvailable()`. Confidence: 0.70

# code-style
- Prefer simple, proven solutions (existing tools, libraries, shell commands) over custom-built implementations that are prone to bugs. Confidence: 0.80
- When implementing a module or feature, ensure all parts are complete — don't omit sub-features like admin article editors (Tiptap), required UI components, or supporting functionality that the prompt/context clearly implies. Confidence: 0.70

# browser-compat
- `crypto.randomUUID()` requires secure context (HTTPS or localhost); use `Math.random().toString(36) + Date.now().toString(36)` as a fallback for non-secure origins. Confidence: 0.70
- Avoid referencing browser-only globals (`window`, `document`) directly in Vue template expressions — use computed refs or `onMounted` instead. Confidence: 0.75

# admin-sidebar
- For addons with multiple `admin_menu` items, the sidebar renders a grouped dropdown — the group header uses `addon_name` (from addon.json `name` field), and sub-items use their individual `label` fields. For single-item addons, the `label` is rendered directly as the link text (no group header). When renaming an addon in the sidebar, update both `name` for the group header AND each `label` in `admin_menu` entries — but keep sub-item labels distinct (e.g., "Overview", "Settings") rather than duplicating the addon name. Confidence: 0.70

# admin-rbac
- Admin permissions use a pivot table (`admin_role_permissions` with `role_id` + `permission_id`) rather than a JSON column on `admin_roles`. Use `DB::table('admin_role_permissions')->updateOrInsert(...)` to grant permissions, not `json_encode` on a `permissions` column. Confidence: 0.70

# laravel-queue
- For long-running queue jobs, use `public int $timeout` (e.g., 600-1800) instead of `$tries = 1` to prevent silent failures on timeout. Confidence: 0.70
- When opening file handles with `fopen()`, wrap the write/read logic in `try/finally` to guarantee `fclose()` always runs. Confidence: 0.70
- Log warnings when database `increment()`/`decrement()` returns 0, indicating the target row may not exist. Confidence: 0.65

# workflow
- When presenting multiple improvement recommendations, implement all identified fixes rather than asking for selection. Confidence: 0.90
