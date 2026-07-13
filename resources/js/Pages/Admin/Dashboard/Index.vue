<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onMounted, onBeforeUnmount, ref, watch, nextTick, type Ref } from 'vue'
import type { TooltipItem } from 'chart.js'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import ChartCard from '@/Components/UI/ChartCard.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminNotesModal from '@/Pages/Admin/Dashboard/AdminNotesModal.vue'
import AdminNotesListModal from '@/Pages/Admin/Dashboard/AdminNotesListModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const toast = useToastr()
const { formatCurrency, currencySymbol } = useNumberFormat()
const page = usePage()

interface AppProps {
    demo?: boolean
}

interface AdminProps {
    isSuperAdmin?: boolean
    permissions?: string[]
}

const isDemo = computed(() => (page.props.app as AppProps | undefined)?.demo ?? false)
const blogEnabled = computed(() => Boolean(page.props.blogEnabled))
const ticketsEnabled = computed(() => Boolean(page.props.ticketsEnabled))

interface TimeSeriesPoint { date: string; value: number }
interface DualSeriesPoint { date: string; revenue: number; cost: number }
interface LabelValue { label: string; credits?: number; cost?: number; tokens?: number; logins?: number; count?: number; country?: string }
interface DashboardMetricItem {
    label: string
    tool_slug?: string
    tool_name?: string
    model?: string
    model_name?: string
    count?: number
    cost?: number
    tokens?: number
    credits?: number
}
interface StatComparison {
    current: number
    previous: number
}

interface PopularBlogPost {
    ulid: string
    title: string
    views_count: number
    published_at: string | null
}

interface RecentProSubscription {
    id: number
    status: string
    billing_cycle: string | null
    created_at: string | null
    user: {
        ulid: string
        name: string
        email: string
    } | null
    plan: {
        name: string
    } | null
}

type ChartPeriod = 'today' | '7d' | '30d' | '90d' | 'lifetime'

const props = defineProps<{
    dashboardPeriod: ChartPeriod
    dashboardCharts: {
        signupsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        revenueChart: Record<ChartPeriod, TimeSeriesPoint[]>
        cardComparisons: Partial<Record<Exclude<ChartPeriod, 'lifetime'>, Record<string, StatComparison>>>
        subscriptionRevenueChart: Record<ChartPeriod, TimeSeriesPoint[]>
        totalCostChart: Record<ChartPeriod, TimeSeriesPoint[]>
        outputTokensChart: Record<ChartPeriod, TimeSeriesPoint[]>
        newsletterSubscribersChart: Record<ChartPeriod, TimeSeriesPoint[]>
        subscriptionConversionsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        subscriptionRetainedChart: Record<ChartPeriod, TimeSeriesPoint[]>
        subscriptionChurnedChart: Record<ChartPeriod, TimeSeriesPoint[]>
        referralChart: Record<ChartPeriod, DualSeriesPoint[]>
        aiRequestsTotalChart: Record<ChartPeriod, TimeSeriesPoint[]>
        aiFailedRequestsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        aiRequestsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        creditsUsedChart: Record<ChartPeriod, TimeSeriesPoint[]>
        internalAiRequestsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        internalAiCostChart: Record<ChartPeriod, TimeSeriesPoint[]>
        openTicketsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        pendingCommentsChart: Record<ChartPeriod, TimeSeriesPoint[]>
        revenueVsCost: Record<ChartPeriod, DualSeriesPoint[]>
        proSubs: Record<ChartPeriod, TimeSeriesPoint[]>
    }
    aiByTool: LabelValue[]
    costByProvider: LabelValue[]
    providerUsageByCount: LabelValue[]
    geoUsage: { country: string; logins: number }[]
    topToolsByUsage: DashboardMetricItem[]
    topToolsByCost: DashboardMetricItem[]
    topToolsByTokens: DashboardMetricItem[]
    topModelsByUsage: DashboardMetricItem[]
    topModelsByCost: DashboardMetricItem[]
    topModelsByTokens: DashboardMetricItem[]
    recentUsers: { ulid: string; name: string; email: string; created_at: string }[]
    popularBlogPosts: PopularBlogPost[]
    recentProSubscriptions: RecentProSubscription[]
    trafficSources: LabelValue[]
    activity: { type: string; icon: string; title: string; detail: string; time: string }[]
    addonStats: { total: number; active: number; recently_activated: number }
}>()

const dashboardCharts = computed(() => props.dashboardCharts)
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const adminFirstName = computed(() => {
    const admin = page.props.admin as { name?: string; user?: { name?: string } } | undefined
    const name = (admin?.name ?? admin?.user?.name ?? (page.props.auth as { user?: { name?: string } } | undefined)?.user?.name ?? '').trim()
    return name.split(/\s+/).filter(Boolean)[0] || ''
})

function can(perm: string): boolean {
    const admin = (page.props.admin as AdminProps | undefined) ?? {}
    return admin.isSuperAdmin || (admin.permissions ?? []).includes(perm)
}

const chartPeriod = ref<ChartPeriod>(props.dashboardPeriod)
const showExportMenu = ref(false)
const exporting = ref(false)
const exportEl = ref<HTMLElement | null>(null)

const exportTypes = computed(() => [
    { value: 'users', label: 'Users' },
    { value: 'ai-usage', label: 'AI Usage' },
    // Revenue export is Pro-only (mirrors ExportCenterController gate); hide it otherwise.
    ...(isProAvailable.value ? [{ value: 'revenue', label: 'Revenue' }] : []),
    // Affiliate export needs Extended license + affiliate_enabled (shared prop).
    ...(affiliateEnabled.value ? [{ value: 'affiliates', label: 'Affiliate' }] : []),
])

function periodDateRange(): { dateFrom: string; dateTo: string } {
    const now = new Date()
    const today = now.toISOString().split('T')[0]
    switch (chartPeriod.value) {
        case 'today': return { dateFrom: today, dateTo: today }
        case '7d': return { dateFrom: new Date(Date.now() - 6 * 86400000).toISOString().split('T')[0], dateTo: today }
        case '30d': return { dateFrom: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0], dateTo: today }
        case '90d': return { dateFrom: new Date(Date.now() - 89 * 86400000).toISOString().split('T')[0], dateTo: today }
        default: return { dateFrom: '2000-01-01', dateTo: today }
    }
}

function handleExportClickOutside(e: MouseEvent) {
    if (exportEl.value && !exportEl.value.contains(e.target as Node)) showExportMenu.value = false
}
onMounted(() => document.addEventListener('click', handleExportClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleExportClickOutside))

async function doDashboardExport(type: string, format: string) {
    exporting.value = true
    showExportMenu.value = false
    const { dateFrom, dateTo } = periodDateRange()

    try {
        const res = await fetch(route('admin.reports.export'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({ type, format, date_from: dateFrom, date_to: dateTo }),
        })

        if (res.headers.get('content-type')?.includes('application/json')) {
            const json = await res.json()
            if (json.queued) {
                toast.info(json.message ?? t('Export queued.'))
            } else if (!res.ok) {
                toast.error(json.message ?? t('Export failed'))
            }
        } else {
            const blob = await res.blob()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url; a.download = 'export.' + format; a.click()
            URL.revokeObjectURL(url)
        }
    } catch {
        toast.error(t('Export failed'))
    }
    finally { exporting.value = false }
}

const showNotesFormModal = ref(false)
const showNotesListModal = ref(false)
const editingNoteId = ref<number | undefined>(undefined)
const notesReminders = ref<{ id: number; subject: string }[]>([])
const deletingDashboardNoteId = ref<number | null>(null)
const deletingDashboardNote = ref(false)

