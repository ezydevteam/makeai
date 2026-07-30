<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

/** Must match ImageProAdminController::PERIODS. */
type Period = '1d' | '7d' | '30d' | 'all'

/** Same contract the core admin dashboard's stat cards use. Null on `all` — no prior span. */
interface Comparison {
    label: string
    type: 'up' | 'down' | 'neutral'
    // Only sent for "lower is better" metrics, where the arrow and the colour disagree.
    direction?: 'up' | 'down'
}

interface Stat {
    value: number
    comparison: Comparison | null
}

interface Stats {
    jobs: Stat
    images: Stat
    credits: Stat
    failure_rate: Stat
}

interface OperationRow {
    /** Registry key — kept for :key, never displayed. */
    operation: string
    /** Human name, resolved server-side from OperationRegistry. */
    label: string
    count: number
    credits: number
}

interface ModelRow {
    /** Raw slug — kept for :key, never displayed. */
    model: string
    /** Human name, resolved server-side from ModelCatalog. */
    label: string
    count: number
}

interface FailureRow {
    ulid?: string | null
    operation: string | null
    model: string | null
    provider: string | null
    error: string | null
    created_at: string | null
    user_name?: string | null
    user_email?: string | null
}

const props = defineProps<{
    period: Period
    stats: Stats
    byOperation: OperationRow[]
    byModel: ModelRow[]
    recentFailures: FailureRow[]
}>()

const settingsRoute = route('addon.aip.admin.settings')

/* ── Reporting period ────────────────────────────────────── */
const PERIODS: { value: Period; short: string }[] = [
    { value: '1d', short: '1D' },
    { value: '7d', short: '7D' },
    { value: '30d', short: '30D' },
    { value: 'all', short: 'All' },
]

const loading = ref(false)

/** Spelled out for the section subtitles, so no figure is left unlabelled. */
const periodLabel = computed(() => {
    switch (props.period) {
        case '1d': return t('today')
        case '7d': return t('last 7 days')
        case 'all': return t('all time')
        default: return t('last 30 days')
    }
})

/** What the percentage change is measured against — the equally-long span before this one. */
const comparisonDetail = computed(() => {
    switch (props.period) {
        case '1d': return t('vs yesterday')
        case '7d': return t('vs previous 7 days')
        case 'all': return ''
        default: return t('vs previous 30 days')
    }
})

function selectPeriod(period: Period): void {
    if (period === props.period) return

    // Partial reload: the header and layout stay put, only the figures are refetched.
    router.get(
        route('addon.aip.admin.overview'),
        { period },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['period', 'stats', 'byOperation', 'byModel', 'recentFailures'],
            onStart: () => { loading.value = true },
            onFinish: () => { loading.value = false },
        },
    )
}

const maxOperationCount = computed(() =>
    Math.max(1, ...props.byOperation.map((r) => r.count)),
)
const maxModelCount = computed(() =>
    Math.max(1, ...props.byModel.map((r) => r.count)),
)

function pct(count: number, max: number): number {
    return Math.max(2, Math.round((count / max) * 100))
}

function formatDate(value: string | null): string {
    if (!value) return ''
    const d = new Date(value)
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString()
}
</script>

