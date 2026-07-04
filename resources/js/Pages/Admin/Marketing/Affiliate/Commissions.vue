<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface CommissionParty { ulid?: string; name?: string; email?: string }
interface CommissionItem {
    id: number
    amount: number
    status: string
    created_at: string | null
    referrer: CommissionParty | null
    referred: CommissionParty | null
    payment: { amount: number; currency: string } | null
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedResponse<T> { data: T[]; links: PaginationLink[]; from?: number; to?: number; total?: number }

const props = defineProps<{
    commissions: PaginatedResponse<CommissionItem>
    stats: {
        pending_commissions: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const searchInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const statusFilter = ref('')
const processing = ref<Record<number, boolean>>({})
const selected = ref<number[]>([])
const approveModal = ref({ open: false, id: null as number | null, processing: false })
const rejectModal = ref({ open: false, id: null as number | null, processing: false })

const statusOptions = computed(() => [
    { value: 'pending', label: t('Pending') },
    { value: 'approved', label: t('Approved') },
    { value: 'paid', label: t('Paid') },
    { value: 'rejected', label: t('Rejected') },
    { value: 'cancelled', label: t('Cancelled') },
])

const hasActiveFilters = computed(() => searchQuery.value.trim().length > 0 || statusFilter.value !== '')

const filteredCommissions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.commissions.data.filter((commission) => {
        const matchesStatus = !statusFilter.value || commission.status === statusFilter.value

        if (!matchesStatus) {
            return false
        }

        if (!query) {
            return true
        }

        const haystacks = [
            commission.referrer?.name,
            commission.referrer?.email,
            commission.referred?.name,
            commission.referred?.email,
            commission.status,
        ]

        return haystacks.some((value) => value?.toLowerCase().includes(query))
    })
})

const pendingIds = computed(() => filteredCommissions.value.filter((c) => c.status === 'pending').map((c) => c.id))
const allPendingSelected = computed(() => pendingIds.value.length > 0 && pendingIds.value.every((id) => selected.value.includes(id)))

const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
}

const badgeClass = (status: string): string => {
    if (status === 'approved') return 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
    if (status === 'paid') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
    if (status === 'rejected') return 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
    if (status === 'cancelled') return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
    return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
}

const toggleRow = (id: number) => {
    selected.value = selected.value.includes(id) ? selected.value.filter((i) => i !== id) : [...selected.value, id]
}
const toggleSelectAll = () => {
    selected.value = allPendingSelected.value
        ? selected.value.filter((id) => !pendingIds.value.includes(id))
        : [...new Set([...selected.value, ...pendingIds.value])]
}

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

watch(filteredCommissions, (items) => {
    const visibleIds = new Set(items.map((item) => item.id))
    selected.value = selected.value.filter((id) => visibleIds.has(id))
})

onMounted(() => {
    document.addEventListener('keydown', handleSearchShortcuts)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleSearchShortcuts)
})

const requestApprove = (id: number) => { approveModal.value = { open: true, id, processing: false } }
const confirmApprove = () => {
    if (approveModal.value.id === null) return
    const id = approveModal.value.id
    approveModal.value.processing = true
    processing.value[id] = true
    router.post(route('admin.affiliate.commissions.approve', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value[id] = false
            approveModal.value = { open: false, id: null, processing: false }
            selected.value = selected.value.filter((i) => i !== id)
        },
    })
}
const requestReject = (id: number) => { rejectModal.value = { open: true, id, processing: false } }
const confirmReject = () => {
    if (rejectModal.value.id === null) return
    const id = rejectModal.value.id
    rejectModal.value.processing = true
    router.post(route('admin.affiliate.commissions.reject', id), {}, {
        preserveScroll: true,
        onFinish: () => { rejectModal.value = { open: false, id: null, processing: false }; selected.value = selected.value.filter((i) => i !== id) },
    })
}
const bulkApprove = () => {
    if (selected.value.length === 0) return
    router.post(route('admin.affiliate.commissions.bulk-approve'), { ids: selected.value }, { preserveScroll: true, onSuccess: () => { selected.value = [] } })
}
</script>

