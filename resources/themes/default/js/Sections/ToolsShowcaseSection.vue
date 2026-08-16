<script setup lang="ts">
import { loadGsapNearViewport } from '../composables/useGsapScrollAnimation'
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import FavoriteButton from '@themes/default/js/Components/FavoriteButton.vue'
import { useTheme } from '@/Composables/useTheme'

const { sectionBgClass, sectionBgIsDark, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass, sectionPaddingStyle, cardBgClass, cardWrapperClass: sectionCardWrapperClass, sectionIconClass, sectionHeaderClass, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]

interface ToolItem {
    id: number
    slug: string
    name: string
    description: string
    icon: string | null
    color: string | null
    category: string | null
    usage_count: number
    avg_rating: number | null
    is_featured: boolean
    is_favorited?: boolean
    favorites_count?: number
    created_at?: string
}

interface HomepageSection {
    id: string
    type: string
    enabled: boolean
    core: boolean
    config: Record<string, SectionConfigValue>
}

const props = defineProps<{ section: HomepageSection; allTools: ToolItem[] }>()
const { t } = useTranslate()

const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const heroButtonClass = (style: string): string => {
    const isDarkTheme = isDark.value
    const map: Record<string, string> = {
        primary: 'bg-gradient-to-r from-primary-500 to-primary-600 !text-white shadow-lg shadow-primary-600/20 hover:from-primary-600 hover:to-primary-500 transition-all',
        primary_filled: 'bg-gradient-to-r from-primary-500 to-primary-600 !text-white shadow-lg shadow-primary-600/20 hover:from-primary-600 hover:to-primary-500 transition-all',
        dark: isDarkTheme
            ? 'bg-white text-gray-950 hover:bg-gray-100 transition-all'
            : 'bg-gradient-to-r from-gray-800 to-gray-900 !text-white hover:from-gray-900 hover:to-gray-800 transition-all',
        purple: 'bg-gradient-to-r from-violet-500 to-violet-600 !text-white shadow-lg shadow-violet-600/20 hover:from-violet-600 hover:to-violet-500 transition-all',
        gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 !text-white shadow-lg hover:opacity-95 transition-all',
        red: 'bg-gradient-to-r from-red-500 to-red-600 !text-white hover:from-red-600 hover:to-red-500 transition-all',
        danger: 'bg-gradient-to-r from-red-500 to-red-600 !text-white hover:from-red-600 hover:to-red-500 transition-all',
        green: 'bg-gradient-to-r from-success-500 to-success-600 !text-white hover:from-success-600 hover:to-success-500 transition-all',
        success: 'bg-gradient-to-r from-emerald-500 to-emerald-600 !text-white hover:from-emerald-600 hover:to-emerald-500 transition-all',
        warning: 'bg-gradient-to-r from-amber-500 to-amber-600 !text-white hover:from-amber-600 hover:to-amber-500 transition-all',
        gradient_sunset: 'bg-gradient-to-r from-orange-500 via-pink-500 to-purple-600 !text-white shadow-lg hover:opacity-95 transition-all',
        gradient_ocean: 'bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-600 !text-white shadow-lg hover:opacity-95 transition-all',
        gradient_royal: 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 !text-white shadow-lg hover:opacity-95 transition-all',
        outline: isDarkTheme
            ? 'border-2 border-white/40 bg-transparent !text-white hover:bg-white/10 transition-all'
            : 'border-2 border-gray-300 bg-transparent text-gray-900 hover:bg-gray-50 transition-all',
        white: isDarkTheme
            ? 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25 transition-all'
            : 'bg-white text-gray-900 shadow-md border border-gray-200 hover:bg-gray-50 transition-all',
        light: isDarkTheme
            ? 'bg-white/10 !text-white shadow-xl hover:bg-white/20 transition-all'
            : 'bg-gray-100 text-gray-900 border border-gray-150 hover:bg-gray-200 transition-all',
        ghost: isDarkTheme
            ? 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25 transition-all'
            : 'bg-transparent text-gray-700 hover:bg-gray-100 transition-all',
    }
    return map[style] ?? map.primary
}

const heroButtonShapeClass = (shape: string): string => ({
    sharp: 'rounded-none',
    rounded: 'rounded-lg',
    rounded_xl: 'rounded-2xl',
    pill: 'rounded-full',
}[shape] ?? 'rounded-2xl')

