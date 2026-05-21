<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface Program {
    is_active: boolean
    commission_type: 'percentage' | 'fixed'
    commission_value: string | number
    commission_on: 'first_purchase' | 'all_purchases' | 'subscription'
    cookie_days: number
    min_payout: string | number
    payouts_enabled: boolean
    payout_methods: string[] | null
    auto_approve_commissions: boolean
    commission_hold_days: number
    allow_custom_alias: boolean
    terms_page_slug: string | null
}

const props = defineProps<{
    program: Program
    termsPageOptions: Array<{ title: string; slug: string }>
    stats: Record<string, number>
    affiliates: { data: Array<any> }
    commissions: { data: Array<any> }
    payouts: { data: Array<any> }
    topEarners: Array<any>
}>()

const { t } = useTranslate()
const payoutMethodOptions = ['paypal', 'bank_transfer', 'credits']
const payoutNote = ref<Record<number, string>>({})
const payoutStatus = ref<Record<number, string>>({})

const form = useForm({
    is_active: props.program.is_active,
    commission_type: props.program.commission_type,
    commission_value: String(props.program.commission_value),
    commission_on: props.program.commission_on,
    cookie_days: props.program.cookie_days,
    min_payout: String(props.program.min_payout),
    payouts_enabled: props.program.payouts_enabled,
    payout_methods: props.program.payout_methods ?? ['paypal', 'bank_transfer', 'credits'],
    auto_approve_commissions: props.program.auto_approve_commissions,
    commission_hold_days: props.program.commission_hold_days,
    allow_custom_alias: props.program.allow_custom_alias,
    terms_page_slug: props.program.terms_page_slug ?? '',
})

const toggleMethod = (method: string) => {
    form.payout_methods = form.payout_methods.includes(method)
        ? form.payout_methods.filter((item) => item !== method)
        : [...form.payout_methods, method]
}

const save = () => form.post(route('admin.affiliate.settings'), { preserveScroll: true })
const approve = (id: number) => router.post(route('admin.affiliate.commissions.approve', id), {}, { preserveScroll: true })
const reject = (id: number) => router.post(route('admin.affiliate.commissions.reject', id), {}, { preserveScroll: true })
const processPayout = (id: number) => router.post(route('admin.affiliate.payouts.process', id), {
    status: payoutStatus.value[id] || 'processing',
    admin_note: payoutNote.value[id] || '',
}, { preserveScroll: true })
</script>

