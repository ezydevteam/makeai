<script setup lang="ts">
import { computed, ref, watch, onMounted, nextTick, onUnmounted } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import QRCode from 'qrcode'

interface Program {
    commission_type: 'percentage' | 'fixed'
    commission_value: number
    min_payout: number
    max_payout: number
    payouts_enabled: boolean
    payout_methods: string[]
    commission_hold_days: number
    allow_custom_alias: boolean
    terms_page: { title: string; url: string } | null
}

interface ChartPoint { label: string; clicks: number; registrations: number; conversions: number; is_current?: boolean }

interface MarketingBanner { url: string; label?: string }
interface MarketingEmail { subject: string; body: string }
interface MarketingPost { text: string; platform?: string }

const props = defineProps<{
    program: Program
    availableBalance: number
    creditPricePerUnit: number
    stats: {
        total_earnings: number
        pending_earnings: number
        total_referrals: number
        successful_conversions: number
    }
    referral: { code: string; custom_slug: string | null; link: string; alias_link: string | null }
    chart: ChartPoint[]
    marketing: { banners: MarketingBanner[]; emails: MarketingEmail[]; posts: MarketingPost[] }
    referrals: {
        data: Array<{ email: string; joined_at: string | null; status: string; commission: number }>
        links: Array<{ url: string | null; label: string; active: boolean }>
        current_page: number
        last_page: number
        total: number
        from: number | null
        to: number | null
    }
    commissions: {
        data: Array<{ id: number; amount: string | number; status: string; created_at: string; payment?: { amount: string | number; currency: string } | null }>
        links: Array<{ url: string | null; label: string; active: boolean }>
        current_page: number
        last_page: number
        total: number
        from: number | null
        to: number | null
    }
    payouts: { data: Array<{ id: number; amount: string | number; method: string; status: string; created_at: string }> }
    filters: { status?: string | null }
}>()

const { t } = useTranslate()
const { isDark } = useTheme()
const { formatCurrency, formatNumber } = useNumberFormat()
const selectedMethod = ref(props.program.payout_methods[0] ?? 'paypal')
// Mirrors the server-side filter rather than owning it: the list is paginated, so a local
// filter only ever searched the 10 newest rows — "paid" and "cancelled" commissions live
// further down the list and appeared to not exist at all.
const commissionStatusFilter = computed(() => props.filters.status ?? '')
const isPayoutModalOpen = ref(false)
const isEditingAlias = ref(props.program.allow_custom_alias && !props.referral.custom_slug)
const referralCopied = ref(false)
let referralCopiedTimer: ReturnType<typeof setTimeout> | null = null

const form = useForm({
    amount: String(props.program.min_payout),
    method: selectedMethod.value,
    details: { paypal_email: '', bank_account: '' },
})
const aliasForm = useForm({ custom_slug: props.referral.custom_slug ?? '' })

// ── QR Code ──
const qrDataUrl = ref('')
const qrError = ref('')
const showQr = ref(false)

const generateQr = async () => {
    if (qrDataUrl.value) { showQr.value = !showQr.value; return }
    try {
        qrDataUrl.value = await QRCode.toDataURL(referralUrl.value, {
            errorCorrectionLevel: 'M',
            margin: 2,
            width: 224,
            color: { dark: '#111827', light: '#ffffff' },
        })
        showQr.value = true
    } catch {
        qrError.value = t('Unable to generate QR code.')
    }
}

const downloadQr = () => {
    if (!qrDataUrl.value) return
    const a = document.createElement('a')
    a.download = 'referral-qr.png'
    a.href = qrDataUrl.value
    a.click()
}

const chartPeriod = ref('1M')
const loadingChart = ref(false)
const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: InstanceType<typeof import('chart.js').Chart> | null = null
const chartData = ref<Array<{ label: string; clicks: number; registrations: number; conversions: number; is_current?: boolean }>>([])

if (Array.isArray(props.chart)) {
    chartData.value = props.chart
}

const hasChartData = computed(() => {
    return chartData.value.length > 0 && chartData.value.some(d => d.clicks > 0 || d.registrations > 0 || d.conversions > 0)
})

