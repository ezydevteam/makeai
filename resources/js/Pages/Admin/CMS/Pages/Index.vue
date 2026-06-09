<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface PageItem {
    id: number
    title: string
    slug: string
    status: string
    template: string
    is_system: boolean
    deleted_at: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PagesResponse {
    data: PageItem[]
    links?: PaginationLink[]
    from?: number | null
    to?: number | null
    total?: number
}

interface Filters {
    search?: string
    status?: string
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
    pages: PagesResponse
    filters?: Filters
}>()

const { t } = useTranslate()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

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

const isTrashed = computed(() => status.value === 'trashed')

const statusOptions = computed(() => [
    { value: '', label: t('All Statuses') },
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
    { value: 'scheduled', label: t('Scheduled') },
    { value: 'trashed', label: t('Trashed') },
])

const pageStats = computed(() => ({
    total: props.pages.total ?? props.pages.data.length,
    published: props.pages.data.filter((page) => !page.deleted_at && page.status === 'published').length,
    drafts: props.pages.data.filter((page) => !page.deleted_at && page.status === 'draft').length,
    system: props.pages.data.filter((page) => page.is_system).length,
}))

const applyStatusFilter = () => {
    router.get(route('admin.pages.index'), {
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const filteredPages = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return props.pages.data
    }

    return props.pages.data.filter((page) => {
        return [
            page.title,
            page.slug,
            page.template,
            page.status,
        ].some((value) => value.toLowerCase().includes(query))
    })
})

const getStatusClass = (pageStatus: string) => {
    switch (pageStatus) {
        case 'published':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
        case 'scheduled':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
        case 'trashed':
            return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
    }
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

const confirmDelete = (page: PageItem) => {
    openConfirmModal({
        title: t('Move page to trash?'),
        message: t('Move page :name to trash?', { name: page.title }),
        confirmLabel: t('Move to Trash'),
        processingLabel: t('Moving...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.pages.delete', page.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const confirmForceDelete = (page: PageItem) => {
    openConfirmModal({
        title: t('Delete permanently?'),
        message: t('Delete page :name permanently? This action cannot be undone.', { name: page.title }),
        confirmLabel: t('Delete Permanently'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.pages.force-delete', page.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const restorePage = (page: PageItem) => {
    router.post(route('admin.pages.restore', page.id), {}, { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Pages')" />

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Pages') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage landing pages, legal pages, and custom content for the public site.') }}
                    </p>
                </div>

                <Link
                    :href="route('admin.pages.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Create Page') }}
                </Link>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Total Pages') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ pageStats.total }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Published') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ pageStats.published }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Drafts') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ pageStats.drafts }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('System Pages') }}</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ pageStats.system }}</p>
                </div>
            </div>

            <div class="mb-4 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="w-full xl:max-w-md">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            :placeholder="t('Filter this table by page title, slug, template, or status...')"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            :aria-label="t('Clear search')"
                            @click="search = ''"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-4 xl:ml-auto xl:flex-row xl:items-center xl:justify-end">
                    <div class="w-full md:w-56">
                        <AppSelect
                            v-model="status"
                            :options="statusOptions"
                            :placeholder="t('All Statuses')"
                            @update:model-value="applyStatusFilter"
                        />
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-6 py-3">{{ t('Page') }}</th>
                                <th scope="col" class="px-6 py-3">{{ t('Template') }}</th>
                                <th scope="col" class="px-6 py-3">{{ t('Status') }}</th>
                                <th scope="col" class="px-6 py-3 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="page in filteredPages"
                                :key="page.id"
                                class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-300">
                                            <i :class="page.is_system ? 'ti ti-lock' : 'ti ti-file-text'" class="text-lg"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ page.title }}</p>
                                                <span
                                                    v-if="page.is_system"
                                                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                                >
                                                    {{ t('System') }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">/{{ page.slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ page.template }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusClass(isTrashed ? 'trashed' : page.status)"
                                    >
                                        {{ isTrashed ? t('Trashed') : t(page.status.charAt(0).toUpperCase() + page.status.slice(1)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div v-if="!isTrashed" class="inline-flex items-center gap-2">
                                        <Tooltip :content="t('View live page')" placement="top">
                                            <a
                                                :href="'/' + page.slug"
                                                target="_blank"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-primary-300"
                                            >
                                                <i class="ti ti-external-link"></i>
                                            </a>
                                        </Tooltip>

                                        <Tooltip v-if="page.status === 'draft' || page.status === 'scheduled'" :content="t('Preview page')" placement="top">
                                            <a
                                                :href="route('admin.pages.preview', page.id)"
                                                target="_blank"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-primary-300"
                                            >
                                                <i class="ti ti-eye text-base"></i>
                                            </a>
                                        </Tooltip>

                                        <Tooltip :content="t('Edit page')" placement="top">
                                            <Link
                                                :href="route('admin.pages.edit', page.id)"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                            >
                                                <i class="ti ti-edit text-base"></i>
                                            </Link>
                                        </Tooltip>

                                        <Tooltip v-if="!page.is_system" :content="t('Move to trash')" placement="top">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                @click="confirmDelete(page)"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </Tooltip>
                                    </div>

                                    <div v-else class="inline-flex items-center gap-2">
                                        <button
                                            v-if="!page.is_system"
                                            type="button"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
                                            @click="restorePage(page)"
                                        >
                                            {{ t('Restore') }}
                                        </button>
                                        <button
                                            v-if="!page.is_system"
                                            type="button"
                                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700 transition-colors hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/30"
                                            @click="confirmForceDelete(page)"
                                        >
                                            {{ t('Delete Forever') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredPages.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    {{ t('No pages found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="pages.links && pages.links.length > 3" class="mt-4">
                <Pagination :links="pages.links" />
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
