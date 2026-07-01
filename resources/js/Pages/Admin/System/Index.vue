<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { defineAsyncComponent, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

type Stats = {
    php_version: string
    laravel_version: string
    server_software: string
    database_version: string
    disk_free: string
    memory_usage: string
}

type MaintenanceSettings = {
    maintenance_title: string
    maintenance_message: string
    maintenance_estimated_restoration_time: string | null
    maintenance_allowed_ips: string
    maintenance_background_image: string | null
    maintenance_background_image_url: string | null
}

type CronTask = {
    key: string
    name: string
    command: string
    frequency: string
    description: string
    runnable: boolean
    last_run_at: string | null
    next_run: string
}

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

type UpdateStatus = {
    current_version: string
    latest_version: string | null
    update_available: boolean
    last_checked: string | null
}

type CronStatus = {
    is_configured: boolean
    last_run_at: string | null
    last_run_human: string | null
    required_entry: string
    project_path: string
    php_binary: string
    cpanel_detected: boolean
    tasks: CronTask[]
}

const props = defineProps<{
    health: HealthData
    healthSummary: HealthSummary
    update: UpdateStatus
    stats: Stats
    status: {
        is_maintenance: boolean
        queue_running: boolean
        scheduler_running: boolean
    }
    cron: CronStatus
    maintenance: MaintenanceSettings
    logs: string[]
}>()

const { t } = useTranslate()
const healthTab = ref('server')
const confirmOpen = ref(false)
const cronCopied = ref(false)
const selectedBackground = ref<File | null>(null)
const backgroundPreview = ref<string | null>(props.maintenance.maintenance_background_image_url)

const cronRunForm = useForm({ task: '' })
const checkUpdatesForm = useForm({})
const maintenanceForm = useForm({
    ...props.maintenance,
    maintenance_background_image: null as File | null,
    remove_maintenance_background_image: false,
})
const toggleForm = useForm({})

const healthTabs: Record<string, string> = {
    server: 'Server',
    application: 'Application',
    services: 'Services',
    license: 'License',
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

const statLabels: Record<keyof Stats, string> = {
    php_version: 'PHP version',
    laravel_version: 'Laravel version',
    server_software: 'Server software',
    database_version: 'Database version',
    disk_free: 'Disk free',
    memory_usage: 'Memory usage',
}

const copyCronEntry = async () => {
    await navigator.clipboard.writeText(props.cron.required_entry)
    cronCopied.value = true
    window.setTimeout(() => {
        cronCopied.value = false
    }, 1600)
}

const runCronTask = (taskKey: string) => {
    cronRunForm.task = taskKey
    cronRunForm.post(route('admin.system.cron.run'), { preserveScroll: true })
}

const saveMaintenance = () => {
    maintenanceForm.maintenance_background_image = selectedBackground.value
    maintenanceForm.post(route('admin.system.maintenance.settings'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedBackground.value = null
            maintenanceForm.remove_maintenance_background_image = false
        },
    })
}

const toggleMaintenance = () => {
    toggleForm.post(route('admin.system.maintenance.toggle'), {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false
        },
    })
}

const selectBackground = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    selectedBackground.value = file
    maintenanceForm.remove_maintenance_background_image = false
    backgroundPreview.value = file ? URL.createObjectURL(file) : props.maintenance.maintenance_background_image_url
}

const removeBackground = () => {
    selectedBackground.value = null
    backgroundPreview.value = null
    maintenanceForm.remove_maintenance_background_image = true
}
</script>

