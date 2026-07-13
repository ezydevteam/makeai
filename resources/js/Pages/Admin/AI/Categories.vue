<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import type { SelectOption } from '@/Components/UI/AppSelect.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import IconClassSelect from '@/Components/Admin/IconClassSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface Category {
    id: number
    name: string
    slug: string
    description?: string
    icon?: string
    color?: string
    is_active: boolean
    access_level: string
    is_system: boolean
    sort_order: number
    tools_count: number
    parent?: { id: number; name: string } | null
    parent_id: number | null
    meta_title?: string
    meta_description?: string
    created_at: string
}

interface ParentOption {
    id: number
    name: string
}

const props = defineProps<{
    categories: {
        data: Category[]
        links: { url: string | null; label: string; active: boolean }[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    parents: ParentOption[]
    filters: { search: string; status: string }
    accessLevels?: SelectOption[]
}>()

const { t } = useTranslate()

const editingId = ref<number | null>(null)
const formModalOpen = ref(false)
const slugTouched = ref(false)
const deleteTarget = ref<Category | null>(null)
const deleteProcessing = ref(false)
const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const actionMenuOpen = ref<number | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)

const form = useForm({
    name: '',
    slug: '',
    description: '',
    icon: '',
    color: '#10b981',
    is_active: true,
    access_level: 'inherit',
    sort_order: 0,
    parent_id: null as string | number | null,
    meta_title: '',
    meta_description: '',
})

const resetForm = () => {
    editingId.value = null
    formModalOpen.value = false
    slugTouched.value = false
    form.reset()
    form.clearErrors()
    form.color = '#10b981'
    form.is_active = true
    form.access_level = 'inherit'
    form.sort_order = 0
    form.parent_id = null
    form.meta_title = ''
    form.meta_description = ''
}

const openCreateModal = () => {
    resetForm()
    formModalOpen.value = true
}

const editCategory = (category: Category) => {
    editingId.value = category.id
    formModalOpen.value = true
    slugTouched.value = true
    actionMenuOpen.value = null
    form.name = category.name
    form.slug = category.slug
    form.description = category.description || ''
    form.icon = category.icon || ''
    form.color = category.color || '#10b981'
    form.is_active = category.is_active
    form.access_level = category.access_level || 'inherit'
    form.sort_order = category.sort_order || 0
    form.parent_id = category.parent_id != null ? String(category.parent_id) : null
    form.meta_title = category.meta_title || ''
    form.meta_description = category.meta_description || ''
}

const makeSlug = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')

const syncSlug = () => {
    if (slugTouched.value) return
    form.slug = makeSlug(form.name)
}

const markSlugTouched = () => {
    slugTouched.value = true
    form.slug = makeSlug(form.slug)
}

const submit = () => {
    const payload = { ...form.data(), parent_id: form.parent_id != null ? Number(form.parent_id) : null }
    if (editingId.value) {
        form.transform(() => payload).put(route('admin.ai.categories.update', editingId.value), {
            preserveScroll: true,
            onSuccess: resetForm,
        })
        return
    }
    form.transform(() => payload).post(route('admin.ai.categories.store'), {
        preserveScroll: true,
        onSuccess: resetForm,
    })
}

const confirmDelete = (category: Category) => {
    actionMenuOpen.value = null
    deleteTarget.value = category
}

const executeDelete = () => {
    if (!deleteTarget.value) return
    deleteProcessing.value = true
    router.delete(route('admin.ai.categories.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false
            deleteTarget.value = null
        },
    })
}

