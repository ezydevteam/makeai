<script setup lang="ts">
import { computed, ref, watch, onMounted, nextTick, onUnmounted } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import AppSelect from '@/Components/AppSelect.vue'
import QRCode from 'qrcode'

interface Program {
    commission_type: 'percentage' | 'fixed'
    commission_value: number
    min_payout: number
    payouts_enabled: boolean
    payout_methods: string[]
    commission_hold_days: number
    allow_custom_alias: boolean
    terms_page: { title: string; url: string } | null
}

interface ChartPoint { date: string; clicks: number; registrations: number; conversions: number }

interface MarketingBanner { url: string; label?: string }
interface MarketingEmail { subject: string; body: string }
interface MarketingPost { text: string; platform?: string }

const props = defineProps<{
    program: Program
    stats: Record<string, number>
    referral: { code: string; custom_slug: string | null; link: string; alias_link: string | null }
    chart: ChartPoint[]
    marketing: { banners: MarketingBanner[]; emails: MarketingEmail[]; posts: MarketingPost[] }
    referrals: Array<{ email: string; joined_at: string | null; status: string; commission: number }>
    commissions: { data: Array<{ id: number; amount: string | number; status: string; created_at: string; payment?: { amount: string | number; currency: string } | null }> }
    payouts: { data: Array<{ id: number; amount: string | number; method: string; status: string; created_at: string }> }
}>()

const { t } = useTranslate()
const { success } = useToastr()
const selectedMethod = ref(props.program.payout_methods[0] ?? 'paypal')
const commissionStatusFilter = ref('')

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
        qrError.value = 'Unable to generate QR code.'
    }
}

const downloadQr = () => {
    if (!qrDataUrl.value) return
    const a = document.createElement('a')
    a.download = 'referral-qr.png'
    a.href = qrDataUrl.value
    a.click()
}

// ── Chart ──
const chartPeriod = ref<'daily' | 'weekly' | 'monthly'>('daily')
const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: InstanceType<typeof import('chart.js').Chart> | null = null

const chartLabels = computed(() => props.chart.map((p) => {
    const d = new Date(p.date)
    if (chartPeriod.value === 'monthly') return d.toLocaleDateString('en', { month: 'short' })
    if (chartPeriod.value === 'weekly') return d.toLocaleDateString('en', { month: 'short', day: 'numeric' })
    return d.toLocaleDateString('en', { day: 'numeric', month: 'short' })
}))

const buildChart = async () => {
    if (!chartCanvas.value) return
    const { Chart, LineController, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler, Legend } = await import('chart.js')
    Chart.register(LineController, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler, Legend)

    if (chartInstance) chartInstance.destroy()

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels: chartLabels.value,
            datasets: [
                { label: t('Clicks'), data: props.chart.map((p) => p.clicks), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3, pointRadius: 0, pointHoverRadius: 0 },
                { label: t('Registrations'), data: props.chart.map((p) => p.registrations), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3, pointRadius: 0, pointHoverRadius: 0 },
                { label: t('Conversions'), data: props.chart.map((p) => p.conversions), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true, tension: 0.3, pointRadius: 0, pointHoverRadius: 0 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            elements: { point: { radius: 0, hoverRadius: 0 } },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 11 } } } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } } }, x: { offset: false, ticks: { font: { size: 10 }, maxRotation: 0 } } },
        },
    })
}

watch(chartPeriod, () => nextTick(buildChart))
onMounted(() => nextTick(buildChart))
onUnmounted(() => chartInstance?.destroy())

// ── Shared ──
const shareText = computed(() => encodeURIComponent(t('Try this AI platform with my referral link.')))
const referralUrl = computed(() => props.referral.alias_link || props.referral.link)

const copy = async (value: string) => {
    try { await navigator.clipboard.writeText(value) }
    catch {
        const ta = document.createElement('textarea')
        ta.value = value
        ta.setAttribute('readonly', 'true')
        ta.style.position = 'fixed'; ta.style.insetInlineStart = '-9999px'
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta)
    }
    success(t('Copied to clipboard.'))
}

const submit = () => { form.method = selectedMethod.value; form.post(route('user.dashboard.affiliate.payouts.store'), { preserveScroll: true }) }
const saveAlias = () => aliasForm.transform((d) => ({ custom_slug: d.custom_slug.trim().toLowerCase() || null })).post(route('user.dashboard.affiliate.alias.update'), { preserveScroll: true })
const normalizeAlias = () => { aliasForm.custom_slug = aliasForm.custom_slug.toLowerCase().replace(/[^a-z0-9-]/g, '') }
const filterCommissions = (status: string) => { commissionStatusFilter.value = status; router.get(route('user.dashboard.affiliate'), { status: status || undefined }, { preserveState: true, replace: true }) }
</script>

