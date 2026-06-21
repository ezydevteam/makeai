<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const asItems = (v: SectionConfigValue | undefined): Record<string, string | number | boolean>[] => Array.isArray(v) && v.every((i) => typeof i !== 'string') ? v : []
const howItWorksSteps = (): Record<string, string | number | boolean>[] => asItems(props.section.config.items).slice(0, Number(asString(props.section.config.max_items, '6')))
const howItWorksStepCardClass = (style: string): string => ({
    simple: 'rounded-[1.5rem] border border-transparent bg-white/80 p-6 shadow-[0_10px_28px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-[0_18px_45px_rgba(31,117,254,0.08)] dark:bg-surface-800/70 dark:hover:bg-surface-800',
    bordered: 'rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900',
}[style] ?? 'rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-[0_28px_80px_rgba(31,117,254,0.12)] dark:border-surface-700 dark:bg-surface-900')
const howItWorksStepIndexClass = (style: string): string => ({
    simple: 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300',
    bordered: 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/20',
}[style] ?? 'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/20')
</script>

<template>
    <section class="bg-gray-50 py-24 dark:bg-surface-900">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-12 text-center">
                <div v-if="asString(section.config.icon, 'ti ti-route')" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon, 'ti ti-route'), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.heading ?? section.config.title, t('How It Works')) }}</h2>
                <p v-if="asString(section.config.subheading ?? section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
            </div>
            <div v-if="howItWorksSteps().length > 0">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="(item, index) in howItWorksSteps()" :key="`${item.title}_${index}`" :class="howItWorksStepCardClass(asString(section.config.step_card_style, 'bordered'))">
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <span :class="['inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-black', howItWorksStepIndexClass(asString(section.config.step_card_style, 'bordered'))]">{{ String(index + 1).padStart(2, '0') }}</span>
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary-500 dark:text-primary-300">{{ t('Step :count', { count: String(index + 1).padStart(2, '0') }) }}</p>
                            <span v-if="item.icon" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300"><i :class="String(item.icon)"></i></span>
                        </div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-white">{{ item.title || item.label || item.name }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ item.description || item.text || item.number }}</p>
                    </article>
                </div>
            </div>
            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No steps have been added to this section yet.') }}</div>
        </div>
    </section>
</template>
