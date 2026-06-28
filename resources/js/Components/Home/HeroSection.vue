<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'

const { isDark } = useTheme()

const enterpriseGridStyle = computed(() => {
    const color = isDark.value ? '#ffffff' : '#000000'
    return {
        backgroundImage: `repeating-linear-gradient(0deg,transparent,transparent 59px,${color} 59px,${color} 60px),repeating-linear-gradient(90deg,transparent,transparent 59px,${color} 59px,${color} 60px)`
    }
})

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

interface ToolItem {
    slug: string
    name: string
    description?: string
    icon?: string | null
    color?: string | null
    category?: string | null
    usage_count?: number
    is_featured?: boolean
}

const props = defineProps<{
    section: HomepageSection
    allTools?: ToolItem[]
}>()

const { t } = useTranslate()
const page = usePage()
const appName = computed(() => String(page.props.branding?.site_name || t('MakeAI')))
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

const heroButtonClass = (style: string): string => {
    const isDarkTheme = isDark.value || isBackgroundDark.value
    const map: Record<string, string> = {
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
        outline: isDarkTheme
            ? 'border-2 border-white/40 bg-transparent !text-white hover:bg-white/10'
            : 'border-2 border-gray-300 bg-transparent text-gray-900 hover:bg-gray-50 dark:border-white/30 dark:bg-transparent dark:!text-white dark:hover:bg-white/10',
        white: isDarkTheme
            ? 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25'
            : 'bg-white border border-gray-200 text-gray-900 shadow-md hover:bg-gray-50 dark:bg-surface-800 dark:border-surface-700 dark:text-white',
        light: isDarkTheme
            ? 'bg-white/10 !text-white shadow-xl hover:bg-white/20'
            : 'bg-gray-100 text-gray-900 border border-gray-150 hover:bg-gray-200 dark:bg-surface-800 dark:text-white dark:border-surface-700',
        ghost: isDarkTheme
            ? 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25'
            : 'bg-transparent text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800',
    }
    return map[style] ?? map.primary_filled
}

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
    const isDarkTheme = isDark.value
    const palettes: Record<string, string> = {
        aurora: `linear-gradient(${dir}, var(--color-primary, #10b981), var(--color-secondary, #3b82f6))`,
        sunset: `linear-gradient(${dir}, #f97316, #ec4899)`,
        royal: `linear-gradient(${dir}, #8b5cf6, #ec4899)`,
        mint_fire: `linear-gradient(${dir}, #10b981, #f97316)`,
        neon_night: `linear-gradient(${dir}, #06b6d4, #8b5cf6)`,
        gold_rush: `linear-gradient(${dir}, #f59e0b, #ef4444)`,
        light_glow: isDarkTheme
            ? `linear-gradient(${dir}, #022c22, #083344)`
            : `linear-gradient(${dir}, #f0fdf4, #e0f2fe)`,
        light_warm: isDarkTheme
            ? `linear-gradient(${dir}, #431407, #0f0505)`
            : `linear-gradient(${dir}, #fff7ed, #fef2f2)`,
    }
    return palettes[palette] ?? palettes.aurora
}

const gradientEnabled = computed(() =>
    !isDark.value &&
    !hasBackgroundMedia.value &&
    asBoolean(props.section.config.hero_gradient_enabled, true) &&
    !!asString(props.section.config.hero_gradient_palette)
)

const hasBackgroundMedia = computed(() =>
    asBoolean(props.section.config.show_hero_background, false) && !!asString(props.section.config.hero_background_url)
)

const isBackgroundDark = computed(() => {
    if (isDark.value) return true
    if (hasBackgroundMedia.value) return true
    const palette = asString(props.section.config.hero_gradient_palette)
    const enabled = asBoolean(props.section.config.hero_gradient_enabled, true)
    return !!(palette && enabled) && isDarkGradient(palette)
})

const isDarkGradientSelected = computed(() => {
    if (isDark.value) return false
    if (hasBackgroundMedia.value) return false
    const palette = asString(props.section.config.hero_gradient_palette)
    const enabled = asBoolean(props.section.config.hero_gradient_enabled, true)
    return !!(palette && enabled) && isDarkGradient(palette)
})

const accentColorClass = computed(() =>
    gradientEnabled.value
        ? heroGradientTextClass.value
        : (isBackgroundDark.value ? '!text-white' : 'text-primary-600 dark:text-primary-400')
)

const toolsGridItems = computed<ToolItem[]>(() => {
    const raw = props.section.config.tools_grid_tool_slugs
    const slugs: string[] = Array.isArray(raw) ? raw as string[] : []
    if (slugs.length === 0) return []
    const all = props.allTools ?? []
    return slugs.map((slug) => all.find((t) => t.slug === slug)).filter(Boolean) as ToolItem[]
})

type DisplayCategory = { name: string; icon: string; color: string; count: number }

const displayCategories = computed<DisplayCategory[]>(() => {
    const all = props.allTools ?? []
    const configCats = props.section.config.display_categories
    const configArray = Array.isArray(configCats) ? configCats as string[] : []

    let categoriesToProcess = configArray
    if (categoriesToProcess.length === 0) {
        const uniqueCats = new Set<string>()
        for (const t of all) {
            const cat = (t.category || '').trim()
            if (cat) {
                uniqueCats.add(cat)
            }
        }
        categoriesToProcess = Array.from(uniqueCats)
    }

    if (categoriesToProcess.length === 0) return []

    const result: DisplayCategory[] = []
    for (const name of categoriesToProcess) {
        const normalizedName = name.trim().toLowerCase()
        const toolsInCat = all.filter((t) => (t.category || 'Other').trim().toLowerCase() === normalizedName)
        const first = toolsInCat[0]
        result.push({
            name: first?.category || name,
            icon: first?.icon || 'ti ti-folder',
            color: first?.color || 'var(--color-primary)',
            count: toolsInCat.length,
        })
        if (result.length >= 4) break
    }
    return result
})

const displayTools = computed<ToolItem[]>(() => {
    const all = props.allTools ?? []
    const configSlugs = props.section.config.display_tool_slugs
    const configArray = Array.isArray(configSlugs) ? configSlugs as string[] : []

    if (configArray.length > 0) {
        return configArray.map((slug) => all.find((t) => t.slug === slug)).filter(Boolean) as ToolItem[]
    }

    // Fallback: featured tools from the database
    const featured = all.filter((t) => t.is_featured)
    if (featured.length > 0) {
        return featured
    }

    return all.slice(0, 10)
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
    if (!hasBackgroundMedia.value) {
        const palette = asString(props.section.config.hero_gradient_palette)
        const enabled = asBoolean(props.section.config.hero_gradient_enabled, true)
        if (palette && enabled) {
            style.background = heroGradientStyle(palette, asString(props.section.config.hero_gradient_direction))
        } else {
            style.background = 'var(--color-bg, #ffffff)'
        }
    }
    return style
})

