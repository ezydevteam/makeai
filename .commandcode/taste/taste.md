# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# Architecture
- AI generation fields should use global settings (default provider + model, fallback provider + model) instead of hardcoded values. Confidence: 0.75
- For RAG embedding model selection, use the fallback/default provider from global AI provider settings when no specific embedding model is configured. Confidence: 0.70

# admin-ui
- Use AppSelect and AppColorPicker components instead of native &lt;select&gt; and color text inputs in admin panel interfaces. Confidence: 0.75

# vue
- Destructure `{ t }` from `useTranslate()` — the composable returns `{ t }` not the function directly. Confidence: 0.70

# vite
- Vite `@` alias cannot chain with `../` — use direct relative paths (e.g., `../../../addons/...`) instead of `@/../addons/...`. Confidence: 0.85
- Add `addons/` to `server.fs.allow` in `vite.config.ts` so Vite dev server can serve files from the addons directory outside `resources/`. Confidence: 0.70
- Use `import.meta.glob` to build resolver maps for addon components/pages instead of dynamic `import()` with `@vite-ignore`, which fails in Vite dev mode. Confidence: 0.70

# code-style
- Prefer simple, proven solutions (existing tools, libraries, shell commands) over custom-built implementations that are prone to bugs. Confidence: 0.80

# browser-compat
- `crypto.randomUUID()` requires secure context (HTTPS or localhost); use `Math.random().toString(36) + Date.now().toString(36)` as a fallback for non-secure origins. Confidence: 0.70

# laravel-queue
- For long-running queue jobs, use `public int $timeout` (e.g., 600-1800) instead of `$tries = 1` to prevent silent failures on timeout. Confidence: 0.70
- When opening file handles with `fopen()`, wrap the write/read logic in `try/finally` to guarantee `fclose()` always runs. Confidence: 0.70
- Log warnings when database `increment()`/`decrement()` returns 0, indicating the target row may not exist. Confidence: 0.65
