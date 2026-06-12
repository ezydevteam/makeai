<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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
    referral_credits_enabled: boolean
    referral_credits_amount: string | number
    commission_hold_days: number
    allow_custom_alias: boolean
    terms_page_slug: string | null
    marketing_banners: Array<{ url: string; label?: string }> | null
    promotional_emails: Array<{ subject: string; body: string }> | null
    social_posts: Array<{ text: string; platform?: string }> | null
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
const showSettingsModal = ref(false)
const activeTab = ref<'commissions' | 'payouts' | 'affiliates'>('commissions')

const mkBanners = ref<Array<{ url: string; label: string }>>(
    (props.program.marketing_banners || []).map((b) => ({ url: b.url || '', label: b.label || '' })),
)
const mkEmails = ref<Array<{ subject: string; body: string }>>(
    (props.program.promotional_emails || []).map((e) => ({ subject: e.subject || '', body: e.body || '' })),
)
const mkPosts = ref<Array<{ text: string; platform: string }>>(
    (props.program.social_posts || []).map((p) => ({ text: p.text || '', platform: p.platform || '' })),
)

const addBanner = () => mkBanners.value.push({ url: '', label: '' })
const removeBanner = (i: number) => mkBanners.value.splice(i, 1)
const addEmail = () => mkEmails.value.push({ subject: '', body: '' })
const removeEmail = (i: number) => mkEmails.value.splice(i, 1)
const addPost = () => mkPosts.value.push({ text: '', platform: '' })
const removePost = (i: number) => mkPosts.value.splice(i, 1)

const commissionTypeOptions = [
    { value: 'percentage', label: t('Percentage') },
    { value: 'fixed', label: t('Fixed') },
]

const commissionOnOptions = [
    { value: 'first_purchase', label: t('First purchase') },
    { value: 'all_purchases', label: t('All purchases') },
    { value: 'subscription', label: t('Subscription') },
]

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
    referral_credits_enabled: props.program.referral_credits_enabled,
    referral_credits_amount: String(props.program.referral_credits_amount),
    commission_hold_days: props.program.commission_hold_days,
    allow_custom_alias: props.program.allow_custom_alias,
    terms_page_slug: props.program.terms_page_slug ?? '',
    marketing_banners: mkBanners.value,
    promotional_emails: mkEmails.value,
    social_posts: mkPosts.value,
} as any)

const toggleMethod = (method: string) => {
    form.payout_methods = form.payout_methods.includes(method)
        ? form.payout_methods.filter((item: string) => item !== method)
        : [...form.payout_methods, method]
}

