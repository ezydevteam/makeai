<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onMounted, onBeforeUnmount, ref, watch, nextTick, type Ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import AdminNotesModal from '@/Pages/Admin/Dashboard/AdminNotesModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const page = usePage()

const isDemo = computed(() => (page.props.app as any)?.demo ?? false)

interface DashboardStats {
    totalUsers: number; newUsersToday: number; newUsersThisMonth: number; newUsersLastMonth: number
    activeUsers: number; bannedUsers: number
    totalRevenue: number; revenueToday: number; revenueThisMonth: number; revenueLastMonth: number; mrr: number
    totalAiRequests: number; aiRequestsToday: number; totalCreditsUsed: number; creditsUsedToday: number
    creditsUsedThisMonth: number; totalCost: number; costToday: number; tokensUsedToday: number
    activeSubscriptions: number; trialingSubscriptions: number; pastDueSubscriptions: number; activePlans: number
    openTickets: number; pendingComments: number
}

interface TimeSeriesPoint { date: string; value: number }
interface DualSeriesPoint { date: string; revenue: number; cost: number }
interface LabelValue { label: string; credits?: number; cost?: number; tokens?: number; logins?: number; count?: number; country?: string }

type ChartPeriod = 'today' | '7d' | '30d' | '90d' | 'lifetime'

const props = defineProps<{
    stats: DashboardStats
    signupsChart: Record<ChartPeriod, TimeSeriesPoint[]>
    revenueChart: Record<ChartPeriod, TimeSeriesPoint[]>
    aiByTool: LabelValue[]
    costByProvider: LabelValue[]
    revenueVsCost: Record<ChartPeriod, DualSeriesPoint[]>
    proSubs: Record<ChartPeriod, TimeSeriesPoint[]>
    geoUsage: { country: string; logins: number }[]
    topToolsByUsage: Record<string, any>[]
    topToolsByCost: Record<string, any>[]
    topToolsByTokens: Record<string, any>[]
    topModelsByUsage: Record<string, any>[]
    topModelsByCost: Record<string, any>[]
    topModelsByTokens: Record<string, any>[]
    recentUsers: { ulid: string; name: string; email: string; created_at: string }[]
    trafficSources: LabelValue[]
    activity: { type: string; icon: string; title: string; detail: string; time: string }[]
}>()

const s = computed(() => props.stats)
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

function can(perm: string): boolean {
    const admin = (page.props.admin as any) ?? {}
    return admin.isSuperAdmin || (admin.permissions ?? []).includes(perm)
}

const chartPeriod = ref<ChartPeriod>('7d')
const showExportMenu = ref(false)
const exporting = ref(false)
const exportEl = ref<HTMLElement | null>(null)

const exportTypes = [
    { value: 'users', label: 'Users' },
    { value: 'ai-usage', label: 'AI Usage' },
    { value: 'revenue', label: 'Revenue' },
    { value: 'affiliates', label: 'Affiliate Commissions' },
]

function periodDateRange(): { dateFrom: string; dateTo: string } {
    const now = new Date()
    const today = now.toISOString().split('T')[0]
    switch (chartPeriod.value) {
        case 'today': return { dateFrom: today, dateTo: today }
        case '7d': return { dateFrom: new Date(Date.now() - 6 * 86400000).toISOString().split('T')[0], dateTo: today }
        case '30d': return { dateFrom: new Date(Date.now() - 29 * 86400000).toISOString().split('T')[0], dateTo: today }
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
            if (json.queued) alert(json.message)
        } else {
            const blob = await res.blob()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url; a.download = 'export.' + format; a.click()
            URL.revokeObjectURL(url)
        }
    } catch { alert(t('Export failed')) }
    finally { exporting.value = false }
}

const showNotesModal = ref(false)
const editingNoteId = ref<number | undefined>(undefined)
const notesReminders = ref<{ id: number; subject: string }[]>([])

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

const allNotes = ref<{ id: number; subject: string; description: string | null; reminder_date: string | null; created_at: string }[]>([])