const toggleActive = (category: Category) => {
    router.post(route('admin.ai.categories.toggle-active', category.id), {}, { preserveScroll: true })
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const parentOptions = computed(() => {
    const opts = props.parents
        .filter((p) => p.id !== editingId.value)
        .map((p) => ({ value: String(p.id), label: p.name }))
    return [{ value: '', label: t('No parent') }, ...opts]
})

const accessLevels = computed<SelectOption[]>(() => props.accessLevels ?? [
    { value: 'inherit', label: t('Inherit (Default)') },
    { value: 'public', label: t('Public (No Login)') },
    { value: 'login_required', label: t('Login Required') },
    { value: 'free_plan', label: t('Free Plan') },
    { value: 'pro_plan', label: t('Pro Plan') },
])

const statusFilterOptions: SelectOption[] = [
    { value: '', label: t('All status') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
]

const applyFilters = () => {
    const params: Record<string, string> = {}
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (statusFilter.value) params.status = statusFilter.value

    router.get(route('admin.ai.categories.index'), params, {
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

const hasActiveFilters = computed(() => Boolean(searchQuery.value || statusFilter.value))

const clearSearchQuery = () => {
    if (!searchQuery.value) {
        return
    }

    searchQuery.value = ''
    applyFilters()
}

const closeAllMenus = () => {
    actionMenuOpen.value = null
}

const clearTableFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
    applyFilters()
}

const isTypingTarget = (target: EventTarget | null) => {
    if (!(target instanceof HTMLElement)) {
        return false
    }

    const tagName = target.tagName.toLowerCase()

    return tagName === 'input'
        || tagName === 'textarea'
        || tagName === 'select'
        || target.isContentEditable
}

const handleKeydown = (event: KeyboardEvent) => {
    if (formModalOpen.value || deleteTarget.value) {
        return
    }

    if (event.key === '/') {
        if (event.metaKey || event.ctrlKey || event.altKey || isTypingTarget(event.target)) {
            return
        }

        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && hasActiveFilters.value) {
        if (isTypingTarget(event.target) && event.target !== searchInput.value) {
            return
        }

        clearTableFilters()
        actionMenuOpen.value = null
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('AI Tool Categories — Admin')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10" @click="closeAllMenus">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Tool Categories') }}</h1>
                <p class="mt-1 w-full sm:max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage the dynamic groups used by public tool pages and admin templates. Categories control tool visibility and access requirements.') }}
                </p>
            </div>
            <div class="shrink-0 flex gap-3">
                <Link
                    :href="route('admin.ai.tools.index')"
                    class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
                >
                    <i class="ti ti-apps text-base"></i>
                    {{ t('All Tools') }}
                </Link>
                <button
                    type="button"
                    class="grow btn-primary-admin inline-flex items-center justify-center gap-2"
                    @click="openCreateModal"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Create Category') }}
                </button>
            </div>
        </div>

        <div class="min-w-0">
            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex-1 min-w-[220px] sm:max-w-xs">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    :placeholder="t('Search tool category...')"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    @focus="searchFocused = true"
                                    @blur="searchFocused = false"
                                />
                                <span
                                    v-if="!searchQuery && !searchFocused"
                                    class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                                >
                                    /
                                </span>
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="t('Clear filter')"
                                    @click="clearSearchQuery"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                            </div>
                        </div>

                        <div class="min-w-[150px]">
                            <AppSelect
                                v-model="statusFilter"
                                :options="statusFilterOptions"
                                :placeholder="t('All status')"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-b-2xl">
                    <div class="overflow-x-auto">
                        <!-- Empty State -->
                        <div
                            v-if="categories.data.length === 0"
                            class="flex flex-col items-center justify-center px-6 py-16 text-center"
                        >
                            <div class="mb-4 text-5xl">
                                {{ hasActiveFilters ? '🔍' : '📂' }}
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ hasActiveFilters ? t('No matching categories') : t('No categories yet') }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ hasActiveFilters
                                    ? t('Try adjusting your search or filters.')
                                    : t('Create your first category to organize AI tools.') }}
                            </p>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                                @click="clearTableFilters"
                            >
                                {{ t('Clear filters') }}
                            </button>
                        </div>

                        <!-- Table -->
                        <table v-else class="min-w-[760px] w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-800/50">
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Category') }}</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Parent') }}</th>
                                    <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tools') }}</th>
                                    <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Active') }}</th>
                                    <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Created') }}</th>
                                    <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                                <tr
                                    v-for="category in categories.data"
                                    :key="category.id"
                                    class="transition-colors hover:bg-gray-50/50 dark:hover:bg-surface-800/30"
                                >
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition-colors"
                                                :class="category.color ? '' : 'bg-gray-50 text-gray-500 border-gray-100 dark:bg-surface-800 dark:text-gray-400 dark:border-surface-700/50'"
                                                :style="category.color ? {
                                                    backgroundColor: `color-mix(in srgb, ${category.color} 12%, transparent)`,
                                                    borderColor: `color-mix(in srgb, ${category.color} 20%, transparent)`,
                                                    color: category.color
                                                } : {}"
                                            >
                                                <i :class="[category.icon || 'ti ti-folder', 'text-lg']"></i>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white">{{ category.name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ category.slug }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ category.parent?.name ?? t('—') }}
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <Link
                                            v-if="category.tools_count > 0"
                                            :href="route('admin.ai.tools.index') + '?category=' + category.id"
                                            class="text-sm font-semibold text-primary-600 hover:text-primary-500"
                                        >
                                            {{ category.tools_count }}
                                        </Link>
                                        <span v-else class="text-sm text-gray-400">{{ t('0') }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <AppSwitch
                                            :model-value="category.is_active"
                                            @update:model-value="toggleActive(category)"
                                        />
                                    </td>
                                    <td class="text-center whitespace-nowrap px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatDate(category.created_at) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <Tooltip :content="t('Edit')">
                                                <button
                                                    type="button"
                                                    class="flex h-8 w-8 items-center justify-center rounded-full text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                                    @click="editCategory(category)"
                                                >
                                                    <i class="ti ti-edit text-base"></i>
                                                </button>
                                            </Tooltip>

                                            <Tooltip :content="t('Delete')">
                                                <button
                                                    type="button"
                                                    class="flex h-8 w-8 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                    @click="confirmDelete(category)"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="categories.links && categories.links.length > 3"
                        class="border-t border-gray-100 px-4 py-4 dark:border-gray-800"
                    >
                        <Pagination
                            :links="categories.links"
                            :from="categories.from"
                            :to="categories.to"
                            :total="categories.total"
                            :current-page="categories.current_page"
                            :last-page="categories.last_page"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <AppModal
        :open="formModalOpen"
        max-width="max-w-3xl"
        :title="editingId ? t('Edit Category') : t('Create Category')"
        :subtitle="editingId ? t('Update category details, hierarchy, and access requirements.') : t('Create a new AI category with icon, color, and access settings.')"
        has-form
        :confirm-text="editingId ? t('Save Changes') : t('Create Category')"
        :confirm-loading="form.processing"
        :confirm-loading-text="t('Saving...')"
        @close="resetForm"
        @submit="submit"
    >
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</label>
                <input
                    v-model="form.name"
                    @input="syncSlug"
                    :placeholder="t('Enter category name')"
                    type="text"
                    required
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-danger-500">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }}</label>
                <input
                    v-model="form.slug"
                    @input="markSlugTouched"
                    :placeholder="t('Enter category slug')"
                    type="text"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                />
                <p class="mt-1 text-xs text-gray-400">{{ t('Generated from name. Edit if needed.') }}</p>
                <p v-if="form.errors.slug" class="mt-1 text-xs text-danger-500">{{ form.errors.slug }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <AppSelect
                v-model="form.parent_id"
                :options="parentOptions"
                :label="t('Parent')"
                :placeholder="t('No parent')"
            />
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort order') }}</label>
                <input
                    v-model.number="form.sort_order"
                    type="number"
                    min="0"
                    :placeholder="t('Enter sort order')"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                />
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
            <textarea
                v-model="form.description"
                :placeholder="t('Enter category description')"
                rows="3"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            ></textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <IconClassSelect v-model="form.icon" :label="t('Icon')" />
            <AppColorPicker v-model="form.color" :label="t('Color')" />
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/80 px-4 py-4 dark:border-surface-700 dark:bg-surface-950/50">
                <span>
                    <span class="block text-sm font-medium text-gray-700 dark:text-white">{{ t('Active Category') }}</span>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Make active or inactive this ai category') }}</span>
                </span>
                <AppSwitch v-model="form.is_active" />
            </label>
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800/60">
                <AppSelect
                    v-model="form.access_level"
                    :options="accessLevels"
                    :label="t('Access Level')"
                />
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta title (SEO)') }}</label>
            <input
                v-model="form.meta_title"
                :placeholder="t('Enter meta title')"
                type="text"
                maxlength="160"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            />
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta description (SEO)') }}</label>
            <textarea
                v-model="form.meta_description"
                :placeholder="t('Enter meta description')"
                rows="3"
                maxlength="500"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            ></textarea>
        </div>
    </AppModal>

    <ActionConfirmModal
        :open="Boolean(deleteTarget)"
        :title="t('Delete Category')"
        :message="t('This category will be permanently removed. Tools in this category will be detached, not deleted.')"
        :confirm-label="t('Delete')"
        :processing-label="t('Deleting...')"
        :processing="deleteProcessing"
        variant="danger"
        @cancel="deleteTarget = null"
        @confirm="executeDelete"
    />
</template>
