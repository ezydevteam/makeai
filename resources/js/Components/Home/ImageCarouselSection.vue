<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []
const resolveMediaUrl = (path?: string | null): string => { if (!path) return ''; if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/') || path.startsWith('data:')) return path; return `/storage/${path}` }
</script>

<template>
    <section class="bg-[var(--color-bg)] py-24">
        <div class="mx-auto max-w-7xl px-6">
            <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-12 text-center">
                <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.title) }}</h2>
                <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="(item, index) in asItems(section.config.items)" :key="`${item.title}_${index}`" class="overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800">
                    <img v-if="item.image_url" :src="resolveMediaUrl(String(item.image_url))" :alt="String(item.title || '')" loading="lazy" class="h-56 w-full object-cover">
                    <div v-else class="flex h-56 items-center justify-center bg-gray-100 text-gray-400 dark:bg-surface-700 dark:text-gray-500"><i class="ti ti-photo text-4xl"></i></div>
                    <div class="p-6">
                        <h3 v-if="item.title" class="text-xl font-black text-gray-900 dark:text-white">{{ item.title }}</h3>
                        <p v-if="item.description" class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                        <a v-if="item.link_url" :href="String(item.link_url)" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 transition hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">{{ t('Learn more') }} <i class="ti ti-arrow-right text-sm"></i></a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
