<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]

interface HomepageSection {
    id: string
    type: string
    enabled: boolean
    core: boolean
    config: Record<string, SectionConfigValue>
}

interface FrontendHeaderSettings {
    desktop?: {
        height?: number
        transparent_on_hero?: boolean
    }
}

const props = defineProps<{
    section: HomepageSection
}>()

const { t } = useTranslate()
const page = usePage()
const appName = computed(() => String(page.props.branding?.site_name || t('Application')))
const frontendHeaderSettings = computed(() => (page.props.frontendHeaderSettings as FrontendHeaderSettings | undefined) ?? {})

const asString = (value: SectionConfigValue | undefined, fallback = ''): string => typeof value === 'string' || typeof value === 'number' ? String(value) : fallback
const asBoolean = (value: SectionConfigValue | undefined, fallback = false): boolean => typeof value === 'boolean' ? value : fallback
const asItems = (value: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(value) && value.every((item) => typeof item !== 'string') ? value : []

const resolveMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path
    return `/storage/${path}`
}

const headingParts = (headline: string): [string, string] => {
    const parts = headline.split('. ')
    return parts.length > 1 ? [`${parts[0]}.`, parts.slice(1).join('. ')] : [headline, '']
}

const headlineLines = (headline: string): string[] => headline.split('/').map(s => s.trim()).filter(Boolean)

const headlineSplitLines = computed(() => {
    const text = asString(props.section.config.headline, '')
    if (text.includes('/')) return headlineLines(text)
    return []
})

const typewriterPrefixLines = computed(() => {
    const prefix = headlineParts.value.prefix
    return headlineLines(prefix)
})

const heroAlignmentClass = (layout: string): string => {
    if (layout === 'left') return 'text-left items-start justify-start'
    if (layout === 'right') return 'text-right items-end justify-end'
    return 'text-center items-center justify-center'
}

const heroHeadingSizeClass = (size: string): string => ({
    sm: 'text-3xl md:text-5xl lg:text-6xl',
    md: 'text-4xl md:text-6xl lg:text-7xl',
    lg: 'text-5xl md:text-7xl lg:text-8xl',
    xl: 'text-6xl md:text-8xl lg:text-[5.5rem]',
}[size] ?? 'text-5xl md:text-7xl lg:text-8xl')

const heroSectionHeightClass = (height: string): string => ({
    default: '',
    compact: 'min-h-[540px]',
    comfortable: 'min-h-[640px]',
    tall: 'min-h-[760px]',
    full: 'min-h-screen',
}[height] ?? '')

const heroColorClass = (color: string, tone: 'heading' | 'subheading' = 'heading'): string => {
    const headingMap: Record<string, string> = {
        dark: 'text-gray-900 dark:text-white',
        light: '!text-white',
        primary: 'text-primary-600 dark:text-primary-400',
        white: '!text-white',
        blue: 'text-blue-600 dark:text-blue-400',
        red: 'text-red-600 dark:text-red-400',
    }
    const subheadingMap: Record<string, string> = {
        dark: 'text-gray-600 dark:text-gray-400',
        light: '!text-white/70',
        primary: 'text-primary-500/80 dark:text-primary-300/80',
        white: '!text-white/70',
        blue: 'text-blue-500/80 dark:text-blue-300/80',
        red: 'text-red-500 dark:text-red-300',
    }
    return tone === 'heading' ? (headingMap[color] ?? headingMap.dark) : (subheadingMap[color] ?? subheadingMap.light)
}

