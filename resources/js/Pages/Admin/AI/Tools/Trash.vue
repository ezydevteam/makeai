<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useDateFormat } from '@/Composables/useDateFormat'

defineOptions({ layout: AdminLayout })

interface ToolCategory {
    id: number
    name: string
    slug: string
}

interface ToolItem {
    id: number
    name: string
    slug: string
    icon?: string | null
    color?: string | null
    is_active: boolean
    usage_count?: number | string | null
    deleted_at?: string | null
    category?: ToolCategory | string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
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
    tools: {
        data: ToolItem[]
        links: PaginationLink[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    categories: ToolCategory[]
    filters: { category?: string; search?: string; status?: string }
}>()

const search = ref(props.filters.search || '')
const selectedCategory = ref(props.filters.category || '')
const selectedStatus = ref(props.filters.status || '')
const selectedIds = ref<number[]>([])
const bulkAction = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
const { t } = useTranslate()
const { formatNumber } = useNumberFormat()
const { formatDate } = useDateFormat()

const categoryOptions = computed(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((category) => ({ value: String(category.id), label: category.name })),
])

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
])

const bulkActionOptions = computed(() => [
    { value: 'restore', label: t('Restore Selected'), icon: 'ti ti-restore', tone: 'success' as const },
    { value: 'force_delete', label: t('Delete Permanently'), icon: 'ti ti-trash-x', tone: 'danger' as const, dividerBefore: true },
])

const visibleIds = computed(() => props.tools.data.map((tool) => tool.id))
const hasActiveFilters = computed(() => Boolean(search.value || selectedCategory.value || selectedStatus.value))
const isAllSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)))

const applyFilters = () => {
    router.get(route('admin.ai.tools.trash'), {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
        status: selectedStatus.value || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

let filterTimer: ReturnType<typeof setTimeout> | null = null

const handleSearchInput = () => {
    if (filterTimer) {
        clearTimeout(filterTimer)
    }

    filterTimer = setTimeout(applyFilters, 350)
}

const clearSearch = () => {
    if (!search.value) {
        return
    }

    search.value = ''
    applyFilters()
}

const resetFilters = () => {
    search.value = ''
    selectedCategory.value = ''
    selectedStatus.value = ''
    applyFilters()
}

const toggleAll = (event: Event) => {
    const target = event.target as HTMLInputElement

    if (target.checked) {
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds.value]))
        return
    }

    const currentVisibleIds = new Set(visibleIds.value)
    selectedIds.value = selectedIds.value.filter((id) => !currentVisibleIds.has(id))
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
    if (!confirmModal.value.action) {
        return
    }

    confirmModal.value.processing = true
    confirmModal.value.action()
}

