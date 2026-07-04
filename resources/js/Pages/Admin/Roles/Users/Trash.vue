<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useTranslate } from '@/Composables/useTranslate'
import { useAdminCan } from '@/Composables/useAdminCan'

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
    deleted_at: string
    plan: UserPlan | null
    profession?: string | null
    country?: string | null
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
    search?: string | null
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
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { canAny, isSuperAdmin } = useAdminCan()

const form = useForm({
    status: props.filters.status !== undefined && props.filters.status !== null ? String(props.filters.status) : '',
    plan: props.filters.plan !== undefined && props.filters.plan !== null ? String(props.filters.plan) : '',
})

const bulkForm = useForm({
    ids: [] as number[],
    action: '',
})

const searchQuery = ref(props.filters.search || '')
const selectedIds = ref<number[]>([])
const bulkAction = ref<string | number | null>('')

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

const bulkActionOptions = computed(() => [
    { value: 'restore', label: t('Restore Users') },
    // Permanent deletion is irreversible — Super Admins only.
    ...(isSuperAdmin.value ? [{ value: 'force_delete', label: t('Permanently Delete Users') }] : []),
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
    return props.users.data
})

const isAllSelected = computed(() => {
    return filteredUsers.value.length > 0 && filteredUsers.value.every((user) => selectedIds.value.includes(user.id))
})

const isApplyDisabled = computed(() => {
    return selectedIds.value.length === 0 || !bulkAction.value
})

const applyDisabledReason = computed(() => {
    if (selectedIds.value.length === 0) {
        return t('Select at least 1 user to apply')
    }

    if (!bulkAction.value) {
        return t('Select a bulk action')
    }

    return ''
})

const applyFilters = () => {
    const params: Record<string, string> = {}

    if (form.status) {
        params.status = String(form.status)
    }

    if (form.plan) {
        params.plan = String(form.plan)
    }

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }

    router.get(route('admin.users.trash'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

let searchTimeout: any = null
watch(searchQuery, (newVal) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 350)
})

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

const submitBulkAction = (action: string) => {
    bulkForm.ids = [...selectedIds.value]
    bulkForm.action = action

    bulkForm.post(route('admin.users.trash.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = []
            bulkAction.value = ''
        },
        onFinish: () => {
            closeConfirmModal(true)
        },
    })
}

const applyBulkAction = () => {
    if (isApplyDisabled.value) {
        return
    }

    const action = String(bulkAction.value)
    const isDanger = action === 'force_delete'

    openConfirmModal({
        title: isDanger ? t('Permanently delete selected users?') : t('Restore selected users?'),
        message: t('Run ":action" for :count selected users?', {
            action: getBulkActionLabel(action),
            count: selectedIds.value.length,
        }),
        confirmLabel: isDanger ? t('Delete Permanently') : t('Restore'),
        processingLabel: isDanger ? t('Deleting...') : t('Restoring...'),
        variant: isDanger ? 'danger' : 'primary',
        action: () => submitBulkAction(action),
    })
}

const restoreUser = (user: UserItem) => {
    openConfirmModal({
        title: t('Restore user?'),
        message: t('Restore :name back to active user management?', { name: user.name }),
        confirmLabel: t('Restore'),
        processingLabel: t('Restoring...'),
        variant: 'primary',
        action: () => {
            router.post(route('admin.users.restore', user.ulid), {}, {
                preserveScroll: true,
                onFinish: () => {
                    closeConfirmModal(true)
                },
            })
        },
    })
}

const forceDeleteUser = (user: UserItem) => {
    openConfirmModal({
        title: t('Permanently delete user?'),
        message: t('Permanently delete :name? This action cannot be undone.', { name: user.name }),
        confirmLabel: t('Delete Permanently'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.users.force-delete', user.ulid), {
                preserveScroll: true,
                onFinish: () => {
                    closeConfirmModal(true)
                },
            })
        },
    })
}
</script>

<template>
    <Head :title="t('User Trash')" />

    <AdminLayout>
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 items-start sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('User Trash') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Restore deleted users or permanently remove them from the platform.') }}
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="route('admin.users.index')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back to Users') }}
                    </Link>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[240px]">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Filter this table by name, email, or ULID...')"
                                />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    @click="searchQuery = ''"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                                <AppSelect
                                    v-model="form.status"
                                    :options="statusOptions"
                                    :placeholder="t('All Status')"
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-52 lg:flex-none">
                                <AppSelect
                                    v-model="form.plan"
                                    :options="planOptions"
                                    :placeholder="t('All Plans')"
                                    live-search
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <template v-if="selectedIds.length > 0 && canAny(['users.delete', 'users.manage'])">
                                <span class="text-sm text-gray-500 dark:text-gray-400 sm:whitespace-nowrap">
                                    {{ t(':count selected', { count: selectedIds.length }) }}
                                </span>

                                <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-56 lg:flex-none">
                                    <AppSelect
                                        v-model="bulkAction"
                                        :options="bulkActionOptions"
                                        :placeholder="t('Bulk Actions')"
                                    />
                                </div>

                                <div :title="applyDisabledReason" class="w-full sm:w-auto">
                                    <button
                                        type="button"
                                        class="btn-primary w-full sm:w-auto rounded-xl px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
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

                <div class="overflow-hidden rounded-b-2xl">
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
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Credits') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Plan') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Deleted') }}</th>
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
                                                <div class="flex flex-wrap items-center gap-x-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="truncate">{{ user.email }}</span>
                                                    <span v-if="user.profession" class="text-gray-300 dark:text-gray-600">•</span>
                                                    <span v-if="user.profession" class="truncate text-gray-400 dark:text-gray-500">{{ user.profession }}</span>
                                                    <span v-if="user.country" class="text-gray-300 dark:text-gray-600">•</span>
                                                    <span v-if="user.country" class="font-semibold text-gray-400 dark:text-gray-500">{{ user.country }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-mono text-gray-900 dark:text-white">{{ formatCredits(user.credits) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
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
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="user.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            {{ user.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(user.deleted_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div v-if="canAny(['users.delete', 'users.manage'])" class="inline-flex items-center gap-2">
                                            <Tooltip :content="t('Restore user')" placement="top">
                                                <button
                                                    type="button"
                                                    :aria-label="t('Restore user')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                                    @click="restoreUser(user)"
                                                >
                                                    <i class="ti ti-restore text-base"></i>
                                                </button>
                                            </Tooltip>

                                            <Tooltip v-if="isSuperAdmin" :content="t('Permanently delete user')" placement="top">
                                                <button
                                                    type="button"
                                                    :aria-label="t('Permanently delete user')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                    @click="forceDeleteUser(user)"
                                                >
                                                    <i class="ti ti-trash-x text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No trashed users found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                        <Pagination
                            :links="users.links"
                            :from="users.from"
                            :to="users.to"
                            :total="users.total"
                        />
                    </div>
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
    </AdminLayout>
</template>
