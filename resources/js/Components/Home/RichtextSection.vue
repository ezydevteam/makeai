<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
</script>

<template>
    <section class="bg-[var(--color-bg)] py-24">
        <div class="mx-auto max-w-4xl px-6">
            <div v-if="asString(section.config.title) || asString(section.config.subtitle)" class="mb-12 text-center">
                <h2 v-if="asString(section.config.title)" class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.title) }}</h2>
                <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
            </div>
            <article class="prose prose-gray max-w-none rounded-[2rem] border border-gray-100 bg-white p-8 shadow-sm dark:prose-invert dark:border-surface-800 dark:bg-surface-900" v-html="asString(section.config.content)"></article>
        </div>
    </section>
</template>