<template>
    <Head :title="t('Affiliate Commissions')" />

    <AdminLayout>
                <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Commissions') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review, approve, and reject affiliate commissions.') }}</p>
                </div>

                <div class="w-full min-w-[220px] rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm sm:w-auto sm:flex-none dark:border-gray-800 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                            <i class="ti ti-coins text-lg"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Pending') }}</p>
                            <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ stats.pending_commissions?.value ?? '$0.00' }}</p>
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
                                    :placeholder="t('Search referrer or referred user...')"
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
                            <div class="w-full sm:w-48">
                                <AppSelect v-model="statusFilter" :options="statusOptions" :placeholder="t('Status')" />
                            </div>
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

                <div v-if="selected.length > 0" class="flex items-center justify-between gap-3 border-b border-gray-100 bg-primary-50/60 px-6 py-3 dark:border-gray-800 dark:bg-primary-900/20">
                    <span class="text-sm font-medium text-primary-700 dark:text-primary-300">{{ t(':count selected', { count: selected.length }) }}</span>
                    <button type="button" class="btn-primary rounded-lg px-4 py-2 text-xs font-semibold text-white" @click="bulkApprove">{{ t('Approve selected') }}</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th v-if="pendingIds.length > 0" class="w-10 px-6 py-3">
                                    <input type="checkbox" :checked="allPendingSelected" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" :aria-label="t('Select all pending')" @change="toggleSelectAll" />
                                </th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referrer') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referred') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Order') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="c in filteredCommissions" :key="c.id" class="bg-white transition hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30">
                                <td v-if="pendingIds.length > 0" class="px-6 py-4">
                                    <input type="checkbox" :checked="selected.includes(c.id)" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" :aria-label="t('Select commission')" @change="toggleRow(c.id)" />
                                </td>
                                <td class="px-6 py-4">
                                    <Link v-if="c.referrer?.ulid" :href="route('admin.affiliate.affiliates.show', c.referrer.ulid)" class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white">{{ c.referrer?.name || '—' }}</Link>
                                    <span v-else class="text-sm text-gray-400">—</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ c.referrer?.email || '—' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ c.referred?.name || '—' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ c.referred?.email || '—' }}</p>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700 dark:text-gray-300">{{ c.payment ? formatCurrency(c.payment.amount, c.payment.currency) : '—' }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(c.amount) }}</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="badgeClass(c.status)">{{ t(c.status) }}</span></td>
                                <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">{{ c.created_at ? formatDateTime(c.created_at) : '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div v-if="c.status === 'pending'" class="inline-flex items-center gap-2">
                                        <button type="button" :disabled="processing[c.id]" class="btn-primary rounded-lg px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50" @click="requestApprove(c.id)">
                                            <span v-if="processing[c.id]"><i class="ti ti-loader-2 animate-spin text-xs"></i></span>
                                            <span v-else>{{ t('Approve') }}</span>
                                        </button>
                                        <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50" @click="requestReject(c.id)">{{ t('Reject') }}</button>
                                    </div>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                </td>
                            </tr>
                            <tr v-if="filteredCommissions.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ hasActiveFilters ? t('No commissions match your filters.') : t('No commissions yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="commissions.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <Pagination :links="commissions.links" :from="commissions.from" :to="commissions.to" :total="commissions.total" />
                </div>
            </div>
        </div>
    

        <ActionConfirmModal :open="approveModal.open" :title="t('Approve commission?')" :message="t('Are you sure you want to approve this commission?')" :confirm-label="t('Approve')" :processing-label="t('Approving...')" :processing="approveModal.processing" variant="primary" @confirm="confirmApprove" @cancel="approveModal.open = false" />
        <ActionConfirmModal :open="rejectModal.open" :title="t('Reject commission?')" :message="t('Are you sure you want to reject this commission? This action cannot be undone.')" :confirm-label="t('Reject')" :processing="rejectModal.processing" variant="danger" @confirm="confirmReject" @cancel="rejectModal.open = false" />
    </AdminLayout>
</template>
