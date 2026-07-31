# Demo host source images

The logo and favicon for the **public demo site**. Drop the files here; nothing else uses
this directory.

| File | Setting it fills | Notes |
|---|---|---|
| `logo-light.png` | `site_logo_light` | Shown on light backgrounds. `png`, `svg`, `webp` all work. |
| `logo-dark.png` | `site_logo_dark` | Shown on dark backgrounds; the header falls back to the light one if absent. |
| `favicon.png` | `site_favicon_png` | The primary `<link rel="icon">`. |
| — | `site_favicon_ico` | Optional and unset. `app.blade.php` renders the `.ico` only as `rel="alternate icon"`, which nothing current falls back to. Set `DEMO_FAVICON_ICO` to a filename here if you want one. |

`DemoProvisionSeeder` copies each file onto the public disk and points the branding
setting at the copy, on every `demo:reset`. A file that is not here is skipped with a
warning in the reset output and that slot stays empty — the header then renders the site
name as text.

## Why here and not somewhere else

`demo:reset` runs `migrate:fresh` (wiping the settings table) and then
`demo:sweep-uploads` (deleting `storage/app/public` and `storage/app/private` wholesale),
so nothing under `storage/` can be the source of truth. And the demo cannot be fixed
through the UI afterwards: `DemoMode` blocks every write that is not on its allowlist, and
Appearance › Branding is not on it. This directory ships as part of the release and is
never written to at runtime, which is what makes it survive.

In the distribution layout it lands at `core/public/demo-assets/`, which the web server
denies — that is fine and intended. The seeder reads it from disk; only the copies it
writes to the public disk are ever served.

## Renaming the files

The filenames are config, not hardcoded — set `DEMO_LOGO_LIGHT`, `DEMO_LOGO_DARK`,
`DEMO_FAVICON_ICO` or `DEMO_FAVICON_PNG` in `.env` to a different filename in this
directory. Values are treated as a filename only (`basename()`), never a path.
