<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import type { Chart as ChartJs, ScriptableContext, TooltipItem } from 'chart.js'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTheme } from '@/Composables/useTheme'
import DashboardChecklist from '@themes/default/js/Components/DashboardChecklist.vue'
import ContextualTooltip from '@/Components/UI/ContextualTooltip.vue'

interface AuthUser {
    name: string
    credits?: number | null
}

interface ToolCard {
    name: string
    slug: string
    description: string
    icon: string
    color: string
    requires_pro: boolean
    is_new: boolean
}

interface TxItem {
    id: number
    amount: number
    balance_after: number
    type: string
    description: string
    created_at: string
}

interface RecentGeneration {
    ulid: string
    tool_slug: string | null
    tool_name: string
    output_preview: string | null
    created_at: string | null
}

interface RecentDoc {
    id: number
    title: string
    tool_slug: string
    word_count: number | null
    created_at: string
}

interface RecentLoginItem {
    id: number
    ip: string | null
    country: string | null
    city: string | null
    success: boolean
    created_at: string | null
}

interface PlanData {
    name: string
    slug: string
    is_free: boolean
    features: string[] | null
    subscription_status: string
    subscription_ends_at: string | null
    trial_ends_at: string | null
}

interface ReferralData {
    code: string | null
    earnings: number
    count: number
    link: string | null
}

interface DashboardStats {
    credits: number
    credits_used_month: number
    total_generations: number
    total_documents: number
    total_open_support_tickets: number
    lifetime_credits_used: number
}

interface PageProps {
    auth?: {
        user?: AuthUser | null
    }
    locale?: {
        code?: string
    }
    affiliateEnabled?: boolean
    isProAvailable?: boolean
    chartPeriod: string
    stats: DashboardStats
    ticketsEnabled?: boolean
    usageChart: { date: string; credits: number }[]
    recentTransactions: TxItem[]
    quickTools: ToolCard[]
    recentGenerations: RecentGeneration[]
    recentDocuments: RecentDoc[]
    recentLoginHistory: RecentLoginItem[]
    plan: PlanData | null
    referral: ReferralData
    isExtendedLicense?: boolean
}

interface PeriodOption {
    label: string
    value: string
}

interface StatCard {
    key: string
    label: string
    value: string
    hint: string
    icon: string
    iconClass: string
    cardClass: string
}

const page = usePage()
const { t } = useTranslate()
const { formatDate, formatRelative } = useDateFormat()
const { formatCompact, formatCredits, formatCurrency, formatNumber } = useNumberFormat()
const { isDark } = useTheme()

const pageProps = computed(() => page.props as unknown as PageProps)
const user = computed(() => pageProps.value.auth?.user ?? null)
const localeCode = computed(() => pageProps.value.locale?.code ?? 'en')
const affiliateEnabled = computed(() => Boolean(pageProps.value.affiliateEnabled))
const ticketsEnabled = computed(() => Boolean(pageProps.value.ticketsEnabled))
const isExtendedLicense = computed(() => Boolean((pageProps.value as any).isExtendedLicense))
// Subscriptions/billing exist only under Extended license with subscriptions on.
// Gate all plan/upgrade/billing UI on this so it never shows in Regular license.
const isProAvailable = computed(() => Boolean(pageProps.value.isProAvailable))
const userFirstName = computed(() => {
    const fullName = user.value?.name?.trim() ?? ''

    if (!fullName) {
        return ''
    }

    return fullName.split(/\s+/)[0] ?? ''
})

const stats = computed(() => pageProps.value.stats)
const recentTransactions = computed(() => pageProps.value.recentTransactions)
const quickTools = computed(() => pageProps.value.quickTools)
const recentGenerations = computed(() => pageProps.value.recentGenerations)
const recentDocuments = computed(() => pageProps.value.recentDocuments)
const recentLoginHistory = computed(() => pageProps.value.recentLoginHistory)
const plan = computed(() => pageProps.value.plan)
const referral = computed(() => pageProps.value.referral)
const usageChart = ref(pageProps.value.usageChart)
const chartPeriod = ref(pageProps.value.chartPeriod)
const chartLoading = ref(false)

