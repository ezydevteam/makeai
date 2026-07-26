<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface AffiliateItem {
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
    created_at: string | null
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedResponse<T> { data: T[]; links: PaginationLink[]; from?: number; to?: number; total?: number }

const props = defineProps<{
    affiliates: PaginatedResponse<AffiliateItem>
    stats: {
        total_affiliates: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        total_earnings: { value: string; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { formatDateTime } = useDateFormat()
const { formatCurrency } = useNumberFormat()

const searchInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const statusFilter = ref('')
const searchFocused = ref(false)
const banning = ref<Record<number, boolean>>({})
const confirmModal = ref({
    open: false,
    affiliate: null as AffiliateItem | null,
    processing: false,
})

const statusOptions = computed(() => [
    { value: 'active', label: t('Active') },
    { value: 'banned', label: t('Suspended') },
])

const hasActiveFilters = computed(() => searchQuery.value.trim().length > 0 || statusFilter.value !== '')

const filteredAffiliates = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.affiliates.data.filter((affiliate) => {
        const matchesStatus = !statusFilter.value || (statusFilter.value === 'banned' ? affiliate.affiliate_banned : !affiliate.affiliate_banned)

        if (!matchesStatus) {
            return false
        }

        if (!query) {
            return true
        }

        const haystacks = [
            affiliate.name,
            affiliate.email,
            affiliate.referral_code,
        ]

        return haystacks.some((value) => value.toLowerCase().includes(query))
    })
})

const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
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

const openConfirmModal = (affiliate: AffiliateItem) => {
    confirmModal.value = {
        open: true,
        affiliate,
        processing: false,
    }
}

const closeConfirmModal = () => {
    if (confirmModal.value.processing) return
    confirmModal.value = {
        open: false,
        affiliate: null,
        processing: false,
    }
}

const confirmToggleBan = () => {
    if (!confirmModal.value.affiliate) return
    const affiliate = confirmModal.value.affiliate
    confirmModal.value.processing = true
    banning.value[affiliate.id] = true
    router.post(route('admin.affiliate.affiliates.ban', affiliate.ulid), {}, {
        preserveScroll: true,
        onFinish: () => {
            banning.value[affiliate.id] = false
            confirmModal.value.processing = false
        },
        onSuccess: () => {
            closeConfirmModal()
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
    <Head :title="t('Affiliates')" />

    <AdminLayout>
                <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Affiliates') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('View affiliate performance and suspend or reinstate accounts.') }}</p>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-auto xl:min-w-[480px]">
                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">
                                <i class="ti ti-users text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Total affiliates') }}</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ stats.total_affiliates?.value ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-300">
                                <i class="ti ti-cash text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Total earnings') }}</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ stats.total_earnings?.value ?? '$0.00' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[240px] sm:max-w-md">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500"><i class="ti ti-search text-base"></i></span>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    :placeholder="t('Search name, email, or referral code...')"
                                    @focus="searchFocused = true"
                                    @blur="searchFocused = false"
                                />
                                <span
                                    v-if="!searchQuery && !searchFocused"
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                                >/</span>
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
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                            <div class="w-full sm:w-48 sm:flex-none">
                                <AppSelect v-model="statusFilter" :options="statusOptions" :placeholder="t('Status')" />
                            </div>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 w-full sm:w-auto"
                                @click="clearFilters"
                            >
                                <i class="ti ti-rotate-clockwise text-base"></i>
                                {{ t('Reset') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Affiliate') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Code') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Referrals') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Commissions') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Earnings') }}</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Joined') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-700 dark:text-gray-400">{{ t('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="a in filteredAffiliates" :key="a.ulid" class="bg-white transition hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/30">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('admin.affiliate.affiliates.show', a.ulid)" class="text-sm font-medium text-gray-900 hover:text-primary-600 dark:text-white">{{ a.name }}</Link>
                                        <span v-if="a.affiliate_banned" class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ t('Suspended') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ a.email }}</p>
                                </td>
                                <td class="px-6 py-4"><span class="font-mono text-sm font-medium text-gray-900 dark:text-white">{{ a.referral_code }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ a.affiliate_referrals_count }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ a.affiliate_commissions_count }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(a.referral_earnings) }}</td>
                                <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">{{ a.created_at ? formatDateTime(a.created_at) : '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <Link :href="route('admin.affiliate.affiliates.show', a.ulid)" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                            <i class="ti ti-eye text-xs"></i>
                                            <span>{{ t('View') }}</span>
                                        </Link>
                                        <button type="button" :disabled="banning[a.id]" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium transition-colors disabled:opacity-50" :class="a.affiliate_banned ? 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' : 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-950/50'" @click="openConfirmModal(a)">
                                            <span v-if="banning[a.id]"><i class="ti ti-loader-2 animate-spin text-xs"></i></span>
                                            <template v-else>
                                                <i :class="a.affiliate_banned ? 'ti ti-rotate-2 text-xs' : 'ti ti-ban text-xs'"></i>
                                                <span>{{ a.affiliate_banned ? t('Reinstate') : t('Suspend') }}</span>
                                            </template>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredAffiliates.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ hasActiveFilters ? t('No affiliates match your filters.') : t('No affiliates yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="affiliates.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <Pagination :links="affiliates.links" :from="affiliates.from" :to="affiliates.to" :total="affiliates.total" />
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="confirmModal.open"
            :title="confirmModal.affiliate?.affiliate_banned ? t('Reinstate affiliate?') : t('Suspend affiliate?')"
            :message="confirmModal.affiliate?.affiliate_banned
                ? t('This affiliate will be restored and can earn referrals again.')
                : t('This affiliate will be suspended and can no longer earn referrals until reinstated.')"
            :confirm-label="confirmModal.affiliate?.affiliate_banned ? t('Reinstate') : t('Suspend')"
            :processing-label="confirmModal.affiliate?.affiliate_banned ? t('Reinstating...') : t('Suspending...')"
            :processing="confirmModal.processing"
            :variant="confirmModal.affiliate?.affiliate_banned ? 'primary' : 'danger'"
            @confirm="confirmToggleBan"
            @cancel="closeConfirmModal"
        />
    </AdminLayout>
</template>