const heroButtonClass = (style: string): string => ({
    primary_filled: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    primary: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    dark: 'bg-gray-900 !text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800',
    purple: 'bg-violet-600 !text-white shadow-2xl shadow-violet-600/20 hover:bg-violet-700',
    gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 !text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
    red: 'bg-red-600 !text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    danger: 'bg-red-600 !text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    green: 'bg-success-600 !text-white shadow-2xl shadow-success-600/20 hover:bg-success-700',
    success: 'bg-emerald-600 !text-white shadow-2xl shadow-emerald-600/20 hover:bg-emerald-700',
    warning: 'bg-amber-500 !text-white shadow-2xl shadow-amber-500/20 hover:bg-amber-600',
    gradient_sunset: 'bg-gradient-to-r from-orange-500 via-pink-500 to-purple-600 !text-white shadow-2xl hover:opacity-95',
    gradient_ocean: 'bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-600 !text-white shadow-2xl hover:opacity-95',
    gradient_royal: 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 !text-white shadow-2xl hover:opacity-95',
    outline: 'border-2 border-white/40 bg-transparent !text-white hover:bg-white/10 dark:border-white/30 dark:bg-transparent dark:!text-white dark:hover:bg-white/10',
    white: 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25',
    light: 'bg-white/10 !text-white shadow-xl hover:bg-white/20',
    ghost: 'bg-transparent border-2 border-white/30 !text-white hover:bg-white/10 dark:border-white/20 dark:!text-white dark:hover:bg-white/10',
}[style] ?? 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')

const heroButtonShapeClass = (shape: string): string => ({
    sharp: 'rounded-lg',
    rounded: 'rounded-xl',
    rounded_xl: 'rounded-2xl',
    pill: 'rounded-full',
}[shape] ?? 'rounded-2xl')

const heroButtonSizeClass = (size: string): string => ({
    sm: 'px-5 py-2.5 text-sm',
    md: 'px-8 py-4 text-base',
    lg: 'px-10 py-5 text-lg',
    xl: 'px-12 py-6 text-xl',
}[size] ?? 'px-8 py-4 text-base')

const isDarkGradient = (palette: string): boolean => !['light_glow', 'light_warm'].includes(palette)

const heroGradientStyle = (palette: string, direction: string): string => {
    const dir = direction || 'to bottom right'
    const palettes: Record<string, string> = {
        aurora: `linear-gradient(${dir}, var(--color-primary, #10b981), var(--color-secondary, #3b82f6))`,
        sunset: `linear-gradient(${dir}, #f97316, #ec4899)`,
        royal: `linear-gradient(${dir}, #8b5cf6, #ec4899)`,
        mint_fire: `linear-gradient(${dir}, #10b981, #f97316)`,
        neon_night: `linear-gradient(${dir}, #06b6d4, #8b5cf6)`,
        gold_rush: `linear-gradient(${dir}, #f59e0b, #ef4444)`,
        light_glow: `linear-gradient(${dir}, #f0fdf4, #e0f2fe)`,
        light_warm: `linear-gradient(${dir}, #fff7ed, #fef2f2)`,
    }
    return palettes[palette] ?? palettes.aurora
}

const gradientEnabled = computed(() => asBoolean(props.section.config.hero_gradient_enabled, true) && !!asString(props.section.config.hero_gradient_palette))

const hasBackgroundMedia = computed(() =>
    asBoolean(props.section.config.show_hero_background, false) && !!asString(props.section.config.hero_background_url)
)

const isBackgroundDark = computed(() => {
    const palette = asString(props.section.config.hero_gradient_palette)
    const enabled = asBoolean(props.section.config.hero_gradient_enabled, true)
    if (!palette || !enabled) return false
    return isDarkGradient(palette)
})

const user = computed(() => {
    const auth = page.props.auth as Record<string, unknown> | undefined
    return (auth?.user as Record<string, unknown> | undefined) ?? null
})
const isAuthenticated = computed(() => user.value !== null)
const isPro = computed(() => Boolean((user.value as Record<string, unknown> | undefined)?.is_pro ?? false))

const checkAccessLevel = (level: string): boolean => {
    if (level === 'all' || !level) return true
    if (level === 'guest') return !isAuthenticated.value
    if (level === 'auth') return isAuthenticated.value
    if (level === 'not_pro') return isAuthenticated.value && !isPro.value
    if (level === 'pro') return isPro.value
    return true
}

// ─── Typewriter effect ───
const typewriterText = ref('')
const typewriterIndex = ref(0)
const typewriterCharIndex = ref(0)
const typewriterDeleting = ref(false)
const typewriterPaused = ref(false)

const headlineParts = computed(() => {
    const text = asString(props.section.config.headline, '')
    const parts = text.split('|').map(s => s.trim()).filter(Boolean)
    return { prefix: parts[0] ?? '', phrases: parts.slice(1) }
})

const showTypewriter = computed(() => headlineParts.value.phrases.length > 0)

let typewriterTimer: ReturnType<typeof setInterval> | null = null
let typewriterPauseTimer: ReturnType<typeof setTimeout> | null = null

function typewriterTick(): void {
    const phrases = headlineParts.value.phrases
    if (phrases.length === 0) return

    if (typewriterPaused.value) return

    const current = phrases[typewriterIndex.value]

    if (typewriterDeleting.value) {
        typewriterCharIndex.value--
        typewriterText.value = current.slice(0, typewriterCharIndex.value)
        if (typewriterCharIndex.value <= 0) {
            typewriterDeleting.value = false
            typewriterIndex.value = (typewriterIndex.value + 1) % phrases.length
            typewriterCharIndex.value = 0
        }
    } else {
        typewriterCharIndex.value++
        typewriterText.value = current.slice(0, typewriterCharIndex.value)
        if (typewriterCharIndex.value >= current.length) {
            typewriterPaused.value = true
            typewriterPauseTimer = setTimeout(() => {
                typewriterPaused.value = false
                typewriterDeleting.value = true
            }, 2500)
        }
    }
}

function startTypewriter(): void {
    typewriterTimer = setInterval(typewriterTick, 100)
}

onMounted(() => {
    if (showTypewriter.value) startTypewriter()
})

onUnmounted(() => {
    if (typewriterTimer) clearInterval(typewriterTimer)
    if (typewriterPauseTimer) clearTimeout(typewriterPauseTimer)
})

const sectionOverlayStyle = (opacity?: number): Record<string, string> => {
    const o = Math.max(0, Math.min(100, Number(opacity || 55))) / 100
    return { opacity: String(o) }
}

const sectionVisibilityClass = (visibility: string): string => ({
    all: '',
    desktop: 'hidden lg:block',
    tablet: 'hidden md:block lg:hidden',
    mobile: 'block md:hidden',
    desktop_tablet: 'hidden md:block',
    tablet_mobile: 'block lg:hidden',
}[visibility] ?? '')

const heroLayoutVariant = computed(() => asString(props.section.config.layout, 'centered-gradient'))

const heroFullscreenClass = computed(() => asString(props.section.config.hero_vertical_padding, '') === 'full' ? 'min-h-screen flex flex-col justify-center' : '')

const heroSectionStyle = computed(() => {
    const padding = asString(props.section.config.hero_vertical_padding, '48')
    const style: Record<string, string> = {}
    if (padding !== 'full') {
        style['--hero-section-padding'] = `${Number(padding)}px`
    }
    const palette = asString(props.section.config.hero_gradient_palette)
    const enabled = asBoolean(props.section.config.hero_gradient_enabled, true)
    if (palette && enabled) {
        style.background = heroGradientStyle(palette, asString(props.section.config.hero_gradient_direction))
    } else {
        style.background = 'var(--color-bg, #ffffff)'
    }
    return style
})

const desktopHeroHeaderOffset = computed(() => {
    const desktop = frontendHeaderSettings.value.desktop ?? {}

    if (desktop.transparent_on_hero !== true) return 0

    return Number(desktop.height ?? 72)
})

const heroSpacingStyle = (basePadding: number): Record<string, string> => {
    if (asString(props.section.config.hero_vertical_padding, '') === 'full') return {}
    return {
        '--hero-padding-top': `${basePadding + desktopHeroHeaderOffset.value}px`,
        '--hero-padding-bottom': `${basePadding}px`,
        '--hero-padding-top-mobile': `${basePadding}px`,
    }
}

const effectiveHeadingColor = computed(() => {
    if (isBackgroundDark.value) return 'light'
    return asString(props.section.config.hero_heading_color, 'dark')
})

const effectiveSubheadingColor = computed(() => {
    if (isBackgroundDark.value) return 'light'
    return 'dark'
})

const heroGradientTextClass = computed(() => {
    if (!gradientEnabled.value) return ''
    if (isBackgroundDark.value) {
        return 'bg-gradient-to-r from-white via-primary-200 to-white bg-clip-text !text-transparent'
    }
    return 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 bg-clip-text !text-transparent'
})

const heroGradientTextDarkClass = computed(() => {
    if (!gradientEnabled.value) return ''
    if (isBackgroundDark.value) {
        return 'bg-gradient-to-r from-white/80 via-primary-300/80 to-white/80 bg-clip-text !text-transparent'
    }
    return 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 bg-clip-text !text-transparent'
})
</script>

<template>
    <!-- centered-gradient (default) -->
    <section v-if="heroLayoutVariant === 'centered-gradient'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="{ ...heroSectionStyle, ...heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '48'))) }"
        :class="[
            heroSectionHeightClass(asString(props.section.config.hero_section_height, 'default')),
            heroFullscreenClass,
            sectionVisibilityClass(asString(props.section.config.visibility, 'all')),
            'hero-section-shell relative isolate overflow-hidden transition-colors duration-300',
        ]"
    >
        <div v-if="asBoolean(props.section.config.show_hero_background, false) && asString(props.section.config.hero_background_url)" class="absolute inset-0 z-0">
            <img v-if="asString(props.section.config.hero_background_type, 'image') === 'image'" :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" alt="" loading="lazy" class="h-full w-full object-cover">
            <video v-else :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" class="h-full w-full object-cover" autoplay muted loop playsinline></video>
        </div>
        <div v-if="(hasBackgroundMedia || isBackgroundDark) && asBoolean(props.section.config.show_hero_gradient_overlay, true)" :style="sectionOverlayStyle(Number(props.section.config.overlay_opacity) || undefined)" class="absolute inset-0 z-10 bg-gradient-to-b from-black/70 via-black/45 to-black/25"></div>
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <div :class="heroAlignmentClass('center')" class="flex flex-col">
                <div>
                    <div v-if="asString(props.section.config.trust_badge_text)"
                        class="mb-10 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/60 px-4 py-2 text-xs font-black uppercase tracking-widest text-gray-900 shadow-sm backdrop-blur-md"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 :class="[heroHeadingSizeClass(asString(props.section.config.hero_heading_size, 'lg')), gradientEnabled ? heroGradientTextClass : heroColorClass(effectiveHeadingColor)]" class="mb-8 font-black leading-[1.1] tracking-tight">
                        <template v-if="showTypewriter">
                            <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                            &nbsp;<span class="inline-block min-w-[2ch]">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                        </template>
                        <template v-else-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="gradientEnabled ? heroGradientTextClass : ''">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mx-auto mb-12 max-w-3xl text-lg font-medium leading-relaxed md:text-xl']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <p v-else :class="isBackgroundDark ? 'mx-auto mb-12 max-w-3xl text-lg font-medium leading-relaxed text-white/70 md:text-xl' : 'mx-auto mb-12 max-w-3xl text-lg font-medium leading-relaxed text-gray-600 md:text-xl dark:text-gray-400'">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div class="flex-col gap-4 sm:flex-row flex justify-center">
                        <Link v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.primary_cta_link, '/register')"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'lg'))]"
                            class="inline-flex w-full items-center justify-center gap-3 font-black transition-all hover:-translate-y-1 sm:w-auto"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </Link>
                        <Link v-if="asBoolean(props.section.config.show_secondary_cta, true) && asString(props.section.config.secondary_cta_text) && checkAccessLevel(asString(props.section.config.secondary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.secondary_cta_link, '/pricing')"
                            :class="[heroButtonClass(asString(props.section.config.secondary_cta_style, 'outline')), heroButtonShapeClass(asString(props.section.config.secondary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.secondary_cta_size, 'lg'))]"
                            class="inline-flex w-full items-center justify-center gap-3 font-black transition-all sm:w-auto"
                        >
                            <i v-if="asString(props.section.config.secondary_cta_icon)"
                                :class="[asString(props.section.config.secondary_cta_icon), asString(props.section.config.secondary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.secondary_cta_text) }}
                        </Link>
                    </div>
                    <div v-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0"
                        :class="isBackgroundDark && !gradientEnabled ? 'mt-24 border-t border-white/20 pt-12 grid grid-cols-2 gap-8 md:grid-cols-4' : 'mt-24 border-t border-gray-100 pt-12 dark:border-surface-800 grid grid-cols-2 gap-8 md:grid-cols-4'"
                    >
                        <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                            <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                            <p v-else :class="isBackgroundDark ? 'text-3xl font-black text-white' : 'text-3xl font-black text-gray-900 dark:text-white'">{{ stat.number }}</p>
                            <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                            <p v-else :class="isBackgroundDark ? 'mt-1 text-xs font-black uppercase tracking-widest text-white/60' : 'mt-1 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400'">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- tools-grid -->
    <section v-else-if="heroLayoutVariant === 'tools-grid'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96')))"
        :class="['hero-section-shell relative isolate overflow-hidden bg-[var(--color-bg)] transition-colors duration-300', heroFullscreenClass]"
    >
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div>
                    <div v-if="asString(props.section.config.trust_badge_text)" class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/60 px-4 py-1.5 text-xs font-black uppercase tracking-widest text-gray-900 shadow-sm backdrop-blur-md">
                        <span class="h-2 w-2 rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 class="mb-6 text-4xl font-black leading-[1.15] tracking-tight text-gray-900 md:text-6xl lg:text-7xl dark:text-white">
                        <template v-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" class="text-primary-600 dark:text-primary-400">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p class="mb-10 max-w-xl text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div class="flex gap-4">
                        <Link v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.primary_cta_link, '/register')"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                            class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </Link>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div v-for="i in 4" :key="i" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-800">
                        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                            <i class="ti ti-apps text-2xl"></i>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ t('AI Tool') }} {{ i }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Powerful AI capabilities') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- split-gradient -->
    <section v-else-if="heroLayoutVariant === 'split-gradient'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :class="['hero-section-shell relative isolate overflow-hidden transition-colors duration-300', heroFullscreenClass]"
        :style="{ ...heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96'))), background: gradientEnabled ? heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) : 'var(--color-bg, #ffffff)' }"
    >
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6 py-24">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div class="text-left">
                    <h1 :class="['mb-6 text-4xl font-black leading-[1.15] tracking-tight md:text-6xl lg:text-7xl', gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white')]">
                        <template v-if="showTypewriter">
                            <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                            &nbsp;<span class="inline-block min-w-[2ch]">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                        </template>
                        <template v-else-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? 'text-white/70' : 'text-primary-600 dark:text-primary-400')">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mb-10 max-w-xl text-lg leading-relaxed']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <p v-else :class="['mb-10 max-w-xl text-lg leading-relaxed', isBackgroundDark ? 'text-white/80' : 'text-gray-600 dark:text-gray-400']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div class="flex gap-4">
                        <Link v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.primary_cta_link, '/register')"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'white')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                            class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </Link>
                    </div>
                </div>
                <div :class="isBackgroundDark && !gradientEnabled ? 'rounded-2xl border border-white/20 bg-white/10 p-8 backdrop-blur-sm' : 'rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-surface-700 dark:bg-surface-800'">
                    <div v-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0" class="grid grid-cols-2 gap-6">
                        <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                            <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                            <p v-else :class="isBackgroundDark ? 'text-3xl font-black text-white' : 'text-3xl font-black text-gray-900 dark:text-white'">{{ stat.number }}</p>
                            <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                            <p v-else :class="isBackgroundDark ? 'mt-1 text-xs font-black uppercase tracking-widest text-white/60' : 'mt-1 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400'">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- app-showcase -->
    <section v-else-if="heroLayoutVariant === 'app-showcase'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96')))"
        :class="['hero-section-shell relative isolate overflow-hidden bg-[var(--color-bg)] transition-colors duration-300', heroFullscreenClass]"
    >
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <div class="grid items-center gap-16 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <div v-if="asString(props.section.config.trust_badge_text)" class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/60 px-4 py-1.5 text-xs font-black uppercase tracking-widest text-gray-900 shadow-sm backdrop-blur-md">
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 class="mb-6 text-4xl font-black leading-[1.15] tracking-tight text-gray-900 md:text-5xl lg:text-6xl dark:text-white">
                        <template v-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>{{ asString(props.section.config.headline, t('One Platform. Every AI Tool.')) }}</template>
                    </h1>
                    <p class="mb-10 max-w-lg text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div class="flex gap-4">
                        <Link v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.primary_cta_link, '/register')"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                            class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </Link>
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xl dark:border-surface-700 dark:bg-surface-800">
                        <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-4 dark:border-surface-700">
                            <div class="flex gap-1.5">
                                <span class="h-3 w-3 rounded-full bg-red-400"></span>
                                <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                                <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                            </div>
                            <div class="ml-auto h-3 w-24 rounded-full bg-gray-200 dark:bg-surface-600"></div>
                        </div>
                        <div class="space-y-3">
                            <div class="h-4 w-3/4 rounded bg-gray-100 dark:bg-surface-700"></div>
                            <div class="h-4 w-1/2 rounded bg-gray-100 dark:bg-surface-700"></div>
                            <div class="h-4 w-5/6 rounded bg-gray-100 dark:bg-surface-700"></div>
                            <div class="flex gap-2 pt-2">
                                <div class="h-8 w-20 rounded-lg bg-primary-100 dark:bg-primary-900/30"></div>
                                <div class="h-8 w-20 rounded-lg bg-gray-100 dark:bg-surface-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- enterprise -->
    <section v-else-if="heroLayoutVariant === 'enterprise'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="[heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96'))), gradientEnabled ? { background: heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) } : {}]"
        :class="['hero-section-shell enterprise-hero relative isolate overflow-hidden transition-colors duration-300', heroFullscreenClass, gradientEnabled ? '' : 'bg-gray-950']"
    >
        <div v-if="!gradientEnabled" class="pointer-events-none absolute inset-0 z-0 opacity-[0.04]" style="background-image: repeating-linear-gradient(0deg,transparent,transparent 59px,#ffffff20 59px,#ffffff20 60px),repeating-linear-gradient(90deg,transparent,transparent 59px,#ffffff20 59px,#ffffff20 60px);"></div>
        <div class="relative z-20 mx-auto w-full max-w-6xl px-6">
            <div class="mx-auto w-full max-w-3xl text-center">
                <div v-if="asString(props.section.config.trust_badge_text)" class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/60 px-4 py-1.5 text-xs font-black uppercase tracking-widest text-gray-900 shadow-sm backdrop-blur-md">
                    {{ asString(props.section.config.trust_badge_text) }}
                </div>
                <h1 :class="['mb-6 text-4xl font-black leading-[1.15] tracking-tight md:text-6xl lg:text-7xl', gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white')]">
                    <template v-if="showTypewriter">
                        <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                        &nbsp;<span class="inline-block min-w-[2ch]">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                    </template>
                    <template v-else-if="headlineSplitLines.length">
                        <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                    </template>
                    <template v-else>
                        {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                        <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="gradientEnabled ? '' : (isBackgroundDark ? 'text-primary-400' : 'text-primary-600 dark:text-primary-400')">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                    </template>
                </h1>
                <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mx-auto mb-12 max-w-2xl text-lg leading-relaxed']">
                    {{ asString(props.section.config.subheadline) }}
                </p>
                <p v-else :class="['mx-auto mb-12 max-w-2xl text-lg leading-relaxed', isBackgroundDark ? 'text-white/60' : 'text-gray-600 dark:text-gray-400']">
                    {{ asString(props.section.config.subheadline) }}
                </p>
                <div class="flex justify-center gap-4">
                    <Link v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                        :href="asString(props.section.config.primary_cta_link, '/register')"
                        :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                        class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                    >
                        <i v-if="asString(props.section.config.primary_cta_icon)"
                            :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                        ></i>
                        {{ asString(props.section.config.primary_cta_text) }}
                    </Link>
                </div>
            </div>
            <div v-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0"
                :class="['mt-24 grid grid-cols-4 gap-8 border-t pt-12', isBackgroundDark ? 'border-white/10' : 'border-gray-100 dark:border-surface-800']">
                <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                    <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                    <p v-else :class="['text-3xl font-black', isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white']">{{ stat.number }}</p>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                    <p v-else :class="['mt-1 text-xs font-black uppercase tracking-widest', isBackgroundDark ? 'text-white/40' : 'text-gray-500 dark:text-gray-400']">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- minimal -->
    <section v-else-if="heroLayoutVariant === 'minimal'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '128')))"
        :class="['hero-section-shell relative isolate overflow-hidden bg-white transition-colors duration-300 dark:bg-surface-950', heroFullscreenClass]"
    >
        <div class="relative z-20 mx-auto w-full max-w-4xl px-6">
            <div class="text-center">
                <h1 class="mb-8 text-5xl font-extralight leading-[1.1] tracking-tight text-gray-900 md:text-7xl lg:text-8xl dark:text-white">
                    <template v-if="headlineSplitLines.length">
                        <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                    </template>
                    <template v-else>{{ asString(props.section.config.headline, t('One Platform. Every AI Tool.')) }}</template>
                </h1>
                <p class="mx-auto mb-14 max-w-2xl text-lg font-light leading-relaxed text-gray-400 dark:text-gray-500">
                    {{ asString(props.section.config.subheadline) }}
                </p>
                <div class="flex justify-center gap-4">
                    <Link v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                        :href="asString(props.section.config.primary_cta_link, '/register')"
                        :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'ghost')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'pill')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'sm'))]"
                        class="inline-flex items-center gap-2 font-medium transition-all"
                    >
                        <i v-if="asString(props.section.config.primary_cta_icon)"
                            :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                        ></i>
                        {{ asString(props.section.config.primary_cta_text) }}
                    </Link>
                    <Link v-if="asBoolean(props.section.config.show_secondary_cta, true) && asString(props.section.config.secondary_cta_text) && checkAccessLevel(asString(props.section.config.secondary_cta_access_level, 'all'))"
                        :href="asString(props.section.config.secondary_cta_link, '/pricing')"
                        :class="[heroButtonClass(asString(props.section.config.secondary_cta_style, 'ghost')), heroButtonShapeClass(asString(props.section.config.secondary_cta_shape, 'pill')), heroButtonSizeClass(asString(props.section.config.secondary_cta_size, 'sm'))]"
                        class="inline-flex items-center gap-2 font-medium transition-all"
                    >
                        <i v-if="asString(props.section.config.secondary_cta_icon)"
                            :class="[asString(props.section.config.secondary_cta_icon), asString(props.section.config.secondary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                        ></i>
                        {{ asString(props.section.config.secondary_cta_text) }}
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-section-shell {
    padding-top: var(--hero-padding-top, 0px);
    padding-bottom: var(--hero-padding-bottom, 0px);
}

@media (max-width: 767px) {
    .hero-section-shell {
        padding-top: var(--hero-padding-top-mobile, var(--hero-padding-top, 0px));
    }
}

.enterprise-hero::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    z-index: 10;
    width: 1200px;
    height: 350px;
    border-radius: 50%;
    transform: translateX(-50%);
    background: radial-gradient(ellipse 50% 50% at 50% 100%, rgba(59,130,246,0.35) 0%, transparent 70%);
    pointer-events: none;
}
</style>