const buildChart = async () => {
    await nextTick()
    if (!chartCanvas.value) return
    const { Chart, LineController, BarController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend } = await import('chart.js')
    Chart.register(LineController, BarController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend)

    if (chartInstance) {
        chartInstance.destroy()
        chartInstance = null
    }
    if (!hasChartData.value) return

    const gridColor = isDark.value ? 'rgba(148, 163, 184, 0.08)' : 'rgba(148, 163, 184, 0.12)'
    const axisBorder = isDark.value ? 'rgba(148, 163, 184, 0.08)' : 'rgba(203, 213, 225, 0.4)'
    const tickColor = isDark.value ? '#9ca3af' : '#64748b'

    chartInstance = new Chart(chartCanvas.value, {
        type: 'bar',
        data: {
            labels: chartData.value.map((d) => d.label),
            datasets: [
                {
                    type: 'bar',
                    label: t('Clicks'),
                    data: chartData.value.map((d) => d.clicks),
                    backgroundColor: 'rgba(59, 130, 246, 0.65)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    // Pill-topped bars, matching the Usage chart: the radius is clamped to
                    // half the bar width, and skipping the bottom edge keeps it square where
                    // it meets the axis.
                    borderRadius: 20,
                    borderSkipped: 'bottom',
                    maxBarThickness: 20
                },
                {
                    type: 'line',
                    label: t('Registrations'),
                    data: chartData.value.map((d) => d.registrations),
                    borderColor: '#10b981',
                    backgroundColor: '#10b981',
                    fill: false,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 0
                },
                {
                    type: 'line',
                    label: t('Conversions'),
                    data: chartData.value.map((d) => d.conversions),
                    borderColor: '#8b5cf6',
                    backgroundColor: '#8b5cf6',
                    fill: false,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 0
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 11 } } } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: tickColor, font: { size: 10 } },
                    grid: { color: gridColor },
                    border: { color: axisBorder }
                },
                x: {
                    offset: true,
                    ticks: { color: tickColor, font: { size: 10 }, maxRotation: 0 },
                    grid: { display: false },
                    border: { color: axisBorder }
                }
            },
        },
    })
}

async function changePeriod(period: string) {
    if (loadingChart.value) return
    chartPeriod.value = period
    loadingChart.value = true
    try {
        const response = await axios.get(route('user.dashboard.affiliate.chart'), {
            params: { period }
        })
        chartData.value = response.data
        await buildChart()
    } catch (e) {
        console.error(e)
    } finally {
        loadingChart.value = false
    }
}

onMounted(() => nextTick(buildChart))
onUnmounted(() => chartInstance?.destroy())
onUnmounted(() => {
    if (referralCopiedTimer) {
        clearTimeout(referralCopiedTimer)
    }
})

// Rebuild the chart when the theme toggles so colors stay in sync with dark mode.
watch(isDark, () => {
    if (chartCanvas.value) {
        buildChart()
    }
})

// ── Shared ──
const shareText = computed(() => encodeURIComponent(t('Try this AI platform with my referral link.')))
const referralUrl = computed(() => props.referral.alias_link || props.referral.link)
const formatChoiceLabel = (value: string) => value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())

const formatMoney = (value: string | number | undefined | null) => {
    if (value === null || value === undefined) return '—'
    const num = typeof value === 'number' ? value : parseFloat(value)
    return isNaN(num) ? String(value) : formatCurrency(num)
}

const commissionTerms = computed(() => {
    const rate = props.program.commission_type === 'fixed'
        ? formatMoney(props.program.commission_value)
        : `${props.program.commission_value}%`
    const parts = [t('Earn :rate per referred sale', { rate })]
    if (props.program.commission_hold_days > 0) {
        parts.push(t('held :days days', { days: props.program.commission_hold_days }))
    }
    return parts.join(' · ')
})