async function deleteDashboardNote(noteId: number) {
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.getAttribute('content') || ''
    await fetch(route('admin.notes.delete', noteId), {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
    fetchReminders()
}

const hasCostData = computed(() => props.costByProvider.length > 0 && props.costByProvider.some((p: any) => (p.cost || 0) > 0))

const periodLabels: Record<ChartPeriod, string> = { today: 'Today', '7d': '7 days', '30d': '30 days', '90d': '90 days', lifetime: 'Lifetime' }
const periodOptions: ChartPeriod[] = ['today', '7d', '30d', '90d', 'lifetime']

const cards = computed(() => [
    { title: 'Total Users', value: s.value.totalUsers.toLocaleString(),
        change: `+${s.value.newUsersToday} today / +${s.value.newUsersThisMonth} month`,
        changeType: (s.value.newUsersToday > 0 ? 'up' : 'neutral') as 'up' | 'down' | 'neutral', color: 'primary' as const, icon: 'users', visible: true },
    { title: 'Revenue', value: '$' + s.value.totalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2 }),
        change: `$${s.value.revenueToday.toLocaleString()} today / MRR $${s.value.mrr.toLocaleString()}`,
        changeType: (s.value.revenueToday > 0 ? (s.value.revenueThisMonth >= s.value.revenueLastMonth ? 'up' : 'down') : 'neutral') as 'up' | 'down' | 'neutral',
        color: 'success' as const, icon: 'dollar', visible: can('dashboard.analytics') },
    { title: 'AI Requests', value: s.value.totalAiRequests.toLocaleString(),
        change: `${s.value.aiRequestsToday.toLocaleString()} today / ${new Intl.NumberFormat().format(s.value.tokensUsedToday)} tokens`,
        changeType: 'neutral' as const, color: 'accent' as const, icon: 'spark', visible: true },
    { title: 'Credits', value: new Intl.NumberFormat().format(s.value.totalCreditsUsed),
        change: `${new Intl.NumberFormat().format(s.value.creditsUsedToday)} today / Cost $${s.value.costToday.toFixed(2)}`,
        changeType: 'neutral' as const, color: 'warning' as const, icon: 'credits', visible: true },
    { title: 'Active Plans', value: s.value.activeSubscriptions.toLocaleString(),
        change: s.value.trialingSubscriptions > 0 ? `${s.value.trialingSubscriptions} trialing / ${s.value.pastDueSubscriptions} past due` : `${s.value.activePlans} plans`,
        changeType: 'neutral' as const, color: 'warning' as const, icon: 'plans', visible: isProAvailable.value },
    { title: 'Open Tickets', value: s.value.openTickets.toLocaleString(),
        change: s.value.pendingComments > 0 ? `${s.value.pendingComments} pending comments` : 'No pending items',
        changeType: s.value.openTickets > 0 ? 'up' as const : 'neutral' as const,
        color: 'danger' as const, icon: 'ticket', visible: can('support.tickets') },
])

// ─── Chart.js dynamic import ───
const signupsCanvas = ref<HTMLCanvasElement | null>(null)
const revenueCanvas = ref<HTMLCanvasElement | null>(null)
const aiByToolCanvas = ref<HTMLCanvasElement | null>(null)
const costByProviderCanvas = ref<HTMLCanvasElement | null>(null)
const revenueVsCostCanvas = ref<HTMLCanvasElement | null>(null)
const proSubsCanvas = ref<HTMLCanvasElement | null>(null)

const chartRefs: Record<string, Ref<HTMLCanvasElement | null>> = {
    signups: signupsCanvas, revenue: revenueCanvas, aiByTool: aiByToolCanvas,
    costByProvider: costByProviderCanvas, revenueVsCost: revenueVsCostCanvas, proSubs: proSubsCanvas,
}
const chartInstances: Record<string, any> = {}

async function loadChartJs() {
    const { Chart, LineController, BarController, DoughnutController, ArcElement, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend } = await import('chart.js')
    Chart.register(LineController, BarController, DoughnutController, ArcElement, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend)
    return Chart
}

function destroyChart(key: string) {
    if (chartInstances[key]) { chartInstances[key].destroy(); chartInstances[key] = null }
}

async function drawChart(key: string, canvasRef: Ref<HTMLCanvasElement | null>, configFn: () => any) {
    await nextTick()
    const canvas = canvasRef.value
    if (!canvas) return
    const ctx = canvas.getContext('2d')
    if (!ctx) return
    destroyChart(key)
    const Chart = await loadChartJs()
    try {
        chartInstances[key] = new Chart(ctx, configFn())
    } catch (e) {
        console.error(`Chart ${key} failed:`, e)
    }
}

