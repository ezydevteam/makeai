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
