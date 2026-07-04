<script setup lang="ts">
import { computed, ref, watch, onMounted, nextTick, onUnmounted } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import AppSelect from '@/Components/AppSelect.vue'
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
    stats: {
        total_earnings: number
        pending_earnings: number
        total_referrals: number
        successful_conversions: number
    }
    referral: { code: string; custom_slug: string | null; link: string; alias_link: string | null }
    chart: ChartPoint[]
    marketing: { banners: MarketingBanner[]; emails: MarketingEmail[]; posts: MarketingPost[] }
    referrals: Array<{ email: string; joined_at: string | null; status: string; commission: number }>
    commissions: { data: Array<{ id: number; amount: string | number; status: string; created_at: string; payment?: { amount: string | number; currency: string } | null }> }
    payouts: { data: Array<{ id: number; amount: string | number; method: string; status: string; created_at: string }> }
}>()

const { t } = useTranslate()
const { isDark } = useTheme()
const { formatCurrency } = useNumberFormat()
const selectedMethod = ref(props.program.payout_methods[0] ?? 'paypal')
const commissionStatusFilter = ref('')
const isPayoutModalOpen = ref(false)
const isEditingAlias = ref(!props.referral.custom_slug)
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

const buildChart = async () => {
    if (!chartCanvas.value) return
    const { Chart, LineController, BarController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend } = await import('chart.js')
    Chart.register(LineController, BarController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler, Legend)

    if (chartInstance) chartInstance.destroy()

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
                    borderRadius: 4,
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
const statOrder = ['total_earnings', 'pending_earnings', 'total_referrals', 'successful_conversions'] as const
const visibleStats = computed(() => statOrder.map((key) => ({ key, value: props.stats[key] })))
const visibleCommissions = computed(() => (
    commissionStatusFilter.value
        ? props.commissions.data.filter((commission) => commission.status === commissionStatusFilter.value)
        : props.commissions.data
))
const statTileClassMap: Record<string, string> = {
    total_earnings: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    pending_earnings: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    total_referrals: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    successful_conversions: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
}
const getStatTileClass = (key: string) => statTileClassMap[key] ?? 'bg-slate-200 text-slate-800 dark:bg-gray-700 dark:text-slate-200'
const getStatIconClass = (key: string) => {
    if (key === 'total_referrals') return 'ti ti-users'
    if (key === 'total_earnings' || key === 'pending_earnings') return 'ti ti-currency-dollar'
    if (key === 'successful_conversions') return 'ti ti-bolt'

    return 'ti ti-chart-bar'
}
const isCurrencyStat = (key: string) => ['total_earnings', 'pending_earnings'].includes(key)
const formatMoney = (value: string | number) => (typeof value === 'number' ? formatCurrency(value) : value)
const formatChoiceLabel = (value: string) => value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())

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
    commissionStatusFilter.value = status
}
</script>

