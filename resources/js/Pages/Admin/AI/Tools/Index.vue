<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

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
    hasTrashedTools: boolean
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

const page = usePage()
const isGlobalSettingsOpen = ref(false)

const settingsForm = useForm({
    global_tools_brand_voice_enabled: true,
    global_tools_variations_enabled: true,
    global_tools_regenerate_enabled: true,
    global_tools_improve_enabled: true,
    global_tools_editor_enabled: true,
    global_tools_show_about_enabled: true,
    global_tools_show_how_it_works_enabled: true,
    global_tools_show_usage_examples_enabled: true,
    global_tools_show_faqs_enabled: true,
    global_tools_show_reviews_enabled: true,
    global_tools_embeddable_enabled: true,
})

const openGlobalSettingsModal = () => {
    const current = (page.props.globalToolSettings || {}) as Record<string, boolean>
    settingsForm.global_tools_brand_voice_enabled = current.brand_voice !== false
    settingsForm.global_tools_variations_enabled = current.variations !== false
    settingsForm.global_tools_regenerate_enabled = current.regenerate !== false
    settingsForm.global_tools_improve_enabled = current.improve !== false
    settingsForm.global_tools_editor_enabled = current.editor !== false
    settingsForm.global_tools_show_about_enabled = current.show_about !== false
    settingsForm.global_tools_show_how_it_works_enabled = current.show_how_it_works !== false
    settingsForm.global_tools_show_usage_examples_enabled = current.show_usage_examples !== false
    settingsForm.global_tools_show_faqs_enabled = current.show_faqs !== false
    settingsForm.global_tools_show_reviews_enabled = current.show_reviews !== false
    settingsForm.global_tools_embeddable_enabled = current.embeddable !== false
    isGlobalSettingsOpen.value = true
}

const saveGlobalSettings = () => {
    settingsForm.post(route('admin.ai.tools.global-settings'), {
        preserveScroll: true,
        onSuccess: () => {
            isGlobalSettingsOpen.value = false
        },
    })
}

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
    { value: 'activate', label: t('Active Selected'), icon: 'ti ti-circle-check', tone: 'success' as const },
    { value: 'deactivate', label: t('Inactive Selected'), icon: 'ti ti-alert-triangle', tone: 'warning' as const },
    { value: 'delete', label: t('Delete Selected'), icon: 'ti ti-trash', tone: 'danger' as const, dividerBefore: true },
])

const visibleIds = computed(() => props.tools.data.map((tool) => tool.id))
const hasActiveFilters = computed(() => Boolean(search.value || selectedCategory.value || selectedStatus.value))
const isAllSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selectedIds.value.includes(id)))

const applyFilters = () => {
    router.get(route('admin.ai.tools.index'), {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
        status: selectedStatus.value || undefined,
    }, { preserveScroll: true, preserveState: true, replace: true })
}

