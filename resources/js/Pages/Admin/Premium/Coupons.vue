<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface PlanOption { id: number; name: string }
type CouponUserLimit = 'all' | 'active' | 'inactive' | 'free' | 'pro' | 'recent_30_days'
interface Coupon {
    id: number
    code: string
    type: 'percent' | 'fixed'
    value: string | number
    max_discount: string | number | null
    max_uses: number | null
    per_user_limit: number | null
    used_count: number
    is_active: boolean
    show_in_header: boolean
    plan_id: number | null
    user_limit: CouponUserLimit
    plan?: PlanOption | null
    starts_at: string | null
    expires_at: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    coupons: {
        data: Coupon[]
        links: PaginationLink[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    plans: PlanOption[]
    filters: { search?: string; status?: string }
    stats: {
        total: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        active: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        published: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        redemptions: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { formatCurrency } = useNumberFormat()

const form = useForm({
    code: '',
    type: 'percent',
    value: '10',
    max_discount: '',
    max_uses: '',
    per_user_limit: '1',
    is_active: true,
    show_in_header: false,
    plan_id: '',
    user_limit: 'all' as CouponUserLimit,
    starts_at: '',
    expires_at: '',
})

const editingId = ref<number | null>(null)
const formModalOpen = ref(false)
const deletingCoupon = ref<Coupon | null>(null)
const deleteProcessing = ref(false)
const searchQuery = ref(props.filters?.search || '')
const searchInputRef = ref<HTMLInputElement | null>(null)
const statusFilter = ref(props.filters?.status || '')

const userLimitOptions: { value: CouponUserLimit; label: string; description: string }[] = [
    { value: 'all', label: t('All users'), description: t('Anyone can use this coupon.') },
    { value: 'active', label: t('Active users'), description: t('Only active accounts.') },
    { value: 'inactive', label: t('Inactive users'), description: t('Only inactive accounts.') },
    { value: 'free', label: t('Free users'), description: t('Users without an active paid plan.') },
    { value: 'pro', label: t('Pro users'), description: t('Users with active paid or trial access.') },
    { value: 'recent_30_days', label: t('Recently joined'), description: t('Users who joined in the last 30 days.') },
]

const typeOptions = computed(() => [
    { label: t('Percent'), value: 'percent' },
    { label: t('Fixed'), value: 'fixed' },
])

const planOptions = computed(() => [
    { label: t('All plans'), value: '' },
    ...props.plans.map((plan) => ({ label: plan.name, value: String(plan.id) })),
])

const userLimitSelectOptions = computed(() => userLimitOptions.map((option) => ({
    label: option.label,
    value: option.value,
})))

const applyFilters = () => {
    const params: Record<string, string> = {}
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (statusFilter.value) params.status = statusFilter.value

    router.get(route('admin.coupons.index'), params, {
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

watch(statusFilter, () => {
    applyFilters()
})

const statusFilterOptions = computed(() => [
    { label: t('All Status'), value: '' },
    { label: t('Active'), value: 'active' },
    { label: t('Inactive'), value: 'inactive' },
    { label: t('Published'), value: 'published' },
    { label: t('Unpublished'), value: 'unpublished' },
])

const userLimitLabel = (limit: CouponUserLimit) => userLimitOptions.find((option) => option.value === limit)?.label ?? t('All users')
const discountLabel = (coupon: Coupon) => coupon.type === 'percent'
    ? `${coupon.value}%`
    : formatCurrency(Number(coupon.value))

const couponDateRangeLabel = (coupon: Coupon) => {
    if (!coupon.starts_at && !coupon.expires_at) return t('No schedule')
    if (coupon.starts_at && coupon.expires_at) return `${coupon.starts_at.slice(0, 10)} - ${coupon.expires_at.slice(0, 10)}`
    if (coupon.starts_at) return t('Starts :date', { date: coupon.starts_at.slice(0, 10) })

    return t('Expires :date', { date: coupon.expires_at?.slice(0, 10) ?? '' })
}

const toggleHeader = (coupon: Coupon) => {
    router.post(route('admin.coupons.header', coupon.id), {}, { preserveScroll: true })
}

const edit = (coupon: Coupon) => {
    editingId.value = coupon.id
    formModalOpen.value = true
    form.code = coupon.code
    form.type = coupon.type
    form.value = String(coupon.value)
    form.max_discount = coupon.max_discount === null ? '' : String(coupon.max_discount)
    form.max_uses = coupon.max_uses === null ? '' : String(coupon.max_uses)
    form.per_user_limit = coupon.per_user_limit === null ? '' : String(coupon.per_user_limit)
    form.is_active = coupon.is_active
    form.show_in_header = coupon.show_in_header
    form.plan_id = coupon.plan_id === null ? '' : String(coupon.plan_id)
    form.user_limit = coupon.user_limit ?? 'all'
    form.starts_at = coupon.starts_at?.slice(0, 10) ?? ''
    form.expires_at = coupon.expires_at?.slice(0, 10) ?? ''
}

const openCreateModal = () => {
    reset()
    formModalOpen.value = true
}

const reset = () => {
    editingId.value = null
    formModalOpen.value = false
    form.reset()
    form.clearErrors()
}

const submit = () => {
    const routeName = editingId.value ? route('admin.coupons.update', editingId.value) : route('admin.coupons.store')
    form.transform((data) => ({
        ...data,
        max_discount: data.max_discount || null,
        max_uses: data.max_uses || null,
        per_user_limit: data.per_user_limit || null,
        plan_id: data.plan_id || null,
        user_limit: data.user_limit || 'all',
        starts_at: data.starts_at || null,
        expires_at: data.expires_at || null,
    })).post(routeName, { preserveScroll: true, onSuccess: reset })
}

const confirmDelete = () => {
    if (!deletingCoupon.value || deleteProcessing.value) return

    deleteProcessing.value = true
    router.delete(route('admin.coupons.delete', deletingCoupon.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false
            deletingCoupon.value = null
        },
    })
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

    if (formModalOpen.value || deletingCoupon.value) {
        return
    }

    if (!searchQuery.value && !statusFilter.value) {
        return
    }

    event.preventDefault()
    searchQuery.value = ''
    statusFilter.value = ''
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
    <Head :title="t('Coupons')" />

    <AdminLayout>


        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Coupons') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create flexible checkout discounts and manage where they appear.') }}</p>
                </div>
                <button
                    type="button"
                    class="btn-primary-admin shrink-0 gap-2 inline-flex items-center justify-center"
                    @click="openCreateModal"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Create Coupon') }}
                </button>
            </div>

            <!-- Stats Grid -->
            <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    :title="t('Total Coupons')"
                    :value="stats.total.value"
                    :comparison="stats.total.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.total.comparison.type"
                    color="primary"
                >
                    <template #icon>
                        <i class="ti ti-ticket text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Active')"
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
                    :title="t('Published')"
                    :value="stats.published.value"
                    :comparison="stats.published.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.published.comparison.type"
                    color="accent"
                >
                    <template #icon>
                        <i class="ti ti-eye text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Redemptions')"
                    :value="stats.redemptions.value"
                    :comparison="stats.redemptions.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.redemptions.comparison.type"
                    color="warning"
                >
                    <template #icon>
                        <i class="ti ti-discount-check text-lg"></i>
                    </template>
                </StatsCard>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:max-w-xs sm:min-w-[280px]">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                :placeholder="t('Search coupons...')"
                            />
                            <span
                                v-if="!searchQuery"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                            >
                                /
                            </span>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                :title="t('Clear search')"
                                @click="searchQuery = ''"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>

                        <div class="w-full sm:w-44">
                            <AppSelect
                                v-model="statusFilter"
                                :options="statusFilterOptions"
                                :placeholder="t('All Status')"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-b-2xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">{{ t('Coupon') }}</th>
                                    <th class="px-4 py-3">{{ t('Discount') }}</th>
                                    <th class="px-4 py-3">{{ t('Eligibility') }}</th>
                                    <th class="px-4 py-3">{{ t('Usage') }}</th>
                                    <th class="px-4 py-3">{{ t('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                                <tr v-if="coupons.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('No coupons found.') }}
                                    </td>
                                </tr>

                                <tr
                                    v-for="coupon in coupons.data"
                                    :key="coupon.id"
                                    class="transition-colors hover:bg-primary-50/40 dark:hover:bg-gray-900/30"
                                >
                                    <td class="px-4 py-4">
                                        <div>
                                            <p class="font-semibold uppercase tracking-wide text-gray-900 dark:text-white">{{ coupon.code }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ couponDateRangeLabel(coupon) }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ discountLabel(coupon) }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ coupon.type === 'percent' ? t('Percentage discount') : t('Fixed amount discount') }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="space-y-1">
                                            <p class="text-gray-900 dark:text-white">{{ coupon.plan?.name || t('All plans') }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ userLimitLabel(coupon.user_limit) }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="space-y-1">
                                            <p class="text-gray-900 dark:text-white">{{ coupon.used_count }} / {{ coupon.max_uses || t('Unlimited') }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ coupon.per_user_limit ? t(':count per user', { count: String(coupon.per_user_limit) }) : t('Unlimited per user') }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="space-y-1">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="coupon.is_active
                                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                            >
                                                {{ coupon.is_active ? t('Active') : t('Inactive') }}
                                            </span>
                                            <p
                                                class="text-xs"
                                                :class="coupon.show_in_header ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                            >
                                                {{ coupon.show_in_header ? t('Published') : t('Unpublished') }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-end">
                                        <TableActionMenu>
                                            <template #default="{ close }">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                    @click="toggleHeader(coupon); close()"
                                                >
                                                    <i class="ti ti-layout-navbar text-base"></i>
                                                    {{ coupon.show_in_header ? t('Unpublish') : t('Publish') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                                    @click="edit(coupon); close()"
                                                >
                                                    <i class="ti ti-edit text-base"></i>
                                                    {{ t('Edit Details') }}
                                                </button>
                                                <hr class="border-gray-200 dark:border-surface-700">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                    @click="deletingCoupon = coupon; close()"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                    {{ t('Delete') }}
                                                </button>
                                            </template>
                                        </TableActionMenu>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="coupons.links && coupons.links.length > 3" class="border-t border-gray-100 p-4 dark:border-surface-800">
                        <Pagination
                            :links="coupons.links"
                            :from="coupons.from"
                            :to="coupons.to"
                            :total="coupons.total"
                            :current-page="coupons.current_page"
                            :last-page="coupons.last_page"
                        />
                    </div>
                </div>
            </div>
        </div>

        <AppModal
            :open="formModalOpen"
            max-width="max-w-4xl"
            :title="editingId ? t('Edit Coupon') : t('Create Coupon')"
            :subtitle="t('Set discount rules, visibility, plan restrictions, and schedule in one place.')"
            has-form
            :confirm-text="editingId ? t('Update Coupon') : t('Create Coupon')"
            :confirm-loading="form.processing"
            :confirm-loading-text="editingId ? t('Saving...') : t('Creating...')"
            @close="reset"
            @submit="submit"
        >
            <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-600 dark:border-red-900/40 dark:bg-red-900/10 dark:text-red-300">
                <ul class="space-y-1">
                    <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                </ul>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block md:col-span-2">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Coupon code') }}</span>
                    <input
                        v-model="form.code"
                        type="text"
                        required
                        :placeholder="t('e.g. SUMMER25')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm uppercase text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                    <span v-if="form.errors.code" class="mt-2 block text-xs text-red-600 dark:text-red-300">{{ form.errors.code }}</span>
                </label>

                <div class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Discount type') }}</span>
                    <AppSelect
                        v-model="form.type"
                        :options="typeOptions"
                        :placeholder="t('Select type')"
                    />
                </div>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Discount value') }}</span>
                    <input
                        v-model="form.value"
                        type="number"
                        min="0"
                        step="0.01"
                        :placeholder="form.type === 'percent' ? t('e.g. 25') : t('e.g. 10.00')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Max discount') }}</span>
                    <input
                        v-model="form.max_discount"
                        type="number"
                        min="0"
                        step="0.01"
                        :placeholder="t('Leave blank for unlimited')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Max uses') }}</span>
                    <input
                        v-model="form.max_uses"
                        type="number"
                        min="1"
                        :placeholder="t('Leave blank for unlimited')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Uses per user') }}</span>
                    <input
                        v-model="form.per_user_limit"
                        type="number"
                        min="1"
                        :placeholder="t('Leave blank for unlimited')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                    <span v-if="form.errors.per_user_limit" class="mt-2 block text-xs text-red-600 dark:text-red-300">{{ form.errors.per_user_limit }}</span>
                </label>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Eligibility & Schedule') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Target specific plans or user groups and define when the coupon can be used.') }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Limit to plan') }}</span>
                    <AppSelect
                        v-model="form.plan_id"
                        :options="planOptions"
                        :placeholder="t('All plans')"
                        live-search
                    />
                </div>

                <div class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Limit to users') }}</span>
                    <AppSelect
                        v-model="form.user_limit"
                        :options="userLimitSelectOptions"
                        :placeholder="t('All users')"
                    />
                    <span class="mt-2 block text-xs text-gray-500 dark:text-gray-400">
                        {{ userLimitOptions.find((option) => option.value === form.user_limit)?.description }}
                    </span>
                </div>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Starts on') }}</span>
                    <input
                        v-model="form.starts_at"
                        type="date"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Expires on') }}</span>
                    <input
                        v-model="form.expires_at"
                        type="date"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                    <span class="flex flex-col">
                        <span>{{ t('Status') }}</span>
                        <span class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                            {{ t('Make active/inactive this coupon') }}
                        </span>
                    </span>
                    <AppSwitch v-model="form.is_active" />
                </label>

                <label class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                    <span class="flex flex-col">
                        <span>{{ t('Publish immediately') }}</span>
                        <span class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                            {{ t('Show this coupon above site header') }}
                        </span>
                    </span>
                    <AppSwitch v-model="form.show_in_header" class="mt-0.5 shrink-0" />
                </label>
            </div>
        </AppModal>

        <ActionConfirmModal
            :open="!!deletingCoupon"
            :title="t('Delete coupon?')"
            :message="t('This coupon will be removed permanently.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            :processing="deleteProcessing"
            @confirm="confirmDelete"
            @cancel="deletingCoupon = null"
        />
    </AdminLayout>
</template>
