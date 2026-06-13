<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    features: {
        scroll_to_top_enabled: boolean
    }
}>()

const { t } = useTranslate()

const form = useForm({
    scroll_to_top_enabled: props.features.scroll_to_top_enabled,
})

const submit = () => {
    form.post(route('admin.features.settings.update'), {
        preserveScroll: true,
    })
}
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
            <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Scroll To Top') }}</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show a floating button on the public homepage that scrolls visitors back to the top.') }}</p>
                </div>
                <button type="button" role="switch" :aria-checked="form.scroll_to_top_enabled" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.scroll_to_top_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="form.scroll_to_top_enabled = !form.scroll_to_top_enabled">
                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.scroll_to_top_enabled ? 'translate-x-5' : 'translate-x-0.5'"></span>
                </button>
            </div>
        </div>
    </div>
</template>
