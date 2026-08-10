import { onUnmounted, type Ref } from 'vue'

/**
 * Load GSAP + ScrollTrigger, but not until the section is close to the viewport.
 *
 * Every homepage section used to call `await import('gsap')` straight from its own
 * onMounted. Twelve of them mount together, so the first paint window carried
 * twelve concurrent module loads and — worse — twelve ScrollTrigger.create() calls,
 * each of which measures its trigger element. That is a forced synchronous layout
 * per section, on content the visitor has not scrolled anywhere near.
 *
 * Deferring to an IntersectionObserver means a section below the fold costs nothing
 * until it is nearly on screen. The 300px rootMargin starts the work early enough
 * that the entrance animation is still armed before the section is actually visible.
 *
 * Returns null when the animation should be skipped entirely — reduced-motion, or a
 * target that never mounted. Callers must treat null as "do not animate".
 */
export async function loadGsapNearViewport(
    target: Ref<HTMLElement | null>
): Promise<{ gsap: any; ScrollTrigger: any } | null> {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return null

    const el = target.value
    if (! el) return null

    // No IntersectionObserver (or an element already on screen): fall straight
    // through rather than wait for a callback that may never come.
    if (typeof IntersectionObserver !== 'undefined') {
        await new Promise<void>((resolve) => {
            const observer = new IntersectionObserver((entries) => {
                if (! entries.some((entry) => entry.isIntersecting)) return
                observer.disconnect()
                resolve()
            }, { rootMargin: '300px' })

            observer.observe(el)
        })
    }

    const [{ gsap }, { ScrollTrigger }] = await Promise.all([
        import('gsap'),
        import('gsap/ScrollTrigger'),
    ])

    gsap.registerPlugin(ScrollTrigger)

    return { gsap, ScrollTrigger }
}

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
