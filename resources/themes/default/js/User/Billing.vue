<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
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
    is_managed_externally: boolean
    is_one_time: boolean
    scheduled_plan_name: string | null
    scheduled_change_at: string | null
}

interface PlanData {
    name: string
    slug: string
    is_free: boolean
    features: string[] | null
    subscription_status: string
    subscription_ends_at: string | null
    trial_ends_at: string | null
}

const props = defineProps<{
    payments: Payment[]
    plan: PlanData | null
    subscription: Subscription
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { formatCurrency } = useNumberFormat()
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
/**
 * Is there actually a paid plan to manage?
 *
 * `plan` is null for an account that has never subscribed, and free-tier users carry a
 * plan row with is_free set — neither has anything to manage. The action below used to
 * read "Manage Plan" for all three cases, which promises a subscription screen to someone
 * who does not have a subscription. It points at pricing either way, so the fix is the
 * label, not the link: hiding it would leave the billing page with no route to a plan at
 * all, which is the one thing these users are here to do.
 */
const hasManageablePlan = computed(() => {
    const plan = props.plan

    if (!plan || plan.is_free) return false

    return ['active', 'trialing', 'past_due', 'cancelled'].includes(plan.subscription_status)
})

const planFeatures = computed(() => props.plan?.features ?? [])
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

const cancelScheduledProcessing = ref(false)
const hasScheduledChange = computed(() => !!props.subscription.scheduled_change_at && !!props.subscription.scheduled_plan_name)
const scheduledDateLabel = computed(() => {
    const iso = props.subscription.scheduled_change_at
    if (!iso) return ''
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
})

// One-time payers renew by re-purchasing their current plan/cycle.
const renewUrl = computed(() => {
    const params = new URLSearchParams({ plan: props.plan?.slug ?? '', billing: props.subscription.billing_cycle ?? 'monthly' })
    return `/checkout?${params.toString()}`
})

/**
 * Only offer renewal once it is nearly due — the last 7 days of access.
 *
 * The button sat in the banner from the moment a one-time plan was bought, so someone
 * eleven months into an annual plan was being invited to pay for it again. Buying early
 * does not extend the existing period either, so the offer was not just noisy but a way
 * to lose money.
 *
 * Also shown once the date has passed: an expired plan is exactly when renewing is the
 * point. A missing end date means nothing to count down to, so it stays hidden.
 */
const RENEWAL_WINDOW_DAYS = 7

const canRenewNow = computed(() => {
    const endsAt = planEndsAt.value

    if (!endsAt || !props.plan?.slug) return false

    const endsAtMs = new Date(endsAt).getTime()

    if (Number.isNaN(endsAtMs)) return false

    const daysRemaining = (endsAtMs - Date.now()) / 86_400_000

    return daysRemaining <= RENEWAL_WINDOW_DAYS
})

const cancelScheduledChange = () => {
    if (cancelScheduledProcessing.value) return

    cancelScheduledProcessing.value = true

    router.post(route('subscription.cancel-scheduled'), {}, {
        preserveScroll: true,
        onFinish: () => {
            cancelScheduledProcessing.value = false
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
        <!-- Banners / Alerts at the top (full width) -->
        <div v-if="isCancelledWithGrace || hasScheduledChange || subscription.is_managed_externally || subscription.is_one_time" class="space-y-4 mb-6">
            <!-- Cancelled with Grace -->
            <div v-if="isCancelledWithGrace" class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                    {{ t('Your subscription is cancelled.') }}
                    <template v-if="planEndsAt">{{ t('Access remains until :date.', { date: formatDate(planEndsAt) }) }}</template>
                </p>
            </div>

            <!-- Scheduled Change -->
            <div v-if="hasScheduledChange" class="rounded-2xl border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-500/20 dark:bg-sky-500/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm font-medium text-sky-800 dark:text-sky-300">
                    {{ t('You will move to the :plan plan on :date. Your current features stay active until then.', { plan: subscription.scheduled_plan_name ?? '', date: scheduledDateLabel }) }}
                </p>
                <button
                    type="button"
                    :disabled="cancelScheduledProcessing"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:opacity-60 dark:border-sky-500/30 dark:bg-surface-900 dark:text-sky-200 dark:hover:bg-sky-500/15"
                    @click="cancelScheduledChange"
                >
                    <i class="ti ti-x"></i>
                    {{ cancelScheduledProcessing ? t('Cancelling...') : t('Cancel scheduled change') }}
                </button>
            </div>

            <!-- Externally Managed -->
            <div v-if="subscription.is_managed_externally" class="flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-500/20 dark:bg-sky-500/10">
                <i class="ti ti-shield-check mt-0.5 text-sky-600 dark:text-sky-400"></i>
                <p class="text-sm font-medium text-sky-800 dark:text-sky-300">
                    {{ t('This plan was granted by an administrator, so there is no billing to cancel. Contact support if you need changes.') }}
                </p>
            </div>

            <!-- One-time Paid -->
            <div v-if="subscription.is_one_time" class="rounded-2xl border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-500/20 dark:bg-sky-500/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="flex items-start gap-3 text-sm font-medium text-sky-800 dark:text-sky-300">
                    <i class="ti ti-circle-check mt-0.5 shrink-0 text-sky-600 dark:text-sky-400"></i>
                    <span>
                        <template v-if="planEndsAt">{{ t('This was a one-time payment — you will not be charged automatically. Your access stays active until :date.', { date: formatDate(planEndsAt) }) }}</template>
                        <template v-else>{{ t('This was a one-time payment — you will not be charged automatically.') }}</template>
                    </span>
                </p>
                <Link
                    v-if="canRenewNow"
                    :href="renewUrl"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-sky-300 bg-white px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 dark:border-sky-500/30 dark:bg-surface-900 dark:text-sky-200 dark:hover:bg-sky-500/15"
                >
                    <i class="ti ti-refresh"></i>
                    {{ t('Renew Now') }}
                </Link>
            </div>
        </div>

        <!--
            Nothing to report yet. Both cards below describe a subscription — its features,
            its status, its cancel and resume actions — so for an account without one they
            were two boxes of "No active plan" and "No features details available", which
            reads as something failing to load rather than as a state. One card that says so
            plainly, with the only action that applies.
        -->
        <div v-if="!hasManageablePlan" class="rounded-2xl border border-gray-200 bg-white p-10 text-center shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-300">
                <i class="ti ti-sparkles text-2xl"></i>
            </div>

            <h2 class="font-heading text-xl font-extrabold text-gray-900 dark:text-white">{{ t('You are not on a plan yet') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                {{ t('Choose a plan to unlock more credits and features. Your payment history stays here either way.') }}
            </p>

            <Link :href="route('pricing')" class="mt-6 inline-flex items-center justify-center gap-2 rounded-xl btn-primary px-6 shadow-lg shadow-primary-600/20 transition">
                <i class="ti ti-sparkles text-base"></i>
                {{ t('View Plans') }}
            </Link>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- Left Column: Current Plan, Features, Actions, History (span 2) -->
            <div class="lg:col-span-2 space-y-6">
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <div class="px-6 py-3 border-b border-gray-100 dark:border-surface-800 bg-white dark:bg-surface-900">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">{{ t('Your Current plan') }}</p>
                        <h2 class="mt-1 break-words font-heading text-2xl font-extrabold text-gray-950 dark:!text-white">
                            {{ plan?.name ?? t('No active plan') }}
                        </h2>
                    </div>

                    <div class="px-6 py-4 space-y-6">
                        <div>
                            <h3 class="text-xs font-medium uppercase tracking-wider !text-gray-500 mb-4">{{ t('Included Features') }}</h3>

                            <div v-if="planFeatures.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="feature in planFeatures" :key="feature" class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/60 px-4 py-2 dark:border-surface-800 dark:bg-surface-950/40">
                                    <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                        <i class="ti ti-check text-sm"></i>
                                    </div>
                                    <p class="break-words text-sm font-medium text-gray-700 dark:text-gray-300">{{ feature }}</p>
                                </div>
                            </div>

                            <div v-else class="text-center py-8 rounded-2xl border border-dashed border-gray-200 dark:border-surface-800">
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('No features details available for this plan.') }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right Column: Sidebar / Status & Limits (span 1) -->
            <div class="space-y-6">
                <!-- Status & Limits Card -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4">{{ t('Subscription Status') }}</h3>

                    <div class="space-y-4">
                        <div>
                            <span :class="planStatusClass" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                                {{ planStatusLabel }}
                            </span>
                        </div>

                        <div class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                            <p v-if="planTrialEndsAt" class="flex items-center gap-2">
                                <i class="ti ti-clock text-base text-gray-400"></i>
                                <span>{{ t('Trial ends :date', { date: formatDate(planTrialEndsAt) }) }}</span>
                            </p>
                            <p v-else-if="planEndsAt" class="flex items-center gap-2">
                                <i class="ti ti-calendar text-base text-gray-400"></i>
                                <span>{{ t('Access reserved through :date', { date: formatDate(planEndsAt) }) }}</span>
                            </p>
                            <p v-if="subscription.billing_cycle" class="flex items-center gap-2 uppercase text-xs tracking-wider font-semibold text-gray-400">
                                <i class="ti ti-repeat text-base text-gray-400"></i>
                                <span>{{ subscription.billing_cycle }} {{ t('billing') }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Billing Actions inside Plan Card -->
                    <div class="pt-4 mt-6 border-t border-gray-100 dark:border-surface-800 bg-gray-50/40 dark:bg-surface-950/20">
                        <div class="flex flex-wrap items-center gap-3">
                            <Link :href="route('pricing')" class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:!border-primary-200 hover:!bg-primary-50 hover:!text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300">
                                <i :class="hasManageablePlan ? 'ti ti-arrow-up' : 'ti ti-sparkles'" class="text-base"></i>
                                {{ hasManageablePlan ? t('Manage Plan') : t('View Plans') }}
                            </Link>

                            <a
                                v-if="subscription.has_billing_portal"
                                :href="route('billing.portal')"
                                class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:!border-primary-200 hover:!bg-primary-50 hover:!text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
                            >
                                <i class="ti ti-credit-card text-base"></i>
                                {{ t('Billing Portal') }}
                            </a>

                            <button
                                v-if="subscription.can_cancel"
                                type="button"
                                class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:!border-red-200 hover:!bg-red-50 hover:!text-red-700 dark:hover:!border-red-900/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                @click="cancelModalOpen = true"
                            >
                                <i class="ti ti-circle-x text-base"></i>
                                {{ t('Cancel Subscription') }}
                            </button>

                            <button
                                v-if="subscription.can_resume"
                                type="button"
                                :disabled="resumeProcessing"
                                class="w-full flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:!bg-emerald-100 disabled:opacity-60 dark:!border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/15"
                                @click="resumeSubscription"
                            >
                                <i class="ti ti-refresh text-base"></i>
                                {{ resumeProcessing ? t('Resuming...') : t('Resume Subscription') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History — full width below the two-column grid, so the columns have room
             to breathe rather than being squeezed into the 2/3 left column. -->
        <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100/80 px-6 py-4 dark:border-surface-800">
                <h3 class="font-bold text-gray-900 dark:text-white">{{ t('Payment History') }}</h3>
            </div>

            <div v-if="payments.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ t('No payments yet.') }}
            </div>

            <!-- Horizontally scrollable rather than wrapping: six columns do not fit a phone,
                 and a wrapped table row is harder to read than a scrolled one. -->
            <div v-else class="min-w-0 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50/80 text-xs uppercase tracking-wide text-gray-700 dark:bg-surface-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">{{ t('Title') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ t('Gateway') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ t('Amount') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ t('Status') }}</th>
                            <th class="px-4 py-3 text-center font-semibold">{{ t('Date') }}</th>
                            <th class="px-6 py-3 text-center font-semibold">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr
                            v-for="payment in payments"
                            :key="payment.id"
                            class="transition hover:bg-gray-50/60 dark:hover:bg-surface-800/40"
                        >
                            <td class="px-6 py-4">
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ payment.plan_name || t('Payment') }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ paymentTypeLabel(payment.type) }}
                                </p>
                            </td>
                            <td class="px-4 py-4 capitalize text-gray-600 dark:text-gray-300">
                                {{ payment.gateway?.replace('_', ' ') || '—' }}
                            </td>
                            <td class="px-4 py-4 text-center font-bold text-gray-900 dark:text-white">
                                {{ formatCurrency(payment.amount, payment.currency) }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span :class="statusClass(payment.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase">
                                    {{ statusLabel(payment.status) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-center text-gray-500 dark:text-gray-400">
                                {{ formatDate(payment.created_at) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <!-- Only settled payments get an invoice: a failed charge
                                     collected nothing, and a pending one has not cleared yet,
                                     so neither has an amount to invoice for. -->
                                <Tooltip
                                    v-if="!['failed', 'pending'].includes(payment.status)"
                                    :content="t('Download invoice')"
                                    placement="top"
                                >
                                    <a
                                        :href="route('user.dashboard.billing.invoice', payment.ulid)"
                                        :aria-label="t('Download invoice')"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-surface-800 dark:hover:text-primary-400"
                                    >
                                        <i class="ti ti-file-download text-base"></i>
                                    </a>
                                </Tooltip>
                                <span v-else class="text-xs text-gray-300 dark:text-gray-600">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        </template>

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
    </div>
</template>