const desktopHeroHeaderOffset = computed(() => {
    const desktop = frontendHeaderSettings.value.desktop ?? {}

    if (String(desktop.transparent_on_hero) !== 'true') return 0

    return Number(desktop.height ?? 72)
})

const heroSpacingStyle = (basePadding: number): Record<string, string> => {
    if (asString(props.section.config.hero_vertical_padding, '') === 'full') return {}
    const top = basePadding + desktopHeroHeaderOffset.value
    return {
        paddingTop: `${top}px`,
        '--hero-padding-top': `${top}px`,
        paddingBottom: `${basePadding}px`,
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

const extractYouTubeId = (url: string): string | null => {
    if (!url) return null
    const patterns = [
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([^&\s?#]+)/,
        /^([a-zA-Z0-9_-]{11})$/, // raw video ID
    ]
    for (const p of patterns) {
        const m = url.match(p)
        if (m?.[1]) return m[1]
    }
    return null
}

const primaryCtaVideoId = computed(() => extractYouTubeId(asString(props.section.config.primary_cta_link, '')))
const showVideoPopup = ref(false)

const openVideoPopup = () => { showVideoPopup.value = true }

const handlePrimaryCtaClick = () => {
    if (primaryCtaVideoId.value) {
        openVideoPopup()
    } else {
        window.location.href = asString(props.section.config.primary_cta_link, '/register')
    }
}

const openCommandPalette = () => {
    window.dispatchEvent(new CustomEvent('palette:open'))
}

const heroRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    const { gsap } = await import('gsap')

    gsapCtx = gsap.context(() => {
        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })
        
        const heading = heroRef.value?.querySelector('.gsap-heading')
        const subtext = heroRef.value?.querySelector('.gsap-subtext')
        const cta = heroRef.value?.querySelector('.gsap-cta')
        const image = heroRef.value?.querySelector('.gsap-image')

        if (heading) {
            tl.from(heading, { y: 40, opacity: 0, duration: 0.8 })
        }
        if (subtext) {
            tl.from(subtext, { y: 30, opacity: 0, duration: 0.7 }, heading ? '-=0.5' : '+=0')
        }
        if (cta) {
            tl.from(cta, { y: 20, opacity: 0, duration: 0.6 }, subtext ? '-=0.4' : (heading ? '-=0.3' : '+=0'))
        }
        if (image) {
            tl.from(image, { x: 60, opacity: 0, duration: 0.9, ease: 'power2.out' }, '-=0.6')
        }
    }, heroRef.value!)
})

onUnmounted(() => {
    gsapCtx?.revert()
})
</script>

