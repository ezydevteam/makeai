<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface RolePermissionRef {
    id: number
}

interface RoleItem {
    id: number
    name: string
    slug: string
    description: string | null
    is_system: boolean
    admins_count: number
    permissions: RolePermissionRef[]
    default_permission_slugs: string[]
    has_default_permissions: boolean
}

interface PermissionItem {
    id: number
    slug: string
    name: string
    group: string
}

type PermissionsPayload = Record<string, PermissionItem[]>

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
    roles: RoleItem[]
    permissions: PermissionsPayload
}>()

const { t } = useTranslate()

const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const roleFilter = ref('')
const showModal = ref(false)
const isEditing = ref(false)
const currentRole = ref<RoleItem | null>(null)
const restoreDefaultApplied = ref(false)

const form = useForm({
    name: '',
    description: '',
    permissions: [] as number[],
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

const allPermissions = computed(() => Object.values(props.permissions).flat())
const allPermissionIds = computed(() => allPermissions.value.map((permission) => permission.id))
const areAllPermissionsSelected = computed(() => {
    if (currentRole.value?.slug === 'super-admin') {
        return true
    }

    if (allPermissionIds.value.length === 0) {
        return false
    }

    return allPermissionIds.value.every((id) => form.permissions.includes(id))
})

const roleFilterOptions = computed(() => [
    { value: '', label: t('All Role Types') },
    { value: 'system', label: t('System Roles') },
    { value: 'custom', label: t('Custom Roles') },
    { value: 'default', label: t('Default Permission Roles') },
    { value: 'manual', label: t('Manual Permission Roles') },
])

const permissionGroups = computed(() => {
    return Object.entries(props.permissions).map(([key, items]) => ({
        key,
        label: key.replace(/_/g, ' '),
        items,
    }))
})

const filteredRoles = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.roles.filter((role) => {
        const matchesQuery = !query || [
            role.name,
            role.slug,
            role.description ?? '',
        ].some((value) => value.toLowerCase().includes(query))

        const matchesFilter =
            roleFilter.value === ''
            || (roleFilter.value === 'system' && role.is_system)
            || (roleFilter.value === 'custom' && !role.is_system)
            || (roleFilter.value === 'default' && role.has_default_permissions)
            || (roleFilter.value === 'manual' && !role.has_default_permissions)

        return matchesQuery && matchesFilter
    })
})

const clearSearchAndFilters = () => {
    searchQuery.value = ''
    roleFilter.value = ''
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

    if (!searchQuery.value && !roleFilter.value) {
        return
    }

    event.preventDefault()
    clearSearchAndFilters()
}

const resetForm = () => {
    form.reset()
    form.clearErrors()
    form.permissions = []
}

const openModal = (role: RoleItem | null = null) => {
    isEditing.value = role !== null
    currentRole.value = role
    restoreDefaultApplied.value = false
    resetForm()

    if (role) {
        form.name = role.name
        form.description = role.description ?? ''
        form.permissions = role.permissions.map((permission) => permission.id)
    }

    showModal.value = true
}

const closeModal = (force = false) => {
    if (form.processing && !force) {
        return
    }

    showModal.value = false
    currentRole.value = null
    restoreDefaultApplied.value = false
    resetForm()
}

const isPermissionSelected = (id: number) => {
    if (currentRole.value?.slug === 'super-admin') {
        return true
    }

    return form.permissions.includes(id)
}

const togglePermission = (id: number) => {
    if (currentRole.value?.slug === 'super-admin') {
        return
    }

    const index = form.permissions.indexOf(id)

    if (index === -1) {
        form.permissions.push(id)
        return
    }

    form.permissions.splice(index, 1)
}

const selectAllPermissions = () => {
    if (currentRole.value?.slug === 'super-admin') {
        return
    }

    restoreDefaultApplied.value = false
    form.permissions = [...allPermissionIds.value]
}

const deselectAllPermissions = () => {
    if (currentRole.value?.slug === 'super-admin') {
        return
    }

    restoreDefaultApplied.value = false
    form.permissions = []
}

const toggleAllPermissions = () => {
    if (areAllPermissionsSelected.value) {
        deselectAllPermissions()
        return
    }

    selectAllPermissions()
}

const restoreDefaultPermissions = () => {
    if (!currentRole.value?.has_default_permissions) {
        return
    }

    const defaultSlugs = new Set(currentRole.value.default_permission_slugs)
    form.permissions = allPermissions.value
        .filter((permission) => defaultSlugs.has(permission.slug))
        .map((permission) => permission.id)
    restoreDefaultApplied.value = true
}

