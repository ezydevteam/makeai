<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppIconSelect from '@/Components/IconClassSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface Category {
    id: number
    name: string
    slug: string
    icon: string
    description: string | null
    sort_order: number
    is_active: boolean
    articles_count: number
    meta_title: string | null
    meta_desc: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface CategoryPagination {
    data: Category[]
    links: PaginationLink[]
}

const { t } = useTranslate()

const props = defineProps<{
    categories: CategoryPagination
}>()

const search = ref('')
const statusFilter = ref('')
const showForm = ref(false)
const editing = ref<Category | null>(null)
const deleting = ref<Category | null>(null)
const slugTouched = ref(false)
const deleteProcessing = ref(false)

const form = useForm({
    name: '',
    slug: '',
    icon: '',
    description: '',
    is_active: true,
    sort_order: 0,
    meta_title: '',
    meta_desc: '',
})

const filteredCategories = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.categories.data.filter((category) => {
        const searchable = [
            category.name,
            category.slug,
            category.icon,
            category.description ?? '',
            category.meta_title ?? '',
            category.meta_desc ?? '',
            category.sort_order.toString(),
            category.is_active ? t('Active') : t('Inactive'),
        ].join(' ').toLowerCase()

        const matchesSearch = !query || searchable.includes(query)
        const matchesStatus =
            !statusFilter.value
            || (statusFilter.value === 'active' && category.is_active)
            || (statusFilter.value === 'inactive' && !category.is_active)

        return matchesSearch && matchesStatus
    })
})

const statusOptions = computed(() => [
    { value: '', label: t('All statuses') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
])

const makeSlug = (value: string) => value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

const resetForm = () => {
    slugTouched.value = false
    form.defaults({
        name: '',
        slug: '',
        icon: '',
        description: '',
        is_active: true,
        sort_order: 0,
        meta_title: '',
        meta_desc: '',
    })
    form.reset()
}

const closeForm = () => {
    showForm.value = false
    editing.value = null
    resetForm()
}

const openCreate = () => {
    editing.value = null
    resetForm()
    showForm.value = true
}

const openEdit = (category: Category) => {
    editing.value = category
    slugTouched.value = true
    form.defaults({
        name: category.name,
        slug: category.slug,
        icon: category.icon || '',
        description: category.description || '',
        is_active: category.is_active,
        sort_order: category.sort_order,
        meta_title: category.meta_title || '',
        meta_desc: category.meta_desc || '',
    })
    form.reset()
    showForm.value = true
}

const syncSlug = () => {
    if (!slugTouched.value) {
        form.slug = makeSlug(form.name)
    }
}

const markSlugTouched = () => {
    slugTouched.value = true
    form.slug = makeSlug(form.slug)
}

const save = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeForm()
        },
    }

    if (editing.value) {
        form.put(`/admin/kb/categories/${editing.value.id}`, options)
        return
    }

    form.post('/admin/kb/categories', options)
}

const confirmDelete = (category: Category) => {
    deleting.value = category
}

