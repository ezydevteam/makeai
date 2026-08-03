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
public/assets/image/demo-assets/demos/default.png
public/assets/image/demo-assets/demos/saas-startup.png
public/assets/image/demo-assets/demos/marketing-agency.png
public/assets/image/demo-assets/demos/corporate-enterprise.png
public/assets/image/demo-assets/demos/creative-studio.png
public/assets/image/demo-assets/demos/minimal-editorial.png
public/assets/image/demo-assets/demos/tools-directory.png
public/assets/image/demo-assets/demos/midnight.png
public/assets/image/demo-assets/demos/sunset.png
public/assets/image/demo-assets/demos/ai-chatbot.png     # shows when the ai-chatbot addon is active
public/assets/image/demo-assets/demos/ai-image-pro.png   # shows when the ai-image-pro addon is active
```

## Recommended

- Aspect ratio ~16:10 (cards crop to that); ~640×400 or larger.
- Keep files reasonably small (a demo screenshot, not a hero asset).

## Regenerating the preset shots

The nine preset images here are homepage captures taken through the demo selector,
which swaps a preset in memory without persisting anything. With `DEMO_ENABLED=true`:

1. `GET /__demo/select?demo=preset:<id>` — sets the `demo_selection` cookie.
2. Load `/` in a 1440×900 viewport.
3. Hide the chrome that is not part of the design, then screenshot the viewport.
4. Downscale to 960×600 and save as `<id>.png` (~50–180 KB each).

Three things will otherwise spoil the shot:

- **Top bars.** The demo notice is the first child of `.frontend-theme`; the coupon and
  announcement stack is the `position: sticky; z-index: 60` block. Hiding them is not
  enough — the sticky header offsets itself by `--top-banners-height`, so set that to
  `0px` too or the header floats down the page.
- **The signup pop-up** fires on a timer, so a slow capture catches it mid-page. Hide the
  whole dialog subtree (its backdrop is a sibling, not a child of the panel).
- **The cookie lags a request.** After switching, assert the rendered `<h1>` is the
  preset's own headline before shooting — otherwise you capture the previous preset.
