<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const page = usePage()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback

const resolveMediaUrl = (path?: string | null): string => { if (!path) return ''; if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path; return `/storage/${path}` }
const ctaBannerWidthClass = (width: string): string => ({ contained: 'max-w-6xl', wide: 'max-w-7xl', full: 'max-w-none' }[width] ?? 'max-w-6xl')
const ctaBannerSurfaceClass = (style: string): string => ({
    'gradient-1': 'bg-gradient-to-r from-primary-600 to-violet-600 text-white shadow-2xl shadow-primary-600/20',
    'gradient-2': 'bg-gradient-to-r from-secondary-600 to-primary-600 text-white shadow-2xl shadow-secondary-600/20',
    'gradient-3': 'bg-gradient-to-br from-primary-700 via-sky-600 to-violet-700 text-white shadow-2xl shadow-violet-700/20',
    primary_light: 'bg-primary-50 text-gray-900 border border-primary-100 shadow-xl shadow-primary-500/10 dark:bg-primary-900/20 dark:border-primary-800 dark:text-white',
    green_light: 'bg-green-50 text-gray-900 border border-green-100 shadow-xl shadow-green-500/10 dark:bg-green-900/20 dark:border-green-800 dark:text-white',
    white: 'bg-white text-gray-900 border border-gray-100 shadow-xl shadow-gray-900/5 dark:bg-surface-900 dark:border-surface-700 dark:text-white',
    light: 'bg-gray-50 text-gray-900 border border-gray-100 shadow-xl shadow-gray-900/5 dark:bg-surface-800 dark:border-surface-700 dark:text-white',
}[style] ?? 'bg-gradient-to-r from-primary-600 to-violet-600 text-white shadow-2xl shadow-primary-600/20')
const ctaBannerImageOverlayClass = (style: string): string => ({ 'gradient-1': 'bg-slate-950/45', 'gradient-2': 'bg-slate-950/45', 'gradient-3': 'bg-slate-950/50', primary_light: 'bg-primary-500/20', green_light: 'bg-green-500/20', white: 'bg-white/65', light: 'bg-white/55' }[style] ?? 'bg-slate-950/45')
const ctaBannerIsLightSurface = (style: string): boolean => ['primary_light', 'green_light', 'white', 'light'].includes(style)
const heroButtonClass = (style: string): string => ({
    primary_filled: 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    outline: 'border-2 border-white/40 bg-transparent !text-white hover:bg-white/10 dark:border-white/30 dark:bg-transparent dark:!text-white dark:hover:bg-white/10',
    dark: 'bg-gray-900 !text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800',
    gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 !text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
    white: 'bg-white/15 backdrop-blur-sm border border-white/25 !text-white shadow-xl hover:bg-white/25',
}[style] ?? 'bg-primary-600 !text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')
const sectionOverlayStyle = (opacity?: number): Record<string, string> => ({ opacity: String(Math.max(0, Math.min(100, Number(opacity || 45))) / 100) })
</script>

<template>
    <section class="bg-white py-24 dark:bg-surface-950">
        <div :class="ctaBannerWidthClass(asString(section.config.width, 'contained'))" class="mx-auto px-6">
            <div :class="ctaBannerSurfaceClass(asString(section.config.background_style, 'gradient-1'))" class="relative isolate overflow-hidden rounded-[2.5rem] p-10 text-center md:p-16">
                <div v-if="asString(section.config.background_image_url)" class="absolute inset-0 z-0 overflow-hidden">
                    <img :src="resolveMediaUrl(asString(section.config.background_image_url))" alt="" loading="lazy" class="h-full w-full object-cover">
                    <div :class="ctaBannerImageOverlayClass(asString(section.config.background_style, 'gradient-1'))" :style="sectionOverlayStyle(Number(section.config.overlay_opacity) || undefined)" class="absolute inset-0"></div>
                </div>
                <div class="relative z-10">
                    <h2 class="mb-4 text-3xl font-black md:text-5xl">{{ asString(section.config.headline ?? section.config.title, t('Ready to create with AI?')) }}</h2>
                    <p v-if="asString(section.config.subheadline ?? section.config.subtitle)" class="mx-auto mb-8 max-w-2xl" :class="ctaBannerIsLightSurface(asString(section.config.background_style, 'gradient-1')) ? 'text-gray-700 dark:text-gray-200' : 'text-white/80'">
                        {{ asString(section.config.subheadline ?? section.config.subtitle) }}
                    </p>
                    <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link v-if="asString(section.config.primary_text ?? section.config.primary_cta_text)" :href="asString(section.config.primary_link ?? section.config.primary_cta_link, '/register')" :class="heroButtonClass(asString(section.config.primary_style ?? section.config.primary_cta_style, 'primary_filled'))" class="w-full rounded-2xl px-8 py-4 font-black transition-colors sm:w-auto">
                            <span class="inline-flex items-center justify-center gap-3">
                                <i v-if="asString(section.config.primary_icon ?? section.config.primary_cta_icon)" :class="[asString(section.config.primary_icon ?? section.config.primary_cta_icon), 'block shrink-0 text-lg leading-none']"></i>
                                {{ asString(section.config.primary_text ?? section.config.primary_cta_text) }}
                            </span>
                        </Link>
                        <Link v-if="asString(section.config.secondary_text ?? section.config.secondary_cta_text)" :href="asString(section.config.secondary_link ?? section.config.secondary_cta_link, '/pricing')" :class="heroButtonClass(asString(section.config.secondary_style ?? section.config.secondary_cta_style, 'outline'))" class="w-full rounded-2xl px-8 py-4 font-black transition-colors sm:w-auto">
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
