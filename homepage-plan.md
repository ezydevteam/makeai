# Homepage Section Components — Implementation Plan

## Goal
Extract MagicAI-style homepage sections from `Welcome.vue` into reusable `.vue` component files under `Components/Home/`. Each component receives its config as props and renders independently. Presets swap styling via CSS custom properties + config overrides — no component duplication.

---

## 1. Component File Structure

```
resources/js/Components/Home/
├── HeroSection.vue
├── FeaturesSection.vue
├── ToolsShowcaseSection.vue
├── HowItWorksSection.vue
├── PricingSection.vue
├── TestimonialsSection.vue
├── FaqSection.vue
├── StatsBarSection.vue
├── CtaBannerSection.vue
├── LatestPostsSection.vue
├── NewsletterSection.vue
├── IntegrationsSection.vue
├── CustomHtmlSection.vue
├── RichtextSection.vue
├── ImageCarouselSection.vue
├── AdSlotSection.vue
├── AnnouncementSection.vue
├── AllToolsSection.vue
└── index.ts                  # Exports all sections + SectionType mapping
```

---

## 2. Component Interface

Every section component follows the same contract:

```typescript
// Shared prop interface
interface SectionComponentProps {
  section: HomepageSection      // { id, type, enabled, core, config }
  locale?: string               // For RTL support
  class?: string                // Extra CSS classes
}
```

Where `config` is a `Record<string, SectionConfigValue>` containing all visual and content options for that section (headings, colors, images, layout choice, etc.)

---

## 3. Section Components Breakdown

### HeroSection.vue
- Props: `section` (HeroConfig), `featuredTools`
- Config: heading, subheading, cta_text, cta_link, secondary_cta_text, secondary_cta_link, layout (centered | split), background_style (solid | gradient | image), background_image, animation
- Renders: Full-width hero with headline, subhead, CTA buttons, optional background image/gradient

### FeaturesSection.vue
- Props: `section` (FeaturesConfig)
- Config: heading, subheading, features[] (icon, title, description), columns (3 | 4), card_style (default | bordered | shadow)
- Renders: Grid of feature cards with icons

### ToolsShowcaseSection.vue
- Props: `section` (ToolsShowcaseConfig)
- Config: items[] (tool_type, heading, subheading, image, features[], cta_text, cta_link), layout (alternating | cards)
- Renders: Alternating rows showing tool capabilities (text gen, image gen, code gen, etc.)

### HowItWorksSection.vue
- Props: `section` (HowItWorksConfig)
- Config: heading, subheading, steps[] (step_number, title, description), layout (horizontal | vertical)
- Renders: Step-by-step numbered process

### PricingSection.vue
- Props: `section` (PricingConfig), `pricingPlans`, `pricingCountry`, `pricingSettings`
- Config: heading, subheading, show_annual, show_lifetime, show_monthly
- Renders: Pricing cards with plan features, toggle for monthly/annual/lifetime

### TestimonialsSection.vue
- Props: `section` (TestimonialsConfig), `testimonials`
- Config: heading, subheading, style (carousel | grid), autoplay, show_rating
- Renders: Testimonial cards with avatar, name, role, quote

### FaqSection.vue
- Props: `section` (FaqConfig), `faqs`
- Config: heading, subheading, layout (accordion | grid)
- Renders: FAQ accordion or grid layout

### StatsBarSection.vue
- Props: `section` (StatsConfig)
- Config: items[] (label, value, icon, suffix), animated
- Renders: Horizontal bar of statistics with animated counters

### CtaBannerSection.vue
- Props: `section` (CtaConfig)
- Config: heading, subheading, cta_text, cta_link, background_style, background_image
- Renders: Full-width call-to-action banner

### LatestPostsSection.vue
- Props: `section` (LatestPostsConfig), `recentPosts`
- Config: heading, subheading, columns (2 | 3 | 4), show_excerpt, show_date
- Renders: Blog post cards grid

### NewsletterSection.vue
- Props: `section` (NewsletterConfig)
- Config: heading, subheading, background_style, placeholder_text, button_text
- Renders: Email signup form with background

### IntegrationsSection.vue
- Props: `section` (IntegrationsConfig)
- Config: heading, subheading, items[] (name, logo, description), style (grid | carousel | marquee)
- Renders: Integration partner logos

### CustomHtmlSection.vue
- Props: `section` (CustomHtmlConfig)
- Config: content (raw HTML)
- Renders: Raw HTML/JS injected into page

### RichtextSection.vue
- Props: `section` (RichtextConfig)
- Config: content (HTML), alignment, max_width
- Renders: Rich text content block

### ImageCarouselSection.vue
- Props: `section` (ImageCarouselConfig)
- Config: images[] (src, alt, link), autoplay, interval, show_nav
- Renders: Image slider/carousel