const save = () => {
    form.marketing_banners = mkBanners.value
    form.promotional_emails = mkEmails.value
    form.social_posts = mkPosts.value
    form.post(route('admin.affiliate.settings'), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsModal.value = false
        },
    })
}

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
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Affiliate Management') }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Manage commission rules, approvals, and payout requests.') }}</p>
                </div>
                <button type="button" class="inline-flex items-center justify-center rounded-lg bg-white text-gray-800 dark:text-gray-100 border border-gray-200 transition-all duration-300 hover:bg-surface-300 dark:bg-surface-800 dark:border-gray-700 dark:hover:bg-surface-700 dark:hover:border-gray-600 px-4 py-2 text-sm" @click="showSettingsModal = true">
                    <i class="ti ti-settings mr-1"></i>
                    {{ t('Settings') }}
                </button>
            </div>

            <div class="mb-6 grid gap-4 md:grid-cols-4">
                <div v-for="(value, key) in stats" :key="key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ t(String(key).replaceAll('_', ' ')) }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ value }}</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 border-b border-gray-100 p-5 md:flex-row md:items-center md:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{
                            activeTab === 'commissions'
                                ? t('Commission approval queue')
                                : activeTab === 'payouts'
                                    ? t('Payout requests')
                                    : t('Affiliates')
                        }}
                    </h2>

                    <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'commissions' ? 'bg-white text-primary-600 shadow-sm dark:bg-gray-900' : 'text-gray-600 dark:text-gray-300'"
                            @click="activeTab = 'commissions'"
                        >
                            {{ t('Commissions') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'payouts' ? 'bg-white text-primary-600 shadow-sm dark:bg-gray-900' : 'text-gray-600 dark:text-gray-300'"
                            @click="activeTab = 'payouts'"
                        >
                            {{ t('Payouts') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'affiliates' ? 'bg-white text-primary-600 shadow-sm dark:bg-gray-900' : 'text-gray-600 dark:text-gray-300'"
                            @click="activeTab = 'affiliates'"
                        >
                            {{ t('Affiliates') }}
                        </button>
                    </div>
                </div>

                <table v-if="activeTab === 'commissions'" class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Referrer') }}</th>
                            <th class="px-4 py-3">{{ t('Referred') }}</th>
                            <th class="px-4 py-3">{{ t('Amount') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="commission in commissions.data" :key="commission.id" class="border-t border-gray-100">
                            <td class="px-4 py-3">{{ commission.referrer?.name }}</td>
                            <td class="px-4 py-3">{{ commission.referred?.name }}</td>
                            <td class="px-4 py-3">{{ commission.amount }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-700">{{ t(commission.status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="commission.status === 'pending'" class="mr-2 rounded-lg btn-primary" @click="approve(commission.id)">{{ t('Approve') }}</button>
                                <button v-if="commission.status === 'pending'" class="rounded-lg bg-red-500 px-3 py-1 text-xs font-bold text-white" @click="reject(commission.id)">{{ t('Reject') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table v-else-if="activeTab === 'payouts'" class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('User') }}</th>
                            <th class="px-4 py-3">{{ t('Amount') }}</th>
                            <th class="px-4 py-3">{{ t('Method') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3">{{ t('Process') }}</th>
                        </tr>
                    </thead>
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
                                    <button class="rounded-lg btn-primary" @click="processPayout(payout.id)">{{ t('Save') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table v-else class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('User') }}</th>
                            <th class="px-4 py-3">{{ t('Code') }}</th>
                            <th class="px-4 py-3">{{ t('Referrals') }}</th>
                            <th class="px-4 py-3">{{ t('Earnings') }}</th>
                        </tr>
                    </thead>
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

        <div v-if="showSettingsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showSettingsModal = false">
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('Affiliate settings') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ t('Configure commission rules, payout handling, and campaign assets.') }}</p>
                    </div>
                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="showSettingsModal = false">
                        <i class="ti ti-x"></i>
                    </button>
                </div>

                <form class="flex-1 overflow-y-auto px-6 py-6" @submit.prevent="save">
                    <div class="space-y-6">
                        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Settings') }}</h3>
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Active') }}
                                    <input v-model="form.is_active" type="checkbox" />
                                </label>
                            </div>

                            <div class="space-y-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <AppSelect v-model="form.commission_type" :label="t('Commission type')" :options="commissionTypeOptions" />
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Value') }}</span>
                                        <input v-model="form.commission_value" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                                    </label>
                                </div>

                                <AppSelect v-model="form.commission_on" :label="t('Commission on')" :options="commissionOnOptions" />

                                <div class="grid gap-4 md:grid-cols-3">
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cookie days') }}</span>
                                        <input v-model="form.cookie_days" type="number" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Minimum payout') }}</span>
                                        <input v-model="form.min_payout" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Commission hold days') }}</span>
                                        <input v-model="form.commission_hold_days" type="number" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                                    </label>
                                </div>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Payout methods') }}</span>
                                    <span class="flex flex-wrap gap-2">
                                        <button v-for="method in payoutMethodOptions" :key="method" type="button" :class="form.payout_methods.includes(method) ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600'" class="rounded-full px-3 py-1 text-xs font-bold" @click="toggleMethod(method)">
                                            {{ t(method.replace('_', ' ')) }}
                                        </button>
                                    </span>
                                </label>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium dark:border-gray-700">
                                        {{ t('Enable payout requests') }}
                                        <input v-model="form.payouts_enabled" type="checkbox" />
                                    </label>
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium dark:border-gray-700">
                                        {{ t('Auto approve commissions') }}
                                        <input v-model="form.auto_approve_commissions" type="checkbox" />
                                    </label>
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium dark:border-gray-700">
                                        {{ t('Award credits on first purchase') }}
                                        <input v-model="form.referral_credits_enabled" type="checkbox" />
                                    </label>
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium dark:border-gray-700">
                                        {{ t('Allow custom alias') }}
                                        <input v-model="form.allow_custom_alias" type="checkbox" />
                                    </label>
                                </div>

                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('First purchase credit amount') }}</span>
                                    <input v-model="form.referral_credits_amount" type="number" min="0" step="0.0001" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" />
                                    <span class="mt-1 block text-xs text-gray-500">{{ t('Credits are added to the referrer only once, when the referred user completes the first purchase.') }}</span>
                                </label>
                            </div>
                        </section>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Marketing banners') }}</h3>
                                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-bold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="addBanner">+ {{ t('Add banner') }}</button>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="(b, i) in mkBanners" :key="i" class="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-500">#{{ i + 1 }}</span>
                                            <button type="button" class="text-xs font-bold text-red-500 hover:text-red-700" @click="removeBanner(i)">{{ t('Remove') }}</button>
                                        </div>
                                        <input v-model="b.url" type="url" class="mb-2 w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-950" placeholder="https://example.com/banner.png" />
                                        <input v-model="b.label" type="text" class="w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-950" :placeholder="t('Label (optional)')" />
                                    </div>
                                    <p v-if="mkBanners.length === 0" class="text-sm text-gray-400">{{ t('No banners configured.') }}</p>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Promotional emails') }}</h3>
                                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-bold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="addEmail">+ {{ t('Add email') }}</button>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="(e, i) in mkEmails" :key="i" class="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-500">#{{ i + 1 }}</span>
                                            <button type="button" class="text-xs font-bold text-red-500 hover:text-red-700" @click="removeEmail(i)">{{ t('Remove') }}</button>
                                        </div>
                                        <input v-model="e.subject" type="text" class="mb-2 w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-950" :placeholder="t('Subject')" />
                                        <textarea v-model="e.body" rows="4" class="w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-950" :placeholder="t('Email body')" />
                                    </div>
                                    <p v-if="mkEmails.length === 0" class="text-sm text-gray-400">{{ t('No email templates configured.') }}</p>
                                </div>
                            </section>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Social media posts') }}</h3>
                                    <button type="button" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-bold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="addPost">+ {{ t('Add post') }}</button>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="(p, i) in mkPosts" :key="i" class="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-500">#{{ i + 1 }}</span>
                                            <button type="button" class="text-xs font-bold text-red-500 hover:text-red-700" @click="removePost(i)">{{ t('Remove') }}</button>
                                        </div>
                                        <input v-model="p.platform" type="text" class="mb-2 w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-950" :placeholder="t('Platform (e.g. Twitter, LinkedIn)')" />
                                        <textarea v-model="p.text" rows="3" class="w-full rounded border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-950" :placeholder="t('Post text')" />
                                    </div>
                                    <p v-if="mkPosts.length === 0" class="text-sm text-gray-400">{{ t('No social posts configured.') }}</p>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Terms') }}</h3>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Terms page slug') }}</span>
                                    <input v-model="form.terms_page_slug" list="affiliate-terms-pages" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" placeholder="affiliate-terms" />
                                    <datalist id="affiliate-terms-pages">
                                        <option v-for="page in termsPageOptions" :key="page.slug" :value="page.slug">{{ page.title }}</option>
                                    </datalist>
                                    <span class="mt-1 block text-xs text-gray-500">{{ t('Create a CMS page first, then enter its slug here.') }}</span>
                                </label>
                            </section>
                        </div>
                    </div>
                </form>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                    <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-900" @click="showSettingsModal = false">
                        {{ t('Cancel') }}
                    </button>
                    <button type="button" :disabled="form.processing" class="rounded-lg btn-primary px-4 py-2 text-sm" @click="save">
                        {{ form.processing ? t('Saving...') : t('Save settings') }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