const toolsShowcaseGridClass = (layout: string): string => ({
    '2-column': 'lg:grid-cols-2',
    '3-column': 'lg:grid-cols-3',
    '4-column': 'lg:grid-cols-4'
}[layout] ?? 'lg:grid-cols-3')

// Config Toggles
// Configs saved with an image upload store these flags as "1"/"0" strings, so accept both.
const asBoolean = (v: unknown, fallback: boolean): boolean => {
    if (v === undefined || v === null || v === '') return fallback
    if (typeof v === 'boolean') return v
    return !['0', 'false', 'no', 'off'].includes(String(v).trim().toLowerCase())
}

const showRating = computed(() => asBoolean(props.section.config.show_rating, true))
const showFavorite = computed(() => asBoolean(props.section.config.show_favorite, true))
const showCategory = computed(() => asBoolean(props.section.config.show_category, true))
const showCategoryFilter = computed(() => asBoolean(props.section.config.show_category_filter, false))
const showSearch = computed(() => asBoolean(props.section.config.show_search, false))

// Only these three have card bodies; anything else (e.g. the legacy 'bordered' default) would
// match no v-if branch and render the tool cards as empty boxes.
const TOOL_CARD_STYLES = ['style-1', 'style-2', 'style-3']
const activeCardStyle = computed(() => {
    const style = asString(props.section.config.card_style, 'style-1')
    return TOOL_CARD_STYLES.includes(style) ? style : 'style-1'
})

// State
const selectedCategory = ref('all')
const searchQuery = ref('')
// Mobile keeps its own tighter cut of 3 inline; this is the desktop one. Shared expand
// state on purpose — someone who opens the full list then narrows the window keeps it open.
const DESKTOP_CATEGORY_LIMIT = 14
const categoriesExpanded = ref(false)

const uniqueCategories = computed(() => {
    if (!props.allTools) return ['all']
    const cats = props.allTools
        .map(t => t.category)
        .filter((c): c is string => typeof c === 'string' && c.length > 0)
    return ['all', ...new Set(cats)]
})

const isGradientCardBg = computed(() => {
    const bg = asString(props.section.config.card_bg_style || props.section.config.background_style, 'white')
    return ['gradient-1', 'gradient-2', 'gradient-3', 'gradient-4'].includes(bg)
})

// The search field and the category pills sit on the SECTION background, not inside a card,
// so their light/dark treatment has to consider section_bg as well. Keying it off the card
// background alone left both unreadable on a dark section: with section_bg 'gradient4' the
// active pill measured 1.09:1 (rose-600 on blue-600 → purple-600) and the search placeholder
// 1.32:1, because the light-surface classes were painted straight onto a dark gradient.
const onDarkSectionSurface = computed(() => isGradientCardBg.value
    || sectionBgIsDark(asString(props.section.config.section_bg, 'default')))

// Only a gradient wrapper forces white — the section heading sits on the section
// background, which card_style says nothing about. style-2 used to force white here
// too, which erased the heading on any light section.
const titleClass = computed(() => {
    const size = titleSizeClass(asString(props.section.config.title_size, 'md'))
    if (isGradientCardBg.value) {
        return `mb-4 font-black ${size} !text-white`
    }
    const colorVal = asString(props.section.config.title_color, 'dark')
    const color = titleColorClass(colorVal)
    return `mb-4 font-black ${size} ${color}`
})

const subtitleClass = computed(() => {
    let color = ''
    if (isGradientCardBg.value) {
        color = '!text-white/80'
    } else {
        const colorVal = asString(props.section.config.title_color, 'dark')
        color = subtitleColorClass(colorVal, asString(props.section.config.section_bg, 'default'))
    }
    const align = titleAlignClass(asString(props.section.config.title_align, 'center'))
    const alignClass = align === 'text-center' ? 'mx-auto' : ''
    return `font-medium max-w-2xl ${color} ${alignClass}`
})

