<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: number) => string

interface Tag {
    id: number
    name: string
    slug: string
    description: string | null
    posts_count: number
    meta_title?: string | null
    meta_description?: string | null
}

interface Paginated<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
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

const props = defineProps<{ tags: Paginated<Tag>; filters: { search?: string } }>()
const { t } = useTranslate()

const showModal = ref(false)
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
    meta_title: '',
    meta_description: '',
})

const reset = () => {
    editingId.value = null
    slugTouched.value = false
    form.reset()
    form.clearErrors()
}

const closeModal = () => {
    if (form.processing) {
        return
    }

    showModal.value = false
    reset()
}

const openCreate = () => {
    reset()
    showModal.value = true
}

const edit = (tag: Tag) => {
    editingId.value = tag.id
    slugTouched.value = true
    form.name = tag.name
    form.slug = tag.slug
    form.description = tag.description ?? ''
    form.meta_title = tag.meta_title ?? ''
    form.meta_description = tag.meta_description ?? ''
    showModal.value = true
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
    if (editingId.value) {
        form.put(route('admin.blog.tags.update', editingId.value), {
            onSuccess: () => {
                showModal.value = false
                reset()
            },
        })
        return
    }

    form.post(route('admin.blog.tags.store'), {
        onSuccess: () => {
            showModal.value = false
            reset()
        },
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

const confirmDelete = (tag: Tag) => {
    openConfirmModal({
        title: t('Delete tag?'),
        message: t('Delete tag :name?', { name: tag.name }),
        confirmLabel: t('Delete'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.blog.tags.destroy', tag.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const confirmDeleteUnused = () => {
    openConfirmModal({
        title: t('Delete unused tags?'),
        message: t('Delete all unused tags with no assigned posts?'),
        confirmLabel: t('Delete Unused'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.blog.tags.unused.delete'), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}
</script>

<template>
    <Head :title="t('Blog Tags')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Tags') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ t('Manage searchable labels and keep your blog taxonomy clean and reusable.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/30" @click="confirmDeleteUnused">
                    <i class="ti ti-trash text-base"></i>
                    {{ t('Delete Unused') }}
                </button>
                <Link :href="route('admin.blog.posts.index')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                    <i class="ti ti-article text-base"></i>
                    {{ t('Posts') }}
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 btn-primary-admin px-4 py-2 text-sm font-medium text-white"
                    @click="openCreate"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Create Tag') }}
                </button>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-800/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3.5">{{ t('Tag') }}</th>
                            <th class="px-4 py-3.5">{{ t('Posts') }}</th>
                            <th class="px-6 py-3.5 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr
                            v-for="tag in tags.data"
                            :key="tag.id"
                            class="bg-white transition-colors hover:bg-gray-50/50 dark:bg-surface-900 dark:hover:bg-surface-800/30"
                        >
                            <td class="px-6 py-4">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900 dark:text-white">{{ tag.name }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ tag.slug }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ tag.posts_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Tooltip :content="t('Edit tag')" placement="top">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20" @click="edit(tag)">
                                            <i class="ti ti-edit text-base"></i>
                                        </button>
                                    </Tooltip>
                                    <Tooltip :content="t('Delete tag')" placement="top">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" @click="confirmDelete(tag)">
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </Tooltip>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!tags.data.length">
                            <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No blog tags found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="tags.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination
                    :links="tags.links"
                    :from="tags.from"
                    :to="tags.to"
                    :total="tags.total"
                    :current-page="tags.current_page"
                    :last-page="tags.last_page"
                />
            </div>
        </section>
    </div>

    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-3 dark:border-surface-700">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ editingId ? t('Edit Tag') : t('Create Tag') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ editingId ? t('Update the tag copy and metadata.') : t('Create a reusable tag for search, discovery, and filtering.') }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-200"
                        @click="closeModal"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-8rem)] overflow-y-auto p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</label>
                            <input v-model="form.name" @input="syncSlug" :placeholder="t('Enter tag name')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }}</label>
                            <input v-model="form.slug" @input="markSlugTouched" :placeholder="t('tag-slug')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ t('Generated from the name. You can still edit it.') }}</p>
                            <p v-if="form.errors.slug" class="mt-1 text-sm text-red-500">{{ form.errors.slug }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
                            <textarea v-model="form.description" :placeholder="t('Describe this tag briefly')" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                            <p v-if="form.errors.description" class="mt-1 text-sm text-red-500">{{ form.errors.description }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Title') }}</label>
                            <input v-model="form.meta_title" :placeholder="t('Optional meta title')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <p v-if="form.errors.meta_title" class="mt-1 text-sm text-red-500">{{ form.errors.meta_title }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Description') }}</label>
                            <textarea v-model="form.meta_description" :placeholder="t('Optional meta description')" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                            <p v-if="form.errors.meta_description" class="mt-1 text-sm text-red-500">{{ form.errors.meta_description }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-3 dark:border-surface-700 dark:bg-surface-800">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                        @click="closeModal"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="btn-primary-admin inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit"
                    >
                        {{ form.processing ? t('Saving...') : editingId ? t('Save Changes') : t('Create Tag') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

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
