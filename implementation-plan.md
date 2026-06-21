# Frontend Theme System Refactor — Current State

## Completed

### 1. Storage model
- frontend runtime now resolves from dedicated `settings` keys only:
  - `frontend_theme_preset`
  - `frontend_theme_settings`
  - `frontend_header_settings`
  - `frontend_footer_settings`
  - `frontend_homepage_settings`
  - `frontend_custom_code`
- legacy runtime dependence on separate buyer-facing `header_preset`, `footer_preset`, and `homepage_preset` keys has been removed.

### 2. Preset architecture
- split preset folders were replaced with standalone preset packages under:
  - `resources/themes/default/presets/*.json`
- active preset packages:
  - `modern.json`
  - `classic.json`
  - `minimal.json`
  - `dark.json`
  - `startup.json`
  - `enterprise.json`
- each preset package contains:
  - `theme`
  - `header`
  - `footer`
  - `homepage`

### 3. Admin theme settings UX
- buyer-facing theme settings were simplified in:
  - [DefaultThemeSettings.vue](/d:/laragon/www/makeai/resources/js/Pages/Admin/Appearance/DefaultThemeSettings.vue)
- embedded builder UIs were removed from the theme settings experience.
- one preset picker now applies the full frontend package.
- header, footer, and homepage tabs now work as manual override panels only.

### 4. Controller and routes
- [ThemeAddonController.php](/d:/laragon/www/makeai/app/Http/Controllers/Admin/ThemeAddonController.php) now serves only simple preset/settings data for the default frontend theme page.
- separate header/footer/homepage preset apply endpoints were removed from active buyer flow.
- Ziggy was regenerated after route cleanup.

### 5. Runtime resolver
- [FrontendPresetService.php](/d:/laragon/www/makeai/app/Services/FrontendPresetService.php) is now the active source of truth for:
  - preset package loading
  - stored override loading
  - resolved theme data
  - resolved header data
  - resolved footer data
  - resolved homepage data

### 6. Legacy cleanup already done
- old split preset JSON files were removed.
- old split preset directories were removed.
- legacy frontend migration command remains only for one-time migration and cleanup:
  - [MigrateLegacyFrontendThemeSettings.php](/d:/laragon/www/makeai/app/Console/Commands/MigrateLegacyFrontendThemeSettings.php)

## Active Architecture

### One source of truth
Frontend rendering should resolve in this order:

1. stored buyer overrides from `frontend_*_settings`
2. selected preset package defaults
3. hardcoded safe PHP defaults in the resolver service

### Preset package schema
Each file under `resources/themes/default/presets/*.json` should follow this shape:

```json
{
  "name": "Modern",
  "slug": "modern",
  "description": "Clean rounded frontend preset for SaaS products.",
  "thumbnail": "modern.png",
  "theme": {},
  "header": {},
  "footer": {},
  "homepage": {}
}
```

### Theme metadata file
- [settings.json](/d:/laragon/www/makeai/resources/themes/default/settings.json) is now metadata-only.
- it should not be expanded again into fake runtime field definitions.

## Remaining Work

### 1. Runtime verification pass
- manually verify public frontend behavior for all preset packages:
  - header
  - footer
  - homepage
  - CSS variables
  - body background image/color
  - dark/light mode default

### 2. Runtime cleanup pass
- review these runtime surfaces for any remaining builder-era assumptions:
  - [AppHeader.vue](/d:/laragon/www/makeai/resources/js/Components/AppHeader.vue)
  - [AppFooter.vue](/d:/laragon/www/makeai/resources/js/Components/AppFooter.vue)
  - [Welcome.vue](/d:/laragon/www/makeai/resources/js/Pages/Welcome.vue)
  - [ThemeCssController.php](/d:/laragon/www/makeai/app/Http/Controllers/ThemeCssController.php)

### 3. Final legacy search
- confirm no active frontend runtime path still depends on:
  - `header_config`
  - `footer_config`
  - `homepage_config`
  - old builder payload shapes

## Verification Checklist

### Build
- `php -l app/Services/FrontendPresetService.php`
- `php -l app/Http/Controllers/Admin/ThemeAddonController.php`
- `npm run build`

### Admin
- applying a preset package updates theme, header, footer, and homepage together
- manual overrides persist after applying a package
- background image upload and remove flow works

### Public frontend
- desktop header renders correctly
- mobile top header renders correctly
- mobile bottom header renders correctly when enabled
- footer variants render correctly
- homepage composition changes correctly by preset package

## Recommendation

The next implementation slice should be runtime verification and targeted cleanup, not another data-model rewrite. The package-based system is already in place; remaining work should focus on removing any last builder-era assumptions from frontend rendering and validating all preset combinations end to end.