const searchInputClass = computed(() => {
    if (onDarkSectionSurface.value) {
        return 'w-full !rounded-full border !border-white/30 !bg-white/10 backdrop-blur-md pl-10 pr-10 py-3 text-sm !text-white !placeholder-white/75 focus:!bg-white/20 focus:!border-white/40 focus:outline-none focus:ring-0 dark:!border-white/30'
    }
    if (activeCardStyle.value === 'style-2') {
        // Full-strength border: at gray-200/50 over a white section the field edge measured
        // 1.11:1 against its own fill, so the input read as floating text with no box.
        return 'w-full !rounded-full border !border-gray-300 !bg-white/30 backdrop-blur-md pl-10 pr-10 py-3 text-sm !text-gray-950 !placeholder-gray-500 focus:!bg-white/60 focus:!border-primary-500 focus:outline-none dark:!border-white/30 dark:!bg-white/5 dark:!text-white dark:!placeholder-white/60 dark:focus:!bg-white/10 shadow-lg shadow-gray-950/5'
    }
    return 'w-full !rounded-full border !border-gray-200/60 !bg-white/60 pl-10 pr-10 py-3 text-sm !text-gray-900 !placeholder-gray-400 focus:!border-primary-500 focus:outline-none dark:!border-surface-800/80 dark:!bg-surface-900/60 dark:!text-white dark:!placeholder-white/40 focus:!border-primary-800/80'
})

const searchIconClass = computed(() => {
    if (onDarkSectionSurface.value) {
        return '!text-white/60'
    }
    if (activeCardStyle.value === 'style-2') {
        return '!text-gray-400 dark:!text-white/60'
    }
    return '!text-gray-400 dark:!text-gray-500'
})

const searchClearBtnClass = computed(() => {
    if (onDarkSectionSurface.value) {
        return '!text-white/60 hover:!text-white'
    }
    if (activeCardStyle.value === 'style-2') {
        return '!text-gray-400 hover:!text-gray-600 dark:!text-white/60 dark:hover:!text-white'
    }
    return '!text-gray-400 hover:!text-gray-600 dark:hover:!text-white'
})

const categoryBtnClass = (cat: string) => {
    const isActive = selectedCategory.value === cat
    const base = 'px-5 py-2 text-xs font-semibold transition-all duration-200 rounded-full border'
    if (onDarkSectionSurface.value) {
        return isActive
            ? `${base} !bg-white !border-white !text-gray-900 shadow-lg`
            : `${base} !bg-white/10 !border-white/20 backdrop-blur-md !text-white/90 hover:!bg-white/20 hover:!text-white`
    }
    if (activeCardStyle.value === 'style-2') {
        // primary-600 rather than -500: the pill is filled with the site's own accent and
        // carries white text, so a light primary (e.g. #d946ef) fell under 3.5:1 at -500.
        return isActive
            ? `${base} !bg-primary-600 !border-primary-600 !text-white shadow-lg`
            : `${base} !bg-white/40 !border-gray-200/50 backdrop-blur-md !text-gray-700 hover:!bg-white/60 hover:!text-gray-900 dark:!bg-white/5 dark:!border-white/10 dark:!text-white/80 dark:hover:!bg-white/10`
    }
    return isActive
        ? `${base} !bg-primary-500/10 !border-primary-500/10 !text-primary-600 dark:!bg-primary-500/20 dark:!border-primary-500/30 dark:!text-primary-400 shadow-md shadow-primary-600/10`
        : `${base} !bg-white !border-gray-200 !text-gray-500 hover:!bg-gray-50 hover:!text-gray-700 dark:!bg-surface-900 dark:!border-surface-800 dark:!text-gray-400 dark:hover:!bg-surface-800/80 dark:hover:!text-gray-300`
}

const textTitleClass = computed(() => {
    if (isGradientCardBg.value) {
        return '!text-white group-hover:!text-white/90'
    }
    // style-2 cards are bg-white/40 in light mode and surface-900 in dark, so the card
    // text has to follow the theme like every other style-2 branch in this file.
    if (activeCardStyle.value === 'style-2') {
        return '!text-gray-900 dark:!text-white group-hover:!text-primary-600 dark:group-hover:!text-white/90'
    }
    return 'text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400'
})

const textDescClass = computed(() => {
    if (isGradientCardBg.value) {
        return '!text-white/70'
    }
    if (activeCardStyle.value === 'style-2') {
        return '!text-gray-500 dark:!text-white/70'
    }
    return 'text-gray-500 dark:text-gray-400'
})