### AdSlotSection.vue
- Props: `section` (AdSlotConfig)
- Config: ad_type (adsense | image_link | custom_html), content
- Renders: Ad placement

### AnnouncementSection.vue
- Props: `section` (AnnouncementConfig)
- Config: text, background_color, text_color, dismissible, link
- Renders: Top announcement bar

### AllToolsSection.vue
- Already exists as `Components/AllToolsSection.vue` — just import and wrap
- Props: `section`, `allTools`, `allToolCategories`

---

## 4. Welcome.vue Refactoring

The `Welcome.vue` template (~1825 lines) compresses to:

```html
<template>
  <Layout>
    <template v-for="section in enabledSections" :key="section.id">
      <HeroSection           v-if="section.type === 'hero'"           :section="section" />
      <FeaturesSection       v-else-if="section.type === 'features'"  :section="section" />
      <ToolsShowcaseSection  v-else-if="section.type === 'tools_showcase'" :section="section" />
      <HowItWorksSection     v-else-if="section.type === 'how_it_works'" :section="section" />
      <PricingSection        v-else-if="section.type === 'pricing'"   :section="section" :pricing-plans="pricingPlans" />
      <TestimonialsSection   v-else-if="section.type === 'testimonials'" :section="section" :testimonials="testimonials" />
      <FaqSection            v-else-if="section.type === 'faq'"       :section="section" :faqs="faqs" />
      <StatsBarSection       v-else-if="section.type === 'stats_bar'" :section="section" />
      <CtaBannerSection      v-else-if="section.type === 'cta_banner'" :section="section" />
      <LatestPostsSection    v-else-if="section.type === 'latest_posts'" :section="section" :recent-posts="recentPosts" />
      <NewsletterSection     v-else-if="section.type === 'newsletter'" :section="section" />
      <IntegrationsSection   v-else-if="section.type === 'integrations'" :section="section" />
      <CustomHtmlSection     v-else-if="section.type === 'custom_html'" :section="section" />
      <RichtextSection       v-else-if="section.type === 'richtext'"  :section="section" />
      <ImageCarouselSection  v-else-if="section.type === 'image_carousel'" :section="section" />
      <AdSlotSection         v-else-if="section.type === 'ad_slot'"   :section="section" />
      <AnnouncementSection   v-else-if="section.type === 'announcement'" :section="section" />
      <AllToolsSection       v-else-if="section.type === 'all_tools'" :section="section" :all-tools="allTools" :all-tool-categories="allToolCategories" />
    </template>
  </Layout>
</template>
```

---

## 5. Preset Styling Mechanism

Each preset JSON supplies config values. Components style themselves based on config + CSS custom properties set by the theme:

```json
// Example: hero section in modern.json
"homepage_config": {
    "sections": [
        {
            "type": "hero",
            "config": {
                "heading": "The Ultimate AI Platform",
                "layout": "centered",
                "background_style": "gradient",
                "animation": "fade-up"
            }
        },
        {
            "type": "features",
            "config": {
                "columns": 3,
                "card_style": "shadow"
            }
        }
    ]
}
```

Components use scoped styles + CSS variables from theme (e.g., `--color-primary`, `--border-radius`) so swapping a preset changes the look without touching components.

---

## 6. File-by-File Implementation Order

| Step | Files | What |
|------|-------|------|
| 1 | Create `Components/Home/index.ts` | Export all section components as named exports + `sectionComponentMap` for dynamic lookup |
| 2 | `ToolsShowcaseSection.vue` | MagicAI alternating tool rows (highest visual impact) |
| 3 | `HeroSection.vue` | Full hero with gradient, CTAs, animation |
| 4 | `FeaturesSection.vue` | Feature cards grid |
| 5 | `HowItWorksSection.vue` | Step cards |
| 6 | `PricingSection.vue` | Pricing table with toggle |
| 7 | `TestimonialsSection.vue` | Carousel/grid |
| 8 | `FaqSection.vue` | Accordion |
| 9 | `StatsBarSection.vue` | Counters |
| 10 | `CtaBannerSection.vue` | CTA banner |
| 11 | `LatestPostsSection.vue` | Blog grid |
| 12 | `NewsletterSection.vue` | Signup form |
| 13 | Remaining sections | Integrations, CustomHtml, Richtext, ImageCarousel, AdSlot, Announcement, AllTools |
| 14 | Refactor `Welcome.vue` | Replace inline template with dynamic `<component :is="..." />` or v-if chain importing from Home/ |

---

## 7. Future-Proofing

- **Dynamic component rendering** — use `sectionComponentMap[s.type]` to resolve components dynamically, making new section types a single registration
- **No hardcoded data in components** — every text, color, image comes from `section.config` props
- **Preset overrides** — components respect `config.customClass` for extra CSS class when a preset needs unique styling