watch(() => pageProps.value.usageChart, (value) => {
    usageChart.value = value
    void drawChart()
})

watch(() => pageProps.value.chartPeriod, (value) => {
    chartPeriod.value = value
})

const chartPeriodOptions = computed<PeriodOption[]>(() => [
    { label: t('Weekly'), value: '7d' },
    { label: t('Monthly'), value: 'month' },
    { label: t('Quarterly'), value: '90d' },
])

const chartTitle = computed(() => {
    if (chartPeriod.value === 'month') return t('Credit usage this month')
    if (chartPeriod.value === '90d') return t('Credit usage over the last 90 days')
    return t('Credit usage over the last 7 days')
})

const chartSummary = computed(() => {
    const values = usageChart.value.map((point) => point.credits)
    const total = values.reduce((sum, value) => sum + value, 0)
    const peak = values.length ? Math.max(...values) : 0
    const average = values.length ? total / values.length : 0

    return {
        total: formatCredits(total),
        peak: formatCredits(peak),
        average: formatCredits(average),
    }
})

const formatLoginLocation = (login: RecentLoginItem): string => {
    const parts = [login.city, login.country].filter((part): part is string => Boolean(part && part.trim()))

    if (parts.length > 0) {
        return parts.join(', ')
    }

    return t('Unknown location')
}

// Surfaced as a meta line under the credits balance. Referral earnings and
// documents already have their own stat cards, so this only carries the open
// support-ticket count — and only when the tickets feature is enabled.
const balanceMetaItem = computed(() => {
    if (!ticketsEnabled.value) {
        return null
    }

    return {
        label: t('Open support tickets'),
        value: formatNumber(stats.value.total_open_support_tickets),
    }
})

const planStatusLabel = computed(() => {
    if (!plan.value) return t('No plan')
    if (plan.value.subscription_status === 'active') return t('Active membership')
    if (plan.value.subscription_status === 'trialing') return t('Trial access')
    if (plan.value.is_free || plan.value.subscription_status === 'none') return t('Starter access')
    return plan.value.name
})

const planStatusClass = computed(() => {
    if (!plan.value) return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'
    if (plan.value.subscription_status === 'active') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
    if (plan.value.subscription_status === 'trialing') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
    return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'
})

const statCards = computed<StatCard[]>(() => {
    const showReferral = affiliateEnabled.value && isExtendedLicense.value
    const middleCard = showReferral ? {
        key: 'referral_earnings',
        label: t('Referral earnings'),
        value: formatCurrency(referral.value.earnings),
        hint: t('Earned from invite links'),
        icon: 'ti ti-cash',
        iconClass: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        cardClass: 'border-sky-100/80 bg-white/95 dark:border-sky-900/60 dark:!bg-surface-900',
    } : {
        key: 'documents',
        label: t('Documents'),
        value: formatNumber(stats.value.total_documents),
        hint: t('Saved outputs and drafts'),
        icon: 'ti ti-file-text',
        iconClass: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        cardClass: 'border-sky-100/80 bg-white/95 dark:border-sky-900/60 dark:!bg-surface-900',
    }

    return [
        {
            key: 'credits',
            label: t('Available credits'),
            value: formatCredits(stats.value.credits),
            hint: t('Ready for new AI workflows'),
            icon: 'ti ti-bolt',
            iconClass: 'bg-primary-500/12 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
            cardClass: 'border-primary-100/80 bg-white/95 dark:border-primary-900/60 dark:!bg-surface-900',
        },
        {
            key: 'monthly',
            label: t('Used this month'),
            value: formatCredits(stats.value.credits_used_month),
            hint: t('Healthy creator momentum'),
            icon: 'ti ti-chart-histogram',
            iconClass: 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            cardClass: 'border-violet-100/80 bg-white/95 dark:border-violet-900/60 dark:!bg-surface-900',
        },
        {
            key: 'lifetime',
            label: t('Lifetime usage'),
            value: formatCompact(stats.value.lifetime_credits_used, 2),
            hint: t('Believable production volume'),
            icon: 'ti ti-activity-heartbeat',
            iconClass: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            cardClass: 'border-amber-100/80 bg-white/95 dark:border-amber-900/60 dark:!bg-surface-900',
        },
        middleCard,
        {
            key: 'generations',
            label: t('Total generations'),
            value: formatNumber(stats.value.total_generations),
            hint: t('Every AI run on this account'),
            icon: 'ti ti-sparkles',
            iconClass: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            cardClass: 'border-emerald-100/80 bg-white/95 dark:border-emerald-900/60 dark:!bg-surface-900',
        },
    ]
})