<template>
    <div ref="heroRef">
    <!-- centered-gradient (default) -->
    <section v-if="heroLayoutVariant === 'centered-gradient'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="[heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '48'))), gradientEnabled ? { background: heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) } : {}]"
        :class="[
            heroSectionHeightClass(asString(props.section.config.hero_section_height, 'default')),
            heroFullscreenClass,
            sectionVisibilityClass(asString(props.section.config.visibility, 'all')),
            'hero-section-shell relative isolate overflow-hidden transition-colors duration-300',
            gradientEnabled ? '' : '!bg-white dark:!bg-surface-950'
        ]"
    >
        <div v-if="asBoolean(props.section.config.show_hero_background, false) && asString(props.section.config.hero_background_url)" class="absolute inset-0 z-0">
            <img v-if="asString(props.section.config.hero_background_type, 'image') === 'image'" :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" alt="" loading="lazy" class="h-full w-full object-cover">
            <video v-else :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" class="h-full w-full object-cover" autoplay muted loop playsinline></video>
        </div>
        <div v-if="(hasBackgroundMedia || (gradientEnabled && isBackgroundDark)) && asBoolean(props.section.config.show_hero_gradient_overlay, true)" :style="sectionOverlayStyle(Number(props.section.config.overlay_opacity) || undefined)" class="absolute inset-0 z-10 bg-gradient-to-b from-black/70 via-black/45 to-black/25"></div>
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <div :class="heroAlignmentClass('center')" class="flex flex-col">
                <div>
                    <div v-if="asString(props.section.config.trust_badge_text)"
                        :class="[
                            'mb-10 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-black uppercase tracking-widest shadow-sm backdrop-blur-md',
                            isDarkGradientSelected
                                ? 'border-white/30 bg-transparent text-white'
                                : 'border-white/30 bg-white/60 text-gray-900 dark:border-surface-700 dark:bg-surface-800/85 dark:text-gray-300'
                        ]"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <div class="max-w-3xl">
                        <h1 :class="[heroHeadingSizeClass(asString(props.section.config.hero_heading_size, 'lg')), gradientEnabled ? heroGradientTextClass : heroColorClass(effectiveHeadingColor)]" class="mb-8 font-black leading-[1.25] tracking-tight gsap-heading">
                            <template v-if="showTypewriter">
                                <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                                &nbsp;<span class="inline-block min-w-[2ch]" :class="accentColorClass">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                            </template>
                            <template v-else-if="headlineSplitLines.length">
                                <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                            </template>
                            <template v-else>
                                {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                                <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="accentColorClass">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                            </template>
                        </h1>
                        <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mx-auto mb-12 text-lg font-medium leading-relaxed md:text-xl gsap-subtext']">
                            {{ asString(props.section.config.subheadline) }}
                        </p>
                        <p v-else :class="isBackgroundDark ? 'mx-auto mb-12 text-lg font-medium leading-relaxed text-white/70 md:text-xl gsap-subtext' : 'mx-auto mb-12 text-lg font-medium leading-relaxed text-gray-600 md:text-xl dark:text-gray-400 gsap-subtext'">
                            {{ asString(props.section.config.subheadline) }}
                        </p>
                    </div>
                    <div v-if="asBoolean(props.section.config.show_search_box, true)" class="mx-auto mb-8 w-full max-w-lg">
                        <button type="button" @click="openCommandPalette()"
                            :class="[
                                'flex w-full items-center gap-3 rounded-full border px-5 py-3 text-left text-sm backdrop-blur-sm transition-all',
                                isDarkGradientSelected
                                    ? 'border-white/20 bg-white/10 text-white/60 hover:bg-white/20 hover:shadow-none shadow-none'
                                    : 'border-white/30 bg-white/85 text-gray-400 shadow-lg hover:bg-white hover:shadow-xl dark:border-surface-700 dark:bg-surface-800/90 dark:text-gray-500 dark:hover:bg-surface-800'
                            ]"
                        >
                            <i class="ti ti-search text-base"></i>
                            <span class="flex-1">{{ t('Search tools, pages & more...') }}</span>
                            <span class="rounded-full bg-primary-600 px-5 py-2.5 text-white">
                                <i class="ti ti-arrow-right text-base"></i>
                            </span>
                        </button>
                    </div>
                    <div class="flex-col gap-4 sm:flex-row flex justify-center gsap-cta">
                        <button v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            @click="handlePrimaryCtaClick"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'lg'))]"
                            class="inline-flex w-full items-center justify-center gap-3 font-black transition-all hover:-translate-y-1 sm:w-auto"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </button>
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
                        :class="asBoolean(props.section.config.show_stats_separator, true) ? (isBackgroundDark && !gradientEnabled ? 'mt-24 border-t border-white/20 pt-12 grid grid-cols-2 gap-8 md:grid-cols-4' : 'mt-24 border-t border-white/20 pt-12 grid grid-cols-2 gap-8 md:grid-cols-4') : 'mt-24 grid grid-cols-2 gap-8 md:grid-cols-4'"
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
        :style="[heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96'))), gradientEnabled ? { background: heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) } : {}]"
        :class="['hero-section-shell relative isolate overflow-hidden transition-colors duration-300', heroFullscreenClass, gradientEnabled ? '' : '!bg-white dark:!bg-surface-950']"
    >
        <div v-if="asBoolean(props.section.config.show_hero_background, false) && asString(props.section.config.hero_background_url)" class="absolute inset-0 z-0">
            <img v-if="asString(props.section.config.hero_background_type, 'image') === 'image'" :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" alt="" loading="lazy" class="h-full w-full object-cover">
            <video v-else :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" class="h-full w-full object-cover" autoplay muted loop playsinline></video>
        </div>
        <div v-if="(hasBackgroundMedia || (gradientEnabled && isBackgroundDark)) && asBoolean(props.section.config.show_hero_gradient_overlay, true)" :style="sectionOverlayStyle(Number(props.section.config.overlay_opacity) || undefined)" class="absolute inset-0 z-10 bg-gradient-to-b from-black/70 via-black/45 to-black/25"></div>
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div>
                    <div v-if="asString(props.section.config.trust_badge_text)" class="mb-6 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-widest shadow-sm backdrop-blur-md"
                        :class="isDarkGradientSelected ? 'border-white/30 bg-transparent text-white' : (isBackgroundDark ? 'border-white/30 bg-white/10 text-white' : 'border-white/30 bg-white/60 text-gray-900 dark:border-surface-700 dark:bg-surface-800/85 dark:text-gray-300')"
                    >
                        <span class="h-2 w-2 rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 :class="['mb-6 text-4xl font-black leading-[1.15] tracking-tight md:text-6xl lg:text-7xl gsap-heading', gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? '!text-white' : 'text-gray-900 dark:text-white')]">
                        <template v-if="showTypewriter">
                            <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                            &nbsp;<span class="inline-block min-w-[2ch]" :class="accentColorClass">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                        </template>
                        <template v-else-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="accentColorClass">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p :class="['mb-10 max-w-xl text-lg leading-relaxed gsap-subtext', gradientEnabled ? heroGradientTextDarkClass : (isBackgroundDark ? 'text-white/80' : 'text-gray-600 dark:text-gray-400')]">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div v-if="asBoolean(props.section.config.show_search_box, true)" class="mb-8 w-full max-w-lg">
                        <button type="button" @click="openCommandPalette()"
                            :class="[
                                'flex w-full items-center gap-3 rounded-full border px-5 py-3 text-left text-sm backdrop-blur-sm transition-all',
                                isDarkGradientSelected
                                    ? 'border-white/20 bg-white/10 text-white/60 hover:bg-white/20 hover:shadow-none shadow-none'
                                    : (isBackgroundDark ? 'border-white/20 bg-white/15 text-white/60 hover:bg-white/25 shadow-lg hover:shadow-xl' : 'border-white/30 bg-white/90 text-gray-400 hover:bg-white hover:shadow-xl dark:border-surface-700 dark:bg-surface-800/90 dark:text-gray-500 dark:hover:bg-surface-800')
                            ]"
                        >
                            <i class="ti ti-search text-base"></i>
                            <span class="flex-1">{{ t('Search tools, pages & more...') }}</span>
                            <span class="rounded-full bg-primary-600 px-5 py-2.5 text-white">
                                <i class="ti ti-arrow-right text-base"></i>
                            </span>
                        </button>
                    </div>
                    <div class="flex gap-4 gsap-cta">
                        <button v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            @click="handlePrimaryCtaClick"
                            :class="[heroButtonClass(isBackgroundDark ? asString(props.section.config.primary_cta_style, 'outline') : asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                            class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </button>
                        <Link v-if="asBoolean(props.section.config.show_secondary_cta, true) && asString(props.section.config.secondary_cta_text) && checkAccessLevel(asString(props.section.config.secondary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.secondary_cta_link, '/pricing')"
                            :class="[heroButtonClass(asString(props.section.config.secondary_cta_style, 'outline')), heroButtonShapeClass(asString(props.section.config.secondary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.secondary_cta_size, 'sm'))]"
                            class="inline-flex items-center gap-2 font-black transition-all"
                        >
                            <i v-if="asString(props.section.config.secondary_cta_icon)"
                                :class="[asString(props.section.config.secondary_cta_icon), asString(props.section.config.secondary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.secondary_cta_text) }}
                        </Link>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 gsap-image">
                    <template v-if="toolsGridItems.length > 0">
                        <Link v-for="tool in toolsGridItems" :key="tool.slug" :href="`/ai-tools/${tool.slug}`"
                            :class="['rounded-2xl border p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md', isBackgroundDark ? 'border-white/20 bg-white/10 hover:bg-white/20' : 'border-gray-200 bg-white hover:border-primary-200 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-600']"
                        >
                            <div :class="['mb-4 flex h-12 w-12 items-center justify-center rounded-xl', isBackgroundDark ? 'bg-white/20 text-white' : 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400']">
                                <i :class="[tool.icon || 'ti ti-apps', 'text-2xl']"></i>
                            </div>
                            <p :class="['text-sm font-bold', isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white']">{{ tool.name }}</p>
                            <p :class="['mt-1 text-xs line-clamp-2', isBackgroundDark ? 'text-white/60' : 'text-gray-500 dark:text-gray-400']">{{ tool.description || t('Powerful AI capabilities') }}</p>
                        </Link>
                    </template>
                    <template v-else>
                        <div v-for="i in 4" :key="i" :class="['rounded-2xl border p-6 shadow-sm', isBackgroundDark ? 'border-white/20 bg-white/10' : 'border-gray-200 bg-white dark:border-surface-700 dark:bg-surface-800']">
                            <div :class="['mb-4 flex h-12 w-12 items-center justify-center rounded-xl', isBackgroundDark ? 'bg-white/20 text-white' : 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400']">
                                <i class="ti ti-apps text-2xl"></i>
                            </div>
                            <p :class="['text-sm font-bold', isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white']">{{ t('AI Tool') }} {{ i }}</p>
                            <p :class="['mt-1 text-xs', isBackgroundDark ? 'text-white/60' : 'text-gray-500 dark:text-gray-400']">{{ t('Powerful AI capabilities') }}</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>

    <!-- split-gradient -->
    <section v-else-if="heroLayoutVariant === 'split-gradient'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="[heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96'))), gradientEnabled ? { background: heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) } : {}]"
        :class="['hero-section-shell relative isolate overflow-hidden transition-colors duration-300', heroFullscreenClass, gradientEnabled ? '' : '!bg-white dark:!bg-surface-950']"
    >
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6 py-24">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div class="text-left">
                    <div v-if="asString(props.section.config.trust_badge_text)" class="mb-6 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-widest shadow-sm backdrop-blur-md"
                        :class="isDarkGradientSelected ? 'border-white/30 bg-transparent text-white' : (isBackgroundDark ? 'border-white/30 bg-white/10 text-white' : 'border-white/30 bg-white/60 text-gray-900 dark:border-surface-700 dark:bg-surface-800/85 dark:text-gray-300')"
                    >
                        <span class="h-2 w-2 rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 :class="['mb-6 text-4xl font-black leading-[1.15] tracking-tight md:text-6xl lg:text-7xl gsap-heading', gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? '!text-white' : 'text-gray-900 dark:text-white')]">
                        <template v-if="showTypewriter">
                            <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                            &nbsp;<span class="inline-block min-w-[2ch]" :class="accentColorClass">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                        </template>
                        <template v-else-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="accentColorClass">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mb-10 max-w-xl text-lg leading-relaxed gsap-subtext']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <p v-else :class="['mb-10 max-w-xl text-lg leading-relaxed gsap-subtext', isBackgroundDark ? 'text-white/80' : 'text-gray-600 dark:text-gray-400']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div v-if="asBoolean(props.section.config.show_search_box, true)" class="mb-8 w-full max-w-lg">
                        <button type="button" @click="openCommandPalette()"
                            :class="[
                                'flex w-full items-center gap-3 rounded-full border px-5 py-3 text-left text-sm backdrop-blur-sm transition-all',
                                isDarkGradientSelected
                                    ? 'border-white/20 bg-white/10 text-white/60 hover:bg-white/20 hover:shadow-none shadow-none'
                                    : 'border-white/30 bg-white/85 text-gray-400 shadow-lg hover:bg-white hover:shadow-xl dark:border-surface-700 dark:bg-surface-800/90 dark:text-gray-500 dark:hover:bg-surface-800'
                            ]"
                        >
                            <i class="ti ti-search text-base"></i>
                            <span class="flex-1">{{ t('Search tools, pages & more...') }}</span>
                            <span class="rounded-full bg-primary-600 px-5 py-2.5 text-white">
                                <i class="ti ti-arrow-right text-base"></i>
                            </span>
                        </button>
                    </div>
                    <div class="flex gap-4 gsap-cta">
                        <button v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            @click="handlePrimaryCtaClick"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'white')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                            class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </button>
                        <Link v-if="asBoolean(props.section.config.show_secondary_cta, true) && asString(props.section.config.secondary_cta_text) && checkAccessLevel(asString(props.section.config.secondary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.secondary_cta_link, '/pricing')"
                            :class="[heroButtonClass(asString(props.section.config.secondary_cta_style, 'outline')), heroButtonShapeClass(asString(props.section.config.secondary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.secondary_cta_size, 'md'))]"
                            class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.secondary_cta_icon)"
                                :class="[asString(props.section.config.secondary_cta_icon), asString(props.section.config.secondary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.secondary_cta_text) }}
                        </Link>
                    </div>
                </div>
                <div v-if="asString(props.section.config.hero_split_image_url)" class="flex items-center justify-center gsap-image">
                    <img :src="resolveMediaUrl(asString(props.section.config.hero_split_image_url))" alt="" loading="lazy" class="h-auto w-full max-w-lg rounded-2xl shadow-2xl" />
                </div>
                <div v-else-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0" :class="['rounded-2xl border p-8 shadow-sm backdrop-blur-sm gsap-image', isBackgroundDark ? 'border-white/20 bg-white/10' : 'border-gray-200/60 bg-white/70 dark:border-surface-700/60 dark:bg-surface-800/70']">
                    <div class="grid grid-cols-2 gap-6">
                        <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                            <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                            <p v-else :class="isBackgroundDark ? 'text-3xl font-black text-white' : 'text-3xl font-black text-gray-900 dark:text-white'">{{ stat.number }}</p>
                            <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                            <p v-else :class="isBackgroundDark ? 'mt-1 text-xs font-black uppercase tracking-widest text-white/60' : 'mt-1 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400'">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom stats card (rendered only when split image is present and show_stats is enabled) -->
            <div v-if="asString(props.section.config.hero_split_image_url) && asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0"
                class="mt-16"
            >
                <div :class="['rounded-2xl border p-8 shadow-sm backdrop-blur-sm', isBackgroundDark ? 'border-white/20 bg-white/10' : 'border-gray-200/60 bg-white/70 dark:border-surface-700/60 dark:bg-surface-800/70']">
                    <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
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
        :style="[heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '48'))), gradientEnabled ? { background: heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) } : {}]"
        :class="[
            heroSectionHeightClass(asString(props.section.config.hero_section_height, 'default')),
            heroFullscreenClass,
            sectionVisibilityClass(asString(props.section.config.visibility, 'all')),
            'hero-section-shell relative isolate overflow-hidden transition-colors duration-300',
            gradientEnabled ? '' : '!bg-white dark:!bg-surface-950'
        ]"
    >
        <div v-if="asBoolean(props.section.config.show_hero_background, false) && asString(props.section.config.hero_background_url)" class="absolute inset-0 z-0">
            <img v-if="asString(props.section.config.hero_background_type, 'image') === 'image'" :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" alt="" loading="lazy" class="h-full w-full object-cover">
            <video v-else :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" class="h-full w-full object-cover" autoplay muted loop playsinline></video>
        </div>
        <div v-if="(hasBackgroundMedia || (gradientEnabled && isBackgroundDark)) && asBoolean(props.section.config.show_hero_gradient_overlay, true)" :style="sectionOverlayStyle(Number(props.section.config.overlay_opacity) || undefined)" class="absolute inset-0 z-10 bg-gradient-to-b from-black/70 via-black/45 to-black/25"></div>
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <div class="flex flex-col items-center text-center">
                <div class="max-w-3xl">
                    <div v-if="asString(props.section.config.trust_badge_text)"
                        class="mb-8 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-black uppercase tracking-widest shadow-lg backdrop-blur-md"
                        :class="isDarkGradientSelected ? 'border-white/30 bg-transparent text-white' : (isBackgroundDark ? 'border-white/30 bg-white/10 text-white' : 'border-white/30 bg-white/60 text-gray-900 dark:border-surface-700 dark:bg-surface-800/60 dark:text-white')"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 :class="[heroHeadingSizeClass(asString(props.section.config.hero_heading_size, 'lg')), 'font-black leading-[1.15] tracking-tight mb-6 gsap-heading', gradientEnabled ? heroGradientTextClass : heroColorClass(effectiveHeadingColor)]">
                        <template v-if="showTypewriter">
                            <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                            &nbsp;<span class="inline-block min-w-[2ch]" :class="accentColorClass">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                        </template>
                        <template v-else-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="accentColorClass">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mx-auto mb-10 max-w-2xl text-lg font-medium leading-relaxed md:text-xl gsap-subtext']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <p v-else :class="isBackgroundDark ? 'mx-auto mb-10 max-w-2xl text-lg font-medium leading-relaxed text-white/70 md:text-xl gsap-subtext' : 'mx-auto mb-10 max-w-2xl text-lg font-medium leading-relaxed text-gray-600 md:text-xl dark:text-gray-400 gsap-subtext'">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div v-if="asBoolean(props.section.config.show_search_box, true)" class="mx-auto mb-8 w-full max-w-lg">
                        <button type="button" @click="openCommandPalette()"
                            :class="[
                                'flex w-full items-center gap-3 rounded-full border px-5 py-3 text-left text-sm backdrop-blur-sm transition-all',
                                isDarkGradientSelected
                                    ? 'border-white/20 bg-white/10 text-white/60 hover:bg-white/20 hover:shadow-none shadow-none'
                                    : 'border-white/30 bg-white/85 text-gray-400 shadow-lg hover:bg-white hover:shadow-xl dark:border-surface-700 dark:bg-surface-800/90 dark:text-gray-500 dark:hover:bg-surface-800'
                            ]"
                        >
                            <i class="ti ti-search text-base"></i>
                            <span class="flex-1">{{ t('Search tools, pages & more...') }}</span>
                            <span class="rounded-full bg-primary-600 px-5 py-2.5 text-white">
                                <i class="ti ti-arrow-right text-base"></i>
                            </span>
                        </button>
                    </div>
                    <div class="flex-col gap-4 sm:flex-row flex items-center justify-center gsap-cta">
                        <button v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            @click="handlePrimaryCtaClick"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'lg'))]"
                            class="inline-flex w-full items-center justify-center gap-3 font-black transition-all hover:-translate-y-1 sm:w-auto"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </button>
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
                </div>

                <!-- Stats row -->
                <div v-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0"
                    :class="asBoolean(props.section.config.show_stats_separator, true) ? (isBackgroundDark && !gradientEnabled ? 'mt-20 border-t border-white/20 pt-12 grid grid-cols-2 gap-8 md:grid-cols-4 w-full max-w-3xl' : 'mt-20 border-t border-white/20 pt-12 grid grid-cols-2 gap-8 md:grid-cols-4 w-full max-w-3xl') : 'mt-20 grid grid-cols-2 gap-8 md:grid-cols-4 w-full max-w-3xl'"
                >
                    <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                        <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                        <p v-else :class="isBackgroundDark ? 'text-3xl font-black text-white' : 'text-3xl font-black text-gray-900 dark:text-white'">{{ stat.number }}</p>
                        <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                        <p v-else :class="isBackgroundDark ? 'mt-1 text-xs font-black uppercase tracking-widest text-white/60' : 'mt-1 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400'">{{ stat.label }}</p>
                    </div>
                </div>

                <!-- Dashboard preview -->
                <div class="mt-16 w-full max-w-5xl gsap-image">
                    <div class="overflow-hidden rounded-2xl shadow-2xl ring-1 ring-black/5">
                        <div class="flex items-center gap-1.5 border-b border-gray-100 bg-white px-5 py-3.5 dark:border-surface-700 dark:bg-surface-800">
                            <span class="h-3 w-3 rounded-full bg-red-400"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                            <div class="ml-3 h-2.5 w-48 rounded-full bg-gray-100 dark:bg-surface-700"></div>
                        </div>
                        <div v-if="asString(props.section.config.hero_split_image_url)" class="bg-white dark:bg-surface-900">
                            <img :src="resolveMediaUrl(asString(props.section.config.hero_split_image_url))" alt="" loading="lazy" class="w-full" />
                        </div>
                        <div v-else class="bg-white p-8 dark:bg-surface-900">
                            <!-- Built-in dashboard mockup -->
                            <div class="mb-6 grid grid-cols-3 gap-4">
                                <div class="rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 p-5 text-white">
                                    <p class="text-xs font-medium opacity-80">{{ t('Revenue') }}</p>
                                    <p class="mt-1 text-2xl font-bold">$128.4K</p>
                                    <p class="mt-1 text-xs font-medium opacity-60">{{ t('+12.5% vs last month') }}</p>
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-violet-400 to-purple-600 p-5 text-white">
                                    <p class="text-xs font-medium opacity-80">{{ t('Users') }}</p>
                                    <p class="mt-1 text-2xl font-bold">52.8K</p>
                                    <p class="mt-1 text-xs font-medium opacity-60">{{ t('+8.3% vs last month') }}</p>
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-orange-400 to-rose-500 p-5 text-white">
                                    <p class="text-xs font-medium opacity-80">{{ t('Active') }}</p>
                                    <p class="mt-1 text-2xl font-bold">98.2%</p>
                                    <p class="mt-1 text-xs font-medium opacity-60">{{ t('+0.4% vs last month') }}</p>
                                </div>
                            </div>
                            <div class="mb-6 grid grid-cols-2 gap-4">
                                <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-700">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Monthly Growth') }}</p>
                                        <span class="text-xs font-bold text-emerald-500">+18.2%</span>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-surface-700"><div class="h-2 w-4/5 rounded-full bg-gradient-to-r from-primary-400 to-primary-600"></div></div>
                                        <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-surface-700"><div class="h-2 w-3/5 rounded-full bg-gradient-to-r from-primary-400 to-primary-600"></div></div>
                                        <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-surface-700"><div class="h-2 w-9/12 rounded-full bg-gradient-to-r from-primary-400 to-primary-600"></div></div>
                                        <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-surface-700"><div class="h-2 w-2/4 rounded-full bg-gradient-to-r from-primary-400 to-primary-600"></div></div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-700">
                                    <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Team Activity') }}</p>
                                    <div class="flex -space-x-2">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-primary-100 text-xs font-bold text-primary-600 dark:border-surface-800 dark:bg-primary-900/30 dark:text-primary-400">JD</div>
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-accent-100 text-xs font-bold text-accent-600 dark:border-surface-800 dark:bg-accent-900/30 dark:text-accent-400">AK</div>
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-amber-100 text-xs font-bold text-amber-600 dark:border-surface-800 dark:bg-amber-900/30 dark:text-amber-400">SM</div>
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-emerald-100 text-xs font-bold text-emerald-600 dark:border-surface-800 dark:bg-emerald-900/30 dark:text-emerald-400">+8</div>
                                    </div>
                                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ t('4 team members online') }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-3">
                                <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-surface-700"></div>
                                <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-surface-700"></div>
                                <div class="h-2 w-3/4 rounded-full bg-gray-100 dark:bg-surface-700"></div>
                                <div class="h-2 w-1/2 rounded-full bg-gray-100 dark:bg-surface-700"></div>
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
        :class="['hero-section-shell enterprise-hero relative isolate overflow-hidden transition-colors duration-300', heroFullscreenClass, gradientEnabled ? '' : '!bg-white dark:!bg-surface-950']"
    >
        <div v-if="!gradientEnabled" class="pointer-events-none absolute inset-0 z-0 opacity-[0.04] dark:opacity-[0.06]" :style="enterpriseGridStyle"></div>
        <div class="relative z-20 mx-auto w-full max-w-6xl px-6">
            <div class="mx-auto w-full max-w-2xl text-center">
                <div v-if="asString(props.section.config.trust_badge_text)"
                    :class="[
                        'mb-6 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-widest shadow-sm backdrop-blur-md',
                        isDarkGradientSelected
                            ? 'border-white/30 bg-transparent text-white'
                            : (isBackgroundDark ? 'border-white/30 bg-white/10 text-white' : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-surface-700 dark:bg-surface-800/85 dark:text-gray-300')
                    ]"
                >
                    {{ asString(props.section.config.trust_badge_text) }}
                </div>
                <h1 :class="['mb-6 text-4xl font-black leading-[1.15] tracking-tight md:text-6xl lg:text-7xl gsap-heading', gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? '!text-white' : 'text-gray-900 dark:text-white')]">
                    <template v-if="showTypewriter">
                        <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                        &nbsp;<span class="inline-block min-w-[2ch]" :class="accentColorClass">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                    </template>
                    <template v-else-if="headlineSplitLines.length">
                        <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                    </template>
                    <template v-else>
                        {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                        <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="accentColorClass">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                    </template>
                </h1>
                <div class="mx-auto !max-w-3xl">
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mb-12 text-lg leading-relaxed gsap-subtext']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <p v-else :class="['mb-12 text-lg leading-relaxed gsap-subtext', isBackgroundDark ? 'text-white/60' : 'text-gray-600 dark:text-gray-400']" >
                        {{ asString(props.section.config.subheadline) }}
                    </p>

                    <div v-if="asBoolean(props.section.config.show_search_box, true)" class="mb-8 w-full">
                        <button type="button" @click="openCommandPalette()"
                            :class="[
                                'flex w-full items-center gap-3 rounded-full border px-5 py-3 text-left text-sm backdrop-blur-sm transition-all',
                                isDarkGradientSelected
                                    ? 'border-white/20 bg-white/10 text-white/60 hover:bg-white/20 hover:shadow-none shadow-none'
                                    : 'border-white/30 bg-white/85 text-gray-400 shadow-lg hover:bg-white hover:shadow-xl dark:border-surface-700 dark:bg-surface-800/90 dark:text-gray-500 dark:hover:bg-surface-800'
                            ]"
                        >
                            <i class="ti ti-search text-base"></i>
                            <span class="flex-1">{{ t('Search tools, pages & more...') }}</span>
                            <span class="rounded-full bg-primary-600 px-4 py-2 text-white">
                                <i class="ti ti-arrow-right text-sm"></i>
                            </span>
                        </button>
                    </div>
                </div>
                <div class="flex justify-center gap-4 gsap-cta">
                    <button v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                        @click="handlePrimaryCtaClick"
                        :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                        class="inline-flex items-center gap-2 font-black transition-all hover:-translate-y-0.5"
                    >
                        <i v-if="asString(props.section.config.primary_cta_icon)"
                            :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                        ></i>
                        {{ asString(props.section.config.primary_cta_text) }}
                    </button>
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
            </div>
            <div v-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0"
                :class="asBoolean(props.section.config.show_stats_separator, true) ? ['mt-24 grid grid-cols-4 gap-8 border-t pt-12', isBackgroundDark ? 'border-white/10' : 'border-gray-100 dark:border-surface-800'] : 'mt-24 grid grid-cols-4 gap-8'">
                <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                    <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                    <p v-else :class="['text-3xl font-black', isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white']">{{ stat.number }}</p>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                    <p v-else :class="['mt-1 text-xs font-black uppercase tracking-widest', isBackgroundDark ? 'text-white/40' : 'text-gray-500 dark:text-gray-400']">{{ stat.label }}</p>
                </div>
            </div>
        </div>
        <!-- Bottom oval shape divider -->
        <div class="absolute inset-x-0 -bottom-[1px] z-10 pointer-events-none">
            <svg class="h-auto w-full fill-white dark:fill-surface-950" width="1440" height="80" viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <path d="M0 0C240 50 480 75 720 75C960 75 1200 50 1440 0V80H0V0Z" />
            </svg>
        </div>
    </section>

    <!-- featured -->
    <section v-else-if="heroLayoutVariant === 'featured'"
        data-home-hero="true"
        :id="asString(props.section.config.section_anchor) || undefined"
        :style="[heroSpacingStyle(Number(asString(props.section.config.hero_vertical_padding, '96'))), gradientEnabled ? { background: heroGradientStyle(asString(props.section.config.hero_gradient_palette), asString(props.section.config.hero_gradient_direction)) } : {}]"
        :class="[
            heroSectionHeightClass(asString(props.section.config.hero_section_height, 'default')),
            heroFullscreenClass,
            sectionVisibilityClass(asString(props.section.config.visibility, 'all')),
            'hero-section-shell display-hero relative isolate overflow-hidden transition-colors duration-300',
            gradientEnabled ? '' : '!bg-white dark:!bg-surface-950'
        ]"
    >
        <div v-if="asBoolean(props.section.config.show_hero_background, false) && asString(props.section.config.hero_background_url)" class="absolute inset-0 z-0">
            <img v-if="asString(props.section.config.hero_background_type, 'image') === 'image'" :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" alt="" loading="lazy" class="h-full w-full object-cover">
            <video v-else :src="resolveMediaUrl(asString(props.section.config.hero_background_url))" class="h-full w-full object-cover" autoplay muted loop playsinline></video>
        </div>
        <div v-if="(hasBackgroundMedia || (gradientEnabled && isBackgroundDark)) && asBoolean(props.section.config.show_hero_gradient_overlay, true)" :style="sectionOverlayStyle(Number(props.section.config.overlay_opacity) || undefined)" class="absolute inset-0 z-10 bg-gradient-to-b from-black/70 via-black/45 to-black/25"></div>
        <div class="relative z-20 mx-auto w-full max-w-7xl px-6">
            <!-- 2-column top: content (left) + categories grid (right) -->
            <div class="grid items-center gap-12 lg:grid-cols-12">
                <!-- Content (8/12) -->
                <div class="lg:col-span-8">
                    <div v-if="asString(props.section.config.trust_badge_text)"
                        class="mb-6 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-[10px] md:text-xs font-semibold uppercase tracking-widest shadow-sm backdrop-blur-md"
                        :class="isDarkGradientSelected ? 'border-white/30 bg-transparent text-white' : (isBackgroundDark ? 'border-white/30 bg-white/10 text-white' : 'border-white/30 bg-white/60 text-gray-900 dark:border-surface-700 dark:bg-surface-800/60 dark:text-white')"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-primary-500"></span>
                        {{ asString(props.section.config.trust_badge_text) }}
                    </div>
                    <h1 :class="['mb-5 text-4xl font-black leading-[1.25] tracking-tight md:text-5xl lg:text-6xl gsap-heading', gradientEnabled ? heroGradientTextClass : (isBackgroundDark ? '!text-white' : 'text-gray-900 dark:text-white')]">
                        <template v-if="showTypewriter">
                            <template v-for="(line, i) in typewriterPrefixLines" :key="'p'+i">{{ line }}<br v-if="i < typewriterPrefixLines.length - 1"></template>
                            &nbsp;<span class="inline-block min-w-[2ch]" :class="accentColorClass">{{ typewriterText }}<span class="animate-pulse">|</span></span>
                        </template>
                        <template v-else-if="headlineSplitLines.length">
                            <template v-for="(line, i) in headlineSplitLines" :key="i">{{ line }}<br v-if="i < headlineSplitLines.length - 1"></template>
                        </template>
                        <template v-else>
                            {{ headingParts(asString(props.section.config.headline, t('One Platform. Every AI Tool.')))[0] }}<br>
                            <span v-if="headingParts(asString(props.section.config.headline))[1]" :class="accentColorClass">{{ headingParts(asString(props.section.config.headline))[1] }}</span>
                        </template>
                    </h1>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mb-8 max-w-xl text-lg leading-relaxed gsap-subtext']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <p v-else :class="['mb-8 max-w-xl text-lg leading-relaxed gsap-subtext', isBackgroundDark ? 'text-white/70' : 'text-gray-600 dark:text-gray-400']">
                        {{ asString(props.section.config.subheadline) }}
                    </p>
                    <div v-if="asBoolean(props.section.config.show_search_box, true)" class="mb-8 w-full max-w-lg">
                        <button type="button" @click="openCommandPalette()"
                            :class="[
                                'flex w-full items-center gap-3 rounded-full border px-5 py-3 text-left text-sm backdrop-blur-sm transition-all',
                                isDarkGradientSelected
                                    ? 'border-white/20 bg-white/10 text-white/60 hover:bg-white/20 hover:shadow-none shadow-none'
                                    : 'border-white/30 bg-white/85 text-gray-400 shadow-lg hover:bg-white hover:shadow-xl dark:border-surface-700 dark:bg-surface-800/90 dark:text-gray-500 dark:hover:bg-surface-800'
                            ]"
                        >
                            <i class="ti ti-search text-base"></i>
                            <span class="flex-1">{{ t('Search tools, pages & more...') }}</span>
                            <span class="shrink-0 rounded-full bg-primary text-white px-3 py-1.5">
                                <i class="ti ti-arrow-right text-sm"></i>
                            </span>
                        </button>
                    </div>
                    <div class="flex flex-col justify-center sm:flex-row sm:justify-start gap-4 gsap-cta">
                        <button v-if="asBoolean(props.section.config.show_primary_cta, true) && asString(props.section.config.primary_cta_text) && checkAccessLevel(asString(props.section.config.primary_cta_access_level, 'all'))"
                            @click="handlePrimaryCtaClick"
                            :class="[heroButtonClass(asString(props.section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(props.section.config.primary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.primary_cta_size, 'md'))]"
                            class="inline-flex items-center justify-center gap-2 font-black transition-all hover:-translate-y-0.5"
                        >
                            <i v-if="asString(props.section.config.primary_cta_icon)"
                                :class="[asString(props.section.config.primary_cta_icon), asString(props.section.config.primary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.primary_cta_text) }}
                        </button>
                        <Link v-if="asBoolean(props.section.config.show_secondary_cta, true) && asString(props.section.config.secondary_cta_text) && checkAccessLevel(asString(props.section.config.secondary_cta_access_level, 'all'))"
                            :href="asString(props.section.config.secondary_cta_link, '/pricing')"
                            :class="[heroButtonClass(asString(props.section.config.secondary_cta_style, 'outline')), heroButtonShapeClass(asString(props.section.config.secondary_cta_shape, 'rounded_xl')), heroButtonSizeClass(asString(props.section.config.secondary_cta_size, 'md'))]"
                            class="inline-flex items-center justify-center gap-2 font-black transition-all"
                        >
                            <i v-if="asString(props.section.config.secondary_cta_icon)"
                                :class="[asString(props.section.config.secondary_cta_icon), asString(props.section.config.secondary_cta_icon_position, 'left') === 'right' ? 'order-1' : '']"
                            ></i>
                            {{ asString(props.section.config.secondary_cta_text) }}
                        </Link>
                    </div>
                </div>

                <!-- Categories grid (4/12) -->
                <div v-if="displayCategories.length > 0" class="lg:col-span-4 gsap-image">
                    <div class="grid grid-cols-2 gap-3">
                        <Link v-for="cat in displayCategories" :key="cat.name" :href="`/ai-tools?category=${encodeURIComponent(cat.name)}`"
                            class="group flex flex-col items-center justify-center gap-2 rounded-2xl border p-5 text-center transition-all hover:-translate-y-0.5 hover:shadow-lg"
                            :class="isBackgroundDark ? 'border-white/15 bg-white/5 hover:bg-white/10' : 'border-gray-200 bg-white/80 hover:border-primary-200 hover:bg-white dark:border-surface-700 dark:bg-surface-800/80 dark:hover:border-primary-600 dark:hover:bg-surface-800'"
                        >
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl text-xl"
                                :style="{ background: 'color-mix(in srgb, ' + (cat.color || 'var(--color-primary)') + ' 12%, transparent)' }"
                            >
                                <i :class="cat.icon" :style="{ color: cat.color || 'var(--color-primary)' }"></i>
                            </div>
                            <span class="text-xs font-bold" :class="isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white'">{{ cat.name }}</span>
                            <span class="text-[10px]" :class="isBackgroundDark ? 'text-white/50' : 'text-gray-400'">{{ cat.count }} {{ t('tools') }}</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Auto-scroll featured tools marquee -->
            <div v-if="displayTools.length > 0" class="mt-16">
                <div class="display-marquee overflow-hidden py-2">
                    <div class="display-marquee-inner flex gap-4" style="--display-marquee-count: 10;">
                        <template v-for="i in 2" :key="i">
                            <Link v-for="tool in displayTools" :key="tool.slug + '-' + i" :href="`/ai-tools/${tool.slug}`"
                                class="flex shrink-0 items-center gap-3 rounded-xl border px-4 py-3 transition-all hover:-translate-y-0.5 hover:shadow-md"
                                :class="isBackgroundDark ? 'border-white/10 bg-white/5 hover:bg-white/10' : 'border-gray-200 bg-white hover:border-primary-200 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-600'"
                            >
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm"
                                    :style="tool.color ? { background: tool.color + '20', color: tool.color } : { background: 'color-mix(in srgb, var(--color-primary) 20%, transparent)', color: 'var(--color-primary)' }"
                                >
                                    <i :class="tool.icon || 'ti ti-apps'"></i>
                                </div>
                                <span class="whitespace-nowrap text-sm font-medium" :class="isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white'">{{ tool.name }}</span>
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Stats row -->
            <div v-if="asBoolean(props.section.config.show_stats, true) && asItems(props.section.config.stats).length > 0"
                :class="asBoolean(props.section.config.show_stats_separator, true) ? ['mt-16 border-t pt-10 grid grid-cols-2 gap-8 md:grid-cols-4', isBackgroundDark && !gradientEnabled ? 'border-white/10' : 'border-white/20'] : 'mt-16 grid grid-cols-2 gap-8 md:grid-cols-4'"
            >
                <div v-for="stat in asItems(props.section.config.stats)" :key="`${stat.number}_${stat.label}`" class="text-center">
                    <p v-if="gradientEnabled" :class="[heroGradientTextClass, 'text-3xl font-black']">{{ stat.number }}</p>
                    <p v-else :class="['text-3xl font-black', isBackgroundDark ? 'text-white' : 'text-gray-900 dark:text-white']">{{ stat.number }}</p>
                    <p v-if="gradientEnabled" :class="[heroGradientTextDarkClass, 'mt-1 text-xs font-black uppercase tracking-widest']">{{ stat.label }}</p>
                    <p v-else :class="['mt-1 text-xs font-black uppercase tracking-widest', isBackgroundDark ? 'text-white/50' : 'text-gray-500 dark:text-gray-400']">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Video Popup -->
    <Teleport to="body">
        <div v-if="showVideoPopup && primaryCtaVideoId"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            @click.self="showVideoPopup = false"
        >
            <div class="relative w-full max-w-5xl rounded-2xl bg-black shadow-2xl">
                <button @click="showVideoPopup = false"
                    class="absolute right-2 top-2 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white transition-all hover:bg-black/80 hover:scale-110"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <div class="aspect-video w-full">
                    <iframe
                        :src="`https://www.youtube.com/embed/${primaryCtaVideoId}?autoplay=1&rel=0&mute=1`"
                        class="h-full w-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>
            </div>
        </div>
    </Teleport>
    </div>
</template>

<style scoped>
.hero-section-shell {
    padding-top: var(--hero-padding-top, 0px);
    padding-bottom: var(--hero-padding-bottom, 0px);
}

@media (max-width: 767px) {
    .hero-section-shell {
        padding-top: var(--hero-padding-top-mobile, var(--hero-padding-top, 0px)) !important;
    }
}

.display-marquee {
    mask-image: linear-gradient(to right, transparent 0%, black 5%, black 95%, transparent 100%);
    -webkit-mask-image: linear-gradient(to right, transparent 0%, black 5%, black 95%, transparent 100%);
}

.display-marquee-inner {
    width: max-content;
    animation: displayMarqueeScroll 40s linear infinite;
}

@keyframes displayMarqueeScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
</style>
