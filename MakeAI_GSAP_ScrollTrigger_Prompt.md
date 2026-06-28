# MakeAI — GSAP ScrollTrigger Homepage Animation Implementation Prompt

> **Target AI:** Gemini 2.5 Pro
> **Task:** Add GSAP ScrollTrigger scroll animations to the MakeAI homepage section components
> **Stack:** Vue 3 + TypeScript + Composition API (`<script setup>`) + Inertia.js SSR + Tailwind CSS v4

---

## ⚠️ CRITICAL INVARIANTS — READ FIRST, NEVER VIOLATE

These rules are non-negotiable. Any code that violates them is wrong:

1. **Vue 3 Composition API with `<script setup>` ONLY** — never Options API, never `defineComponent({})`, never `data()` / `methods:`
2. **TypeScript in all `.vue` files** — proper types everywhere, no `any` unless explicitly noted below for GSAP (acceptable due to no official TS bundle)
3. **GSAP + ScrollTrigger MUST be dynamically imported inside `onMounted()`** — NEVER top-level import. Inertia.js runs SSR where `window` does not exist. Top-level import will crash the SSR server.
4. **`gsap.context()` is mandatory** for every animation block — store result in a variable and call `.revert()` in `onUnmounted()` to prevent memory leaks on Inertia page navigation
5. **`once: true` on ALL ScrollTrigger instances** — animations play once, never repeat on re-scroll
6. **`prefers-reduced-motion` check at the top of every `onMounted()`** — if true, return early without initializing any animations
7. **`ScrollTrigger.batch()` for repeated elements** (cards, tool items, FAQ items, etc.) — never create individual ScrollTrigger instances in a loop
8. **Hero section has NO ScrollTrigger** — it's a page-load timeline animation, fires immediately on mount
9. **No hardcoded brand text** — use `$t()` for all user-visible strings (already handled by existing components, don't add new hardcoded strings)
10. **Do not modify** any non-animation logic — only add GSAP animation code to existing components, never change props, emits, data fetching, or business logic

---

## PROJECT CONTEXT

MakeAI is a white-label AI SaaS platform built with:
- **Laravel 13 + Inertia.js (SSR enabled)** backend
- **Vue 3 + TypeScript** frontend (`<script setup>` throughout)
- **Tailwind CSS v4** for styling
- **Homepage** is at `resources/js/Pages/Home.vue` using `GuestLayout.vue`
- Homepage is composed of **section components**, each in `resources/js/Components/Homepage/`

### Existing Homepage Section Components (already exist — you are MODIFYING them):

```
resources/js/Components/Homepage/
  HeroSection.vue
  StatsBar.vue
  FeaturesSection.vue
  HowItWorksSection.vue
  AllToolsSection.vue
  TestimonialsSection.vue
  PricingSection.vue
  FaqSection.vue
  CtaBannerSection.vue
  NewsletterSection.vue
  AllToolsSection.vue
  LatestPostsSection.vue
  ToolsShowcaseSection.vue
  LatestPostsSection.vue
```

Each component already has its template, props, and logic. **You are only adding GSAP animation code** to each — do not change anything else.

---

## TASK

### 1. Install GSAP

Add to `package.json` dependencies:

```bash
npm install gsap
```

No other packages needed. GSAP's ScrollTrigger plugin is included with the main `gsap` package.

---

### 2. Create Shared Composable

Create a new file:

**`resources/js/composables/useGsapScrollAnimation.ts`**

```typescript
import { onUnmounted } from 'vue'

/**
 * Shared utility for GSAP context cleanup.
 * Each component creates its own gsap.context() and stores it.
 * Call cleanup() in onUnmounted() to kill all ScrollTriggers and animations.
 */
export function useGsapScrollAnimation() {
  let ctx: { revert: () => void } | null = null

  const setContext = (gsapCtx: { revert: () => void }) => {
    ctx = gsapCtx
  }

  onUnmounted(() => {
    ctx?.revert()
  })

  return { setContext }
}
```

---

### 3. Modify Each Section Component

For each component below, add the GSAP animation code **only in the `<script setup>` block**. Do not change the `<template>` or `<style>` unless a `ref` needs to be added to an existing element.

---

#### 3.1 — `HeroSection.vue`

**Animation:** Page-load timeline. No ScrollTrigger. Fires immediately on mount.

**Refs needed on template elements:**
- `heroRef` → root `<section>` element
- `headingRef` → main `<h1>` heading
- `subtextRef` → subtitle `<p>`
- `ctaRef` → CTA buttons wrapper `<div>`
- `imageRef` → hero image/illustration wrapper `<div>`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const heroRef = ref<HTMLElement | null>(null)
const headingRef = ref<HTMLElement | null>(null)
const subtextRef = ref<HTMLElement | null>(null)
const ctaRef = ref<HTMLElement | null>(null)
const imageRef = ref<HTMLElement | null>(null)

let gsapCtx: any = null

onMounted(async () => {
  // Respect reduced motion preference
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')

  gsapCtx = gsap.context(() => {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })

    tl.from(headingRef.value, { y: 40, opacity: 0, duration: 0.8 })
      .from(subtextRef.value, { y: 30, opacity: 0, duration: 0.7 }, '-=0.5')
      .from(ctaRef.value,     { y: 20, opacity: 0, duration: 0.6 }, '-=0.4')
      .from(imageRef.value,   { x: 60, opacity: 0, duration: 0.9, ease: 'power2.out' }, '-=0.6')
  }, heroRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.2 — `StatsBar.vue`

**Animation:** Section fades up on scroll. Then each counter animates from 0 to its target value (count-up effect).

**Refs needed:**
- `sectionRef` → root `<section>` element
- Each counter `<span>` must have class `stat-counter` and a `data-target` attribute with the numeric end value

**Example template structure** (adapt to existing markup):
```html
<span class="stat-counter" data-target="1000000" data-suffix="M+">0</span>
<span class="stat-counter" data-target="50000"   data-suffix="K+">0</span>
<span class="stat-counter" data-target="255"     data-suffix="+">0</span>
<span class="stat-counter" data-target="99.9"    data-suffix="%" data-decimal="1">0</span>
```

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section entrance
    gsap.from(sectionRef.value, {
      opacity: 0,
      y: 40,
      duration: 0.8,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: sectionRef.value,
        start: 'top 85%',
        once: true,
      },
    })

    // Counter animations
    const counters = sectionRef.value!.querySelectorAll<HTMLElement>('.stat-counter')
    counters.forEach((el, i) => {
      const target = parseFloat(el.dataset.target ?? '0')
      const suffix = el.dataset.suffix ?? ''
      const decimals = parseInt(el.dataset.decimal ?? '0')
      const obj = { val: 0 }

      gsap.to(obj, {
        val: target,
        duration: 2,
        ease: 'power2.out',
        delay: i * 0.15,
        onUpdate: () => {
          el.textContent = obj.val.toFixed(decimals) + suffix
        },
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 80%',
          once: true,
        },
      })
    })
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.3 — `FeaturesSection.vue`