<template>
    <Head :title="t('Affiliate')" />

    <UserDashboardLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliate Program') }}</h1>
                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Share your link, track conversions, and request payouts.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="program.payouts_enabled"
                        type="button"
                        class="inline-flex items-center justify-center rounded-full bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600"
                        @click="openPayoutModal"
                    >
                        <i class="ti ti-send mr-2"></i>
                        {{ t('Request payout') }}
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="(stat, index) in visibleStats"
                    :key="stat.key"
                    class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900"
                >
                    <div class="absolute -right-8 -top-8 h-20 w-20 rounded-full bg-sky-100/50 blur-2xl dark:bg-sky-500/10"></div>
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl shadow-sm" :class="getStatTileClass(String(stat.key))">
                        <i :class="['text-lg', getStatIconClass(String(stat.key))]"></i>
                    </div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t(String(stat.key).replaceAll('_', ' ')) }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ isCurrencyStat(String(stat.key)) ? formatMoney(stat.value) : stat.value }}</p>
                </div>
            </div>
            <!-- Referral Link -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                    <label class="min-w-0 flex-1">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ t('Referral link') }}</span>
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
                        <button type="button" class="rounded-xl border border-primary-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-primary-50 dark:border-primary-800/60 dark:bg-gray-900 dark:text-primary-300 dark:hover:bg-primary-900/20" @click="editAlias">
                            {{ isEditingAlias ? t('Close edit') : t('Edit alias') }}
                        </button>
                        <button type="button" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 sm:w-auto" @click="generateQr" :title="t('QR Code')">
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
                    <a v-for="s in [{label:'Facebook',u:`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralUrl)}`},{label:'X',u:`https://twitter.com/intent/tweet?text=${shareText}&url=${encodeURIComponent(referralUrl)}`},{label:'WhatsApp',u:`https://wa.me/?text=${shareText}%20${encodeURIComponent(referralUrl)}`},{label:'LinkedIn',u:`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(referralUrl)}`},{label:'Telegram',u:`https://t.me/share/url?url=${encodeURIComponent(referralUrl)}&text=${shareText}`}]" :key="s.label" :href="s.u" target="_blank" class="rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:text-primary-300">{{ s.label }}</a>
                </div>
            </section>

            <!-- Performance -->
            <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Performance') }}</h2>
                    <div class="flex w-full rounded-full border border-gray-200 bg-gray-50 p-1 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:w-auto">
                        <button v-for="p in ['1D', '7D', '1M', '1Y']" :key="p" :class="chartPeriod === p ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-full px-3 py-1.5 transition-colors" @click="changePeriod(p)">{{ p }}</button>
                    </div>
                </div>
                <div class="relative mt-4 h-72">
                    <canvas ref="chartCanvas" />
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
                                <thead class="bg-gray-50/80 text-xs uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ t('User') }}</th>
                                        <th class="px-4 py-3">{{ t('Joined') }}</th>
                                        <th class="px-4 py-3">{{ t('Status') }}</th>
                                        <th class="px-4 py-3">{{ t('Commission') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ref in referrals" :key="`${ref.email}-${ref.joined_at}`" class="border-t border-gray-100/80 transition hover:bg-sky-50/60 dark:border-gray-800 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 break-all text-gray-700 dark:text-gray-200">{{ ref.email }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ formatDateTime(ref.joined_at) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-primary-100 px-2.5 py-1 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ t(ref.status) }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(ref.commission) }}</td>
                                    </tr>
                                    <tr v-if="referrals.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ t('No referrals yet. Share your link to get started.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Commissions Table with Filter -->
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                        <div class="flex flex-col gap-3 border-b border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Commissions') }}</h2>
                            <div class="flex flex-wrap gap-1">
                                <button v-for="s in ['', 'pending', 'approved', 'paid', 'rejected', 'cancelled']" :key="s" type="button" :class="commissionStatusFilter === s ? 'bg-primary-100 text-primary-700' : 'text-gray-500 hover:bg-gray-100'" class="rounded-lg px-2.5 py-1 text-xs font-medium" @click="filterCommissions(s)">{{ s ? formatChoiceLabel(s) : t('All') }}</button>
                            </div>
                        </div>
                        <div class="min-w-0 overflow-x-auto">
                            <table class="w-full table-fixed text-left text-sm">
                                <thead class="bg-gray-50/80 text-xs uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ t('Date') }}</th>
                                        <th class="px-4 py-3">{{ t('Order') }}</th>
                                        <th class="px-4 py-3">{{ t('Amount') }}</th>
                                        <th class="px-4 py-3">{{ t('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in visibleCommissions" :key="c.id" class="border-t border-gray-100/80 transition hover:bg-sky-50/60 dark:border-gray-800 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ formatDateTime(c.created_at) }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ c.payment ? c.payment.amount : '—' }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(c.amount) }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="c.status === 'approved' || c.status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' : c.status === 'pending' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : c.status === 'cancelled' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'" class="rounded-full px-2.5 py-1 text-xs font-bold">
                                                {{ t(c.status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="visibleCommissions.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ t('No commissions yet.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
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
                            <thead class="bg-gray-50/80 text-xs uppercase text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">{{ t('Date') }}</th>
                                    <th class="px-4 py-3">{{ t('Amount') }}</th>
                                    <th class="px-4 py-3">{{ t('Method') }}</th>
                                    <th class="px-4 py-3">{{ t('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in payouts.data" :key="p.id" class="border-t border-gray-100/80 transition hover:bg-sky-50/60 dark:border-gray-800 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ formatDateTime(p.created_at) }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ formatMoney(p.amount) }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ t(String(p.method).replaceAll('_', ' ')) }}</td>
                                    <td class="px-4 py-3">
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

            <div
                v-if="isPayoutModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-6 backdrop-blur-sm"
                @click.self="closePayoutModal"
            >
                <div class="w-full max-w-xl rounded-3xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Request payout') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Available balance') }}: <span class="font-semibold text-gray-900 dark:text-white">{{ formatMoney(availableBalance) }}</span></p>
                        </div>
                        <button type="button" class="rounded-full w-8 h-8 text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white" :aria-label="t('Close')" @click="closePayoutModal">
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Amount') }}</span>
                            <input v-model="form.amount" type="number" :min="program.min_payout" :max="program.max_payout > 0 ? program.max_payout : undefined" step="0.01" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-900 transition focus:border-primary-400 focus:bg-white focus:outline-none dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:bg-gray-900" />
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Minimum :min', { min: formatMoney(program.min_payout) }) }}<template v-if="program.max_payout > 0"> · {{ t('Maximum :max', { max: formatMoney(program.max_payout) }) }}</template>
                            </span>
                            <p v-if="form.errors.amount" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.amount }}</p>
                        </label>
                        <div>
                            <AppSelect v-model="selectedMethod" :options="program.payout_methods.map((m: string) => ({ value: m, label: formatChoiceLabel(m) }))" :label="t('Payout method')" />
                            <p v-if="form.errors.method" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.method }}</p>
                        </div>
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
                        <p v-if="selectedMethod === 'credits'" class="rounded-xl bg-sky-50 px-3 py-2.5 text-xs text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                            {{ t('The payout amount will be added to your account credit balance.') }}
                        </p>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="rounded-full border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" @click="closePayoutModal">
                                {{ t('Cancel') }}
                            </button>
                            <button type="submit" :disabled="form.processing || availableBalance < program.min_payout" class="inline-flex items-center justify-center rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-600 disabled:opacity-60">
                                {{ form.processing ? t('Submitting...') : t('Request payout') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Terms -->
            <section v-if="program.terms_page" class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900 dark:text-gray-400">
                <span>{{ t('Affiliate terms apply.') }}</span>
                <a :href="program.terms_page.url" class="ms-1 font-semibold text-primary-600 hover:text-primary-500">{{ program.terms_page.title }}</a>
            </section>
        </div>
    </UserDashboardLayout>
</template>
