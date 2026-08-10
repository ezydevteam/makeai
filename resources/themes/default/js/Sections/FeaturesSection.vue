<script setup lang="ts">
import { loadGsapNearViewport } from '../composables/useGsapScrollAnimation'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
import { mediaUrl } from '@/lib/media'

const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, cardBgClass, cardWrapperClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }

const props = defineProps<{ section: HomepageSection; sectionTitle?: (section: HomepageSection, fallback: string) => string; sectionSubtitle?: (section: HomepageSection) => string }>()
const { t } = useTranslate()

const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []

const resolveMediaUrl = (path?: string | null): string => mediaUrl(path)

// Configs saved with an image upload store this flag as a "1"/"0" string, so accept both.
const cardStyleEnabled = computed((): boolean => {
    const v = props.section.config.enable_card_style
    if (v === undefined || v === null || v === '') return true
    if (typeof v === 'boolean') return v
    return !['0', 'false', 'no', 'off'].includes(String(v).trim().toLowerCase())
})

const heroColorClass = (color: string, tone: 'heading' | 'subheading' = 'heading'): string => {
    const h: Record<string, string> = { dark: 'text-gray-900 dark:text-white', light: 'text-white', primary: 'text-primary-600 dark:text-primary-400', white: 'text-white' }
    const s: Record<string, string> = { dark: 'text-gray-600 dark:text-gray-400', light: 'text-white/70', primary: 'text-primary-500/80 dark:text-primary-300/80', white: 'text-white/70' }
    return tone === 'heading' ? (h[color] ?? h.dark) : (s[color] ?? s.light)
}

const featureGridClass = (layout: string): string => ({ '2-column': 'lg:grid-cols-2', '3-column': 'lg:grid-cols-3', '4-column': 'lg:grid-cols-4' }[layout] ?? 'lg:grid-cols-3')
const featureCardClass = (style: string): string => {
    return {
        simple: 'relative h-full overflow-hidden rounded-3xl border border-gray-100/50 bg-white/60 shadow-[0_8px_30px_rgb(0,0,0,0.02)] backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-[0_20px_50px_rgba(31,117,254,0.06)] dark:border-surface-800/40 dark:bg-surface-900/40 dark:hover:bg-surface-900/80 dark:hover:border-primary-500/30',
        bordered: 'relative h-full overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-[0_12px_40px_rgba(0,0,0,0.035)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-400 hover:shadow-[0_30px_60px_rgba(31,117,254,0.08)] dark:border-surface-800/60 dark:bg-surface-900/40 dark:hover:bg-surface-900/80 dark:hover:border-primary-500/30',
        image_focus: 'relative h-full overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-[0_12px_40px_rgba(0,0,0,0.035)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-400 hover:shadow-[0_30px_60px_rgba(31,117,254,0.08)] dark:border-surface-800/60 dark:bg-surface-900/40 dark:hover:bg-surface-900/80 dark:hover:border-primary-500/30',
    }[style] ?? 'relative h-full overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-[0_12px_40px_rgba(0,0,0,0.035)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-400 hover:shadow-[0_30px_60px_rgba(31,117,254,0.08)] dark:border-surface-800/60 dark:bg-surface-900/40 dark:hover:bg-surface-900/80 dark:hover:border-primary-500/30'
}
const featureCardBodyClass = (style: string): string => ({ simple: 'relative z-10 px-7 pb-7 pt-2', bordered: 'relative z-10 p-8', image_focus: 'relative z-10 p-8' }[style] ?? 'relative z-10 p-8')
const featureCardMediaClass = (style: string): string => ({
    simple: 'mx-0 mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-none dark:bg-primary-500/10 dark:text-primary-300',
    bordered: 'mx-0 mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white text-primary-600 shadow-sm dark:border-primary-950 dark:from-primary-950 dark:to-surface-900 dark:text-primary-400',
    image_focus: 'w-full h-56 rounded-none mb-0 flex items-center justify-center',
}[style] ?? 'mx-0 mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white text-primary-600 shadow-sm dark:border-primary-950 dark:from-primary-950 dark:to-surface-900 dark:text-primary-400')
const featureCardImageClass = (style: string): string => ({ simple: 'w-full h-32 object-cover mb-8', bordered: 'w-full h-32 object-cover mb-8', image_focus: 'w-full h-56 object-cover' }[style] ?? 'w-full h-32 object-cover mb-8')
const heroButtonClass = (style: string): string => {
    const isDarkTheme = isDark.value
    const map: Record<string, string> = {
        primary_filled: 'bg-gradient-to-r from-primary-500 to-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:from-primary-600 hover:to-primary-500',
        outline: isDarkTheme
            ? 'border-2 border-white/40 bg-transparent !text-white hover:bg-white/10'
            : 'border-2 border-gray-300 bg-transparent text-gray-900 hover:bg-gray-50 dark:border-white/30 dark:bg-transparent dark:!text-white dark:hover:bg-white/10',
        dark: isDarkTheme
            ? 'bg-white text-gray-950 hover:bg-gray-100'
            : 'bg-gradient-to-r from-gray-800 to-gray-900 text-white shadow-2xl shadow-gray-900/20 hover:from-gray-900 hover:to-gray-800',
        gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 !text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
        white: isDarkTheme
            ? 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25'
            : 'bg-white text-gray-900 shadow-xl hover:bg-gray-100',
    }
    return map[style] ?? map.primary_filled
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return cardWrapperClass(style)
})