const visibleStats = computed(() => [
    {
        key: 'total_earnings',
        label: t('Total earnings'),
        value: formatMoney(props.stats.total_earnings),
        icon: 'ti ti-cash',
        iconClass: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        cardClass: 'border-emerald-100/80 bg-white/95 dark:border-emerald-900/60 dark:!bg-surface-900'
    },
    {
        key: 'pending_earnings',
        label: t('Pending earnings'),
        value: formatMoney(props.stats.pending_earnings),
        icon: 'ti ti-cash-banknote',
        iconClass: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        cardClass: 'border-amber-100/80 bg-white/95 dark:border-amber-900/60 dark:!bg-surface-900'
    },
    {
        key: 'total_referrals',
        label: t('Total referrals'),
        value: formatNumber(props.stats.total_referrals),
        icon: 'ti ti-users',
        iconClass: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        cardClass: 'border-sky-100/80 bg-white/95 dark:border-sky-900/60 dark:!bg-surface-900'
    },
    {
        key: 'successful_conversions',
        label: t('Successful conversions'),
        value: formatNumber(props.stats.successful_conversions),
        icon: 'ti ti-bolt',
        iconClass: 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        cardClass: 'border-violet-100/80 bg-white/95 dark:border-violet-900/60 dark:!bg-surface-900'
    }
])

const visibleCommissions = computed(() => props.commissions.data)

const formatDateTime = (value: string | null | undefined) => {
    if (!value) return t('—')

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date)
}

const copy = async (value: string) => {
    try { await navigator.clipboard.writeText(value) }
    catch {
        const ta = document.createElement('textarea')
        ta.value = value
        ta.setAttribute('readonly', 'true')
        ta.style.position = 'fixed'; ta.style.insetInlineStart = '-9999px'
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta)
    }
}

const copyReferralLink = async () => {
    await copy(referralUrl.value)

    referralCopied.value = true
    if (referralCopiedTimer) {
        clearTimeout(referralCopiedTimer)
    }
    referralCopiedTimer = setTimeout(() => {
        referralCopied.value = false
    }, 1400)
}

// Estimated wallet credits for a "credits" payout — mirrors the server-side
// conversion (floor(round(amount / credit_price_per_unit, 6))) so the affiliate
// sees the same figure that will actually be granted.
const estimatedCredits = computed(() => {
    const amount = parseFloat(form.amount)
    const price = props.creditPricePerUnit
    if (!Number.isFinite(amount) || amount <= 0 || !price || price <= 0) return 0

    return Math.floor(Number((amount / price).toFixed(6)))
})

const openPayoutModal = () => {
    if (!props.program.payouts_enabled) return

    selectedMethod.value = props.program.payout_methods[0] ?? selectedMethod.value
    form.method = selectedMethod.value
    form.clearErrors()
    isPayoutModalOpen.value = true
}
const closePayoutModal = () => {
    isPayoutModalOpen.value = false
}
const submit = () => {
    form.method = selectedMethod.value
    form.post(route('user.dashboard.affiliate.payouts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closePayoutModal()
            form.reset('amount', 'details')
        },
    })
}
const saveAlias = () => aliasForm.transform((d) => ({ custom_slug: d.custom_slug.trim().toLowerCase() || null })).post(route('user.dashboard.affiliate.alias.update'), { preserveScroll: true, onSuccess: () => { isEditingAlias.value = false } })
const normalizeAlias = () => { aliasForm.custom_slug = aliasForm.custom_slug.toLowerCase().replace(/[^a-z0-9-]/g, '') }
const editAlias = () => {
    if (isEditingAlias.value) {
        cancelAliasEdit()
        return
    }

    isEditingAlias.value = true
}
const cancelAliasEdit = () => {
    aliasForm.reset('custom_slug')
    aliasForm.clearErrors('custom_slug')
    aliasForm.custom_slug = props.referral.custom_slug ?? ''
    isEditingAlias.value = false
}
const filterCommissions = (status: string) => {
    if (commissionStatusFilter.value === status) return

    router.get(
        route('user.dashboard.affiliate'),
        status ? { status } : {},
        { preserveScroll: true, preserveState: true, only: ['commissions', 'filters'] },
    )
}
</script>

