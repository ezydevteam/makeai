<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
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
const healthTab = ref<keyof HealthData>('server')

const healthTabs = computed(() => [
    { key: 'server' as const, label: t('Server'), icon: 'ti ti-server-2', count: props.health.server.length },
    { key: 'application' as const, label: t('Application'), icon: 'ti ti-app-window', count: props.health.application.length },
    { key: 'services' as const, label: t('Services'), icon: 'ti ti-plug-connected', count: props.health.services.length },
    { key: 'license' as const, label: t('License'), icon: 'ti ti-id-badge-2', count: props.health.license.length },
])

const statCards = computed(() => [
    { key: 'app_name', label: t('Application'), value: props.stats.app_name, icon: 'ti ti-app-window', tone: 'text-primary-700 dark:text-primary-300' },
    { key: 'app_version', label: t('Version'), value: props.stats.app_version, icon: 'ti ti-package', tone: 'text-gray-900 dark:text-white' },
    { key: 'php_version', label: t('PHP version'), value: props.stats.php_version, icon: 'ti ti-brand-php', tone: 'text-blue-700 dark:text-blue-300' },
    { key: 'laravel_version', label: t('Laravel version'), value: props.stats.laravel_version, icon: 'ti ti-brand-laravel', tone: 'text-red-700 dark:text-red-300' },
    { key: 'server_software', label: t('Server software'), value: props.stats.server_software, icon: 'ti ti-server-bolt', tone: 'text-gray-900 dark:text-white' },
    { key: 'database_version', label: t('Database version'), value: props.stats.database_version, icon: 'ti ti-database', tone: 'text-amber-700 dark:text-amber-300' },
    { key: 'uptime', label: t('Uptime'), value: props.stats.uptime, icon: 'ti ti-clock-hour-4', tone: 'text-emerald-700 dark:text-emerald-300' },
    { key: 'disk_free', label: t('Disk free'), value: props.stats.disk_free, icon: 'ti ti-device-sd-card', tone: 'text-gray-900 dark:text-white' },
    { key: 'memory_usage', label: t('Memory usage'), value: props.stats.memory_usage, icon: 'ti ti-cpu', tone: 'text-violet-700 dark:text-violet-300' },
])

const overviewCards = computed(() => [
    { key: 'pass', label: t('Pass'), value: props.healthSummary.pass, icon: 'ti ti-circle-check', cardClass: 'border-l-4 border-l-green-500 bg-white dark:bg-surface-900 border-gray-200 dark:border-surface-800', iconClass: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300', valueClass: 'text-green-700 dark:text-green-300' },
    { key: 'warn', label: t('Warn'), value: props.healthSummary.warn, icon: 'ti ti-alert-triangle', cardClass: 'border-l-4 border-l-amber-500 bg-white dark:bg-surface-900 border-gray-200 dark:border-surface-800', iconClass: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', valueClass: 'text-amber-700 dark:text-amber-300' },
    { key: 'fail', label: t('Fail'), value: props.healthSummary.fail, icon: 'ti ti-alert-circle', cardClass: 'border-l-4 border-l-red-500 bg-white dark:bg-surface-900 border-gray-200 dark:border-surface-800', iconClass: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300', valueClass: 'text-red-700 dark:text-red-300' },
    { key: 'total', label: t('Total checks'), value: props.healthSummary.pass + props.healthSummary.warn + props.healthSummary.fail, icon: 'ti ti-list-check', cardClass: 'border-l-4 border-l-violet-500 bg-white dark:bg-surface-900 border-gray-200 dark:border-surface-800', iconClass: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300', valueClass: 'text-violet-700 dark:text-violet-300' },
])

const currentChecks = computed(() => props.health[healthTab.value] ?? [])

const statusBadgeClass: Record<string, string> = {
    pass: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    warn: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    fail: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}

const statusRowClass: Record<string, string> = {
    pass: 'border-green-200/70 bg-green-50/60 dark:border-green-900/40 dark:bg-green-900/10',
    warn: 'border-amber-200/70 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-900/10',
    fail: 'border-red-200/70 bg-red-50/60 dark:border-red-900/40 dark:bg-red-900/10',
}

const statusIconClass: Record<string, string> = {
    pass: 'ti ti-circle-check bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    warn: 'ti ti-alert-triangle bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    fail: 'ti ti-alert-circle bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
}
</script>

<template>
    <Head :title="t('Health Check')" />

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Site Health Monitor') }}</h1>
                </div>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review pass, warning, and failure signals across the server, application, connected services, and license state.') }}
                </p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in overviewCards"
                :key="card.key"
                :class="card.cardClass"
                class="rounded-xl border p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                        <p :class="card.valueClass" class="mt-3 text-3xl font-bold">{{ card.value }}</p>
                    </div>
                    <span :class="card.iconClass" class="inline-flex h-10 w-10 items-center justify-center rounded-xl">
                        <i :class="card.icon" class="text-lg"></i>
                    </span>
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Environment') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Key runtime details for the current installation and infrastructure.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="card in statCards"
                    :key="card.key"
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                            <p :class="card.tone" class="mt-3 break-all text-sm font-semibold">{{ card.value }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-300">
                            <i :class="card.icon" class="text-lg"></i>
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Health Checks') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Inspect each monitoring area and review any suggested follow-up actions.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in healthTabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                            :class="healthTab === tab.key
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700'"
                            @click="healthTab = tab.key"
                        >
                            <i :class="tab.icon" class="text-base"></i>
                            <span>{{ tab.label }}</span>
                            <span
                                :class="healthTab === tab.key
                                    ? 'bg-white/80 text-primary-700 dark:bg-primary-950/50 dark:text-primary-200'
                                    : 'bg-white text-gray-500 dark:bg-surface-700 dark:text-gray-300'"
                                class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-6">
                <div
                    v-for="check in currentChecks"
                    :key="check.label"
                    :class="statusRowClass[check.status]"
                    class="rounded-xl border p-5"
                >
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl">
                            <i :class="statusIconClass[check.status]" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(check.label) }}</p>
                                <span :class="statusBadgeClass[check.status]" class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]">
                                    {{ t(check.status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ t(check.detail) }}</p>
                            <p v-if="check.suggestion" class="mt-3 text-sm text-blue-700 dark:text-blue-300">
                                {{ t(check.suggestion) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="currentChecks.length === 0"
                    class="rounded-xl border border-dashed border-gray-200 px-6 py-12 text-center dark:border-surface-700"
                >
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                        <i class="ti ti-stethoscope text-xl"></i>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('No checks available') }}</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('This section does not have any diagnostics to display right now.') }}</p>
                </div>
            </div>
        </section>
    </div>

</template>