**Animation:** Heading fades up. Feature cards stagger fade-up using `ScrollTrigger.batch()`.

**Refs / classes needed:**
- `sectionRef` → root `<section>`
- Heading element: add class `features-heading` (or use existing heading ref)
- Each feature card: add class `feature-card`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section heading
    gsap.from(sectionRef.value!.querySelector('.features-heading'), {
      opacity: 0,
      y: 30,
      duration: 0.7,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: sectionRef.value,
        start: 'top 85%',
        once: true,
      },
    })

    // Feature cards — batch stagger
    ScrollTrigger.batch(
      sectionRef.value!.querySelectorAll('.feature-card'),
      {
        onEnter: (elements: Element[]) => {
          gsap.from(elements, {
            opacity: 0,
            y: 50,
            duration: 0.6,
            stagger: 0.12,
            ease: 'power2.out',
          })
        },
        once: true,
        start: 'top 88%',
      }
    )
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.4 — `HowItWorksSection.vue`

**Animation:** Steps alternate — odd steps enter from left, even steps from right. If there's a connector line element between steps, it draws in with a scrub effect.

**Classes needed:**
- Each step item: class `step-item`
- Connector line (if exists): class `steps-connector-line`
- Steps wrapper: class `steps-wrapper`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Section heading
    gsap.from(sectionRef.value!.querySelector('h2, h3'), {
      opacity: 0,
      y: 30,
      duration: 0.7,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: sectionRef.value,
        start: 'top 85%',
        once: true,
      },
    })

    // Steps — alternating left/right
    const steps = sectionRef.value!.querySelectorAll<HTMLElement>('.step-item')
    steps.forEach((step, i) => {
      gsap.from(step, {
        opacity: 0,
        x: i % 2 === 0 ? -60 : 60,
        duration: 0.7,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: step,
          start: 'top 82%',
          once: true,
        },
      })
    })

    // Connector line draw (only if element exists)
    const line = sectionRef.value!.querySelector<HTMLElement>('.steps-connector-line')
    if (line) {
      gsap.from(line, {
        scaleY: 0,
        transformOrigin: 'top center',
        ease: 'none',
        scrollTrigger: {
          trigger: sectionRef.value!.querySelector('.steps-wrapper') ?? sectionRef.value,
          start: 'top 70%',
          end: 'bottom 30%',
          scrub: 1,
        },
      })
    }
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.5 — `ToolsShowcaseSection.vue`

