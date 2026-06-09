<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: number) => string

interface Category {
    id: number
    name: string
    slug: string
    description: string | null
    parent_id: number | null
    parent?: { name: string } | null
    icon: string | null
    color: string | null
    is_active: boolean
    sort_order: number
    posts_count: number
}

interface Paginated<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
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

const props = defineProps<{ categories: Paginated<Category>; parents: Category[] }>()
const { t } = useTranslate()

const editingId = ref<number | null>(null)
const slugTouched = ref(false)
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

const form = useForm({
    name: '',
    slug: '',
    description: '',
    parent_id: '',
    icon: '',
    color: '#10b981',
    meta_title: '',
    meta_description: '',
    og_image: '',
    is_active: true,
    sort_order: 0,
})

const parentOptions = computed(() => [
    { value: '', label: t('No Parent') },
    ...props.parents
        .filter((item) => item.id !== editingId.value)
        .map((item) => ({
            value: String(item.id),
            label: item.name,
        })),
])

const edit = (category: Category) => {
    editingId.value = category.id
    slugTouched.value = true
    form.name = category.name
    form.slug = category.slug
    form.description = category.description ?? ''
    form.parent_id = category.parent_id ? String(category.parent_id) : ''
    form.icon = category.icon ?? ''
    form.color = category.color ?? '#10b981'
    form.is_active = category.is_active
    form.sort_order = category.sort_order
}

const reset = () => {
    editingId.value = null
    slugTouched.value = false
    form.reset()
    form.clearErrors()
    form.color = '#10b981'
    form.is_active = true
    form.sort_order = 0
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
    const payload = { ...form.data(), parent_id: form.parent_id || null }

    if (editingId.value) {
        form.transform(() => payload).put(route('admin.blog.categories.update', editingId.value), { onSuccess: reset })
        return
    }

    form.transform(() => payload).post(route('admin.blog.categories.store'), { onSuccess: reset })
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

const confirmDelete = (category: Category) => {
    openConfirmModal({
        title: t('Delete category?'),
        message: t('Delete category :name?', { name: category.name }),
        confirmLabel: t('Delete'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.blog.categories.destroy', category.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}
</script>

<template>
    <Head :title="t('Blog Categories')" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Categories') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Organize blog posts with active, hierarchical categories and cleaner taxonomy rules.') }}</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Link :href="route('admin.blog.posts.index')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="ti ti-article text-base"></i>
                        {{ t('Posts') }}
                    </Link>
                    <Link :href="route('admin.blog.tags.index')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="ti ti-tags text-base"></i>
                        {{ t('Tags') }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ editingId ? t('Edit Category') : t('Create Category') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ editingId ? t('Update the category details and hierarchy.') : t('Create a category to group related blog content.') }}</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</label>
                            <input v-model="form.name" @input="syncSlug" :placeholder="t('Enter category name')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }}</label>
                            <input v-model="form.slug" @input="markSlugTouched" :placeholder="t('category-slug')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ t('Generated from the name. You can still edit it.') }}</p>
                        </div>

                        <div>
                            <AppSelect v-model="form.parent_id" :options="parentOptions" :label="t('Parent Category')" :placeholder="t('No Parent')" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
                            <textarea v-model="form.description" :placeholder="t('Describe this category briefly')" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                        </div>

                        <div>
                            <IconClassSelect v-model="form.icon" :label="t('Icon')" :placeholder="t('Search icons...')" />
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_180px]">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sort Order') }}</label>
                                <input v-model="form.sort_order" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                            <div>
                                <AppColorPicker v-model="form.color" :label="t('Color')" />
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="relative h-6 w-12 rounded-full transition-colors"
                                    :class="form.is_active ? 'bg-emerald-500 dark:bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'"
                                    @click="form.is_active = !form.is_active"
                                >
                                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="form.is_active ? 'translate-x-6' : 'translate-x-0'"></span>
                                </button>

                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ form.is_active ? t('Category is active') : t('Category is inactive') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Inactive categories stay hidden from normal editorial usage.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button @click="submit" :disabled="form.processing" type="button" class="flex-1 rounded-lg btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                                {{ form.processing ? t('Saving...') : editingId ? t('Save Changes') : t('Create Category') }}
                            </button>
                            <button v-if="editingId" @click="reset" type="button" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                                {{ t('Cancel') }}
                            </button>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">{{ t('Category') }}</th>
                                    <th class="px-4 py-3">{{ t('Parent') }}</th>
                                    <th class="px-4 py-3">{{ t('Posts') }}</th>
                                    <th class="px-4 py-3">{{ t('Status') }}</th>
                                    <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="category in categories.data" :key="category.id" class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                                <i :class="category.icon || 'ti ti-category'" class="text-base"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ category.name }}</p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ category.slug }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ category.parent?.name ?? t('None') }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ category.posts_count }}</td>
                                    <td class="px-4 py-4">
                                        <span :class="category.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                                            {{ category.is_active ? t('Active') : t('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <Tooltip :content="t('Edit category')" placement="top">
                                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20" @click="edit(category)">
                                                    <i class="ti ti-edit text-base"></i>
                                                </button>
                                            </Tooltip>
                                            <Tooltip :content="t('Delete category')" placement="top">
                                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" @click="confirmDelete(category)">
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!categories.data.length">
                                    <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No blog categories found.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div v-if="categories.links.length > 3" class="mt-4">
                <Pagination :links="categories.links" />
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
</template>
