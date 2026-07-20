<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface AffiliateDetail {
    id: number
    ulid: string
    name: string
    email: string
    referral_code: string
    referral_earnings: number
    affiliate_referrals_count: number
    affiliate_commissions_count: number
    total_commissions: number
    affiliate_banned: boolean
}
interface CommissionItem { id: number; amount: number; status: string; created_at: string | null; referred: { ulid?: string; name?: string; email?: string } | null; payment: { amount: number; currency: string } | null }
interface PayoutItem { id: number; amount: number; method: string; status: string; created_at: string | null }
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedResponse<T> { data: T[]; links: PaginationLink[]; from?: number; to?: number; total?: number }

const props = defineProps<{
    affiliate: AffiliateDetail
    availableBalance: number
    referrals: Array<{ email: string; joined_at: string | null; status: string }>
    commissions: PaginatedResponse<CommissionItem>
    payouts: PaginatedResponse<PayoutItem>
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const banning = ref(false)
const showConfirmModal = ref(false)

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

const handleToggleBan = () => {
    showConfirmModal.value = true
}

const confirmToggleBan = () => {
    showConfirmModal.value = false
    banning.value = true
    router.post(route('admin.affiliate.affiliates.ban', props.affiliate.ulid), {}, { preserveScroll: true, onFinish: () => { banning.value = false } })
}
</script>

<template>
    <Head :title="t('Affiliate Details')" />

    <AdminLayout>
        <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliate Details') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review affiliate profile activity, commissions, payouts, and account status.') }}</p>
                </div>
                <Link :href="route('admin.affiliate.affiliates.index')" class="inline-flex items-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i class="ti ti-arrow-left text-sm"></i>
                    <span>{{ t('Back') }}</span>
                </Link>
            </div>

            <div class="mb-6 flex flex-col gap-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-800 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xl font-semibold text-primary-600 dark:bg-primary-900/30 dark:text-primary-300">{{ affiliate.name.charAt(0).toUpperCase() }}</div>
                    <div>
                        <div class="flex items-center gap-2">
                            <Link :href="route('admin.users.show', affiliate.ulid)" class="hover:text-primary-600 transition-colors">
                                <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ affiliate.name }}</h1>
                            </Link>
                            <span v-if="affiliate.affiliate_banned" class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ t('Suspended') }}</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ affiliate.email }}</p>
                        <p class="mt-1 font-mono text-xs text-gray-400 dark:text-gray-500">{{ affiliate.referral_code }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4 sm:gap-6 xl:gap-8">
                    <div class="flex items-center gap-4 sm:gap-6">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Referrals') }}</p>
                            <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ affiliate.affiliate_referrals_count }}</p>
                        </div>
                        <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Commissions') }}</p>
                            <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ affiliate.affiliate_commissions_count }}</p>
                        </div>
                        <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ t('Total earnings') }}</p>
                            <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(affiliate.referral_earnings) }}</p>
                        </div>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-4 py-2 text-center dark:bg-gray-900/60">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Available balance') }}</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ formatCurrency(availableBalance) }}</p>
                    </div>
                    <button type="button" :disabled="banning" class="rounded-xl px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50" :class="affiliate.affiliate_banned ? 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' : 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50'" @click="handleToggleBan">
                        <span v-if="banning"><i class="ti ti-loader-2 animate-spin text-sm"></i></span>
                        <span v-else>{{ affiliate.affiliate_banned ? t('Reinstate') : t('Suspend') }}</span>
                    </button>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800"><h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Recent referrals') }}</h2></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Joined') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="(r, i) in referrals" :key="i" class="bg-white dark:bg-gray-800">
                                    <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ r.email }}</td>
                                    <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{{ r.joined_at ? formatDateTime(r.joined_at) : '—' }}</td>
                                    <td class="px-6 py-3"><span class="rounded-full bg-primary-100 px-2.5 py-1 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ t(r.status) }}</span></td>
                                </tr>
                                <tr v-if="referrals.length === 0"><td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No referrals yet.') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800"><h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Payouts') }}</h2></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Method') }}</th>
                                    <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="p in payouts.data" :key="p.id" class="bg-white dark:bg-gray-800">
                                     <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{{ p.created_at ? formatDateTime(p.created_at) : '—' }}</td>
                                    <td class="px-6 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(p.amount) }}</td>
                                    <td class="px-6 py-3 text-xs text-gray-700 dark:text-gray-300">{{ t(p.method) }}</td>
                                    <td class="px-6 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="payoutBadgeClass(p.status)">{{ t(p.status) }}</span></td>
                                </tr>
                                <tr v-if="payouts.data.length === 0"><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No payouts yet.') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="payouts.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                        <Pagination :links="payouts.links" :from="payouts.from" :to="payouts.to" :total="payouts.total" />
                    </div>
                </section>
            </div>

            <section class="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800"><h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Commissions') }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Referred') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Order') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Amount') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="c in commissions.data" :key="c.id" class="bg-white dark:bg-gray-800">
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ c.referred?.email || '—' }}</td>
                                <td class="px-6 py-3 text-xs text-gray-700 dark:text-gray-300">{{ c.payment ? formatCurrency(c.payment.amount, c.payment.currency) : '—' }}</td>
                                <td class="px-6 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(c.amount) }}</td>
                                <td class="px-6 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="commissionBadgeClass(c.status)">{{ t(c.status) }}</span></td>
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{{ c.created_at ? formatDateTime(c.created_at) : '—' }}</td>
                            </tr>
                            <tr v-if="commissions.data.length === 0"><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No commissions yet.') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="commissions.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <Pagination :links="commissions.links" :from="commissions.from" :to="commissions.to" :total="commissions.total" />
                </div>
            </section>
        </div>

        <ActionConfirmModal
            :open="showConfirmModal"
            :title="affiliate.affiliate_banned ? t('Reinstate Affiliate') : t('Suspend Affiliate')"
            :message="affiliate.affiliate_banned ? t('Are you sure you want to reinstate this affiliate? They will be able to earn commissions again.') : t('Are you sure you want to suspend this affiliate? All referral commission tracking will be paused.')"
            :confirm-label="affiliate.affiliate_banned ? t('Reinstate') : t('Suspend')"
            :processing-label="affiliate.affiliate_banned ? t('Reinstating...') : t('Suspending...')"
            :processing="banning"
            :variant="affiliate.affiliate_banned ? 'primary' : 'danger'"
            @cancel="showConfirmModal = false"
            @confirm="confirmToggleBan"
        />
    </AdminLayout>
</template>
