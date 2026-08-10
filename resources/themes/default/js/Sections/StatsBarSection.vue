<script setup lang="ts">
import { loadGsapNearViewport } from '../composables/useGsapScrollAnimation'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
import { mediaUrl } from '@/lib/media'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
// Configs saved with an image upload store these flags as "1"/"0" strings, so accept both.
const asBoolean = (v: SectionConfigValue | undefined, fallback = false): boolean => {
    if (v === undefined || v === null || v === '') return fallback
    if (typeof v === 'boolean') return v
    return !['0', 'false', 'no', 'off'].includes(String(v).trim().toLowerCase())
}
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []

const resolveMediaUrl = (path?: string | null): string => mediaUrl(path)

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

const parseStatNumber = (numStr: string) => {
    const match = numStr.match(/^([\d.]+)(.*)$/)
    if (!match) return { target: 0, suffix: '', decimals: 0 }
    const target = parseFloat(match[1])
    const suffix = match[2]
    const dotIndex = match[1].indexOf('.')
    const decimals = dotIndex !== -1 ? (match[1].length - dotIndex - 1) : 0
    return { target, suffix, decimals }
}

onMounted(async () => {
  const gsapLoaded = await loadGsapNearViewport(sectionRef)
  if (! gsapLoaded) return
  const { gsap, ScrollTrigger } = gsapLoaded

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
      const rawTarget = el.getAttribute('data-target-raw') || '0'
      const parsed = parseStatNumber(rawTarget)
      const obj = { val: 0 }

      gsap.to(obj, {
        val: parsed.target,
        duration: 2,
        ease: 'power2.out',
        delay: i * 0.15,
        onUpdate: () => {
          el.textContent = parsed.target === 0 ? rawTarget : (obj.val.toFixed(parsed.decimals) + parsed.suffix)
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

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})

// The stats used to inherit the section's Title Color. They can now be styled independently,
// but fall back to it when unset so existing sections look exactly as they did.
const statsStyle = computed(() => asString(
    props.section.config.stats_style,
    asString(props.section.config.title_color, 'dark'),
))

// The masks fade the marquee out at both edges, so each one has to start in the section's
// OWN background colour — anything else reads as two coloured slabs pinned to the sides.
//
// `default` paints bg-[var(--color-bg)], which the operator can set to anything, so the
// mask follows that variable instead of assuming white. It was hardcoded white, which is
// why a custom page background showed two white blocks.
//
// The keys also have to match SECTION_BG_DEFAULTS exactly. They did not — the map spelled
// them primary_light / success_light / danger_light while the real values are
// primary-light / green-light / red-light — so four of the tinted backgrounds missed the
// map entirely and fell through to the white default as well.
const marqueeMaskClass = computed(() => {
    if (isDark.value) {
        return 'from-surface-950 to-transparent'
    }
    const bg = asString(props.section.config.section_bg, 'default')
    const map: Record<string, string> = {
        default: 'from-[var(--color-bg)] to-transparent',
        light: 'from-gray-50 to-transparent',
        'primary-light': 'from-primary-50 to-transparent',
        'green-light': 'from-emerald-50 to-transparent',
        'warning-light': 'from-amber-50 to-transparent',
        'red-light': 'from-red-50 to-transparent',
        gradient1: 'from-[var(--color-primary)] to-transparent',
        gradient2: 'from-purple-600 to-transparent',
        gradient3: 'from-blue-600 to-transparent',
        gradient4: 'from-blue-600 to-transparent',
    }

    // Anything unmapped — a background added later, or one set outside this list — gets a
    // neutral slate rather than a white slab that fights every custom colour.
    return map[bg] ?? 'from-slate-100 to-transparent'
})
</script>

<template>
    <section
        :id="sectionAnchorId(section.config.section_anchor)" ref="sectionRef" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '64')" class="transition-colors duration-300">
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-12">
                    <!-- Top Position Icon -->
                    <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="asString(section.config.icon)"></i>
                    </div>
                    <div class="w-full">
                        <div v-if="asString(section.config.badge_text)" :class="[badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))]">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ asString(section.config.badge_text) }}
                        </div>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ asString(section.config.heading ?? section.config.title, t('Social Proof')) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subheading ?? section.config.subtitle)" :class="['font-medium mt-4 max-w-2xl', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
                    </div>
                </div>

            <!-- Stats Block -->
            <div v-if="asBoolean(section.config.show_stats, true) && asItems(section.config.stats).length > 0"
                 :class="[asBoolean(section.config.show_stats_separator, true) ? 'border-t border-gray-100 pt-12 dark:border-surface-800' : 'pt-4']"
                 class="grid grid-cols-2 gap-8 text-center md:grid-cols-4">
                <div v-for="stat in asItems(section.config.stats)" :key="`${stat.number}_${stat.label}`">
                    <p :class="[titleColorClass(statsStyle)]" class="text-4xl font-black"><span class="stat-counter" :data-target-raw="stat.number">{{ stat.number }}</span></p>
                    <p :class="[subtitleColorClass(statsStyle, asString(section.config.section_bg, 'default'))]" class="mt-2 text-xs font-black uppercase tracking-widest">{{ stat.label }}</p>
                </div>
            </div>

            <!-- Brands Marquee Block -->
            <div v-if="asBoolean(section.config.show_brands, false) && asItems(section.config.brands).length > 0"
                 :class="[asBoolean(section.config.show_stats, true) && asItems(section.config.stats).length > 0 ? 'mt-16 border-t border-gray-100 pt-12 dark:border-surface-800' : '']"
                 class="w-full overflow-hidden relative py-4">
                <!-- Smooth gradient edge masks for marquee -->
                <div class="absolute left-0 top-0 bottom-0 w-24 bg-gradient-to-r pointer-events-none z-10 hidden md:block" :class="[marqueeMaskClass]"></div>
                <div class="absolute right-0 top-0 bottom-0 w-24 bg-gradient-to-l pointer-events-none z-10 hidden md:block" :class="[marqueeMaskClass]"></div>

                <!-- Scrolling container -->
                <div class="marquee-wrapper flex gap-12 select-none overflow-hidden w-full">
                    <!-- First track -->
                    <div class="marquee-track flex items-center justify-around gap-12 min-w-full shrink-0">
                        <div v-for="(brand, idx) in asItems(section.config.brands)" :key="`brand1_${idx}`" class="flex items-center justify-center h-12 rounded-xl px-4 transition-colors dark:bg-white/90 dark:px-5 dark:py-2 dark:shadow-sm">
                            <img v-if="brand.image" :src="resolveMediaUrl(brand.image ? String(brand.image) : null)" :alt="String(brand.name || '')" :title="String(brand.name || '')" class="max-h-8 w-auto object-contain hover:scale-105 transition-transform duration-300" />
                            <span v-else :title="String(brand.name || '')" class="text-lg font-bold text-gray-600 dark:text-gray-800">{{ brand.name }}</span>
                        </div>
                    </div>
                    <!-- Second track (cloned for seamless looping) -->
                    <div class="marquee-track flex items-center justify-around gap-12 min-w-full shrink-0" aria-hidden="true">
                        <div v-for="(brand, idx) in asItems(section.config.brands)" :key="`brand2_${idx}`" class="flex items-center justify-center h-12 rounded-xl px-4 transition-colors dark:bg-white/90 dark:px-5 dark:py-2 dark:shadow-sm">
                            <img v-if="brand.image" :src="resolveMediaUrl(brand.image ? String(brand.image) : null)" :alt="String(brand.name || '')" :title="String(brand.name || '')" class="max-h-8 w-auto object-contain hover:scale-105 transition-transform duration-300" />
                            <span v-else :title="String(brand.name || '')" class="text-lg font-bold text-gray-600 dark:text-gray-800">{{ brand.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</template>

<style scoped>
.marquee-wrapper {
    display: flex;
    overflow: hidden;
    user-select: none;
}

.marquee-track {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-around;
    gap: 3rem;
    min-width: 100%;
    animation: marquee 25s linear infinite;
    will-change: transform;
}

.marquee-wrapper:hover .marquee-track {
    animation-play-state: paused;
}

@keyframes marquee {
    0% {
        transform: translateX(0%);
    }
    100% {
        transform: translateX(calc(-100% - 3rem));
    }
}
</style>

