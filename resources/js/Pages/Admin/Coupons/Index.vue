<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
    used_count: number
    is_recurring: boolean
    is_active: boolean
    show_in_header: boolean
    plan_id: number | null
    user_limit: CouponUserLimit
    plan?: PlanOption | null
    starts_at: string | null
    expires_at: string | null
}

const props = defineProps<{
    coupons: { data: Coupon[] }
    plans: PlanOption[]
}>()

const { t } = useTranslate()
const { formatCurrency } = useNumberFormat()

const form = useForm({
    code: '',
    type: 'percent',
    value: '10',
    max_discount: '',
    max_uses: '',
    is_recurring: false,
    is_active: true,
    plan_id: '',
    user_limit: 'all' as CouponUserLimit,
    starts_at: '',
    expires_at: '',
})

const editingId = ref<number | null>(null)
const deletingCoupon = ref<Coupon | null>(null)
const deleteProcessing = ref(false)
const actionMenuOpen = ref<number | null>(null)
const actionMenuStyle = ref<Record<string, string>>({})

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

const totalCoupons = computed(() => props.coupons.data.length)
const activeCoupons = computed(() => props.coupons.data.filter((coupon) => coupon.is_active).length)
const headerCoupons = computed(() => props.coupons.data.filter((coupon) => coupon.show_in_header).length)
const recurringCoupons = computed(() => props.coupons.data.filter((coupon) => coupon.is_recurring).length)

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
    form.code = coupon.code
    form.type = coupon.type
    form.value = String(coupon.value)
    form.max_discount = coupon.max_discount === null ? '' : String(coupon.max_discount)
    form.max_uses = coupon.max_uses === null ? '' : String(coupon.max_uses)
    form.is_recurring = coupon.is_recurring
    form.is_active = coupon.is_active
    form.plan_id = coupon.plan_id === null ? '' : String(coupon.plan_id)
    form.user_limit = coupon.user_limit ?? 'all'
    form.starts_at = coupon.starts_at?.slice(0, 10) ?? ''
    form.expires_at = coupon.expires_at?.slice(0, 10) ?? ''
}

