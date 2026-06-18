<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
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
    current_page?: number | null
    last_page?: number | null
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

const searchInput = ref(props.filters?.search ?? '')
const searchField = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
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
const hasActiveFilters = computed(() => Boolean(searchInput.value.trim() || status.value))

const statusOptions = computed(() => [
    { value: '', label: t('All Statuses') },
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
    { value: 'scheduled', label: t('Scheduled') },
    { value: 'trashed', label: t('Trashed') },
])

const buildFilterPayload = () => ({
    search: searchInput.value.trim() || undefined,
    status: status.value || undefined,
})

const applyFilters = () => {
    router.get(route('admin.pages.index'), buildFilterPayload(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const clearSearch = () => {
    searchInput.value = ''
    applyFilters()
}

const resetFilters = () => {
    searchInput.value = ''
    status.value = ''
    router.get(route('admin.pages.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName?.toLowerCase()
    const isTypingTarget = tagName === 'input' || tagName === 'textarea' || target?.isContentEditable

    if (event.key === '/' && !isTypingTarget && !confirmModal.value.open) {
        event.preventDefault()
        searchField.value?.focus()
        return
    }

    if (event.key === 'Escape' && !confirmModal.value.open && hasActiveFilters.value) {
        resetFilters()
    }
}

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

const formatStatusLabel = (pageStatus: string) => {
    switch (pageStatus) {
        case 'published':
            return t('Published')
        case 'scheduled':
            return t('Scheduled')
        case 'draft':
            return t('Draft')
        case 'trashed':
            return t('Trashed')
        default:
            return pageStatus
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

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Pages')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('Pages') }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Manage landing pages, legal pages, and custom content for the public site.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="route('admin.pages.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary px-4 py-2 text-sm font-medium text-white"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('Create Page') }}
                </Link>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-surface-800">
                <div class="relative min-w-[240px] flex-1">
                    <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input
                        ref="searchField"
                        v-model="searchInput"
                        type="text"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        :placeholder="t('Search page title, slug, or template...')"
                        @keydown.enter="applyFilters"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                    />
                    <span
                        v-if="!searchInput && !searchFocused"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                    >
                        /
                    </span>
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                        :aria-label="t('Clear search')"
                        @click="clearSearch"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <AppSelect
                    v-model="status"
                    :options="statusOptions"
                    :placeholder="t('All Statuses')"
                    class="w-full sm:w-56"
                    @update:model-value="applyFilters"
                />

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    @click="resetFilters"
                >
                    {{ t('Clear filters') }}
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="border-b border-gray-100 bg-gray-50/50 text-xs uppercase text-gray-700 dark:border-surface-800 dark:bg-surface-800/50 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3.5">{{ t('Page') }}</th>
                            <th scope="col" class="px-6 py-3.5">{{ t('Template') }}</th>
                            <th scope="col" class="px-6 py-3.5">{{ t('Status') }}</th>
                            <th scope="col" class="px-6 py-3.5 text-right">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr
                            v-for="page in pages.data"
                            :key="page.id"
                            class="bg-white transition-colors hover:bg-gray-50/50 dark:bg-surface-900 dark:hover:bg-surface-800/30"
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
                                    {{ formatStatusLabel(isTrashed ? 'trashed' : page.status) }}
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

                        <tr v-if="pages.data.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="ti ti-file-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                <p class="font-medium">{{ hasActiveFilters ? t('No pages match your filters') : t('No pages found') }}</p>
                                <button
                                    v-if="hasActiveFilters"
                                    type="button"
                                    class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                                    @click="resetFilters"
                                >
                                    {{ t('Clear filters') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pages.links && pages.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination
                    :links="pages.links"
                    :from="pages.from"
                    :to="pages.to"
                    :total="pages.total"
                    :current-page="pages.current_page"
                    :last-page="pages.last_page"
                />
            </div>
        </section>
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
