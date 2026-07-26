<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
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

interface PlanOption {
    id: number
    name: string
}

interface GatewayOption {
    value: string
    label: string
}

interface SubscriptionUser {
    ulid: string
    name: string
    email: string
}

interface SubscriptionPlan {
    id: number
    name: string
}

interface SubscriptionItem {
    id: number | string
    user_id: number | null
    plan_id: number | null
    billing_cycle: string | null
    status: string
    gateway: string | null
    gateway_subscription_id: string | null
    amount: string | number | null
    currency: string | null
    trial_ends_at: string | null
    current_period_start: string | null
    current_period_end: string | null
    cancelled_at: string | null
    created_at: string
    user: SubscriptionUser | null
    plan: SubscriptionPlan | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface SubscriptionResponse {
    data: SubscriptionItem[]
    links: PaginationLink[]
    from?: number
    to?: number
    total?: number
}

interface Filters {
    status?: string | null
    gateway?: string | null
    plan?: string | number | null
    search?: string | null
}

interface ConfirmDeactivateState {
    open: boolean
    processing: boolean
    mode: 'immediate' | 'period_end'
    reason: string
    subscription: SubscriptionItem | null
}

const props = defineProps<{
    subscriptions: SubscriptionResponse
    filters: Filters
    plans: PlanOption[]
    gateways: GatewayOption[]
    stats: {
        total: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        active: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        trialing: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        cancelled: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { formatDate, formatDateTime, formatRelative } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const searchQuery = ref(props.filters.search ? String(props.filters.search) : '')
const searchInputRef = ref<HTMLInputElement | null>(null)
const statusFilter = ref(props.filters.status ? String(props.filters.status) : '')
const gatewayFilter = ref(props.filters.gateway ? String(props.filters.gateway) : '')
const planFilter = ref(props.filters.plan ? String(props.filters.plan) : '')

const activeFiltersCount = computed(() => {
    return [statusFilter.value, gatewayFilter.value, planFilter.value].filter(Boolean).length
})

const resetFilters = () => {
    statusFilter.value = ''
    gatewayFilter.value = ''
    planFilter.value = ''
    applyFilters()
}
const confirmDeactivate = ref<ConfirmDeactivateState>({
    open: false,
    processing: false,
    mode: 'immediate',
    reason: '',
    subscription: null,
})

const grantModalOpen = ref(false)
const grantForm = useForm({
    email: '',
    plan_id: '',
    billing_cycle: 'monthly',
})

const grantPlanOptions = computed(() => props.plans.map((plan) => ({
    value: String(plan.id),
    label: plan.name,
})))

const billingCycleOptions = computed(() => [
    { value: 'monthly', label: t('Monthly') },
    { value: 'yearly', label: t('Yearly') },
    { value: 'lifetime', label: t('Lifetime') },
])

const openGrantModal = () => {
    grantForm.reset()
    grantForm.clearErrors()
    grantModalOpen.value = true
}

const closeGrantModal = () => {
    if (!grantForm.processing) {
        grantModalOpen.value = false
    }
}

const submitGrant = () => {
    grantForm
        .transform((data) => ({ ...data, plan_id: data.plan_id ? Number(data.plan_id) : null }))
        .post(route('admin.subscriptions.grant'), {
            preserveScroll: true,
            onSuccess: () => {
                grantModalOpen.value = false
            },
        })
}

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: 'active', label: t('Active') },
    { value: 'trialing', label: t('Trialing') },
    { value: 'cancelled', label: t('Cancelled') },
    { value: 'past_due', label: t('Past Due') },
    { value: 'expired', label: t('Expired') },
])

const gatewayOptions = computed(() => [
    { value: '', label: t('All Gateways') },
    ...props.gateways.map((gateway) => ({
        value: gateway.value,
        label: gateway.label,
    })),
])

const planOptions = computed(() => [
    { value: '', label: t('All Plans') },
    ...props.plans.map((plan) => ({
        value: String(plan.id),
        label: plan.name,
    })),
])

const canCancelSubscription = (status: string) => ['active', 'trialing', 'past_due'].includes(status)