function timeLabels(data: TimeSeriesPoint[] | DualSeriesPoint[] | undefined | null): string[] {
    if (!data || !data.length) return []
    if (chartPeriod.value === 'lifetime') {
        return (data as any[]).map((d: any) => d.date) // "Mar 2025" from backend
    }
    return (data as any[]).map((d: any) => {
        if (d.date.includes(':')) return d.date // hourly: "14:00"
        // Parse as local date to avoid UTC timezone shift
        const [y, m, day] = d.date.split('-').map(Number)
        const dt = new Date(y, m - 1, day)
        if (chartPeriod.value === '7d') {
            return dt.toLocaleDateString('en', { weekday: 'short' })
        }
        return dt.toLocaleDateString('en', { month: 'short', day: 'numeric' })
    })
}

function isEmptySeries(data: TimeSeriesPoint[] | DualSeriesPoint[] | undefined | null): boolean {
    if (!data || !data.length) return true
    return data.every((d: any) => (d.value ?? d.revenue ?? 0) === 0)
}

async function drawAllCharts() {
    const period = chartPeriod.value

    await drawChart('signups', chartRefs.signups, () => ({
        type: 'bar', data: { labels: timeLabels(props.signupsChart[period]), datasets: [{ data: props.signupsChart[period].map(d => d.value), backgroundColor: '#a5b4fc', borderColor: '#818cf8', borderWidth: 1, borderRadius: 4, barPercentage: 0.6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: (v: any) => v % 1 === 0 ? v : '', stepSize: 1 } }, x: { border: { display: false }, grid: { display: false } } } },
    }))

    await drawChart('revenue', chartRefs.revenue, () => ({
        type: 'bar', data: { labels: timeLabels(props.revenueChart[period]), datasets: [{ data: props.revenueChart[period].map(d => d.value), backgroundColor: '#22c55e', borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { border: { display: false }, grid: { display: false } } } },
    }))

    await drawChart('revenueVsCost', chartRefs.revenueVsCost, () => ({
        type: 'bar',
        data: {
            labels: timeLabels(props.revenueVsCost[period]),
            datasets: [
                { label: 'Revenue', data: props.revenueVsCost[period].map(d => d.revenue), backgroundColor: '#22c55e', borderRadius: 4 },
                { label: 'Cost', data: props.revenueVsCost[period].map(d => d.cost), backgroundColor: '#ef4444', borderRadius: 4 },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 10 } } } }, scales: { y: { beginAtZero: true }, x: { border: { display: false }, grid: { display: false } } } },
    }))

    if (isProAvailable.value) {
        await drawChart('proSubs', chartRefs.proSubs, () => ({
            type: 'line', data: { labels: timeLabels(props.proSubs[period]), datasets: [{ data: props.proSubs[period].map(d => d.value), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true, tension: 0.3, pointRadius: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
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
        await drawChart('costByProvider', chartRefs.costByProvider, () => ({
            type: 'bar',
            data: { labels: props.costByProvider.map(p => p.label), datasets: [{ data: props.costByProvider.map(p => p.cost || 0), backgroundColor: '#f59e0b', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y' as const, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, border: { display: false }, grid: { display: false } } } },
        }))
    }
}

watch(chartPeriod, () => drawAllCharts())
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

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Dashboard') }}</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ t('Overview of your platform') }}</p>
        </div>

        <!-- Demo Mode Indicator -->
        <div v-if="isDemo" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
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
        <div v-if="notesReminders.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ t('Note Reminders') }}</p>
                    <div class="mt-1 space-y-0.5">
                        <p v-for="n in notesReminders" :key="n.id" class="text-sm text-amber-700 dark:text-amber-400">{{ n.subject }}</p>
                    </div>
                </div>
                <button @click="editingNoteId = undefined; showNotesModal = true" class="shrink-0 text-xs font-bold text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200">{{ t('View all') }}</button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <StatsCard v-for="card in cards.filter(c => c.visible)" :key="card.title" :title="card.title" :value="card.value" :change="card.change" :change-type="card.changeType" :color="card.color">
                <template #icon>
                    <svg v-if="card.icon === 'users'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    <svg v-else-if="card.icon === 'dollar'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else-if="card.icon === 'spark'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                    <svg v-else-if="card.icon === 'credits'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75" /></svg>
                    <svg v-else-if="card.icon === 'plans'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path d="M6 6h.008v.008H6V6z" /></svg>
                    <svg v-else-if="card.icon === 'ticket'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                </template>
            </StatsCard>
        </div>

        <!-- Chart period toggle -->
        <div class="flex items-center justify-between gap-2 mb-6">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 mr-2">{{ t('Chart range') }}:</span>
                <button v-for="opt in periodOptions" :key="opt" @click="chartPeriod = opt"
                    class="px-3 py-1 text-xs rounded-lg border transition-colors"
                    :class="chartPeriod === opt ? 'btn-primary border-primary-600' : 'bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-surface-700 hover:border-primary-300'">
                    {{ (periodLabels as any)[opt] }}
                </button>
            </div>
            <div ref="exportEl" class="relative">
                <button @click.stop="showExportMenu = !showExportMenu" :disabled="exporting"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ exporting ? t('Exporting...') : t('Export XLSX') }}
                </button>
                <div v-if="showExportMenu"
                    class="absolute right-0 top-full mt-1 z-50 w-52 rounded-xl border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 p-1.5 shadow-lg">
                    <button v-for="et in exportTypes" :key="et.value"
                        @click="doDashboardExport(et.value, 'xlsx')"
                        class="w-full rounded-lg px-3 py-2 text-start text-sm text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-surface-800 transition-colors">
                        {{ et.label }}
                    </button>
                    <div class="border-t border-gray-100 dark:border-surface-800 my-1" />
                    <Link :href="route('admin.reports.export-center')"
                        class="block rounded-lg px-3 py-2 text-sm text-primary-600 hover:bg-primary-50 dark:hover:bg-surface-800 transition-colors">
                        {{ t('Open Export Center →') }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Row 1: Quick Actions (left) + User Signups (right) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">{{ t('Quick Actions') }}</h3>
                <div class="space-y-2">
                    <Link v-if="can('ai.tools')" :href="route('admin.ai.tools.create')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:border-primary-200 transition-all flex items-center gap-3">
                        <svg class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>{{ t('Add AI Tool') }}</Link>
                    <Link :href="route('admin.announcements.index')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-warning-50 dark:hover:bg-warning-900/20 hover:border-warning-200 transition-all flex items-center gap-3">
                        <svg class="w-4 h-4 text-warning-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" /></svg>{{ t('Send Announcement') }}</Link>
                    <Link v-if="can('support.tickets')" :href="route('admin.support.tickets.index')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-danger-50 dark:hover:bg-danger-900/20 hover:border-danger-200 transition-all flex items-center gap-3">
                        <svg class="w-4 h-4 text-danger-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>{{ t('Support Tickets') }}</Link>
                    <Link :href="route('admin.settings.index')" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-success-50 dark:hover:bg-success-900/20 hover:border-success-200 transition-all flex items-center gap-3">
                        <svg class="w-4 h-4 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>{{ t('Site Settings') }}</Link>
                    <button @click="router.post(route('admin.system.maintenance.toggle'), {}, { preserveState: true })" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-700 transition-all flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>{{ t('Maintenance Mode') }}</button>
                    <button @click="showNotesModal = true" class="w-full text-left px-4 py-3 rounded-xl bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:border-indigo-200 transition-all flex items-center gap-3">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>{{ t('Make a Note') }}</button>
                </div>
            </div>
            <div class="lg:col-span-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm flex flex-col">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 shrink-0">{{ t('User Signups') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="!isEmptySeries(signupsChart[chartPeriod])" class="relative flex-1 min-h-48"><canvas ref="signupsCanvas" /></div>
                <div v-else class="flex-1 min-h-48 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No signup data for this period') }}</p>
                </div>
            </div>
        </div>

        <!-- Row 2: Revenue + Revenue vs Cost -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Revenue') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="!isEmptySeries(revenueChart[chartPeriod])" class="relative h-48"><canvas ref="revenueCanvas" /></div>
                <div v-else class="h-48 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No revenue data for this period') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Revenue vs Cost') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="!isEmptySeries(revenueVsCost[chartPeriod])" class="relative h-48"><canvas ref="revenueVsCostCanvas" /></div>
                <div v-else class="h-48 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No data for this period') }}</p>
                </div>
            </div>
        </div>

        <!-- Row 3: AI by Tool + Cost by Provider (static aggregation, not period-based) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('AI Usage by Tool') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="aiByTool.length" class="relative h-56"><canvas ref="aiByToolCanvas" /></div>
                <div v-else class="h-56 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-sm">{{ t('No AI tool data yet') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Cost by Provider') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="hasCostData" class="relative h-56"><canvas ref="costByProviderCanvas" /></div>
                <div v-else class="h-56 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-sm">{{ t('No cost data yet') }}</p>
                </div>
            </div>
        </div>

        <!-- Row 4: Pro Subs + Geo Usage -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-if="isProAvailable" class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Pro Subscriptions') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="!isEmptySeries(proSubs[chartPeriod])" class="relative h-48"><canvas ref="proSubsCanvas" /></div>
                <div v-else class="h-48 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 3v18h18" /><path d="M3 12l5-5 4 4 5-7" /></svg>
                    <p class="text-sm">{{ t('No subscription data for this period') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Geo Usage') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels[chartPeriod] }})</span></h3>
                <div v-if="geoUsage.length" class="space-y-2">
                    <div v-for="g in geoUsage.slice(0, 8)" :key="g.country" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ g.country }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ g.logins.toLocaleString() }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                    <svg class="w-10 h-10 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
                    <p class="text-sm">{{ t('No geo data yet') }}</p>
                </div>
            </div>
        </div>

        <!-- List sections: Traffic Sources + Top Tools + Top Models + Recent Users -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Traffic Sources') }} <span class="text-xs text-gray-400 font-normal">({{ periodLabels['30d'] }})</span></h3>
                <div v-if="trafficSources.length" class="space-y-2">
                    <div v-for="src in trafficSources" :key="src.label" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ src.label }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ (src.count || 0).toLocaleString() }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                    <p class="text-xs">{{ t('No data') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Top AI Tools') }}</h3>
                <div v-if="topToolsByUsage.length" class="space-y-2">
                    <div v-for="tool in topToolsByUsage.slice(0, 6)" :key="tool.tool_slug" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400 truncate mr-2">{{ tool.tool_slug }}</span>
                        <span class="font-medium text-gray-900 dark:text-white shrink-0">{{ tool.count?.toLocaleString() || 0 }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-xs">{{ t('No data') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Top AI Models') }}</h3>
                <div v-if="topModelsByUsage.length" class="space-y-2">
                    <div v-for="model in topModelsByUsage.slice(0, 6)" :key="model.model" class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400 truncate mr-2">{{ model.model }}</span>
                        <span class="font-medium text-gray-900 dark:text-white shrink-0">{{ model.count?.toLocaleString() || 0 }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-8 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <p class="text-xs">{{ t('No data') }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Recent Users') }}</h3>
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
            </div>
        </div>

        <!-- Activity Feed + Notes -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">{{ t('Recent Activity') }}</h3>
                <div v-if="activity.length" class="space-y-1">
                    <div v-for="(item, i) in activity.slice(0, 15)" :key="i" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-surface-800 transition-colors">
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
            </div>
            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('My Notes') }}</h3>
                    <button @click="editingNoteId = undefined; showNotesModal = true" class="text-xs font-bold text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ t('Manage') }}</button>
                </div>
                <div v-if="allNotes.length" class="space-y-2 max-h-80 overflow-y-auto">
                    <div v-for="note in allNotes.slice(0, 8)" :key="note.id" class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-surface-800 dark:bg-surface-800/50 group">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ note.subject }}</p>
                                <p v-if="note.description" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ note.description }}</p>
                                <span v-if="note.reminder_date" class="inline-block mt-1 text-[10px] text-amber-600 dark:text-amber-400">{{ new Date(note.reminder_date).toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center gap-0.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editingNoteId = note.id; showNotesModal = true" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-300">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                                </button>
                                <button @click="deleteDashboardNote(note.id)" class="rounded p-1 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-12 text-gray-400">
                    <svg class="w-8 h-8 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    <p class="text-xs">{{ t('No notes yet') }}</p>
                </div>
            </div>
        </div>
    </div>

    <AdminNotesModal :open="showNotesModal" :edit-note-id="editingNoteId" @close="showNotesModal = false; editingNoteId = undefined; fetchReminders()" />
</template>