<template>
    <Head :title="t('System Tools')" />

    <div class="mx-auto max-w-7xl px-6 py-6">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('System Tools') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage cron jobs, maintenance mode, and platform health.') }}</p>
            </div>
            <button
                type="button"
                :class="status.is_maintenance ? 'btn-primary' : 'btn-danger'"
                class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all disabled:opacity-60"
                :disabled="toggleForm.processing"
                @click="confirmOpen = true"
            >
                <svg v-if="toggleForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span>{{ status.is_maintenance ? t('Go Live') : t('Enter Maintenance') }}</span>
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <!-- Health Monitor -->
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Site Health Monitor') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('Pass, warn, and fail checks across server, application, services, and license.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ healthSummary.pass }} pass</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ healthSummary.warn }} warn</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ healthSummary.fail }} fail</span>
                        </div>
                    </div>

                    <div class="mb-4 flex gap-2 overflow-x-auto border-b border-gray-200 pb-2 dark:border-surface-700">
                        <button v-for="(label, key) in healthTabs" :key="key" type="button"
                            :class="healthTab === key ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="border-b-2 px-3 py-1.5 text-sm font-medium transition-colors"
                            @click="healthTab = key">{{ t(label) }}</button>
                    </div>

                    <div class="space-y-2">
                        <div v-for="check in health[healthTab as keyof HealthData]" :key="check.label"
                            class="flex items-start gap-3 rounded-lg border border-gray-100 p-3 dark:border-surface-700">
                            <span class="mt-0.5 text-sm">{{ statusIcon[check.status] }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(check.label) }}</span>
                                    <span :class="statusBadgeClass[check.status]" class="inline-flex rounded-full px-2 py-0 text-[10px] font-semibold uppercase">{{ check.status }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ check.detail }}</p>
                                <p v-if="check.suggestion" class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                    💡 {{ t(check.suggestion) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Update Status -->
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('One-Click Updates') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('Check for the latest version from Envato Marketplace.') }}</p>
                        </div>
                        <button type="button" :disabled="checkUpdatesForm.processing"
                            class="inline-flex items-center gap-2 rounded-lg btn-primary text-sm disabled:opacity-60"
                            @click="checkUpdatesForm.post(route('admin.system.check-updates'), { preserveScroll: true })">
                            <svg v-if="checkUpdatesForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            {{ checkUpdatesForm.processing ? t('Checking...') : t('Check for Updates') }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Current version') }}</div>
                            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ update.current_version }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Latest version') }}</div>
                            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-white">{{ update.latest_version || '—' }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Last checked') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ update.last_checked || t('Never') }}</div>
                        </div>
                    </div>

                    <div v-if="update.update_available" class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-900/20">
                        <div class="flex items-start gap-3">
                            <span class="text-lg">🎉</span>
                            <div>
                                <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">{{ t('Update Available') }}</p>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    {{ t('Version :version is available. Go to License → Update to download and install.', { version: update.latest_version ?? '' }) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="cron-jobs" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Cron Jobs') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('Configure Laravel scheduler so renewals, reminders, counters, and automation run on time.') }}</p>
                        </div>
                        <span :class="cron.is_configured ? 'bg-primary-100 text-primary-700' : 'bg-amber-100 text-amber-700'" class="inline-flex rounded-full px-3 py-1 text-xs font-semibold">
                            {{ cron.is_configured ? t('Configured') : t('Setup Required') }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Last scheduler run') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ cron.last_run_human || t('Never detected') }}</div>
                            <div v-if="cron.last_run_at" class="mt-1 font-mono text-xs text-gray-500">{{ cron.last_run_at }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Project path') }}</div>
                            <div class="mt-1 break-all font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ cron.project_path }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('PHP binary') }}</div>
                            <div class="mt-1 break-all font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ cron.php_binary }}</div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-xl border border-gray-200 bg-gray-950 p-4 text-white shadow-sm dark:border-surface-700">
                        <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold">{{ t('Required Cron Entry') }}</h3>
                                <p class="mt-1 text-xs text-gray-400">{{ t('Add this command in hosting cron jobs and run it every minute.') }}</p>
                            </div>
                            <button type="button" class="inline-flex items-center justify-center rounded-lg btn-primary transition-colors" @click="copyCronEntry">
                                {{ cronCopied ? t('Copied') : t('Copy Command') }}
                            </button>
                        </div>
                        <code class="block overflow-x-auto whitespace-pre rounded-lg bg-black/40 p-3 font-mono text-xs text-primary-100">{{ cron.required_entry }}</code>
                    </div>

                    <div v-if="cron.cpanel_detected" class="mt-5 rounded-xl border border-secondary-100 bg-secondary-50 p-4 text-secondary-900 dark:border-secondary-900/40 dark:bg-secondary-900/20 dark:text-secondary-100">
                        <h3 class="text-sm font-semibold">{{ t('cPanel detected') }}</h3>
                        <p class="mt-1 text-sm leading-relaxed">{{ t('Open cPanel Cron Jobs, choose Once Per Minute, paste the command above, and save. The warning banner disappears after the scheduler heartbeat runs.') }}</p>
                    </div>
                    <div v-else class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hosting setup') }}</h3>
                        <p class="mt-1 text-sm leading-relaxed">{{ t('Use your server cron manager, cPanel, Plesk, or supervisor to run the Laravel scheduler once every minute.') }}</p>
                    </div>

                    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 dark:border-surface-700">
                        <div class="grid grid-cols-12 gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-surface-700 dark:bg-surface-800">
                            <div class="col-span-5">{{ t('Task') }}</div>
                            <div class="col-span-3 hidden md:block">{{ t('Frequency') }}</div>
                            <div class="col-span-2 hidden lg:block">{{ t('Last Run') }}</div>
                            <div class="col-span-7 text-right md:col-span-4 lg:col-span-2">{{ t('Action') }}</div>
                        </div>
                        <div v-for="task in cron.tasks" :key="task.key" class="grid grid-cols-12 gap-3 border-b border-gray-100 px-4 py-4 last:border-b-0 hover:bg-primary-50/40 dark:border-surface-800 dark:hover:bg-primary-900/10">
                            <div class="col-span-12 md:col-span-5">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(task.name) }}</div>
                                <div class="mt-1 font-mono text-xs text-gray-500">{{ task.command }}</div>
                                <p class="mt-1 text-xs text-gray-500">{{ t(task.description) }}</p>
                            </div>
                            <div class="col-span-6 md:col-span-3">
                                <div class="md:hidden text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Frequency') }}</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300">{{ t(task.frequency) }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ task.next_run }}</div>
                            </div>
                            <div class="col-span-6 lg:col-span-2">
                                <div class="lg:hidden text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Last Run') }}</div>
                                <div class="text-sm text-gray-700 dark:text-gray-300">{{ task.last_run_at || t('Not run manually') }}</div>
                            </div>
                            <div class="col-span-12 flex items-center justify-end md:col-span-4 lg:col-span-2">
                                <button type="button" :disabled="cronRunForm.processing" class="inline-flex items-center justify-center rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition-colors hover:border-primary-300 hover:bg-primary-100 disabled:opacity-60 dark:border-primary-900/50 dark:bg-primary-900/20 dark:text-primary-200" @click="runCronTask(task.key)">
                                    {{ cronRunForm.processing && cronRunForm.task === task.key ? t('Running...') : t('Run Now') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Maintenance Mode') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('Customize the page visitors see while the platform is temporarily unavailable.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Page title') }}
                            <input v-model="maintenanceForm.maintenance_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <span v-if="maintenanceForm.errors.maintenance_title" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_title }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Estimated restoration time') }}
                            <input v-model="maintenanceForm.maintenance_estimated_restoration_time" type="datetime-local" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <span v-if="maintenanceForm.errors.maintenance_estimated_restoration_time" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_estimated_restoration_time }}</span>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Allowed IPs') }}
                            <input v-model="maintenanceForm.maintenance_allowed_ips" type="text" :placeholder="t('Comma-separated IP addresses')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <span v-if="maintenanceForm.errors.maintenance_allowed_ips" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_allowed_ips }}</span>
                        </label>
                    </div>

                    <div class="mt-5">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Maintenance message') }}</label>
                        <RichEditor v-model="maintenanceForm.maintenance_message" variant="full" />
                        <span v-if="maintenanceForm.errors.maintenance_message" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_message }}</span>
                    </div>

                    <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Background image') }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ t('Optional image used behind the standalone maintenance page.') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="cursor-pointer rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-300 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200">
                                    {{ t('Choose Image') }}
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="selectBackground">
                                </label>
                                <button v-if="backgroundPreview" type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50" @click="removeBackground">{{ t('Remove') }}</button>
                            </div>
                        </div>
                        <img v-if="backgroundPreview" :src="backgroundPreview" :alt="t('Maintenance background preview')" class="mt-4 h-36 w-full rounded-lg object-cover">
                        <span v-if="maintenanceForm.errors.maintenance_background_image" class="mt-1 block text-xs text-danger-600">{{ maintenanceForm.errors.maintenance_background_image }}</span>
                    </div>

                    <button type="button" :disabled="maintenanceForm.processing" class="mt-6 inline-flex items-center gap-2 rounded-lg btn-primary disabled:opacity-60" @click="saveMaintenance">
                        <svg v-if="maintenanceForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        <span>{{ maintenanceForm.processing ? t('Saving...') : t('Save Maintenance Settings') }}</span>
                    </button>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="mb-5 text-lg font-bold text-gray-900 dark:text-white">{{ t('Environment') }}</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-for="(value, key) in stats" :key="key" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div>
                                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t(statLabels[key]) }}</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ value }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl bg-gray-900 p-6 shadow-xl">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-white">{{ t('Recent Logs') }}</h2>
                        <span class="font-mono text-xs text-gray-500">storage/logs/laravel.log</span>
                    </div>
                    <div class="h-80 overflow-y-auto font-mono text-xs text-gray-400">
                        <div v-for="(log, index) in logs" :key="index" class="border-b border-white/5 py-1 last:border-none">
                            <span :class="log.includes('ERROR') ? 'text-danger-400' : (log.includes('INFO') ? 'text-primary-400' : 'text-gray-500')">{{ log }}</span>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="mb-5 text-lg font-bold text-gray-900 dark:text-white">{{ t('Service Status') }}</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Maintenance') }}</span>
                            <span :class="status.is_maintenance ? 'bg-amber-100 text-amber-700' : 'bg-primary-100 text-primary-700'" class="rounded-full px-3 py-1 text-xs font-semibold">{{ status.is_maintenance ? t('Enabled') : t('Disabled') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Queue worker') }}</span>
                            <span :class="status.queue_running ? 'bg-primary-100 text-primary-700' : 'bg-danger-100 text-danger-700'" class="rounded-full px-3 py-1 text-xs font-semibold">{{ status.queue_running ? t('Active') : t('Offline') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Scheduler') }}</span>
                            <span :class="status.scheduler_running ? 'bg-primary-100 text-primary-700' : 'bg-danger-100 text-danger-700'" class="rounded-full px-3 py-1 text-xs font-semibold">{{ status.scheduler_running ? t('Active') : t('Offline') }}</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-primary-100 bg-primary-50 p-6 text-primary-900 shadow-sm dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-100">
                    <h2 class="text-lg font-bold">{{ t('Recommendation') }}</h2>
                    <p class="mt-2 text-sm leading-relaxed">{{ t('Save maintenance content before enabling maintenance mode so Laravel can prerender the latest standalone page.') }}</p>
                </section>
            </aside>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="confirmOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="confirmOpen = false">
            <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ status.is_maintenance ? t('Disable maintenance mode?') : t('Enable maintenance mode?') }}</h2>
                    <p class="mt-2 text-sm text-gray-500">{{ status.is_maintenance ? t('Visitors will be able to access the platform again.') : t('Visitors will see the maintenance page while the admin panel remains available.') }}</p>
                </div>
                <div class="flex items-center justify-end gap-3 rounded-b-xl border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-700 dark:bg-surface-800">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-700" @click="confirmOpen = false">{{ t('Cancel') }}</button>
                    <button type="button" :disabled="toggleForm.processing" class="rounded-lg btn-primary disabled:opacity-60" @click="toggleMaintenance">
                        {{ status.is_maintenance ? t('Go Live') : t('Enter Maintenance') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
