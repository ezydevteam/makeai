<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface Testimonial { id: number; name: string; role: string | null; company: string | null; avatar: string | null; content: string; rating: number; is_featured: boolean; source: string }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; testimonials: Testimonial[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const resolveMediaUrl = (path?: string | null): string => { if (!path) return ''; if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path; return `/storage/${path}` }
const stars = (n: number): boolean[] => Array.from({ length: 5 }, (_, i) => i < n)
const getTestimonialsSlice = (): Testimonial[] => {
    const max = parseInt(String(props.section.config.max_items ?? 6), 10)
    const source = asString(props.section.config.source, 'all')
    const list = source === 'featured' ? props.testimonials.filter(t => t.is_featured) : props.testimonials
    return list.slice(0, max)
}
const testimonialsCardClass = (style: string): string => ({
    simple: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white/90 p-7 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_20px_50px_rgba(31,117,254,0.10)] dark:border-surface-700 dark:bg-surface-900/80',
    bordered: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-8 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_70px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
    spotlight: 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.75rem] border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-sky-50 p-8 shadow-[0_18px_50px_rgba(16,185,129,0.10)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(16,185,129,0.16)] dark:border-primary-900/40 dark:from-primary-950/30 dark:via-surface-900 dark:to-surface-900',
}[style] ?? 'relative flex h-full flex-col gap-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-8 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_70px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900')
</script>

<template>
    <section class="bg-gray-50 py-24 transition-colors duration-300 dark:bg-surface-900">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.heading ?? section.config.title, t('What Our Users Say')) }}</h2>
                <p v-if="asString(section.config.subheading ?? section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
            </div>
            <div v-if="getTestimonialsSlice().length > 0" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="testimonial in getTestimonialsSlice()" :key="testimonial.id" :class="testimonialsCardClass(asString(section.config.card_style, 'bordered'))">
                    <div v-if="asString(section.config.card_style, 'bordered') !== 'simple'" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-violet-500"></div>
                    <div v-if="asString(section.config.card_style, 'spotlight') === 'spotlight'" class="absolute -right-12 -top-12 h-28 w-28 rounded-full bg-primary-500/10 blur-3xl"></div>
                    <div class="relative z-10 flex items-center gap-0.5">
                        <svg v-for="(filled, i) in stars(testimonial.rating)" :key="i" class="h-4 w-4" :class="filled ? 'text-yellow-400' : 'text-gray-200 dark:text-surface-700'" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <p class="relative z-10 flex-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">&ldquo;{{ testimonial.content }}&rdquo;</p>
                    <div class="relative z-10 flex items-center gap-3 border-t border-gray-100 pt-4 dark:border-surface-700">
                        <div v-if="testimonial.avatar" class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-700">
                            <img :src="resolveMediaUrl(testimonial.avatar)" :alt="testimonial.name" class="h-full w-full object-cover">
                        </div>
                        <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-black text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">{{ testimonial.name.charAt(0) }}</div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ testimonial.name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ [testimonial.role, testimonial.company].filter(Boolean).join(' · ') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="py-16 text-center text-gray-400 dark:text-gray-600">
                <svg class="mx-auto mb-3 h-12 w-12 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <p class="font-medium">{{ t('No testimonials yet. Add some from the admin panel.') }}</p>
            </div>
        </div>
    </section>
</template>
