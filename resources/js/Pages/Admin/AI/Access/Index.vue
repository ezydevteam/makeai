<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface Category {
    id: number | string
    name: string
}

interface ToolCategory {
    name?: string | null
}

interface ToolItem {
    id: number
    name: string
    access_level: string
    category?: ToolCategory | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface ToolsResponse {
    data: ToolItem[]
    links?: PaginationLink[]
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
}

interface Filters {
    category?: string | number | null
    access_level?: string | null
}

interface ConfirmModalState {
    open: boolean
    title: string
    message: string
    confirmLabel: string
    processingLabel: string
    processing: boolean
    action: null | (() => void)
}

interface PageProps {
    tools: ToolsResponse
    categories: Category[]
    filters: Filters
    globalDefault: string
}

const { t } = useTranslate()

const props = defineProps<PageProps>()

const form = useForm({
    category: props.filters.category ? String(props.filters.category) : '',
    access_level: props.filters.access_level ? String(props.filters.access_level) : '',
})

const searchQuery = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const searchFocused = ref(false)
const selectedIds = ref<number[]>([])
const bulkAction = ref<string | number | null>('')

const accessLevels = [
    { value: 'inherit', label: t('Inherit (Default)') },
    { value: 'public', label: t('Public (No Login)') },
    { value: 'login_required', label: t('Login Required') },
    { value: 'free_plan', label: t('Free Plan') },
    { value: 'pro_plan', label: t('Pro Plan') },
]

const accessFilterOptions = computed(() => [
    { value: '', label: t('All Access Levels') },
    ...accessLevels,
])

const categoryOptions = computed(() => [
    { value: '', label: t('All Categories') },
    ...props.categories.map((category) => ({
        value: String(category.id),
        label: category.name,
    })),
])

const confirmModal = ref<ConfirmModalState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    processingLabel: '',
    processing: false,
    action: null,
})

const isAllSelected = computed(() => {
    return filteredTools.value.length > 0 && filteredTools.value.every((tool) => selectedIds.value.includes(tool.id))
})

const hasActiveFilters = computed(() => searchQuery.value.trim().length > 0 || Boolean(form.category) || Boolean(form.access_level))

const toggleAll = (e: Event) => {
    const target = e.target as HTMLInputElement
    if (target.checked) {
        const visibleIds = filteredTools.value.map((tool) => tool.id)
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds]))
    } else {
        const visibleIds = new Set(filteredTools.value.map((tool) => tool.id))
        selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id))
    }
}

const getAccessLevelLabel = (level: string) => {
    return accessLevels.find((item) => item.value === level)?.label || level
}

const getAccessLevelBadgeClass = (level: string) => {
    switch (level) {
        case 'public': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        case 'login_required': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
        case 'pro_plan': return 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-300'
        case 'free_plan': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    }
}

const buildFilterPayload = () => {
    const payload: Record<string, string> = {}

    if (form.category) {
        payload.category = form.category
    }

    if (form.access_level) {
        payload.access_level = form.access_level
    }

    return payload
}