const isExternalUrl = (url: string): boolean => url.startsWith('http://') || url.startsWith('https://')

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  const gsapLoaded = await loadGsapNearViewport(sectionRef)
  if (! gsapLoaded) return
  const { gsap, ScrollTrigger } = gsapLoaded

  gsapCtx = gsap.context(() => {
    // Section heading
    const heading = sectionRef.value!.querySelector('.features-heading')
    if (heading) {
      gsap.from(heading, {
        opacity: 0,
        y: 30,
        duration: 0.7,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        },
      })
    }

    // Feature cards — Staggered Entrance
    const cards = sectionRef.value!.querySelectorAll('.feature-card')
    if (cards.length) {
      gsap.from(cards, {
        opacity: 0,
        y: 50,
        duration: 0.6,
        stagger: 0.1,
        ease: 'power2.out',
        immediateRender: false,
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        }
      })
    }
  }, sectionRef.value!)

  setTimeout(() => {
    ScrollTrigger.refresh()
  }, 100)
})

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section
        :id="sectionAnchorId(section.config.section_anchor)"
        ref="sectionRef"
        :style="sectionPaddingStyle(section.config.vertical_padding ?? section.config.feature_vertical_padding, '96')"
        :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]"
        class="transition-colors duration-300"
    >
        <div class="mx-auto max-w-7xl px-6">
            <div :class="effectiveCardWrapperClass">
                <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="mb-20">
                    <!-- Top Position Icon -->
                    <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                        sectionIconClass(asString(section.config.icon_style, 'primary')),
                        'mb-5 h-14 w-14 text-2xl'
                    ]">
                        <i :class="asString(section.config.icon)"></i>
                    </div>
                    <div class="w-full">
                        <span v-if="asString(section.config.badge_text)" :class="badgeClass(asString(section.config.section_bg, 'default'), asString(section.config.title_color, 'dark'))">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ asString(section.config.badge_text) }}
                        </span>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 w-9 text-lg shrink-0'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 :class="['font-black features-heading', titleSizeClass(asString(section.config.title_size, 'md')), titleColorClass(asString(section.config.title_color, 'dark'))]">{{ section.config.title || t('Supercharge your workflow') }}</h2>
                        </div>
                        <p v-if="asString(section.config.subtitle)" :class="['font-medium max-w-2xl mt-4', subtitleColorClass(asString(section.config.title_color, 'dark'), asString(section.config.section_bg, 'default')), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">{{ asString(section.config.subtitle) }}</p>
                    </div>
                </div>
                <div :class="[!cardStyleEnabled ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-12 gap-x-10' : `${featureGridClass(asString(section.config.layout, '3-column'))} grid grid-cols-1 gap-8 md:grid-cols-2`]">
                    <component
                        v-for="item in asItems(section.config.items)"
                        :is="String(item.link_url) ? (isExternalUrl(String(item.link_url)) ? 'a' : Link) : 'div'"
                        :key="`${item.title}_${item.icon}`"
                        :href="String(item.link_url || '') || undefined"
                        :target="String(item.link_url) && isExternalUrl(String(item.link_url)) ? '_blank' : undefined"
                        :rel="String(item.link_url) && isExternalUrl(String(item.link_url)) ? 'noopener noreferrer' : undefined"
                        :class="[
                            !cardStyleEnabled
                                ? 'group relative block h-full text-left feature-card' 
                                : `${featureCardClass(asString(section.config.card_style, 'bordered'))} group block h-full text-left feature-card`
                        ]"
                    >
                        <!-- List style layout: Icon inline with title and description -->
                        <template v-if="!cardStyleEnabled">
                            <div class="flex gap-4 items-start">
                                <div v-if="!item.image_url" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50/80 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400 transition-all duration-300 group-hover:bg-primary-100 group-hover:scale-105 dark:group-hover:bg-primary-500/20">
                                    <i :class="String(item.icon || 'ti ti-sparkles')" class="block shrink-0 text-2xl leading-none"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="mb-2 text-[1.15rem] font-black tracking-tight !text-gray-900 dark:!text-white transition-colors duration-300 group-hover:!text-primary-600 dark:group-hover:!text-primary-400">{{ item.title }}</h3>
                                    <p class="text-sm font-medium leading-7 text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                                    <div v-if="String(item.link_url) && asString(section.config.learn_more_text)" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 transition group-hover:gap-3 dark:text-primary-400">
                                        {{ asString(section.config.learn_more_text) }}
                                        <i class="ti ti-arrow-right text-base leading-none"></i>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Standard card style layout -->
                        <template v-else>
                            <div class="absolute -right-14 -top-14 h-32 w-32 rounded-full bg-primary-500/10 blur-3xl transition-transform duration-300 group-hover:scale-125"></div>
                            <img v-if="item.image_url" :src="resolveMediaUrl(String(item.image_url))" alt="" loading="lazy" :class="featureCardImageClass(asString(section.config.card_style, 'bordered'))">
                            <div :class="featureCardBodyClass(asString(section.config.card_style, 'bordered'))">
                                <div v-if="!item.image_url" :class="[featureCardMediaClass(asString(section.config.card_style, 'bordered')), 'mx-0 mb-6 transition-transform duration-300 group-hover:scale-105']">
                                    <i :class="String(item.icon || 'ti ti-sparkles')" class="block shrink-0 text-2xl leading-none"></i>
                                </div>
                                <h3 class="mb-3 text-[1.15rem] font-black tracking-tight !text-gray-900 dark:!text-white transition-colors duration-300 group-hover:!text-primary-600 dark:group-hover:!text-primary-400">{{ item.title }}</h3>
                                <p class="text-sm font-medium leading-7 text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                                <div v-if="String(item.link_url) && asString(section.config.learn_more_text)" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 transition group-hover:gap-3 dark:text-primary-400">
                                    {{ asString(section.config.learn_more_text) }}
                                    <i class="ti ti-arrow-right text-base leading-none"></i>
                                </div>
                            </div>
                        </template>
                    </component>
                </div>
                <div v-if="asString(section.config.button_text) && asString(section.config.button_link)" class="mt-12 text-center">
                    <Link :href="asString(section.config.button_link)" :class="heroButtonClass(asString(section.config.button_style, 'primary_filled'))" class="inline-flex items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors">
                        <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'block shrink-0 text-lg leading-none']"></i>
                        {{ asString(section.config.button_text) }}
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
