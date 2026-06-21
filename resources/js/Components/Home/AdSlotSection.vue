<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import AdSection from '@/Components/AdSection.vue'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
type AdZone = 'header_banner' | 'sidebar_top' | 'sidebar_bottom' | 'content_top' | 'content_bottom' | 'content-injection' | 'between_posts' | 'between_ai_tools' | 'tool_page_top' | 'tool_page_bottom' | 'template_page' | 'chat_banner' | 'dashboard_top' | 'footer_banner' | 'custom_zone_1' | 'custom_zone_2'
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
</script>

<template>
    <section class="bg-[var(--color-bg)] py-16">
        <div class="mx-auto max-w-5xl px-6">
            <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-8 text-center">
                <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-4xl">{{ asString(section.config.title) }}</h2>
                <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
            </div>
            <AdSection :zone="asString(section.config.zone, 'content_top') as AdZone" />
        </div>
    </section>
</template>
