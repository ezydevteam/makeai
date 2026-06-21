<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }

const props = defineProps<{ section: HomepageSection; sectionTitle?: (section: HomepageSection, fallback: string) => string; sectionSubtitle?: (section: HomepageSection) => string }>()
const { t } = useTranslate()

const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []

const resolveMediaUrl = (path?: string | null): string => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path
    return `/storage/${path}`
}

const heroColorClass = (color: string, tone: 'heading' | 'subheading' = 'heading'): string => {
    const h: Record<string, string> = { dark: 'text-gray-900 dark:text-white', light: 'text-white', primary: 'text-primary-600 dark:text-primary-400', white: 'text-white' }
    const s: Record<string, string> = { dark: 'text-gray-600 dark:text-gray-400', light: 'text-white/70', primary: 'text-primary-500/80 dark:text-primary-300/80', white: 'text-white/70' }
    return tone === 'heading' ? (h[color] ?? h.dark) : (s[color] ?? s.light)
}

const featureGridClass = (layout: string): string => ({ '2-column': 'lg:grid-cols-2', '3-column': 'lg:grid-cols-3', '4-column': 'lg:grid-cols-4' }[layout] ?? 'lg:grid-cols-3')
const featureCardClass = (style: string): string => ({
    simple: 'relative h-full overflow-hidden rounded-[1.5rem] border border-transparent bg-white/80 shadow-[0_10px_28px_rgba(15,23,42,0.05)] backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-[0_18px_45px_rgba(31,117,254,0.08)] dark:bg-surface-800/70 dark:hover:bg-surface-800',
    bordered: 'relative h-full overflow-hidden rounded-[2rem] border-2 border-slate-200/90 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-800',
    image_focus: 'relative h-full overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.14)] dark:border-surface-700/70 dark:bg-surface-800',
}[style] ?? 'relative h-full overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700/70 dark:bg-surface-800')
const featureCardBodyClass = (style: string): string => ({ simple: 'relative z-10 px-7 pb-7 pt-2', bordered: 'relative z-10 p-8', image_focus: 'relative z-10 p-8' }[style] ?? 'relative z-10 p-8')
const featureCardMediaClass = (style: string): string => ({
    simple: 'mx-auto mb-8 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-none dark:bg-primary-500/10 dark:text-primary-300',
    bordered: 'mx-auto mb-8 flex h-16 w-16 items-center justify-center rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white text-primary-600 shadow-[0_10px_24px_rgba(31,117,254,0.14)] dark:border-primary-500/20 dark:from-primary-500/15 dark:to-surface-800 dark:text-primary-300',
    image_focus: 'w-full h-56 rounded-none mb-0 flex items-center justify-center',
}[style] ?? 'mx-auto mb-8 flex h-16 w-16 items-center justify-center rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50 to-white text-primary-600 shadow-[0_10px_24px_rgba(31,117,254,0.14)] dark:border-primary-500/20 dark:from-primary-500/15 dark:to-surface-800 dark:text-primary-300')
const featureCardImageClass = (style: string): string => ({ simple: 'w-full h-32 object-cover mb-8', bordered: 'w-full h-32 object-cover mb-8', image_focus: 'w-full h-56 object-cover' }[style] ?? 'w-full h-32 object-cover mb-8')
const heroButtonClass = (style: string): string => ({
    primary_filled: 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700',
    outline: 'border-2 border-white/40 bg-transparent text-white hover:bg-white/10 dark:border-white/30 dark:bg-transparent dark:text-white dark:hover:bg-white/10',
    dark: 'bg-gray-900 text-white shadow-2xl shadow-gray-900/20 hover:bg-gray-800',
    gradient: 'bg-gradient-to-r from-primary-600 via-violet-600 to-primary-500 text-white shadow-2xl shadow-primary-600/20 hover:opacity-95',
    white: 'bg-white text-gray-900 shadow-xl hover:bg-gray-100',
}[style] ?? 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')

const isExternalUrl = (url: string): boolean => url.startsWith('http://') || url.startsWith('https://')
const sectionVisibilityClass = (v: string): string => ({ all: '', desktop: 'hidden lg:block', tablet: 'hidden md:block lg:hidden', mobile: 'block md:hidden', desktop_tablet: 'hidden md:block', tablet_mobile: 'block lg:hidden' }[v] ?? '')
</script>

<template>
    <section
        :style="{ '--feature-section-padding': `${Number(asString(section.config.feature_vertical_padding, 96))}px` }"
        :class="[sectionVisibilityClass(asString(section.config.visibility, 'all'))]"
        class="bg-[var(--color-bg)] py-[var(--feature-section-padding)] transition-colors duration-300"
    >
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-20 text-center">
                <h2 :class="heroColorClass(asString(section.config.heading_color, 'dark'))" class="mb-4 text-3xl font-black md:text-5xl">{{ section.config.title || t('Supercharge your workflow') }}</h2>
                <p v-if="asString(section.config.subtitle)" :class="heroColorClass(asString(section.config.subheading_color, 'light'), 'subheading')" class="font-medium">{{ asString(section.config.subtitle) }}</p>
            </div>
            <div :class="[featureGridClass(asString(section.config.layout, '3-column'))]" class="grid grid-cols-1 gap-8 md:grid-cols-2">
                <component
                    v-for="item in asItems(section.config.items)"
                    :is="String(item.link_url) ? (isExternalUrl(String(item.link_url)) ? 'a' : Link) : 'div'"
                    :key="`${item.title}_${item.icon}`"
                    :href="String(item.link_url || '') || undefined"
                    :target="String(item.link_url) && isExternalUrl(String(item.link_url)) ? '_blank' : undefined"
                    :rel="String(item.link_url) && isExternalUrl(String(item.link_url)) ? 'noopener noreferrer' : undefined"
                    :class="[featureCardClass(asString(section.config.card_style, 'bordered')), 'group block h-full text-left']"
                >
                    <div v-if="asString(section.config.card_style, 'bordered') !== 'image_focus'" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-violet-500 to-secondary-500 opacity-70"></div>
                    <div class="absolute -right-14 -top-14 h-32 w-32 rounded-full bg-primary-500/10 blur-3xl transition-transform duration-300 group-hover:scale-125"></div>
                    <img v-if="item.image_url" :src="resolveMediaUrl(String(item.image_url))" alt="" loading="lazy" :class="featureCardImageClass(asString(section.config.card_style, 'bordered'))">
                    <div :class="featureCardBodyClass(asString(section.config.card_style, 'bordered'))">
                        <div v-if="!item.image_url" :class="[featureCardMediaClass(asString(section.config.card_style, 'bordered')), 'mx-0 mb-6 transition-transform duration-300 group-hover:scale-105']">
                            <i :class="String(item.icon || 'ti ti-sparkles')" class="block shrink-0 text-2xl leading-none"></i>
                        </div>
                        <h3 class="mb-3 text-[1.15rem] font-black tracking-tight text-gray-900 dark:text-white">{{ item.title }}</h3>
                        <p class="text-sm font-medium leading-7 text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                        <div v-if="String(item.link_url) && asString(section.config.learn_more_text)" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 transition group-hover:gap-3 dark:text-primary-400">
                            {{ asString(section.config.learn_more_text) }}
                            <i class="ti ti-arrow-right text-base leading-none"></i>
                        </div>
                    </div>
                </component>
            </div>
            <div v-if="asString(section.config.button_text) && asString(section.config.button_link)" class="mt-12 text-center">
                <Link :href="asString(section.config.button_link)" :class="heroButtonClass(asString(section.config.button_style, 'primary_filled'))" class="inline-flex items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors">
                    <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'block shrink-0 text-lg leading-none']"></i>
                    {{ asString(section.config.button_text) }}
                </Link>
            </div>
        </div>
    </section>
</template>
