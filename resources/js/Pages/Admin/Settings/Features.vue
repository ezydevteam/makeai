<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    features: {
        scroll_to_top_enabled: boolean
        ai_chat_enabled: boolean
        ai_variations_enabled: boolean
        social_sharing_enabled: boolean
        document_editor_enabled: boolean
        favorites_enabled: boolean
        reviews_enabled: boolean
        recently_used_tools_enabled: boolean
        estimated_generation_time_enabled: boolean
    }
}>()

const { t } = useTranslate()

const form = useForm({
    scroll_to_top_enabled: props.features.scroll_to_top_enabled,
    ai_chat_enabled: props.features.ai_chat_enabled,
    ai_variations_enabled: props.features.ai_variations_enabled,
    social_sharing_enabled: props.features.social_sharing_enabled,
    document_editor_enabled: props.features.document_editor_enabled,
    favorites_enabled: props.features.favorites_enabled,
    reviews_enabled: props.features.reviews_enabled,
    recently_used_tools_enabled: props.features.recently_used_tools_enabled,
    estimated_generation_time_enabled: props.features.estimated_generation_time_enabled,
})

const submit = () => {
    form.post(route('admin.features.settings.update'), {
        preserveScroll: true,
    })
}

const featureToggles = [
    { key: 'scroll_to_top_enabled', label: 'Scroll To Top', description: 'Show a floating button on the public homepage that scrolls visitors back to the top.' },
    { key: 'ai_chat_enabled', label: 'AI Chat Assistant', description: 'Enable the AI chat assistant feature' },
    { key: 'ai_variations_enabled', label: 'AI Variations', description: 'Allow users to generate multiple variations of AI output' },
    { key: 'social_sharing_enabled', label: 'Social Sharing', description: 'Enable social media sharing buttons for content' },
    { key: 'document_editor_enabled', label: 'Document Editor', description: 'Enable the built-in document editor' },
    { key: 'favorites_enabled', label: 'Favorites', description: 'Allow users to favorite tools and content' },
    { key: 'reviews_enabled', label: 'Reviews', description: 'Enable user reviews and ratings' },
    { key: 'recently_used_tools_enabled', label: 'Recently Used Tools', description: 'Show recently used tools section' },
    { key: 'estimated_generation_time_enabled', label: 'Estimated Generation Time', description: 'Show estimated time for AI generation' },
    { key: 'subscriptions_enabled', label: 'Premium Subscriptions', description: 'Enable the subscription/billing system for premium plans (requires Extended License).' },
] as const
</script>

<template>
    <Head :title="t('Features Settings')" />

    <div class="mx-auto max-w-5xl px-6 py-8">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Features') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enable or disable core system features from one place.') }}</p>
            </div>
            <button type="button" @click="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg btn-primary px-5 py-2.5 text-sm font-semibold disabled:opacity-60">
                <span>{{ form.processing ? t('Saving...') : t('Save Settings') }}</span>
            </button>
        </section>

        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
            <div class="space-y-4">
                <div v-for="feature in featureToggles" :key="feature.key" class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(feature.label) }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t(feature.description) }}</p>
                    </div>
                    <button 
                        type="button" 
                        role="switch" 
                        :aria-checked="(form as any)[feature.key]" 
                        class="relative inline-flex h-6 w-11 rounded-full transition" 
                        :class="(form as any)[feature.key] ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" 
                        @click="(form as any)[feature.key] = !(form as any)[feature.key]"
                    >
                        <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="(form as any)[feature.key] ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