const tryToolColorClass = computed(() => {
    if (isGradientCardBg.value) {
        return '!text-white group-hover:!text-white/90'
    }
    return 'text-primary-700 dark:text-primary-400'
})

const categoryTagClass = computed(() => {
    if (isGradientCardBg.value) {
        return 'bg-white/10 border border-white/10 text-white'
    }
    if (activeCardStyle.value === 'style-2') {
        return 'bg-primary-50/80 text-primary-600 border border-primary-100/30 dark:bg-white/10 dark:border-white/10 dark:text-white'
    }
    return 'bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400'
})

const ratingBadgeClass = computed(() => {
    const base = 'inline-flex items-center gap-1.5 rounded-xl border p-2 shadow-sm text-xs font-bold transition-all h-[34px]'
    if (isGradientCardBg.value) {
        return `${base} border-white/10 bg-white/10 text-white backdrop-blur-md`
    }
    if (activeCardStyle.value === 'style-2') {
        return `${base} border-gray-200/50 bg-white/40 text-gray-700 backdrop-blur-md dark:border-white/10 dark:bg-white/5 dark:text-gray-300`
    }
    return `${base} border-gray-100 bg-gray-50 text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300`
})

const emptyStarClass = computed(() => {
    if (isGradientCardBg.value) {
        return '!text-white/40'
    }
    if (activeCardStyle.value === 'style-2') {
        return '!text-gray-400 dark:!text-white/30'
    }
    return '!text-gray-400 dark:!text-gray-500'
})

const style2CategoryClass = computed(() => {
    if (isGradientCardBg.value) {
        return 'text-xs font-semibold tracking-widest text-white/70'
    }
    if (activeCardStyle.value === 'style-2') {
        return 'text-xs font-semibold tracking-widest text-gray-500 dark:text-white/70'
    }
    return 'text-xs font-semibold tracking-widest text-gray-400 dark:text-gray-500'
})

const style2CategoryBorderClass = computed(() => {
    if (isGradientCardBg.value) {
        return 'mt-auto border-t border-white/10 pt-4 flex items-center justify-center'
    }
    if (activeCardStyle.value === 'style-2') {
        return 'mt-auto border-t border-gray-200/50 dark:border-white/10 pt-4 flex items-center justify-center'
    }
    return 'mt-auto border-t border-gray-100/50 dark:border-surface-800 pt-4 flex items-center justify-center'
})

const cardWrapperClass = (style: string): string => {
    const baseClasses = 'group relative overflow-hidden transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl'

    if (isDark.value) {
        const darkClasses = `${baseClasses} bg-surface-900/40 backdrop-blur-md border border-surface-800/60 hover:bg-surface-900/80 hover:border-primary-500/30`
        const styleMap: Record<string, string> = {
            'style-1': `${darkClasses} rounded-[2rem]`,
            'style-2': `${darkClasses} rounded-[1.75rem] border-t-4`,
            'style-3': `${darkClasses} rounded-2xl`,
        }
        return styleMap[style] ?? styleMap['style-1']
    }

    if (isGradientCardBg.value) {
        const glassClasses = `${baseClasses} bg-white/5 backdrop-blur-xl border border-white/10 hover:border-white/20 hover:bg-white/10 hover:shadow-white/5`
        const styleMap: Record<string, string> = {
            'style-1': `${glassClasses} rounded-[2rem]`,
            'style-2': `${glassClasses} rounded-[1.75rem] border-t-4`,
            'style-3': `${glassClasses} rounded-2xl`,
        }
        return styleMap[style] ?? styleMap['style-1']
    }

    const styleMap: Record<string, string> = {
        'style-1': `${baseClasses} rounded-[2rem] bg-gradient-to-br from-white to-primary-50/15 border border-gray-100 hover:border-primary-200/50`,
        'style-2': `${baseClasses} rounded-[1.75rem] border-l border-t-4 border-r border-b border-gray-200/60 bg-white/40 backdrop-blur-md hover:bg-white/60`,
        'style-3': `${baseClasses} rounded-2xl bg-gradient-to-br from-gray-50/90 via-white to-violet-50/15 border border-gray-100`,
    }
    return styleMap[style] ?? styleMap['style-1']
}

