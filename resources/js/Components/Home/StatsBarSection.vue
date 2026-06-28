<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '@/Composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asBoolean = (v: SectionConfigValue | undefined, fallback = false): boolean => typeof v === 'boolean' ? v : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []

const resolveMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path
    return `/storage/${path}`
}

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

const marqueeMaskClass = computed(() => {
    if (isDark.value) {
        return 'from-surface-950 to-transparent'
    }
    const bg = asString(props.section.config.section_bg, 'default')
    const map: Record<string, string> = {
        default: 'from-white to-transparent',
        light: 'from-gray-50 to-transparent',
        primary_light: 'from-primary-50/50 to-transparent',
        success_light: 'from-emerald-50/50 to-transparent',
        danger_light: 'from-rose-50/50 to-transparent',
        warning_light: 'from-amber-50/50 to-transparent',
        gradient1: 'from-emerald-950 to-transparent',
        gradient2: 'from-blue-950 to-transparent',
        gradient3: 'from-purple-950 to-transparent'
    }
    return map[bg] ?? 'from-white to-transparent'
})
</script>

<template>
    <section ref="sectionRef" :class="[isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '64')" class="transition-colors duration-300">
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
                    <p :class="[titleColorClass(asString(section.config.title_color, 'dark'))]" class="text-4xl font-black"><span class="stat-counter" :data-target-raw="stat.number">{{ stat.number }}</span></p>
                    <p :class="[subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default'))]" class="mt-2 text-xs font-black uppercase tracking-widest">{{ stat.label }}</p>
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
                        <div v-for="(brand, idx) in asItems(section.config.brands)" :key="`brand1_${idx}`" class="flex items-center justify-center h-12 px-4">
                            <img v-if="brand.image" :src="resolveMediaUrl(brand.image ? String(brand.image) : null)" :alt="String(brand.name || '')" :title="String(brand.name || '')" class="max-h-8 w-auto object-contain opacity-50 hover:opacity-100 hover:scale-105 transition-all duration-300 dark:brightness-0 dark:invert" />
                            <span v-else :title="String(brand.name || '')" class="text-lg font-bold text-gray-400 dark:text-gray-500">{{ brand.name }}</span>
                        </div>
                    </div>
                    <!-- Second track (cloned for seamless looping) -->
                    <div class="marquee-track flex items-center justify-around gap-12 min-w-full shrink-0" aria-hidden="true">
                        <div v-for="(brand, idx) in asItems(section.config.brands)" :key="`brand2_${idx}`" class="flex items-center justify-center h-12 px-4">
                            <img v-if="brand.image" :src="resolveMediaUrl(brand.image ? String(brand.image) : null)" :alt="String(brand.name || '')" :title="String(brand.name || '')" class="max-h-8 w-auto object-contain opacity-50 hover:opacity-100 hover:scale-105 transition-all duration-300 dark:brightness-0 dark:invert" />
                            <span v-else :title="String(brand.name || '')" class="text-lg font-bold text-gray-400 dark:text-gray-500">{{ brand.name }}</span>
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

