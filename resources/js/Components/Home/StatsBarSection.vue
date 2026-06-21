<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asBoolean = (v: SectionConfigValue | undefined, fallback = false): boolean => typeof v === 'boolean' ? v : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []
const heroColorClass = (color: string, tone: 'heading' | 'subheading' = 'heading'): string => {
    const h: Record<string, string> = { dark: 'text-gray-900 dark:text-white', light: 'text-white' }
    const s: Record<string, string> = { dark: 'text-gray-600 dark:text-gray-400', light: 'text-white/70' }
    return tone === 'heading' ? (h[color] ?? h.dark) : (s[color] ?? s.light)
}
</script>

<template>
    <section class="border-y border-gray-100 bg-[var(--color-bg)] py-16">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-12 text-center">
                <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.heading ?? section.config.title, t('Social Proof')) }}</h2>
                <p v-if="asString(section.config.subheading ?? section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
            </div>
            <div :class="asBoolean(section.config.show_stats_separator, true) ? 'border-t border-gray-100 pt-12 dark:border-surface-800' : 'pt-12'" class="grid grid-cols-2 gap-8 text-center md:grid-cols-4">
                <div v-for="stat in asItems(section.config.stats)" :key="`${stat.number}_${stat.label}`">
                    <p :class="heroColorClass(asString(section.config.stats_number_color, 'dark'))" class="text-4xl font-black">{{ stat.number }}</p>
                    <p :class="heroColorClass(asString(section.config.stats_label_color, 'light'), 'subheading')" class="mt-2 text-xs font-black uppercase tracking-widest">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