<template>
    <Head :title="t('Affiliate')" />

    <UserDashboardLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliate Program') }}</h1>
                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Share your link, track conversions, and request payouts.') }}</p>
                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                        <i class="ti ti-affiliate"></i>{{ commissionTerms }}
                    </p>
                </div>
                <button
                    v-if="program.payouts_enabled"
                    type="button"
                    class="shrink-0 btn-primary"
                    @click="openPayoutModal"
                >
                    <i class="ti ti-send mr-1"></i>
                    {{ t('Request Payout') }}
                </button>
            </div>

            <!-- Stats -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in visibleStats"
                    :key="stat.key"
                    class="group rounded-2xl border p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:shadow-lg"
                    :class="stat.cardClass"
                >
                    <div class="flex flex-col items-start justify-between gap-4 w-full">
                        <div :class="stat.iconClass" class="shrink-0 flex h-10 w-10 items-center justify-center rounded-2xl">
                            <i :class="['text-2xl', stat.icon]"></i>
                        </div>
                        <div class="min-w-0 w-full">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 truncate">{{ stat.label }}</p>
                            <p class="mt-3 font-heading text-xl font-bold text-gray-950 dark:!text-white tracking-tight sm:text-xl xl:text-2xl" :title="stat.value">{{ stat.value }}</p>
                        </div>
                    </div>
                </article>
            </div>
            <!-- Referral Link -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                    <label class="min-w-0 flex-1">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-900 dark:text-gray-400">{{ t('Your Referral link') }}</span>
                        <div class="relative">
                            <input
                                :value="referralUrl"
                                readonly
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pe-10 ps-3 text-sm text-gray-900 transition focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:bg-gray-900"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 end-0 flex w-10 items-center justify-center rounded-r-xl text-gray-400 transition hover:text-primary-600 dark:hover:text-primary-300"
                                :aria-label="t('Copy referral link')"
                                @click="copyReferralLink"
                            >
                                <i :class="[referralCopied ? 'ti ti-check text-primary-600 dark:text-primary-400' : 'ti ti-copy', 'text-base']"></i>
                            </button>
                        </div>
                    </label>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <button v-if="program.allow_custom_alias" type="button" class="rounded-xl border border-primary-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:!bg-primary-50 dark:border-primary-900/30 dark:bg-gray-900 dark:text-primary-300 dark:hover:!bg-primary-900/20" @click="editAlias">
                            {{ isEditingAlias ? t('Close edit') : t('Edit alias') }}
                        </button>
                        <button type="button" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:!bg-surface-700/30 sm:w-auto" @click="generateQr" :title="t('QR Code')">
                            <i class="ti ti-qrcode"></i>
                        </button>
                    </div>
                </div>
                <div v-if="showQr" class="mt-4 flex flex-col items-center gap-3 rounded-2xl border border-sky-100 bg-sky-50 p-4 dark:border-sky-500/10 dark:bg-sky-500/10">
                    <img v-if="qrDataUrl" :src="qrDataUrl" alt="Referral QR code" class="h-48 w-48 rounded-lg border border-gray-200 dark:border-gray-600" />
                    <p v-if="qrError" class="text-sm text-red-500">{{ qrError }}</p>
                    <button v-if="qrDataUrl" type="button" class="rounded-full border border-gray-200 bg-white px-4 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" @click="downloadQr">{{ t('Download PNG') }}</button>
                </div>
                <form v-if="isEditingAlias" class="mt-4 grid gap-3 rounded-2xl border border-gray-100 bg-gray-50 p-4 md:grid-cols-[minmax(0,1fr)_auto] dark:border-gray-700 dark:bg-gray-800" @submit.prevent="saveAlias">
                    <label class="min-w-0">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ t('Referral name') }}</span>
                        <input
                            v-model="aliasForm.custom_slug"
                            type="text"
                            maxlength="60"
                            inputmode="url"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm lowercase shadow-sm transition focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            :placeholder="t('my-brand')"
                            @input="normalizeAlias"
                        />
                        <span class="mt-1 block text-xs text-gray-500">{{ t('Use lowercase letters, numbers, and hyphens only.') }}</span>
                        <p v-if="aliasForm.errors.custom_slug" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">
                            {{ aliasForm.errors.custom_slug }}
                        </p>
                    </label>
                    <div class="flex flex-wrap gap-2 self-center">
                        <button type="submit" :disabled="aliasForm.processing" class="rounded-full border border-primary-200 bg-primary-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600 disabled:opacity-50">
                            {{ aliasForm.processing ? t('Saving...') : t('Save alias') }}
                        </button>
                        <button type="button" class="rounded-full border border-gray-200 bg-white px-5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" @click="cancelAliasEdit">
                            {{ t('Cancel') }}
                        </button>
                    </div>
                </form>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a v-for="s in [{label:'Facebook',u:`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralUrl)}`},{label:'Twitter X',u:`https://twitter.com/intent/tweet?text=${shareText}&url=${encodeURIComponent(referralUrl)}`},{label:'WhatsApp',u:`https://wa.me/?text=${shareText}%20${encodeURIComponent(referralUrl)}`},{label:'LinkedIn',u:`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(referralUrl)}`},{label:'Telegram',u:`https://t.me/share/url?url=${encodeURIComponent(referralUrl)}&text=${shareText}`}]" :key="s.label" :href="s.u" target="_blank" class="rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:text-primary-300">{{ s.label }}</a>
                </div>
            </section>

            <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Performance') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <span v-if="chartPeriod === '1D'">{{ t('Hourly traffic and conversions over the last 24 hours.') }}</span>
                            <span v-else-if="chartPeriod === '7D'">{{ t('Daily traffic and conversions over the last 7 days.') }}</span>
                            <span v-else-if="chartPeriod === '1Y'">{{ t('Monthly traffic and conversions over the last 12 months.') }}</span>
                            <span v-else>{{ t('Daily traffic and conversions over the last 30 days.') }}</span>
                        </p>
                    </div>
                    <div class="flex rounded-full border border-gray-200 bg-gray-50 p-1 text-xs dark:border-gray-700 dark:bg-gray-800 sm:w-auto shrink-0 self-start sm:self-center">
                        <button v-for="p in ['1D', '7D', '1M', '1Y']" :key="p" :class="chartPeriod === p ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-full px-3 py-1.5 transition-colors" @click="changePeriod(p)">{{ p }}</button>
                    </div>
                </div>
                <div class="relative mt-4 h-72">
                    <div v-if="!hasChartData" class="flex flex-col items-center justify-center h-full border border-dashed border-gray-200 dark:border-surface-800 rounded-2xl bg-gray-50/30 dark:bg-surface-900/30">
                        <i class="ti ti-chart-bar-off text-3xl text-gray-300 dark:text-gray-600 mb-2"></i>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('No data found') }}</p>
                    </div>
                    <canvas v-else ref="chartCanvas" />
                    <!-- Loading overlay -->
                    <div v-if="loadingChart" class="absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-[2px] transition-all dark:bg-gray-900/70">
                        <div class="flex flex-col items-center gap-2">
                            <i class="ti ti-loader animate-spin text-2xl text-primary-500"></i>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Updating...') }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mt-6 space-y-6">
                <div class="min-w-0 space-y-6">
                    <!-- Referrals Table -->
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100/80 p-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Referrals') }}</h2></div>
                        <div class="min-w-0 overflow-x-auto">
                            <table class="w-full table-fixed text-left text-sm">
                                <thead class="bg-gray-50/80 text-xs uppercase text-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ t('User') }}</th>
                                        <th class="px-4 py-3 text-center">{{ t('Joined') }}</th>
                                        <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                                        <th class="px-4 py-3 text-center">{{ t('Commission') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(ref, index) in referrals.data" :key="`${ref.email}-${ref.joined_at}-${index}`" class="border-t border-gray-100/80 transition hover:bg-sky-50/60 dark:border-gray-800 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 break-all text-gray-700 dark:text-gray-200">{{ ref.email }}</td>
                                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ formatDateTime(ref.joined_at) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span :class="ref.status === 'clicked' ? 'bg-gray-200/60 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300' : 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'" class="rounded-full px-2.5 py-1 text-xs font-bold">{{ t(ref.status) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(ref.commission) }}</td>
                                    </tr>
                                    <tr v-if="referrals.data.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ t('No referrals yet. Share your link to get started.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="referrals.last_page > 1" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
                            <Pagination
                                :links="referrals.links"
                                :from="referrals.from"
                                :to="referrals.to"
                                :total="referrals.total"
                                :current-page="referrals.current_page"
                                :last-page="referrals.last_page"
                            />
                        </div>
                    </section>

                    <!-- Commissions Table with Filter -->
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                        <div class="flex flex-col gap-3 border-b border-gray-100/80 p-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Commissions') }}</h2>
                            <div class="flex flex-wrap gap-1">
                                <button v-for="s in ['', 'pending', 'approved', 'paid', 'rejected', 'cancelled']" :key="s" type="button" :class="commissionStatusFilter === s ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40' : 'text-gray-500 hover:bg-gray-100 dark:hover:!text-white dark:hover:bg-surface-950/60'" class="rounded-full px-2.5 py-1 text-xs font-medium" @click="filterCommissions(s)">{{ s ? formatChoiceLabel(s) : t('All') }}</button>
                            </div>
                        </div>
                        <div class="min-w-0 overflow-x-auto">
                            <table class="w-full table-fixed text-left text-sm">
                                <thead class="bg-gray-50/80 text-xs uppercase text-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ t('Date') }}</th>
                                        <th class="px-4 py-3 text-center">{{ t('Purchased') }}</th>
                                        <th class="px-4 py-3 text-center">{{ t('Amount') }}</th>
                                        <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in visibleCommissions" :key="c.id" class="border-t border-gray-100/80 transition hover:bg-sky-50/60 dark:border-gray-800 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ formatDateTime(c.created_at) }}</td>
                                        <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-200">{{ c.payment ? c.payment.amount : '—' }}</td>
                                        <td class="px-4 py-3 text-center font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(c.amount) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span :class="c.status === 'approved' || c.status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' : c.status === 'pending' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : c.status === 'cancelled' ? 'bg-gray-200/60 text-gray-600 dark:bg-gray-500/15 dark:text-gray-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'" class="rounded-full px-2.5 py-1 text-xs font-bold">
                                                {{ t(c.status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="visibleCommissions.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                            <span v-if="commissionStatusFilter">{{ t('No :status commissions.', { status: formatChoiceLabel(commissionStatusFilter).toLowerCase() }) }}</span>
                                            <span v-else>{{ t('No commissions yet.') }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="commissions.last_page > 1" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">
                            <Pagination
                                :links="commissions.links"
                                :from="commissions.from"
                                :to="commissions.to"
                                :total="commissions.total"
                                :current-page="commissions.current_page"
                                :last-page="commissions.last_page"
                            />
                        </div>
                    </section>
                </div>

                <!-- Payout Request / History -->
                <section v-if="!program.payouts_enabled" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Payout requests disabled') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Affiliate earnings are tracked, but payout requests are currently disabled by admin.') }}</p>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100/80 p-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Payout history') }}</h2></div>
                    <div class="min-w-0 overflow-x-auto">
                        <table class="w-full table-fixed text-left text-sm">
                            <thead class="bg-gray-50/80 text-xs uppercase text-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">{{ t('Date') }}</th>
                                    <th class="px-4 py-3 text-center">{{ t('Amount') }}</th>
                                    <th class="px-4 py-3 text-center">{{ t('Method') }}</th>
                                    <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in payouts.data" :key="p.id" class="border-t border-gray-100/80 transition hover:bg-sky-50/60 dark:border-gray-800 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ formatDateTime(p.created_at) }}</td>
                                    <td class="px-4 py-3 text-center font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(p.amount) }}</td>
                                    <td class="px-4 py-3 text-center capitalize text-gray-700 dark:text-gray-200">{{ t(String(p.method).replaceAll('_', ' ')) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="p.status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' : p.status === 'processing' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : p.status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'" class="rounded-full px-2.5 py-1 text-xs font-bold">
                                            {{ t(p.status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="payouts.data.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ t('No payout requests yet.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Marketing Materials -->
            <div v-if="marketing.banners.length || marketing.emails.length || marketing.posts.length" class="mt-6 grid gap-6 lg:grid-cols-3">
                <section v-if="marketing.banners.length" class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Banners') }}</h2>
                    <div class="space-y-3">
                        <div v-for="(b, i) in marketing.banners" :key="i" class="rounded-2xl border border-gray-200 bg-white p-3 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-950/40">
                            <img v-if="b.url" :src="b.url" :alt="b.label || `Banner ${i + 1}`" class="mb-2 h-auto w-full rounded" />
                            <p v-if="b.label" class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">{{ b.label }}</p>
                            <button type="button" class="text-xs font-semibold text-primary-600 hover:text-primary-500" @click="copy(b.url)">{{ t('Copy URL') }}</button>
                        </div>
                    </div>
                </section>
                <section v-if="marketing.emails.length" class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Email templates') }}</h2>
                    <div class="space-y-3">
                        <details v-for="(e, i) in marketing.emails" :key="i" class="rounded-2xl border border-gray-200 bg-white p-3 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-950/40">
                            <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">{{ e.subject }}</summary>
                            <p class="mt-2 whitespace-pre-wrap text-xs text-gray-500 dark:text-gray-400">{{ e.body }}</p>
                            <button type="button" class="mt-2 text-xs font-semibold text-primary-600 hover:text-primary-500" @click="copy(e.body)">{{ t('Copy text') }}</button>
                        </details>
                    </div>
                </section>
                <section v-if="marketing.posts.length" class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Social posts') }}</h2>
                    <div class="space-y-3">
                        <div v-for="(p, i) in marketing.posts" :key="i" class="rounded-2xl border border-gray-200 bg-white p-3 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-950/40">
                            <p v-if="p.platform" class="mb-1 text-xs font-bold uppercase text-gray-400">{{ p.platform }}</p>
                            <p class="break-words text-sm text-gray-700 dark:text-gray-300">{{ p.text }}</p>
                            <button type="button" class="mt-2 text-xs font-semibold text-primary-600 hover:text-primary-500" @click="copy(p.text)">{{ t('Copy text') }}</button>
                        </div>
                    </div>
                </section>
            </div>

            <AppModal
                :open="isPayoutModalOpen"
                max-width="max-w-xl"
                :title="t('Request Payout')"
                :subtitle="t('Available balance') + ': ' + formatMoney(availableBalance)"
                @close="closePayoutModal"
                has-form
                @submit="submit"
            >
                <div class="space-y-4">
                    <div>
                        <AppSelect v-model="selectedMethod" :options="program.payout_methods.map((m: string) => ({ value: m, label: formatChoiceLabel(m) }))" :label="t('Payout method')" />
                        <p v-if="form.errors.method" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.method }}</p>
                    </div>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Amount') }}</span>
                        <input v-model="form.amount" type="number" :min="program.min_payout" :max="program.max_payout > 0 ? program.max_payout : undefined" step="0.01" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:bg-gray-900" />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Minimum payout: :min', { min: formatMoney(program.min_payout) }) }}<template v-if="program.max_payout > 0"> · {{ t('Maximum :max', { max: formatMoney(program.max_payout) }) }}</template>
                        </span>
                        <p v-if="form.errors.amount" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.amount }}</p>
                    </label>
                    <label v-if="selectedMethod === 'paypal'" class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('PayPal email') }}</span>
                        <input v-model="form.details.paypal_email" type="email" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:bg-gray-900" />
                        <p v-if="form.errors['details.paypal_email']" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors['details.paypal_email'] }}</p>
                    </label>
                    <label v-if="selectedMethod === 'bank_transfer'" class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Bank details') }}</span>
                        <textarea v-model="form.details.bank_account" rows="4" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:bg-gray-900" />
                        <p v-if="form.errors['details.bank_account']" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors['details.bank_account'] }}</p>
                    </label>
                    <div v-if="selectedMethod === 'credits'" class="rounded-xl bg-sky-50 px-3 py-2.5 text-xs text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                        <p>{{ t('This payout will be converted into account credits at :price per credit.', { price: formatMoney(creditPricePerUnit) }) }}</p>
                        <p v-if="estimatedCredits > 0" class="mt-1 font-semibold">
                            <i class="ti ti-coins me-1"></i>{{ t('You will receive about :credits credits.', { credits: formatNumber(estimatedCredits) }) }}
                        </p>
                    </div>
                </div>

                <template #footer>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-full border border-gray-200 bg-white px-5 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800" @click="closePayoutModal">
                            {{ t('Cancel') }}
                        </button>
                        <button type="submit" :disabled="form.processing || availableBalance < program.min_payout" class="btn-primary disabled:opacity-60">
                            {{ form.processing ? t('Submitting...') : t('Request payout') }}
                        </button>
                    </div>
                </template>
            </AppModal>

            <!-- Terms -->
            <section v-if="program.terms_page" class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900 dark:text-gray-400">
                <span>{{ t('Affiliate terms apply.') }}</span>
                <a :href="program.terms_page.url" class="ms-1 font-semibold text-primary-600 hover:text-primary-500">{{ program.terms_page.title }}</a>
            </section>
        </div>
    </UserDashboardLayout>
</template>