<template>
    <Head :title="t('AI Image Pro Overview')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <!-- Header -->
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Image Pro') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Ai image all operations overview.') }}
                    <span class="font-medium text-gray-600 dark:text-gray-300">
                        {{ t('Showing: :period', { period: periodLabel }) }}
                    </span>
                </p>
            </div>
            <div class="shrink-0 flex flex-wrap items-center gap-3">
                <!-- Reporting period. Drives every figure on the page, not just the tiles. -->
                <div
                    class="inline-flex items-center rounded-xl p-1 border border-gray-100 bg-white p-0.5 shadow-sm dark:border-surface-700 dark:bg-gray-900"
                    role="group"
                    :aria-label="t('Reporting period')"
                    :aria-busy="loading"
                >
                    <button
                        v-for="option in PERIODS"
                        :key="option.value"
                        type="button"
                        class="rounded-lg px-3 py-1.75 text-xs font-semibold transition disabled:cursor-not-allowed"
                        :class="option.value === period
                            ? 'bg-primary-100 text-primary-600 shadow-sm dark:bg-primary-900/80 dark:text-primary-300'
                            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white'"
                        :disabled="loading"
                        :aria-pressed="option.value === period"
                        @click="selectPeriod(option.value)"
                    >
                        {{ option.short }}
                    </button>
                </div>

                <Link :href="settingsRoute" class="btn-primary-admin">
                    <i class="ti ti-settings"></i> {{ t('Settings') }}
                </Link>
            </div>
        </div>

        <!-- Stat tiles. The period lives in the switcher above, not in every label — so the
             titles stay clean and the change line carries the comparison instead. -->
        <div class="grid gap-4 transition-opacity sm:grid-cols-2 xl:grid-cols-4" :class="{ 'opacity-60': loading }">
            <StatsCard
                :title="t('Jobs')"
                :value="stats.jobs.value"
                :comparison="stats.jobs.comparison?.label"
                :comparison-detail="comparisonDetail"
                :comparison-type="stats.jobs.comparison?.type"
                color="primary"
            >
                <template #icon><i class="ti ti-bolt text-lg"></i></template>
            </StatsCard>
            <StatsCard
                :title="t('Images')"
                :value="stats.images.value"
                :comparison="stats.images.comparison?.label"
                :comparison-detail="comparisonDetail"
                :comparison-type="stats.images.comparison?.type"
                color="success"
            >
                <template #icon><i class="ti ti-photo text-lg"></i></template>
            </StatsCard>
            <StatsCard
                :title="t('Credits')"
                :value="stats.credits.value"
                :comparison="stats.credits.comparison?.label"
                :comparison-detail="comparisonDetail"
                :comparison-type="stats.credits.comparison?.type"
                color="pink"
            >
                <template #icon><i class="ti ti-coin text-lg"></i></template>
            </StatsCard>
            <StatsCard
                :title="t('Failure Rate')"
                :value="`${Number(stats.failure_rate.value).toFixed(1)}%`"
                :comparison="stats.failure_rate.comparison?.label"
                :comparison-detail="comparisonDetail"
                :comparison-type="stats.failure_rate.comparison?.type"
                :comparison-direction="stats.failure_rate.comparison?.direction"
                :color="stats.failure_rate.value >= 10 ? 'danger' : 'warning'"
            >
                <template #icon><i class="ti ti-alert-triangle text-lg"></i></template>
            </StatsCard>
        </div>

        <!-- Breakdowns -->
        <div class="mt-5 grid gap-5 xl:grid-cols-2">
            <!-- By operation -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('By Operation') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Jobs and credits spent per operation.') }}</p>
                </div>

                <div v-if="byOperation.length" class="space-y-3">
                    <div v-for="row in byOperation" :key="row.operation">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ row.label }}</span>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ t(':n jobs', { n: row.count }) }}
                                <span v-if="row.credits" class="text-gray-400">· {{ row.credits }} {{ t('credits') }}</span>
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800">
                            <div class="h-full rounded-full bg-primary-500" :style="{ width: `${pct(row.count, maxOperationCount)}%` }"></div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                    {{ t('No operation activity yet.') }}
                </div>
            </section>

            <!-- By model -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('By Model') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Generation volume per image model.') }}</p>
                </div>

                <div v-if="byModel.length" class="space-y-3">
                    <div v-for="row in byModel" :key="row.model">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="truncate font-medium text-gray-700 dark:text-gray-200">{{ row.label }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ row.count }}</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800">
                            <div class="h-full rounded-full bg-accent-500" :style="{ width: `${pct(row.count, maxModelCount)}%` }"></div>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                    {{ t('No model activity yet.') }}
                </div>
            </section>
        </div>

        <!-- Recent failures -->
        <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Recent Failures') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Latest failed jobs and their errors') }}</p>
            </div>

            <div v-if="recentFailures.length" class="overflow-x-auto rounded-xl border border-gray-100 dark:border-surface-800">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50 dark:bg-surface-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Date / Time') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Operation') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Model / Provider') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-surface-800 dark:bg-gray-900">
                        <tr v-for="(row, i) in recentFailures" :key="row.ulid ?? i" class="hover:bg-gray-50/60 dark:hover:bg-surface-800/60">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(row.created_at) }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ row.operation ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                <span>{{ row.model ?? '—' }}</span>
                                <span v-if="row.provider" class="text-gray-400"> · {{ row.provider }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                <div v-if="row.user_name || row.user_email">
                                    <div class="font-medium text-gray-700 dark:text-gray-200">{{ row.user_name ?? '' }}</div>
                                    <div class="text-xs text-gray-400">{{ row.user_email ?? '' }}</div>
                                </div>
                                <span v-else class="text-gray-400">{{ t('Guest') }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-danger-600 dark:text-danger-400">
                                <span class="line-clamp-2 max-w-md">{{ row.error ?? t('Unknown error') }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-10 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                <i class="ti ti-circle-check mb-2 block text-2xl text-emerald-500"></i>
                {{ t('No recent failures — everything is running smoothly.') }}
            </div>
        </section>
    </div>
</template>
