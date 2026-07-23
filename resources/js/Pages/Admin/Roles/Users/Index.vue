<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
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
    created_at: string
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
    hasTrashedUsers: boolean
    stats: {
        total_users: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        new_users: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        active_users: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
        banned_users: { value: number; comparison: { label: string; type: 'up' | 'down' | 'neutral' } }
    }
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { canAny } = useAdminCan()

// Plans are a subscription (Extended License) feature. Hide the assignment on a
// Regular license, matching the standalone create/edit forms (Create.vue, Show.vue)
// and the backend, which nulls plan_id server-side regardless of what the form sends.
const page = usePage()
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

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

const searchQuery = ref(props.filters.search || '')
const searchFocused = ref(false)
const searchInputRef = ref<HTMLInputElement | null>(null)
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
    ...props.plans.map((plan) => ({
        value: String(plan.id),
        label: plan.name,
    })),
])

// Each bulk action is offered only when the admin holds the matching granular
// permission — mirrors the per-sub-action gate enforced server-side.
const bulkActionOptions = computed(() => [
    ...(canAny(['users.edit']) ? [
        { value: 'activate', label: t('Activate Users') },
        { value: 'deactivate', label: t('Deactivate Users') },
    ] : []),
    ...(canAny(['users.credits']) ? [{ value: 'add_credits', label: t('Add Credits') }] : []),
    ...(canAny(['users.delete']) ? [{ value: 'delete', label: t('Delete Users') }] : []),
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
    if (selectedIds.value.length === 0 || !bulkAction.value) {
        return true
    }

    if (bulkAction.value === 'add_credits' && !bulkCredits.value.trim()) {
        return true
    }

    return false
})

