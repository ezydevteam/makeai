<script setup lang="ts">
import { reactive } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    formData: Record<string, any>
}>()

const site = reactive({
    site_name: props.formData?.step_3?.site_name ?? '',
    site_url: props.formData?.step_3?.site_url ?? (typeof window !== 'undefined' ? `${window.location.protocol}//${window.location.host}` : 'http://localhost'),
})

const { t } = useTranslate()

defineExpose({ getData: () => ({ ...site }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Site Setup') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ t('Configure the basic details of your :app installation.', { app: t('Application') }) }}</p>

        <div class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Site Name') }}</span>
                <input
                    v-model="site.site_name"
                    type="text"
                    :placeholder="t('My :app Site', { app: t('Application') })"
                    class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Site URL') }}</span>
                <input
                    v-model="site.site_url"
                    type="text"
                    :placeholder="t('https://example.com')"
                    class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                />
                <span class="mt-1 block text-xs text-gray-400">{{ t('Auto-detected from your current URL — change if needed.') }}</span>
            </label>
        </div>
    </div>
</template>
