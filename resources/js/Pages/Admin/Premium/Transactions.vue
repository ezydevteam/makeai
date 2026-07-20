<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppFilterDropdown from '@/Components/Admin/AppFilterDropdown.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface GatewayOption {
    value: string
    label: string
}

interface TransactionUser {
    ulid: string
    name: string
    email: string
}

interface TransactionPlan {
    id: number
    name: string
}

interface BankTransferInfo {
    proof_url: string | null
    original_name: string | null
    reference: string | null
    note: string | null
    uploaded_at: string | null
}

interface AdminReview {
    action: string
    note?: string | null
    reviewed_at?: string | null
}

interface TransactionItem {
    id: number
    ulid: string
    gateway: string | null
    gateway_payment_id: string | null
    amount: number
    currency: string | null
    status: string
    type: string
    billing_cycle: string | null
    total_credits: number | string | null
    created_at: string | null
    user: TransactionUser | null
    plan: TransactionPlan | null
    bank_transfer: BankTransferInfo | null
    admin_review: AdminReview | null
    gateway_error: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface TransactionResponse {
    data: TransactionItem[]
    links: PaginationLink[]
    from?: number | null
    to?: number | null
    total?: number
}

interface Filters {
    status?: string | null
    gateway?: string | null
    type?: string | null
    search?: string | null
}

interface Stats {
    total: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    pending: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    completed: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    revenue: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
}

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{
    payments: TransactionResponse
    filters: Filters
    gateways: GatewayOption[]
    stats: Stats
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const statusFilter = ref(props.filters.status ? String(props.filters.status) : '')
const gatewayFilter = ref(props.filters.gateway ? String(props.filters.gateway) : '')
const typeFilter = ref(props.filters.type ? String(props.filters.type) : '')
const searchQuery = ref(props.filters.search ? String(props.filters.search) : '')

const activeFiltersCount = computed(() => {
    return [statusFilter.value, gatewayFilter.value, typeFilter.value].filter(Boolean).length
})

const resetFilters = () => {
    statusFilter.value = ''
    gatewayFilter.value = ''
    typeFilter.value = ''
    applyFilters()
}

interface ApproveState {
    open: boolean
    processing: boolean
    payment: TransactionItem | null
}

interface RejectState {
    open: boolean
    processing: boolean
    note: string
    payment: TransactionItem | null
}

const approveState = ref<ApproveState>({ open: false, processing: false, payment: null })
const rejectState = ref<RejectState>({ open: false, processing: false, note: '', payment: null })

interface DetailsState {
    open: boolean
    payment: TransactionItem | null
}

const detailsState = ref<DetailsState>({ open: false, payment: null })

const openDetails = (payment: TransactionItem) => {
    detailsState.value = { open: true, payment }
}

const closeDetails = () => {
    detailsState.value = { open: false, payment: null }
}

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: 'pending', label: t('Pending') },
    { value: 'completed', label: t('Completed') },
    { value: 'failed', label: t('Failed') },
    { value: 'refunded', label: t('Refunded') },
])

const typeOptions = computed(() => [
    { value: '', label: t('All Types') },
    { value: 'subscription', label: t('Subscription') },
    { value: 'credit_topup', label: t('Credit Top-up') },
])

const gatewayOptions = computed(() => [
    { value: '', label: t('All Gateways') },
    ...props.gateways.map((gateway) => ({ value: gateway.value, label: gateway.label })),
])

