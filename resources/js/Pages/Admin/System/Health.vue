<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type HealthCheck = {
    status: 'pass' | 'warn' | 'fail'
    label: string
    detail: string
    suggestion: string | null
}

type HealthData = {
    server: HealthCheck[]
    application: HealthCheck[]
    services: HealthCheck[]
    license: HealthCheck[]
}

type HealthSummary = {
    pass: number
    warn: number
    fail: number
}

type Stats = {
    app_name: string
    app_version: string
    php_version: string
    laravel_version: string
    server_software: string
    database_version: string
    uptime: string
    disk_free: string
    memory_usage: string
}

const props = defineProps<{
    health: HealthData
    healthSummary: HealthSummary
    stats: Stats
}>()

const { t } = useTranslate()
const healthTab = ref('server')

const healthTabs: Record<string, string> = {
    server: 'Server',
    application: 'Application',
    services: 'Services',
    license: 'License',
}

const statLabels: Record<string, string> = {
    app_name: 'Application',
    app_version: 'Version',
    php_version: 'PHP version',
    laravel_version: 'Laravel version',
    server_software: 'Server software',
    database_version: 'Database version',
    uptime: 'Uptime',
    disk_free: 'Disk free',
    memory_usage: 'Memory usage',
}

const statusIcon: Record<string, string> = {
    pass: '✅',
    warn: '⚠️',
    fail: '❌',
}

const statusBadgeClass: Record<string, string> = {
    pass: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    warn: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    fail: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}
</script>

<template>
    <Head :title="t('Health Check')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Site Health Monitor') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Pass, warn, and fail checks across server, application, services, and license.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1.5 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ healthSummary.pass }} {{ t('pass') }}</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1.5 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ healthSummary.warn }} {{ t('warn') }}</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1.5 text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ healthSummary.fail }} {{ t('fail') }}</span>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Environment') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="(value, key) in stats" :key="key" class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                    <div class="mb-1 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">{{ t(statLabels[key] ?? key) }}</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ value }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="mb-4 flex gap-2 overflow-x-auto border-b border-gray-200 pb-2 dark:border-surface-700">
                <button
                    v-for="(label, key) in healthTabs"
                    :key="key"
                    type="button"
                    :class="healthTab === key ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="rounded-t-lg border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                    @click="healthTab = key"
                >
                    {{ t(label) }}
                </button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="check in health[healthTab as keyof HealthData]"
                    :key="check.label"
                    class="flex items-start gap-3 rounded-xl border border-gray-100 p-4 dark:border-surface-700"
                >
                    <span class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-sm dark:bg-surface-800">{{ statusIcon[check.status] }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(check.label) }}</span>
                            <span :class="statusBadgeClass[check.status]" class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase">{{ check.status }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ check.detail }}</p>
                        <p v-if="check.suggestion" class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                            {{ t(check.suggestion) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
