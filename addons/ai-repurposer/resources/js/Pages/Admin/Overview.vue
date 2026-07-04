<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

interface ComparisonData {
    label: string
    type: 'up' | 'down' | 'neutral'
}

interface StatObject {
    value: number
    comparison: ComparisonData
}

interface Stats {
    total_jobs: StatObject
    queued_jobs: StatObject
    processing_jobs: StatObject
    completed_jobs: StatObject
    failed_jobs: StatObject
    completed_today: StatObject
    total_outputs: StatObject
    saved_outputs: StatObject
    total_credits: StatObject
    total_words: StatObject
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



const formatCards = computed(() =>
    Object.entries(props.stats.by_format).map(([format, count]) => ({
        format,
        count,
        label: format
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase()),
    })),
)

type BadgeTone = 'green' | 'blue' | 'amber' | 'red' | 'gray'

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

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <StatsCard
                :title="t('Total Jobs')"
                :value="stats.total_jobs.value"
                :comparison="stats.total_jobs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_jobs.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-refresh text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Queued')"
                :value="stats.queued_jobs.value"
                :comparison="stats.queued_jobs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.queued_jobs.comparison.type"
                :color="stats.queued_jobs.value > 0 ? 'warning' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-clock text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Processing')"
                :value="stats.processing_jobs.value"
                :comparison="stats.processing_jobs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.processing_jobs.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-loader text-lg animate-spin"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Completed')"
                :value="stats.completed_jobs.value"
                :comparison="stats.completed_jobs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.completed_jobs.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-circle-check text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Failed')"
                :value="stats.failed_jobs.value"
                :comparison="stats.failed_jobs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.failed_jobs.comparison.type"
                :color="stats.failed_jobs.value > 0 ? 'danger' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-alert-triangle text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Completed Today')"
                :value="stats.completed_today.value"
                :comparison="stats.completed_today.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.completed_today.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-calendar-check text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Total Outputs')"
                :value="stats.total_outputs.value"
                :comparison="stats.total_outputs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_outputs.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-files text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Saved Outputs')"
                :value="stats.saved_outputs.value"
                :comparison="stats.saved_outputs.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.saved_outputs.comparison.type"
                color="accent"
            >
                <template #icon>
                    <i class="ti ti-bookmark text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Credits Used')"
                :value="Number(stats.total_credits.value).toFixed(0)"
                :comparison="stats.total_credits.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_credits.comparison.type"
                color="pink"
            >
                <template #icon>
                    <i class="ti ti-coins text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Words Processed')"
                :value="Number(stats.total_words.value).toLocaleString()"
                :comparison="stats.total_words.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_words.comparison.type"
                color="accent"
            >
                <template #icon>
                    <i class="ti ti-align-left text-lg"></i>
                </template>
            </StatsCard>
        </div>

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
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ props.stats.queued_jobs.value }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Outputs Saved') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ props.stats.saved_outputs.value }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Words Processed') }}</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ Number(props.stats.total_words.value).toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
