<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface Program {
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

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedResponse<T> {
    data: T[]
    links: PaginationLink[]
    from?: number
    to?: number
    total?: number
}

interface AffiliateUser {
    id: number
    ulid: string
    name: string
    email: string
    referral_code: string
    referral_earnings: string | number
    affiliate_referrals_count?: number
    referral_count?: number
}

interface CommissionParty {
    ulid?: string
    name?: string
    email?: string
}

interface CommissionItem {
    id: number
    amount: string | number
    status: string
    referrer: CommissionParty | null
    referred: CommissionParty | null
}

interface PayoutUser {
    ulid?: string
    name?: string
    email?: string
}

interface PayoutItem {
    id: number
    amount: string | number
    method: string
    status: string
    user: PayoutUser | null
}

const props = defineProps<{
    program: Program
    termsPageOptions: Array<{ title: string; slug: string }>
    stats: Record<string, number>
    affiliates: PaginatedResponse<AffiliateUser>
    commissions: PaginatedResponse<CommissionItem>
    payouts: PaginatedResponse<PayoutItem>
}>()

const { t } = useTranslate()

const payoutMethodOptions = ['paypal', 'bank_transfer', 'credits']
const payoutNote = ref<Record<number, string>>({})
const payoutStatus = ref<Record<number, string>>({})
const showSettingsModal = ref(false)
const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const activeTab = ref<'commissions' | 'payouts' | 'affiliates'>('commissions')
const processing = ref<Record<number, boolean>>({})
const rejectModal = ref({
    open: false,
    commissionId: null as number | null,
    processing: false,
})

const mkBanners = ref<Array<{ url: string; label: string }>>(
    (props.program.marketing_banners || []).map((banner) => ({
        url: banner.url || '',
        label: banner.label || '',
    })),
)
const mkEmails = ref<Array<{ subject: string; body: string }>>(
    (props.program.promotional_emails || []).map((email) => ({
        subject: email.subject || '',
        body: email.body || '',
    })),
)
const mkPosts = ref<Array<{ text: string; platform: string }>>(
    (props.program.social_posts || []).map((post) => ({
        text: post.text || '',
        platform: post.platform || '',
    })),
)

const addBanner = () => mkBanners.value.push({ url: '', label: '' })
const removeBanner = (index: number) => mkBanners.value.splice(index, 1)
const addEmail = () => mkEmails.value.push({ subject: '', body: '' })
const removeEmail = (index: number) => mkEmails.value.splice(index, 1)
const addPost = () => mkPosts.value.push({ text: '', platform: '' })
const removePost = (index: number) => mkPosts.value.splice(index, 1)

const commissionTypeOptions = computed(() => [
    { value: 'percentage', label: t('Percentage') },
    { value: 'fixed', label: t('Fixed') },
])

const commissionOnOptions = computed(() => [
    { value: 'first_purchase', label: t('First purchase') },
    { value: 'all_purchases', label: t('All purchases') },
    { value: 'subscription', label: t('Subscription') },
])

const form = useForm({
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
})

const searchPlaceholder = computed(() => {
    if (activeTab.value === 'commissions') {
        return t('Search referrer, referred user, status...')
    }

    if (activeTab.value === 'payouts') {
        return t('Search user, method, status...')
    }

    return t('Search affiliate, email, code...')
})

const summaryItems = computed(() => [
    {
        key: 'total_affiliates',
        label: t('Total affiliates'),
        value: props.stats.total_affiliates ?? 0,
    },
    {
        key: 'total_paid',
        label: t('Total paid'),
        value: props.stats.total_paid ?? 0,
    },
    {
        key: 'pending_payouts',
        label: t('Pending payouts'),
        value: props.stats.pending_payouts ?? 0,
    },
    {
        key: 'pending_commissions',
        label: t('Pending commissions'),
        value: props.stats.pending_commissions ?? 0,
    },
])

const filteredCommissions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.commissions.data
    }

    return props.commissions.data.filter((commission) => {
        return [
            commission.referrer?.name ?? '',
            commission.referrer?.email ?? '',
            commission.referred?.name ?? '',
            commission.referred?.email ?? '',
            commission.status,
            String(commission.amount),
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const filteredPayouts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.payouts.data
    }

    return props.payouts.data.filter((payout) => {
        return [
            payout.user?.name ?? '',
            payout.user?.email ?? '',
            payout.method,
            payout.status,
            String(payout.amount),
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const filteredAffiliates = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.affiliates.data
    }

    return props.affiliates.data.filter((affiliate) => {
        return [
            affiliate.name,
            affiliate.email,
            affiliate.referral_code,
            affiliate.ulid,
            String(affiliate.referral_earnings),
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const visibleRowsCount = computed(() => {
    if (activeTab.value === 'commissions') {
        return filteredCommissions.value.length
    }

    if (activeTab.value === 'payouts') {
        return filteredPayouts.value.length
    }

    return filteredAffiliates.value.length
})

const activePagination = computed(() => {
    if (activeTab.value === 'commissions') {
        return props.commissions
    }

    if (activeTab.value === 'payouts') {
        return props.payouts
    }

    return props.affiliates
})

const toggleMethod = (method: string) => {
    form.payout_methods = form.payout_methods.includes(method)
        ? form.payout_methods.filter((item) => item !== method)
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

const approve = (id: number) => {
    processing.value[id] = true

    router.post(route('admin.affiliate.commissions.approve', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value[id] = false
        },
    })
}

const requestReject = (id: number) => {
    rejectModal.value = {
        open: true,
        commissionId: id,
        processing: false,
    }
}

const confirmReject = () => {
    if (rejectModal.value.commissionId === null) {
        return
    }

    rejectModal.value.processing = true
    const id = rejectModal.value.commissionId

    router.post(route('admin.affiliate.commissions.reject', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            rejectModal.value = {
                open: false,
                commissionId: null,
                processing: false,
            }
        },
    })
}

const processPayout = (id: number) => {
    processing.value[id] = true

    router.post(route('admin.affiliate.payouts.process', id), {
        status: payoutStatus.value[id] || 'processing',
        admin_note: payoutNote.value[id] || '',
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value[id] = false
        },
    })
}

const focusSearchOnSlash = (event: KeyboardEvent) => {
    if (event.key !== '/' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    const target = event.target as HTMLElement | null

    if (target) {
        const isTypingContext = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)

        if (isTypingContext) {
            return
        }
    }

    event.preventDefault()
    searchInputRef.value?.focus()
    searchInputRef.value?.select()
}

const clearSearchOnEscape = (event: KeyboardEvent) => {
    if (event.key !== 'Escape' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    if (showSettingsModal.value || rejectModal.value.open || !searchQuery.value) {
        return
    }

    event.preventDefault()
    searchQuery.value = ''
}

onMounted(() => {
    document.addEventListener('keydown', focusSearchOnSlash)
    document.addEventListener('keydown', clearSearchOnEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', focusSearchOnSlash)
    document.removeEventListener('keydown', clearSearchOnEscape)
})
</script>

<template>
    <Head :title="t('Affiliate')" />

    <AdminLayout>
        <div class="py-6">
            <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
                <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('Affiliate Management') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Manage commission rules, approvals, payout requests, and affiliate assets.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="showSettingsModal = true"
                    >
                        <i class="ti ti-settings text-base"></i>
                        {{ t('Settings') }}
                    </button>
                </div>

                <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="item in summaryItems"
                        :key="item.key"
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ item.label }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ item.value }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="w-full xl:max-w-md">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                        <i class="ti ti-search text-base"></i>
                                    </span>
                                    <input
                                        ref="searchInputRef"
                                        v-model="searchQuery"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="searchPlaceholder"
                                    />
                                    <span
                                        v-if="!searchQuery"
                                        class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                                    >
                                        /
                                    </span>
                                    <button
                                        v-if="searchQuery"
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        :aria-label="t('Clear search')"
                                        :title="t('Clear search')"
                                        @click="searchQuery = ''"
                                    >
                                        <i class="ti ti-x text-base"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                                    :class="activeTab === 'commissions' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    @click="activeTab = 'commissions'"
                                >
                                    {{ t('Commissions') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                                    :class="activeTab === 'payouts' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    @click="activeTab = 'payouts'"
                                >
                                    {{ t('Payouts') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                                    :class="activeTab === 'affiliates' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    @click="activeTab = 'affiliates'"
                                >
                                    {{ t('Affiliates') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table v-if="activeTab === 'commissions'" class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referrer') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referred') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="commission in filteredCommissions"
                                    :key="commission.id"
                                    class="bg-white transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30"
                                >
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ commission.referrer?.name || '—' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ commission.referrer?.email || '—' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ commission.referred?.name || '—' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ commission.referred?.email || '—' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ commission.amount }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ t(commission.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div v-if="commission.status === 'pending'" class="inline-flex items-center gap-2">
                                            <button
                                                type="button"
                                                :disabled="processing[commission.id]"
                                                :aria-label="t('Approve commission :id', { id: commission.id })"
                                                class="btn-primary rounded-lg px-3 py-2 text-xs font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                @click="approve(commission.id)"
                                            >
                                                <span v-if="processing[commission.id]" class="inline-flex items-center gap-2">
                                                    <i class="ti ti-loader-2 animate-spin text-sm"></i>
                                                    {{ t('Approving...') }}
                                                </span>
                                                <span v-else>{{ t('Approve') }}</span>
                                            </button>
                                            <button
                                                type="button"
                                                :aria-label="t('Reject commission :id', { id: commission.id })"
                                                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50"
                                                @click="requestReject(commission.id)"
                                            >
                                                {{ t('Reject') }}
                                            </button>
                                        </div>
                                        <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                    </td>
                                </tr>
                                <tr v-if="filteredCommissions.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('No commissions found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-else-if="activeTab === 'payouts'" class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Method') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Process') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="payout in filteredPayouts"
                                    :key="payout.id"
                                    class="bg-white transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30"
                                >
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ payout.user?.name || '—' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ payout.user?.email || '—' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ payout.amount }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ t(payout.method) }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="payout.status === 'paid'
                                                ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                : payout.status === 'rejected'
                                                    ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                                        >
                                            {{ t(payout.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="grid gap-2 lg:grid-cols-[minmax(0,140px)_minmax(0,180px)_auto]">
                                            <select
                                                v-model="payoutStatus[payout.id]"
                                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            >
                                                <option value="processing">{{ t('Processing') }}</option>
                                                <option value="paid">{{ t('Paid') }}</option>
                                                <option value="rejected">{{ t('Rejected') }}</option>
                                            </select>
                                            <input
                                                v-model="payoutNote[payout.id]"
                                                type="text"
                                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                :placeholder="t('Add note')"
                                            />
                                            <button
                                                type="button"
                                                :disabled="processing[payout.id]"
                                                :aria-label="t('Process payout :id', { id: payout.id })"
                                                class="btn-primary rounded-lg px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                @click="processPayout(payout.id)"
                                            >
                                                <span v-if="processing[payout.id]" class="inline-flex items-center gap-2">
                                                    <i class="ti ti-loader-2 animate-spin text-sm"></i>
                                                    {{ t('Saving...') }}
                                                </span>
                                                <span v-else>{{ t('Save') }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredPayouts.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('No payouts found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-else class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Affiliate') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Code') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referrals') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Earnings') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="affiliate in filteredAffiliates"
                                    :key="affiliate.ulid"
                                    class="bg-white transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30"
                                >
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ affiliate.name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ affiliate.email }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">{{ affiliate.referral_code }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ affiliate.affiliate_referrals_count ?? affiliate.referral_count ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ affiliate.referral_earnings }}</td>
                                </tr>
                                <tr v-if="filteredAffiliates.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('No affiliates found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="!searchQuery && activePagination.links.length > 3"
                        class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6"
                    >
                        <Pagination
                            :links="activePagination.links"
                            :from="activePagination.from"
                            :to="activePagination.to"
                            :total="activePagination.total"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showSettingsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm"
            @click.self="showSettingsModal = false"
        >
            <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Affiliate Settings') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Configure commission rules, payout handling, and campaign assets.') }}</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                        :aria-label="t('Close modal')"
                        :disabled="form.processing"
                        @click="showSettingsModal = false"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <form class="overflow-y-auto p-6" @submit.prevent="save">
                    <div class="space-y-6">
                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="mb-4">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Program Settings') }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Control default commission behavior and affiliate eligibility rules.') }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <AppSelect v-model="form.commission_type" :label="t('Commission type')" :options="commissionTypeOptions" />

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Value') }}</span>
                                    <input
                                        v-model="form.commission_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    />
                                </label>

                                <AppSelect v-model="form.commission_on" :label="t('Commission on')" :options="commissionOnOptions" />

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Terms page slug') }}</span>
                                    <input
                                        v-model="form.terms_page_slug"
                                        list="affiliate-terms-pages"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="t('Enter page slug')"
                                    />
                                    <datalist id="affiliate-terms-pages">
                                        <option v-for="page in termsPageOptions" :key="page.slug" :value="page.slug">{{ page.title }}</option>
                                    </datalist>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Create a CMS page first, then enter its slug here.') }}</p>
                                </label>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Cookie days') }}</span>
                                    <input
                                        v-model="form.cookie_days"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    />
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Minimum payout') }}</span>
                                    <input
                                        v-model="form.min_payout"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    />
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Commission hold days') }}</span>
                                    <input
                                        v-model="form.commission_hold_days"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    />
                                </label>
                            </div>

                            <div class="mt-4">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Payout methods') }}</span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="method in payoutMethodOptions"
                                        :key="method"
                                        type="button"
                                        class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                                        :class="form.payout_methods.includes(method)
                                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'"
                                        @click="toggleMethod(method)"
                                    >
                                        {{ t(method.replace('_', ' ')) }}
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <label class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                    <span>{{ t('Enable payout requests') }}</span>
                                    <input v-model="form.payouts_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                    <span>{{ t('Auto approve commissions') }}</span>
                                    <input v-model="form.auto_approve_commissions" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                    <span>{{ t('Award credits on first purchase') }}</span>
                                    <input v-model="form.referral_credits_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                                <label class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                    <span>{{ t('Allow custom alias') }}</span>
                                    <input v-model="form.allow_custom_alias" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                </label>
                            </div>

                            <label class="mt-4 block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('First purchase credit amount') }}</span>
                                <input
                                    v-model="form.referral_credits_amount"
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Credits are added to the referrer only once, when the referred user completes the first purchase.') }}
                                </p>
                            </label>
                        </section>

                        <div class="grid gap-6 xl:grid-cols-2">
                            <section class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Marketing Banners') }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Share banner assets with affiliates for external promotion.') }}</p>
                                    </div>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        @click="addBanner"
                                    >
                                        {{ t('Add banner') }}
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        v-for="(banner, index) in mkBanners"
                                        :key="index"
                                        class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                                    >
                                        <div class="mb-3 flex items-center justify-between">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">#{{ index + 1 }}</span>
                                            <button type="button" class="text-xs font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" @click="removeBanner(index)">
                                                {{ t('Remove') }}
                                            </button>
                                        </div>

                                        <div class="space-y-3">
                                            <input
                                                v-model="banner.url"
                                                type="url"
                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                :placeholder="t('Banner URL')"
                                            />
                                            <input
                                                v-model="banner.label"
                                                type="text"
                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                :placeholder="t('Label optional')"
                                            />
                                        </div>
                                    </div>

                                    <p v-if="mkBanners.length === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No banners configured.') }}</p>
                                </div>
                            </section>

                            <section class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Promotional Emails') }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Prepare reusable outreach copy for affiliate campaigns.') }}</p>
                                    </div>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                        @click="addEmail"
                                    >
                                        {{ t('Add email') }}
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        v-for="(email, index) in mkEmails"
                                        :key="index"
                                        class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                                    >
                                        <div class="mb-3 flex items-center justify-between">
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">#{{ index + 1 }}</span>
                                            <button type="button" class="text-xs font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" @click="removeEmail(index)">
                                                {{ t('Remove') }}
                                            </button>
                                        </div>

                                        <div class="space-y-3">
                                            <input
                                                v-model="email.subject"
                                                type="text"
                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                :placeholder="t('Subject')"
                                            />
                                            <textarea
                                                v-model="email.body"
                                                rows="4"
                                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                                :placeholder="t('Email body')"
                                            />
                                        </div>
                                    </div>

                                    <p v-if="mkEmails.length === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No email templates configured.') }}</p>
                                </div>
                            </section>
                        </div>

                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Social Media Posts') }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Save short affiliate-ready post ideas for reuse across channels.') }}</p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                    @click="addPost"
                                >
                                    {{ t('Add post') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(post, index) in mkPosts"
                                    :key="index"
                                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">#{{ index + 1 }}</span>
                                        <button type="button" class="text-xs font-medium text-red-600 transition-colors hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" @click="removePost(index)">
                                            {{ t('Remove') }}
                                        </button>
                                    </div>

                                    <div class="space-y-3">
                                        <input
                                            v-model="post.platform"
                                            type="text"
                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            :placeholder="t('Platform name')"
                                        />
                                        <textarea
                                            v-model="post.text"
                                            rows="3"
                                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            :placeholder="t('Post text')"
                                        />
                                    </div>
                                </div>

                                <p v-if="mkPosts.length === 0" class="text-sm text-gray-500 dark:text-gray-400">{{ t('No social posts configured.') }}</p>
                            </div>
                        </section>
                    </div>
                </form>

                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        :disabled="form.processing"
                        @click="showSettingsModal = false"
                    >
                        {{ t('Cancel') }}
                    </button>

                    <button
                        type="button"
                        :disabled="form.processing"
                        class="btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        @click="save"
                    >
                        {{ form.processing ? t('Saving...') : t('Save Settings') }}
                    </button>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="rejectModal.open"
            :title="t('Reject commission?')"
            :message="t('Are you sure you want to reject this commission? This action cannot be undone.')"
            :confirm-label="t('Reject')"
            :processing="rejectModal.processing"
            variant="danger"
            @confirm="confirmReject"
            @cancel="rejectModal.open = false"
        />
    </AdminLayout>
</template>