const applyFilters = () => {
    const params: Record<string, string> = {}

    if (statusFilter.value) params.status = statusFilter.value
    if (gatewayFilter.value) params.gateway = gatewayFilter.value
    if (typeFilter.value) params.type = typeFilter.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    router.get(route('admin.transactions.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

let searchTimeout: any = null
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
})

const statusLabel = (status: string) => {
    switch (status) {
        case 'pending':
            return t('Pending')
        case 'completed':
            return t('Completed')
        case 'failed':
            return t('Failed')
        case 'refunded':
            return t('Refunded')
        default:
            return status.replace(/[_-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
    }
}

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
        case 'pending':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'
        case 'failed':
            return 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20'
        case 'refunded':
            return 'bg-purple-50 text-purple-700 ring-1 ring-purple-200 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-500/20'
        default:
            return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'
    }
}

const gatewayLabel = (gateway: string | null) => {
    if (!gateway) {
        return t('Unknown')
    }

    return gateway.replace(/[_-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

const typeLabel = (payment: TransactionItem) => {
    if (payment.type === 'credit_topup') {
        return payment.total_credits
            ? t(':count credits', { count: String(payment.total_credits) })
            : t('Credit Top-up')
    }

    return payment.plan?.name ?? t('Subscription')
}

const openApprove = (payment: TransactionItem) => {
    approveState.value = { open: true, processing: false, payment }
}

const closeApprove = () => {
    if (!approveState.value.processing) {
        approveState.value = { open: false, processing: false, payment: null }
    }
}

const confirmApprove = () => {
    const target = approveState.value.payment

    if (!target || approveState.value.processing) {
        return
    }

    approveState.value.processing = true

    router.post(route('admin.transactions.approve', target.ulid), {}, {
        preserveScroll: true,
        onFinish: () => {
            approveState.value.processing = false
            closeApprove()
        },
    })
}

const openReject = (payment: TransactionItem) => {
    rejectState.value = { open: true, processing: false, note: '', payment }
}

const closeReject = () => {
    if (!rejectState.value.processing) {
        rejectState.value = { open: false, processing: false, note: '', payment: null }
    }
}

const confirmReject = () => {
    const target = rejectState.value.payment

    if (!target || rejectState.value.processing) {
        return
    }

    rejectState.value.processing = true

    router.post(route('admin.transactions.reject', target.ulid), { note: rejectState.value.note || null }, {
        preserveScroll: true,
        onFinish: () => {
            rejectState.value.processing = false
            closeReject()
        },
    })
}
</script>

<template>
    <Head :title="t('Transactions')" />

    <AdminLayout>
        <ActionConfirmModal
            :open="approveState.open"
            :title="t('Approve transaction')"
            :message="t('This will mark the payment as completed and activate the subscription or credits for this user.')"
            :confirm-label="t('Approve')"
            :cancel-label="t('Cancel')"
            :processing-label="t('Approving...')"
            :processing="approveState.processing"
            variant="primary"
            @cancel="closeApprove"
            @confirm="confirmApprove"
        />
        <ActionConfirmModal
            :open="rejectState.open"
            :title="t('Reject transaction')"
            :message="t('The payment will be marked as failed and the user will be notified.')"
            :confirm-label="t('Reject')"
            :cancel-label="t('Cancel')"
            :processing-label="t('Rejecting...')"
            :processing="rejectState.processing"
            variant="danger"
            @cancel="closeReject"
            @confirm="confirmReject"
        >
            <label class="mt-4 block">
                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Reason (optional)') }}</span>
                <textarea
                    v-model="rejectState.note"
                    rows="3"
                    maxlength="500"
                    :placeholder="t('e.g. Payment reference not found in bank statement')"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                ></textarea>
            </label>
        </ActionConfirmModal>

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ t('Transactions') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Review payments, approve bank transfers, and track failed or refunded transactions.') }}
                    </p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    :title="t('Total Transactions')"
                    :value="stats.total.value"
                    :comparison="stats.total.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.total.comparison.type"
                    color="primary"
                >
                    <template #icon>
                        <i class="ti ti-receipt text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Pending')"
                    :value="stats.pending.value"
                    color="warning"
                >
                    <template #icon>
                        <i class="ti ti-hourglass-low text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Completed')"
                    :value="stats.completed.value"
                    :comparison="stats.completed.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.completed.comparison.type"
                    color="success"
                >
                    <template #icon>
                        <i class="ti ti-circle-check text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Total Transactions')"
                    :value="stats.revenue.value"
                    :comparison="stats.revenue.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.revenue.comparison.type"
                    color="accent"
                >
                    <template #icon>
                        <i class="ti ti-currency-dollar text-lg"></i>
                    </template>
                </StatsCard>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                    <div class="flex gap-4 items-center justify-between">
                        <div class="flex-1 relative sm:max-w-xs">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    :placeholder="t('Search transactions...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    @keyup.enter="applyFilters"
                                />
                                <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                    <span v-if="!searchQuery" class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500">/</span>
                                </div>
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    @click="searchQuery = ''"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <AppFilterDropdown :active-filters-count="activeFiltersCount">
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('Status') }}</label>
                                        <AppSelect
                                            v-model="statusFilter"
                                            :options="statusOptions"
                                            option-label="label"
                                            option-value="value"
                                            :placeholder="t('All Status')"
                                            @update:model-value="applyFilters"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('Gateway') }}</label>
                                        <AppSelect
                                            v-model="gatewayFilter"
                                            :options="gatewayOptions"
                                            option-label="label"
                                            option-value="value"
                                            :placeholder="t('All Gateways')"
                                            @update:model-value="applyFilters"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('Type') }}</label>
                                        <AppSelect
                                            v-model="typeFilter"
                                            :options="typeOptions"
                                            option-label="label"
                                            option-value="value"
                                            :placeholder="t('All Types')"
                                            @update:model-value="applyFilters"
                                        />
                                    </div>
                                    <div v-if="activeFiltersCount > 0" class="border-t border-gray-100 pt-3 dark:border-surface-800 flex justify-end">
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-red-600 hover:text-red-500 transition-colors"
                                            @click="resetFilters"
                                        >
                                            {{ t('Clear Filters') }}
                                        </button>
                                    </div>
                                </div>
                            </AppFilterDropdown>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-b-2xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">{{ t('Customer') }}</th>
                                    <th class="px-6 py-3">{{ t('Item') }}</th>
                                    <th class="px-6 py-3">{{ t('Amount') }}</th>
                                    <th class="px-6 py-3">{{ t('Gateway') }}</th>
                                    <th class="px-6 py-3">{{ t('Status') }}</th>
                                    <th class="px-6 py-3">{{ t('Date') }}</th>
                                    <th class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                                <tr
                                    v-for="payment in payments.data"
                                    :key="payment.id"
                                    class="transition hover:bg-primary-50/40 dark:hover:bg-white/[0.03]"
                                >
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[200px]">
                                            <Link
                                                v-if="payment.user?.ulid"
                                                :href="route('admin.users.show', payment.user.ulid)"
                                                class="font-semibold text-gray-900 transition hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                            >
                                                {{ payment.user.name }}
                                            </Link>
                                            <p v-else class="font-semibold text-gray-900 dark:text-white">{{ t('Deleted User') }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ payment.user?.email ?? t('No email available') }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[150px]">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ typeLabel(payment) }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ payment.type === 'credit_topup' ? t('Credit Top-up') : (payment.billing_cycle ?? t('Subscription')) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ formatCurrency(payment.amount, payment.currency ?? undefined) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[130px]">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ gatewayLabel(payment.gateway) }}</p>
                                            <p v-if="payment.gateway_payment_id" class="mt-1 max-w-[160px] truncate text-xs text-gray-500 dark:text-gray-400" :title="payment.gateway_payment_id">
                                                {{ payment.gateway_payment_id }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(payment.status)">
                                            {{ statusLabel(payment.status) }}
                                        </span>
                                        <p v-if="payment.admin_review?.action === 'rejected' && payment.admin_review?.note" class="mt-2 max-w-[200px] text-xs text-gray-500 dark:text-gray-400">
                                            {{ payment.admin_review.note }}
                                        </p>
                                        <p v-else-if="payment.gateway_error" class="mt-2 max-w-[200px] text-xs text-red-500 dark:text-red-400">
                                            {{ payment.gateway_error }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <p class="whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ payment.created_at ? formatDateTime(payment.created_at) : '—' }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex items-center justify-end">
                                            <TableActionMenu>
                                                <template #default="{ close }">
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                        @click="openDetails(payment); close()"
                                                    >
                                                        <i class="ti ti-eye text-base"></i>
                                                        {{ t('View details') }}
                                                    </button>

                                                    <a
                                                        v-if="payment.bank_transfer?.proof_url"
                                                        :href="payment.bank_transfer.proof_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                        @click="close()"
                                                    >
                                                        <i class="ti ti-file-search text-base"></i>
                                                        {{ t('View payment proof') }}
                                                    </a>

                                                    <template v-if="payment.status === 'pending'">
                                                        <hr class="border-gray-200 dark:border-surface-700">
                                                        <button
                                                            type="button"
                                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-900 dark:text-emerald-400 dark:hover:bg-surface-800"
                                                            @click="openApprove(payment); close()"
                                                        >
                                                            <i class="ti ti-check text-base"></i>
                                                            {{ t('Approve') }}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 hover:text-red-900 dark:text-red-400 dark:hover:bg-surface-800"
                                                            @click="openReject(payment); close()"
                                                        >
                                                            <i class="ti ti-x text-base"></i>
                                                            {{ t('Reject') }}
                                                        </button>
                                                    </template>
                                                </template>
                                            </TableActionMenu>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="payments.data.length === 0">
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <div class="mx-auto max-w-sm">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                                <i class="ti ti-receipt-2 text-xl"></i>
                                            </div>
                                            <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">{{ t('No transactions found') }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ t('Try clearing the search or changing the active filters.') }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="payments.links.length > 3"
                        class="border-t border-gray-100 px-5 py-4 dark:border-surface-800"
                    >
                        <Pagination
                            :links="payments.links"
                            :from="payments.from ?? 0"
                            :to="payments.to ?? payments.data.length"
                            :total="payments.total ?? payments.data.length"
                        />
                    </div>
                </div>
            </div>
        </div>

        <AppModal
            :open="detailsState.open && !!detailsState.payment"
            max-width="max-w-2xl"
            :title="t('Transaction Details')"
            :subtitle="detailsState.payment ? `ID: ${detailsState.payment.ulid}` : ''"
            :cancel-text="t('Close')"
            @close="closeDetails"
        >
            <div v-if="detailsState.payment" class="space-y-6">
                <!-- Transaction Overview -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-surface-800/60 dark:bg-surface-800/30">
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ t('Amount') }}</span>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatCurrency(detailsState.payment.amount, detailsState.payment.currency ?? undefined) }}
                            </span>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusBadgeClass(detailsState.payment.status)">
                                {{ statusLabel(detailsState.payment.status) }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-surface-800/60 dark:bg-surface-800/30">
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ t('Customer') }}</span>
                        <div class="mt-1.5 min-w-0">
                            <Link
                                v-if="detailsState.payment.user?.ulid"
                                :href="route('admin.users.show', detailsState.payment.user.ulid)"
                                class="block truncate font-semibold text-gray-900 transition hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                @click="closeDetails"
                            >
                                {{ detailsState.payment.user.name }}
                            </Link>
                            <span v-else class="block truncate font-semibold text-gray-900 dark:text-white">
                                {{ t('Deleted User') }}
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ detailsState.payment.user?.email ?? t('No email available') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Technical and Billing Details -->
                <div class="rounded-2xl border border-gray-100 p-5 dark:border-surface-800">
                    <h4 class="mb-4 text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ t('Billing & Gateway Information') }}</h4>
                    <div class="grid gap-y-4 gap-x-6 sm:grid-cols-2">
                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Item Name / Type') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ typeLabel(detailsState.payment) }} ({{ detailsState.payment.type === 'credit_topup' ? t('Credit Top-up') : (detailsState.payment.billing_cycle ?? t('Subscription')) }})
                            </span>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Credits Added') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ detailsState.payment.total_credits || '—' }}
                            </span>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Payment Method') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ gatewayLabel(detailsState.payment.gateway) }}
                            </span>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Gateway Reference') }}</span>
                            <span class="mt-1 block truncate text-sm font-semibold text-gray-900 dark:text-white" :title="detailsState.payment.gateway_payment_id || undefined">
                                {{ detailsState.payment.gateway_payment_id || '—' }}
                            </span>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Created Date') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ detailsState.payment.created_at ? formatDateTime(detailsState.payment.created_at) : '—' }}
                            </span>
                        </div>

                        <div v-if="detailsState.payment.gateway_error">
                            <span class="text-xs text-red-400 dark:text-red-500">{{ t('Gateway Error') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-red-600 dark:text-red-400">
                                {{ detailsState.payment.gateway_error }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Bank Transfer Section -->
                <div v-if="detailsState.payment.bank_transfer" class="rounded-2xl border border-amber-100 bg-amber-50/20 p-5 dark:border-amber-900/30 dark:bg-amber-950/10">
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-sm font-bold text-amber-800 dark:text-amber-400 uppercase tracking-wider">{{ t('Bank Transfer Reference') }}</h4>
                        <a
                            v-if="detailsState.payment.bank_transfer.proof_url"
                            :href="detailsState.payment.bank_transfer.proof_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 shadow-sm transition hover:bg-amber-50 dark:border-amber-900/40 dark:bg-surface-900 dark:text-amber-300 dark:hover:bg-amber-900/20"
                        >
                            <i class="ti ti-download text-sm"></i>
                            {{ t('Download Proof') }}
                        </a>
                    </div>
                    <div class="grid gap-y-4 gap-x-6 sm:grid-cols-2">
                        <div>
                            <span class="text-xs text-amber-600 dark:text-amber-500/80">{{ t('Transaction Reference') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ detailsState.payment.bank_transfer.reference || '—' }}
                            </span>
                        </div>

                        <div>
                            <span class="text-xs text-amber-600 dark:text-amber-500/80">{{ t('Uploaded At') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ detailsState.payment.bank_transfer.uploaded_at ? formatDateTime(detailsState.payment.bank_transfer.uploaded_at) : '—' }}
                            </span>
                        </div>

                        <div class="sm:col-span-2">
                            <span class="text-xs text-amber-600 dark:text-amber-500/80">{{ t('Customer Note') }}</span>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                {{ detailsState.payment.bank_transfer.note || '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Admin Review Details -->
                <div v-if="detailsState.payment.admin_review" class="rounded-2xl border border-gray-100 bg-gray-50/50 p-5 dark:border-surface-800 dark:bg-surface-800/20">
                    <h4 class="mb-4 text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ t('Admin Verification') }}</h4>
                    <div class="grid gap-y-4 gap-x-6 sm:grid-cols-2">
                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Verification Action') }}</span>
                            <span class="mt-1 block text-sm font-semibold capitalize" :class="detailsState.payment.admin_review.action === 'approved' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                {{ detailsState.payment.admin_review.action }}
                            </span>
                        </div>

                        <div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Reviewed At') }}</span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900 dark:text-white">
                                {{ detailsState.payment.admin_review.reviewed_at ? formatDateTime(detailsState.payment.admin_review.reviewed_at) : '—' }}
                            </span>
                        </div>

                        <div v-if="detailsState.payment.admin_review.note" class="sm:col-span-2">
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ t('Verification Note') }}</span>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                {{ detailsState.payment.admin_review.note }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AppModal>
    </AdminLayout>
</template>
