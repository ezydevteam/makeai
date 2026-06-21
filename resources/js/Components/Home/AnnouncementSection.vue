<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface Announcement { id: number; type: 'topbar' | 'popup' | 'notification'; title: string | null; content: string | null; bg_color: string | null; text_color: string | null; cta_text: string | null; cta_url: string | null; image: string | null; target_audience: string }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; announcements: Announcement[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const getAnnouncementSlice = (): Announcement[] => {
    const max = parseInt(String(props.section.config.max_items ?? 3), 10)
    const selectedType = asString(props.section.config.announcement_type, 'topbar')
    const filtered = selectedType === 'all' ? props.announcements : props.announcements.filter((a) => a.type === selectedType)
    return filtered.slice(0, max)
}
</script>

<template>
    <section class="bg-[var(--color-bg)] py-16">
        <div class="mx-auto max-w-5xl px-6">
            <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-8 text-center">
                <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-4xl">{{ asString(section.config.title) }}</h2>
                <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
            </div>
            <div v-if="getAnnouncementSlice().length > 0" class="space-y-4">
                <article v-for="announcement in getAnnouncementSlice()" :key="announcement.id" class="overflow-hidden rounded-[2rem] border border-transparent p-6 shadow-sm" :style="{ backgroundColor: announcement.bg_color || '#111827', color: announcement.text_color || '#ffffff' }">
                    <div class="mb-2 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{{ t(announcement.type) }}</div>
                    <h3 v-if="announcement.title" class="text-xl font-black">{{ announcement.title }}</h3>
                    <div v-if="announcement.content" class="mt-2 text-sm leading-relaxed opacity-90" v-html="announcement.content"></div>
                    <a v-if="announcement.cta_text && announcement.cta_url" :href="announcement.cta_url" class="mt-5 inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-gray-900 transition hover:bg-gray-100">{{ announcement.cta_text }}</a>
                </article>
            </div>
            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No announcements available yet.') }}</div>
        </div>
    </section>
</template>
