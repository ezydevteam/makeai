<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useSectionStyle } from '../composables/useSectionStyle'
import { useTheme } from '@/Composables/useTheme'
import { mediaUrl } from '@/lib/media'
const { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, sectionIconClass, sectionHeaderClass, sectionPaddingStyle, sectionVisibilityClass, sectionAnchorId } = useSectionStyle()
const { isDark } = useTheme()

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const page = usePage()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const resolveMediaUrl = (path?: string | null): string => mediaUrl(path)
const ctaBannerWidthClass = (width: string): string => ({ contained: 'max-w-6xl', wide: 'max-w-7xl', full: 'max-w-none' }[width] ?? 'max-w-6xl')
const ctaBannerSurfaceClass = (style: string): string => ({
    'default': '',
    'gradient-1': 'bg-gradient-to-r from-primary-600 to-violet-600 text-white',
    'gradient-2': 'bg-gradient-to-r from-secondary-600 to-primary-600 text-white',
    'gradient-3': 'bg-gradient-to-br from-primary-700 via-sky-600 to-violet-700 text-white',
    'gradient-4': 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 text-white',
    primary_light: 'bg-primary-50 text-gray-900 border border-primary-100 dark:bg-primary-900/20 dark:border-primary-800 dark:text-white',
    green_light: 'bg-green-50 text-gray-900 border border-green-100 dark:bg-green-900/20 dark:border-green-800 dark:text-white',
    white: 'bg-white text-gray-900 border border-gray-100 dark:bg-surface-900 dark:border-surface-700 dark:text-white',
    light: 'bg-gray-50 text-gray-900 border border-gray-100 dark:bg-surface-800 dark:border-surface-700 dark:text-white',
    transparent: 'bg-transparent text-gray-900 border border-gray-200 dark:border-surface-800 dark:text-white',
}[style] ?? '')
const ctaBannerImageOverlayClass = (style: string): string => ({ 'gradient-1': 'bg-slate-950/45', 'gradient-2': 'bg-slate-950/45', 'gradient-3': 'bg-slate-950/50', 'gradient-4': 'bg-slate-950/50', primary_light: 'bg-primary-500/20 dark:bg-slate-950/70', green_light: 'bg-green-500/20 dark:bg-slate-950/70', white: 'bg-white/65 dark:bg-slate-950/70', light: 'bg-white/55 dark:bg-slate-950/75', transparent: 'bg-white/65 dark:bg-slate-950/70' }[style] ?? 'bg-slate-950/45')
const ctaBannerIsLightSurface = (style: string): boolean => ['primary_light', 'green_light', 'white', 'light', 'transparent'].includes(style)

const heroButtonClass = (style: string): string => ({
    primary: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    primary_filled: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    dark: 'bg-gray-900 !text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800',
    purple: 'bg-violet-600 !text-white shadow-2xl shadow-violet-600/20 hover:bg-violet-700',
    gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 !text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
    red: 'bg-red-600 !text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    danger: 'bg-red-600 !text-white shadow-2xl shadow-red-600/20 hover:bg-red-700',
    green: 'bg-success-600 !text-white shadow-2xl shadow-success-600/20 hover:bg-success-700',
    success: 'bg-emerald-600 !text-white shadow-2xl shadow-emerald-600/20 hover:bg-emerald-700',
    warning: 'bg-amber-50 !text-white shadow-2xl shadow-amber-50/20 hover:bg-amber-600',
    gradient_sunset: 'bg-gradient-to-r from-orange-500 via-pink-500 to-purple-600 !text-white shadow-2xl hover:opacity-95',
    gradient_ocean: 'bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-600 !text-white shadow-2xl hover:opacity-95',
    gradient_royal: 'bg-gradient-to-r from-purple-600 via-pink-500 to-red-500 !text-white shadow-2xl hover:opacity-95',
    outline: 'border-2 border-white/40 bg-transparent !text-white hover:bg-white/10 dark:border-white/30 dark:bg-transparent dark:!text-white dark:hover:bg-white/10',
    white: 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25',
    light: 'bg-white/10 !text-white shadow-xl hover:bg-white/20',
    ghost: 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25',
}[style] ?? 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')

const heroButtonShapeClass = (shape: string): string => ({
    sharp: 'rounded-none',
    rounded: 'rounded-lg',
    rounded_xl: 'rounded-2xl',
    pill: 'rounded-full',
}[shape] ?? 'rounded-2xl')

const sectionOverlayStyle = (opacity?: number): Record<string, string> => ({ opacity: String(Math.max(0, Math.min(100, Number(opacity || 45))) / 100) })

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
const activeBgStyle = computed(() => {
    const cardBg = asString(props.section.config.card_bg_style)
    return !cardBg || cardBg === 'default'
        ? asString(props.section.config.background_style, 'gradient-1')
        : cardBg
})

const effectiveBannerClass = computed(() => {
    const style = activeBgStyle.value
    const align = titleAlignClass(asString(props.section.config.title_align, 'center'))
    const isContained = style !== 'default'
    
    if (isDark.value) {
        if (!isContained) return align
        return `${align} bg-surface-900/40 border border-surface-800/60 relative isolate overflow-hidden rounded-[2.5rem] p-10 md:p-16`
    }
    
    return [
        ctaBannerSurfaceClass(style),
        align,
        isContained ? 'relative isolate overflow-hidden rounded-[2.5rem] p-10 md:p-16' : ''
    ].filter(Boolean).join(' ')
})

const sectionRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null

onMounted(async () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  const { gsap } = await import('gsap')
  const { ScrollTrigger } = await import('gsap/ScrollTrigger')
  gsap.registerPlugin(ScrollTrigger)

  gsapCtx = gsap.context(() => {
    // Subtle scale-up entrance for the CTA banner
    const banner = sectionRef.value!.querySelector('.mx-auto > div')
    if (banner) {
      gsap.from(banner, {
        opacity: 0,
        scale: 0.95,
        y: 40,
        duration: 0.8,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: sectionRef.value,
          start: 'top 85%',
          once: true,
        },
      })
    }
  }, sectionRef.value!)
})