const effectiveCardWrapperClass = computed(() => {
    const style = asString(props.section.config.card_bg_style || props.section.config.background_style, 'default')
    if (isDark.value) {
        if (style === 'default' || style === 'transparent') return ''
        return 'bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-8 md:p-16'
    }
    return sectionCardWrapperClass(style, 'default')
})

const cardBodyClass = (style: string): string => {
    const map: Record<string, string> = {
        'style-1': 'relative z-10 p-7 flex flex-col h-full',
        'style-2': 'relative z-10 p-8 flex flex-col h-full',
        'style-3': 'relative z-10 p-6 flex flex-col h-full'
    }
    return map[style] ?? map['style-1']
}

// category color gradient builder for Style 2
const getCardBgStyle = (tool: ToolItem, styleName: string) => {
    const color = tool.color || '#3b82f6'
    const hexColor = color.startsWith('#') ? color : '#3b82f6'
    if (styleName === 'style-2') {
        return {
            borderTopColor: hexColor,
            backgroundImage: `linear-gradient(135deg, ${hexColor}0d 0%, ${hexColor}02 60%, transparent 100%)`
        }
    }
    return {}
}

const toolsShowcaseItems = (): ToolItem[] => {
    const tools = [...(props.allTools ?? [])]
    const source = asString(props.section.config.source, 'all')

    let filtered = tools
    if (source === 'featured') {
        filtered = tools.filter((t) => t.is_featured)
    } else if (source === 'popular') {
        filtered = tools.sort((a, b) => (b.usage_count ?? 0) - (a.usage_count ?? 0))
    } else if (source === 'recent') {
        filtered = tools.sort((a, b) => {
            const da = a.created_at ? Date.parse(a.created_at) : 0
            const db = b.created_at ? Date.parse(b.created_at) : 0
            return db - da
        })
    }
    return filtered
}

const filteredTools = computed(() => {
    let list = toolsShowcaseItems()
    const max = parseInt(String(props.section.config.max_items ?? 6), 10)

    // Apply category filter
    if (showCategoryFilter.value && selectedCategory.value !== 'all') {
        list = list.filter(t => t.category === selectedCategory.value)
    }

    // Apply search query filter
    if (showSearch.value && searchQuery.value.trim() !== '') {
        const query = searchQuery.value.toLowerCase().trim()
        list = list.filter(t =>
            t.name.toLowerCase().includes(query) ||
            t.description.toLowerCase().includes(query) ||
            (t.category && t.category.toLowerCase().includes(query))
        )
    }

    return list.slice(0, max)
})

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null
let headerAnimated = false
let isMounted = false
let isInitializing = false
let pendingInit = false

