<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
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
}

const props = defineProps<{
    payments: Payment[]
    subscription: Subscription
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { formatNumber, formatCurrency } = useNumberFormat()
const page = usePage()

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

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
</script>

<template>
    <Head :title="t('Billing')" />

    <div class="space-y-6">
        <!-- Not available -->
        <div v-if="!isProAvailable" class="rounded-xl border border-gray-200 bg-white p-10 text-center shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">{{ t('Premium subscriptions are not available on this installation.') }}</p>
        </div>

        <template v-else>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Billing & Invoices') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('View your payment history and manage your subscription.') }}</p>
        </div>

        <!-- Subscription Status -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">{{ t('Current Subscription') }}</h2>
            <div class="flex items-center gap-3 mb-3">
                <span v-if="subscription.status === 'active'" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ t('Active') }}
                </span>
                <span v-else-if="subscription.status === 'trialing'" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> {{ t('Trialing') }}
                </span>
                <span v-else class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    {{ t('Inactive') }}
                </span>
            </div>
            <p v-if="subscription.trial_ends_at" class="text-sm text-gray-500 mt-1">{{ t('Trial ends :date', { date: formatDate(subscription.trial_ends_at) }) }}</p>
            <p v-if="subscription.ends_at" class="text-sm text-gray-500 mt-1">{{ t('Access until :date', { date: formatDate(subscription.ends_at) }) }}</p>
            <div class="mt-4">
                <Link :href="route('pricing')" class="inline-flex items-center gap-2 rounded-xl bg-[#1F75FE] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1a65e0] transition">
                    <i class="ti ti-arrow-up"></i> {{ t('Manage Plan') }}
                </Link>
            </div>
        </div>

        <!-- Payment History -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
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
                        <div class="text-xs text-gray-400">{{ payment.type }}</div>
                    </div>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>
