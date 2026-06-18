<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
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
    id: number
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
}

interface ConfirmDeactivateState {
    open: boolean
    processing: boolean
    subscription: SubscriptionItem | null
}

const props = defineProps<{
    subscriptions: SubscriptionResponse
    filters: Filters
    plans: PlanOption[]
    gateways: GatewayOption[]
}>()

const { t } = useTranslate()
const { formatDate, formatDateTime, formatRelative } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const statusFilter = ref(props.filters.status ? String(props.filters.status) : '')
const gatewayFilter = ref(props.filters.gateway ? String(props.filters.gateway) : '')
const planFilter = ref(props.filters.plan ? String(props.filters.plan) : '')
const confirmDeactivate = ref<ConfirmDeactivateState>({
    open: false,
    processing: false,
    subscription: null,
})

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

const filteredSubscriptions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.subscriptions.data
    }

    return props.subscriptions.data.filter((subscription) => {
        return [
            subscription.user?.name ?? '',
            subscription.user?.email ?? '',
            subscription.user?.ulid ?? '',
            subscription.plan?.name ?? '',
            subscription.gateway ?? '',
            subscription.gateway_subscription_id ?? '',
            subscription.status,
            subscription.billing_cycle ?? '',
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const totalSubscriptions = computed(() => props.subscriptions.total ?? props.subscriptions.data.length)
const activeSubscriptions = computed(() => props.subscriptions.data.filter((subscription) => subscription.status === 'active').length)
const trialingSubscriptions = computed(() => props.subscriptions.data.filter((subscription) => subscription.status === 'trialing').length)
const cancelledSubscriptions = computed(() => props.subscriptions.data.filter((subscription) => ['canceled', 'cancelled'].includes(subscription.status)).length)

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

    router.get(route('admin.subscriptions.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

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

const openDeactivateModal = (subscription: SubscriptionItem) => {
    confirmDeactivate.value = {
        open: true,
        processing: false,
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
        subscription: null,
    }
}

const deactivateSubscription = () => {
    const target = confirmDeactivate.value.subscription

    if (!target?.user?.ulid || confirmDeactivate.value.processing) {
        return
    }

    confirmDeactivate.value.processing = true

    router.post(route('admin.subscriptions.deactivate', target.user.ulid), {}, {
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
        <ActionConfirmModal
            :open="confirmDeactivate.open"
            :title="t('Cancel subscription')"
            :message="t('This will immediately cancel the subscription and remove the active plan for this user.')"
            :confirm-label="t('Cancel subscription')"
            :cancel-label="t('Cancel')"
            :processing-label="t('Cancelling...')"
            :processing="confirmDeactivate.processing"
            variant="danger"
            @cancel="closeDeactivateModal"
            @confirm="deactivateSubscription"
        />

        <div class="w-full px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ t('Subscriptions') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Review active and historical premium subscriptions in one table.') }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:min-w-[540px]">
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ t('Total') }}</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ totalSubscriptions }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ t('Active') }}</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ activeSubscriptions }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ t('Trialing') }}</p>
                        <p class="mt-2 text-2xl font-bold text-sky-600 dark:text-sky-400">{{ trialingSubscriptions }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ t('Cancelled') }}</p>
                        <p class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">{{ cancelledSubscriptions }}</p>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 dark:border-surface-800 xl:flex-row xl:items-center xl:justify-between">
                    <div class="relative w-full xl:max-w-md">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400 dark:text-gray-500"></i>
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('Search subscriptions...')"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white pl-10 pr-20 text-sm text-gray-700 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-primary-300 focus:ring-4 focus:ring-primary-100/60 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-primary-500/60 dark:focus:ring-primary-500/10"
                        />
                        <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                            <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border border-gray-200 bg-gray-50 px-2 text-[11px] font-semibold text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-500">/</span>
                        </div>
                    </div>

                    <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 xl:w-auto xl:min-w-[560px]">
                        <AppSelect
                            v-model="statusFilter"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="t('All Status')"
                            @update:model-value="applyFilters"
                        />
                        <AppSelect
                            v-model="gatewayFilter"
                            :options="gatewayOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="t('All Gateways')"
                            @update:model-value="applyFilters"
                        />
                        <AppSelect
                            v-model="planFilter"
                            :options="planOptions"
                            option-label="label"
                            option-value="value"
                            :placeholder="t('All Plans')"
                            @update:model-value="applyFilters"
                        />
                    </div>
                </div>

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
                                v-for="subscription in filteredSubscriptions"
                                :key="subscription.id"
                                class="transition hover:bg-primary-50/40 dark:hover:bg-white/[0.03]"
                            >
                                <td class="px-6 py-4 align-top">
                                    <div class="min-w-[220px]">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ subscription.user?.name ?? t('Deleted User') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ subscription.user?.email ?? t('No email available') }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="min-w-[150px]">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ subscription.plan?.name ?? t('Deleted Plan') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ billingCycleLabel(subscription.billing_cycle) }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="min-w-[130px]">
                                        <p class="font-medium text-gray-900 dark:text-white">
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
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ subscription.current_period_end ? formatRelative(subscription.current_period_end) : t('Not set') }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ subscription.current_period_end ? formatDateTime(subscription.current_period_end) : t('No renewal period available') }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Tooltip v-if="subscription.user?.ulid" :content="t('View user')" placement="top">
                                            <Link
                                                :href="route('admin.users.show', subscription.user.ulid)"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-950 dark:text-gray-300 dark:hover:border-primary-500/30 dark:hover:text-primary-300"
                                            >
                                                <i class="ti ti-user text-base"></i>
                                            </Link>
                                        </Tooltip>

                                        <Tooltip v-if="subscription.user?.ulid && canCancelSubscription(subscription.status)" :content="t('Cancel subscription')" placement="top">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/15"
                                                @click="openDeactivateModal(subscription)"
                                            >
                                                <i class="ti ti-user-off text-base"></i>
                                            </button>
                                        </Tooltip>

                                        <span
                                            v-if="!subscription.user?.ulid"
                                            class="inline-flex items-center rounded-lg border border-dashed border-gray-200 px-3 py-2 text-sm text-gray-400 dark:border-surface-700 dark:text-gray-500"
                                        >
                                            {{ t('Unavailable') }}
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredSubscriptions.length === 0">
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

                <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Showing :from to :to of :total subscriptions', {
                            from: props.subscriptions.from ?? 0,
                            to: props.subscriptions.to ?? filteredSubscriptions.length,
                            total: props.subscriptions.total ?? filteredSubscriptions.length,
                        }) }}
                    </p>
                    <Pagination :links="props.subscriptions.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
