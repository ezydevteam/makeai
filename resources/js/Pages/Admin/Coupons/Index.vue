<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'

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
const userLimitOptions: { value: CouponUserLimit; label: string; description: string }[] = [
    { value: 'all', label: 'All users', description: 'Anyone can use this coupon.' },
    { value: 'active', label: 'Active users', description: 'Only active accounts.' },
    { value: 'inactive', label: 'Inactive users', description: 'Only inactive accounts.' },
    { value: 'free', label: 'Free users', description: 'Users without an active paid plan.' },
    { value: 'pro', label: 'Pro users', description: 'Users with active paid or trial access.' },
    { value: 'recent_30_days', label: 'Recently joined', description: 'Users who joined in the last 30 days.' },
]
const userLimitLabel = (limit: CouponUserLimit) => userLimitOptions.find((option) => option.value === limit)?.label ?? 'All users'
const discountLabel = (coupon: Coupon) => coupon.type === 'percent'
    ? `${coupon.value}%`
    : formatCurrency(Number(coupon.value))

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
</script>

<template>
    <Head :title="$t('Coupons')" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Coupons</h1>
                    <p class="mt-1 text-sm text-gray-500">Create fixed or percentage discounts for checkout.</p>
                </div>
                <Link :href="route('admin.payment-gateways.index')" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300">Payment gateways</Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">
                <form class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" @submit.prevent="submit">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ editingId ? 'Edit coupon' : 'Create coupon' }}</h2>
                    <div class="space-y-4">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Code</span>
                            <input v-model="form.code" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase" />
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Type</span>
                                <select v-model="form.type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="percent">Percent</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Value</span>
                                <input v-model="form.value" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Max discount</span>
                                <input v-model="form.max_discount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Max uses</span>
                                <input v-model="form.max_uses" type="number" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Limit to plan</span>
                            <select v-model="form.plan_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <option value="">All plans</option>
                                <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">{{ plan.name }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700">Limit to users</span>
                            <select v-model="form.user_limit" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <option v-for="option in userLimitOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <span class="mt-1 block text-xs text-gray-500">
                                {{ userLimitOptions.find((option) => option.value === form.user_limit)?.description }}
                            </span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Starts</span>
                                <input v-model="form.starts_at" type="date" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Expires</span>
                                <input v-model="form.expires_at" type="date" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </label>
                        </div>
                        <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                            Recurring
                            <input v-model="form.is_recurring" type="checkbox" />
                        </label>
                        <label class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium">
                            Active
                            <input v-model="form.is_active" type="checkbox" />
                        </label>
                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white">{{ editingId ? 'Update' : 'Create' }}</button>
                            <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium" @click="reset">Cancel</button>
                        </div>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Discount</th>
                                <th class="px-4 py-3">Plan</th>
                                <th class="px-4 py-3">Users</th>
                                <th class="px-4 py-3">Uses</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Header</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="coupon in coupons.data" :key="coupon.id" class="border-t border-gray-100">
                                <td class="px-4 py-3 font-bold">{{ coupon.code }}</td>
                                <td class="px-4 py-3">{{ discountLabel(coupon) }}</td>
                                <td class="px-4 py-3">{{ coupon.plan?.name || 'All plans' }}</td>
                                <td class="px-4 py-3">{{ userLimitLabel(coupon.user_limit) }}</td>
                                <td class="px-4 py-3">{{ coupon.used_count }} / {{ coupon.max_uses || '∞' }}</td>
                                <td class="px-4 py-3">{{ coupon.is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-4 py-3">
                                    <span :class="coupon.show_in_header ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600'" class="rounded-full px-2.5 py-1 text-xs font-bold">
                                        {{ coupon.show_in_header ? 'Shown' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button class="mr-2 rounded-lg border border-primary-200 px-3 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-50" @click="toggleHeader(coupon)">
                                        {{ coupon.show_in_header ? 'Hide header' : 'Show header' }}
                                    </button>
                                    <button class="mr-2 rounded-lg border border-gray-200 px-3 py-1 text-xs" @click="edit(coupon)">Edit</button>
                                    <button class="rounded-lg bg-red-500 px-3 py-1 text-xs text-white" @click="router.delete(route('admin.coupons.delete', coupon.id), { preserveScroll: true })">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