const initAnimations = async () => {
  if (!isMounted) return
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  if (isInitializing) {
    pendingInit = true
    return
  }
  isInitializing = true

  if (gsapCtx) {
    gsapCtx.revert()
  }

  const gsapLoaded = await loadGsapNearViewport(sectionRef)
  if (! gsapLoaded) { isInitializing = false; return }
  const { gsap, ScrollTrigger } = gsapLoaded

  gsapCtx = gsap.context(() => {
    // Section Header Entrance
    if (!headerAnimated) {
      const header = sectionRef.value!.querySelector('.mb-12')
      if (header) {
        gsap.from(header, {
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
          onComplete: () => {
            headerAnimated = true
          }
        })
      }
    }

    // Showcase Cards — Staggered Entrance
    const cards = sectionRef.value!.querySelectorAll('.showcase-card')
    if (cards.length) {
      gsap.from(cards, {
        opacity: 0,
        y: 40,
        duration: 0.6,
        stagger: 0.08,
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

  isInitializing = false
  if (pendingInit) {
    pendingInit = false
    initAnimations()
  }
}

onMounted(() => {
  isMounted = true
  initAnimations()
})

watch(() => filteredTools.value, async () => {
  await nextTick()
  initAnimations()
}, { deep: true })

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section
        :id="sectionAnchorId(section.config.section_anchor)" ref="sectionRef" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="overflow-hidden transition-colors duration-300">
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
                        <div v-if="asString(section.config.badge_text)" :class="[badgeClass(asString(section.config.card_bg_style || section.config.background_style, 'default'), asString(section.config.title_color, 'dark'))]">
                            <i class="ti ti-sparkles text-xs"></i>
                            {{ t(asString(section.config.badge_text)) }}
                        </div>
                        <!-- Title Wrapper with Left Position Icon -->
                        <div :class="['flex items-center gap-3', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                            <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                sectionIconClass(asString(section.config.icon_style, 'primary')),
                                'h-9 h-12 w-12 text-xl shrink-0 -mt-3'
                            ]">
                                <i :class="asString(section.config.icon)"></i>
                            </div>
                            <h2 v-if="asString(section.config.title)" :class="titleClass">{{ t(asString(section.config.title, t('AI Tools Showcase'))) }}</h2>
                        </div>
                        <p v-if="asString(section.config.subtitle)" :class="subtitleClass">{{ t(asString(section.config.subtitle)) }}</p>
                    </div>
                </div>

                 <!-- Search Box -->
                <div v-if="showSearch" class="relative max-w-md mx-auto mb-8 z-10">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 z-20">
                        <i class="ti ti-search text-base" :class="searchIconClass"></i>
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="asString(section.config.search_placeholder, t('Search AI tools...'))"
                        :class="[searchInputClass, 'relative z-10']"
                    />
                    <button v-if="searchQuery" @click="searchQuery = ''" :class="['absolute inset-y-0 right-0 flex items-center pr-3 z-20', searchClearBtnClass]">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <!-- Category Filters -->
                <div v-if="showCategoryFilter && uniqueCategories.length > 1">
                    <!-- Desktop Filter — same collapse as mobile, just a higher cut. A catalog
                         with 30+ categories filled the width with four rows of pills and pushed
                         the tools themselves below the fold. -->
                    <div class="hidden sm:flex flex-wrap items-center justify-center gap-2 mb-10 py-1">
                        <button
                            v-for="cat in (categoriesExpanded ? uniqueCategories : uniqueCategories.slice(0, DESKTOP_CATEGORY_LIMIT))"
                            :key="cat"
                            @click="selectedCategory = cat"
                            :class="categoryBtnClass(cat)"
                        >
                            {{ cat === 'all' ? t('All categories') : t(cat) }}
                        </button>
                        <button
                            v-if="uniqueCategories.length > DESKTOP_CATEGORY_LIMIT"
                            @click="categoriesExpanded = !categoriesExpanded"
                            :class="categoryBtnClass('')"
                            class="inline-flex items-center gap-1.5"
                        >
                            <span>{{ categoriesExpanded ? t('Less') : t('More') }}</span>
                            <i :class="['ti', categoriesExpanded ? 'ti-chevron-up' : 'ti-chevron-down', 'text-[10px]']"></i>
                        </button>
                    </div>

                    <!-- Mobile Filter -->
                    <div class="flex sm:hidden flex-wrap items-center justify-center gap-2 mb-10 py-1">
                        <button
                            v-for="cat in (categoriesExpanded ? uniqueCategories : uniqueCategories.slice(0, 3))"
                            :key="cat"
                            @click="selectedCategory = cat"
                            :class="categoryBtnClass(cat)"
                        >
                            {{ cat === 'all' ? t('All categories') : t(cat) }}
                        </button>
                        <button
                            v-if="uniqueCategories.length > 3"
                            @click="categoriesExpanded = !categoriesExpanded"
                            :class="categoryBtnClass('')"
                            class="inline-flex items-center gap-1.5"
                        >
                            <span>{{ categoriesExpanded ? t('Less') : t('More') }}</span>
                            <i :class="['ti', categoriesExpanded ? 'ti-chevron-up' : 'ti-chevron-down', 'text-[10px]']"></i>
                        </button>
                    </div>
                </div>

                <div v-if="filteredTools.length > 0" class="grid gap-6 text-left" :class="toolsShowcaseGridClass(asString(section.config.layout, '3-column'))">
                    <Link v-for="tool in filteredTools" :key="tool.slug" :href="`/ai-tools/${tool.slug}`" :class="[cardWrapperClass(activeCardStyle), 'showcase-card']" :style="getCardBgStyle(tool, activeCardStyle)">

                        <!-- Style 1: Arrow hover indicator -->
                        <div v-if="activeCardStyle === 'style-1'" class="absolute right-6 top-6  opacity-0 transition-all duration-300 translate-x-1 -translate-y-1 group-hover:opacity-100 group-hover:translate-x-0 group-hover:translate-y-0 text-gray-400 dark:text-gray-500">
                            <i class="ti ti-arrow-up-right text-lg text-primary-600 dark:text-primary-400"></i>
                        </div>

                        <!-- CARD BODY: Style 1 (Modern Sleek) -->
                        <div v-if="activeCardStyle === 'style-1'" :class="cardBodyClass(activeCardStyle)">
                            <div class="flex items-center justify-between mb-4">
                                <span v-if="showCategory && tool.category" :class="categoryTagClass" class="inline-flex rounded-full px-3 py-1 text-[10px] font-semibold tracking-wider">{{ tool.category }}</span>
                                <span v-if="tool.is_featured" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ t('Featured') }}</span>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center text-white rounded-2xl shadow-md transition-all duration-300 group-hover:scale-105" :style="tool.color ? { background: tool.color } : { background: 'var(--color-primary-500)' }">
                                    <i v-if="tool.icon" :class="[tool.icon, 'text-lg']"></i>
                                    <span v-else class="text-sm font-black">{{ tool.name.charAt(0) }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-lg font-bold" :class="textTitleClass">{{ tool.name }}</h3>
                                    <p class="mt-1 line-clamp-2 text-sm" :class="textDescClass">{{ tool.description }}</p>
                                </div>
                            </div>
                            <div v-if="showRating" class="mt-4 flex items-center justify-between gap-4 border-t border-gray-100/10 dark:border-surface-800 pt-4">
                                <span :class="ratingBadgeClass">
                                    <i v-if="tool.avg_rating && Number(tool.avg_rating) > 0" class="ti ti-star-filled text-sm text-amber-400 shrink-0"></i>
                                    <i v-else class="ti ti-star text-sm shrink-0" :class="emptyStarClass"></i>
                                    <span>{{ tool.avg_rating && Number(tool.avg_rating) > 0 ? Number(tool.avg_rating).toFixed(1) : '0.0' }}</span>
                                </span>
                                <div v-if="showFavorite" class="z-20">
                                    <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" :is-gradient="isGradientCardBg" :card-style="activeCardStyle" show-count size="sm" />
                                </div>
                            </div>
                        </div>

                        <!-- CARD BODY: Style 2 (Vibrant Category Gradient) -->
                        <div v-if="activeCardStyle === 'style-2'" :class="cardBodyClass(activeCardStyle)">
                            <div class="flex items-center justify-between mb-4">
                                <span v-if="showRating" :class="ratingBadgeClass">
                                    <i v-if="tool.avg_rating && Number(tool.avg_rating) > 0" class="ti ti-star-filled text-sm text-amber-400 shrink-0"></i>
                                    <i v-else class="ti ti-star text-sm shrink-0" :class="emptyStarClass"></i>
                                    <span>{{ tool.avg_rating && Number(tool.avg_rating) > 0 ? Number(tool.avg_rating).toFixed(1) : '0.0' }}</span>
                                </span>
                                <div v-else></div>
                                <div v-if="showFavorite" class="z-20">
                                    <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" :is-gradient="isGradientCardBg" :card-style="activeCardStyle" show-count size="sm" />
                                </div>
                            </div>

                            <div class="flex flex-col items-center text-center mt-2 mb-4 flex-1">
                                <div class="flex h-14 w-14 items-center justify-center text-white rounded-full transition-transform duration-300 group-hover:scale-110 mb-4" :style="tool.color ? { background: tool.color, boxShadow: `0 10px 25px -5px ${tool.color}50` } : { background: 'var(--color-primary-500)' }">
                                    <i v-if="tool.icon" :class="[tool.icon, 'text-xl']"></i>
                                    <span v-else class="text-sm font-black">{{ tool.name.charAt(0) }}</span>
                                </div>
                                <h3 class="text-lg font-bold line-clamp-1 mb-2" :class="textTitleClass">{{ tool.name }}</h3>
                                <p class="line-clamp-2 text-sm leading-relaxed" :class="textDescClass">{{ tool.description }}</p>
                            </div>
                            <div v-if="showCategory && tool.category" class="flex items-center justify-between gap-3" :class="style2CategoryBorderClass">
                                <span :class="style2CategoryClass">{{ tool.category }}</span>
                                <span v-if="tool.is_featured" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ t('Featured') }}</span>
                            </div>
                        </div>

                        <!-- CARD BODY: Style 3 (Bento Minimal/Creative) -->
                        <div v-if="activeCardStyle === 'style-3'" :class="cardBodyClass(activeCardStyle)">
                            <div class="flex items-start gap-4 flex-1">
                                <!-- Left Bento column -->
                                <div class="flex flex-col items-center gap-4">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center text-white rounded-2xl shadow-md transition-all duration-300 group-hover:scale-105 group-hover:rotate-3" :style="tool.color ? { background: tool.color } : { background: 'var(--color-primary-500)' }">
                                        <i v-if="tool.icon" :class="[tool.icon, 'text-xl']"></i>
                                        <span v-else class="text-sm font-black">{{ tool.name.charAt(0) }}</span>
                                    </div>
                                    <span v-if="showRating" :class="ratingBadgeClass">
                                        <i v-if="tool.avg_rating && Number(tool.avg_rating) > 0" class="ti ti-star-filled text-sm text-amber-400 shrink-0"></i>
                                        <i v-else class="ti ti-star text-sm shrink-0" :class="emptyStarClass"></i>
                                        <span>{{ tool.avg_rating && Number(tool.avg_rating) > 0 ? Number(tool.avg_rating).toFixed(1) : '0.0' }}</span>
                                    </span>
                                    <div v-if="showFavorite" class="z-20">
                                        <FavoriteButton model-type="ai_templates" :model-id="tool.id" :is-favorited="Boolean(tool.is_favorited)" :count="tool.favorites_count" :is-gradient="isGradientCardBg" :card-style="activeCardStyle" show-count size="sm" />
                                    </div>
                                </div>

                                <!-- Right Bento column -->
                                <div class="min-w-0 flex-1 flex flex-col h-full">
                                    <div class="min-w-0 flex-1">
                                        <span v-if="showCategory && tool.category" :class="categoryTagClass" class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-semibold tracking-wider mb-2">{{ tool.category }}</span>
                                        <h3 class="text-lg font-bold truncate" :class="textTitleClass">{{ tool.name }}</h3>
                                        <span v-if="tool.is_featured" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ t('Featured') }}</span>
                                    </div>
                                    <p class="mt-2 line-clamp-3 text-sm" :class="textDescClass">{{ tool.description }}</p>
                                    <div class="mt-auto pt-4 flex items-center justify-end">
                                        <span :class="tryToolColorClass" class="text-xs font-bold group-hover:translate-x-1 transition-transform inline-flex items-center gap-0.5">
                                            {{ t('Try Tool') }} <i class="ti ti-chevron-right"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
                <div v-else :class="['p-10 text-center text-sm', isGradientCardBg ? '!text-white/80' : 'text-gray-500 dark:text-gray-400']">
                    <i class="ti ti-search-off text-5xl mb-4 block text-2xl"></i>
                    {{ t('No tools found matching your selection.') }}
                </div>

                <!-- Button -->
                <div v-if="asString(section.config.primary_text) && asString(section.config.primary_link)" :class="['mt-12 flex flex-col gap-4 sm:flex-row', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'items-center justify-center' : 'items-start justify-start']">
                    <Link :href="asString(section.config.primary_link, '/ai-tools')" :class="[heroButtonClass(asString(section.config.primary_style, 'primary')), heroButtonShapeClass(asString(section.config.primary_shape, 'rounded_xl'))]" class="inline-flex w-full items-center justify-center gap-3 px-8 py-4 font-black transition-colors sm:w-auto">
                        <i v-if="asString(section.config.primary_icon) && asString(section.config.primary_icon_position, 'left') !== 'right'" :class="[asString(section.config.primary_icon), 'block shrink-0 text-lg leading-none']"></i>
                        {{ t(asString(section.config.primary_text)) }}
                        <i v-if="asString(section.config.primary_icon) && asString(section.config.primary_icon_position, 'left') === 'right'" :class="[asString(section.config.primary_icon), 'block shrink-0 text-lg leading-none']"></i>
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