const toggleTool = (id: number) => {
    router.post(route('admin.ai.tools.toggle', id), {}, { preserveScroll: true })
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

const confirmDelete = (id: number) => {
    openConfirmModal({
        title: t('Delete Tool'),
        message: t('Move this tool to trash? You can restore it later from the trash page.'),
        confirmLabel: t('Move to Trash'),
        processingLabel: t('Moving...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.ai.tools.destroy', id), {
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
        activate: t('mark these tools as active'),
        deactivate: t('mark these tools as inactive'),
        delete: t('move these tools to trash'),
    }

    openConfirmModal({
        title: t('Apply Bulk Action'),
        message: t('Apply this action to :count selected tools: :action?', {
            count: selectedIds.value.length,
            action: actionLabels[bulkAction.value] ?? bulkAction.value,
        }),
        confirmLabel: t('Apply'),
        processingLabel: t('Applying...'),
        variant: bulkAction.value === 'delete' ? 'danger' : 'primary',
        action: () => {
            router.post(route('admin.ai.tools.bulk'), {
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
    <Head :title="t('AI Tools — Admin')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Tools') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage AI tools, prompts, and configurations.') }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row w-full sm:w-auto">
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 w-full sm:w-auto"
                    @click="openGlobalSettingsModal"
                >
                    <i class="ti ti-settings text-base"></i>
                    {{ t('Global Settings') }}
                </button>
                <Link
                    v-if="hasTrashedTools"
                    :href="route('admin.ai.tools.trash')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 w-full sm:w-auto"
                >
                    <i class="ti ti-trash text-base"></i>
                    {{ t('Trash') }}
                </Link>
                <Link
                    :href="route('admin.ai.tools.create')"
                    class="btn-primary-admin inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm text-white w-full sm:w-auto"
                >
                    <i class="ti ti-plus text-base"></i>
                    {{ t('New Tool') }}
                </Link>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex-1 min-w-[240px]">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInput"
                                v-model="search"
                                type="text"
                                :placeholder="t('Search tools...')"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                @input="handleSearchInput"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            />
                            <span
                                v-if="!search && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
                            >
                                /
                            </span>
                            <button
                                v-if="search"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                @click="clearSearch"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[200px] lg:w-56 lg:flex-none">
                            <AppSelect
                                v-model="selectedCategory"
                                :options="categoryOptions"
                                :placeholder="t('All Categories')"
                                live-search
                                @update:model-value="applyFilters"
                            />
                        </div>

                        <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                            <AppSelect
                                v-model="selectedStatus"
                                :options="statusOptions"
                                :placeholder="t('All Status')"
                                @update:model-value="applyFilters"
                            />
                        </div>

                        <template v-if="selectedIds.length">
                            <span class="text-sm text-gray-500 dark:text-gray-400 sm:whitespace-nowrap">
                                {{ t(':count selected', { count: selectedIds.length }) }}
                            </span>

                            <div class="w-full sm:flex-grow sm:flex-1 sm:min-w-[150px] lg:w-44 lg:flex-none">
                                <AppSelect
                                    v-model="bulkAction"
                                    :options="bulkActionOptions"
                                    :placeholder="t('Bulk Actions')"
                                />
                            </div>

                            <button
                                type="button"
                                class="inline-flex w-full sm:w-auto items-center justify-center btn-primary-admin px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!bulkAction || selectedIds.length === 0"
                                @click="applyBulkAction"
                            >
                                {{ t('Apply') }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-b-2xl">
                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full text-sm">
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
                                <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Category') }}</th>
                                <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                                <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Uses') }}</th>
                                <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                            <tr v-for="tool in tools.data" :key="tool.id" class="transition-colors hover:bg-gray-50/50 dark:hover:bg-surface-800/30">
                                <td class="w-4 px-4 py-4">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                        :value="tool.id"
                                        v-model="selectedIds"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-xl dark:bg-surface-800" :style="{ color: tool.color || 'var(--color-primary-500)' }">
                                            <i :class="tool.icon || 'ti ti-tool'"></i>
                                        </div>
                                        <div>
                                            <Link :href="route('admin.ai.tools.edit', tool.id)" class="font-semibold text-gray-900 transition-colors hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                                {{ tool.name }}
                                            </Link>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ tool.slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span v-if="tool.category" class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-400">
                                        {{ typeof tool.category === 'object' ? tool.category.name : tool.category }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">{{ t('—') }}</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button
                                        type="button"
                                        @click="toggleTool(tool.id)"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
                                        :class="[tool.is_active ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700']"
                                    >
                                        <span
                                            aria-hidden="true"
                                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out mt-0.5 ml-0.5"
                                            :class="[tool.is_active ? 'translate-x-4' : 'translate-x-0']"
                                        />
                                    </button>
                                </td>
                                <td class="px-4 py-4 text-center font-medium text-gray-600 dark:text-gray-400">
                                    {{ formatNumber(Number(tool.usage_count || 0)) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Tooltip :content="t('Edit tool')" placement="top">
                                            <Link :href="route('admin.ai.tools.edit', tool.id)" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20">
                                                <i class="ti ti-edit text-base"></i>
                                            </Link>
                                        </Tooltip>
                                        <Tooltip :content="t('Delete tool')" placement="top">
                                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" @click="confirmDelete(tool.id)">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </Tooltip>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!tools.data?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-table-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                    <p class="font-medium">{{ t('No tools found') }}</p>
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

    <!-- Global Settings Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isGlobalSettingsOpen" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm flex items-center justify-center p-4" @click="isGlobalSettingsOpen = false">
                <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl overflow-hidden w-full max-w-2xl max-h-[90vh] shadow-2xl flex flex-col" @click.stop>
                    <!-- Header -->
                    <div class="px-6 py-3 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between sticky top-0 bg-white dark:bg-surface-900 z-10">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Global AI Tool Settings') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ t('Override individual tool settings globally.') }}</p>
                        </div>
                        <button type="button" class="rounded-full w-8 h-8 text-gray-400 hover:bg-gray-200/30 hover:text-gray-600 dark:hover:text-white dark:hover:bg-gray-800" @click="isGlobalSettingsOpen = false">
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6 space-y-6 flex-1 overflow-y-auto">
                        <!-- Group 1: Features -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-primary-600 dark:text-primary-400 uppercase tracking-wider">{{ t('Generation Features') }}</h4>
                            <div class="rounded-xl border border-gray-100 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50 p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Supports Brand Voice') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Allows users to target brand voices.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_brand_voice_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_brand_voice_enabled = !settingsForm.global_tools_brand_voice_enabled">
                                        <span :class="settingsForm.global_tools_brand_voice_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable Variations') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Allows generating multiple output variants simultaneously.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_variations_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_variations_enabled = !settingsForm.global_tools_variations_enabled">
                                        <span :class="settingsForm.global_tools_variations_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable Regenerate Button') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Show the option to regenerate output.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_regenerate_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_regenerate_enabled = !settingsForm.global_tools_regenerate_enabled">
                                        <span :class="settingsForm.global_tools_regenerate_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable Improve Button') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Show the option to automatically improve input prompts.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_improve_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_improve_enabled = !settingsForm.global_tools_improve_enabled">
                                        <span :class="settingsForm.global_tools_improve_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Enable Edit in Editor Button') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Allows opening generated content in full document editor.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_editor_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_editor_enabled = !settingsForm.global_tools_editor_enabled">
                                        <span :class="settingsForm.global_tools_editor_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Content Sections -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-primary-600 dark:text-primary-400 uppercase tracking-wider">{{ t('Tool Details Content') }}</h4>
                            <div class="rounded-xl border border-gray-100 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50 p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show About') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display the tool description/about section.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_show_about_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_show_about_enabled = !settingsForm.global_tools_show_about_enabled">
                                        <span :class="settingsForm.global_tools_show_about_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show How It Works') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display instructions on how to use the tool.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_show_how_it_works_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_show_how_it_works_enabled = !settingsForm.global_tools_show_how_it_works_enabled">
                                        <span :class="settingsForm.global_tools_show_how_it_works_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Examples') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display usage examples.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_show_usage_examples_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_show_usage_examples_enabled = !settingsForm.global_tools_show_usage_examples_enabled">
                                        <span :class="settingsForm.global_tools_show_usage_examples_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show FAQs') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display FAQs for the tool.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_show_faqs_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_show_faqs_enabled = !settingsForm.global_tools_show_faqs_enabled">
                                        <span :class="settingsForm.global_tools_show_faqs_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 dark:border-surface-800 pt-4">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Reviews') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Display ratings and reviews section.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_show_reviews_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_show_reviews_enabled = !settingsForm.global_tools_show_reviews_enabled">
                                        <span :class="settingsForm.global_tools_show_reviews_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Group 3: Embedding -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-primary-600 dark:text-primary-400 uppercase tracking-wider">{{ t('Sharing & Embedding') }}</h4>
                            <div class="rounded-xl border border-gray-100 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-900/50 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Embeddable') }}</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Allow tools to be embedded in external websites.') }}</p>
                                    </div>
                                    <button type="button" :class="settingsForm.global_tools_embeddable_enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="settingsForm.global_tools_embeddable_enabled = !settingsForm.global_tools_embeddable_enabled">
                                        <span :class="settingsForm.global_tools_embeddable_enabled ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3 border-t border-gray-100 dark:border-surface-800 flex items-center justify-end gap-3 sticky bottom-0 bg-white dark:bg-surface-900 z-10">
                        <button type="button" class="rounded-lg border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-800" @click="isGlobalSettingsOpen = false">
                            {{ t('Cancel') }}
                        </button>
                        <button type="button" :disabled="settingsForm.processing" class="btn-primary-admin px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" @click="saveGlobalSettings">
                            {{ settingsForm.processing ? t('Saving...') : t('Save Settings') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
