<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

interface Stats {
    total_episodes: number
    processing: number
    completed_today: number
    failed: number
    total_storage: number
    credits_used_today: number
    by_provider: Record<string, number>
}

const props = defineProps<{
    stats: Stats
}>()

type StatCard = {
    label: string
    value: string | number
    icon: string
    iconClass: string
    toneClass: string
}

const statCards = computed<StatCard[]>(() => [
    {
        label: t('Total Episodes'),
        value: props.stats.total_episodes,
        icon: 'ti ti-headphones',
        iconClass: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        toneClass: 'border-blue-100 dark:border-blue-900/30',
    },
    {
        label: t('Processing'),
        value: props.stats.processing,
        icon: 'ti ti-loader-2',
        iconClass: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
        toneClass: 'border-amber-100 dark:border-amber-900/30',
    },
    {
        label: t('Completed Today'),
        value: props.stats.completed_today,
        icon: 'ti ti-circle-check',
        iconClass: 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
        toneClass: 'border-green-100 dark:border-green-900/30',
    },
    {
        label: t('Failed'),
        value: props.stats.failed,
        icon: 'ti ti-alert-triangle',
        iconClass: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        toneClass: 'border-red-100 dark:border-red-900/30',
    },
    {
        label: t('Credits Used Today'),
        value: props.stats.credits_used_today,
        icon: 'ti ti-coins',
        iconClass: 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
        toneClass: 'border-violet-100 dark:border-violet-900/30',
    },
    {
        label: t('Total Storage'),
        value: formatBytes(props.stats.total_storage),
        icon: 'ti ti-database',
        iconClass: 'bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400',
        toneClass: 'border-sky-100 dark:border-sky-900/30',
    },
])

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

const hasFailures = computed(() => props.stats.failed > 0)

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

    <div class="mx-auto max-w-7xl px-6 py-6">
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

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-2xl border bg-white p-5 shadow-sm transition dark:border-surface-800 dark:bg-gray-900"
                :class="card.toneClass"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-lg" :class="card.iconClass">
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

                    <span
                        v-if="card.label === t('Failed') && hasFailures"
                        class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-300"
                    >
                        {{ t('Needs review') }}
                    </span>
                </div>
            </div>
        </section>

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
                            {{ props.stats.processing }} {{ t('episodes in progress') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ t('Completion Today') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ props.stats.completed_today }} {{ t('episodes completed') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <p class="text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                            {{ t('Storage Footprint') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ formatBytes(props.stats.total_storage) }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
