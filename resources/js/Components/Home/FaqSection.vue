<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface Faq { id: number; question: string; answer: string; category_id: number | null; sort_order: number }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; faqs: Faq[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const openFaqId = ref<number | null>(null)
const getFaqsSlice = (): Faq[] => {
    const max = parseInt(String(props.section.config.max_items ?? 10), 10)
    return props.faqs.slice(0, max)
}
</script>

<template>
    <section class="bg-white py-24 transition-colors duration-300 dark:bg-surface-950">
        <div class="mx-auto max-w-3xl px-6">
            <div class="mb-16 text-center">
                <div v-if="asString(section.config.icon, 'ti ti-help-circle')" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon, 'ti ti-help-circle'), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.heading ?? section.config.title, t('Frequently Asked Questions')) }}</h2>
                <p v-if="asString(section.config.subheading ?? section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
            </div>
            <div v-if="getFaqsSlice().length > 0" class="space-y-3">
                <div v-for="faq in getFaqsSlice()" :key="faq.id" class="overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 transition-all dark:border-surface-800 dark:bg-surface-900">
                    <button @click="openFaqId = openFaqId === faq.id ? null : faq.id" type="button" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                        <span class="text-sm font-black text-gray-900 dark:text-white md:text-base">{{ faq.question }}</span>
                        <svg :class="openFaqId === faq.id ? 'rotate-180' : ''" class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div v-show="openFaqId === faq.id" class="px-6 pb-5">
                        <div class="text-sm leading-relaxed text-gray-600 dark:text-gray-400" v-html="faq.answer"></div>
                    </div>
                </div>
            </div>
            <div v-else class="py-16 text-center text-gray-400 dark:text-gray-600">
                <svg class="mx-auto mb-3 h-12 w-12 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="font-medium">{{ t('No FAQs yet. Add some from the admin panel.') }}</p>
            </div>
        </div>
    </section>
</template>
