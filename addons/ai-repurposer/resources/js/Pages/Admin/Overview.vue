<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface Stats {
    total_jobs: number
    queued_jobs: number
    processing_jobs: number
    completed_jobs: number
    failed_jobs: number
    completed_today: number
    total_outputs: number
    saved_outputs: number
    total_credits: number
    total_words: number
    by_format: Record<string, number>
}

interface RecentJob {
    ulid: string
    source_label: string
    source_type: string
    status: 'queued' | 'transcribing' | 'generating' | 'completed' | 'failed' | 'partial'
    outputs_count: number
    credits_deducted: number
    created_at: string
    updated_at: string
    progress_percent: number
    user?: {
        name: string
    } | null
}

const { t } = useTranslate()

const props = defineProps<{
    stats: Stats
    recentJobs: RecentJob[]
}>()

type BadgeTone = 'green' | 'blue' | 'amber' | 'red' | 'gray'

interface StatCard {
    label: string
    value: string | number
    icon: string
    tone: BadgeTone
    note: string
}

const statCards = computed<StatCard[]>(() => [
    { label: t('Total Jobs'), value: props.stats.total_jobs, icon: 'ti ti-refresh', tone: 'blue', note: t('All repurpose jobs created so far.') },
    { label: t('Queued'), value: props.stats.queued_jobs, icon: 'ti ti-clock', tone: 'amber', note: t('Waiting in the processing queue.') },
    { label: t('Processing'), value: props.stats.processing_jobs, icon: 'ti ti-loader-2', tone: 'blue', note: t('Currently transcribing or generating.') },
    { label: t('Completed'), value: props.stats.completed_jobs, icon: 'ti ti-circle-check', tone: 'green', note: t('Finished jobs ready for review.') },
    { label: t('Failed'), value: props.stats.failed_jobs, icon: 'ti ti-alert-triangle', tone: 'red', note: t('Jobs that need attention.') },
    { label: t('Completed Today'), value: props.stats.completed_today, icon: 'ti ti-calendar-check', tone: 'green', note: t('Work finished during the current day.') },
    { label: t('Total Outputs'), value: props.stats.total_outputs, icon: 'ti ti-files', tone: 'blue', note: t('Generated content formats.') },
    { label: t('Saved Outputs'), value: props.stats.saved_outputs, icon: 'ti ti-bookmark', tone: 'gray', note: t('Outputs sent to the core blog.') },
    { label: t('Credits Used'), value: Number(props.stats.total_credits).toFixed(0), icon: 'ti ti-coins', tone: 'amber', note: t('Credits deducted across jobs.') },
    { label: t('Words Processed'), value: Number(props.stats.total_words).toLocaleString(), icon: 'ti ti-align-left', tone: 'gray', note: t('Transcript and output word volume.') },
])

const formatCards = computed(() =>
    Object.entries(props.stats.by_format).map(([format, count]) => ({
        format,
        count,
        label: format
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase()),
    })),
)

const statusToneMap: Record<RecentJob['status'], BadgeTone> = {
    queued: 'amber',
    transcribing: 'blue',
    generating: 'blue',
    completed: 'green',
    failed: 'red',
    partial: 'gray',
}

function statusLabel(status: RecentJob['status']): string {
    return status.charAt(0).toUpperCase() + status.slice(1)
}

function toneClasses(tone: BadgeTone): string {
    return {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        gray: 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300',
    }[tone]
}
</script>

<template>
    <Head :title="t('Content Repurposer Overview')" />

    <div class="mx-auto max-w-7xl px-6 py-6">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Content Repurposer Overview') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Track job throughput, output volume, and the current format mix from a single admin view.') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    href="/admin/content-repurposer/settings"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-settings mr-2 text-base"></i>
                    {{ t('Settings') }}
                </Link>
            </div>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-lg" :class="toneClasses(card.tone)">
                            <i :class="card.icon"></i>
                        </span>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ card.value }}
                            </p>
                            <p class="mt-1 text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                {{ card.label }}
                            </p>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ card.note }}
                </p>
            </div>
        </section>

        <section class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,0.95fr)]">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ t('Recent Jobs') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Latest content repurposing runs and their progress.') }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-100 dark:border-surface-800">
                    <div class="max-h-[28rem] divide-y divide-gray-100 overflow-y-auto dark:divide-surface-800">
                        <div
                            v-for="job in props.recentJobs"
                            :key="job.ulid"
                            class="flex flex-col gap-3 px-4 py-4 transition hover:bg-primary-50/60 dark:hover:bg-primary-950/20 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ job.source_label }}
                                    </p>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="toneClasses(statusToneMap[job.status])">
                                        {{ statusLabel(job.status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ job.user?.name ?? t('System') }} · {{ job.outputs_count }} {{ t('outputs') }} · {{ job.progress_percent }}%
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400 sm:justify-end">
                                <span>{{ t('Credits') }}: {{ Number(job.credits_deducted).toFixed(0) }}</span>
                                <span>{{ t('Created') }}: {{ new Date(job.created_at).toLocaleString() }}</span>
                            </div>
                        </div>

                        <div
                            v-if="props.recentJobs.length === 0"
                            class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t('No repurposing jobs yet.') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <div class="mb-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ t('Format Mix') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Which output formats are being generated most often.') }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="item in formatCards"
                            :key="item.format"
                            class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ item.format }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                    {{ item.count }} {{ t('outputs') }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="formatCards.length === 0"
                            class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-700 dark:text-gray-400"
                        >
                            {{ t('No output formats generated yet.') }}
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ t('Operational Notes') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Use this dashboard to spot queue buildup, failed runs, and format coverage gaps quickly.') }}
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Queued') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ props.stats.queued_jobs }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Outputs Saved') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ props.stats.saved_outputs }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Words Processed') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ Number(props.stats.total_words).toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