const submit = () => {
    if (isEditing.value && currentRole.value) {
        form.post(route('admin.roles.update', currentRole.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(true),
        })
        return
    }

    form.post(route('admin.roles.store'), {
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

const confirmDelete = (role: RoleItem) => {
    if (role.admins_count > 0) {
        openConfirmModal({
            title: t('Role in use'),
            message: t('This role cannot be deleted because it is assigned to administrators.'),
            confirmLabel: t('Okay'),
            processingLabel: t('Closing...'),
            variant: 'primary',
            action: () => closeConfirmModal(true),
        })
        return
    }

    openConfirmModal({
        title: t('Delete role?'),
        message: t('Delete role :name? This action cannot be undone.', { name: role.name }),
        confirmLabel: t('Delete'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.roles.delete', role.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
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
    <Head :title="t('Roles & Permissions')" />

    <AdminLayout>
        <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Roles & Permissions') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage administrator roles, assign permissions, and restore seeded defaults.') }}
                    </p>
                </div>

                <div class="shrink-0 flex gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="router.visit(route('admin.admins.index'))"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 btn-primary-admin"
                        @click="openModal()"
                    >
                        <i class="ti ti-plus text-base"></i>
                        {{ t('Create Role') }}
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[240px] md:max-w-sm">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Filter this table by role name, slug, or description...')"
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

                        <div class="w-full sm:w-52 md:w-56">
                            <AppSelect
                                v-model="roleFilter"
                                :options="roleFilterOptions"
                                :placeholder="t('All Role Types')"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-b-2xl">
                    <div v-if="filteredRoles.length > 0" class="p-4 sm:p-6">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            <div
                                v-for="role in filteredRoles"
                                :key="role.id"
                                class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-900/40 dark:hover:border-primary-900/40"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ role.name }}</h3>
                                            <span
                                                v-if="role.is_system"
                                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                            >
                                                {{ t('System') }}
                                            </span>
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="role.has_default_permissions ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                            >
                                                {{ role.has_default_permissions ? t('Default Role') : t('Custom Role') }}
                                            </span>
                                        </div>

                                        <p class="mt-3 min-h-[2.5rem] text-sm text-gray-500 dark:text-gray-400">
                                            {{ role.description || t('No description provided.') }}
                                        </p>
                                    </div>

                                    <div class="inline-flex items-center gap-2">
                                        <Tooltip v-if="!role.is_system" :content="t('Delete role')" placement="top">
                                            <button
                                                type="button"
                                                :aria-label="t('Delete role')"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                @click="confirmDelete(role)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </Tooltip>
                                    </div>
                                </div>

                                <div class="mt-5 grid grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/60">
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">
                                            {{ t('Admins') }}
                                        </p>
                                        <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                                            {{ role.admins_count }}
                                        </p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/60">
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">
                                            {{ t('Permissions') }}
                                        </p>
                                        <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                                            {{ role.permissions.length }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 flex items-center justify-between rounded-xl border border-primary-100 bg-primary-50/70 px-4 py-3 dark:border-primary-900/30 dark:bg-primary-900/10">
                                    <div class="min-w-0 flex-1 pr-2">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {{ role.has_default_permissions ? t('Manage permission for ' + role.name) : t('Custom permission set') }}
                                        </p>
                                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ role.has_default_permissions ? t('This role can be restored to its default permission bundle.') : t('This role uses a manually maintained permission bundle.') }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="rounded-xl border border-primary-200 bg-white px-3 py-2 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-50 dark:border-primary-900/40 dark:bg-surface-900 dark:text-primary-300 dark:hover:bg-primary-900/20 shrink-0"
                                        @click="openModal(role)"
                                    >
                                        {{ t('Manage') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="px-6 py-14 text-center"
                    >
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500">
                            <i class="ti ti-shield-lock text-xl"></i>
                        </div>
                        <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('No roles found.') }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Try a different filter or create a new role.') }}
                        </p>
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
            :open="showModal"
            max-width="max-w-6xl"
            :title="isEditing ? t('Edit Role') : t('Create Role')"
            :subtitle="isEditing ? t('Update role details and permission coverage.') : t('Create a new role and assign its permission set.')"
            has-form
            :confirm-text="isEditing ? t('Save Role') : t('Create Role')"
            :confirm-loading="form.processing"
            confirm-loading-text="Saving..."
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Role Name') }}
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :disabled="currentRole?.is_system"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :placeholder="t('Enter role name')"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Description') }}
                    </label>
                    <input
                        v-model="form.description"
                        type="text"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :placeholder="t('Enter a short description')"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-500">{{ form.errors.description }}</p>
                </div>
            </div>

            <div :class="isEditing && currentRole?.slug === 'super-admin' ? 'hidden' : '' " class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/40 md:flex-row md:items-center md:justify-between mt-6">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Permission Actions') }}</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('Use quick actions to select all permissions or restore the seeded default set for built-in roles.') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                        @click="toggleAllPermissions"
                    >
                        {{ areAllPermissionsSelected ? t('Deselect All') : t('Select All') }}
                    </button>

                    <button
                        type="button"
                        v-if="currentRole?.has_default_permissions"
                        class="rounded-xl px-3 py-2 text-sm font-medium transition-colors"
                        :class="restoreDefaultApplied
                            ? 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/30'
                            : 'border border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30'"
                        @click="restoreDefaultPermissions"
                    >
                        {{ restoreDefaultApplied ? t('Default Restored') : t('Restore Default') }}
                    </button>
                </div>
            </div>

            <div
                v-if="currentRole?.slug === 'super-admin'"
                class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-indigo-700 dark:border-indigo-900/30 dark:bg-indigo-900/20 dark:text-indigo-300 mt-6"
            >
                <p class="text-sm font-medium">{{ t('Super Admin bypasses all permission checks automatically.') }}</p>
                <p class="mt-1 text-xs">{{ t('The permission grid remains visible for reference, but this role already has full access.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3 mt-6">
                <div
                    v-for="group in permissionGroups"
                    :key="group.key"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h5 class="text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                            {{ group.label }}
                        </h5>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ group.items.length }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <label
                            v-for="permission in group.items"
                            :key="permission.id"
                            class="flex cursor-pointer items-start gap-3"
                        >
                            <input
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:!border-gray-700 dark:bg-gray-700"
                                :checked="isPermissionSelected(permission.id)"
                                :disabled="currentRole?.slug === 'super-admin'"
                                @change="togglePermission(permission.id)"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ permission.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ permission.slug }}
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </AppModal>
    </AdminLayout>
</template>
