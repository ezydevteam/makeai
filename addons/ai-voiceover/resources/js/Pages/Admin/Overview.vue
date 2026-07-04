<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

interface ComparisonData {
    label: string
    type: 'up' | 'down' | 'neutral'
}

interface StatObject {
    value: number
    comparison: ComparisonData
}

interface Stats {
    total_episodes: StatObject
    processing: StatObject
    completed_today: StatObject
    failed: StatObject
    total_storage: StatObject
    credits_used_today: StatObject
    by_provider: Record<string, number>
}

const props = defineProps<{
    stats: Stats
}>()



const providerRows = computed(() =>
    Object.entries(props.stats.by_provider)
        .map(([provider, count]) => ({
            provider,
            count,
            label: provider
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (char) => char.toUpperCase()),
        }))
        .sort((a, b) => b.count - a.count),
)

const hasFailures = computed(() => props.stats.failed.value > 0)

function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return t('0 MB')
    }

    const mb = bytes / (1024 * 1024)

    if (mb >= 1024) {
        return `${(mb / 1024).toFixed(1)} GB`
    }

    return `${mb.toFixed(1)} MB`
}
</script>

<template>
    <Head :title="t('Voiceover Overview')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Voiceover Overview') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor generation activity, storage usage, and provider distribution from a single admin dashboard.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    :href="route('addon.vo.admin.settings')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-adjustments-horizontal text-base"></i>
                    {{ t('Settings') }}
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <StatsCard
                :title="t('Total Episodes')"
                :value="stats.total_episodes.value"
                :comparison="stats.total_episodes.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_episodes.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-headphones text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Processing')"
                :value="stats.processing.value"
                :comparison="stats.processing.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.processing.comparison.type"
                :color="stats.processing.value > 0 ? 'warning' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-loader text-lg animate-spin"></i>
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
                    <i class="ti ti-circle-check text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Failed')"
                :value="stats.failed.value"
                :comparison="stats.failed.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.failed.comparison.type"
                :color="stats.failed.value > 0 ? 'danger' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-alert-triangle text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Credits Used Today')"
                :value="stats.credits_used_today.value"
                :comparison="stats.credits_used_today.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.credits_used_today.comparison.type"
                color="pink"
            >
                <template #icon>
                    <i class="ti ti-coins text-lg"></i>
                </template>
            </StatsCard>

        </div>

        <section class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ t('Provider Breakdown') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Episode volume grouped by TTS provider.') }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-100 dark:border-surface-800">
                    <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-surface-800">
                        <div
                            v-for="row in providerRows"
                            :key="row.provider"
                            class="flex items-center justify-between px-4 py-3 transition hover:bg-primary-50/60 dark:hover:bg-primary-950/20"
                        >
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ row.label }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Provider key') }}: {{ row.provider }}
                                </p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-surface-800 dark:text-gray-300">
                                {{ row.count }} {{ t('episodes') }}
                            </span>
                        </div>

                        <div
                            v-if="providerRows.length === 0"
                            class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t('No provider activity yet.') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ t('Quick Notes') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('This panel reflects the current voiceover workload and system usage.') }}
                </p>

                <div class="mt-4 space-y-3">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ t('Processing Queue') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ props.stats.processing.value }} {{ t('episodes in progress') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ t('Completion Today') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ props.stats.completed_today.value }} {{ t('episodes completed') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ t('Storage Footprint') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ formatBytes(props.stats.total_storage.value) }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