**Animation:** Tool cards scale + fade in with batch stagger. `batchMax: 12` for performance (tool grid can have 200+ items).

**Classes needed:**
- `sectionRef` → root `<section>`
- Each tool card: class `tool-card`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Heading
    gsap.from(sectionRef.value!.querySelector('h2, h3'), {
      opacity: 0,
      y: 30,
      duration: 0.7,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: sectionRef.value,
        start: 'top 85%',
        once: true,
      },
    })

    // Tool cards — batch with batchMax for performance
    ScrollTrigger.batch(
      sectionRef.value!.querySelectorAll('.tool-card'),
      {
        onEnter: (elements: Element[]) => {
          gsap.from(elements, {
            opacity: 0,
            scale: 0.88,
            duration: 0.5,
            stagger: 0.06,
            ease: 'back.out(1.4)',
          })
        },
        once: true,
        start: 'top 90%',
        batchMax: 12,
      }
    )
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.6 — `TestimonialsSection.vue`

**Animation:** Cards stagger fade-up.

**Classes needed:**
- `sectionRef` → root `<section>`
- Each testimonial card: class `testimonial-card`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    gsap.from(sectionRef.value!.querySelector('h2, h3'), {
      opacity: 0, y: 30, duration: 0.7, ease: 'power2.out',
      scrollTrigger: { trigger: sectionRef.value, start: 'top 85%', once: true },
    })

    ScrollTrigger.batch(
      sectionRef.value!.querySelectorAll('.testimonial-card'),
      {
        onEnter: (elements: Element[]) => {
          gsap.from(elements, {
            opacity: 0, y: 40, duration: 0.65, stagger: 0.15, ease: 'power2.out',
          })
        },
        once: true,
        start: 'top 85%',
      }
    )
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.7 — `PricingSection.vue`

**Animation:** Side cards slide in from left/right. Featured (Pro/highlighted) card scales up for emphasis.

**Classes needed:**
- `sectionRef` → root `<section>`
- First pricing card: class `pricing-card` + `pricing-card--first`
- Featured/highlighted card: class `pricing-card` + `pricing-card--featured`
- Last pricing card: class `pricing-card` + `pricing-card--last`

> If only 2 plans exist, skip `pricing-card--last`.

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    const trigger = {
      scrollTrigger: { trigger: sectionRef.value, start: 'top 80%', once: true },
    }

    gsap.from(sectionRef.value!.querySelector('h2, h3'), {
      opacity: 0, y: 30, duration: 0.7, ease: 'power2.out', ...trigger,
    })

    const firstCard    = sectionRef.value!.querySelector('.pricing-card--first')
    const featuredCard = sectionRef.value!.querySelector('.pricing-card--featured')
    const lastCard     = sectionRef.value!.querySelector('.pricing-card--last')

    if (firstCard) {
      gsap.from(firstCard, { opacity: 0, x: -50, duration: 0.7, ease: 'power2.out', ...trigger })
    }
    if (featuredCard) {
      gsap.from(featuredCard, {
        opacity: 0, scale: 0.9, duration: 0.8, delay: 0.15, ease: 'back.out(1.2)', ...trigger,
      })
    }
    if (lastCard) {
      gsap.from(lastCard, { opacity: 0, x: 50, duration: 0.7, delay: 0.1, ease: 'power2.out', ...trigger })
    }
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.8 — `FaqSection.vue`

**Animation:** FAQ accordion items stagger fade-up.

