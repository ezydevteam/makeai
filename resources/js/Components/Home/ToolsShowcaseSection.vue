<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface ToolItem { slug: string; name: string; description: string; icon: string | null; color: string | null; category: string | null; usage_count: number; avg_rating: number | null; is_featured: boolean; created_at?: string }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; allTools: ToolItem[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const heroButtonClass = (style: string): string => ({ primary_filled: 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700', outline: 'border-2 border-white/40 bg-transparent text-white hover:bg-white/10 dark:border-white/30 dark:bg-transparent dark:text-white dark:hover:bg-white/10', white: 'bg-white text-gray-900 shadow-xl hover:bg-gray-100' }[style] ?? 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')
const resolveMediaUrl = (path?: string | null): string => { if (!path) return ''; if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path; return `/storage/${path}` }
const heroColorClass = (color: string, tone: 'heading' | 'subheading' = 'heading'): string => {
    const h: Record<string, string> = { dark: 'text-gray-900 dark:text-white', white: 'text-white', light: 'text-white', primary: 'text-primary-600' }
    const s: Record<string, string> = { dark: 'text-gray-600 dark:text-gray-400', white: 'text-white/70', light: 'text-white/70', primary: 'text-primary-500/80' }
    return tone === 'heading' ? (h[color] ?? h.dark) : (s[color] ?? s.light)
}
const ctaBannerWidthClass = (width: string): string => ({ contained: 'max-w-6xl', wide: 'max-w-7xl', full: 'max-w-none' }[width] ?? 'max-w-6xl')
const ctaBannerSurfaceClass = (style: string): string => ({
    'gradient-1': 'bg-gradient-to-r from-primary-600 to-violet-600 text-white shadow-2xl shadow-primary-600/20', 'gradient-2': 'bg-gradient-to-r from-secondary-600 to-primary-600 text-white shadow-2xl shadow-secondary-600/20',
    'gradient-3': 'bg-gradient-to-br from-primary-700 via-sky-600 to-violet-700 text-white shadow-2xl shadow-violet-700/20',
    primary_light: 'bg-primary-50 text-gray-900 border border-primary-100 shadow-xl shadow-primary-500/10 dark:bg-primary-900/20 dark:border-primary-800 dark:text-white',
    green_light: 'bg-green-50 text-gray-900 border border-green-100 shadow-xl shadow-green-500/10', white: 'bg-white text-gray-900 border border-gray-100 shadow-xl shadow-gray-900/5 dark:bg-surface-900 dark:border-surface-700 dark:text-white',
}[style] ?? 'bg-gradient-to-r from-primary-600 to-violet-600 text-white shadow-2xl shadow-primary-600/20')
const ctaBannerImageOverlayClass = (style: string): string => ({ 'gradient-1': 'bg-slate-950/45', 'gradient-2': 'bg-slate-950/45', 'gradient-3': 'bg-slate-950/50', primary_light: 'bg-primary-500/20', green_light: 'bg-green-500/20', white: 'bg-white/65' }[style] ?? 'bg-slate-950/45')
const ctaBannerIsLightSurface = (style: string): boolean => ['primary_light', 'green_light', 'white', 'light'].includes(style)
const sectionOverlayStyle = (opacity?: number): Record<string, string> => ({ opacity: String(Math.max(0, Math.min(100, Number(opacity || 45))) / 100) })
const toolsShowcaseGridClass = (layout: string): string => ({ '2-column': 'grid-cols-1 md:grid-cols-2', '3-column': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3', '4-column': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4' }[layout] ?? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3')
const toolsShowcaseCardClass = (style: string): string => ({
    simple: 'group relative flex h-full flex-col overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white/90 p-6 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_22px_60px_rgba(31,117,254,0.10)] dark:border-surface-700 dark:bg-surface-900/80',
    bordered: 'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
}[style] ?? 'group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900')
const toolsShowcaseCardBodyClass = (style: string): string => ({ simple: 'flex flex-1 flex-col gap-4', bordered: 'flex flex-1 flex-col gap-4', image_focus: 'flex flex-1 flex-col gap-4 px-6 pb-6 pt-5' }[style] ?? 'flex flex-1 flex-col gap-4')
const toolsShowcaseFormatCount = (value: number | undefined): string => { if (!value) return '0'; if (value >= 1000000) return `${(value / 1000000).toFixed(1)}M`; if (value >= 1000) return `${(value / 1000).toFixed(1)}K`; return String(value) }
const toolsShowcaseItems = (): ToolItem[] => {
    const max = Number(props.section.config.max_items ?? 6); const source = asString(props.section.config.source, 'all'); const tools = [...(props.allTools ?? [])]
    const filtered = source === 'featured' ? tools.filter((t) => t.is_featured) : source === 'popular' ? tools.sort((a, b) => (b.usage_count ?? 0) - (a.usage_count ?? 0)) : source === 'recent' ? tools.sort((a, b) => { const da = a.created_at ? Date.parse(a.created_at) : 0; const db = b.created_at ? Date.parse(b.created_at) : 0; return db - da }) : tools
    return filtered.slice(0, max)
}
</script>

<template>
    <section class="overflow-hidden bg-white py-24 transition-colors duration-300 dark:bg-surface-950">
        <div :class="ctaBannerWidthClass(asString(section.config.width, 'contained'))" class="mx-auto px-6">
            <div :class="ctaBannerSurfaceClass(asString(section.config.background_style, 'gradient-1'))" class="relative isolate overflow-hidden rounded-[2.5rem] p-10 md:p-16">
                <div v-if="asString(section.config.background_image_url)" class="absolute inset-0 z-0 overflow-hidden">
                    <img :src="resolveMediaUrl(asString(section.config.background_image_url))" alt="" loading="lazy" class="h-full w-full object-cover">
                    <div :class="ctaBannerImageOverlayClass(asString(section.config.background_style, 'gradient-1'))" :style="sectionOverlayStyle(Number(section.config.overlay_opacity) || undefined)" class="absolute inset-0"></div>
                </div>
                <div class="relative z-10">
                    <div class="mb-12 text-center">
                        <h2 class="mb-4 text-3xl font-black md:text-5xl" :class="heroColorClass(asString(section.config.heading_color, ctaBannerIsLightSurface(asString(section.config.background_style, 'gradient-1')) ? 'dark' : 'white'))">{{ asString(section.config.title, t('AI Tools Showcase')) }}</h2>
                        <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium" :class="heroColorClass(asString(section.config.subheading_color, ctaBannerIsLightSurface(asString(section.config.background_style, 'gradient-1')) ? 'dark' : 'white'), 'subheading')">{{ asString(section.config.subtitle) }}</p>
                    </div>
                    <div v-if="toolsShowcaseItems().length > 0" class="grid gap-5" :class="toolsShowcaseGridClass(asString(section.config.layout, '3-column'))">
                        <Link v-for="tool in toolsShowcaseItems()" :key="tool.slug" :href="`/ai-tools/${tool.slug}`" class="group" :class="toolsShowcaseCardClass(asString(section.config.card_style, 'bordered'))">
                            <div :class="toolsShowcaseCardBodyClass(asString(section.config.card_style, 'bordered'))">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg" :style="tool.color ? { background: tool.color } : { background: 'var(--color-primary-500)' }">
                                        <i v-if="tool.icon" :class="[tool.icon, 'text-lg']"></i>
                                        <span v-else class="text-sm font-black">{{ tool.name.charAt(0) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ tool.name }}</h3>
                                            <span v-if="tool.is_featured" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ t('Featured') }}</span>
                                        </div>
                                        <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ tool.description }}</p>
                                    </div>
                                </div>
                                <div class="mt-auto flex items-center justify-between gap-4 border-t border-gray-100 pt-4 dark:border-surface-700">
                                    <span v-if="tool.avg_rating" class="inline-flex items-center gap-1 text-xs font-medium text-gray-400"><i class="ti ti-star-filled text-amber-400 text-xs"></i> {{ Number(tool.avg_rating).toFixed(1) }}</span>
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400"><i class="ti ti-users text-xs"></i> {{ toolsShowcaseFormatCount(tool.usage_count) }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-gray-50 p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400">{{ t('No tools are available for this showcase yet.') }}</div>
                    <div v-if="asString(section.config.primary_text) && asString(section.config.primary_link)" class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link :href="asString(section.config.primary_link, '/ai-tools')" :class="heroButtonClass(asString(section.config.primary_style, 'primary_filled'))" class="inline-flex w-full items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors sm:w-auto">
                            <i v-if="asString(section.config.primary_icon)" :class="[asString(section.config.primary_icon), 'block shrink-0 text-lg leading-none']"></i>
                            {{ asString(section.config.primary_text) }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
