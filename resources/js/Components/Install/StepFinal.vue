<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    formData: Record<string, any>
}>()

const steps = props.formData
const { t } = useTranslate()

const items = [
    { label: t('Database Host'), value: steps?.step_2?.db_host ?? '—' },
    { label: t('Database Port'), value: steps?.step_2?.db_port ?? '—' },
    { label: t('Database Name'), value: steps?.step_2?.db_database ?? '—' },
    { label: t('Database User'), value: steps?.step_2?.db_username ?? '—' },
    { label: t('Site Name'), value: steps?.step_3?.site_name ?? '—' },
    { label: t('Site URL'), value: steps?.step_3?.site_url ?? '—' },
    { label: t('Purchase Code'), value: steps?.step_4?.purchase_code ?? '—' },
    { label: t('Admin Name'), value: steps?.step_5?.admin_name ?? '—' },
    { label: t('Admin Email'), value: steps?.step_5?.admin_email ?? '—' },
    { label: t('Demo Content'), value: steps?.step_6?.install_demo ? t('Yes') : t('No, start fresh') },
]
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Ready to Install') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ t('Review your configuration and click install to complete setup.') }}</p>

        <!-- Summary -->
        <div class="mt-6 rounded-lg border border-gray-200 dark:border-surface-700 divide-y divide-gray-100 dark:divide-surface-700">
            <div
                v-for="item in items"
                :key="item.label"
                class="flex items-center justify-between px-4 py-3"
            >
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ item.label }}</span>
                <span class="text-sm font-mono font-medium text-gray-900 dark:text-white ml-4 text-right max-w-[60%] truncate">
                    {{ item.value }}
                </span>
            </div>
        </div>

        <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
            <p class="font-semibold">{{ t('What will happen:') }}</p>
            <ol class="mt-2 list-decimal list-inside space-y-1 text-xs">
                <li>{{ t('Database migrations will run') }}</li>
                <li>{{ t('Admin roles and permissions will be created') }}</li>
                <li>{{ t('Your admin account will be set up') }}</li>
                <li>{{ t('Your license will be activated') }}</li>
                <li v-if="steps?.step_6?.install_demo">{{ t('Demo content will be imported') }}</li>
                <li>{{ t('The installer will be disabled (404)') }}</li>
            </ol>
        </div>
    </div>
</template>