const applyFilters = () => {
    router.get(route('admin.ai.access.index'), buildFilterPayload(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const clearSearch = () => {
    searchQuery.value = ''
}

const resetFilters = () => {
    searchQuery.value = ''
    form.category = ''
    form.access_level = ''
    selectedIds.value = []
    router.get(route('admin.ai.access.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const filteredTools = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()
    return props.tools.data.filter((tool) => {
        const categoryName = tool.category?.name?.toLowerCase() ?? ''
        const matchesQuery = !query || tool.name.toLowerCase().includes(query) || categoryName.includes(query)
        const matchesAccessLevel = !form.access_level || tool.access_level === form.access_level

        return matchesQuery && matchesAccessLevel
    })
})

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName?.toLowerCase()
    const isTypingTarget = tagName === 'input' || tagName === 'textarea' || target?.isContentEditable

    if (event.key === '/' && !isTypingTarget && !confirmModal.value.open) {
        event.preventDefault()
        searchInput.value?.focus()
        return
    }

    if (event.key === 'Escape' && !confirmModal.value.open && hasActiveFilters.value) {
        searchQuery.value = ''

        if (form.category || form.access_level) {
            form.category = ''
            form.access_level = ''
            applyFilters()
        }
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

const applyBulkUpdate = () => {
    if (selectedIds.value.length === 0 || !bulkAction.value) return

    const selectedLevel = String(bulkAction.value)

    openConfirmModal({
        title: t('Apply access level?'),
        message: t('Update access level to ":level" for :count tools?', {
            level: getAccessLevelLabel(selectedLevel),
            count: selectedIds.value.length,
        }),
        confirmLabel: t('Apply'),
        processingLabel: t('Applying...'),
        action: () => {
            router.post(route('admin.ai.access.bulk'), {
                tool_ids: selectedIds.value,
                access_level: selectedLevel,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = []
                    bulkAction.value = ''
                },
                onFinish: () => {
                    closeConfirmModal(true)
                },
            })
        },
    })
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('AI Access Settings')" />

    <AdminLayout>
        <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <section class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('AI Access Settings') }}
                    </h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage visibility and access requirements for all AI tools.') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 dark:border-surface-700 dark:bg-surface-900">
                        <i class="ti ti-shield-check text-sm text-primary-600 dark:text-primary-400"></i>
                        {{ t(':count tools', { count: props.tools.total ?? props.tools.data.length }) }}
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 dark:border-surface-700 dark:bg-surface-900">
                        <i class="ti ti-adjustments text-sm text-primary-600 dark:text-primary-400"></i>
                        {{ t('Default: :level', { level: getAccessLevelLabel(props.globalDefault) }) }}
                    </span>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-surface-800">
                    <div class="relative min-w-[240px] flex-1">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                        <input
                            ref="searchInput"
                            v-model="searchQuery"
                            type="text"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:ring-2 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            :placeholder="t('Search tools...')"
                            @focus="searchFocused = true"
                            @blur="searchFocused = false"
                        />
                        <span
                            v-if="!searchQuery && !searchFocused"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                        >
                            /
                        </span>
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                            :aria-label="t('Clear search')"
                            @click="clearSearch"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>

                    <AppSelect
                        v-model="form.category"
                        :options="categoryOptions"
                        :placeholder="t('All Categories')"
                        class="w-full sm:w-56"
                        live-search
                        @update:model-value="applyFilters"
                    />

                    <AppSelect
                        v-model="form.access_level"
                        :options="accessFilterOptions"
                        :placeholder="t('All Access Levels')"
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

                    <div v-if="selectedIds.length" class="ml-auto flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ t(':count selected', { count: selectedIds.length }) }}
                        </span>
                        <AppSelect
                            v-model="bulkAction"
                            :options="accessLevels"
                            :placeholder="t('Access Level')"
                            class="w-full sm:w-52"
                        />
                        <button
                            type="button"
                            :disabled="!bulkAction || selectedIds.length === 0"
                            class="inline-flex items-center justify-center rounded-lg btn-primary px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                            :title="selectedIds.length === 0 ? t('Select at least 1 tool to apply') : ''"
                            @click="applyBulkUpdate"
                        >
                            {{ t('Apply') }}
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-surface-800 dark:bg-surface-800/50">
                                <th scope="col" class="w-4 px-4 py-3.5 text-left">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                                        :checked="isAllSelected"
                                        @change="toggleAll"
                                    />
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Tool') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Category') }}</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Current Access') }}</th>
                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                            <tr
                                v-for="tool in filteredTools"
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
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ tool.name }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-400">
                                        {{ tool.category?.name || t('Uncategorized') }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getAccessLevelBadgeClass(tool.access_level)">
                                        {{ getAccessLevelLabel(tool.access_level) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="route('admin.ai.tools.edit', tool.id)"
                                        :title="t('Edit tool')"
                                        :aria-label="t('Edit tool')"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-surface-800"
                                    >
                                        <i class="ti ti-edit text-base"></i>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="filteredTools.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search-off mx-auto mb-3 block text-4xl text-gray-300 dark:text-gray-600"></i>
                                    <p class="font-medium">{{ hasActiveFilters ? t('No tools match your filters') : t('No tools found') }}</p>
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

                <div v-if="tools.links && tools.links.length > 3 && !searchQuery" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
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
            variant="primary"
            @cancel="closeConfirmModal"
            @confirm="runConfirmedAction"
        />
    </AdminLayout>
</template>
