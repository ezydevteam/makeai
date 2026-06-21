<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const heroButtonClass = (style: string): string => ({ primary_filled: 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700', outline: 'border-2 border-gray-200 bg-transparent text-gray-900 hover:bg-gray-50', dark: 'bg-gray-900 text-white' }[style] ?? 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')
</script>

<template>
    <section class="bg-[var(--color-bg)] py-24">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-12 text-center">
                <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.heading ?? section.config.title, t('Stay in the Loop')) }}</h2>
                <p v-if="asString(section.config.subheading ?? section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subheading ?? section.config.subtitle) }}</p>
            </div>
            <div class="mx-auto max-w-4xl">
                <form :method="'post'" :action="asString(section.config.button_link, '/newsletter/subscribe')" class="mt-8 flex flex-col gap-3 rounded-[2rem] border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center dark:border-surface-800 dark:bg-surface-900">
                    <input name="email" type="email" required :placeholder="asString(section.config.placeholder_text, t('Enter your email address'))" class="flex-1 rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-gray-900 focus:border-primary-400 focus:ring-primary-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <button type="submit" :class="heroButtonClass(asString(section.config.button_style, 'primary_filled'))" class="inline-flex items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors">
                        <i v-if="asString(section.config.button_icon)" :class="[asString(section.config.button_icon), 'text-lg']"></i>
                        {{ asString(section.config.button_text, t('Subscribe')) }}
                    </button>
                </form>
                <p v-if="asString(section.config.privacy_text)" class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ asString(section.config.privacy_text, t('No spam. Unsubscribe anytime.')) }}</p>
            </div>
        </div>
    </section>
</template>