const planFeatures = computed(() => plan.value?.features?.slice(0, 5) ?? [])

const txTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        purchase: t('Purchase'),
        usage: t('Usage'),
        refund: t('Refund'),
        bonus: t('Bonus'),
        referral: t('Referral'),
        admin_adjust: t('Adjustment'),
    }

    return map[type] ?? type
}

const txTypeClass = (amount: number) => (
    amount > 0
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
        : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'
)

const toolCardClass = (tool: ToolCard) => (
    tool.requires_pro
        ? 'border-violet-200/80 hover:border-violet-400 dark:border-violet-900/60'
        : 'border-primary-100/80 hover:border-primary-300 dark:border-primary-900/60'
)

const toolIconClass = (tool: ToolCard) => (
    tool.requires_pro
        ? 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
        : 'bg-primary-500/12 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
)

const copyReferralLink = () => {
    if (referral.value.link) {
        void navigator.clipboard.writeText(referral.value.link)
    }
}

const switchChartPeriod = async (period: string) => {
    // The buttons are disabled while loading, but guard the handler too — a keyboard repeat
    // or a double-tap would otherwise race two fetches and land whichever returns last.
    if (chartLoading.value) return

    chartLoading.value = true

    try {
        const response = await fetch(route('user.dashboard.chart.data', { period }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        if (response.ok) {
            const data = await response.json() as { chart: { date: string; credits: number }[] }
            usageChart.value = data.chart
            chartPeriod.value = period
            // Draw before releasing the overlay: drawChart awaits nextTick and a dynamic
            // import, so handing off to the watcher would lift the overlay a frame or two
            // before the new bars actually appear.
            await drawChart()
        }
    } finally {
        chartLoading.value = false
    }
}

const formatChartLabel = (date: string): string => {
    const dateObject = new Date(date)

    if (chartPeriod.value === '7d') {
        return new Intl.DateTimeFormat(localeCode.value, { weekday: 'short' }).format(dateObject)
    }

    return new Intl.DateTimeFormat(localeCode.value, { month: 'short', day: 'numeric' }).format(dateObject)
}

const canvasRef = ref<HTMLCanvasElement | null>(null)
let chartInstance: ChartJs<'line'> | null = null

const loadChartJs = async () => {
    const { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler } = await import('chart.js')
    Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler)

    return Chart
}

const hasChartData = computed(() => {
    return usageChart.value.length > 0 && usageChart.value.some(point => point.credits > 0)
})

const drawChart = async () => {
    await nextTick()

    if (!canvasRef.value) {
        return
    }

    if (!hasChartData.value) {
        if (chartInstance) {
            chartInstance.destroy()
            chartInstance = null
        }
        return
    }

    const context = canvasRef.value.getContext('2d')

    if (!context) {
        return
    }

    const labels = usageChart.value.map((point) => formatChartLabel(point.date))
    const data = usageChart.value.map((point) => point.credits)
    const maxValue = Math.max(...data, 1)
    const gridColor = isDark.value ? 'rgba(148, 163, 184, 0.22)' : 'rgba(148, 163, 184, 0.18)'
    const tickColor = isDark.value ? '#94a3b8' : '#64748b'
    const lineColor = isDark.value ? '#60a5fa' : '#1f75fe'
    const peakColor = isDark.value ? '#93c5fd' : '#1d4ed8'
    const fillTop = isDark.value ? 'rgba(96, 165, 250, 0.32)' : 'rgba(31, 117, 254, 0.22)'
    const fillBottom = isDark.value ? 'rgba(96, 165, 250, 0)' : 'rgba(31, 117, 254, 0)'

    if (chartInstance) {
        chartInstance.destroy()
    }

    const Chart = await loadChartJs()

    chartInstance = new Chart(context, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data,
                    borderColor: lineColor,
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    // Vertical gradient under the line. Built from the chart area rather than
                    // the canvas so it stays correct on resize; chartArea is undefined on the
                    // very first scriptable call, before layout has run.
                    backgroundColor: (chartContext: ScriptableContext<'line'>) => {
                        const { chart } = chartContext
                        const { ctx: canvasContext, chartArea } = chart

                        if (!chartArea) {
                            return fillTop
                        }

                        const gradient = canvasContext.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
                        gradient.addColorStop(0, fillTop)
                        gradient.addColorStop(1, fillBottom)

                        return gradient
                    },
                    // Points stay hidden except on the peak day, so the "Peak day" tile above
                    // the chart has something to point at.
                    pointRadius: (chartContext: ScriptableContext<'line'>) => (
                        data[chartContext.dataIndex] === maxValue ? 4 : 0
                    ),
                    pointBackgroundColor: peakColor,
                    pointBorderColor: peakColor,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: lineColor,
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // Points are hidden, so hovering anywhere in the column must still resolve to
            // that day rather than requiring a hit on the line itself.
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: (tooltipContext: TooltipItem<'line'>) => `${formatCredits(Number(tooltipContext.raw ?? 0))} ${t('credits')}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => formatNumber(Number(value)),
                        stepSize: Math.max(1, Math.ceil(maxValue / 4)),
                        color: tickColor,
                    },
                    grid: {
                        color: gridColor,
                    },
                    border: {
                        display: false,
                    },
                },
                x: {
                    border: {
                        display: false,
                    },
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: tickColor,
                    },
                },
            },
        },
    })
}

onMounted(() => {
    void drawChart()
})

// Redraws are explicit (period switch, Inertia prop update, theme toggle) rather than a
// watcher on usageChart — otherwise switching period drew the chart twice, once from the
// handler and once from the watcher.
watch(isDark, () => {
    void drawChart()
})
</script>

<template>
    <Head :title="t('Dashboard')" />

    <UserDashboardLayout>
        <div class="space-y-6">
            <div class="min-w-0 space-y-2">
                <h1 class="mb-1 break-words !font-semibold text-xl tracking-tight text-gray-950 dark:!text-white lg:text-2xl">
                    {{ t('Welcome back! :name', { name: userFirstName }) }} 👏
                </h1>
                <p class="max-w-2xl break-words text-sm text-gray-600 dark:text-gray-300 lg:text-base">
                    {{ t('Track your plan details, credit usage, and quick links to get started.') }}
                </p>
            </div>

            <DashboardChecklist />

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <ContextualTooltip tooltip-key="dashboard.stats" :content="t('Track credits, saved work, and momentum at a glance.')" full-width>
                    <article class="group h-full rounded-2xl border p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:shadow-lg" :class="statCards[0].cardClass">
                        <div class="flex flex-col items-start justify-between gap-4 w-full">
                            <div :class="statCards[0].iconClass" class="shrink-0 flex h-10 w-10 items-center justify-center rounded-2xl">
                                <i :class="statCards[0].icon" class="text-2xl"></i>
                            </div>
                            <div class="min-w-0 w-full">
                                <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 truncate">{{ statCards[0].label }}</p>
                                <p class="mt-3 font-heading text-xl font-bold text-gray-950 dark:!text-white tracking-tight sm:text-xl xl:text-2xl" :title="statCards[0].value">{{ statCards[0].value }}</p>
                                <p v-if="balanceMetaItem" class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-surface-800 dark:text-gray-400">
                                    <span class="truncate">{{ balanceMetaItem.label }}</span>
                                    <span class="shrink-0 font-semibold text-gray-700 dark:text-gray-300">{{ balanceMetaItem.value }}</span>
                                </p>
                            </div>
                        </div>
                    </article>
                </ContextualTooltip>

                <article v-for="card in statCards.slice(1)" :key="card.key" class="group rounded-2xl border p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:shadow-lg" :class="card.cardClass">
                    <div class="flex flex-col items-start justify-between gap-4 w-full">
                        <div :class="card.iconClass" class="shrink-0 flex h-10 w-10 items-center justify-center rounded-2xl">
                            <i :class="card.icon" class="text-2xl"></i>
                        </div>
                        <div class="min-w-0 w-full">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 truncate">{{ card.label }}</p>
                            <p class="mt-3 font-heading text-xl font-bold text-gray-950 dark:!text-white tracking-tight sm:text-xl xl:text-2xl" :title="card.value">{{ card.value }}</p>
                        </div>
                    </div>
                </article>
            </section>

            <section>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 space-y-2">
                                <h2 class="mb-1 font-heading text-xl font-bold text-gray-950 dark:!text-white">{{ chartTitle }}</h2>
                                <p class="mb-0 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Credit usage is calculated from real wallet activity for this account.') }}</p>
                            </div>

                            <div class="inline-flex flex-wrap gap-1 rounded-full border border-gray-200 bg-gray-50 p-1 dark:border-surface-700 dark:bg-surface-950">
                                <button
                                    v-for="option in chartPeriodOptions"
                                    :key="option.value"
                                    type="button"
                                    :disabled="chartLoading"
                                    class="rounded-full px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                                    :class="chartPeriod === option.value ? 'bg-white text-gray-900 shadow-sm dark:bg-surface-800 dark:!text-white' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                                    @click="switchChartPeriod(option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 px-6 pt-5 md:grid-cols-3">
                        <div class="rounded-2xl border border-primary-100 bg-primary-50/60 p-4 dark:border-primary-900/60 dark:bg-primary-900/15">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Period total') }}</p>
                            <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ chartSummary.total }}</p>
                        </div>
                        <div class="rounded-2xl border border-violet-100 bg-violet-50/60 p-4 dark:border-violet-900/60 dark:bg-violet-900/15">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">{{ t('Peak day') }}</p>
                            <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ chartSummary.peak }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-900/15">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">{{ t('Daily average') }}</p>
                            <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ chartSummary.average }}</p>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="relative h-[320px]">
                            <div v-if="!hasChartData" class="flex h-full flex-col items-center justify-center border border-dashed border-gray-200 dark:border-surface-800 rounded-2xl bg-gray-50/30 dark:bg-surface-900/30">
                                <i class="ti ti-chart-bar-off text-3xl text-gray-300 dark:text-gray-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('No data found') }}</p>
                            </div>
                            <canvas v-else ref="canvasRef"></canvas>
                            <!-- Loading overlay — covers the plot only, so the heading, the
                                 summary tiles and the period switch stay legible while the new
                                 range loads. -->
                            <div v-if="chartLoading" class="absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-[2px] transition-all dark:bg-surface-900/70">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="ti ti-loader animate-spin text-2xl text-primary-500"></i>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Updating...') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6" :class="isProAvailable ? 'xl:grid-cols-2' : ''">
                <section v-if="isProAvailable" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <div class="px-6 pt-6 bg-white dark:bg-surface-900">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Current plan') }}</p>
                                <h2 class="mt-2 break-words font-heading text-xl font-bold text-gray-950 dark:!text-white">{{ plan?.name ?? t('No active plan') }}</h2>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span :class="planStatusClass" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold">
                                        {{ planStatusLabel }}
                                    </span>
                                    <span v-if="plan?.trial_ends_at" class="text-xs text-gray-500 dark:text-gray-400">{{ t('Trial ends :date', { date: formatDate(plan.trial_ends_at) }) }}</span>
                                    <span v-else-if="plan?.subscription_ends_at" class="text-xs text-gray-500 dark:text-gray-400">{{ t('Access reserved through :date', { date: formatDate(plan.subscription_ends_at) }) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 px-6 pb-6 pt-4">

                        <div v-if="planFeatures.length" class="space-y-3">
                            <div v-for="feature in planFeatures" :key="feature" class="flex items-start gap-3 rounded-2xl border border-gray-100 bg-gray-50/80 px-4 py-2 dark:border-surface-800 dark:bg-surface-950/60">
                                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                    <i class="ti ti-check text-sm"></i>
                                </div>
                                <p class="break-words text-sm text-gray-600 dark:text-gray-300">{{ feature }}</p>
                            </div>
                        </div>

                        <Link
                            v-if="!plan || plan.is_free || plan.subscription_status === 'none'"
                            :href="route('pricing')"
                            class="btn-primary"
                        >
                            <i class="ti ti-arrow-up"></i>
                            {{ t('Upgrade plan') }}
                        </Link>
                        <Link
                            v-else
                            :href="route('user.dashboard.billing')"
                            class="flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:!border-gray-200 hover:!bg-gray-50 hover:text-gray-700 dark:border-surface-700 dark:bg-surface-900/20 dark:text-gray-200 dark:hover:!border-gray-800 dark:hover:!bg-surface-900/30 dark:hover:!text-gray-300"
                        >
                            <i class="ti ti-credit-card"></i>
                            {{ t('Manage billing') }}
                        </Link>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex flex-col gap-2 border-b border-gray-100 px-6 py-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Recent login history') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('The last 5 login attempts for this account.') }}</p>
                        </div>
                        <!-- Anchored at the Login History section: the privacy page's own
                             Session Management block lists LIVE sessions (usually just the
                             current one), which is not what this panel is showing. -->
                        <Link :href="route('user.dashboard.privacy') + '#login-history'" class="shrink-0 text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                            {{ t('View all') }}
                        </Link>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-if="recentLoginHistory.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No login history yet.') }}</div>
                        <div v-for="login in recentLoginHistory" :key="login.id" class="flex flex-col gap-3 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span :class="login.success ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'" class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em]">
                                        {{ login.success ? t('Success') : t('Failed') }}
                                    </span>
                                    <p class="break-all text-sm font-semibold text-gray-950 dark:text-white">{{ login.ip || t('Unknown IP') }}</p>
                                </div>
                                <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ formatLoginLocation(login) }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:!bg-surface-800 dark:text-gray-300">
                                {{ login.created_at ? formatRelative(login.created_at) : t('Unknown time') }}
                            </span>
                        </div>
                    </div>
                </section>
            </section>

            <section>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <ContextualTooltip tooltip-key="dashboard.quick-tools" :content="t('Show buyers how quickly a user can jump into meaningful AI workflows from the dashboard.')">
                        <h2 class="font-heading text-xl font-bold text-gray-950 dark:!text-white">{{ t('Quick AI tools') }}</h2>
                    </ContextualTooltip>
                    <Link :href="route('ai.tools.index')" class="text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                        {{ t('View all') }}
                    </Link>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="tool in quickTools"
                        :key="tool.slug"
                        :href="route('ai.tools.show', tool.slug)"
                        class="group rounded-2xl border bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-1 hover:shadow-xl dark:bg-surface-900"
                        :class="toolCardClass(tool)"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div :class="toolIconClass(tool)" class="flex h-12 w-12 items-center justify-center rounded-2xl">
                                <i :class="tool.icon || 'ti ti-wand'" class="text-2xl"></i>
                            </div>
                            <div class="min-w-0 flex flex-wrap items-center justify-end gap-2">
                                <span v-if="tool.is_new" class="rounded-full bg-primary-500/12 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ t('New') }}</span>
                                <span v-if="tool.requires_pro" class="rounded-full bg-violet-500/12 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ t('Pro') }}</span>
                            </div>
                        </div>

                        <div class="mt-5 min-w-0 space-y-2">
                            <h3 class="break-words font-heading text-lg font-bold text-gray-950 transition group-hover:text-primary-700 dark:!text-white dark:group-hover:text-primary-500">{{ tool.name }}</h3>
                            <p class="break-words text-sm leading-6 text-gray-500 dark:text-gray-400">{{ tool.description }}</p>
                        </div>

                        <div class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary-700 dark:text-primary-300">
                            {{ t('Open workflow') }}
                            <i class="ti ti-arrow-up-right text-base"></i>
                        </div>
                    </Link>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Recent generations') }}</h2>
                            <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Your latest AI runs across every tool.') }}</p>
                        </div>
                        <Link :href="route('user.dashboard.history.index')" class="shrink-0 text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                            {{ t('View all') }}
                        </Link>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-if="recentGenerations.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No generations yet.') }}</div>
                        <div v-for="generation in recentGenerations" :key="generation.ulid" class="flex items-start justify-between gap-4 px-6 py-4">
                            <div class="min-w-0 flex-1">
                                <Link v-if="generation.tool_slug" :href="route('ai.tools.show', { slug: generation.tool_slug })" class="break-words font-semibold text-gray-950 transition hover:text-primary-700 dark:!text-gray-200 dark:hover:!text-primary-400">
                                    {{ generation.tool_name }}
                                </Link>
                                <p v-else class="break-words font-semibold text-gray-950 dark:!text-gray-200">{{ generation.tool_name }}</p>
                                <p class="mt-1 break-words text-sm text-gray-500 line-clamp-1 dark:text-gray-400">{{ generation.output_preview }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:!bg-surface-800 dark:text-gray-300">
                                {{ generation.created_at ? formatRelative(generation.created_at) : t('Just now') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Recent documents') }}</h2>
                            <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Saved deliverables that make the dashboard feel alive and valuable.') }}</p>
                        </div>
                        <Link :href="route('user.dashboard.documents.index')" class="shrink-0 text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                            {{ t('View all') }}
                        </Link>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-if="recentDocuments.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No documents yet.') }}</div>
                        <div v-for="document in recentDocuments" :key="document.id" class="flex items-start justify-between gap-4 px-6 py-4">
                            <div class="min-w-0 flex-1">
                                <p class="break-words font-semibold text-gray-950 dark:!text-gray-200">{{ document.title }}</p>
                                <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ document.tool_slug }} · {{ formatNumber(document.word_count ?? 0) }} {{ t('words') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:!bg-surface-800 dark:text-gray-300">
                                {{ formatRelative(document.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-6 py-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Wallet activity') }}</h2>
                        <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Your latest credit purchases, usage, and balance updates.') }}</p>
                    </div>
                    <!-- Anchored at the wallet ledger: the Usage page's charts count
                         generations, which is not what this panel is listing. -->
                    <Link :href="route('user.dashboard.usage.index') + '#wallet-activity'" class="text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                        {{ t('View all') }}
                    </Link>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-if="recentTransactions.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No transactions yet.') }}</div>
                    <div v-for="transaction in recentTransactions" :key="transaction.id" class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="break-words font-semibold text-gray-950 dark:!text-gray-200">{{ transaction.description }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span :class="txTypeClass(transaction.amount)" class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em]">
                                    {{ txTypeLabel(transaction.type) }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-400">{{ formatDate(transaction.created_at) }}</span>
                            </div>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <p :class="transaction.amount > 0 ? '!text-emerald-600 dark:!text-emerald-300' : '!text-rose-600 dark:!text-rose-300'" class="font-heading text-lg font-bold">
                                {{ transaction.amount > 0 ? '+' : '' }}{{ formatCredits(transaction.amount) }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-400">{{ t('Balance: :value', { value: formatCredits(transaction.balance_after) }) }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </UserDashboardLayout>
</template>
