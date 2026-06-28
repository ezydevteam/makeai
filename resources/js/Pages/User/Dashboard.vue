<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import type { Chart as ChartJs, ScriptableContext, TooltipItem } from 'chart.js'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTheme } from '@/Composables/useTheme'
import DashboardChecklist from '@/Components/DashboardChecklist.vue'
import ContextualTooltip from '@/Components/ContextualTooltip.vue'

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

interface RecentConv {
    id: number
    ulid: string
    title: string
    model: string
    message_count: number
    last_message_at: string | null
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
    daily_limit: number | null
    monthly_limit: number | null
}

interface ReferralData {
    code: string | null
    earnings: number
    count: number
    link: string | null
}

interface DashboardStats {
    credits: number
    credits_used_today: number
    credits_used_month: number
    total_conversations: number
    total_documents: number
    total_favorites: number
    total_collections: number
    total_open_support_tickets: number
    active_days: number
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
    chartPeriod: string
    stats: DashboardStats
    ticketsEnabled?: boolean
    usageChart: { date: string; credits: number }[]
    recentTransactions: TxItem[]
    quickTools: ToolCard[]
    recentConversations: RecentConv[]
    recentDocuments: RecentDoc[]
    recentLoginHistory: RecentLoginItem[]
    plan: PlanData | null
    referral: ReferralData
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
const recentConversations = computed(() => pageProps.value.recentConversations)
const recentDocuments = computed(() => pageProps.value.recentDocuments)
const recentLoginHistory = computed(() => pageProps.value.recentLoginHistory)
const plan = computed(() => pageProps.value.plan)
const referral = computed(() => pageProps.value.referral)
const usageChart = ref(pageProps.value.usageChart)
const chartPeriod = ref(pageProps.value.chartPeriod)
const chartLoading = ref(false)

watch(() => pageProps.value.usageChart, (value) => {
    usageChart.value = value
})

watch(() => pageProps.value.chartPeriod, (value) => {
    chartPeriod.value = value
})

const chartPeriodOptions = computed<PeriodOption[]>(() => [
    { label: t('Weekly'), value: '7d' },
    { label: t('Monthly'), value: 'month' },
    { label: t('Quaterly'), value: '90d' },
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

const balanceMetaItem = computed(() => {
    if (affiliateEnabled.value) {
        return {
            label: t('Referral earnings'),
            value: formatCurrency(referral.value.earnings),
        }
    }

    if (ticketsEnabled.value) {
        return {
            label: t('Open support tickets'),
            value: formatNumber(stats.value.total_open_support_tickets),
        }
    }

    return {
        label: t('Documents saved'),
        value: formatNumber(stats.value.total_documents),
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

const statCards = computed<StatCard[]>(() => [
    {
        key: 'credits',
        label: t('Available credits'),
        value: formatCredits(stats.value.credits),
        hint: t('Ready for new AI workflows'),
        icon: 'ti ti-bolt',
        iconClass: 'bg-primary-500/12 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
        cardClass: 'border-primary-100/80 bg-white/95 dark:border-primary-900/60 dark:!bg-gray-900',
    },
    {
        key: 'monthly',
        label: t('Used this month'),
        value: formatCredits(stats.value.credits_used_month),
        hint: t('Healthy creator momentum'),
        icon: 'ti ti-chart-histogram',
        iconClass: 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        cardClass: 'border-violet-100/80 bg-white/95 dark:border-violet-900/60 dark:!bg-gray-900',
    },
    {
        key: 'documents',
        label: t('Documents'),
        value: formatNumber(stats.value.total_documents),
        hint: t('Saved outputs and drafts'),
        icon: 'ti ti-file-text',
        iconClass: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        cardClass: 'border-sky-100/80 bg-white/95 dark:border-sky-900/60 dark:!bg-gray-900',
    },
    {
        key: 'conversations',
        label: t('Conversations'),
        value: formatNumber(stats.value.total_conversations),
        hint: t('Live ideation sessions'),
        icon: 'ti ti-message-2',
        iconClass: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        cardClass: 'border-emerald-100/80 bg-white/95 dark:border-emerald-900/60 dark:!bg-gray-900',
    },
    {
        key: 'lifetime',
        label: t('Lifetime usage'),
        value: formatCompact(stats.value.lifetime_credits_used),
        hint: t('Believable production volume'),
        icon: 'ti ti-activity-heartbeat',
        iconClass: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        cardClass: 'border-amber-100/80 bg-white/95 dark:border-amber-900/60 dark:!bg-gray-900',
    },
])

const planFeatures = computed(() => plan.value?.features?.slice(0, 4) ?? [])

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
let chartInstance: ChartJs<'bar'> | null = null

const loadChartJs = async () => {
    const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler } = await import('chart.js')
    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler)

    return Chart
}

const drawChart = async () => {
    await nextTick()

    if (!canvasRef.value) {
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
    const barBaseColor = isDark.value ? '#60a5fa' : '#bfdbfe'
    const barPeakColor = isDark.value ? '#3b82f6' : '#1f75fe'
    const borderPeakColor = isDark.value ? '#60a5fa' : '#1d4ed8'
    const borderBaseColor = isDark.value ? '#93c5fd' : '#93c5fd'

    if (chartInstance) {
        chartInstance.destroy()
    }

    const Chart = await loadChartJs()

    chartInstance = new Chart(context, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    data,
                    backgroundColor: (chartContext: ScriptableContext<'bar'>) => (
                        data[chartContext.dataIndex] === maxValue ? barPeakColor : barBaseColor
                    ),
                    borderColor: (chartContext: ScriptableContext<'bar'>) => (
                        data[chartContext.dataIndex] === maxValue ? borderPeakColor : borderBaseColor
                    ),
                    borderWidth: 1,
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 32,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (tooltipContext: TooltipItem<'bar'>) => `${formatCredits(Number(tooltipContext.raw ?? 0))} ${t('credits')}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => formatNumber(Number(value)),
                        stepSize: Math.max(1, Math.ceil(maxValue / 4)),
                    },
                    grid: {
                        color: gridColor,
                    },
                    border: {
                        display: false,
                    },
                    ticks: {
                        color: tickColor,
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

watch(usageChart, () => {
    void drawChart()
})

watch(isDark, () => {
    void drawChart()
})
</script>

<template>
    <Head :title="t('Dashboard')" />

    <UserDashboardLayout>
        <div class="space-y-6">
            <section class="relative overflow-hidden rounded-[28px] border border-primary-100/80 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.22),_transparent_34%),linear-gradient(135deg,_rgba(255,255,255,0.98),_rgba(239,246,255,0.92),_rgba(248,250,252,0.98))] p-6 shadow-xl shadow-primary-100/40 dark:border-primary-900/60 dark:bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.24),_transparent_34%),linear-gradient(135deg,_rgba(15,23,42,0.98),_rgba(17,24,39,0.96),_rgba(15,23,42,0.98))] dark:shadow-black/20 lg:p-8">
                <div class="absolute inset-y-0 right-0 hidden w-1/3 bg-[radial-gradient(circle_at_center,_rgba(139,92,246,0.16),_transparent_58%)] lg:block"></div>
                <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_20rem]">
                    <div class="min-w-0 space-y-5">
                        <div class="min-w-0 space-y-2">
                            <h1 class="break-words font-bold text-2xl tracking-tight text-gray-950 dark:!text-white lg:text-3xl">
                                {{ t('Welcome back! :name', { name: userFirstName }) }} 👏
                            </h1>
                            <p class="max-w-2xl break-words text-sm leading-6 text-gray-600 dark:text-gray-300 lg:text-base">
                                {{ t('Track your credits, saved work, recent activity, and plan details from one workspace.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span :class="planStatusClass" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                                {{ planStatusLabel }}
                            </span>
                            <span v-if="plan?.subscription_ends_at" class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/70 px-3 py-1 text-xs font-medium text-gray-600 dark:border-surface-700 dark:bg-surface-950/90 dark:text-gray-300">
                                <i class="ti ti-calendar-event text-sm"></i>
                                {{ t('Renews through :date', { date: formatDate(plan.subscription_ends_at) }) }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Link :href="route('ai.tools.index')" class="inline-flex items-center gap-2 rounded-full border border-primary-500 bg-primary-500 px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-primary-600 dark:border-primary-400 dark:bg-primary-500 dark:hover:bg-primary-400">
                                <i class="ti ti-wand"></i>
                                {{ t('Explore AI tools') }}
                            </Link>
                            <Link :href="route('user.dashboard.documents.index')" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/80 px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 dark:border-gray-200 dark:!bg-gray-800 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300">
                                <i class="ti ti-folders"></i>
                                {{ t('Open content library') }}
                            </Link>
                        </div>
                    </div>

                    <div class="min-w-0 rounded-[24px] border border-white/80 bg-white/90 p-5 shadow-lg backdrop-blur dark:border-surface-800 dark:!bg-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Current balance') }}</p>
                                <p class="mt-2 font-heading text-3xl font-bold text-gray-950 dark:!text-white">{{ formatCredits(stats.credits) }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Credits currently available') }}</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <i class="ti ti-crown text-2xl"></i>
                            </div>
                        </div>

                        <div class="mt-5 space-y-3 rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-700 dark:bg-surface-950">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('Used today') }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCredits(stats.credits_used_today) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('Used this month') }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ formatCredits(stats.credits_used_month) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ balanceMetaItem.label }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ balanceMetaItem.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <DashboardChecklist />

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <ContextualTooltip tooltip-key="dashboard.stats" :content="t('Track credits, saved work, and momentum at a glance.')">
                    <article class="group h-full rounded-[24px] border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:shadow-black/30" :class="statCards[0].cardClass">
                        <div class="flex flex-col items-start justify-between gap-4">
                            <div :class="statCards[0].iconClass" class="shrink-0 flex h-10 w-10 items-center justify-center rounded-2xl">
                                <i :class="statCards[0].icon" class="text-2xl"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-wide text-gray-600 dark:text-gray-400">{{ statCards[0].label }}</p>
                                <p class="mt-3 font-heading text-3xl font-bold text-gray-950 dark:!text-white">{{ statCards[0].value }}</p>
                            </div>
                        </div>
                    </article>
                </ContextualTooltip>

                <article v-for="card in statCards.slice(1)" :key="card.key" class="group rounded-[24px] border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:shadow-black/30" :class="card.cardClass">
                    <div class="flex flex-col items-start justify-between gap-4">
                        <div :class="card.iconClass" class="shrink-0 flex h-10 w-10 items-center justify-center rounded-2xl">
                            <i :class="card.icon" class="text-2xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-gray-600 dark:text-gray-400">{{ card.label }}</p>
                            <p class="mt-3 font-heading text-3xl font-bold text-gray-950 dark:!text-white">{{ card.value }}</p>
                        </div>
                    </div>
                </article>
            </section>

            <section>
                <div class="overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 space-y-2">
                                <h2 class="font-heading text-xl font-bold text-gray-950 dark:!text-white">{{ chartTitle }}</h2>
                                <p class="break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Credit usage is calculated from real wallet activity for this account.') }}</p>
                            </div>

                            <div class="inline-flex flex-wrap gap-1 rounded-2xl border border-gray-200 bg-gray-50 p-1 dark:border-surface-700 dark:bg-surface-950">
                                <button
                                    v-for="option in chartPeriodOptions"
                                    :key="option.value"
                                    type="button"
                                    :disabled="chartLoading"
                                    class="rounded-xl px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
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
                        <div class="h-[320px]">
                            <canvas ref="canvasRef"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <section class="overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="bg-[linear-gradient(135deg,_rgba(31,117,254,0.08),_rgba(139,92,246,0.08))] px-6 py-5 dark:bg-surface-900">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Current plan') }}</p>
                                    <h2 class="mt-2 break-words font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ plan?.name ?? t('No active plan') }}</h2>
                                    <p class="mt-2 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Your current plan, limits, and renewal details.') }}</p>
                                </div>
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/80 text-primary-700 shadow-sm dark:!bg-surface-900/80 dark:text-primary-500">
                                    <i class="ti ti-rocket text-2xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5 px-6 py-5">
                            <div class="flex items-center gap-2">
                                <span :class="planStatusClass" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                                    {{ planStatusLabel }}
                                </span>
                            </div>

                            <p v-if="plan?.trial_ends_at" class="text-sm text-gray-500 dark:text-gray-400">{{ t('Trial ends :date', { date: formatDate(plan.trial_ends_at) }) }}</p>
                            <p v-else-if="plan?.subscription_ends_at" class="text-sm text-gray-500 dark:text-gray-400">{{ t('Access reserved through :date', { date: formatDate(plan.subscription_ends_at) }) }}</p>

                            <div v-if="planFeatures.length" class="space-y-3">
                                <div v-for="feature in planFeatures" :key="feature" class="flex items-start gap-3 rounded-2xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-surface-800 dark:bg-surface-950/60">
                                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                        <i class="ti ti-check text-sm"></i>
                                    </div>
                                    <p class="break-words text-sm text-gray-600 dark:text-gray-300">{{ feature }}</p>
                                </div>
                            </div>

                            <div v-if="plan?.daily_limit || plan?.monthly_limit" class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-950/60">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Daily limit') }}</p>
                                    <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ formatCredits(plan?.daily_limit ?? 0) }}</p>
                                </div>
                                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-950/60">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Monthly limit') }}</p>
                                    <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ formatCredits(plan?.monthly_limit ?? 0) }}</p>
                                </div>
                            </div>

                            <Link
                                v-if="!plan || plan.is_free || plan.subscription_status === 'none'"
                                :href="route('pricing')"
                                class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold"
                            >
                                <i class="ti ti-arrow-up"></i>
                                {{ t('Upgrade plan') }}
                            </Link>
                            <Link
                                v-else
                                :href="route('user.dashboard.billing')"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                            >
                                <i class="ti ti-credit-card"></i>
                                {{ t('Manage billing') }}
                            </Link>
                        </div>
                </section>

                <section class="overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="flex flex-col gap-2 border-b border-gray-100 px-6 py-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Recent login history') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('The last 10 login attempts for this account.') }}</p>
                            </div>
                            <Link :href="route('user.dashboard.privacy')" class="shrink-0 text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                                {{ t('View details') }}
                            </Link>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-surface-800">
                            <div v-if="recentLoginHistory.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No login history yet.') }}</div>
                            <div v-for="login in recentLoginHistory" :key="login.id" class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
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
                        class="group rounded-[24px] border bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:bg-surface-900"
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
                <div class="overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Recent conversations') }}</h2>
                            <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Fresh idea sessions and client-facing discussions.') }}</p>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-surface-800">
                        <div v-if="recentConversations.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No conversations yet.') }}</div>
                        <div v-for="conversation in recentConversations" :key="conversation.id" class="flex items-start justify-between gap-4 px-6 py-4">
                            <div class="min-w-0 flex-1">
                                <p class="break-words font-semibold text-gray-950 dark:!text-gray-200">{{ conversation.title }}</p>
                                <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ conversation.model }} · {{ formatNumber(conversation.message_count) }} {{ t('messages') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:!bg-surface-800 dark:text-gray-300">
                                {{ conversation.last_message_at ? formatRelative(conversation.last_message_at) : t('Just now') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div class="min-w-0">
                            <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Recent documents') }}</h2>
                            <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Saved deliverables that make the dashboard feel alive and valuable.') }}</p>
                        </div>
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

            <section class="overflow-hidden rounded-[28px] border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-6 py-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="font-heading text-lg font-bold text-gray-950 dark:!text-white">{{ t('Wallet activity') }}</h2>
                        <p class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Your latest credit purchases, usage, and balance updates.') }}</p>
                    </div>
                    <Link :href="route('user.dashboard.usage.index')" class="text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400">
                        {{ t('Usage details') }}
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
                            <p :class="transaction.amount > 0 ? 'text-emerald-600 dark:!text-emerald-300' : 'text-rose-600 dark:!text-rose-300'" class="font-heading text-xl font-bold">
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