const reset = () => {
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const submit = () => {
    const routeName = editingId.value ? route('admin.coupons.update', editingId.value) : route('admin.coupons.store')
    form.transform((data) => ({
        ...data,
        max_discount: data.max_discount || null,
        max_uses: data.max_uses || null,
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

const toggleActionMenu = (couponId: number) => {
    actionMenuOpen.value = actionMenuOpen.value === couponId ? null : couponId
}

const closeActionMenu = () => {
    actionMenuOpen.value = null
}

const openActionMenu = (couponId: number, event: MouseEvent) => {
    if (actionMenuOpen.value === couponId) {
        closeActionMenu()
        return
    }

    const trigger = event.currentTarget as HTMLElement | null

    if (!trigger) return

    const rect = trigger.getBoundingClientRect()
    actionMenuStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${Math.max(16, rect.right - 144)}px`,
        zIndex: '60',
    }
    actionMenuOpen.value = couponId
}
</script>

<template>
    <Head :title="t('Coupons')" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl px-6 py-8" @click="closeActionMenu">
            <div class="space-y-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Coupons') }}</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create flexible checkout discounts and manage where they appear.') }}</p>
                    </div>

                    <Link
                        :href="route('admin.payment-gateways.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <i class="ti ti-credit-card text-base"></i>
                        {{ t('Payment Gateways') }}
                    </Link>
                </div>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="border border-gray-100 bg-white px-5 py-4 shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Total coupons') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ totalCoupons }}</p>
                    </div>
                    <div class="border border-gray-100 bg-white px-5 py-4 shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Active') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ activeCoupons }}</p>
                    </div>
                    <div class="border border-gray-100 bg-white px-5 py-4 shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Published') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ headerCoupons }}</p>
                    </div>
                    <div class="border border-gray-100 bg-white px-5 py-4 shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Recurring') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ recurringCoupons }}</p>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                    <form class="space-y-6" @submit.prevent="submit">
                        <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingId ? t('Edit Coupon') : t('Create Coupon') }}</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Set discount rules, visibility, plan restrictions, and schedule in one place.') }}</p>
                                </div>
                            </div>

                            <div class="grid gap-6 p-6">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Coupon code') }}</span>
                                        <input
                                            v-model="form.code"
                                            type="text"
                                            :placeholder="t('e.g. SUMMER25')"
                                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm uppercase text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Discount type') }}</span>
                                        <AppSelect
                                            v-model="form.type"
                                            :options="typeOptions"
                                            :placeholder="t('Select type')"
                                        />
                                    </label>

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
                                </div>

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Eligibility & Schedule') }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Target specific plans or user groups and define when the coupon can be used.') }}</p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Limit to plan') }}</span>
                                        <AppSelect
                                            v-model="form.plan_id"
                                            :options="planOptions"
                                            :placeholder="t('All plans')"
                                            live-search
                                        />
                                    </label>

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Limit to users') }}</span>
                                        <AppSelect
                                            v-model="form.user_limit"
                                            :options="userLimitSelectOptions"
                                            :placeholder="t('All users')"
                                        />
                                        <span class="mt-2 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ userLimitOptions.find((option) => option.value === form.user_limit)?.description }}
                                        </span>
                                    </label>

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

                                <div class="grid gap-4 md:grid-cols-2">
                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                                        <span>{{ t('Recurring coupon') }}</span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.is_recurring"
                                            class="relative inline-flex h-6 w-11 rounded-full transition"
                                            :class="form.is_recurring ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                            @click="form.is_recurring = !form.is_recurring"
                                        >
                                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.is_recurring ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                        </button>
                                    </label>

                                    <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-200">
                                        <span>{{ t('Active') }}</span>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.is_active"
                                            class="relative inline-flex h-6 w-11 rounded-full transition"
                                            :class="form.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                            @click="form.is_active = !form.is_active"
                                        >
                                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                        </button>
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                                <button
                                    type="button"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                    @click="reset"
                                >
                                    <i class="ti ti-refresh text-base"></i>
                                    {{ t('Reset') }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <i class="ti ti-device-floppy text-base"></i>
                                    {{ form.processing ? t('Saving...') : (editingId ? t('Update Coupon') : t('Create Coupon')) }}
                                </button>
                            </div>
                        </section>
                    </form>

                    <section class="border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Coupon List') }}</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review usage, visibility, and quickly edit existing coupons.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-visible">
                            <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">{{ t('Coupon') }}</th>
                                        <th class="px-4 py-3">{{ t('Discount') }}</th>
                                        <th class="px-4 py-3">{{ t('Eligibility') }}</th>
                                        <th class="px-4 py-3">{{ t('Usage') }}</th>
                                        <th class="px-4 py-3">{{ t('Status') }}</th>
                                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="coupons.data.length === 0">
                                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ t('No coupons found.') }}
                                        </td>
                                    </tr>

                                    <tr
                                        v-for="coupon in coupons.data"
                                        :key="coupon.id"
                                        class="border-t border-gray-100 transition-colors hover:bg-primary-50/40 dark:border-gray-800 dark:hover:bg-gray-900/30"
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
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ coupon.is_recurring ? t('Recurring') : t('One-time coupon') }}</p>
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
                                                    :class="coupon.show_in_header ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'"
                                                >
                                                    {{ coupon.show_in_header ? t('Published') : t('Unpublished') }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="relative inline-flex justify-end" @click.stop>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                                    @click="openActionMenu(coupon.id, $event)"
                                                >
                                                    <i class="ti ti-dots-vertical text-base"></i>
                                                </button>

                                                <div
                                                    v-if="actionMenuOpen === coupon.id"
                                                    class="w-36 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                                                    :style="actionMenuStyle"
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                                        @click="edit(coupon); actionMenuOpen = null"
                                                    >
                                                        <i class="ti ti-edit text-sm"></i>
                                                        {{ t('Edit') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                                        @click="toggleHeader(coupon); actionMenuOpen = null"
                                                    >
                                                        <i class="ti ti-layout-navbar text-sm"></i>
                                                        {{ coupon.show_in_header ? t('Unpublish') : t('Publish') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                                                        @click="deletingCoupon = coupon; actionMenuOpen = null"
                                                    >
                                                        <i class="ti ti-trash text-sm"></i>
                                                        {{ t('Delete') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

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
