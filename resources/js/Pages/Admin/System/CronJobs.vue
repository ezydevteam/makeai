<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

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
    cron: CronStatus
    logs: string[]
}>()

const { t } = useTranslate()
const cronCopied = ref(false)
const cronRunForm = useForm({ task: '' })

const copyCronEntry = async () => {
    await navigator.clipboard.writeText(props.cron.required_entry)
    cronCopied.value = true
    window.setTimeout(() => { cronCopied.value = false }, 1600)
}

const runCronTask = (taskKey: string) => {
    cronRunForm.task = taskKey
    cronRunForm.post(route('admin.system.cron.run'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Cron Jobs')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Cron Jobs') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Cron jobs, environment info, and platform logs.') }}</p>
        </div>

        <div class="space-y-6">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
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

                <div v-if="cron.cpanel_detected" class="mt-5 rounded-xl border border-gray-200 bg-secondary-50 p-4 text-secondary-900 dark:border-secondary-900/40 dark:bg-secondary-900/20 dark:text-secondary-100">
                    <h3 class="text-sm font-semibold text-[#0058ff]">{{ t('cPanel detected') }}</h3>
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

            <section class="rounded-xl bg-gray-900 p-6 shadow-xl">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-white">{{ t('Recent Logs') }}</h2>
                    <span class="font-mono text-xs text-gray-500">storage/logs/laravel.log</span>
                </div>
                <div class="h-80 overflow-y-auto font-mono text-xs text-gray-400">
                    <div v-if="logs.length === 0" class="flex h-full flex-col items-center justify-center text-center">
                        <svg class="mb-2 h-8 w-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-sm font-medium text-gray-500">{{ t('No recent logs found.') }}</p>
                        <p class="mt-1 text-xs text-gray-600">{{ t('Logs will appear here after cron tasks or system events run.') }}</p>
                    </div>
                    <div v-else v-for="(log, index) in logs" :key="index" class="border-b border-white/5 py-1 last:border-none">
                        <span :class="log.includes('ERROR') ? 'text-danger-400' : (log.includes('INFO') ? 'text-primary-400' : 'text-gray-500')">{{ log }}</span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
