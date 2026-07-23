<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useTranslate } from '@/Composables/useTranslate'
import { useAdminCan } from '@/Composables/useAdminCan'

interface Role {
    id: number
    name: string
    slug: string
}

interface AdminItem {
    id: number
    name: string
    email: string
    is_active: boolean
    deleted_at: string
    role: Role | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface AdminsResponse {
    data: AdminItem[]
    links: PaginationLink[]
    from?: number
    to?: number
    total?: number
}

interface Filters {
    status?: string | number | null
    role?: string | number | null
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
    admins: AdminsResponse
    roles: Role[]
    filters: Filters
}>()

const { t } = useTranslate()
const { isSuperAdmin } = useAdminCan()
const { formatDate } = useDateFormat()

const searchQuery = ref(props.filters.search || '')
const selectedIds = ref<number[]>([])
const bulkAction = ref<string | number | null>('')

const filtersForm = useForm({
    status: props.filters.status !== undefined && props.filters.status !== null ? String(props.filters.status) : '',
    role: props.filters.role !== undefined && props.filters.role !== null ? String(props.filters.role) : '',
})

const bulkForm = useForm({
    ids: [] as number[],
    action: '',
})

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

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: '1', label: t('Active') },
    { value: '0', label: t('Inactive') },
])

const roleOptions = computed(() => [
    { value: '', label: t('All Roles') },
    ...props.roles.map((role) => ({
        value: String(role.id),
        label: role.name,
    })),
])

const bulkActionOptions = computed(() => [
    { value: 'restore', label: t('Restore') },
    // Permanent deletion is irreversible — Super Admins only.
    ...(isSuperAdmin.value ? [{ value: 'force_delete', label: t('Permanently Delete') }] : []),
])

const filteredAdmins = computed(() => {
    return props.admins.data
})

const isAllSelected = computed(() => {
    return filteredAdmins.value.length > 0 && filteredAdmins.value.every((admin) => selectedIds.value.includes(admin.id))
})

const isApplyDisabled = computed(() => selectedIds.value.length === 0 || !bulkAction.value)

const applyDisabledReason = computed(() => {
    if (selectedIds.value.length === 0) {
        return t('Select at least 1 administrator to apply')
    }

    if (!bulkAction.value) {
        return t('Select a bulk action')
    }

    return ''
})

const applyFilters = () => {
    const params: Record<string, string> = {}

    if (filtersForm.status) {
        params.status = String(filtersForm.status)
    }

    if (filtersForm.role) {
        params.role = String(filtersForm.role)
    }

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }

    router.get(route('admin.admins.trash'), params, {
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
        const visibleIds = filteredAdmins.value.map((admin) => admin.id)
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds]))
        return
    }

    const visibleIds = new Set(filteredAdmins.value.map((admin) => admin.id))
    selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id))
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

    bulkForm.post(route('admin.admins.trash.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = []
            bulkAction.value = ''
        },
        onFinish: () => closeConfirmModal(true),
    })
}

const applyBulkAction = () => {
    if (isApplyDisabled.value) {
        return
    }

    const action = String(bulkAction.value)
    const isDanger = action === 'force_delete'

    openConfirmModal({
        title: isDanger ? t('Permanently delete selected administrators?') : t('Restore selected administrators?'),
        message: t('Run ":action" for :count selected administrators?', {
            action: getBulkActionLabel(action),
            count: selectedIds.value.length,
        }),
        confirmLabel: isDanger ? t('Delete Permanently') : t('Restore'),
        processingLabel: isDanger ? t('Deleting...') : t('Restoring...'),
        variant: isDanger ? 'danger' : 'primary',
        action: () => submitBulkAction(action),
    })
}

const restoreAdmin = (admin: AdminItem) => {
    openConfirmModal({
        title: t('Restore administrator?'),
        message: t('Restore administrator :name back to active admin management?', { name: admin.name }),
        confirmLabel: t('Restore'),
        processingLabel: t('Restoring...'),
        variant: 'primary',
        action: () => {
            router.post(route('admin.admins.restore', admin.id), {}, {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const forceDeleteAdmin = (admin: AdminItem) => {
    openConfirmModal({
        title: t('Permanently delete administrator?'),
        message: t('Permanently delete administrator :name? This action cannot be undone.', { name: admin.name }),
        confirmLabel: t('Delete Permanently'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.admins.force-delete', admin.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}
</script>

<template>
    <Head :title="t('Administrator Trash')" />

    <AdminLayout>
        <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 items-start sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Administrator Trash') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Restore deleted administrators or permanently remove them from admin access.') }}
                    </p>
                </div>

                <Link
                    :href="route('admin.admins.index')"
                    class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back to Admins') }}
                </Link>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[240px] md:max-w-sm">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Filter this table by name, email, or role...')"
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
                                    v-model="filtersForm.status"
                                    :options="statusOptions"
                                    :placeholder="t('All Status')"
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[180px] lg:w-52 lg:flex-none">
                                <AppSelect
                                    v-model="filtersForm.role"
                                    :options="roleOptions"
                                    :placeholder="t('All Roles')"
                                    live-search
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <template v-if="selectedIds.length > 0">
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
                                        class="btn-primary-admin w-full sm:w-auto rounded-xl px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
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
                                                class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:!border-gray-700 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                                :checked="isAllSelected"
                                                @change="toggleAll"
                                            />
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3">{{ t('Administrator') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Role') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Deleted') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="admin in filteredAdmins"
                                    :key="admin.id"
                                    class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                                >
                                    <td class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input
                                                v-model="selectedIds"
                                                type="checkbox"
                                                :value="admin.id"
                                                class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:!border-gray-800 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                                                {{ admin.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ admin.name }}</p>
                                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ admin.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="admin.role?.slug === 'super-admin' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'"
                                        >
                                            {{ admin.role?.name ?? t('No Role') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="admin.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            {{ admin.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(admin.deleted_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <Tooltip :content="t('Restore')" placement="top">
                                                <button
                                                    type="button"
                                                    :aria-label="t('Restore')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                                    @click="restoreAdmin(admin)"
                                                >
                                                    <i class="ti ti-restore text-base"></i>
                                                </button>
                                            </Tooltip>

                                            <Tooltip v-if="isSuperAdmin" :content="t('Permanently delete')" placement="top">
                                                <button
                                                    type="button"
                                                    :aria-label="t('Permanently delete')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                    @click="forceDeleteAdmin(admin)"
                                                >
                                                    <i class="ti ti-trash-x text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredAdmins.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No trashed administrators found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="admins.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                        <Pagination
                            :links="admins.links"
                            :from="admins.from"
                            :to="admins.to"
                            :total="admins.total"
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
