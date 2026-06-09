<template>
    <Head :title="t('User Management')" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('User Management') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Manage platform users, credits, and subscription states.') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary"
                            @click="openCreateModal"
                        >
                            <i class="ti ti-plus text-base"></i>
                            {{ t('Create User') }}
                        </button>

                        <Link
                            v-if="hasTrashedUsers"
                            :href="route('admin.users.trash')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <i class="ti ti-trash text-base"></i>
                            {{ t('Trash') }}
                        </Link>

                        <a
                            :href="route('admin.users.export')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <i class="ti ti-download text-base"></i>
                            {{ t('Export CSV') }}
                        </a>
                    </div>
                </div>

                <div class="mb-4 flex flex-col gap-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="w-full xl:max-w-md">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Filter this table by name, email, or ULID...')"
                                />
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
                        </div>

                        <div class="flex flex-col gap-4 xl:ml-auto xl:flex-row xl:items-center xl:justify-end">
                            <div class="w-full md:w-52">
                                <AppSelect
                                    v-model="form.status"
                                    :options="statusOptions"
                                    :placeholder="t('All Status')"
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <div class="w-full md:w-56">
                                <AppSelect
                                    v-model="form.plan"
                                    :options="planOptions"
                                    :placeholder="t('All Plans')"
                                    live-search
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <template v-if="selectedIds.length > 0">
                                <span class="text-sm text-gray-500 dark:text-gray-400 xl:whitespace-nowrap">
                                    {{ t(':count selected', { count: selectedIds.length }) }}
                                </span>

                                <div class="w-full md:w-56">
                                    <AppSelect
                                        v-model="bulkAction"
                                        :options="bulkActionOptions"
                                        :placeholder="t('Bulk Actions')"
                                    />
                                </div>

                                <input
                                    v-if="bulkAction === 'add_credits'"
                                    v-model="bulkCredits"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white md:w-40"
                                    :placeholder="t('Credits')"
                                />

                                <div :title="applyDisabledReason">
                                    <button
                                        type="button"
                                        class="btn-primary rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="isApplyDisabled"
                                        @click="applyBulkAction"
                                    >
                                        {{ t('Apply') }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden border border-gray-100 bg-white shadow-sm sm:rounded-lg dark:border-gray-800 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                                :checked="isAllSelected"
                                                @change="toggleAll"
                                            />
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3">{{ t('User') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Credits') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Plan') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Status') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Joined') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in filteredUsers"
                                    :key="user.id"
                                    class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                                >
                                    <td class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input
                                                v-model="selectedIds"
                                                type="checkbox"
                                                :value="user.id"
                                                class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-gray-900 dark:text-white">{{ formatCredits(user.credits) }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            v-if="user.plan"
                                            class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                        >
                                            {{ user.plan.name }}
                                        </span>
                                        <span v-else class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ t('Free Tier') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="user.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            {{ user.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <Tooltip :content="t('Edit user')" placement="top">
                                                <Link
                                                    :href="route('admin.users.show', user.ulid)"
                                                    :aria-label="t('Edit user')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                                >
                                                    <i class="ti ti-edit text-base"></i>
                                                </Link>
                                            </Tooltip>

                                            <Tooltip :content="t('Login as User')" placement="top">
                                                <button
                                                    type="button"
                                                    :aria-label="t('Login as User')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                                    @click="impersonateUser(user)"
                                                >
                                                    <i class="ti ti-user-share text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No users found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="users.links.length > 3" class="mt-4">
                    <Pagination :links="users.links" />
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="confirmModal.open"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-label="confirmModal.confirmLabel"
            :processing-label="confirmModal.processingLabel"
            :processing="confirmModal.processing"
            :variant="confirmModal.variant"
            @cancel="closeConfirmModal"
            @confirm="runConfirmedAction"
        />

        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ t('Create User') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Add a new platform user, assign credits, and set an optional plan.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        :aria-label="t('Close modal')"
                        @click="() => closeCreateModal()"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="overflow-y-auto p-6">
                    <form class="space-y-6" @submit.prevent="submitCreateUser">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Full Name') }}
                                </span>
                                <input
                                    v-model="createForm.name"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Enter full name')"
                                />
                                <p v-if="createForm.errors.name" class="mt-1 text-xs text-danger-600">{{ createForm.errors.name }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Email Address') }}
                                </span>
                                <input
                                    v-model="createForm.email"
                                    type="email"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Enter email address')"
                                />
                                <p v-if="createForm.errors.email" class="mt-1 text-xs text-danger-600">{{ createForm.errors.email }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Password') }}
                                </span>
                                <div class="relative">
                                    <input
                                        v-model="createForm.password"
                                        :type="showCreatePassword ? 'text' : 'password'"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="t('Enter password')"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        :aria-label="showCreatePassword ? t('Hide password') : t('Show password')"
                                        @click="showCreatePassword = !showCreatePassword"
                                    >
                                        <i :class="showCreatePassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                    </button>
                                </div>
                                <p v-if="createForm.errors.password" class="mt-1 text-xs text-danger-600">{{ createForm.errors.password }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Confirm Password') }}
                                </span>
                                <div class="relative">
                                    <input
                                        v-model="createForm.password_confirmation"
                                        :type="showCreatePasswordConfirmation ? 'text' : 'password'"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="t('Confirm password')"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        :aria-label="showCreatePasswordConfirmation ? t('Hide password confirmation') : t('Show password confirmation')"
                                        @click="showCreatePasswordConfirmation = !showCreatePasswordConfirmation"
                                    >
                                        <i :class="showCreatePasswordConfirmation ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                    </button>
                                </div>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Credits Balance') }}
                                </span>
                                <input
                                    v-model="createForm.credits"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <p v-if="createForm.errors.credits" class="mt-1 text-xs text-danger-600">{{ createForm.errors.credits }}</p>
                            </label>

                            <div class="block">
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Active Plan') }}
                                </span>
                                <AppSelect
                                    v-model="createForm.plan_id"
                                    :options="createPlanOptions"
                                    :placeholder="t('No Plan (Free)')"
                                    live-search
                                />
                                <p v-if="createForm.errors.plan_id" class="mt-1 text-xs text-danger-600">{{ createForm.errors.plan_id }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="relative h-6 w-12 rounded-full transition-colors"
                                    :class="createForm.is_active ? 'bg-success-500' : 'bg-gray-300 dark:bg-gray-600'"
                                    @click="createForm.is_active = !createForm.is_active"
                                >
                                    <span
                                        class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                        :class="createForm.is_active ? 'translate-x-6' : 'translate-x-0'"
                                    ></span>
                                </button>

                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        {{ createForm.is_active ? t('User account is active') : t('User account is disabled') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('Inactive users cannot sign in until you enable their account.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <button
                        type="button"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        @click="() => closeCreateModal()"
                    >
                        {{ t('Cancel') }}
                    </button>

                    <button
                        type="button"
                        :disabled="createForm.processing"
                        class="btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        @click="submitCreateUser"
                    >
                        {{ createForm.processing ? t('Creating...') : t('Create User') }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface Plan {
    id: number
    name: string
}

interface UserPlan {
    id: number
    name: string
}

interface UserItem {
    id: number
    ulid: string
    name: string
    email: string
    credits: number | string
    is_active: boolean
    created_at: string
    plan: UserPlan | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface UsersResponse {
    data: UserItem[]
    links: PaginationLink[]
    from?: number
    to?: number
    total?: number
}

interface Filters {
    status?: string | number | null
    plan?: string | number | null
}

interface ConfirmModalState {
    open: boolean
    title: string
    message: string
    confirmLabel: string
    processingLabel: string
    processing: boolean
    variant: 'primary' | 'danger'
    action: null | (() => void)
}

const props = defineProps<{
    users: UsersResponse
    filters: Filters
    plans: Plan[]
    hasTrashedUsers: boolean
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()

const form = useForm({
    status: props.filters.status !== undefined && props.filters.status !== null ? String(props.filters.status) : '',
    plan: props.filters.plan !== undefined && props.filters.plan !== null ? String(props.filters.plan) : '',
})

const bulkForm = useForm({
    ids: [] as number[],
    action: '',
    value: '',
})

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    credits: '0',
    plan_id: '',
    is_active: true,
})

const searchQuery = ref('')
const selectedIds = ref<number[]>([])
const bulkAction = ref<string | number | null>('')
const bulkCredits = ref('')
const showCreateModal = ref(false)
const showCreatePassword = ref(false)
const showCreatePasswordConfirmation = ref(false)

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: '1', label: t('Active') },
    { value: '0', label: t('Inactive') },
])

const planOptions = computed(() => [
    { value: '', label: t('All Plans') },
    ...props.plans.map((plan) => ({
        value: String(plan.id),
        label: plan.name,
    })),
])

const createPlanOptions = computed(() => [
    { value: '', label: t('No Plan (Free)') },
    ...props.plans.map((plan) => ({
        value: String(plan.id),
        label: plan.name,
    })),
])

const bulkActionOptions = computed(() => [
    { value: 'activate', label: t('Activate Users') },
    { value: 'deactivate', label: t('Deactivate Users') },
    { value: 'add_credits', label: t('Add Credits') },
    { value: 'delete', label: t('Delete Users') },
])

const confirmModal = ref<ConfirmModalState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    processingLabel: '',
    processing: false,
    variant: 'primary',
    action: null,
})

const filteredUsers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.users.data
    }

    return props.users.data.filter((user) => {
        return [
            user.name,
            user.email,
            user.ulid,
            user.plan?.name ?? '',
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const isAllSelected = computed(() => {
    return filteredUsers.value.length > 0 && filteredUsers.value.every((user) => selectedIds.value.includes(user.id))
})

const isApplyDisabled = computed(() => {
    if (selectedIds.value.length === 0 || !bulkAction.value) {
        return true
    }

    if (bulkAction.value === 'add_credits' && !bulkCredits.value.trim()) {
        return true
    }

    return false
})

const applyDisabledReason = computed(() => {
    if (selectedIds.value.length === 0) {
        return t('Select at least 1 user to apply')
    }

    if (!bulkAction.value) {
        return t('Select a bulk action')
    }

    if (bulkAction.value === 'add_credits' && !bulkCredits.value.trim()) {
        return t('Enter credits to add')
    }

    return ''
})

const applyFilters = () => {
    router.get(route('admin.users.index'), {
        status: form.status,
        plan: form.plan,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const toggleAll = (event: Event) => {
    const target = event.target as HTMLInputElement

    if (target.checked) {
        const visibleIds = filteredUsers.value.map((user) => user.id)
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds]))
        return
    }

    const visibleIds = new Set(filteredUsers.value.map((user) => user.id))
    selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id))
}

const formatCredits = (value: number | string) => {
    const numericValue = typeof value === 'number' ? value : Number(value)

    return Number.isFinite(numericValue) ? numericValue.toFixed(2) : '0.00'
}

const getBulkActionLabel = (action: string) => {
    return bulkActionOptions.value.find((item) => item.value === action)?.label ?? action
}

const openConfirmModal = (config: Omit<ConfirmModalState, 'open' | 'processing'>) => {
    confirmModal.value = {
        ...config,
        open: true,
        processing: false,
    }
}

const resetCreateForm = () => {
    createForm.reset()
    createForm.clearErrors()
    createForm.credits = '0'
    createForm.plan_id = ''
    createForm.is_active = true
    showCreatePassword.value = false
    showCreatePasswordConfirmation.value = false
}

const openCreateModal = () => {
    resetCreateForm()
    showCreateModal.value = true
}

const closeCreateModal = (force = false) => {
    if (createForm.processing && !force) {
        return
    }

    showCreateModal.value = false
    resetCreateForm()
}

const closeConfirmModal = (force = false) => {
    if (confirmModal.value.processing && !force) {
        return
    }

    confirmModal.value = {
        open: false,
        title: '',
        message: '',
        confirmLabel: '',
        processingLabel: '',
        processing: false,
        variant: 'primary',
        action: null,
    }
}

const runConfirmedAction = () => {
    confirmModal.value.processing = true
    confirmModal.value.action?.()
}

const applyBulkAction = () => {
    if (isApplyDisabled.value) {
        return
    }

    const action = String(bulkAction.value)
    const value = action === 'add_credits' ? bulkCredits.value.trim() : ''

    openConfirmModal({
        title: action === 'delete' ? t('Delete selected users?') : t('Apply bulk action?'),
        message: t('Run ":action" for :count selected users?', {
            action: getBulkActionLabel(action),
            count: selectedIds.value.length,
        }),
        confirmLabel: action === 'delete' ? t('Delete') : t('Apply'),
        processingLabel: action === 'delete' ? t('Deleting...') : t('Applying...'),
        variant: action === 'delete' ? 'danger' : 'primary',
        action: () => {
            bulkForm.ids = [...selectedIds.value]
            bulkForm.action = action
            bulkForm.value = value

            bulkForm.post(route('admin.users.bulk'), {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = []
                    bulkAction.value = ''
                    bulkCredits.value = ''
                },
                onFinish: () => {
                    closeConfirmModal(true)
                },
            })
        },
    })
}

const submitCreateUser = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal(true)
        },
    })
}

const impersonateUser = (user: UserItem) => {
    openConfirmModal({
        title: t('Login as user?'),
        message: t('You will be signed in to the user account for :name.', { name: user.name }),
        confirmLabel: t('Login as User'),
        processingLabel: t('Opening...'),
        variant: 'primary',
        action: () => {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content')

            if (!csrfToken) {
                closeConfirmModal()
                return
            }

            const form = document.createElement('form')
            form.method = 'POST'
            form.action = route('admin.users.impersonate', user.ulid)
            form.target = '_blank'
            form.style.display = 'none'

            const tokenInput = document.createElement('input')
            tokenInput.type = 'hidden'
            tokenInput.name = '_token'
            tokenInput.value = csrfToken

            form.appendChild(tokenInput)
            document.body.appendChild(form)
            form.submit()
            document.body.removeChild(form)
            closeConfirmModal(true)
        },
    })
}
</script>