<template>
    <Head :title="t('Affiliate')" />

    <UserDashboardLayout>
        <div>
            <!-- Header -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('Affiliate Program') }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Share your link, track conversions, and request payouts.') }}</p>
                </div>
                <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-700">
                    {{ program.commission_type === 'percentage' ? `${program.commission_value}%` : program.commission_value }} {{ t('commission') }}
                </span>
            </div>

            <!-- Stats -->
            <div class="mb-6 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="(value, key) in stats" :key="key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ t(String(key).replaceAll('_', ' ')) }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ value }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <!-- Referral Link -->
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <label class="min-w-0 flex-1">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Referral link') }}</span>
                                <input :value="referralUrl" readonly class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                            </label>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg btn-primary shadow-sm" @click="copy(referralUrl)">{{ t('Copy') }}</button>
                                <button type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="generateQr" :title="t('QR Code')">
                                    <i class="ti ti-qrcode"></i>
                                </button>
                            </div>
                        </div>
                        <div v-if="showQr" class="mt-4 flex flex-col items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <img v-if="qrDataUrl" :src="qrDataUrl" alt="Referral QR code" class="h-48 w-48 rounded-lg border border-gray-200 dark:border-gray-600" />
                            <p v-if="qrError" class="text-sm text-red-500">{{ qrError }}</p>
                            <button v-if="qrDataUrl" type="button" class="rounded-lg border border-gray-200 px-4 py-1.5 text-xs font-semibold text-gray-700 hover:bg-white dark:border-gray-600 dark:text-gray-300" @click="downloadQr">{{ t('Download PNG') }}</button>
                        </div>
                        <form v-if="program.allow_custom_alias" class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="saveAlias">
                            <label class="min-w-0">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom referral alias') }}</span>
                                <input v-model="aliasForm.custom_slug" type="text" maxlength="60" inputmode="url" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm lowercase dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="my-brand" @input="normalizeAlias" />
                                <span class="mt-1 block text-xs text-gray-500">{{ t('Use lowercase letters, numbers, and hyphens only.') }}</span>
                            </label>
                            <button type="submit" :disabled="aliasForm.processing" class="self-start rounded-lg border border-primary-200 px-5 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">{{ aliasForm.processing ? t('Saving...') : t('Save alias') }}</button>
                        </form>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a v-for="s in [{label:'Facebook',u:`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralUrl)}`},{label:'X',u:`https://twitter.com/intent/tweet?text=${shareText}&url=${encodeURIComponent(referralUrl)}`},{label:'WhatsApp',u:`https://wa.me/?text=${shareText}%20${encodeURIComponent(referralUrl)}`},{label:'LinkedIn',u:`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(referralUrl)}`},{label:'Telegram',u:`https://t.me/share/url?url=${encodeURIComponent(referralUrl)}&text=${shareText}`}]" :key="s.label" :href="s.u" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ s.label }}</a>
                        </div>
                    </section>

                    <!-- Chart -->
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Performance') }}</h2>
                            <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs dark:border-gray-700 dark:bg-gray-800">
                                <button v-for="p in (['daily','weekly','monthly'] as const)" :key="p" :class="chartPeriod === p ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold' : 'text-gray-500'" class="rounded-md px-2.5 py-1 transition-colors" @click="chartPeriod = p">{{ t(p) }}</button>
                            </div>
                        </div>
                        <div class="mt-4 h-64">
                            <canvas ref="chartCanvas" />
                        </div>
                    </section>

                    <!-- Referrals Table -->
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 p-5"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Referrals') }}</h2></div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">{{ t('User') }}</th><th class="px-4 py-3">{{ t('Joined') }}</th><th class="px-4 py-3">{{ t('Status') }}</th><th class="px-4 py-3">{{ t('Commission') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="ref in referrals" :key="`${ref.email}-${ref.joined_at}`" class="border-t border-gray-100">
                                    <td class="px-4 py-3">{{ ref.email }}</td>
                                    <td class="px-4 py-3">{{ ref.joined_at || t('Not registered') }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-primary-100 px-2.5 py-1 text-xs font-bold text-primary-700">{{ t(ref.status) }}</span></td>
                                    <td class="px-4 py-3">{{ ref.commission }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <!-- Commissions Table with Filter -->
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-100 p-5">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Commissions') }}</h2>
                            <div class="flex gap-1">
                                <button v-for="s in ['', 'pending', 'approved', 'paid', 'rejected']" :key="s" :class="commissionStatusFilter === s ? 'bg-primary-100 text-primary-700' : 'text-gray-500 hover:bg-gray-100'" class="rounded-lg px-2.5 py-1 text-xs font-medium" @click="filterCommissions(s)">{{ s ? t(s) : t('All') }}</button>
                            </div>
                        </div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">{{ t('Date') }}</th><th class="px-4 py-3">{{ t('Order') }}</th><th class="px-4 py-3">{{ t('Amount') }}</th><th class="px-4 py-3">{{ t('Status') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="c in commissions.data" :key="c.id" class="border-t border-gray-100">
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ c.created_at }}</td>
                                    <td class="px-4 py-3 text-xs">{{ c.payment ? `${c.payment.amount} ${c.payment.currency}` : '—' }}</td>
                                    <td class="px-4 py-3 font-medium">{{ c.amount }}</td>
                                    <td class="px-4 py-3"><span :class="c.status === 'approved' || c.status === 'paid' ? 'bg-green-100 text-green-700' : c.status === 'pending' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'" class="rounded-full px-2.5 py-1 text-xs font-bold">{{ t(c.status) }}</span></td>
                                </tr>
                                <tr v-if="commissions.data.length === 0"><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">{{ t('No commissions yet.') }}</td></tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <aside class="space-y-6">
                    <!-- Payout Request -->
                    <form v-if="program.payouts_enabled" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" @submit.prevent="submit">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Request payout') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('Available balance') }}: <span class="font-bold text-gray-900 dark:text-white">{{ stats.available_balance }}</span></p>
                        <div class="mt-4 space-y-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Amount') }}</span>
                                <input v-model="form.amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Payout method') }}</span>
                                <AppSelect v-model="selectedMethod" :options="program.payout_methods.map((m: string) => ({ value: m, label: t(m.replace('_', ' ')) }))" :label="t('Payout method')" />
                            </label>
                            <label v-if="selectedMethod === 'paypal'" class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('PayPal email') }}</span>
                                <input v-model="form.details.paypal_email" type="email" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                            <label v-if="selectedMethod === 'bank_transfer'" class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Bank details') }}</span>
                                <textarea v-model="form.details.bank_account" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                            <button type="submit" :disabled="form.processing || stats.available_balance < program.min_payout" class="w-full rounded-lg btn-primary disabled:opacity-50">{{ form.processing ? t('Submitting...') : t('Request payout') }}</button>
                        </div>
                    </form>
                    <section v-else class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Payout requests disabled') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('Affiliate earnings are tracked, but payout requests are currently disabled by admin.') }}</p>
                    </section>

                    <!-- Payout History -->
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 p-5"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Payout history') }}</h2></div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">{{ t('Date') }}</th><th class="px-4 py-3">{{ t('Amount') }}</th><th class="px-4 py-3">{{ t('Method') }}</th><th class="px-4 py-3">{{ t('Status') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="p in payouts.data" :key="p.id" class="border-t border-gray-100">
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ p.created_at }}</td>
                                    <td class="px-4 py-3 font-medium">{{ p.amount }}</td>
                                    <td class="px-4 py-3 text-xs">{{ t(String(p.method).replaceAll('_', ' ')) }}</td>
                                    <td class="px-4 py-3"><span :class="p.status === 'paid' ? 'bg-green-100 text-green-700' : p.status === 'processing' ? 'bg-blue-100 text-blue-700' : p.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'" class="rounded-full px-2.5 py-1 text-xs font-bold">{{ t(p.status) }}</span></td>
                                </tr>
                                <tr v-if="payouts.data.length === 0"><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">{{ t('No payout requests yet.') }}</td></tr>
                            </tbody>
                        </table>
                    </section>
                </aside>
            </div>

            <!-- Marketing Materials -->
            <div v-if="marketing.banners.length || marketing.emails.length || marketing.posts.length" class="mt-6 grid gap-6 lg:grid-cols-3">
                <section v-if="marketing.banners.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Banners') }}</h2>
                    <div class="space-y-3">
                        <div v-for="(b, i) in marketing.banners" :key="i" class="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                            <img v-if="b.url" :src="b.url" :alt="b.label || `Banner ${i + 1}`" class="mb-2 h-auto w-full rounded" />
                            <p v-if="b.label" class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">{{ b.label }}</p>
                            <button type="button" class="text-xs font-semibold text-primary-600 hover:text-primary-500" @click="copy(b.url)">{{ t('Copy URL') }}</button>
                        </div>
                    </div>
                </section>
                <section v-if="marketing.emails.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Email templates') }}</h2>
                    <div class="space-y-3">
                        <details v-for="(e, i) in marketing.emails" :key="i" class="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                            <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">{{ e.subject }}</summary>
                            <p class="mt-2 whitespace-pre-wrap text-xs text-gray-500 dark:text-gray-400">{{ e.body }}</p>
                            <button type="button" class="mt-2 text-xs font-semibold text-primary-600 hover:text-primary-500" @click="copy(e.body)">{{ t('Copy text') }}</button>
                        </details>
                    </div>
                </section>
                <section v-if="marketing.posts.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Social posts') }}</h2>
                    <div class="space-y-3">
                        <div v-for="(p, i) in marketing.posts" :key="i" class="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                            <p v-if="p.platform" class="mb-1 text-xs font-bold uppercase text-gray-400">{{ p.platform }}</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ p.text }}</p>
                            <button type="button" class="mt-2 text-xs font-semibold text-primary-600 hover:text-primary-500" @click="copy(p.text)">{{ t('Copy text') }}</button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Terms -->
            <section v-if="program.terms_page" class="mt-6 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                <span>{{ t('Affiliate terms apply.') }}</span>
                <a :href="program.terms_page.url" class="ms-1 font-semibold text-primary-600 hover:text-primary-500">{{ program.terms_page.title }}</a>
            </section>
        </div>
    </UserDashboardLayout>
</template>
