<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import type { SelectOption } from '@/Components/AppSelect.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import Pagination from '@/Components/Pagination.vue'
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
    requires_pro: boolean
    requires_login: boolean
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
    }
    parents: ParentOption[]
    filters: { search: string; status: string }
}>()

const { t } = useTranslate()

const editingId = ref<number | null>(null)
const slugTouched = ref(false)
const deleteTarget = ref<Category | null>(null)
const deleteProcessing = ref(false)
const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const actionMenuOpen = ref<number | null>(null)

const form = useForm({
    name: '',
    slug: '',
    description: '',
    icon: '',
    color: '#10b981',
    is_active: true,
    requires_pro: false,
    requires_login: false,
    sort_order: 0,
    parent_id: null as string | number | null,
    meta_title: '',
    meta_description: '',
})

const resetForm = () => {
    editingId.value = null
    slugTouched.value = false
    form.reset()
    form.color = '#10b981'
    form.is_active = true
    form.requires_pro = false
    form.requires_login = false
    form.sort_order = 0
    form.parent_id = null
    form.meta_title = ''
    form.meta_description = ''
}

const editCategory = (category: Category) => {
    editingId.value = category.id
    slugTouched.value = true
    actionMenuOpen.value = null
    form.name = category.name
    form.slug = category.slug
    form.description = category.description || ''
    form.icon = category.icon || ''
    form.color = category.color || '#10b981'
    form.is_active = category.is_active
    form.requires_pro = category.requires_pro
    form.requires_login = category.requires_login
    form.sort_order = category.sort_order || 0
    form.parent_id = category.parent_id != null ? String(category.parent_id) : null
    form.meta_title = category.meta_title || ''
    form.meta_description = category.meta_description || ''

    nextTick(() => {
        document.getElementById('category-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
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

const togglePro = (category: Category) => {
    router.post(route('admin.ai.categories.toggle-pro', category.id), {}, { preserveScroll: true })
}

const toggleLogin = (category: Category) => {
    router.post(route('admin.ai.categories.toggle-login', category.id), {}, { preserveScroll: true })
}

const applyFilters = () => {
    router.get(route('admin.ai.categories.index'), {
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveScroll: true, preserveState: true, replace: true })
}

let filterTimer: ReturnType<typeof setTimeout> | null = null
const searchInput = () => {
    if (filterTimer) clearTimeout(filterTimer)
    filterTimer = setTimeout(applyFilters, 400)
}

watch(statusFilter, applyFilters)

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

const statusFilterOptions: SelectOption[] = [
    { value: '', label: t('All status') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
]

const hasActiveFilters = computed(() => searchQuery.value || statusFilter.value)

const closeAllMenus = () => {
    actionMenuOpen.value = null
}
</script>

<template>
    <Head :title="t('AI Tool Categories — Admin')" />

    <ActionConfirmModal
        :open="Boolean(deleteTarget)"
        :title="t('Delete ' + (deleteTarget?.name ?? ''))"
        :message="t('This category will be permanently removed. Tools in this category will be detached, not deleted.')"
        :confirm-label="t('Delete')"
        :processing-label="t('Deleting...')"
        :processing="deleteProcessing"
        variant="danger"
        @cancel="deleteTarget = null"
        @confirm="executeDelete"
    />

    <div class="mx-auto w-full max-w-7xl px-6 py-8" @click="closeAllMenus">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Tool Categories') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage the dynamic groups used by public tool pages and admin templates. Categories control tool visibility and access requirements.') }}
                </p>
            </div>
            <Link
                :href="route('admin.ai.tools.index')"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"
            >
                <i class="ti ti-apps"></i>{{ t('All Tools') }}
            </Link>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <!-- Form Sidebar -->
            <div class="w-full shrink-0 lg:w-[380px]">
            <section id="category-form" class="flex  flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="shrink-0 border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                <i :class="editingId ? 'ti ti-edit' : 'ti ti-circle-plus'" class="mr-1"></i>
                {{ editingId ? t('Edit Category') : t('Create Category') }}
                    </h2>
                </div>

                <form class="flex flex-1 flex-col" @submit.prevent="submit">
                    <div class="flex-1 space-y-4 p-5">
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

                    <AppSelect
                        v-model="form.parent_id"
                        :options="parentOptions"
                        :label="t('Parent')"
                        :placeholder="t('No parent')"
                    />

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
                        <textarea
                            v-model="form.description"
                            :placeholder="t('Enter category description')"
                            rows="3"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <IconClassSelect v-model="form.icon" :label="t('Icon')" />
                        <AppColorPicker v-model="form.color" :label="t('Color')" />
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
                            rows="2"
                            maxlength="500"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        ></textarea>
                    </div>

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

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                        <button
                            type="button"
                            :class="form.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'"
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                            @click="form.is_active = !form.is_active"
                        >
                            <span
                                :class="form.is_active ? 'translate-x-4' : 'translate-x-0'"
                                class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                            />
                        </button>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Requires Pro') }}</span>
                        <button
                            type="button"
                            :class="form.requires_pro ? 'bg-accent-500' : 'bg-gray-200 dark:bg-surface-600'"
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                            @click="form.requires_pro = !form.requires_pro"
                        >
                            <span
                                :class="form.requires_pro ? 'translate-x-4' : 'translate-x-0'"
                                class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                            />
                        </button>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Requires Login') }}</span>
                        <button
                            type="button"
                            :class="form.requires_login ? 'bg-blue-500' : 'bg-gray-200 dark:bg-surface-600'"
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                            @click="form.requires_login = !form.requires_login"
                        >
                            <span
                                :class="form.requires_login ? 'translate-x-4' : 'translate-x-0'"
                                class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                            />
                        </button>
                    </div>

                    </div>

                    <div class="shrink-0 border-t border-gray-100 bg-gray-50/50 p-4 dark:border-surface-800 dark:bg-surface-800/30">
                        <div class="flex items-center gap-2">
                            <button
                                :disabled="form.processing"
                                type="submit"
                                class="flex-1 rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-600 disabled:opacity-50"
                            >
                                {{ form.processing ? t('Saving...') : t('Save') }}
                            </button>
                            <button
                                v-if="editingId"
                                type="button"
                                class="rounded-lg bg-surface-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-surface-200 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                                @click="resetForm"
                            >
                                {{ t('Cancel') }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>
            </div>

            <!-- Table Section -->
            <section class="min-w-0 flex-1">
                <!-- Filters Bar -->
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            @input="searchInput"
                            :placeholder="t('Search by name or slug...')"
                            type="text"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </div>
                    <AppSelect
                        v-model="statusFilter"
                        :options="statusFilterOptions"
                        :placeholder="t('All status')"
                        class="w-36"
                    />
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400 dark:hover:bg-surface-800"
                        @click="searchQuery = ''; statusFilter = ''; applyFilters()"
                    >
                        {{ t('Clear') }}
                    </button>
                </div>

                <!-- Categories Table -->
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
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
                            @click="searchQuery = ''; statusFilter = ''; applyFilters()"
                        >
                            {{ t('Clear filters') }}
                        </button>
                    </div>

                    <!-- Table -->
                    <table v-else class="min-w-[760px] w-full text-left">
                        <thead class="bg-gray-50 dark:bg-surface-800">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Category') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Parent') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Tools') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">{{ t('Active') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">{{ t('Pro') }}</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">{{ t('Login') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Created') }}</th>
                                <th class="w-10 px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="category in categories.data"
                                :key="category.id"
                                class="border-t border-gray-100 transition-colors hover:bg-gray-50/50 dark:border-surface-800 dark:hover:bg-surface-800/50"
                            >
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            v-if="category.color"
                                            class="h-3 w-3 shrink-0 rounded-full"
                                            :style="{ backgroundColor: category.color }"
                                        />
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ category.name }}</div>
                                            <div class="text-xs text-gray-500">{{ category.slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ category.parent?.name ?? t('—') }}
                                </td>
                                <td class="px-4 py-4">
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
                                    <button
                                        type="button"
                                        :class="category.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'"
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                        @click="toggleActive(category)"
                                    >
                                        <span
                                            :class="category.is_active ? 'translate-x-4' : 'translate-x-0'"
                                            class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                                        />
                                    </button>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button
                                        type="button"
                                        :class="category.requires_pro ? 'bg-accent-500' : 'bg-gray-200 dark:bg-surface-600'"
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                        @click="togglePro(category)"
                                    >
                                        <span
                                            :class="category.requires_pro ? 'translate-x-4' : 'translate-x-0'"
                                            class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                                        />
                                    </button>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button
                                        type="button"
                                        :class="category.requires_login ? 'bg-blue-500' : 'bg-gray-200 dark:bg-surface-600'"
                                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                        @click="toggleLogin(category)"
                                    >
                                        <span
                                            :class="category.requires_login ? 'translate-x-4' : 'translate-x-0'"
                                            class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                                        />
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-500">
                                    {{ formatDate(category.created_at) }}
                                </td>
                                <td class="relative px-4 py-4 text-right">
                                    <button
                                        type="button"
                                        class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:border-gray-300 hover:text-gray-600 dark:border-surface-700 dark:hover:border-surface-600 dark:hover:text-gray-300"
                                        @click.stop="actionMenuOpen = actionMenuOpen === category.id ? null : category.id"
                                    >
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>

                                    <div
                                        v-if="actionMenuOpen === category.id"
                                        class="absolute right-4 top-12 z-20 w-36 rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-surface-700 dark:bg-surface-900"
                                        @click.stop
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800"
                                            @click="editCategory(category)"
                                        >
                                            <i class="ti ti-edit"></i>
                                            {{ t('Edit') }}
                                        </button>
                                        <hr class="border-gray-200 dark:border-surface-700">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-danger-600 hover:bg-gray-50 dark:text-danger-400 dark:hover:bg-gray-900/20"
                                            @click="confirmDelete(category)"
                                        >
                                            <i class="ti ti-trash"></i>
                                            {{ t('Delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="categories.links && categories.links.length > 3" class="mt-4">
                    <Pagination :links="categories.links" />
                </div>
            </section>
        </div>
    </div>
</template>