async function fetchReminders() {
    const res = await fetch(route('admin.notes.index'), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
    if (res.ok) {
        const data = await res.json()
        notesReminders.value = data.remindersDue || []
        allNotes.value = data.notes || []
    }
}

async function snoozeReminder(noteId: number) {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''
    try {
        const res = await fetch(route('admin.notes.snooze', noteId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        if (res.ok) {
            toast.success(t('Reminder postponed by 1 hour.'))
            await fetchReminders()
        } else {
            toast.error(t('Snooze failed.'))
        }
    } catch {
        toast.error(t('Snooze failed.'))
    }
}

async function dismissReminder(noteId: number) {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''
    try {
        const res = await fetch(route('admin.notes.dismiss', noteId), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        if (res.ok) {
            toast.success(t('Reminder dismissed.'))
            await fetchReminders()
        } else {
            toast.error(t('Dismiss failed.'))
        }
    } catch {
        toast.error(t('Dismiss failed.'))
    }
}

const allNotes = ref<{ id: number; subject: string; description: string | null; reminder_date: string | null; reminder_sent?: boolean; created_at: string }[]>([])

async function handleDashboardNoteDeleted() {
    await fetchReminders()
    showNotesListModal.value = false
}

function requestDeleteDashboardNote(noteId: number) {
    deletingDashboardNoteId.value = noteId
}

async function confirmDashboardNoteDelete() {
    if (deletingDashboardNoteId.value === null || deletingDashboardNote.value) {
        return
    }

    deletingDashboardNote.value = true
    const noteId = deletingDashboardNoteId.value
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''

    try {
        await fetch(route('admin.notes.delete', noteId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })

        await fetchReminders()
    } finally {
        deletingDashboardNote.value = false
        deletingDashboardNoteId.value = null
    }
}

const hasCostData = computed(() => props.costByProvider.length > 0 && props.costByProvider.some((p) => (p.cost || 0) > 0))

function formatCurrentMonthLabel(): string {
    return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(new Date())
}

function formatPreviousMonthLabel(): string {
    const date = new Date()
    date.setMonth(date.getMonth() - 1)
    return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date)
}

const periodLabels: Record<ChartPeriod, string> = { today: 'Today', '7d': 'Last 7 days', '30d': formatCurrentMonthLabel(), '90d': 'Last 90 days', lifetime: 'All Time' }
const comparisonWindowLabels: Record<Exclude<ChartPeriod, 'lifetime'>, string> = {
    today: 'vs yesterday',
    '7d': 'vs last week',
    '30d': 'vs last month',
    '90d': 'vs last 90 days',
}
const periodShortLabels: Record<ChartPeriod, string> = {
    today: '1D',
    '7d': '7D',
    '30d': '1M',
    '90d': '90D',
    lifetime: 'All',
}
const periodOptions: ChartPeriod[] = ['today', '7d', '30d', '90d', 'lifetime']
const periodSelectOptions = computed(() => periodOptions.map((period) => ({
    value: period,
    label: t(periodLabels[period]),
})))

interface StatSeriesCard {
    title: string
    value: string
    sparkline: number[]
    comparison?: string
    comparisonDetail?: string
    comparisonType?: 'up' | 'down' | 'neutral'
    color: 'primary' | 'accent' | 'success' | 'warning' | 'danger' | 'pink'
    icon: 'users' | 'dollar' | 'spark' | 'shield' | 'credits' | 'plans' | 'ticket'
    metric: string
    visible: boolean
}

function normalizeSeriesValue(point: TimeSeriesPoint | DualSeriesPoint): number {
    if ('value' in point) {
        return Number(point.value) || 0
    }

    return Number(point.revenue) || 0
}

function getSeriesValues(series: Array<TimeSeriesPoint | DualSeriesPoint> | undefined): number[] {
    return (series ?? []).map((point) => normalizeSeriesValue(point))
}

function sumSeries(series: number[]): number {
    return series.reduce((total, value) => total + value, 0)
}

function lastSeriesValue(series: number[]): number {
    return series.length ? series[series.length - 1] : 0
}

function formatRangeNumber(value: number): string {
    return value.toLocaleString()
}

function formatRangeMoney(value: number): string {
    return formatCurrency(value)
}

function formatComparisonPercent(current: number, previous: number): { label: string; type: 'up' | 'down' | 'neutral' } {
    if (previous === 0) {
        if (current === 0) {
            return { label: '0%', type: 'neutral' }
        }

        return { label: '+100%', type: 'up' }
    }

    const delta = ((current - previous) / previous) * 100
    const rounded = Math.round(Math.abs(delta))

    if (rounded === 0) {
        return { label: '0%', type: 'neutral' }
    }

    return {
        label: `${delta > 0 ? '+' : '-'}${rounded}%`,
        type: delta > 0 ? 'up' : 'down',
    }
}

function getPeriodSeries<T>(seriesMap: Partial<Record<ChartPeriod, T[]>> | undefined, period: ChartPeriod): T[] {
    return seriesMap?.[period] ?? []
}

function getCardComparison(metric: string): { label: string; type: 'up' | 'down' | 'neutral' } | null {
    if (chartPeriod.value === 'lifetime') {
        return null
    }

    const comparison = dashboardCharts.value.cardComparisons?.[chartPeriod.value]?.[metric]
    if (!comparison) {
        return null
    }

    return formatComparisonPercent(comparison.current, comparison.previous)
}

function comparisonProps(metric: string): { comparison?: string; comparisonDetail?: string; comparisonType?: 'up' | 'down' | 'neutral' } {
    const comparison = getCardComparison(metric)
    if (!comparison) {
        return {}
    }

    const comparisonWindow = chartPeriod.value === '30d'
        ? formatPreviousMonthLabel()
        : comparisonWindowLabels[chartPeriod.value as Exclude<ChartPeriod, 'lifetime'>].replace(/^vs\s+/i, '')

    return {
        comparison: comparison.label,
        comparisonDetail: `vs ${comparisonWindow}`,
        comparisonType: comparison.type,
    }
}

const periodSeries = computed(() => {
    const period = chartPeriod.value

    return {
        signups: getSeriesValues(getPeriodSeries(dashboardCharts.value.signupsChart, period)),
        revenue: getSeriesValues(getPeriodSeries(dashboardCharts.value.revenueChart, period)),
        subscriptionRevenue: getSeriesValues(getPeriodSeries(dashboardCharts.value.subscriptionRevenueChart, period)),
        totalCost: getSeriesValues(getPeriodSeries(dashboardCharts.value.totalCostChart, period)),
        outputTokens: getSeriesValues(getPeriodSeries(dashboardCharts.value.outputTokensChart, period)),
        subscriptionConversions: getSeriesValues(getPeriodSeries(dashboardCharts.value.subscriptionConversionsChart, period)),
        subscriptionRetained: getSeriesValues(getPeriodSeries(dashboardCharts.value.subscriptionRetainedChart, period)),
        subscriptionChurned: getSeriesValues(getPeriodSeries(dashboardCharts.value.subscriptionChurnedChart, period)),
        aiRequestsTotal: getSeriesValues(getPeriodSeries(dashboardCharts.value.aiRequestsTotalChart, period)),
        aiFailedRequests: getSeriesValues(getPeriodSeries(dashboardCharts.value.aiFailedRequestsChart, period)),
        aiRequests: getSeriesValues(getPeriodSeries(dashboardCharts.value.aiRequestsChart, period)),
        creditsUsed: getSeriesValues(getPeriodSeries(dashboardCharts.value.creditsUsedChart, period)),
        internalAiRequests: getSeriesValues(getPeriodSeries(dashboardCharts.value.internalAiRequestsChart, period)),
        internalAiCost: getSeriesValues(getPeriodSeries(dashboardCharts.value.internalAiCostChart, period)),
        openTickets: getSeriesValues(getPeriodSeries(dashboardCharts.value.openTicketsChart, period)),
        pendingComments: getSeriesValues(getPeriodSeries(dashboardCharts.value.pendingCommentsChart, period)),
    }
})

const cards = computed<StatSeriesCard[]>(() => {
    const series = periodSeries.value

    return [
        {
            title: 'Total Users',
            value: formatRangeNumber(sumSeries(series.signups)),
            sparkline: series.signups,
            ...comparisonProps('signups'),
            color: 'primary',
            icon: 'users',
            metric: 'signups',
            visible: true,
        },
        {
            title: 'AI Requests',
            value: formatRangeNumber(sumSeries(series.aiRequests)),
            sparkline: series.aiRequests,
            ...comparisonProps('aiRequests'),
            color: 'accent',
            icon: 'spark',
            metric: 'aiRequests',
            visible: true,
        },
        {
            title: 'Output Tokens',
            value: formatRangeNumber(sumSeries(series.outputTokens)),
            sparkline: series.outputTokens,
            ...comparisonProps('outputTokens'),
            color: 'pink',
            icon: 'spark',
            metric: 'outputTokens',
            visible: true,
        },
        {
            title: 'Internal Usage',
            value: formatRangeMoney(sumSeries(series.internalAiCost)),
            sparkline: series.internalAiCost,
            ...comparisonProps('internalAiCost'),
            color: 'warning',
            icon: 'shield',
            metric: 'internalAiCost',
            visible: true,
        },
        {
            title: 'API Cost',
            value: formatRangeMoney(sumSeries(series.totalCost)),
            sparkline: series.totalCost,
            ...comparisonProps('totalCost'),
            color: 'danger',
            icon: 'credits',
            metric: 'totalCost',
            visible: true,
        },
        {
            title: 'Revenue',
            value: formatRangeMoney(sumSeries(series.revenue)),
            sparkline: series.revenue,
            ...comparisonProps('revenue'),
            color: 'success',
            icon: 'dollar',
            metric: 'revenue',
            visible: isProAvailable.value,
        },
    ]
})

// ─── Chart.js dynamic import ───
const signupsCanvas = ref<HTMLCanvasElement | null>(null)
const revenueCanvas = ref<HTMLCanvasElement | null>(null)
const newsletterSubscribersCanvas = ref<HTMLCanvasElement | null>(null)
const churnRetentionCanvas = ref<HTMLCanvasElement | null>(null)
const referralCanvas = ref<HTMLCanvasElement | null>(null)
const providerUsageCanvas = ref<HTMLCanvasElement | null>(null)
const failureRateCanvas = ref<HTMLCanvasElement | null>(null)
const conversionCanvas = ref<HTMLCanvasElement | null>(null)
const aiByToolCanvas = ref<HTMLCanvasElement | null>(null)
const costByProviderCanvas = ref<HTMLCanvasElement | null>(null)
const revenueVsCostCanvas = ref<HTMLCanvasElement | null>(null)
const proSubsCanvas = ref<HTMLCanvasElement | null>(null)

const chartRefs: Record<string, Ref<HTMLCanvasElement | null>> = {
    signups: signupsCanvas, revenue: revenueCanvas, newsletterSubscribers: newsletterSubscribersCanvas, churnRetention: churnRetentionCanvas,
    referral: referralCanvas, providerUsage: providerUsageCanvas, failureRate: failureRateCanvas, conversion: conversionCanvas,
    aiByTool: aiByToolCanvas, costByProvider: costByProviderCanvas, revenueVsCost: revenueVsCostCanvas, proSubs: proSubsCanvas,
}
const chartInstances: Record<string, { destroy: () => void } | null> = {}

async function loadChartJs() {
    const { Chart, LineController, BarController, DoughnutController, RadarController, ArcElement, CategoryScale, LinearScale, RadialLinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend } = await import('chart.js')
    Chart.register(LineController, BarController, DoughnutController, RadarController, ArcElement, CategoryScale, LinearScale, RadialLinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend)
    return Chart
}

function destroyChart(key: string) {
    if (chartInstances[key]) { chartInstances[key]?.destroy(); chartInstances[key] = null }
}

async function drawChart(key: string, canvasRef: Ref<HTMLCanvasElement | null>, configFn: () => unknown) {
    await nextTick()
    const canvas = canvasRef.value
    if (!canvas) return
    const ctx = canvas.getContext('2d')
    if (!ctx) return
    destroyChart(key)
    const Chart = await loadChartJs()
    try {
        chartInstances[key] = new Chart(ctx, configFn() as never)
    } catch (e) {
        console.error(`Chart ${key} failed:`, e)
    }
}

function timeLabels(data: Array<TimeSeriesPoint | DualSeriesPoint> | undefined | null): string[] {
    if (!data || !data.length) return []
    if (chartPeriod.value === 'lifetime') {
        return data.map((point) => point.date)
    }
    return data.map((point) => {
        if (point.date.includes(':')) return point.date
        // Parse as local date to avoid UTC timezone shift
        const [y, m, day] = point.date.split('-').map(Number)
        const dt = new Date(y, m - 1, day)
        if (chartPeriod.value === '7d') {
            return dt.toLocaleDateString('en', { weekday: 'short' })
        }
        return dt.toLocaleDateString('en', { month: 'short', day: 'numeric' })
    })
}

function isEmptySeries(data: TimeSeriesPoint[] | DualSeriesPoint[] | undefined | null): boolean {
    if (!data || !data.length) return true
    return data.every((point) => {
        if ('value' in point) {
            return Number(point.value) === 0
        }
        return (Number(point.revenue) || 0) === 0 && (Number(point.cost) || 0) === 0
    })
}

function chartXAxisOptions(period: ChartPeriod) {
    if (period === '90d') {
        return {
            offset: false,
            ticks: {
                autoSkip: true,
                maxTicksLimit: 9,
                maxRotation: 0,
                minRotation: 0,
                padding: 8,
                autoSkipPadding: 18,
            },
        }
    }

    if (period === '30d') {
        return {
            offset: false,
            ticks: {
                autoSkip: true,
                maxTicksLimit: 8,
                maxRotation: 0,
                minRotation: 0,
                padding: 8,
                autoSkipPadding: 16,
            },
        }
    }

    if (period === '7d') {
        return {
            offset: false,
            ticks: {
                autoSkip: true,
                maxTicksLimit: 7,
                maxRotation: 0,
                minRotation: 0,
                padding: 6,
            },
        }
    }

    return {
        offset: false,
        ticks: {
            autoSkip: true,
            maxTicksLimit: 8,
            maxRotation: 0,
            minRotation: 0,
            padding: 6,
        },
    }
}

const chartGridColor = 'rgba(148, 163, 184, 0.1)'
const providerBarColors = [
    '#3b82f6',
    '#10b981',
    '#8b5cf6',
    '#f59e0b',
    '#ef4444',
    '#06b6d4',
    '#f97316',
    '#14b8a6',
]

function createVerticalAreaGradient(
    context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } },
    topColor: string,
    midColor: string,
    bottomColor: string,
    fallbackColor: string,
): CanvasGradient | string {
    const { chart } = context
    const { ctx, chartArea } = chart
    if (!chartArea) return fallbackColor

    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
    gradient.addColorStop(0, topColor)
    gradient.addColorStop(0.72, midColor)
    gradient.addColorStop(1, bottomColor)
    return gradient
}

async function drawAllCharts() {
    const period = chartPeriod.value
    const xAxis = chartXAxisOptions(period)

    await drawChart('signups', chartRefs.signups, () => ({
        type: 'bar', data: { labels: timeLabels(getPeriodSeries(dashboardCharts.value.signupsChart, period)), datasets: [{ data: getPeriodSeries(dashboardCharts.value.signupsChart, period).map(d => d.value), backgroundColor: 'rgba(31, 117, 254, 0.62)', borderColor: 'rgba(31, 117, 254, 0.92)', borderWidth: 1, borderRadius: 4, barPercentage: 0.6, categoryPercentage: 0.72, maxBarThickness: 18 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: chartGridColor }, border: { display: false }, ticks: { callback: (value: string | number) => (typeof value === 'number' && value % 1 === 0 ? value : ''), stepSize: 1 } }, x: { border: { display: false }, grid: { display: false }, ...xAxis, offset: true } } },
    }))

    await drawChart('revenue', chartRefs.revenue, () => ({
        type: 'line', data: { labels: timeLabels(getPeriodSeries(dashboardCharts.value.revenueChart, period)), datasets: [{ data: getPeriodSeries(dashboardCharts.value.revenueChart, period).map(d => d.value), borderColor: '#22c55e', backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } }) => createVerticalAreaGradient(context, 'rgba(34, 197, 94, 0.26)', 'rgba(34, 197, 94, 0.08)', 'rgba(255, 255, 255, 0.02)', 'rgba(34, 197, 94, 0.12)'), fill: true, tension: 0.35, pointRadius: 0, pointHoverRadius: 0, pointBackgroundColor: '#22c55e', pointBorderColor: '#22c55e', borderWidth: 2.5 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    elements: { point: { radius: 0, hoverRadius: 0 } },
                    plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context: TooltipItem<'line'>) => ` ${currencySymbol.value}${Number(context.parsed.y || 0).toLocaleString()}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: chartGridColor }, border: { display: false },
                    ticks: {
                        callback: (value: string | number) => `${currencySymbol.value}${Number(value).toLocaleString()}`,
                    },
                },
                x: { border: { display: false }, grid: { display: false }, ...xAxis },
            },
        },
    }))

    await drawChart('newsletterSubscribers', chartRefs.newsletterSubscribers, () => {
        const series = getPeriodSeries(dashboardCharts.value.newsletterSubscribersChart, period)
        return {
            type: 'line',
            data: {
                labels: timeLabels(series),
                datasets: [
                    {
                        label: t('Newsletter Subscribers'),
                        data: series.map((d) => Number(d.value) || 0),
                        borderColor: '#10b981',
                        backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } }) => createVerticalAreaGradient(context, 'rgba(16, 185, 129, 0.24)', 'rgba(16, 185, 129, 0.08)', 'rgba(255, 255, 255, 0.02)', 'rgba(16, 185, 129, 0.14)'),
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#10b981',
                        borderWidth: 2.5,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                elements: { point: { radius: 0, hoverRadius: 0 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context: TooltipItem<'line'>) => ` ${t('Subscribers')}: ${Number(context.parsed.y || 0).toLocaleString()}`,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGridColor }, border: { display: false },
                        ticks: { callback: (value: string | number) => Number(value).toLocaleString() },
                    },
                    x: { border: { display: false }, grid: { display: false }, ...xAxis },
                },
            },
        }
    })

    await drawChart('churnRetention', chartRefs.churnRetention, () => {
        const retainedSeries = getPeriodSeries(dashboardCharts.value.subscriptionRetainedChart, period)
        const churnedSeries = getPeriodSeries(dashboardCharts.value.subscriptionChurnedChart, period)
        const retainedValues = retainedSeries.map((d) => Number(d.value) || 0)
        const churnValues = churnedSeries.map((d) => -(Number(d.value) || 0))
        const maxAbsValue = Math.max(...retainedValues.map((value) => Math.abs(value)), ...churnValues.map((value) => Math.abs(value)), 0)
        const scaleMax = maxAbsValue > 0 ? Math.ceil(maxAbsValue * 1.15) : 100
        const gap = Math.max(4, Math.round(scaleMax * 0.04))
        const retainedBars = retainedValues.map((value) => [gap, gap + value])
        const churnBars = churnValues.map((value) => [-gap, value - gap])

        return {
            type: 'bar',
            data: {
                labels: timeLabels(retainedSeries),
                datasets: [
                    {
                        label: t('New'),
                        data: retainedBars,
                        backgroundColor: 'rgba(124, 58, 237, 0.78)',
                        borderColor: 'rgba(124, 58, 237, 0.98)',
                        borderWidth: 0,
                        borderRadius: 9999,
                        borderSkipped: false,
                        barPercentage: 0.34,
                        categoryPercentage: 0.58,
                        maxBarThickness: 12,
                    },
                    {
                        label: t('Churn'),
                        data: churnBars,
                        backgroundColor: 'rgba(255, 159, 67, 0.88)',
                        borderColor: 'rgba(255, 159, 67, 1)',
                        borderWidth: 0,
                        borderRadius: 9999,
                        borderSkipped: false,
                        barPercentage: 0.34,
                        categoryPercentage: 0.58,
                        maxBarThickness: 12,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                elements: { point: { radius: 0, hoverRadius: 0 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context: TooltipItem<'bar'>) => {
                                const raw = Array.isArray(context.raw) ? Math.abs(Number(context.raw[1]) - Number(context.raw[0])) : Math.abs(Number(context.parsed.y || 0))
                                const value = Number(raw) || 0
                                return ` ${context.dataset.label}: ${value.toLocaleString()}`
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ...xAxis,
                        offset: true,
                    },
                    y: {
                        min: -(scaleMax + gap),
                        max: scaleMax + gap,
                        beginAtZero: true,
                        grid: { color: chartGridColor }, border: { display: false },
                        ticks: { callback: (value: string | number) => Number(value).toLocaleString() },
                    },
                },
            },
        }
    })

    await drawChart('referral', chartRefs.referral, () => {
        const series = getPeriodSeries(dashboardCharts.value.referralChart, period)
        const clicks = series.map((d) => Number(d.revenue) || 0)
        const conversions = series.map((d) => Number(d.cost) || 0)

        return {
            type: 'line',
            data: {
                labels: timeLabels(series),
                datasets: [
                    {
                        label: t('Clicks'),
                        data: clicks,
                        borderColor: 'rgba(59, 130, 246, 0.95)',
                        backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } }) => createVerticalAreaGradient(context, 'rgba(59, 130, 246, 0.36)', 'rgba(59, 130, 246, 0.12)', 'rgba(255, 255, 255, 0.02)', 'rgba(59, 130, 246, 0.20)'),
                        fill: true,
                        tension: 0.34,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: 'rgba(59, 130, 246, 0.95)',
                        pointBorderColor: 'rgba(59, 130, 246, 0.95)',
                        borderWidth: 2.5,
                    },
                    {
                        label: t('Conversions'),
                        data: conversions,
                        borderColor: 'rgba(139, 92, 246, 0.96)',
                        backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } }) => createVerticalAreaGradient(context, 'rgba(139, 92, 246, 0.30)', 'rgba(139, 92, 246, 0.10)', 'rgba(255, 255, 255, 0.02)', 'rgba(139, 92, 246, 0.18)'),
                        fill: true,
                        tension: 0.34,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: 'rgba(139, 92, 246, 0.96)',
                        pointBorderColor: 'rgba(139, 92, 246, 0.96)',
                        borderWidth: 2.25,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                elements: { point: { radius: 0, hoverRadius: 0 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'center',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8,
                            padding: 18,
                            color: '#6b7280',
                            font: { size: 10 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context: TooltipItem<'line'>) => ` ${context.dataset.label}: ${Number(context.parsed.y || 0).toLocaleString()}`,
                        },
                    },
                },
                scales: {
                    x: { border: { display: false }, grid: { display: false }, ...xAxis, offset: true },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGridColor }, border: { display: false },
                        ticks: { callback: (value: string | number) => Number(value).toLocaleString() },
                    },
                },
            },
        }
    })

    await drawChart('providerUsage', chartRefs.providerUsage, () => {
        const providers = props.providerUsageByCount
        const providerColors = providers.map((_, index) => providerBarColors[index % providerBarColors.length])

        return {
            type: 'doughnut',
            data: {
                labels: providers.map((provider) => provider.label),
                datasets: [{
                    label: t('Requests'),
                    data: providers.map((provider) => Number(provider.count) || 0),
                    backgroundColor: providerColors,
                    borderColor: providerColors,
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: 0,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        labels: { boxWidth: 12, font: { size: 10 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context: TooltipItem<'doughnut'>) => {
                                const value = Number(context.parsed || 0)
                                const total = providers.reduce((sum, provider) => sum + (Number(provider.count) || 0), 0)
                                const percent = total > 0 ? Math.round((value / total) * 100) : 0
                                return ` ${context.label}: ${value.toLocaleString()} (${percent}%)`
                            },
                        },
                    },
                },
            },
        }
    })

    await drawChart('failureRate', chartRefs.failureRate, () => {
        const totalSeries = getPeriodSeries(dashboardCharts.value.aiRequestsTotalChart, period)
        const failedSeries = getPeriodSeries(dashboardCharts.value.aiFailedRequestsChart, period)
        const labels = timeLabels(totalSeries)
        const totals = totalSeries.map((point) => Number(point.value) || 0)
        const failures = failedSeries.map((point) => Number(point.value) || 0)
        const rates = totals.map((value, index) => {
            const failed = failures[index] || 0
            return value > 0 ? (failed / value) * 100 : 0
        })

        return {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: t('Requests'),
                        data: totals,
                        backgroundColor: 'rgba(59, 130, 246, 0.65)',
                        borderColor: 'rgba(59, 130, 246, 0.95)',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: t('Failure Rate'),
                        data: rates,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.16)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#ef4444',
                        borderWidth: 2.25,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                elements: { point: { radius: 0, hoverRadius: 0 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, labels: { boxWidth: 12, font: { size: 10 } } },
                    tooltip: {
                        callbacks: {
                            label: (context: TooltipItem<'bar'> | TooltipItem<'line'>) => {
                                if (context.datasetIndex === 1) {
                                    return ` ${context.dataset.label}: ${Number(context.parsed.y || 0).toFixed(1)}%`
                                }

                                return ` ${context.dataset.label}: ${Number(context.parsed.y || 0).toLocaleString()}`
                            },
                        },
                    },
                },
                scales: {
                    x: { border: { display: false }, grid: { display: false }, ...xAxis },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGridColor }, border: { display: false },
                        ticks: { callback: (value: string | number) => Number(value).toLocaleString() },
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false }, border: { display: false },
                        ticks: { callback: (value: string | number) => `${Number(value).toFixed(0)}%` },
                    },
                },
            },
        }
    })

    if (isProAvailable.value) {
        await drawChart('conversion', chartRefs.conversion, () => {
            const signupsSeries = getPeriodSeries(dashboardCharts.value.signupsChart, period)
            const conversionsSeries = getPeriodSeries(dashboardCharts.value.subscriptionConversionsChart, period)
            const signupsValues = signupsSeries.map((d) => Number(d.value) || 0)
            const proValues = conversionsSeries.map((point) => Number(point.value) || 0)
            // Free = signups that did not convert to a paid plan in the same bucket.
            const freeValues = signupsValues.map((signups, index) => Math.max(0, signups - (proValues[index] || 0)))

            return {
                type: 'bar',
                data: {
                    labels: timeLabels(signupsSeries),
                    datasets: [
                        {
                            label: t('Free'),
                            data: freeValues,
                            backgroundColor: 'rgba(34, 197, 94, 0.68)',
                            borderColor: 'rgba(34, 197, 94, 0.95)',
                            borderWidth: 1,
                            borderRadius: 6,
                            barPercentage: 0.72,
                            categoryPercentage: 0.68,
                            maxBarThickness: 18,
                        },
                        {
                            label: t('Pro'),
                            data: proValues,
                            backgroundColor: 'rgba(99, 102, 241, 0.72)',
                            borderColor: 'rgba(99, 102, 241, 0.96)',
                            borderWidth: 1,
                            borderRadius: 6,
                            barPercentage: 0.72,
                            categoryPercentage: 0.68,
                            maxBarThickness: 18,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    elements: { point: { radius: 0, hoverRadius: 0 } },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                boxHeight: 8,
                                padding: 18,
                                color: '#6b7280',
                                font: { size: 10 },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: (context: TooltipItem<'bar'>) => ` ${context.dataset.label}: ${Number(context.parsed.y || 0).toLocaleString()}`,
                            },
                        },
                    },
                    scales: {
                        x: { stacked: true, border: { display: false }, grid: { display: false }, ...xAxis, offset: true },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: { color: chartGridColor }, border: { display: false },
                            ticks: { callback: (value: string | number) => Number(value).toLocaleString() },
                        },
                    },
                },
            }
        })
    }

    await drawChart('revenueVsCost', chartRefs.revenueVsCost, () => {
        const series = getPeriodSeries(dashboardCharts.value.revenueVsCost, period)

        return {
            type: 'line',
            data: {
                labels: timeLabels(series),
                datasets: [
                    {
                        label: t('Revenue'),
                        data: series.map((d) => Number(d.revenue) || 0),
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 2.75,
                        fill: true,
                        backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } }) => createVerticalAreaGradient(context, 'rgba(99, 102, 241, 0.30)', 'rgba(99, 102, 241, 0.08)', 'rgba(255, 255, 255, 0.02)', 'rgba(99, 102, 241, 0.18)'),
                        tension: 0.35,
                        pointBackgroundColor: 'rgb(99, 102, 241)',
                        pointBorderColor: 'rgb(99, 102, 241)',
                    },
                    {
                        label: t('Cost'),
                        data: series.map((d) => Number(d.cost) || 0),
                        backgroundColor: 'rgba(249, 115, 22, 0.06)',
                        borderColor: 'rgb(234, 88, 12)',
                        borderWidth: 2.25,
                        fill: false,
                        tension: 0.35,
                        pointBackgroundColor: 'rgb(234, 88, 12)',
                        pointBorderColor: 'rgb(234, 88, 12)',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                elements: { point: { radius: 0, hoverRadius: 0 } },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: true, labels: { boxWidth: 12, font: { size: 10 } } },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title: (items: TooltipItem<'line'>[]) => items[0]?.label ?? '',
                            label: (context: TooltipItem<'line'>) => {
                                const index = context.dataIndex
                                const point = series[index]
                                if (!point) return ''
                                const value = context.datasetIndex === 0 ? (Number(point.revenue) || 0) : (Number(point.cost) || 0)
                                return ` ${context.dataset.label}: ${currencySymbol.value}${value.toLocaleString()}`
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ...xAxis,
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartGridColor }, border: { display: false },
                        ticks: {
                            callback: (value: string | number) => `${currencySymbol.value}${Math.round(Number(value)).toLocaleString()}`,
                        },
                    },
                },
            },
        }
    })

    if (isProAvailable.value) {
        await drawChart('proSubs', chartRefs.proSubs, () => ({
            type: 'line', data: { labels: timeLabels(getPeriodSeries(dashboardCharts.value.proSubs, period)), datasets: [{ data: getPeriodSeries(dashboardCharts.value.proSubs, period).map(d => d.value), borderColor: '#8b5cf6', backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; chartArea?: { top: number; bottom: number } } }) => createVerticalAreaGradient(context, 'rgba(139, 92, 246, 0.26)', 'rgba(139, 92, 246, 0.08)', 'rgba(255, 255, 255, 0.02)', 'rgba(139,92,246,0.1)'), fill: true, tension: 0.3 }] },
            options: { responsive: true, maintainAspectRatio: false, elements: { point: { radius: 0, hoverRadius: 0 } }, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: chartGridColor }, border: { display: false } }, x: { border: { display: false }, grid: { display: false }, ...xAxis } } },
        }))
    }

    // Static aggregation charts (drawn once)
    if (props.aiByTool.length) {
        await drawChart('aiByTool', chartRefs.aiByTool, () => ({
            type: 'doughnut',
            data: { labels: props.aiByTool.map(t => t.label), datasets: [{ data: props.aiByTool.map(t => t.credits || 0), backgroundColor: ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899','#14b8a6'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } } } },
        }))
    }
    if (hasCostData.value) {
        const providerColors = props.costByProvider.map((_, index) => providerBarColors[index % providerBarColors.length])

        await drawChart('costByProvider', chartRefs.costByProvider, () => ({
            type: 'bar',
            data: {
                labels: props.costByProvider.map(p => p.label),
                datasets: [{
                    data: props.costByProvider.map(p => p.cost || 0),
                    backgroundColor: providerColors,
                    borderColor: providerColors,
                    borderWidth: 1,
                    borderRadius: 4,
                    // Cap thickness so a handful of providers render as slim bars
                    // instead of stretching to fill the whole chart height.
                    maxBarThickness: 28,
                    categoryPercentage: 0.7,
                    barPercentage: 0.9,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y' as const,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context: TooltipItem<'bar'>) => ` ${context.label}: ${currencySymbol.value}${Number(context.parsed.x || 0).toLocaleString()}`,
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { display: false },
                        ticks: {
                            callback: (value: string | number) => `${currencySymbol.value}${Number(value).toLocaleString()}`,
                        },
                    },
                    y: { grid: { color: chartGridColor }, border: { display: false } },
                },
            },
        }))
    }
}

watch(chartPeriod, (period, previous) => {
    if (period === previous) {
        drawAllCharts()
        return
    }
    // Redraw only AFTER the partial reload settles. Drawing immediately races with
    // Inertia's re-render of the canvas subtree (which leaves the chart showing the
    // prior period), so we defer to onFinish — it fires on success, cancel, or error,
    // and drawAllCharts reads chartPeriod.value live, so rapid switching self-corrects
    // to whichever period ends up selected.
    router.get(route('admin.dashboard'), { period }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => nextTick(() => drawAllCharts()),
    })
})
onMounted(() => { drawAllCharts(); fetchReminders() })
onBeforeUnmount(() => Object.keys(chartInstances).forEach(k => destroyChart(k)))

function timeAgo(time: string): string {
    const diff = Date.now() - new Date(time).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return t('Just now')
    if (mins < 60) return `${mins}m ago`
    const h = Math.floor(mins / 60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h / 24)}d ago`
}

function formatShortDate(value: string | null): string {
    if (!value) {
        return t('No publish date')
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

function formatCompactNumber(value: number): string {
    return new Intl.NumberFormat().format(value)
}

function subscriptionStatusClasses(status: string): string {
    if (status === 'active') {
        return 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
    }

    if (status === 'trialing') {
        return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
    }

    return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'
}

function formatSubscriptionMeta(subscription: RecentProSubscription): string {
    const parts = [
        subscription.plan?.name ?? t('Unknown plan'),
        subscription.billing_cycle ? t(subscription.billing_cycle) : null,
        subscription.created_at ? timeAgo(subscription.created_at) : t('Just now'),
    ].filter(Boolean)

    return parts.join(' • ')
}

const activityIcons: Record<string, string> = {
    user: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    dollar: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    spark: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
    ticket: 'M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z',
}

const activityTypeLabels: Record<string, string> = {
    user_registered: t('User Registered'),
    payment: t('Payment'),
    subscription: t('Pro Subscription'),
    ai_request: t('Tool Use'),
    referral: t('Referral'),
}
</script>

<template>
    <Head :title="t('Dashboard')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold text-gray-900 dark:text-white">
                    <span>{{ t('Welcome back!') }}{{ adminFirstName ? ` ${adminFirstName}` : '' }} 👏</span>
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Overview of your platform') }} · <span class="font-medium text-gray-600 dark:text-gray-300">{{ t('Showing') }}: {{ t(periodLabels[chartPeriod]) }}</span></p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="inline-flex rounded-xl border border-gray-200 bg-white p-1 dark:bg-surface-800 dark:border-surface-700 shrink-0">
                    <Tooltip
                        v-for="period in periodOptions"
                        :key="period"
                        :content="t(periodLabels[period])"
                        placement="top"
                    >
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-150"
                            :class="chartPeriod === period
                                ? 'bg-gray-100 text-gray-900 shadow-sm dark:bg-surface-700 dark:text-white'
                                : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:!text-white'"
                            @click="chartPeriod = period"
                        >
                            {{ t(periodShortLabels[period]) }}
                        </button>
                    </Tooltip>
                </div>
                <div ref="exportEl" class="relative">
                    <button @click.stop="showExportMenu = !showExportMenu" :disabled="exporting"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ exporting ? t('Exporting...') : t('Export') }}
                    </button>
                    <div v-if="showExportMenu"
                        class="absolute right-0 top-full z-50 mt-1 w-40 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                        <button v-for="et in exportTypes" :key="et.value"
                            @click="doDashboardExport(et.value, 'xlsx')"
                            class="w-full rounded-xl px-3 py-2 text-start text-sm text-gray-700 transition-colors hover:bg-primary-50 dark:text-gray-300 dark:hover:bg-surface-800">
                            {{ et.label }}
                        </button>
                        <div class="my-1 border-t border-gray-100 dark:border-surface-800" />
                        <Link :href="route('admin.reports.export-center')"
                            class="block rounded-xl px-3 py-2 text-sm text-primary-600 transition-colors hover:bg-primary-50 dark:hover:bg-surface-800">
                            {{ t('Export Center →') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demo Mode Indicator -->
        <div v-if="isDemo" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ t('Demo Mode Active') }}</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">{{ t('Destructive write operations are blocked. AI generation, login/logout, and preferences remain functional. To change demo settings, edit the .env file.') }}</p>
                </div>
            </div>
        </div>

        <!-- Notes Reminder Alert -->
        <div v-if="notesReminders.length" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-slate-800 dark:bg-blue-900/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">{{ t('Note Reminders') }}</p>
                    <div class="mt-2 space-y-2">
                        <div v-for="n in notesReminders" :key="n.id" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-blue-100/30 dark:bg-blue-950/20 px-3 py-2">
                            <span class="text-sm text-blue-700 dark:text-blue-400 font-medium">{{ n.subject }}</span>
                            <div class="flex items-center gap-3">
                                <button @click="snoozeReminder(n.id)" class="text-xs font-bold text-blue-700 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-100 transition">
                                    {{ t('Remind later (1h)') }}
                                </button>
                                <button @click="dismissReminder(n.id)" class="text-xs font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 transition">
                                    {{ t('Dismiss') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <button @click="showNotesListModal = true" class="shrink-0 text-xs font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 mt-0.5">{{ t('View all') }}</button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <StatsCard v-for="card in cards.filter(c => c.visible)" :key="card.title" :title="card.title" :value="card.value" :sparkline="card.sparkline" :comparison="card.comparison" :comparison-detail="card.comparisonDetail" :comparison-type="card.comparisonType" :color="card.color">
                <template #icon>
                    <svg v-if="card.icon === 'users'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    <svg v-else-if="card.icon === 'dollar'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else-if="card.icon === 'spark'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                    <svg v-else-if="card.icon === 'shield'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    <svg v-else-if="card.icon === 'credits'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75" /></svg>
                    <svg v-else-if="card.icon === 'plans'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path d="M6 6h.008v.008H6V6z" /></svg>
                    <svg v-else-if="card.icon === 'ticket'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                </template>
            </StatsCard>
        </div>

        <!-- Row 1: Quick Actions + Revenue -->
        <div class="grid grid-cols-1 lg:grid-cols-3 items-stretch gap-6">
            <div class="flex flex-col gap-6">
                <ChartCard :title="t('Quick Actions')">
                    <div class="space-y-2">
                        <Link v-if="can('ai.tools')" :href="route('admin.ai.tools.create')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:border-primary-200 transition-all flex items-center gap-3">
                            <svg class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>{{ t('Add AI Tool') }}</Link>
                        <Link :href="route('admin.announcements.index')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-warning-50 dark:hover:bg-warning-900/20 hover:border-warning-200 transition-all flex items-center gap-3">
                            <svg class="w-4 h-4 text-warning-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" /></svg>{{ t('Send Announcement') }}</Link>
                        <Link v-if="can('support.tickets') && ticketsEnabled" :href="route('admin.support.tickets.index')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-danger-50 dark:hover:bg-danger-900/20 hover:border-danger-200 transition-all flex items-center gap-3">
                            <svg class="w-4 h-4 text-danger-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>{{ t('Support Tickets') }}</Link>
                    </div>
                </ChartCard>
                <ChartCard :title="t('My Notes')">
                    <template #action>
                    <div class="flex items-center gap-1.5">
                        <Tooltip :content="t('Create note')" placement="top">
                            <button @click="editingNoteId = undefined; showNotesFormModal = true" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-primary-600 hover:bg-primary-50 hover:text-primary-500 dark:text-primary-400 dark:hover:bg-primary-900/20" :aria-label="t('Create note')">
                                <i class="ti ti-plus text-base"></i>
                            </button>
                        </Tooltip>
                        <Tooltip :content="t('View all notes')" placement="top">
                            <button @click="showNotesListModal = true" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-primary-600 hover:bg-primary-50 hover:text-primary-500 dark:text-primary-400 dark:hover:bg-primary-900/20" :aria-label="t('View all notes')">
                                <i class="ti ti-eye text-base"></i>
                            </button>
                        </Tooltip>
                    </div>
                    </template>
                <div v-if="allNotes.length" class="space-y-2">
                    <div v-for="note in allNotes.slice(0, 2)" :key="note.id" class="rounded-2xl border border-gray-100 bg-gray-50 p-3 dark:border-surface-800 dark:bg-surface-800/50 group">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ note.subject }}</p>
                                <p v-if="note.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ note.description }}</p>
                                <span v-if="note.reminder_date && !note.reminder_sent && new Date(note.reminder_date) > new Date()" class="inline-block mt-1 text-[10px] text-amber-600 dark:text-amber-400">
                                    {{ t('Reminder') }}: {{ new Date(note.reminder_date).toLocaleString() }}
                                </span>
                            </div>
                            <div class="flex items-center gap-0.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editingNoteId = note.id; showNotesFormModal = true" :title="t('Edit')" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-300">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button @click="requestDeleteDashboardNote(note.id)" :title="t('Delete')" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-gray-400">
                        <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        <p class="text-xs">{{ t('No notes yet') }}</p>
                    </div>
                </ChartCard>
            </div>
            <ChartCard :title="isProAvailable ? t('Revenue') : t('User Registrations')" class="lg:col-span-2 flex h-full min-h-0 flex-col">
                <!-- Revenue is premium (Extended license + subscriptions). Non-pro installs
                     get User Registrations as the hero chart so the panel is never empty. -->
                <template v-if="isProAvailable">
                    <div v-if="!isEmptySeries(dashboardCharts.revenueChart[chartPeriod])" class="relative min-h-0 flex-1"><canvas ref="revenueCanvas" class="h-full w-full" /></div>
                    <div v-else class="flex min-h-0 flex-1 flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                        <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                        <p class="text-sm">{{ t('No revenue data for this period') }}</p>
                    </div>
                </template>
                <template v-else>
                    <div v-if="!isEmptySeries(dashboardCharts.signupsChart[chartPeriod])" class="relative min-h-0 flex-1"><canvas ref="signupsCanvas" class="h-full w-full" /></div>
                    <div v-else class="flex min-h-0 flex-1 flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                        <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                        <p class="text-sm">{{ t('No signup data for this period') }}</p>
                    </div>
                </template>
            </ChartCard>
        </div>

        <!-- Section: Growth & Revenue -->
        <h2 class="pt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Growth & Revenue') }}</h2>

        <!-- Row 2: User Registrations + Cost vs Revenue — pro-only (non-pro shows signups as the hero above) -->
        <div v-if="isProAvailable" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <ChartCard :title="t('User Registrations')" class="flex flex-col">
                <div v-if="!isEmptySeries(dashboardCharts.signupsChart[chartPeriod])" class="relative flex-1 min-h-48"><canvas ref="signupsCanvas" /></div>
                <div v-else class="flex-1 min-h-48 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No signup data for this period') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Cost vs Revenue')">
                <div v-if="!isEmptySeries(dashboardCharts.revenueVsCost[chartPeriod])" class="relative h-48"><canvas ref="revenueVsCostCanvas" /></div>
                <div v-else class="h-48 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No data for this period') }}</p>
                </div>
            </ChartCard>
        </div>

        <!-- Row 3: Growth & Health -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <ChartCard :title="t('Newsletter Subscribers')" :class="{ 'lg:col-span-2': !isProAvailable }">
                <div v-if="!isEmptySeries(dashboardCharts.newsletterSubscribersChart[chartPeriod])" class="relative h-72"><canvas ref="newsletterSubscribersCanvas" /></div>
                <div v-else class="h-72 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No newsletter subscriber data for this period') }}</p>
                </div>
            </ChartCard>
            <ChartCard v-if="isProAvailable" :title="t('Subscription Health')" class="flex flex-col">
                <div v-if="!isEmptySeries(dashboardCharts.subscriptionRetainedChart[chartPeriod]) || !isEmptySeries(dashboardCharts.subscriptionChurnedChart[chartPeriod])" class="relative flex-1 min-h-[14rem]"><canvas ref="churnRetentionCanvas" /></div>
                <div v-else class="flex flex-1 min-h-[14rem] flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No subscription health data for this period') }}</p>
                </div>
            </ChartCard>
        </div>

        <!-- Section: AI Usage & Cost -->
        <h2 class="pt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('AI Usage & Cost') }}</h2>

        <!-- Row 4: AI Credits by Tool + Cost by Provider -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <ChartCard :title="t('Credit Usage by Tool')" class="lg:col-span-5">
                <div v-if="aiByTool.length" class="relative h-56"><canvas ref="aiByToolCanvas" /></div>
                <div v-else class="h-72 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-sm">{{ t('No AI tool data yet') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Cost by Provider')" class="lg:col-span-7">
                <div v-if="hasCostData" class="relative h-56"><canvas ref="costByProviderCanvas" /></div>
                <div v-else class="h-56 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-sm">{{ t('No cost data yet') }}</p>
                </div>
            </ChartCard>
        </div>

        <!-- Row 5: AI Usage + Failure Rate -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <ChartCard :title="t('AI Usage by Provider')" class="lg:col-span-5">
                <div v-if="providerUsageByCount.length" class="relative h-56"><canvas ref="providerUsageCanvas" /></div>
                <div v-else class="h-56 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-sm">{{ t('No provider usage data yet') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Failure Rate')" class="lg:col-span-7">
                <div v-if="!isEmptySeries(dashboardCharts.aiRequestsTotalChart[chartPeriod])" class="relative h-56"><canvas ref="failureRateCanvas" /></div>
                <div v-else class="h-56 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No AI request data for this period') }}</p>
                </div>
            </ChartCard>
        </div>

        <!-- Section: Acquisition -->
        <h2 class="pt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Acquisition') }}</h2>

        <!-- Row 6: Acquisition -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <ChartCard :title="t('Traffic Sources')">
                <div v-if="trafficSources.length" class="space-y-2">
                    <div v-for="src in trafficSources.slice(0, 9)" :key="src.label" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ src.label }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ (src.count || 0).toLocaleString() }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                    <p class="text-xs">{{ t('No data') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Top AI Tools')">
                <div v-if="topToolsByUsage.length" class="space-y-2">
                    <div v-for="tool in topToolsByUsage.slice(0, 9)" :key="tool.tool_slug" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400 truncate mr-2">{{ tool.tool_name ?? tool.tool_slug }}</span>
                        <span class="font-medium text-gray-900 dark:text-white shrink-0">{{ tool.count?.toLocaleString() || 0 }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-xs">{{ t('No data') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Top AI Models')">
                <div v-if="topModelsByUsage.length" class="space-y-2">
                    <div v-for="model in topModelsByUsage.slice(0, 9)" :key="model.model" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400 truncate mr-2">{{ model.model_name ?? model.model }}</span>
                        <span class="font-medium text-gray-900 dark:text-white shrink-0">{{ model.count?.toLocaleString() || 0 }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-xs">{{ t('No data') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Recent Users')">
                <div v-if="recentUsers.length" class="space-y-2">
                    <div v-for="user in recentUsers" :key="user.ulid" class="text-sm">
                        <p class="font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    <p class="text-xs">{{ t('No users yet') }}</p>
                </div>
            </ChartCard>
        </div>

        <!-- Section: Engagement & Activity -->
        <h2 class="pt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Engagement & Activity') }}</h2>

        <!-- Row 7: Referral + Conversion -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <ChartCard v-if="affiliateEnabled" :class="blogEnabled ? 'lg:col-span-8' : 'lg:col-span-12'" :title="t('Affiliate Performance')" class="flex h-full flex-col">
                <div v-if="!isEmptySeries(dashboardCharts.referralChart[chartPeriod])" class="relative min-h-[20rem] flex-1"><canvas ref="referralCanvas" /></div>
                <div v-else class="flex min-h-[20rem] flex-1 flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No referral data for this period') }}</p>
                </div>
            </ChartCard>
            <ChartCard v-if="blogEnabled" :class="affiliateEnabled ? 'lg:col-span-4' : 'lg:col-span-12'" :title="t('Popular Blog Posts')" class="flex h-full flex-col">
                <template #action>
                    <Link :href="route('admin.blog.posts.index')" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        {{ t('View all') }}
                    </Link>
                </template>
                <div v-if="popularBlogPosts.length" class="space-y-2">
                    <Link
                        v-for="post in popularBlogPosts.slice(0, 5)"
                        :key="post.ulid"
                        :href="route('admin.blog.posts.edit', post.ulid)"
                        class="flex items-start justify-between gap-3 rounded-2xl border border-gray-100 px-3 py-2.5 transition-colors hover:border-primary-200 hover:bg-primary-50/60 dark:border-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-surface-800"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ post.title }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ formatShortDate(post.published_at) }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-primary-50 px-2 py-1 text-[11px] font-medium text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                            {{ formatCompactNumber(post.views_count) }} {{ t('views') }}
                        </span>
                    </Link>
                </div>
                <div v-else class="flex min-h-[20rem] flex-1 flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="mb-2 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M19.5 21a1.5 1.5 0 001.5-1.5V6.848a1.5 1.5 0 00-.44-1.06l-3.348-3.348A1.5 1.5 0 0016.152 2H6a3 3 0 00-3 3v14a2 2 0 002 2h14.5z" /><path d="M17 21v-8a2 2 0 00-2-2H9a2 2 0 00-2 2v8" /><path d="M9 7h6" /></svg>
                    <p class="text-sm">{{ t('No popular blog posts yet') }}</p>
                </div>
            </ChartCard>
        </div>

        <div v-if="isProAvailable" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <ChartCard :title="t('Free vs Pro Conversion')" class="lg:col-span-8 flex h-full flex-col">
                <div v-if="!isEmptySeries(dashboardCharts.signupsChart[chartPeriod]) || !isEmptySeries(dashboardCharts.subscriptionConversionsChart[chartPeriod])" class="relative min-h-[20rem] flex-1"><canvas ref="conversionCanvas" /></div>
                <div v-else class="flex min-h-[20rem] flex-1 flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No conversion data for this period') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Recent Subscriptions')" class="lg:col-span-4 flex h-full flex-col">
                <template #action>
                    <Link :href="route('admin.users.index')" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        {{ t('View all') }}
                    </Link>
                </template>
                <div v-if="recentProSubscriptions.length" class="space-y-2">
                    <Link
                        v-for="subscription in recentProSubscriptions.slice(0, 5)"
                        :key="subscription.id"
                        :href="subscription.user ? route('admin.users.show', subscription.user.ulid) : route('admin.users.index')"
                        class="flex items-start justify-between gap-3 rounded-2xl border border-gray-100 px-3 py-2.5 transition-colors hover:border-primary-200 hover:bg-primary-50/60 dark:border-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-surface-800"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ subscription.user?.name ?? t('Unknown user') }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ formatSubscriptionMeta(subscription) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center">
                            <span :class="subscriptionStatusClasses(subscription.status)" class="rounded-full px-2 py-1 text-[11px] font-medium">
                                {{ t(subscription.status) }}
                            </span>
                        </div>
                    </Link>
                </div>
                <div v-else class="flex min-h-[20rem] flex-1 flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="mb-2 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M12 6v12m6-6H6" /><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <p class="text-sm">{{ t('No recent pro subscriptions yet') }}</p>
                </div>
            </ChartCard>
        </div>

        <!-- Activity Feed + Countries -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <ChartCard :title="t('Recent Activity')" class="lg:col-span-7">
                <template #action>
                    <Link :href="route('admin.activity.user-logs.index')" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        {{ t('View more') }}
                    </Link>
                </template>
                <div v-if="activity.length" class="space-y-1">
                    <div v-for="(item, i) in activity.slice(0, 5)" :key="i" class="flex items-center gap-3 px-3 py-2.5 rounded-2xl hover:bg-gray-50 dark:hover:bg-surface-800 transition-colors">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="{
                            'bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400': item.icon === 'user',
                            'bg-success-100 dark:bg-success-900/30 text-success-600 dark:text-success-400': item.icon === 'dollar',
                            'bg-accent-100 dark:bg-accent-900/30 text-accent-600 dark:text-accent-400': item.icon === 'spark',
                        }">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path :d="activityIcons[item.icon] || activityIcons.user" /></svg>
                        </div>
                        <div class="flex-1 min-w-0"><p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.title }}</p><p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ item.detail }}</p></div>
                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-md shrink-0" :class="{
                            'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400': item.type === 'user_registered',
                            'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400': item.type === 'payment',
                            'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400': item.type === 'subscription',
                            'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400': item.type === 'ai_request',
                            'bg-pink-50 text-pink-600 dark:bg-pink-900/30 dark:text-pink-400': item.type === 'referral',
                        }">{{ activityTypeLabels[item.type] || item.type }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ timeAgo(item.time) }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <p class="text-sm">{{ t('No activity yet') }}</p>
                    <p class="text-xs mt-1">{{ t('Activity will appear here as users interact with your platform') }}</p>
                </div>
            </ChartCard>
            <ChartCard :title="t('Usage by Countries')" class="lg:col-span-5">
                <div v-if="geoUsage.length" class="space-y-2">
                    <div v-for="g in geoUsage.slice(0, 10)" :key="g.country" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ g.country }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ g.logins.toLocaleString() }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                    <p class="text-sm">{{ t('No geo data yet') }}</p>
                </div>
            </ChartCard>
        </div>
    </div>

    <AdminNotesModal :open="showNotesFormModal" :edit-note-id="editingNoteId" @close="showNotesFormModal = false; editingNoteId = undefined; fetchReminders()" />
    <AdminNotesListModal
        :open="showNotesListModal"
        :notes="allNotes"
        @close="showNotesListModal = false"
        @create="editingNoteId = undefined; showNotesListModal = false; showNotesFormModal = true"
        @edit="(noteId) => { editingNoteId = noteId; showNotesListModal = false; showNotesFormModal = true }"
        @delete="handleDashboardNoteDeleted"
    />
    <ActionConfirmModal
        :open="deletingDashboardNoteId !== null"
        :title="t('Delete note?')"
        :message="t('This note will be removed permanently.')"
        :confirm-label="t('Delete')"
        :cancel-label="t('Cancel')"
        :processing="deletingDashboardNote"
        :processing-label="t('Deleting...')"
        variant="danger"
        @confirm="confirmDashboardNoteDelete"
        @cancel="deletingDashboardNoteId = null"
    />
</template>
ate>