const remove = () => {
    if (!deleting.value) {
        return
    }

    deleteProcessing.value = true

    router.delete(`/admin/kb/categories/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null
        },
        onFinish: () => {
            deleteProcessing.value = false
        },
    })
}
</script>

<template>
    <Head :title="t('AI Knowledge Base Categories')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Knowledge Base Categories') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage KB categories and the metadata used for grouping articles.') }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm font-medium"
                @click="openCreate"
            >
                <i class="ti ti-plus text-base"></i>
                {{ t('New Category') }}
            </button>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 p-4 dark:border-surface-800">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative w-full max-w-md">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('Search categories')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-2 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-surface-700 dark:hover:text-white"
                            :title="t('Clear search')"
                            @click="search = ''"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <div class="w-full lg:w-56">
                        <AppSelect
                            v-model="statusFilter"
                            :options="statusOptions"
                            :placeholder="t('All statuses')"
                        />
                    </div>
                </div>
            </div>

            <div class="max-h-[calc(100vh-18rem)] overflow-auto">
                <table class="min-w-full">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:bg-surface-950/90 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">{{ t('Icon') }}</th>
                            <th class="px-4 py-3">{{ t('Name') }}</th>
                            <th class="px-4 py-3">{{ t('Articles') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3">{{ t('Sort') }}</th>
                            <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-if="filteredCategories.length === 0">
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ t('No categories found.') }}
                            </td>
                        </tr>

                        <tr
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="hover:bg-primary-50/60 dark:hover:bg-surface-800/60"
                        >
                            <td class="px-4 py-4 align-top">
                                <i v-if="category.icon" :class="category.icon" class="text-xl text-primary-600 dark:text-primary-400"></i>
                                <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ category.name }}</div>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">/{{ category.slug }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                {{ category.articles_count }}
                            </td>
                            <td class="px-4 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="category.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300'"
                                >
                                    {{ category.is_active ? t('Active') : t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                {{ category.sort_order }}
                            </td>
                            <td class="px-4 py-4 align-top">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-700 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                        :title="t('Edit')"
                                        @click="openEdit(category)"
                                    >
                                        <i class="ti ti-pencil text-base"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        :title="t('Delete')"
                                        @click="confirmDelete(category)"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="props.categories.links?.length" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination :links="props.categories.links" />
            </div>
        </div>
    </div>

    <ActionConfirmModal
        :open="!!deleting"
        :title="t('Delete category?')"
        :message="t('This category will be removed permanently if it has no articles.')"
        :confirm-label="t('Delete')"
        :cancel-label="t('Cancel')"
        :processing="deleteProcessing"
        @confirm="remove"
        @cancel="deleting = null"
    />

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showForm"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                @click.self="closeForm"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <section class="flex max-h-[90vh] w-full sm:max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-800 dark:bg-surface-900">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ editing ? t('Edit Category') : t('New Category') }}
                                    </h3>
                                    <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                                        {{ t('Use this form to create or update a KB category.') }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                                    @click="closeForm"
                                    >
                                        <i class="ti ti-x text-base"></i>
                                    </button>
                        </div>

                        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="save">
                            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</span>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        :placeholder="t('e.g. Getting Started')"
                                        class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        @input="syncSlug"
                                    >
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-danger-500">
                                        {{ form.errors.name }}
                                    </p>
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }}</span>
                                    <input
                                        v-model="form.slug"
                                        type="text"
                                        :placeholder="t('e.g. getting-started')"
                                        class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        @input="markSlugTouched"
                                    >
                                </label>

                                <div>
                                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Icon') }}</span>
                                    <AppIconSelect
                                        :model-value="form.icon"
                                        :placeholder="t('Choose an icon...')"
                                        @update:model-value="form.icon = $event"
                                    />
                                </div>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort Order') }}</span>
                                    <input
                                        v-model.number="form.sort_order"
                                        type="number"
                                        min="0"
                                        :placeholder="t('0')"
                                        class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                    >
                                </label>
                                </div>

                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</span>
                                        <textarea
                                            v-model="form.description"
                                            rows="3"
                                            :placeholder="t('Short description shown in the KB directory')"
                                            class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        ></textarea>
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Title') }}</span>
                                        <input
                                            v-model="form.meta_title"
                                            type="text"
                                            maxlength="160"
                                            :placeholder="t('SEO title for this category')"
                                            class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Description') }}</span>
                                        <textarea
                                            v-model="form.meta_desc"
                                            rows="2"
                                            maxlength="320"
                                            :placeholder="t('SEO description for this category')"
                                            class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-4 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                        ></textarea>
                                    </label>
                                </div>

                                <div class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-surface-700">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="form.is_active"
                                        class="relative inline-flex h-6 w-11 rounded-full transition"
                                        :class="form.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                        @click="form.is_active = !form.is_active"
                                    >
                                        <span
                                            class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                            :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                                        ></span>
                                    </button>
                                </div>
                            </div>

                            <div class="shrink-0 w-full border-t border-gray-100 bg-gray-50/80 px-5 py-2 dark:border-surface-800 dark:bg-surface-950">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                                        @click="closeForm"
                                    >
                                        {{ t('Cancel') }}
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="rounded-lg btn-primary px-5 disabled:opacity-50"
                                    >
                                        {{ form.processing ? t('Processing...') : (editing ? t('Update') : t('Create')) }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </section>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
