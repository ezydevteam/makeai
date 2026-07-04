<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: UserDashboardLayout })

interface Payment {
    id: number
    ulid: string
    amount: number
    currency: string
    status: string
    type: string
    gateway: string
    plan_name: string | null
    created_at: string
}

interface Subscription {
    status: string | null
    ends_at: string | null
    trial_ends_at: string | null
    gateway: string | null
    billing_cycle: string | null
    can_cancel: boolean
    can_resume: boolean
    has_billing_portal: boolean
}

interface PlanData {
    name: string
    slug: string
    is_free: boolean
    features: string[] | null
    subscription_status: string
    subscription_ends_at: string | null
    trial_ends_at: string | null
    daily_limit: number | null
    monthly_limit: number | null
}

const props = defineProps<{
    payments: Payment[]
    plan: PlanData | null
    subscription: Subscription
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { formatNumber, formatCurrency } = useNumberFormat()
const page = usePage()

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const planStatusLabel = computed(() => {
    if (!props.plan) return t('No plan')
    if (props.plan.subscription_status === 'active') return t('Active membership')
    if (props.plan.subscription_status === 'trialing') return t('Trial access')
    if (props.plan.is_free || props.plan.subscription_status === 'none') return t('Starter access')
    return props.plan.name
})
const planStatusClass = computed(() => {
    if (!props.plan) return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'
    if (props.plan.subscription_status === 'active') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
    if (props.plan.subscription_status === 'trialing') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
    return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'
})
const planFeatures = computed(() => props.plan?.features?.slice(0, 4) ?? [])
const planTrialEndsAt = computed(() => props.plan?.trial_ends_at ?? props.subscription.trial_ends_at)
const planEndsAt = computed(() => props.plan?.subscription_ends_at ?? props.subscription.ends_at)

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        completed: t('Completed'),
        pending: t('Pending'),
        failed: t('Failed'),
        cancelled: t('Cancelled'),
        refunded: t('Refunded'),
    }
    return map[status] ?? status
}

const paymentTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        bank_transfer: t('Bank transfer'),
        credit_topup: t('Credit top-up'),
        subscription: t('Subscription'),
        payment: t('Payment'),
    }

    if (map[type]) {
        return map[type]
    }

    return type
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}

const statusClass = (status: string) => {
    const map: Record<string, string> = {
        completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
        refunded: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    }
    return map[status] ?? 'bg-gray-100 text-gray-700'
}

const cancelModalOpen = ref(false)
const cancelProcessing = ref(false)
const resumeProcessing = ref(false)

const isCancelledWithGrace = computed(() => props.subscription.can_resume)

const confirmCancel = () => {
    if (cancelProcessing.value) return

    cancelProcessing.value = true

    router.post(route('subscription.cancel'), {}, {
        preserveScroll: true,
        onFinish: () => {
            cancelProcessing.value = false
            cancelModalOpen.value = false
        },
    })
}

const resumeSubscription = () => {
    if (resumeProcessing.value) return

    resumeProcessing.value = true

    router.post(route('subscription.resume'), {}, {
        preserveScroll: true,
        onFinish: () => {
            resumeProcessing.value = false
        },
    })
}
</script>

