<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
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
}
interface PayoutItem {
    id: number
    amount: number
    method: string
    status: string
    user: { ulid?: string; name?: string; email?: string } | null
}
interface TopAffiliate { ulid: string; name: string; email: string; referral_earnings: number; referral_count: number }

const props = defineProps<{
    stats: {
        total_affiliates: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        total_paid: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        pending_payouts: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        pending_commissions: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
    program: { commission_type: string; commission_value: string | number; commission_on: string; payouts_enabled: boolean }
    recentCommissions: CommissionItem[]
    recentPayouts: PayoutItem[]
    topAffiliates: TopAffiliate[]
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const processing = ref<Record<number, boolean>>({})
const approveModal = ref({ open: false, id: null as number | null, processing: false })



const commissionBadgeClass = (status: string): string => {
    if (status === 'approved') return 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
    if (status === 'paid') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
    if (status === 'rejected') return 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
    if (status === 'cancelled') return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
    return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
}

const payoutBadgeClass = (status: string): string =>
    status === 'paid' ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
        : status === 'rejected' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'

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
        },
    })
}
</script>

<template>
    <Head :title="t('Affiliate Overview')" />

    <AdminLayout>
                <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliate Overview') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Overview of commissions, payouts, and affiliate performance.') }}</p>
                </div>
                <a :href="route('admin.reports.export-center') + '?type=affiliates'" class="shrink-0 inline-flex inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300">
                    <i class="ti ti-download text-base"></i>{{ t('Export') }}
                </a>
            </div>

            <!-- Stats Grid -->
            <div v-if="stats" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    :title="t('Total Affiliates')"
                    :value="stats.total_affiliates.value"
                    :comparison="stats.total_affiliates.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.total_affiliates.comparison.type"
                    color="primary"
                >
                    <template #icon>
                        <i class="ti ti-users text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Total Paid')"
                    :value="stats.total_paid.value"
                    :comparison="stats.total_paid.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.total_paid.comparison.type"
                    color="success"
                >
                    <template #icon>
                        <i class="ti ti-cash text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Pending Payouts')"
                    :value="stats.pending_payouts.value"
                    color="warning"
                >
                    <template #icon>
                        <i class="ti ti-clock text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Pending Commissions')"
                    :value="stats.pending_commissions.value"
                    color="accent"
                >
                    <template #icon>
                        <i class="ti ti-hourglass-low text-lg"></i>
                    </template>
                </StatsCard>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Recent commissions') }}</h2>
                        <Link :href="route('admin.affiliate.commissions.index')" class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ t('View all') }}</Link>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="c in recentCommissions" :key="c.id" class="flex items-center justify-between gap-3 px-6 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ c.referrer?.name || '—' }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ c.referred?.email || '—' }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(c.amount) }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium" :class="commissionBadgeClass(c.status)">{{ t(c.status) }}</span>
                            </div>
                        </div>
                        <p v-if="recentCommissions.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No commissions yet.') }}</p>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Recent payouts') }}</h2>
                        <Link :href="route('admin.affiliate.payouts.index')" class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ t('View all') }}</Link>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="p in recentPayouts" :key="p.id" class="flex items-center justify-between gap-3 px-6 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ p.user?.name || '—' }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ t(p.method) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(p.amount) }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium" :class="payoutBadgeClass(p.status)">{{ t(p.status) }}</span>
                            </div>
                        </div>
                        <p v-if="recentPayouts.length === 0" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No payout requests yet.') }}</p>
                    </div>
                </section>
            </div>

            <section class="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Top affiliates') }}</h2>
                    <Link :href="route('admin.affiliate.affiliates.index')" class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ t('View all') }}</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Affiliate') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referrals') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Earnings') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="a in topAffiliates" :key="a.ulid" class="bg-white transition hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30">
                                <td class="px-6 py-4">
                                    <Link :href="route('admin.affiliate.affiliates.show', a.ulid)" class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white">{{ a.name }}</Link>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ a.email }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ a.referral_count }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(a.referral_earnings) }}</td>
                            </tr>
                            <tr v-if="topAffiliates.length === 0">
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No affiliates yet.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>


        <ActionConfirmModal :open="approveModal.open" :title="t('Approve commission?')" :message="t('Are you sure you want to approve this commission?')" :confirm-label="t('Approve')" :processing-label="t('Approving...')" :processing="approveModal.processing" variant="primary" @confirm="confirmApprove" @cancel="approveModal.open = false" />
    </AdminLayout>
</template>
