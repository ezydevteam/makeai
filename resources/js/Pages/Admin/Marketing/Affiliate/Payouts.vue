<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface PayoutItem {
    id: number
    amount: number
    method: string
    status: string
    payout_details: Record<string, string> | null
    admin_note: string | null
    processed_at: string | null
    created_at: string | null
    user: { ulid?: string; name?: string; email?: string } | null
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedResponse<T> { data: T[]; links: PaginationLink[]; from?: number; to?: number; total?: number }

const props = defineProps<{
    payouts: PaginatedResponse<PayoutItem>
    stats: {
        pending_payouts: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        total_paid: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const searchInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const statusFilter = ref('')
const processing = ref<Record<number, boolean>>({})
const reviewModal = ref({
    open: false,
    payout: null as PayoutItem | null,
    status: 'processing' as 'processing' | 'paid' | 'rejected',
    note: '',
    processing: false,
})

const statusOptions = computed(() => [
    { value: 'pending', label: t('Pending') },
    { value: 'processing', label: t('Processing') },
    { value: 'paid', label: t('Paid') },
    { value: 'rejected', label: t('Rejected') },
])

const actionStatusOptions = computed(() => [
    { value: 'processing', label: t('Processing') },
    { value: 'paid', label: t('Paid') },
    { value: 'rejected', label: t('Rejected') },
])

const hasActiveFilters = computed(() => searchQuery.value.trim().length > 0 || statusFilter.value !== '')

const filteredPayouts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.payouts.data.filter((payout) => {
        const matchesStatus = !statusFilter.value || payout.status === statusFilter.value

        if (!matchesStatus) {
            return false
        }

        if (!query) {
            return true
        }

        const haystacks = [
            payout.user?.name,
            payout.user?.email,
            payout.method,
            payout.status,
            payout.admin_note,
            formatDetails(payout),
        ]

        return haystacks.some((value) => value?.toLowerCase().includes(query))
    })
})

const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
}

const badgeClass = (status: string): string =>
    status === 'paid' ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
        : status === 'rejected' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'

const isFinalized = (status: string) => status === 'paid' || status === 'rejected'

const focusSearch = () => {
    searchInput.value?.focus()
    searchInput.value?.select()
}

const handleSearchShortcuts = (event: KeyboardEvent) => {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    const target = event.target as HTMLElement | null
    const tagName = target?.tagName
    const isTypingTarget = tagName === 'INPUT' || tagName === 'TEXTAREA' || target?.isContentEditable

    if (event.key === '/' && !isTypingTarget) {
        event.preventDefault()
        focusSearch()
        return
    }

    if (event.key === 'Escape' && hasActiveFilters.value) {
        clearFilters()
        if (document.activeElement === searchInput.value) {
            searchInput.value?.blur()
        }
    }
}

const formatDetails = (payout: PayoutItem): string => {
    const details = payout.payout_details
    if (!details) return payout.method === 'credits' ? t('Account credits') : '—'
    if (payout.method === 'paypal' && details.paypal_email) return details.paypal_email
    if (payout.method === 'bank_transfer' && details.bank_account) return details.bank_account
    if (payout.method === 'credits') return t('Account credits')
    const values = Object.values(details).filter(Boolean)
    return values.length ? values.join(' · ') : '—'
}

const detailEntries = (payout: PayoutItem) => {
    const details = payout.payout_details

    if (!details || Object.keys(details).length === 0) {
        return [{ label: t('Details'), value: formatDetails(payout) }]
    }

    return Object.entries(details)
        .filter(([, value]) => Boolean(value))
        .map(([key, value]) => ({
            label: t(key.replace(/_/g, ' ')),
            value,
        }))
}

const openReviewModal = (payout: PayoutItem) => {
    reviewModal.value = {
        open: true,
        payout,
        status: payout.status === 'pending' ? 'processing' : (payout.status as 'processing' | 'paid' | 'rejected'),
        note: payout.admin_note || '',
        processing: false,
    }
}

const resetReviewModal = () => {
    reviewModal.value = {
        open: false,
        payout: null,
        status: 'processing',
        note: '',
        processing: false,
    }
}

const closeReviewModal = () => {
    if (reviewModal.value.processing) return
    resetReviewModal()
}

const submitReview = () => {
    if (!reviewModal.value.payout) return
    const id = reviewModal.value.payout.id
    reviewModal.value.processing = true
    processing.value[id] = true
    router.post(route('admin.affiliate.payouts.process', id), { status: reviewModal.value.status, admin_note: reviewModal.value.note }, {
        preserveScroll: true,
        onSuccess: () => {
            resetReviewModal()
        },
        onFinish: () => {
            processing.value[id] = false
            reviewModal.value.processing = false
        },
    })
}

onMounted(() => {
    document.addEventListener('keydown', handleSearchShortcuts)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleSearchShortcuts)
})
</script>

