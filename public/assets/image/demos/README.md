# Demo Screenshots

Drop a screenshot here and it renders automatically in the demo style selector
(the floating brush → "Choose a demo style" modal). No database entry, no rebuild —
the file just has to exist and be named after the demo.

## Naming

`<demo-name>.<ext>` where `<ext>` is one of: `png`, `jpg`, `jpeg`, `webp`
(probed in that order; the first match wins).

`<demo-name>` is:

- for a **theme preset** — the preset id (its JSON filename under
  `resources/themes/<theme>/presets/`), e.g. `default`, `midnight`, `sunset`
- for an **addon homepage** — the addon slug, e.g. `ai-chatbot`, `ai-image-pro`

## Examples

```
public/assets/image/demos/default.png
public/assets/image/demos/midnight.png
public/assets/image/demos/sunset.png
public/assets/image/demos/ai-chatbot.png     # shows when the ai-chatbot addon is active
public/assets/image/demos/ai-image-pro.png   # shows when the ai-image-pro addon is active
```

## Recommended

- Aspect ratio ~16:10 (cards crop to that); ~640×400 or larger.
- Keep files reasonably small (a demo screenshot, not a hero asset).
