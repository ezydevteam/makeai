<template>
    <Head :title="t('Roles & Permissions')" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ t('Roles & Permissions') }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Manage administrator roles, assign permissions, and restore seeded defaults.') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="router.visit(route('admin.admins.index'))"
                            >
                                <i class="ti ti-arrow-left text-base"></i>
                                {{ t('Back to Admins') }}
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary"
                                @click="openModal()"
                            >
                                <i class="ti ti-plus text-base"></i>
                                {{ t('Create Role') }}
                            </button>
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
                                    :placeholder="t('Filter this table by role name, slug, or description...')"
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
                    </div>
                </div>

                <div v-if="filteredRoles.length > 0" class="grid grid-cols-1 gap-4 xl:grid-cols-2 2xl:grid-cols-3">
                    <div
                        v-for="role in filteredRoles"
                        :key="role.id"
                        class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-800 dark:hover:border-primary-900/40"
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
                                <Tooltip :content="t('Edit role')" placement="top">
                                    <button
                                        type="button"
                                        :aria-label="t('Edit role')"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                        @click="openModal(role)"
                                    >
                                        <i class="ti ti-edit text-base"></i>
                                    </button>
                                </Tooltip>

                                <Tooltip v-if="!role.is_system" :content="t('Delete role')" placement="top">
                                    <button
                                        type="button"
                                        :aria-label="t('Delete role')"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
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
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ role.has_default_permissions ? t('Seeded permission set available') : t('Custom permission set') }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ role.has_default_permissions ? t('This role can be restored to its default permission bundle.') : t('This role uses a manually maintained permission bundle.') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm font-medium text-primary-700 transition-colors hover:bg-primary-50 dark:border-primary-900/40 dark:bg-surface-900 dark:text-primary-300 dark:hover:bg-primary-900/20"
                                @click="openModal(role)"
                            >
                                {{ t('Manage') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-14 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800"
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

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ isEditing ? t('Edit Role') : t('Create Role') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ isEditing ? t('Update role details and permission coverage.') : t('Create a new role and assign its permission set.') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                        :aria-label="t('Close modal')"
                        @click="() => closeModal()"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="overflow-y-auto p-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Role Name') }}
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :disabled="currentRole?.is_system"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
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
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    :placeholder="t('Enter a short description')"
                                />
                                <p v-if="form.errors.description" class="mt-1 text-sm text-red-500">{{ form.errors.description }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/40 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Permission Actions') }}</h4>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Use quick actions to select all permissions or restore the seeded default set for built-in roles.') }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                                    @click="selectAllPermissions"
                                >
                                    {{ t('Select All') }}
                                </button>

                                <button
                                    v-if="currentRole?.has_default_permissions"
                                    type="button"
                                    class="rounded-lg px-3 py-2 text-sm font-medium transition-colors"
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
                            class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-indigo-700 dark:border-indigo-900/30 dark:bg-indigo-900/20 dark:text-indigo-300"
                        >
                            <p class="text-sm font-medium">{{ t('Super Admin bypasses all permission checks automatically.') }}</p>
                            <p class="mt-1 text-xs">{{ t('The permission grid remains visible for reference, but this role already has full access.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
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
                                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
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
                    </form>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800/50">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                        @click="() => closeModal()"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="btn-primary rounded-lg px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? t('Saving...') : t('Save Role') }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
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

const permissionGroups = computed(() => {
    return Object.entries(props.permissions).map(([key, items]) => ({
        key,
        label: key.replace(/_/g, ' '),
        items,
    }))
})

const filteredRoles = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.roles
    }

    return props.roles.filter((role) => {
        return [
            role.name,
            role.slug,
            role.description ?? '',
        ].some((value) => value.toLowerCase().includes(query))
    })
})

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
    form.permissions = allPermissions.value.map((permission) => permission.id)
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
</script>