<template>
    <Head :title="t('Affiliate Payouts')" />

    <AdminLayout>
                <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Payouts') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Process withdrawal requests and review payout details.') }}</p>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-auto xl:min-w-[480px]">
                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                                <i class="ti ti-wallet text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Pending payouts') }}</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ stats.pending_payouts?.value ?? '$0.00' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-300">
                                <i class="ti ti-cash-banknote text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Total paid') }}</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ stats.total_paid?.value ?? '$0.00' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500"><i class="ti ti-search text-base"></i></span>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Search user name or email...')"
                                />
                                <span
                                    v-if="!searchQuery"
                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
                                >
                                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-md border border-gray-200 bg-white px-1.5 text-[11px] font-medium text-gray-400 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500">/</span>
                                </span>
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    @click="searchQuery = ''"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                            <div class="w-full sm:w-48"><AppSelect v-model="statusFilter" :options="statusOptions" :placeholder="t('Status')" /></div>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition hover:border-gray-300 hover:text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear filters')"
                                @click="clearFilters"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Method') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Payout details') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="p in filteredPayouts" :key="p.id" class="bg-white transition hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30">
                                <td class="px-6 py-4">
                                    <Link v-if="p.user?.ulid" :href="route('admin.affiliate.affiliates.show', p.user.ulid)" class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white">{{ p.user?.name || '—' }}</Link>
                                    <span v-else class="text-sm text-gray-400">—</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ p.user?.email || '—' }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(p.amount) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ t(p.method) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"><span class="break-all">{{ formatDetails(p) }}</span></td>
                                <td class="px-6 py-4"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="badgeClass(p.status)">{{ t(p.status) }}</span></td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        type="button"
                                        :disabled="processing[p.id]"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium disabled:opacity-50"
                                        :class="isFinalized(p.status)
                                            ? 'border border-gray-200 bg-white text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'
                                            : 'bg-primary-600 text-white transition hover:bg-primary-700'"
                                        @click="openReviewModal(p)"
                                    >
                                        <i :class="isFinalized(p.status) ? 'ti ti-eye text-xs' : 'ti ti-settings text-xs'"></i>
                                        <span>{{ isFinalized(p.status) ? t('View') : t('Review') }}</span>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredPayouts.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ hasActiveFilters ? t('No payouts match your filters.') : t('No payout requests yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="payouts.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <Pagination :links="payouts.links" :from="payouts.from" :to="payouts.to" :total="payouts.total" />
                </div>
            </div>
        </div>
    

        <div v-if="reviewModal.open && reviewModal.payout" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm" @click.self="closeReviewModal">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Payout details') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Review the payout request and update its status.') }}</p>
                        </div>
                        <button type="button" class="rounded-full w-8 h-8 flex items-center justify-center text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300" :aria-label="t('Close modal')" @click="closeReviewModal">
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-5">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Affiliate') }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ reviewModal.payout.user?.name || '—' }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ reviewModal.payout.user?.email || '—' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(reviewModal.payout.amount) }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t(reviewModal.payout.method) }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Requested') }}</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ reviewModal.payout.created_at ? formatDateTime(reviewModal.payout.created_at) : '—' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Current status') }}</p>
                            <div class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="badgeClass(reviewModal.payout.status)">{{ t(reviewModal.payout.status) }}</span>
                            </div>
                            <p v-if="reviewModal.payout.processed_at" class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(reviewModal.payout.processed_at) }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-900/30">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Payout details') }}</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div v-for="entry in detailEntries(reviewModal.payout)" :key="`${entry.label}-${entry.value}`" class="min-w-0">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ entry.label }}</p>
                                <p class="mt-1 break-all text-sm text-gray-700 dark:text-gray-200">{{ entry.value }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-900/30">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Admin action') }}</p>
                            <span v-if="isFinalized(reviewModal.payout.status)" class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ t('This payout is finalized.') }}</span>
                        </div>

                        <div class="mt-3 space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Action status') }}</label>
                                <AppSelect v-model="reviewModal.status" :options="actionStatusOptions" :disabled="isFinalized(reviewModal.payout.status)" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Admin note') }}</label>
                                <textarea
                                    v-model="reviewModal.note"
                                    rows="3"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :disabled="isFinalized(reviewModal.payout.status)"
                                    :placeholder="t('Optional note for the affiliate')"
                                />
                            </div>

                            <p v-if="reviewModal.payout.admin_note && isFinalized(reviewModal.payout.status)" class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Saved note') }}: {{ reviewModal.payout.admin_note }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60">
                    <button type="button" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700" :disabled="reviewModal.processing" @click="closeReviewModal">{{ isFinalized(reviewModal.payout.status) ? t('Close') : t('Cancel') }}</button>
                    <button v-if="!isFinalized(reviewModal.payout.status)" type="button" :disabled="reviewModal.processing" class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-50" :class="reviewModal.status === 'rejected' ? 'bg-red-600 hover:bg-red-700' : 'btn-primary'" @click="submitReview">
                        {{ reviewModal.processing ? t('Saving...') : t('Update payout') }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
