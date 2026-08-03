# Hero Backgrounds

Background media referenced by a theme preset's hero section — the file a preset points
`hero_background_url` at when `show_hero_background` is `true`.

Unlike an admin upload (which lands in storage), these ship with the repo so a freshly
installed preset renders its hero exactly as designed, with no media library step.

## Naming

`<preset-id>-hero.<ext>` where `<preset-id>` is the preset's JSON filename under
`resources/themes/<theme>/presets/`, e.g. `sunset-hero.svg`.

## Referencing one from a preset

```json
"show_hero_background": true,
"hero_background_type": "image",
"hero_background_url": "/assets/image/demo-assets/hero/sunset-hero.svg",
"show_hero_gradient_overlay": true,
"overlay_opacity": 45
```

## Recommended

- 1920×1080 or wider — the hero crops with `object-cover`.
- Go dark or mid-tone. Background media forces the hero's text to white and draws a
  black top-to-bottom overlay over it; a light image leaves the headline unreadable.
- SVG where the art allows (gradients, orbs, grids) — it stays sharp at any width and
  costs a few KB. Use `webp`/`jpg` only for photography.
