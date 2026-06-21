<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asBoolean = (v: SectionConfigValue | undefined, fallback = false): boolean => typeof v === 'boolean' ? v : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []
const resolveMediaUrl = (path?: string | null): string => { if (!path) return ''; if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path; return `/storage/${path}` }
</script>

<template>
    <section class="bg-[var(--color-bg)] py-24">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-12 text-center">
                <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.title, t('Technology Partners')) }}</h2>
                <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
            </div>
            <div v-if="asItems(section.config.items).length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <a v-for="(item, index) in asItems(section.config.items)" :key="`${String(item.title ?? item.label ?? item.name ?? 'logo')}_${index}`" :href="String(item.link_url ?? '') || undefined" :target="asBoolean(item.link_open_new_tab, false) ? '_blank' : undefined" :rel="asBoolean(item.link_open_new_tab, false) ? 'noopener noreferrer' : undefined" class="group flex min-w-0 flex-col items-center text-center">
                    <div class="flex w-full items-center justify-center rounded-[1.5rem] border border-gray-200 bg-white px-5 py-4 shadow-sm transition duration-200 group-hover:-translate-y-0.5 group-hover:border-primary-200 group-hover:shadow-lg dark:border-surface-700 dark:bg-surface-800">
                        <img v-if="String(item.image_url ?? '')" :src="resolveMediaUrl(String(item.image_url))" :alt="String(item.title ?? '')" class="max-h-10 w-auto object-contain">
                        <div v-else class="flex h-10 w-full items-center justify-center rounded-[1rem] bg-gradient-to-br from-primary-50 to-secondary-50 px-4 py-3 text-sm font-black text-gray-700 dark:from-primary-500/10 dark:to-secondary-500/10 dark:text-white">{{ String(item.title ?? item.label ?? item.name ?? t('Logo')) }}</div>
                    </div>
                    <p v-if="String(item.title ?? '')" class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ String(item.title ?? '') }}</p>
                </a>
            </div>
            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No logos have been added yet.') }}</div>
        </div>
    </section>
</template>