<template>
    <Head :title="t('Affiliate')" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Affiliate Management') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage commission rules, approvals, and payout requests.') }}</p>
            </div>

            <div class="mb-6 grid gap-4 md:grid-cols-4">
                <div v-for="(value, key) in stats" :key="key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ t(String(key).replaceAll('_', ' ')) }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ value }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[420px_minmax(0,1fr)]">
                <form class="space-y-6" @submit.prevent="save">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Settings') }}</h2>
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                {{ t('Active') }}
                                <input v-model="form.is_active" type="checkbox" />
                            </label>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Commission type') }}</span>
                                    <select v-model="form.commission_type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                        <option value="percentage">{{ t('Percentage') }}</option>
                                        <option value="fixed">{{ t('Fixed') }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Value') }}</span>
                                    <input v-model="form.commission_value" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                            </div>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Commission on') }}</span>
                                <select v-model="form.commission_on" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="first_purchase">{{ t('First purchase') }}</option>
                                    <option value="all_purchases">{{ t('All purchases') }}</option>
                                    <option value="subscription">{{ t('Subscription') }}</option>
                                </select>
                            </label>
                            <div class="grid gap-3 md:grid-cols-3">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Cookie days') }}</span>
                                    <input v-model="form.cookie_days" type="number" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Minimum payout') }}</span>
                                    <input v-model="form.min_payout" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Commission hold days') }}</span>
                                    <input v-model="form.commission_hold_days" type="number" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </label>
                            </div>
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700">{{ t('Payout methods') }}</span>
                                <span class="flex flex-wrap gap-2">
                                    <button v-for="method in payoutMethodOptions" :key="method" type="button" :class="form.payout_methods.includes(method) ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600'" class="rounded-full px-3 py-1 text-xs font-bold" @click="toggleMethod(method)">
                                        {{ t(method.replace('_', ' ')) }}
                                    </button>
                                </span>
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                                {{ t('Enable payout requests') }}
                                <input v-model="form.payouts_enabled" type="checkbox" />
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                                {{ t('Auto approve commissions') }}
                                <input v-model="form.auto_approve_commissions" type="checkbox" />
                            </label>
                            <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                                {{ t('Allow custom alias') }}
                                <input v-model="form.allow_custom_alias" type="checkbox" />
                            </label>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Terms') }}</h2>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">{{ t('Terms page slug') }}</span>
                            <input v-model="form.terms_page_slug" list="affiliate-terms-pages" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="affiliate-terms" />
                            <datalist id="affiliate-terms-pages">
                                <option v-for="page in termsPageOptions" :key="page.slug" :value="page.slug">{{ page.title }}</option>
                            </datalist>
                            <span class="mt-1 block text-xs text-gray-500">{{ t('Create a CMS page first, then enter its slug here.') }}</span>
                        </label>
                        <button type="submit" :disabled="form.processing" class="mt-4 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white">{{ form.processing ? t('Saving...') : t('Save settings') }}</button>
                    </section>
                </form>

                <div class="space-y-6">
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 p-5"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Commission approval queue') }}</h2></div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">{{ t('Referrer') }}</th><th class="px-4 py-3">{{ t('Referred') }}</th><th class="px-4 py-3">{{ t('Amount') }}</th><th class="px-4 py-3">{{ t('Status') }}</th><th class="px-4 py-3 text-right">{{ t('Actions') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="commission in commissions.data" :key="commission.id" class="border-t border-gray-100">
                                    <td class="px-4 py-3">{{ commission.referrer?.name }}</td>
                                    <td class="px-4 py-3">{{ commission.referred?.name }}</td>
                                    <td class="px-4 py-3">{{ commission.amount }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-700">{{ t(commission.status) }}</span></td>
                                    <td class="px-4 py-3 text-right">
                                        <button v-if="commission.status === 'pending'" class="mr-2 rounded-lg bg-primary-600 px-3 py-1 text-xs font-bold text-white" @click="approve(commission.id)">{{ t('Approve') }}</button>
                                        <button v-if="commission.status === 'pending'" class="rounded-lg bg-red-500 px-3 py-1 text-xs font-bold text-white" @click="reject(commission.id)">{{ t('Reject') }}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 p-5"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Payout requests') }}</h2></div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">{{ t('User') }}</th><th class="px-4 py-3">{{ t('Amount') }}</th><th class="px-4 py-3">{{ t('Method') }}</th><th class="px-4 py-3">{{ t('Status') }}</th><th class="px-4 py-3">{{ t('Process') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="payout in payouts.data" :key="payout.id" class="border-t border-gray-100">
                                    <td class="px-4 py-3">{{ payout.user?.name }}</td>
                                    <td class="px-4 py-3">{{ payout.amount }}</td>
                                    <td class="px-4 py-3">{{ t(payout.method) }}</td>
                                    <td class="px-4 py-3">{{ t(payout.status) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            <select v-model="payoutStatus[payout.id]" class="rounded-lg border border-gray-200 px-2 py-1 text-xs">
                                                <option value="processing">{{ t('Processing') }}</option>
                                                <option value="paid">{{ t('Paid') }}</option>
                                                <option value="rejected">{{ t('Rejected') }}</option>
                                            </select>
                                            <input v-model="payoutNote[payout.id]" class="w-28 rounded-lg border border-gray-200 px-2 py-1 text-xs" :placeholder="t('Note')" />
                                            <button class="rounded-lg bg-primary-600 px-3 py-1 text-xs font-bold text-white" @click="processPayout(payout.id)">{{ t('Save') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 p-5"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Affiliates') }}</h2></div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">{{ t('User') }}</th><th class="px-4 py-3">{{ t('Code') }}</th><th class="px-4 py-3">{{ t('Referrals') }}</th><th class="px-4 py-3">{{ t('Earnings') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="affiliate in affiliates.data" :key="affiliate.ulid" class="border-t border-gray-100">
                                    <td class="px-4 py-3">{{ affiliate.name }}<span class="block text-xs text-gray-500">{{ affiliate.email }}</span></td>
                                    <td class="px-4 py-3 font-semibold">{{ affiliate.referral_code }}</td>
                                    <td class="px-4 py-3">{{ affiliate.affiliate_referrals_count }}</td>
                                    <td class="px-4 py-3">{{ affiliate.referral_earnings }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
