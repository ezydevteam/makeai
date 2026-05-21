<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

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

const props = defineProps<{
    program: Program
    stats: Record<string, number>
    referral: { code: string; custom_slug: string | null; link: string; alias_link: string | null }
    chart: ChartPoint[]
    referrals: Array<{ email: string; joined_at: string | null; status: string; commission: number }>
    commissions: { data: Array<{ id: number; amount: string | number; status: string; created_at: string; payment?: { amount: string | number; currency: string } | null }> }
    payouts: { data: Array<{ id: number; amount: string | number; method: string; status: string; created_at: string }> }
}>()

const { t } = useTranslate()
const { success } = useToastr()
const selectedMethod = ref(props.program.payout_methods[0] ?? 'paypal')
const form = useForm({
    amount: String(props.program.min_payout),
    method: selectedMethod.value,
    details: {
        paypal_email: '',
        bank_account: '',
    },
})
const aliasForm = useForm({
    custom_slug: props.referral.custom_slug ?? '',
})

const shareText = computed(() => encodeURIComponent(t('Try this AI platform with my referral link.')))
const referralUrl = computed(() => props.referral.alias_link || props.referral.link)
const maxChart = computed(() => Math.max(1, ...props.chart.flatMap((point) => [point.clicks, point.registrations, point.conversions])))
const polyline = (key: keyof ChartPoint) => props.chart.map((point, index) => {
    const x = props.chart.length <= 1 ? 0 : (index / (props.chart.length - 1)) * 100
    const y = 48 - ((Number(point[key]) / maxChart.value) * 44)

    return `${x},${y}`
}).join(' ')

const copy = async (value: string) => {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value)
    } else {
        const textarea = document.createElement('textarea')
        textarea.value = value
        textarea.setAttribute('readonly', 'true')
        textarea.style.position = 'fixed'
        textarea.style.insetInlineStart = '-9999px'
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
    }

    success(t('Copied to clipboard.'))
}

const submit = () => {
    form.method = selectedMethod.value
    form.post(route('affiliate.payouts.store'), { preserveScroll: true })
}

const saveAlias = () => {
    aliasForm.transform((data) => ({
        custom_slug: data.custom_slug.trim().toLowerCase() || null,
    })).post(route('affiliate.alias.update'), { preserveScroll: true })
}

const normalizeAlias = () => {
    aliasForm.custom_slug = aliasForm.custom_slug
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '')
}
</script>

<template>
    <Head :title="t('Affiliate')" />

    <UserDashboardLayout>
        <div>
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('Affiliate Program') }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ t('Share your link, track conversions, and request payouts.') }}</p>
                    </div>
                    <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-700">
                        {{ program.commission_type === 'percentage' ? `${program.commission_value}%` : program.commission_value }} {{ t('commission') }}
                    </span>
                </div>

                <div class="mb-6 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <div v-for="(value, key) in stats" :key="key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ t(String(key).replaceAll('_', ' ')) }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ value }}</p>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="space-y-6">
                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex flex-wrap items-end justify-between gap-4">
                                <label class="min-w-0 flex-1">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Referral link') }}</span>
                                    <input :value="referralUrl" readonly class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                                </label>
                                <button type="button" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500" @click="copy(referralUrl)">
                                    {{ t('Copy') }}
                                </button>
                            </div>
                            <form v-if="program.allow_custom_alias" class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="saveAlias">
                                <label class="min-w-0">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom referral alias') }}</span>
                                    <input v-model="aliasForm.custom_slug" type="text" maxlength="60" inputmode="url" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm lowercase dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="my-brand" @input="normalizeAlias" />
                                    <span class="mt-1 block text-xs text-gray-500">{{ t('Use lowercase letters, numbers, and hyphens only.') }}</span>
                                </label>
                                <button type="submit" :disabled="aliasForm.processing" class="self-start rounded-lg border border-primary-200 px-5 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">
                                    {{ aliasForm.processing ? t('Saving...') : t('Save alias') }}
                                </button>
                            </form>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralUrl)}`" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ t('Facebook') }}</a>
                                <a :href="`https://twitter.com/intent/tweet?text=${shareText}&url=${encodeURIComponent(referralUrl)}`" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ t('X') }}</a>
                                <a :href="`https://wa.me/?text=${shareText}%20${encodeURIComponent(referralUrl)}`" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ t('WhatsApp') }}</a>
                                <a :href="`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(referralUrl)}`" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ t('LinkedIn') }}</a>
                                <a :href="`https://t.me/share/url?url=${encodeURIComponent(referralUrl)}&text=${shareText}`" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-primary-300">{{ t('Telegram') }}</a>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Performance') }}</h2>
                            <svg viewBox="0 0 100 52" class="mt-4 h-56 w-full overflow-visible">
                                <polyline :points="polyline('clicks')" fill="none" stroke="#3b82f6" stroke-width="1.8" />
                                <polyline :points="polyline('registrations')" fill="none" stroke="#10b981" stroke-width="1.8" />
                                <polyline :points="polyline('conversions')" fill="none" stroke="#8b5cf6" stroke-width="1.8" />
                            </svg>
                        </section>

                        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="border-b border-gray-100 p-5">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Referrals') }}</h2>
                            </div>
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                    <tr><th class="px-4 py-3">{{ t('User') }}</th><th class="px-4 py-3">{{ t('Joined') }}</th><th class="px-4 py-3">{{ t('Status') }}</th><th class="px-4 py-3">{{ t('Commission') }}</th></tr>
                                </thead>
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
                    </div>

                    <aside class="space-y-6">
                        <form v-if="program.payouts_enabled" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" @submit.prevent="submit">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Request payout') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('Available balance') }}: {{ stats.available_balance }}</p>
                            <div class="mt-4 space-y-3">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Amount') }}</span>
                                    <input v-model="form.amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Payout method') }}</span>
                                    <select v-model="selectedMethod" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                        <option v-for="method in program.payout_methods" :key="method" :value="method">{{ t(method.replace('_', ' ')) }}</option>
                                    </select>
                                </label>
                                <label v-if="selectedMethod === 'paypal'" class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('PayPal email') }}</span>
                                    <input v-model="form.details.paypal_email" type="email" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                                <label v-if="selectedMethod === 'bank_transfer'" class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Bank details') }}</span>
                                    <textarea v-model="form.details.bank_account" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                                <button type="submit" :disabled="form.processing || stats.available_balance < program.min_payout" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                                    {{ form.processing ? t('Submitting...') : t('Request payout') }}
                                </button>
                            </div>
                        </form>
                        <section v-else class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Payout requests disabled') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('Affiliate earnings are tracked, but payout requests are currently disabled by admin.') }}</p>
                        </section>
                    </aside>
                </div>

                <section v-if="program.terms_page" class="mt-6 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    <span>{{ t('Affiliate terms apply.') }}</span>
                    <a :href="program.terms_page.url" class="ms-1 font-semibold text-primary-600 hover:text-primary-500">
                        {{ program.terms_page.title }}
                    </a>
                </section>
        </div>
    </UserDashboardLayout>
</template>