const restoreTool = (tool: ToolItem) => {
    openConfirmModal({
        title: t('Restore Tool'),
        message: t('Restore :name back to the active AI tools list?', { name: tool.name }),
        confirmLabel: t('Restore'),
        processingLabel: t('Restoring...'),
        variant: 'primary',
        action: () => {
            router.post(route('admin.ai.tools.restore', tool.id), {}, {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const forceDeleteTool = (tool: ToolItem) => {
    openConfirmModal({
        title: t('Permanently Delete Tool'),
        message: t('Permanently delete :name? This action cannot be undone.', { name: tool.name }),
        confirmLabel: t('Delete Permanently'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.ai.tools.force-delete', tool.id), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const applyBulkAction = () => {
    if (!bulkAction.value || selectedIds.value.length === 0) {
        return
    }

    const actionLabels: Record<string, string> = {
        restore: t('restore these tools'),
        force_delete: t('permanently delete these tools'),
    }

    openConfirmModal({
        title: t('Apply Bulk Action'),
        message: t('Apply this action to :count selected tools: :action?', {
            count: selectedIds.value.length,
            action: actionLabels[bulkAction.value] ?? bulkAction.value,
        }),
        confirmLabel: t('Apply'),
        processingLabel: t('Applying...'),
        variant: bulkAction.value === 'force_delete' ? 'danger' : 'primary',
        action: () => {
            router.post(route('admin.ai.tools.trash.bulk'), {
                ids: selectedIds.value,
                action: bulkAction.value,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = []
                    bulkAction.value = ''
                },
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

const handleKeydown = (event: KeyboardEvent) => {
    if (confirmModal.value.open) {
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

        resetFilters()
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)

    if (filterTimer) {
        clearTimeout(filterTimer)
    }
})
</script>

<template>
    <Head :title="t('AI Tool Trash — Admin')" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Tool Trash') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Restore deleted AI tools or permanently remove them from the platform.') }}
                </p>
            </div>

            <Link
                :href="route('admin.ai.tools.index')"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
            >
                <i class="ti ti-arrow-left"></i>
                {{ t('Back to Tools') }}
            </Link>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-surface-800">
                <div class="relative min-w-[240px] flex-1">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        :placeholder="t('Search trashed tools...')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        @input="handleSearchInput"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                    />
                    <span
                        v-if="!search && !searchFocused"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                    >
                        /
                    </span>
                    <button
                        v-if="search"
                        type="button"
                        class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                        :aria-label="t('Clear search')"
                        @click="clearSearch"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <AppSelect
                    v-model="selectedCategory"
                    :options="categoryOptions"
                    :placeholder="t('All Categories')"
                    live-search
                    class="w-full sm:w-56"
                    @update:model-value="applyFilters"
                />

                <AppSelect
                    v-model="selectedStatus"
                    :options="statusOptions"
                    :placeholder="t('All Status')"
                    class="w-full sm:w-44"
                    @update:model-value="applyFilters"
                />

                <div v-if="selectedIds.length" class="ml-auto flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t(':count selected', { count: selectedIds.length }) }}
                    </span>
                    <AppSelect
                        v-model="bulkAction"
                        :options="bulkActionOptions"
                        :placeholder="t('Bulk Actions')"
                        class="w-full sm:w-52"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg btn-primary px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!bulkAction || selectedIds.length === 0"
                        @click="applyBulkAction"
                    >
                        {{ t('Apply') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[900px] w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-800/50">
                            <th class="w-4 px-4 py-3.5 text-left">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                    :checked="isAllSelected"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Category') }}</th>
                            <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                            <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Uses') }}</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Deleted') }}</th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                        <tr
                            v-for="tool in tools.data"
                            :key="tool.id"
                            class="transition-colors hover:bg-gray-50/50 dark:hover:bg-surface-800/30"
                        >
                            <td class="w-4 px-4 py-4">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    :value="tool.id"
                                    class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                />
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        :style="{ background: tool.color || '#6366f1' }"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl text-sm text-white shadow-sm"
                                    >
                                        <i v-if="tool.icon" :class="tool.icon"></i>
                                        <span v-else class="font-bold leading-none">{{ String(tool.name || '?').charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ tool.name }}</p>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{{ tool.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    v-if="tool.category"
                                    class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-400"
                                >
                                    {{ typeof tool.category === 'object' ? tool.category.name : tool.category }}
                                </span>
                                <span v-else class="text-xs text-gray-400">{{ t('—') }}</span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="tool.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                >
                                    {{ tool.is_active ? t('Active') : t('Inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center font-medium text-gray-600 dark:text-gray-400">
                                {{ formatNumber(Number(tool.usage_count || 0)) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                {{ tool.deleted_at ? formatDate(tool.deleted_at) : t('—') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-900 dark:text-primary-400 dark:hover:bg-primary-900/20"
                                        @click="restoreTool(tool)"
                                    >
                                        <i class="ti ti-restore text-base"></i>
                                        {{ t('Restore') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/40 dark:bg-surface-900 dark:text-red-400 dark:hover:bg-red-900/20"
                                        @click="forceDeleteTool(tool)"
                                    >
                                        <i class="ti ti-trash-x text-base"></i>
                                        {{ t('Delete Permanently') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!tools.data?.length">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                <i class="ti ti-trash-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                <p class="font-medium">
                                    {{ hasActiveFilters ? t('No trashed tools match your filters') : t('No trashed tools found') }}
                                </p>
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

            <div v-if="tools.links && tools.links.length > 3" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                <Pagination
                    :links="tools.links"
                    :from="tools.from"
                    :to="tools.to"
                    :total="tools.total"
                    :current-page="tools.current_page"
                    :last-page="tools.last_page"
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