**Classes needed:**
- `sectionRef` → root `<section>`
- Each FAQ item: class `faq-item`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    gsap.from(sectionRef.value!.querySelector('h2, h3'), {
      opacity: 0, y: 30, duration: 0.7, ease: 'power2.out',
      scrollTrigger: { trigger: sectionRef.value, start: 'top 85%', once: true },
    })

    ScrollTrigger.batch(
      sectionRef.value!.querySelectorAll('.faq-item'),
      {
        onEnter: (elements: Element[]) => {
          gsap.from(elements, {
            opacity: 0, y: 30, duration: 0.55, stagger: 0.08, ease: 'power2.out',
          })
        },
        once: true,
        start: 'top 85%',
      }
    )
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.9 — `CtaBannerSection.vue`

**Animation:** Banner scales up, then heading and buttons fade up in sequence (timeline).

**Classes needed:**
- `sectionRef` → root `<section>`
- Inner banner wrapper: class `cta-banner`
- Heading inside: class `cta-heading`
- Buttons wrapper: class `cta-buttons`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    const tl = gsap.timeline({
      scrollTrigger: {
        trigger: sectionRef.value,
        start: 'top 80%',
        once: true,
      },
    })

    tl.from(sectionRef.value!.querySelector('.cta-banner'), {
        opacity: 0, scale: 0.95, duration: 0.8, ease: 'power3.out',
      })
      .from(sectionRef.value!.querySelector('.cta-heading'), {
        opacity: 0, y: 25, duration: 0.6,
      }, '-=0.4')
      .from(sectionRef.value!.querySelector('.cta-buttons'), {
        opacity: 0, y: 20, duration: 0.5,
      }, '-=0.3')
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

#### 3.10 — `IntegrationLogosSection.vue`

**Animation:** Entire logos row fades up as one block.

**Refs needed:**
- `sectionRef` → root `<section>`

**Animation logic to add:**

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    gsap.from(sectionRef.value, {
      opacity: 0,
      y: 40,
      duration: 0.8,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: sectionRef.value,
        start: 'top 85%',
        once: true,
      },
    })
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
```

---

### 4. Global CSS — Reduced Motion Safety Net

Add to `resources/css/app.css` (or the global stylesheet):

```css
/* Accessibility: respect system reduced-motion preference */
@media (prefers-reduced-motion: reduce) {
  /* Catch any GSAP inline styles that may have been set before the JS check */
  [data-gsap],
  .gsap-animated {
    transition: none !important;
    transform: none !important;
    opacity: 1 !important;
  }
}
```

---

### 5. Vite Config — No Changes Needed

GSAP is tree-shakeable and works out of the box with Vite. No additional config required.

---

## DELIVERABLES CHECKLIST

For each of the 10 section components, confirm:

- [ ] `gsap` and `ScrollTrigger` imported dynamically inside `onMounted()` only
- [ ] `gsap.context()` used and result stored in a variable
- [ ] `onUnmounted()` calls `.revert()` on the context
- [ ] `prefers-reduced-motion` check at the top of `onMounted()`
- [ ] `once: true` on all `ScrollTrigger` instances
- [ ] `ScrollTrigger.batch()` used for all card/list elements (never individual triggers in a loop)
- [ ] Hero section uses timeline with NO ScrollTrigger
- [ ] StatsBar counter uses `data-target` attribute from template
- [ ] Composable `useGsapScrollAnimation.ts` created at correct path
- [ ] Global CSS reduced-motion block added

---

## WHAT NOT TO DO

```typescript
// ❌ WRONG — top-level import crashes SSR
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

// ❌ WRONG — individual trigger in loop (creates hundreds of ScrollTrigger instances)
cards.forEach(card => {
  gsap.from(card, { scrollTrigger: { trigger: card, ... } })
})

// ❌ WRONG — no cleanup
onMounted(async () => {
  const { gsap } = await import('gsap')
  gsap.from('.card', { opacity: 0 }) // no context, no cleanup
})

// ❌ WRONG — missing once:true (animation replays on re-scroll)
ScrollTrigger.batch('.card', {
  onEnter: (els) => gsap.from(els, { opacity: 0 }),
  // once: true MISSING
})

// ✅ CORRECT pattern
onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)
  gsapCtx = gsap.context(() => {
    ScrollTrigger.batch('.card', {
      onEnter: (elements) => gsap.from(elements, { opacity: 0, y: 30, stagger: 0.1 }),
      once: true,
      start: 'top 85%',
    })
  }, sectionRef.value!)
})
onUnmounted(() => gsapCtx?.revert())
```

---

*End of prompt. Implement all 10 sections + composable + CSS.*