onUnmounted(() => gsapCtx?.revert())
</script>

<template>
    <section
        :id="sectionAnchorId(section.config.section_anchor)" ref="sectionRef" :class="[sectionVisibilityClass(asString(section.config.visibility, 'all')), isDark ? '!bg-surface-950' : sectionBgClass(asString(section.config.section_bg, 'default'))]" :style="sectionPaddingStyle(section.config.vertical_padding, '96')" class="transition-colors duration-300">
        <div :class="ctaBannerWidthClass(asString(section.config.width, 'contained'))" class="mx-auto px-6">
            <div :class="[effectiveBannerClass]">
                <div v-if="asString(section.config.background_image_url)" class="absolute inset-0 z-0 overflow-hidden">
                    <img :src="resolveMediaUrl(asString(section.config.background_image_url))" alt="" loading="lazy" class="h-full w-full object-cover">
                    <div :class="ctaBannerImageOverlayClass(activeBgStyle)" :style="sectionOverlayStyle(Number(section.config.overlay_opacity) || undefined)" class="absolute inset-0"></div>
                </div>
                <div class="relative z-10">
                    <div :class="[sectionHeaderClass(asString(section.config.title_align, 'center'))]" class="w-full">
                        <!-- Top Position Icon -->
                        <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'top'" :class="[
                            sectionIconClass(asString(section.config.icon_style, 'primary')),
                            'mb-5 h-14 w-14 text-2xl'
                        ]">
                            <i :class="asString(section.config.icon)"></i>
                        </div>
                        <div class="w-full">
                            <!-- Title Wrapper with Left Position Icon -->
                            <div :class="['flex items-center gap-3 mb-4', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'justify-center' : 'justify-start']">
                                <div v-if="asString(section.config.icon) && asString(section.config.icon_position, 'top') === 'left'" :class="[
                                    sectionIconClass(asString(section.config.icon_style, 'primary')),
                                    'h-9 w-9 text-lg shrink-0'
                                ]">
                                    <i :class="asString(section.config.icon)"></i>
                                </div>
                                <h2 :class="['font-black', titleSizeClass(asString(section.config.title_size, 'md')), isDark ? '!text-white' : (titleColorClass(asString(section.config.title_color, 'dark')) === 'text-gray-900 dark:text-white' ? '' : titleColorClass(asString(section.config.title_color, 'dark')))]">{{ asString(section.config.headline ?? section.config.title, t('Ready to create with AI?')) }}</h2>
                            </div>
                            <p v-if="asString(section.config.subheadline ?? section.config.subtitle)" :class="['max-w-2xl mb-8 font-medium', isDark ? '!text-gray-300' : (titleColorClass(asString(section.config.title_color, 'dark')) === 'text-gray-900 dark:text-white' ? (ctaBannerIsLightSurface(activeBgStyle) ? 'text-gray-700 dark:text-gray-200' : 'text-white/80') : subtitleColorClass(asString(section.config.title_color, 'dark'), activeBgStyle)), asString(section.config.title_align, 'center') === 'center' ? 'mx-auto' : '']">
                                {{ asString(section.config.subheadline ?? section.config.subtitle) }}
                            </p>
                        </div>
                    </div>
                    <div :class="['flex flex-col gap-4 sm:flex-row', titleAlignClass(asString(section.config.title_align, 'center')) === 'text-center' ? 'items-center justify-center' : 'items-start justify-start']">
                        <Link v-if="asString(section.config.primary_text ?? section.config.primary_cta_text) && checkAccessLevel(asString(section.config.primary_access_level ?? section.config.primary_cta_access_level, 'all'))" :href="asString(section.config.primary_link ?? section.config.primary_cta_link, '/register')" :class="[heroButtonClass(asString(section.config.primary_style ?? section.config.primary_cta_style, 'primary_filled')), heroButtonShapeClass(asString(section.config.primary_shape ?? section.config.primary_cta_shape, 'rounded_xl'))]" class="w-full px-8 py-4 font-black transition-colors sm:w-auto text-center">
                            <span class="inline-flex items-center justify-center gap-3">
                                <i v-if="asString(section.config.primary_icon ?? section.config.primary_cta_icon)" :class="[asString(section.config.primary_icon ?? section.config.primary_cta_icon), 'block shrink-0 text-lg leading-none']"></i>
                                {{ asString(section.config.primary_text ?? section.config.primary_cta_text) }}
                            </span>
                        </Link>
                        <Link v-if="asString(section.config.secondary_text ?? section.config.secondary_cta_text) && checkAccessLevel(asString(section.config.secondary_access_level ?? section.config.secondary_cta_access_level, 'all'))" :href="asString(section.config.secondary_link ?? section.config.secondary_cta_link, '/pricing')" :class="[heroButtonClass(asString(section.config.secondary_style ?? section.config.secondary_cta_style, 'outline')), heroButtonShapeClass(asString(section.config.secondary_shape ?? section.config.secondary_cta_shape, 'rounded_xl'))]" class="w-full px-8 py-4 font-black transition-colors sm:w-auto text-center">
                            <span class="inline-flex items-center justify-center gap-3">
                                <i v-if="asString(section.config.secondary_icon ?? section.config.secondary_cta_icon)" :class="[asString(section.config.secondary_icon ?? section.config.secondary_cta_icon), 'block shrink-0 text-lg leading-none']"></i>
                                {{ asString(section.config.secondary_text ?? section.config.secondary_cta_text) }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