<template>
    <Head :title="t('Billing')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Billing & Invoices') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('View your payment history and manage your subscription.') }}</p>
        </div>

        <!-- Not available -->
        <div v-if="!isProAvailable" class="rounded-2xl border border-gray-200 bg-white p-10 text-center shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">{{ t('Premium subscriptions are not available on this installation.') }}</p>
        </div>

        <template v-else>
        <!-- Subscription Status -->
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="bg-[linear-gradient(135deg,_rgba(31,117,254,0.08),_rgba(139,92,246,0.08))] px-6 py-5 dark:bg-surface-900">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Current plan') }}</p>
                        <h2 class="mt-2 break-words font-heading text-2xl font-bold text-gray-950 dark:!text-white">
                            {{ plan?.name ?? t('No active plan') }}
                        </h2>
                        <p class="mt-2 break-words text-sm text-gray-500 dark:text-gray-400">{{ t('Your current plan, limits, and renewal details.') }}</p>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/80 text-primary-700 shadow-sm dark:!bg-surface-900/80 dark:text-primary-500">
                        <i class="ti ti-credit-card text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div class="flex items-center gap-2">
                    <span :class="planStatusClass" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                        {{ planStatusLabel }}
                    </span>
                </div>

                <p v-if="planTrialEndsAt" class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Trial ends :date', { date: formatDate(planTrialEndsAt) }) }}
                </p>
                <p v-else-if="planEndsAt" class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Access reserved through :date', { date: formatDate(planEndsAt) }) }}
                </p>

                <div v-if="planFeatures.length" class="space-y-3">
                    <div v-for="feature in planFeatures" :key="feature" class="flex items-start gap-3 rounded-2xl border border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-surface-800 dark:bg-surface-950/60">
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <i class="ti ti-check text-sm"></i>
                        </div>
                        <p class="break-words text-sm text-gray-600 dark:text-gray-300">{{ feature }}</p>
                    </div>
                </div>

                <div v-if="plan?.daily_limit || plan?.monthly_limit" class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-950/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Daily limit') }}</p>
                        <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ formatNumber(plan?.daily_limit ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-950/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Monthly limit') }}</p>
                        <p class="mt-2 font-heading text-2xl font-bold text-gray-950 dark:!text-white">{{ formatNumber(plan?.monthly_limit ?? 0) }}</p>
                    </div>
                </div>

                <div v-if="isCancelledWithGrace" class="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                        {{ t('Your subscription is cancelled.') }}
                        <template v-if="planEndsAt">{{ t('Access remains until :date.', { date: formatDate(planEndsAt) }) }}</template>
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <Link :href="route('pricing')" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300" :class="{ 'sm:col-span-2': !subscription.can_cancel && !subscription.can_resume && !subscription.has_billing_portal }">
                        <i class="ti ti-arrow-up"></i>
                        {{ t('Manage Plan') }}
                    </Link>

                    <a
                        v-if="subscription.has_billing_portal"
                        :href="route('billing.portal')"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                    >
                        <i class="ti ti-credit-card"></i>
                        {{ t('Billing Portal') }}
                    </a>

                    <button
                        v-if="subscription.can_cancel"
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/15"
                        @click="cancelModalOpen = true"
                    >
                        <i class="ti ti-circle-x"></i>
                        {{ t('Cancel Subscription') }}
                    </button>

                    <button
                        v-if="subscription.can_resume"
                        type="button"
                        :disabled="resumeProcessing"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-60 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/15"
                        @click="resumeSubscription"
                    >
                        <i class="ti ti-refresh"></i>
                        {{ resumeProcessing ? t('Resuming...') : t('Resume Subscription') }}
                    </button>
                </div>
            </div>
        </section>

        <ActionConfirmModal
            :open="cancelModalOpen"
            :title="t('Cancel subscription')"
            :message="t('Your subscription will not renew. You keep full access until the end of the current billing period.')"
            :confirm-label="t('Cancel subscription')"
            :cancel-label="t('Keep subscription')"
            :processing-label="t('Cancelling...')"
            :processing="cancelProcessing"
            variant="danger"
            @cancel="cancelModalOpen = false"
            @confirm="confirmCancel"
        />

        <!-- Payment History -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Payment History') }}</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <div v-if="payments.length === 0" class="px-6 py-10 text-center text-sm text-gray-500">{{ t('No payments yet.') }}</div>
                <div v-for="payment in payments" :key="payment.id" class="flex items-center justify-between px-6 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ payment.plan_name || t('Payment') }}
                        </p>
                        <div class="flex items-center gap-2 mt-1">
                            <span :class="statusClass(payment.status)" class="inline-flex items-center rounded-full px-2 py-px text-[10px] font-semibold uppercase">
                                {{ statusLabel(payment.status) }}
                            </span>
                            <span class="text-xs text-gray-400">{{ formatDate(payment.created_at) }}</span>
                            <span class="text-xs text-gray-400">· {{ payment.gateway }}</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-3">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ formatCurrency(payment.amount, payment.currency) }}
                        </div>
                        <div class="text-xs text-gray-400">{{ paymentTypeLabel(payment.type) }}</div>
                    </div>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>