const hasActiveFilters = computed(() => Boolean(searchQuery.value || form.status || form.plan))

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

    router.get(route('admin.users.index'), params, {
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

const clearSearchAndFilters = () => {
    form.status = ''
    form.plan = ''
    searchQuery.value = ''
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

    if (showCreateModal.value || confirmModal.value.open) {
        return
    }

    if (document.activeElement === searchInputRef.value) {
        event.preventDefault()
        searchQuery.value = ''
        applyFilters()
        searchInputRef.value?.blur()
        return
    }

    if (!searchQuery.value && !form.status && !form.plan) {
        return
    }

    event.preventDefault()
    clearSearchAndFilters()
    applyFilters()
}

onMounted(() => {
    document.addEventListener('keydown', focusSearchOnSlash)
    document.addEventListener('keydown', clearSearchOnEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', focusSearchOnSlash)
    document.removeEventListener('keydown', clearSearchOnEscape)
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

const deleteUser = (user: UserItem) => {
    openConfirmModal({
        title: t('Delete User'),
        message: t('Are you sure you want to move user ":name" to trash?', { name: user.name }),
        confirmLabel: t('Delete'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.users.delete', user.ulid), {
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
    <Head :title="t('User Management')" />

    <AdminLayout>
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('User Management') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage platform users, credits, and subscription states.') }}
                    </p>
                </div>

                <div class="shrink-0 flex gap-3">
                    <!-- Native <a>, not Inertia <Link>: the endpoint streams a CSV file
                         download, which an Inertia XHR visit can't handle. -->
                    <a v-if="canAny(['users.manage'])"
                        :href="route('admin.users.export')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
                    >
                            <i class="ti ti-file-export text-base"></i>
                            {{ t('Export') }}
                     </a>

                    <Link
                        v-if="hasTrashedUsers"
                        :href="route('admin.users.trash')"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:hover:!border-red-900/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300`"
                    >
                        <i class="ti ti-trash text-base"></i>
                        {{ t('Trash') }}
                    </Link>

                     <button
                        v-if="canAny(['users.create'])"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 btn-primary-admin"
                        @click="openCreateModal"
                    >
                        <i class="ti ti-plus text-base"></i>
                        {{ t('Create User') }}
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div v-if="stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    :title="t('Total Users')"
                    :value="stats.total_users.value"
                    :comparison="stats.total_users.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.total_users.comparison.type"
                    color="primary"
                >
                    <template #icon>
                        <i class="ti ti-users text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('New Users (7d)')"
                    :value="stats.new_users.value"
                    :comparison="stats.new_users.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.new_users.comparison.type"
                    color="accent"
                >
                    <template #icon>
                        <i class="ti ti-user-plus text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Active (7d)')"
                    :value="stats.active_users.value"
                    :comparison="stats.active_users.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.active_users.comparison.type"
                    color="success"
                >
                    <template #icon>
                        <i class="ti ti-user-check text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Banned Users')"
                    :value="stats.banned_users.value"
                    :comparison="stats.banned_users.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="stats.banned_users.comparison.type"
                    color="danger"
                >
                    <template #icon>
                        <i class="ti ti-user-x text-lg"></i>
                    </template>
                </StatsCard>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 md:flex-row md:flex-wrap md:items-center md:justify-between">
                        <div class="flex-1 w-full min-w-[220px] md:max-w-sm relative">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    :placeholder="t('Search users...')"
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
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear search')"
                                    :title="t('Clear search')"
                                    @click="searchQuery = ''"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto justify-start lg:justify-end shrink-0">
                            <div class="sm:w-44">
                                <AppSelect
                                    v-model="form.status"
                                    :options="statusOptions"
                                    :placeholder="t('All Status')"
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <div v-if="isProAvailable" class="sm:w-52">
                                <AppSelect
                                    v-model="form.plan"
                                    :options="planOptions"
                                    :placeholder="t('All Plans')"
                                    live-search
                                    @update:model-value="applyFilters"
                                />
                            </div>

                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 w-full sm:w-auto"
                                @click="() => { clearSearchAndFilters(); applyFilters(); }"
                            >
                                <i class="ti ti-rotate-clockwise text-base"></i>
                                {{ t('Reset') }}
                            </button>

                            <template v-if="selectedIds.length > 0 && bulkActionOptions.length > 0">
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

                                <input
                                    v-if="bulkAction === 'add_credits'"
                                    v-model="bulkCredits"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white sm:flex-grow sm:flex-1 sm:min-w-[100px] lg:w-32 lg:flex-none"
                                    :placeholder="t('Credits')"
                                />

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
                                                class="h-4 w-4 rounded border-gray-200 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:!border-gray-700 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                                :checked="isAllSelected"
                                                @change="toggleAll"
                                            />
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3">{{ t('User') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Credits') }}</th>
                                    <th v-if="isProAvailable" scope="col" class="px-6 py-3 text-center">{{ t('Plan') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-center">{{ t('Joined') }}</th>
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
                                                class="h-4 w-4 rounded border-gray-200 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
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
                                    <td v-if="isProAvailable" class="px-6 py-4 text-center">
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
                                    <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="overflow-visible px-6 py-4 text-right">
                                        <TableActionMenu>
                                            <template #default="{ close }">
                                                <Link
                                                    :href="route('admin.users.show', user.ulid)"
                                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                    @click="close"
                                                >
                                                    <i class="ti ti-edit text-base" />
                                                    <span>{{ t('Edit User') }}</span>
                                                </Link>

                                                <button
                                                    v-if="canAny(['users.impersonate'])"
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                                    @click="impersonateUser(user); close()"
                                                >
                                                    <i class="ti ti-user-share text-base" />
                                                    <span>{{ t('Login as User') }}</span>
                                                </button>

                                                <template v-if="canAny(['users.delete'])">
                                                    <hr class="my-1 border-gray-200 dark:border-surface-700" />
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                                        @click="deleteUser(user); close()"
                                                    >
                                                        <i class="ti ti-trash text-base" />
                                                        <span>{{ t('Delete') }}</span>
                                                    </button>
                                                </template>
                                            </template>
                                        </TableActionMenu>
                                    </td>
                                </tr>

                                <tr v-if="filteredUsers.length === 0">
                                    <td :colspan="isProAvailable ? 7 : 6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No users found.') }}
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

        <AppModal
            :open="showCreateModal"
            max-width="max-w-3xl"
            :title="t('Create User')"
            :subtitle="t('Add a new platform user, assign credits, and set an optional plan.')"
            has-form
            :confirm-text="t('Create User')"
            :confirm-loading="createForm.processing"
            confirm-loading-text="Creating..."
            @close="closeCreateModal"
            @submit="submitCreateUser"
        >
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

                <div v-if="isProAvailable" class="block">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ t('Choose Plan') }}
                    </span>
                    <AppSelect
                        v-model="createForm.plan_id"
                        :options="createPlanOptions"
                        :placeholder="t('Free Plan')"
                        dropdown-placement="bottom"
                        live-search
                    />
                    <p v-if="createForm.errors.plan_id" class="mt-1 text-xs text-danger-600">{{ createForm.errors.plan_id }}</p>
                </div>

                <div :class="isProAvailable ? 'md:col-span-2' : ''" class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/40 mt-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('Status') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ createForm.is_active ? t('User account is active') : t('User account is disabled') }}

                            </p>
                        </div>

                        <AppSwitch v-model="createForm.is_active" />
                    </div>
                </div>
            </div>
        </AppModal>
    </AdminLayout>
</template>