const applyFilters = () => {
    const params: Record<string, string> = {}

    if (statusFilter.value) {
        params.status = statusFilter.value
    }

    if (gatewayFilter.value) {
        params.gateway = gatewayFilter.value
    }

    if (planFilter.value) {
        params.plan = planFilter.value
    }

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }

    router.get(route('admin.subscriptions.index'), params, {
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

const clearSearchAndFilters = () => {
    searchQuery.value = ''

    const hadServerFilters = Boolean(statusFilter.value || gatewayFilter.value || planFilter.value)

    statusFilter.value = ''
    gatewayFilter.value = ''
    planFilter.value = ''

    if (hadServerFilters) {
        applyFilters()
    }
}

const focusSearchOnSlash = (event: KeyboardEvent) => {
    if (event.key !== '/' || event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    const target = event.target as HTMLElement | null

    if (target) {
        const tagName = target.tagName
        const isTypingContext = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)

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

    if (!searchQuery.value && !statusFilter.value && !gatewayFilter.value && !planFilter.value) {
        return
    }

    event.preventDefault()
    clearSearchAndFilters()
}

const statusLabel = (status: string) => {
    switch (status) {
        case 'active':
            return t('Active')
        case 'trialing':
            return t('Trialing')
        case 'canceled':
            return t('Canceled')
        case 'cancelled':
            return t('Cancelled')
        case 'past_due':
            return t('Past Due')
        case 'expired':
            return t('Expired')
        default:
            return status.replace(/[_-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
    }
}

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
        case 'trialing':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20'
        case 'canceled':
        case 'cancelled':
            return 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20'
        case 'past_due':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'
        case 'expired':
            return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'
        default:
            return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'
    }
}

const billingCycleLabel = (billingCycle: string | null) => {
    if (!billingCycle) {
        return t('Not set')
    }

    switch (billingCycle) {
        case 'monthly':
            return t('Monthly')
        case 'yearly':
            return t('Yearly')
        case 'lifetime':
            return t('Lifetime')
        default:
            return billingCycle.replace(/[_-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
    }
}

const gatewayLabel = (gateway: string | null) => {
    if (!gateway) {
        return t('Unknown')
    }

    return gateway.replace(/[_-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

// Read-only details view. Every field is already on the row, so opening this needs no
// extra request — synthetic rows (a user holding a plan with no billing record) simply
// have nulls, which render as an explicit "Not available" rather than a blank cell.
const detailsSubscription = ref<SubscriptionItem | null>(null)

const openDetailsModal = (subscription: SubscriptionItem) => {
    detailsSubscription.value = subscription
}

const closeDetailsModal = () => {
    detailsSubscription.value = null
}

const isSyntheticRow = (subscription: SubscriptionItem) => typeof subscription.id === 'string'
    && String(subscription.id).startsWith('user-')

const openDeactivateModal = (subscription: SubscriptionItem) => {
    confirmDeactivate.value = {
        open: true,
        processing: false,
        mode: 'immediate',
        reason: '',
        subscription,
    }
}

const closeDeactivateModal = () => {
    if (confirmDeactivate.value.processing) {
        return
    }

    confirmDeactivate.value = {
        open: false,
        processing: false,
        mode: 'immediate',
        reason: '',
        subscription: null,
    }
}

const deactivateSubscription = () => {
    const target = confirmDeactivate.value.subscription

    if (!target?.user?.ulid || confirmDeactivate.value.processing) {
        return
    }

    confirmDeactivate.value.processing = true

    const reason = confirmDeactivate.value.mode === 'immediate' ? (confirmDeactivate.value.reason || null) : null

    router.post(route('admin.subscriptions.deactivate', target.user.ulid), { mode: confirmDeactivate.value.mode, reason }, {
        preserveScroll: true,
        onFinish: () => {
            confirmDeactivate.value.processing = false
            closeDeactivateModal()
        },
    })
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
    <Head :title="t('Subscriptions')" />

    <AdminLayout>
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex-1 space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ t('Subscriptions') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Review active and historical premium subscriptions in one table.') }}
                    </p>
                </div>
                <div class="shrink-0 flex items-center justify-center gap-2">
                    <button
                        type="button"
                        class="btn-primary-admin inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition w-full sm:w-auto"
                        @click="openGrantModal"
                    >
                        <i class="ti ti-gift text-base"></i>
                        {{ t('Grant subscription') }}
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    :title="t('Total Subscriptions')"
                    :value="stats.total.value"
                    :comparison="stats.total.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.total.comparison.type"
                    color="primary"
                >
                    <template #icon>
                        <i class="ti ti-activity text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Active Subscriptions')"
                    :value="stats.active.value"
                    :comparison="stats.active.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.active.comparison.type"
                    color="success"
                >
                    <template #icon>
                        <i class="ti ti-checkbox text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Trialing Subscriptions')"
                    :value="stats.trialing.value"
                    :comparison="stats.trialing.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.trialing.comparison.type"
                    color="accent"
                >
                    <template #icon>
                        <i class="ti ti-player-play text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Cancelled Subscriptions')"
                    :value="stats.cancelled.value"
                    :comparison="stats.cancelled.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.cancelled.comparison.type"
                    color="danger"
                >
                    <template #icon>
                        <i class="ti ti-circle-x text-lg"></i>
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
                                    ref="searchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    :placeholder="t('Search subscriptions...')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
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
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ t('Plan') }}</label>
                                        <AppSelect
                                            v-model="planFilter"
                                            :options="planOptions"
                                            option-label="label"
                                            option-value="value"
                                            :placeholder="t('All Plans')"
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
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-950/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">{{ t('Subscriber') }}</th>
                                    <th class="px-6 py-3">{{ t('Plan') }}</th>
                                    <th class="px-6 py-3">{{ t('Amount') }}</th>
                                    <th class="px-6 py-3">{{ t('Status') }}</th>
                                    <th class="px-6 py-3">{{ t('Period End') }}</th>
                                    <th class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                                <tr
                                    v-for="subscription in subscriptions.data"
                                    :key="subscription.id"
                                    class="transition hover:bg-primary-50/40 dark:hover:bg-white/[0.03]"
                                >
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[220px]">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ subscription.user?.name ?? t('Deleted User') }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ subscription.user?.email ?? t('No email available') }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[150px]">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ subscription.plan?.name ?? t('Deleted Plan') }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ billingCycleLabel(subscription.billing_cycle) }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[130px]">
                                            <p class="font-semibold text-gray-900 dark:text-white">
                                                {{ subscription.amount !== null && subscription.amount !== undefined && subscription.amount !== ''
                                                    ? formatCurrency(Number(subscription.amount), subscription.currency ?? undefined)
                                                    : t('Not available') }}
                                            </p>
                                            <p class="mt-1 text-xs uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                                {{ subscription.currency ?? t('Manual') }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="statusBadgeClass(subscription.status)"
                                        >
                                            {{ statusLabel(subscription.status) }}
                                        </span>
                                        <p
                                            v-if="subscription.cancelled_at"
                                            class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ t('Cancelled :date', { date: formatDate(subscription.cancelled_at) }) }}
                                        </p>
                                        <p
                                            v-else-if="subscription.trial_ends_at"
                                            class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ t('Trial ends :date', { date: formatDate(subscription.trial_ends_at) }) }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="min-w-[170px]">
                                            <p class="font-semibold text-gray-900 dark:text-white">
                                                {{ subscription.current_period_end ? formatRelative(subscription.current_period_end) : t('Not set') }}
                                            </p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ subscription.current_period_end ? formatDateTime(subscription.current_period_end) : t('No renewal period available') }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top text-end">
                                        <TableActionMenu v-if="subscription.user?.ulid">
                                            <template #default="{ close }">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                                    @click="openDetailsModal(subscription); close()"
                                                >
                                                    <i class="ti ti-eye text-base"></i>
                                                    {{ t('View Details') }}
                                                </button>
                                                <Link
                                                    :href="route('admin.users.show', subscription.user.ulid)"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-white"
                                                    @click="close"
                                                >
                                                    <i class="ti ti-user text-base"></i>
                                                    {{ t('View User') }}
                                                </Link>
                                                <button
                                                    v-if="canCancelSubscription(subscription.status)"
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                    @click="openDeactivateModal(subscription); close()"
                                                >
                                                    <i class="ti ti-user-off text-base"></i>
                                                    {{ t('Cancel Subscription') }}
                                                </button>
                                            </template>
                                        </TableActionMenu>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-lg border border-dashed border-gray-200 px-3 py-2 text-sm text-gray-400 dark:border-surface-700 dark:text-gray-500"
                                        >
                                            {{ t('Unavailable') }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="subscriptions.data.length === 0">
                                    <td colspan="6" class="px-6 py-16 text-center">
                                        <div class="mx-auto max-w-sm">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                                <i class="ti ti-receipt-2 text-xl"></i>
                                            </div>
                                            <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">{{ t('No subscriptions found') }}</p>
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
                        v-if="props.subscriptions.links.length > 3"
                        class="border-t border-gray-100 px-5 py-4 dark:border-surface-800"
                    >
                        <Pagination
                            :links="props.subscriptions.links"
                            :from="props.subscriptions.from ?? 0"
                            :to="props.subscriptions.to ?? subscriptions.data.length"
                            :total="props.subscriptions.total ?? subscriptions.data.length"
                        />
                </div>
            </div>
        </div>

        <AppModal
            :open="detailsSubscription !== null"
            :title="t('Subscription details')"
            :subtitle="detailsSubscription?.user?.email ?? ''"
            cancel-text="Close"
            @close="closeDetailsModal"
        >
            <div v-if="detailsSubscription" class="space-y-5">
                <!-- Customer -->
                <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/40">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                            {{ detailsSubscription.user?.name ?? t('Unknown user') }}
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ detailsSubscription.user?.email ?? '—' }}
                        </p>
                    </div>
                    <Link
                        v-if="detailsSubscription.user?.ulid"
                        :href="route('admin.users.show', detailsSubscription.user.ulid)"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                    >
                        <i class="ti ti-user text-sm"></i>
                        {{ t('View User') }}
                    </Link>
                </div>

                <!-- A synthetic row has no billing record behind it; say so explicitly rather
                     than showing a screen of "Not available" with no explanation. -->
                <div
                    v-if="isSyntheticRow(detailsSubscription)"
                    class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <i class="ti ti-alert-triangle mt-0.5 text-sm"></i>
                    <span>{{ t('This user holds a plan without a gateway billing record — typically an admin grant or a legacy import. Billing fields below are unavailable.') }}</span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Plan') }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ detailsSubscription.plan?.name ?? t('Deleted Plan') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Billing Cycle') }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ billingCycleLabel(detailsSubscription.billing_cycle) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Amount') }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ detailsSubscription.amount !== null && detailsSubscription.amount !== undefined && detailsSubscription.amount !== ''
                                ? formatCurrency(Number(detailsSubscription.amount), detailsSubscription.currency ?? undefined)
                                : t('Not available') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Status') }}</p>
                        <span
                            class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="statusBadgeClass(detailsSubscription.status)"
                        >
                            {{ statusLabel(detailsSubscription.status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Gateway') }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ gatewayLabel(detailsSubscription.gateway) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Gateway Subscription ID') }}</p>
                        <p class="mt-1 break-all font-mono text-xs text-gray-900 dark:text-white">
                            {{ detailsSubscription.gateway_subscription_id ?? t('Not available') }}
                        </p>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="border-t border-gray-100 pt-4 dark:border-surface-800">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Timeline') }}</p>
                    <dl class="space-y-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Started') }}</dt>
                            <dd class="text-right text-xs font-medium text-gray-900 dark:text-white">
                                {{ detailsSubscription.created_at ? formatDateTime(detailsSubscription.created_at) : t('Not set') }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Current Period Start') }}</dt>
                            <dd class="text-right text-xs font-medium text-gray-900 dark:text-white">
                                {{ detailsSubscription.current_period_start ? formatDateTime(detailsSubscription.current_period_start) : t('Not set') }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Renews / Expires') }}</dt>
                            <dd class="text-right">
                                <span class="block text-xs font-medium text-gray-900 dark:text-white">
                                    {{ detailsSubscription.current_period_end ? formatDateTime(detailsSubscription.current_period_end) : t('Not set') }}
                                </span>
                                <span v-if="detailsSubscription.current_period_end" class="block text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ formatRelative(detailsSubscription.current_period_end) }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="detailsSubscription.trial_ends_at" class="flex items-start justify-between gap-3">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Trial Ends') }}</dt>
                            <dd class="text-right text-xs font-medium text-gray-900 dark:text-white">
                                {{ formatDateTime(detailsSubscription.trial_ends_at) }}
                            </dd>
                        </div>
                        <div v-if="detailsSubscription.cancelled_at" class="flex items-start justify-between gap-3">
                            <dt class="text-xs font-medium text-red-500 dark:text-red-400">{{ t('Cancelled') }}</dt>
                            <dd class="text-right text-xs font-medium text-red-600 dark:text-red-300">
                                {{ formatDateTime(detailsSubscription.cancelled_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </AppModal>

        <AppModal
            :open="confirmDeactivate.open"
            :title="t('Cancel subscription')"
            :subtitle="t('Choose how this subscription should be cancelled.')"
            :confirm-text="t('Cancel subscription')"
            :confirm-loading="confirmDeactivate.processing"
            confirm-variant="delete"
            has-form
            @close="closeDeactivateModal"
            @submit="deactivateSubscription"
        >
            <div class="space-y-4">
                <div class="space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition" :class="confirmDeactivate.mode === 'period_end' ? 'border-primary-300 bg-primary-50/50 dark:border-primary-500/40 dark:bg-primary-500/10' : 'border-gray-200 dark:border-surface-700'">
                        <input v-model="confirmDeactivate.mode" type="radio" value="period_end" class="mt-1" />
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('At period end') }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ t('Stops future billing. The user keeps access until the paid period ends.') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition" :class="confirmDeactivate.mode === 'immediate' ? 'border-red-300 bg-red-50/50 dark:border-red-500/40 dark:bg-red-500/10' : 'border-gray-200 dark:border-surface-700'">
                        <input v-model="confirmDeactivate.mode" type="radio" value="immediate" class="mt-1" />
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t('Immediately') }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ t('Cancels now and removes the active plan right away.') }}</span>
                        </span>
                    </label>
                </div>
                <div v-if="confirmDeactivate.mode === 'immediate'" class="space-y-1.5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ t('Reason') }} <span class="font-normal text-gray-400">({{ t('optional') }})</span>
                    </label>
                    <textarea
                        v-model="confirmDeactivate.reason"
                        rows="2"
                        maxlength="500"
                        :placeholder="t('Shown to the user in their cancellation notice, and kept in the logs.')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    ></textarea>
                </div>
            </div>
        </AppModal>

        <AppModal
            :open="grantModalOpen"
            :title="t('Grant subscription')"
            :subtitle="t('Manually assign a plan to a user without a payment (support cases, offline sales, giveaways).')"
            :confirm-text="t('Grant subscription')"
            :confirm-loading="grantForm.processing"
            confirm-variant="admin"
            has-form
            @close="closeGrantModal"
            @submit="submitGrant"
            maxWidth="max-w-lg"
        >
            <div class="space-y-4">
                <div class="space-y-1.5">
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('User email') }}</span>
                    <input
                        v-model="grantForm.email"
                        type="email"
                        :placeholder="t('user@example.com')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                     />
                    <span v-if="grantForm.errors.email" class="mt-1 block text-xs text-red-500">{{ grantForm.errors.email }}</span>
                </div>
                <div class="space-y-1.5">
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Plan') }}</span>
                    <AppSelect
                        v-model="grantForm.plan_id"
                        :options="grantPlanOptions"
                        option-label="label"
                        option-value="value"
                        :placeholder="t('Select plan')"
                    />
                    <span v-if="grantForm.errors.plan_id" class="mt-1 block text-xs text-red-500">{{ grantForm.errors.plan_id }}</span>
                </div>
                <div class="space-y-1.5">
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Billing cycle') }}</span>
                    <AppSelect
                        v-model="grantForm.billing_cycle"
                        :options="billingCycleOptions"
                        option-label="label"
                        option-value="value"
                    />
                    <span v-if="grantForm.errors.billing_cycle" class="mt-1 block text-xs text-red-500">{{ grantForm.errors.billing_cycle }}</span>
                </div>
            </div>
        </AppModal>
        </div>
    </AdminLayout>
</template>
