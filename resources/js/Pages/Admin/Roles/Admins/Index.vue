<template>
    <Head :title="t('Administrators')" />

    <AdminLayout>
        <div class="py-6">
            <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
                <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('Administrators') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Manage administrator accounts, roles, and access status.') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="route('admin.roles.index')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <i class="ti ti-shield-lock text-base"></i>
                            {{ t('Manage Roles') }}
                        </Link>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary"
                            @click="openModal()"
                        >
                            <i class="ti ti-plus text-base"></i>
                            {{ t('Add Admin') }}
                        </button>

                        <Link
                            v-if="hasTrashedAdmins"
                            :href="route('admin.admins.trash')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <i class="ti ti-trash text-base"></i>
                            {{ t('Trash') }}
                        </Link>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="w-full xl:max-w-md">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                        <i class="ti ti-search text-base"></i>
                                    </span>
                                    <input
                                        ref="searchInputRef"
                                        v-model="searchQuery"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="t('Filter this table by name, email, or role...')"
                                    />
                                    <span
                                        v-if="!searchQuery"
                                        class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
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
                            </div>

                            <div class="flex flex-col gap-4 xl:ml-auto xl:flex-row xl:items-center xl:justify-end">
                                <div class="w-full md:w-52">
                                    <AppSelect
                                        v-model="filtersForm.status"
                                        :options="statusOptions"
                                        :placeholder="t('All Status')"
                                        @update:model-value="applyFilters"
                                    />
                                </div>

                                <div class="w-full md:w-56">
                                    <AppSelect
                                        v-model="filtersForm.role"
                                        :options="roleOptions"
                                        :placeholder="t('All Roles')"
                                        live-search
                                        @update:model-value="applyFilters"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">{{ t('Administrator') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Role') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Status') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Last Login') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="admin in filteredAdmins"
                                    :key="admin.id"
                                    class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                                >
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
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="admin.role?.slug === 'super-admin' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'"
                                        >
                                            {{ admin.role?.name ?? t('No Role') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="admin.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            {{ admin.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ admin.last_login_at ? formatDate(admin.last_login_at) : t('Never') }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <Tooltip :content="t('Edit administrator')" placement="top">
                                                <button
                                                    type="button"
                                                    :aria-label="t('Edit administrator')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                                    @click="openModal(admin)"
                                                >
                                                    <i class="ti ti-edit text-base"></i>
                                                </button>
                                            </Tooltip>

                                            <Tooltip :content="t('Delete administrator')" placement="top">
                                                <button
                                                    v-if="admin.role?.slug !== 'super-admin'"
                                                    type="button"
                                                    :aria-label="t('Delete administrator')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                    @click="confirmDelete(admin)"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredAdmins.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No administrators found.') }}
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

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm" @click.self="closeModal()">
            <div class="flex max-h-[90vh] w-full flex-col overflow-visible rounded-2xl bg-white shadow-xl sm:max-w-2xl dark:bg-gray-800">
                <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-100 px-6 py-3 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ isEditing ? t('Edit Administrator') : t('Add Administrator') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ isEditing ? t('Update administrator details and access role.') : t('Create a new administrator account and assign a role.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                        :aria-label="t('Close modal')"
                        @click="() => closeModal()"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="overflow-y-auto p-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Name') }}
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Enter full name')"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-danger-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Email') }}
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Enter email address')"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-danger-600">{{ form.errors.email }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Role') }}
                                </label>
                                <AppSelect
                                    v-model="form.role_id"
                                    :options="roleOptions"
                                    :placeholder="t('Select a role')"
                                    live-search
                                    dropdown-placement="bottom"
                                />
                                <p v-if="form.errors.role_id" class="mt-1 text-xs text-danger-600">{{ form.errors.role_id }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Password') }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="t('Enter password')"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        :aria-label="showPassword ? t('Hide password') : t('Show password')"
                                        @click="showPassword = !showPassword"
                                    >
                                        <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                    </button>
                                </div>
                                <p v-if="isEditing" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Leave blank to keep the current password.') }}</p>
                                <p v-if="form.errors.password" class="mt-1 text-xs text-danger-600">{{ form.errors.password }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('Confirm Password') }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.password_confirmation"
                                        :type="showPasswordConfirmation ? 'text' : 'password'"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        :placeholder="t('Confirm password')"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                        :aria-label="showPasswordConfirmation ? t('Hide password confirmation') : t('Show password confirmation')"
                                        @click="showPasswordConfirmation = !showPasswordConfirmation"
                                    >
                                        <i :class="showPasswordConfirmation ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="md:col-span-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="app-switch"
                                        :aria-checked="form.is_active"
                                        @click="form.is_active = !form.is_active"
                                    >
                                        <span class="app-switch__thumb"></span>
                                    </button>

                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                            {{ form.is_active ? t('Administrator account is active') : t('Administrator account is disabled') }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ t('Inactive administrators cannot sign in until you enable their account.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-3 dark:border-surface-800 dark:bg-surface-800/50">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                        @click="() => closeModal()"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? t('Saving...') : t('Save Changes') }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useTranslate } from '@/Composables/useTranslate'

interface Role {
    id: number
    name: string
    slug: string
}

interface AdminItem {
    id: number
    name: string
    email: string
    role_id: number | null
    is_active: boolean
    last_login_at: string | null
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
    hasTrashedAdmins: boolean
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const page = usePage()
const isSuperAdmin = computed(() => (page.props.admin as any)?.isSuperAdmin ?? false)

const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const showModal = ref(false)
const isEditing = ref(false)
const currentAdmin = ref<AdminItem | null>(null)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const filtersForm = useForm({
    status: props.filters.status !== undefined && props.filters.status !== null ? String(props.filters.status) : '',
    role: props.filters.role !== undefined && props.filters.role !== null ? String(props.filters.role) : '',
})

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    is_active: true,
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

const filteredAdmins = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.admins.data
    }

    return props.admins.data.filter((admin) => {
        return [
            admin.name,
            admin.email,
            admin.role?.name ?? '',
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const applyFilters = () => {
    const params: Record<string, string> = {}

    if (filtersForm.status) {
        params.status = String(filtersForm.status)
    }

    if (filtersForm.role) {
        params.role = String(filtersForm.role)
    }

    router.get(route('admin.admins.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const clearSearchAndFilters = () => {
    searchQuery.value = ''

    const hadServerFilters = Boolean(filtersForm.status || filtersForm.role)

    filtersForm.status = ''
    filtersForm.role = ''

    if (hadServerFilters) {
        applyFilters()
    }
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

    if (showModal.value || confirmModal.value.open) {
        return
    }

    if (!searchQuery.value && !filtersForm.status && !filtersForm.role) {
        return
    }

    event.preventDefault()
    clearSearchAndFilters()
}

onMounted(() => {
    document.addEventListener('keydown', focusSearchOnSlash)
    document.addEventListener('keydown', clearSearchOnEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', focusSearchOnSlash)
    document.removeEventListener('keydown', clearSearchOnEscape)
})

const resetModalState = () => {
    showPassword.value = false
    showPasswordConfirmation.value = false
}

const openModal = (admin: AdminItem | null = null) => {
    isEditing.value = admin !== null
    currentAdmin.value = admin
    form.reset()
    form.clearErrors()
    resetModalState()

    if (admin) {
        form.name = admin.name
        form.email = admin.email
        form.role_id = admin.role_id ? String(admin.role_id) : ''
        form.is_active = admin.is_active
    } else {
        form.is_active = true
    }

    showModal.value = true
}

const closeModal = (force = false) => {
    if (form.processing && !force) {
        return
    }

    showModal.value = false
    form.reset()
    form.clearErrors()
    currentAdmin.value = null
    resetModalState()
}

const submit = () => {
    if (isEditing.value && currentAdmin.value) {
        form.post(route('admin.admins.update', currentAdmin.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(true),
        })
        return
    }

    form.post(route('admin.admins.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(true),
    })
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

const confirmDelete = (admin: AdminItem) => {
    openConfirmModal({
        title: t('Delete administrator?'),
        message: t('Delete administrator :name? This action cannot be undone.', { name: admin.name }),
        confirmLabel: t('Delete'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.admins.delete', admin.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}
</script>
